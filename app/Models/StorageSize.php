<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class StorageSize extends Model
{
    protected $fillable = ['sample_type_id', 'study_id', 'size'];

    public function study() {
        return $this->belongsTo(Study::class);
    }

    public function sampleType() {
        return $this->belongsTo(SampleType::class);
    }

    public static function sizeFor($study_id, $sample_type_id) {
        $size = self::where('study_id', $study_id)
            ->where('sample_type_id', $sample_type_id)
            ->first(['size']);

        return $size ? $size->size : null;
    }

    public static function sampleTypesFor($study_id) {
        $storageSizes = self::where('study_id', $study_id)
            ->get();
        return $storageSizes;
        if(count($names)) {
            return $names->names;
        }
        return new Collection();
    }
}