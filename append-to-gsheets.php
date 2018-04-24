<?php

require_once $GOOGLE_API4_AUTOLOAD;

define('SCOPES', implode(' ', array(
  Google_Service_Sheets::SPREADSHEETS)
));

date_default_timezone_set('America/New_York'); // Prevent DateTime tz exception

/**
 * Returns a google API client using service account authentication.
 * Note that the service account must have rights to edit the spreadsheet.
 * To create a service account see:
 *  https://developers.google.com/api-client-library/php/auth/service-accounts#creatinganaccount
 * @param string $srvAccount Path to json file or array representing a service account
 * @return Google_Client the authorized client object
 */
function getClient($srvAccount) {

  $client = new Google_Client();
  $client->setAuthConfig($srvAccount);
  $client->setScopes(SCOPES);

  return $client;

}

/**
 * @param string $srvAccount
 * @param string $fileId The Id of the spreadsheets document
 * @param string $sheetName The name of the sheet
 * @param array $array_values An array containing the cell values
 * @return int Number of cells updated
 */
function appendRowToSpreadsheet($srvAccount, $fileId, $sheetName, $array_values) {

  $client = getClient($srvAccount);
  $sheet_service = new Google_Service_Sheets($client);

  $body = new Google_Service_Sheets_ValueRange([
    'values' => [ $array_values ]
  ]);
  $params = [
    'valueInputOption' => 'USER_ENTERED'
  ];

  // letter corresponding to the last array element index
  $end_column = chr( ord('A') + count($array_values));

  $result = $sheet_service->spreadsheets_values->append(
    $fileId,
    $sheetName . "!A1:" . $end_column . "1",
    $body,
    $params
  );

  // WARNING:
  //
  // There are two known bugs that affect this function. Please be sure to follow the instructions
  // below when designing the spreadsheet in order to get the attended results.
  //
  // 1. There must be no empty cells in the heading range (A1:<end_column>1),
  //    otherwise the new values will be shifted to the right.
  //    E.g.:
  //     _________________________________
  //    |___|__A__|__B__|__C__|__D__|__E__|
  //    | 1 |     |     | abc | def | ghi |
  //    | 2 |     |     |     |     |     |
  //
  //    appendRowToSpreadsheet( ... , ["xyz", "123"]);
  //     _________________________________
  //    |___|__A__|__B__|__C__|__D__|__E__|
  //    | 1 |     |     | abc | def | ghi |
  //    | 2 |     |     | xyz | 123 |     |
  //
  //
  // 2. There must be no empty rows within written ones (gaps),
  //    otherwise they will be filled.
  //    E.g.:
  //     _________________________________
  //    |___|__A__|__B__|__C__|__D__|__E__|
  //    | 1 | aaa | bbb | ccc | ddd | eee |
  //    | 2 |     |     |     |     |     |
  //    | 3 | fff | ggg | hhh | iii | lll |
  //    | 4 |     |     |     |     |     |
  //
  //    appendRowToSpreadsheet( ... , ["xyz", "123"]);
  //     _________________________________
  //    |___|__A__|__B__|__C__|__D__|__E__|
  //    | 1 | aaa | bbb | ccc | ddd | eee |
  //    | 2 | xyz | 123 |     |     |     |
  //    | 3 | fff | ggg | hhh | iii | lll |
  //    | 4 |     |     |     |     |     |

  return $result->getUpdates()->getUpdatedCells();

}

?>
