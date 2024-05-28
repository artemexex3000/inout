<?php

namespace App\Services\Spreadsheet\Classes;

use App\Models\User;
use App\Services\Spreadsheet\ConnectSheetService;
use App\Services\Spreadsheet\Interfaces\SpreadsheetServiceInterface;
use Google\Service\Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SpreadsheetService implements SpreadsheetServiceInterface
{
    public function __construct(protected ConnectSheetService $connectSheetService)
    {
    }

    /**
     * @param array $collectedData
     * @param null $userId
     * @return string|null
     */
    public function saveRecord(array $collectedData, $userId = null): ?string
    {
        $spreadsheetID = User::where('telegram_user_id', $userId)->first()->table_id;

        try {
            $service = $this->connectSheetService->sheetInstance();

            $dataFromTable = $service->spreadsheets_values->get($spreadsheetID, "Лист1!A:K")->getValues();

            end($dataFromTable);
            $numberOfCell = (int)key($dataFromTable) + 2;

            $values = [
                ['A' . $numberOfCell, $collectedData[0]],
                ['B' . $numberOfCell, $collectedData[1]],
                ['G' . $numberOfCell, $collectedData[2]],
                ['H' . $numberOfCell, (int)$collectedData[4]],
                ['I' . $numberOfCell, (int)$collectedData[3]],
            ];

            foreach ($values as $cellData) {
                $cell = $cellData[0];
                $data = $cellData[1];

                $range = "Лист1!" . $cell;

                $body = new \Google_Service_Sheets_ValueRange([
                    'values' => [[$data]]
                ]);

                $params = [
                    'valueInputOption' => 'RAW'
                ];

                $service->spreadsheets_values->append($spreadsheetID, $range, $body, $params);
            }

            return '';
        } catch (Exception $e) {
            return $e->getMessage() . ' ' . $e->getLine() . ' ' . $e->getFile() . ' ' . $e->getCode();
        }
    }

    public static function getConditionElements($userId = null)
    {
        $spreadsheetID = User::where('telegram_user_id', $userId)->first()->table_id;

        $client = new \Google_Client();

        $client->setApplicationName('Inout');
        $client->setScopes([\Google_Service_Sheets::SPREADSHEETS]);
        $client->setAccessType('offline');
        $client->setAuthConfig(base_path('credentials.json'));

        $httpClient = $client->authorize();

        $response = $httpClient->get("https://sheets.googleapis.com/v4/spreadsheets/$spreadsheetID?includeGridData=true&ranges=Лист1!G5");

        $jsonResp = json_decode((string)$response->getBody(), true, 512, JSON_THROW_ON_ERROR);

        $conditionValues = $jsonResp['sheets'][0]['data'][0]['rowData'][0]['values'][0]['dataValidation']['condition']['values'];

        return array_reduce($conditionValues, static function ($carry, $conditionValue) {
            return array_merge($carry, array_values($conditionValue));
        }, []);
    }
}
