<?php

namespace App\Http\Controllers;

use App\Models\Assay;
use Illuminate\Http\Request;
use App\Exports\ResultExport;
use Maatwebsite\Excel\Facades\Excel;
use App\Collections\ResultDataCollection;

class ResultOverviewController extends Controller
{
    public function handle(Assay $assay)
    {
        return Excel::download(new ResultExport($assay), $assay->name . ".xlsx");
    }
}