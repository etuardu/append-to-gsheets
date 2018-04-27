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
 * Turn an array into a RowData.
 * The data is always set as string.
 * @param Array $array_values
 * @return RowData Data about each cell in a row representing the input array
 */
function arrayToRowData($array_values) {
  $values = array();
  foreach( $array_values as $d ) {
    $cellData = new Google_Service_Sheets_CellData();
    $value = new Google_Service_Sheets_ExtendedValue();
    $value->setStringValue($d);
    $cellData->setUserEnteredValue($value);
    $values[] = $cellData;
  }
  // Build the RowData
  $rowData = new Google_Service_Sheets_RowData();
  $rowData->setValues($values);
  return $rowData;
}

/**
 * Get the id of a sheet from its name.
 * @param Google_ServiceSheets $sheet_service
 * @param string $fileId The id of the spreadsheets document
 * @param string $sheetName
 * @return number The id of the sheet
 */
function getSheetIdFromName($sheet_service, $fileId, $sheetName) {
  $sheets = $sheet_service->spreadsheets->get($fileId)->sheets;
  foreach( $sheets as $sheet ) {
    if ($sheet->properties->title == $sheetName) {
      return $sheet->properties->sheetId;
    }
  }
  throw new Exception("Sheet $sheetName not found");
}

/**
 * Create a request to append a RowData into a sheet.
 * @param RowData $rowData
 * @param string $fileId
 * @param string $sheetId
 * @return Class Google_Service_Sheets_BatchUpdateSpreadsheetRequest
 */
function appendRowRequest($rowData, $fileId, $sheetId) {
  // Prepare the request
  $append_request = new Google_Service_Sheets_AppendCellsRequest();
  $append_request->setSheetId($sheetId);
  $append_request->setRows($rowData);
  $append_request->setFields('userEnteredValue');
  // Set the request
  $request = new Google_Service_Sheets_Request();
  $request->setAppendCells($append_request);
	return new Google_Service_Sheets_BatchUpdateSpreadsheetRequest(array(
		'requests' => [ $request ]
	));
}


/**
 * Add a leading apostrophe to each string in the array that would
 * be interpreted as formula in a spreadsheet
 * @param Array Array of strings
 * @return Array The array escaped
 */
function escapeFormulas($array_values) {
  foreach ($array_values as $key => $val) {
    $first_char = substr($val, 0, 1);
    if (in_array($first_char, ['=', '+'])) {
      $array_values[$key] = "'" . $val;
    }
  }
  return $array_values;
}

/**
 * Reformat the last row written in a sheet as it was user entered.
 * @param Google_ServiceSheets $sheet_service
 * @param string $fileId The Id of the spreadsheets document
 * @param string $sheetName
 */
function reformatLastRow($sheet_service, $fileId, $sheetName) {
  $result = $sheet_service->spreadsheets_values->get($fileId, $sheetName . "!A1:ZZ");
  $values = $result->getValues();
  $last_row_number = count($values);
  $array_values = $values[$last_row_number - 1];

  $array_values = escapeFormulas($array_values);

  $end_column = chr( ord('A') + count($array_values));

  $body = new Google_Service_Sheets_ValueRange([
    'values' => [ $array_values ]
  ]);
  $params = [
    'valueInputOption' => 'USER_ENTERED'
  ];

  $result = $sheet_service->spreadsheets_values->update(
    $fileId,
    $sheetName . "!A" . $last_row_number . ":" . $end_column . $last_row_number,
    $body,
    $params
  );
}

/**
 * Append an array in a sheet. All values will be strings.
 * @param Google_ServiceSheets $sheet_service
 * @param string $fileId The Id of the spreadsheets document
 * @param string $sheetName The name of the sheet
 * @param array $array_values An array containing the cell values
 */
function appendRowToSpreadsheet($sheet_service, $fileId, $sheetName, $array_values) {

  $resp = $sheet_service->spreadsheets->batchUpdate(
    $fileId,
    appendRowRequest(
      arrayToRowData($array_values),
      $fileId,
      getSheetIdFromName($sheet_service, $fileId, $sheetName)
    )
  );

}

/**
 * Append an array in a sheet and reformat the values to be
 * interpretated as user entered.
 * @param string $srvAccount
 * @param string $fileId The Id of the spreadsheets document
 * @param string $sheetName The name of the sheet
 * @param array $array_values An array containing the cell values
 */
function gsheetAppendAndAdjust($srvAccount, $fileId, $sheetName, $array_values) {

  $client = getClient($srvAccount);
  $sheet_service = new Google_Service_Sheets($client);

  appendRowToSpreadsheet($sheet_service, $fileId, $sheetName, $array_values);
  reformatLastRow($sheet_service, $fileId, $sheetName);

}

?>
