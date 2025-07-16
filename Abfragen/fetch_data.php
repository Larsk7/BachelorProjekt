<?php
ob_start(); 

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); 
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once 'config.php';
require_once '../Funktionen.php';
require_once '../Info_Tabellen.php';
require_once  '../SQL_Abfragen.php';

require_once 'db_loader.php';
require_once '1_join_sva_portal.php';
require_once '2_leftjoin_info.php';
require_once '3_aggregate_info.php';

$stichtag = $_POST['stichtag'] ?? null;
$wahl = $_POST['wahl'] ?? null;

if (!$stichtag || !$wahl) {
    ob_clean();
    echo json_encode(['error' => 'Missing Stichtag or Wahl parameter.']);
    exit();
}

// --- 1 ---
try {
    $rawData = loadAllRawData(DB_CONFIG_HISRM, DB_CONFIG_PORTAL, $stichtag);
    
    $combinedHisrmPortal = combineHisrmPortalData(
        $rawData['hisrm_data'],
        $rawData['portal_data']
    );

} catch (PDOException $e) {
    error_log("DB Access Error: " . $e->getMessage());
    ob_clean();
    echo json_encode(['error' => 'Datenbankfehler beim Laden der Rohdaten: ' . $e->getMessage()]);
    exit();
}

// --- 2 ---
$finalTransformedDataPhase2 = leftjoin_info(
    $combinedHisrmPortal,
    $stichtag,
    TBL_INFO_ABTEILUNGEN,
    $rawData['pbv_lookup_data'],
    $rawData['pbl_lookup_data'],
    $rawData['pbu_lookup_data']
);

// --- 3 ---
$finalOutputData = aggregate_info($finalTransformedDataPhase2);

// Output
$buffered_output = ob_get_clean();

if (!empty($buffered_output)) {
    error_log("!!! Unerwartete Ausgabe VOR dem JSON-Header (HEX): " . bin2hex($buffered_output));
    error_log("!!! Unerwartete Ausgabe VOR dem JSON-Header (STRING): '" . $buffered_output . "'");
    http_response_code(500);
    echo json_encode(['error' => 'Interner Serverfehler: Unerwartete Ausgabe vor JSON-Daten. Prüfen Sie das PHP-Error-Log für Details.']);
    exit();
}

echo json_encode($finalOutputData);