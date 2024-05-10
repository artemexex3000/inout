<?php

namespace App\Services\Spreadsheet\Interfaces;

interface SpreadsheetServiceInterface
{
    /**
     * @param array $collectedData
     * @return string|null
     */
    public function saveRecord(array $collectedData): ?string;
}
