<?php

namespace App\Services\Spreadsheet\Classes;

use App\Models\User;
use App\Services\Spreadsheet\ConnectSheetService;
use App\Services\Spreadsheet\Interfaces\SpreadsheetServiceInterface;
use Google\Service\Exception;

class SpreadsheetService implements SpreadsheetServiceInterface
{
    public function __construct(protected ConnectSheetService $connectSheetService)
    {
    }

    /**
     * @param array $collectedData
     * @return string|null
     */
    public function saveRecord(array $collectedData, $userId = null): ?string
    {
        $spreadsheetID = User::where('telegram_user_id', '=', $userId)->first()->table_id;

        try {
            $numberOfCell = 2;
            $range = "logging!A$numberOfCell:E$numberOfCell";

            $service = $this->connectSheetService->sheetInstance();

            while ($numberOfCell <= 1000) {
                $range = "logging!A$numberOfCell:E$numberOfCell";
                $response = $service->spreadsheets_values->get($spreadsheetID, $range)->values;

                if (empty($response)) {
                    break;
                }

                $numberOfCell++;
            }

            $values = [
                $collectedData
            ];

            $body = new \Google_Service_Sheets_ValueRange([
                'values' => $values
            ]);
            $params = [
                'valueInputOption' => 'RAW'
            ];

            $service->spreadsheets_values->update($spreadsheetID, $range, $body, $params);

            return '';
        } catch (Exception $e) {
            return $e->getMessage() . ' ' . $e->getLine() . ' ' . $e->getFile() . ' ' . $e->getCode();
        }
    }
}
