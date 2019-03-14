<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QPCRProgram extends Model
{
    protected $table = 'qpcr_programs';

    protected $casts = [
        'program' => 'collection',
        'detection_table' => 'collection',
    ];

    public function assays()
    {
        return $this->hasMany(Assay::class, 'qpcr_program_id');
    }
}