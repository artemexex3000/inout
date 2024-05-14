<?php

namespace App\Services\Spreadsheet;

use Google_Client;

class ConnectSheetService
{
    /**
     * Return sheet instance if success
     * @return string|\Google_Service_Sheets
     */
    public function sheetInstance(): string|\Google_Service_Sheets
    {
        try {
            $client = new Google_Client();

            $client->setApplicationName('Inout');
            $client->setScopes([\Google_Service_Sheets::SPREADSHEETS]);
            $client->setAccessType('offline');
            $client->setAuthConfig(base_path('credentials.json'));
            return new \Google_Service_Sheets($client);
        } catch (\Google\Exception $e) {
            return $e->getMessage() . ' ' . $e->getLine() . ' ' . $e->getFile() . ' ' . $e->getCode();
        }
    }
}
