<?php

namespace App\Experiments;

use App\Exceptions\ExperimentException;
use App\Models\Sample;
use App\Support\Position;
use App\Support\QPCRResultSpecifier;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Text;
use Nathanmac\Utilities\Parser\Facades\Parser;
use ZipArchive;

class QPCR extends ExperimentType
{
    public const ERROR_REPLICAS = 1;

    public const ERROR_STDDEV = 2;

    public const ERROR_REPEAT = 3;

    public const POSITIVE_CONTROL = 'pos';

    public const NEGATIVE_CONTROL = 'neg';

    public const NTC_CONTROL = 'ntc';

    public const CONTROL_IDS = [self::POSITIVE_CONTROL, self::NTC_CONTROL, self::NEGATIVE_CONTROL];

    protected $fileContents;

    protected $data;

    protected $parsedData;

    protected $ignoredSamples = [];

    /**
     * Extract the sample ids of the result file
     *
     * @return Collection
     */
    public function extractSamplesIds(): Collection
    {
        return collect($this->getData()['sample'])
            ->pluck('@id')
            ->map(
                function ($value) {
                    return strtolower($value);
                }
            )
            ->reject(
                function ($id) {
                    return in_array($id, self::CONTROL_IDS) ||
                        in_array($id, $this->ignoredSamples);
                }
            )
            ->values();
    }

    protected function getData()
    {
        if (! $this->data) {
            $data = Parser::xml($this->getFileContents());

            $this->data = $this->normalizeData($data);
        }

        return $this->data;
    }

    protected function getFileContents()
    {
        if ($this->fileContents === null) {
            if (pathinfo($this->resultFile, PATHINFO_EXTENSION) == 'xml') {
                $this->fileContents = file_get_contents($this->resultFile);
            } else {
                $zip = new ZipArchive();
                if ($zip->open($this->resultFile) !== true) {
                    $this->fileContents = "";
                } else {
                    if ($zip->numFiles > 0) {
                        $this->fileContents = $zip->getFromIndex(0);
                    } else {
                        $this->fileContents = "";
                    }
                    $zip->close();
                }
            }
        }

        return $this->fileContents;
    }

    /**
     * Validates the result file
     *
     * @throws ExperimentException
     */
    public function validate(): void
    {
        $this->assertFileNotEmpty();
        $this->assertTargetsMatchParameter();
        $this->assertControlsAreValid();
    }

    protected function assertFileNotEmpty()
    {
        if (strlen($this->getFileContents()) === 0) {
            throw new ExperimentException('Invalid .rdml file');
        }
    }

    /**
     * @throws ExperimentException
     */
    protected function assertTargetsMatchParameter()
    {
        $differences = collect($this->getData()['target'])
            ->pluck('@id')
            ->map(
                function ($value) {
                    return strtolower($value);
                }
            )
            ->diff($this->parameters->keys());
        if ($differences->count() > 0) {
            throw new ExperimentException(
                sprintf(
                    'Targets of input parameters and .rdml files do not match: %s',
                    $differences->implode(', ')
                )
            );
        }
    }

    /**
     * @throws ExperimentException
     */
    protected function assertControlsAreValid()
    {
        foreach ($this->getControlData() as $control) {
            switch (strtolower($control['sampleId'])) {
                case self::POSITIVE_CONTROL:
                    $this->assertControlIsValid(
                        $control,
                        $this->parameters[$control['target']]['posctrl'],
                        $this->parameters[$control['target']]['cutoff']
                    );
                    break;
                case self::NEGATIVE_CONTROL:
                    $this->assertControlIsValid(
                        $control,
                        $this->parameters[$control['target']]['negctrl'],
                        $this->parameters[$control['target']]['cutoff']
                    );
                    break;
                case self::NTC_CONTROL:
                    $this->assertControlIsValid(
                        $control['cq'],
                        $this->parameters[$control['target']]['ntc'],
                        $this->parameters[$control['target']]['cutoff']
                    );
                    break;
            }
        }
    }

    protected function getControlData()
    {
        $controls = $this->getParsedData()->filter(
            function ($sample) {
                return in_array($sample['sampleId'], self::CONTROL_IDS);
            }
        );

        $this->assertAllControlsExist($controls);

        return $controls;
    }

