<?php

namespace App\Http\Controllers;

use App\Services\Spreadsheet\Classes\SpreadsheetService;
use Illuminate\Http\Request;

class RowController extends Controller
{
    /**
     * @param Request $request
     * @param SpreadsheetService $storeNewRecordService
     * @return void
     */
    public function store(Request $request, SpreadsheetService $storeNewRecordService): void
    {
        $storeNewRecordService->saveRecord([
            $request->date,
            $request->name,
            $request->expense_item,
            $request->status ? $request->sum : 0,
            $request->status ? 0 : $request->sum
        ]);
    }
}
