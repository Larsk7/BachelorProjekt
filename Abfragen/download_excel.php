<?php
// error_reporting(E_ALL); // Uncomment for debugging
// ini_set('display_errors', 1); // Uncomment for debugging

require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

ob_start(); // Start output buffering

// Get data from POST request
$stichtag = $_POST['stichtag'] ?? null;
$wahl = $_POST['wahl'] ?? null;
$voterDataJson = $_POST['voter_data_json'] ?? null;

// Basic validation
if (!$stichtag || !$wahl || !$voterDataJson) {
    ob_clean();
    http_response_code(400); // Bad Request
    header('Content-Type: text/plain');
    die('Fehler: Stichtag, Wahl oder Wählerdaten fehlen im Request.');
}

// Decode the JSON data received from JavaScript
$finalesWaehlerverzeichnis = json_decode($voterDataJson, true); // true for associative array

if (json_last_error() !== JSON_ERROR_NONE) {
    ob_clean();
    http_response_code(400); // Bad Request
    header('Content-Type: text/plain');
    die('Fehler: Ungültige JSON-Daten empfangen: ' . json_last_error_msg());
}

// Ensure it's an array, even if empty
if (!is_array($finalesWaehlerverzeichnis)) {
    $finalesWaehlerverzeichnis = [];
}


// --- PhpSpreadsheet GENERATION ---

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Wählerverzeichnis');

if (!empty($finalesWaehlerverzeichnis)) {
    // Add header row
    $headerRow = array_keys($finalesWaehlerverzeichnis[0]);
    $sheet->fromArray($headerRow, NULL, 'A1');

    // Add data rows
    $sheet->fromArray($finalesWaehlerverzeichnis, NULL, 'A2');

    // Optional: Auto-size columns for better readability
    foreach (range('A', $sheet->getHighestColumn()) as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }
} else {
    // If no data, just add a message
    $sheet->setCellValue('A1', 'Keine Wähler für die ausgewählten Kriterien gefunden.');
}

// Clean any buffer content that might have been generated before this point
$buffered_output = ob_get_clean();
if (!empty($buffered_output)) {
    error_log("!!! Unerwartete Ausgabe VOR dem Excel-Download-Header (HEX): " . bin2hex($buffered_output));
    error_log("!!! Unerwartete Ausgabe VOR dem Excel-Download-Header (STRING): '" . $buffered_output . "'");
    http_response_code(500);
    die('Interner Serverfehler: Unerwartete Ausgabe vor dem Excel-Download. Bitte prüfen Sie das PHP-Error-Log.');
}

// Set headers for download
$filename = 'Waehlerverzeichnis_' . $wahl . '_' . $stichtag . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');
header('Cache-Control: max-age=1');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); // Date in the past
header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT'); // always modified
header('Cache-Control: cache, must-revalidate'); // HTTP/1.1
header('Pragma: public'); // HTTP/1.0

// Save the Excel file to output
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');

exit(); // Ensure no further code is executed
?>