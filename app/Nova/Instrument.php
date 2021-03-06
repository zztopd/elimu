<?php

namespace App\Nova;

use Illuminate\Http\Request;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Text;
use Maatwebsite\LaravelNovaExcel\Actions\DownloadExcel;
use Sparclex\NovaCreatableBelongsTo\CreatableBelongsTo;

class Instrument extends Resource
{
    /**
     * The model the resource corresponds to.
     *
     * @var string
     */
    public static $model = 'App\Models\Instrument';

    /**
     * The single value that should be used to represent the resource when being displayed.
     *
     * @var string
     */
    public static $title = 'instrument_id';
    /**
     * The columns that should be searched.
     *
     * @var array
     */
    public static $search = [
        'instrument_id',
        'name',
        'serial_number'
    ];

    public function subtitle()
    {
        return sprintf('%s (%s)', $this->name, $this->serial_number);
    }

    /**
     * Get the fields displayed by the resource.
     *
     * @param  \Illuminate\Http\Request $request
     * @return array
     */
    public function fields(Request $request)
    {
        return [
            ID::make()
                ->sortable()
                ->onlyOnForms(),
            Text::make('Instrument ID')
                ->rules('required', 'unique:instruments,instrument_id,{{resourceId}}'),
            Text::make('Name')
                ->rules('required'),
            Text::make('Serial Number'),
            CreatableBelongsTo::make('Responsible', 'responsible', Person::class),
            CreatableBelongsTo::make('Institution')->hideFromIndex(),
            CreatableBelongsTo::make('Laboratory'),
        ];
    }

    /**
     * Get the cards available for the request.
     *
     * @param  \Illuminate\Http\Request $request
     * @return array
     */
    public function cards(Request $request)
    {
        return [];
    }

    /**
     * Get the filters available for the resource.
     *
     * @param  \Illuminate\Http\Request $request
     * @return array
     */
    public function filters(Request $request)
    {
        return [];
    }

    /**
     * Get the lenses available for the resource.
     *
     * @param  \Illuminate\Http\Request $request
     * @return array
     */
    public function lenses(Request $request)
    {
        return [];
    }

    /**
     * Get the actions available for the resource.
     *
     * @param  \Illuminate\Http\Request $request
     * @return array
     */
    public function actions(Request $request)
    {
        return [
            (new DownloadExcel)->withHeadings()->allFields(),
        ];
    }
}
