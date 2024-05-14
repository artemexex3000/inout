<?php

namespace App\Services\Spreadsheet\Interfaces;

interface SpreadsheetServiceInterface
{
    /**
     * @param array $collectedData
     * @param null $userId
     * @return string|null
     */
    public function saveRecord(array $collectedData, $userId = null): ?string;
}
