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
require_once '4_filter_mit_pbv.php';

require_once '5_1_lehr_dt.php';
require_once '5_2_lehr_dis_dt.php';
require_once '5_1_wiss_dt.php';
require_once '5_2_wiss_dis_dt.php';
require_once '5_1_sons_dt.php';
require_once '5_2_sons_dis_dt.php';
require_once '5_studierende.php';
require_once '5_promovierende.php';

require_once '6_wvz_st.php';


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
$aggregatedData = aggregate_info($finalTransformedDataPhase2); // Ergebnis der Abfrage "mitarbeiter-pbv-distinct-a-zwT (2/6 auf der ReadMe-todo-liste)

// --- 4 ---
$dataDisBFvzwT = filterMitPbv($aggregatedData); // Ergebnis von "mitarbeiter_pbv_distinct_b_filtered_von_zwT 

// --- 5 --- (die 5 Quellen -> Join um WVZ-Stichtag zu bekommen)

    //für lehr
    $dataLehrDt = filterLehrDt($dataDisBFvzwT); // 5_1_lehr_dt data

    $filteredLehrDisDt = aggregateAndFilterLehrDisDt($dataLehrDt); // 5_2 filter and aggregate
    $dataLehrDisDt = SelectLehrDisDt($filteredLehrDisDt);                    // 5_2_lehr_dis_dt data

    //für wiss
    $dataWissDt = filterWissDt($dataDisBFvzwT); // 5_1_wiss_dt data

    $dataWissDisDt = WissDisDt($dataWissDt); // 5_2_wiss_dis_dt data

    //für sons
    $dataSonsDt = filterSonsDt($dataDisBFvzwT); // 5_1_sons_dt data

    $dataSonsDisDt = SonsDisDt($dataSonsDt); // 5_2_sons_dis_dt data      
    
    //für studierende
    $dataStudierende = processStudierende(
        DB_CONFIG_PORTAL,
        $stichtag,
        TBL_INFO_LEHRBEREICHE,
        TBL_INFO_FACHSCHAFTEN,
        TBL_INFO_ABTEILUNGEN
    );

    //für promovierende
    $dataPromovierende = processPromovierende(
        DB_CONFIG_PORTAL, // Rohdaten von mannheim.wahlen2
        $stichtag,
        TBL_INFO_LEHRBEREICHE,
        TBL_INFO_FACHSCHAFTEN,
        TBL_INFO_ABTEILUNGEN
    );

// --- 6 ---

$dataVwzSt = wvz_st(
    $dataLehrDisDt,
    $dataWissDisDt,
    $dataStudierende,
    $dataPromovierende,
    $dataSonsDisDt
);

// Output
$buffered_output = ob_get_clean();

if (!empty($buffered_output)) {
    error_log("!!! Unerwartete Ausgabe VOR dem JSON-Header (HEX): " . bin2hex($buffered_output));
    error_log("!!! Unerwartete Ausgabe VOR dem JSON-Header (STRING): '" . $buffered_output . "'");
    http_response_code(500);
    echo json_encode(['error' => 'Interner Serverfehler: Unerwartete Ausgabe vor JSON-Daten. Prüfen Sie das PHP-Error-Log für Details.']);
    exit();
}

echo json_encode($dataVwzSt);