    protected function getParsedData()
    {
        if (! $this->parsedData) {
            $parsedData = [];
            foreach ($this->getData()['experiment']['run'] as $run) {
                foreach ($run['react'] as $react) {
                    if (in_array($react['sample']['@id'], $this->ignoredSamples)) {
                        continue;
                    }
                    $parsedData[] = [
                        'sampleId' => strtolower($react['sample']['@id']),
                        'target' => strtolower($react['data']['tar']['@id']),
                        'reactId' => $react['@id'],
                        'content' => collect($this->getData()['sample'])
                            ->firstWhere('@id', $react['sample']['@id'])['type'],
                        'cq' => $this->getCq($react['data'], $react['data']['tar']['@id']),
                        'position' => Position::fromPosition($react['@id'])
                            ->withColumns(12)
                            ->withRows(8)
                            ->withColumnFormat('123')
                            ->withRowFormat('ABC')
                            ->toLabel(),

                    ];
                }
            }
            $this->parsedData = collect($parsedData);
        }

        return $this->parsedData;
    }

    protected function getCq($data, $target)
    {
        return strtolower($data['cq']) == "nan" ? null : $data['cq'];
    }

    /**
     * @param Collection $controls
     * @throws ExperimentException
     */
    protected function assertAllControlsExist($controls): void
    {
        $missingControls = collect(self::CONTROL_IDS)
            ->reject(
                function ($controlId) {
                    $column = $controlId == self::NTC_CONTROL ? self::NTC_CONTROL : $controlId.'ctrl';

                    return $this->parameters->pluck($column)->filter()->isEmpty();
                }
            )
            ->diff($controls->pluck('sampleId'));

        if ($missingControls->isNotEmpty()) {
            throw new ExperimentException(
                sprintf(
                    'The following controls are missing: %s',
                    $missingControls->implode(', ')
                )
            );
        }
    }

    protected function assertControlIsValid($control, $parameter, $cutoff)
    {
        if (! $parameter) {
            return;
        }

        $cqValue = $control['cq'];

        switch (strtolower($parameter)) {
            case 'null':
                $valid = $cqValue == null;
                break;

            case 'cutoff':
                $valid = $cqValue && $cqValue <= $cutoff;
                break;

            default:
                $valid = $cqValue <= $parameter;
        }

        if (! $valid) {
            throw new ExperimentException(
                sprintf(
                    'Control %s (%s, %s) invalid',
                    $control['sampleId'],
                    $control['target'],
                    $control['position']
                )
            );
        }
    }

    /**
     * Stores the experiment results
     *
     * @param $experiment
     *
     * @return Collection
     */
    public function getDatabaseData($experiment): Collection
    {
        return $this->getResultData()->map(
            function ($data) use ($experiment) {
                return [
                    'sample' => $data['sampleId'],
                    'target' => $data['target'],
                    'primary_value' => floatval($data['cq']),
                    'secondary_value' => $data['reactId'],
                    'extra' => json_encode(
                        Arr::only(
                            array_change_key_case($data, CASE_LOWER),
                            ['reactid', 'fluor', 'content']
                        )
                    ),
                ];
            }
        );
    }

    /**
     * @return Collection
     */
    public function getResultData()
    {
        return $this->getParsedData()->reject(
            function ($sample) {
                return in_array($sample['sampleId'], self::CONTROL_IDS);
            }
        );
    }

    public function ignore(array $ignoredSamples)
    {
        $this->ignoredSamples = $ignoredSamples;
    }

    public static function indexQuery($query, $assay)
    {
        $query->select("results.*")
            ->selectRaw('avg(primary_value) as avg_cq')
            ->selectRaw('count(*) as replicas')
            ->selectRaw('stddev(primary_value) as stddev')
            ->join('result_data', 'results.id', 'result_id')
            ->where('included', true)
            ->groupBy('result_id');

        $targetPositives = $assay->definitionFile->parameters->map(
            function ($targetParameters) {
                return [
                    'sql' => '(primary_value <= ? and results.target = ?)',
                    'bindings' => [$targetParameters['cutoff'], $targetParameters['target']],
                ];
            }
        );

        $query->selectRaw(
            sprintf(
                'count(case when (%s) and primary_value <> 0 then 1 end) as positives',
                $targetPositives->pluck('sql')->implode(' or ')
            ),
            $targetPositives->pluck('bindings')->flatten()->toArray()
        );

        return $query;
    }

