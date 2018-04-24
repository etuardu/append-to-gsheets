<?php

$GOOGLE_API4_AUTOLOAD = __DIR__ . '/google-api-php-client-2.2.1/vendor/autoload.php';
$fileId = "GOOGLE_SPREADSHEETS_ID";
$sheetName = "SHEET_NAME";
$values = ["asd", "xxx", (string) rand(1,999), "TEST", "123", "2018-04-24"];
$srv_account_json = <<<'EOT'
{
  "type": "service_account",
  "project_id": "..."
}
EOT;

// ---

include 'append-to-gsheets.php';

appendRowToSpreadsheet(
  json_decode($srv_account_json, true),
  $fileId,
  $sheetName,
  $values
);

?>