    public static function fields($request, $resource)
    {
        if (! $resource->assay) {
            return [];
        }

        $parameters = $resource->assay
            ->definitionFile
            ->parameters
            ->firstWhere('target', strtolower($resource->target));

        return [
            Text::make(
                'Result',
                (function () use ($parameters) {
                    return (new QPCRResultSpecifier($parameters, $this->resource))
                        ->withStyles()
                        ->qualitative();
                })->bindTo($resource)
            )->asHtml(),
            Number::make(
                'Quant',
                (function () use ($parameters) {
                    return (new QPCRResultSpecifier($parameters, $this->resource))
                        ->quantitative();
                })->bindTo($resource)
            ),
        ];
    }

    public static function exportQuery($assay, $resultIds)
    {
        return Sample::whereHas(
            'results',
            function ($query) use ($resultIds) {
                return $query->whereIn('results.id', $resultIds);
            }
        )
            ->with(
                [
                    'results' => function ($query) use ($assay, $resultIds) {
                        $query = $query
                            ->select('results.*')
                            ->selectRaw('avg(primary_value) as avg_cq')
                            ->selectRaw('count(*) as replicas')
                            ->selectRaw('stddev(primary_value) as stddev')
                            ->join('result_data', 'results.id', 'result_id')
                            ->where('included', true)
                            ->whereIn('result_id', $resultIds)
                            ->groupBy('result_id');

                        $targetPositives = $assay->definitionFile->parameters->map(
                            function ($targetParameters) {
                                return [
                                    'sql' => '(primary_value <= ? and results.target = ?)',
                                    'bindings' => [$targetParameters['cutoff'], $targetParameters['target']],
                                ];
                            }
                        );

                        $query->selectRaw(
                            sprintf(
                                'count(case when (%s) and primary_value <> 0 then 1 end) as positives',
                                $targetPositives->pluck('sql')->implode(' or ')
                            ),
                            $targetPositives->pluck('bindings')->flatten()->toArray()
                        );

                        return $query;
                    },
                    'sampleTypes' => function ($query) use ($assay) {
                        return $query->where('sample_types.id', $assay->definitionFile->sample_type_id);
                    },
                ]
            );
    }

    public static function headings($assay): array
    {
        $headings = [];

        foreach ($assay->definitionFile->parameters->pluck('target') as $target) {
            $headings[] = 'replicas_'.$target;
            $headings[] = 'mean_cq_'.$target;
            $headings[] = 'sd_cq_'.$target;
            $headings[] = 'qual_'.$target;
            $headings[] = 'quant_'.$target;
        }

        return $headings;
    }

    public static function exportMap($row, $assay)
    {
        $map = [];

        foreach ($assay->definitionFile->parameters as $targetParameters) {
            $result = $row->results->first(
                function ($result) use ($targetParameters) {
                    return strtolower($result->target) == strtolower($targetParameters['target']);
                }
            );

            if (! $result) {
                $map[] = '';
                $map[] = '';
                $map[] = '';
                $map[] = '';
                $map[] = '';
                continue;
            }

            $specifier = new QPCRResultSpecifier($targetParameters, $result);

            $map[] = $result->replicas;
            $map[] = $result->avg_cq;
            $map[] = $result->stddev;
            $map[] = $specifier->qualitative();
            $map[] = $specifier->quantitative();
        }

        return $map;
    }

    public static function primaryValue(Request $request, $parameters)
    {
        return Number::make('Primary Value')
            ->displayUsing(
                function ($value) {
                    if (! $value) {
                        return "&mdash;";
                    }

                    return number_format(round($value, 2), 2);
                }
            )
            ->asHtml();
    }

    public static function secondaryValue(Request $request, $parameters)
    {
        return Text::make('Secondary Value')
            ->displayUsing(
                function ($value) {
                    return Position::fromPosition($value)
                        ->withColumns(12)
                        ->withRows(8)
                        ->withColumnFormat('123')
                        ->withRowFormat('ABC')
                        ->toLabel();
                }
            );
    }

    /**
     * @param $data
     * @return array
     */
    protected function normalizeData($data)
    {
        if (isset($data['sample']['@id'])) {
            $data['sample'] = [$data['sample']];
        }
        if (isset($data['target']['@id'])) {
            $data['target'] = [$data['target']];
        }
        if (isset($data['experiment']['run']['@id'])) {
            $data['experiment']['run'] = [$data['experiment']['run']];
        }

        return $data;
    }
}
