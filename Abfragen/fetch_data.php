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
require_once '7_wvz_st_f.php';
require_once '8_wvz_st_f_ecum.php';
require_once '9_wvz_st_f_ecum_2020.php';
require_once '9_1_hilf_degree.php';
require_once '9_2_hilf_degree_dis.php';
require_once '10_wvz_befüllen.php';

// 4.
require_once '11_fin_wvz_promprob.php';
require_once '12_fin_wv_promprob_akad.php';
require_once '13_fin_wvz_promZdel.php';

// 5. und 6.
require_once '14_updateFakultaetPromovierende.php';
require_once '15_fin_wvz.php';

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
    
    $dataPhase1 = combineHisrmPortalData(
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
$dataPhase2 = leftjoin_info(
    $dataPhase1,
    $stichtag,
    TBL_INFO_ABTEILUNGEN,
    $rawData['pbv_lookup_data'],
    $rawData['pbl_lookup_data'],
    $rawData['pbu_lookup_data']
);

// --- 3 --- 
$dataPhase3 = aggregate_info($dataPhase2); // Ergebnis der Abfrage "mitarbeiter-pbv-distinct-a-zwT (2/6 auf der ReadMe-todo-liste)

// --- 4 ---
$dataPhase4 = filterMitPbv($dataPhase3); // Ergebnis von "mitarbeiter_pbv_distinct_b_filtered_von_zwT 

// --- 5 --- (die 5 Quellen -> Join um WVZ-Stichtag zu bekommen)

    //für lehr
    $dataPhase5_1lehr = filterLehrDt($dataPhase4); // 5_1_lehr_dt data

    $dataPhase5_2_1_lehr = aggregateAndFilterLehrDisDt($dataPhase5_1lehr); // 5_2 filter and aggregate
    $dataPhase5_2_lehr = SelectLehrDisDt($dataPhase5_2_1_lehr);                    // 5_2_lehr_dis_dt data

    //für wiss
    $dataPhase5_1_wiss = filterWissDt($dataPhase4); // 5_1_wiss_dt data

    $dataPhase5_2_wiss = WissDisDt($dataPhase5_1_wiss); // 5_2_wiss_dis_dt data

    //für sons
    $dataPhase5_1_sons = filterSonsDt($dataPhase4); // 5_1_sons_dt data

    $dataPhase5_2_sons = SonsDisDt($dataPhase5_1_sons); // 5_2_sons_dis_dt data      
    
    //für studierende
    $dataPhase5_studi = processStudierende(
        DB_CONFIG_PORTAL,
        $stichtag,
        TBL_INFO_LEHRBEREICHE,
        TBL_INFO_FACHSCHAFTEN,
        TBL_INFO_ABTEILUNGEN
    );

    //für promovierende
    $dataPhase5_promo = processPromovierende(
        DB_CONFIG_PORTAL, // Rohdaten von mannheim.wahlen2
        $stichtag,
        TBL_INFO_LEHRBEREICHE,
        TBL_INFO_FACHSCHAFTEN,
        TBL_INFO_ABTEILUNGEN
    );

// --- 6 --- liefert wvz_st

$dataPhase6 = wvz_st(
    $dataPhase5_2_lehr,
    $dataPhase5_2_wiss,
    $dataPhase5_studi,
    $dataPhase5_promo,
    $dataPhase5_2_sons
);

// --- 7 --- liefert wvz_st_form
$dataPhase7 = wvz_st_f(
    $dataPhase6,
    TBL_INFO_WÄHLENDENGRUPPE,
    TBL_INFO_ABTEILUNGEN,
    TBL_INFO_FAKULTÄTEN,
    TBL_INFO_FACHSCHAFTEN
);

// --- 8 --- liefert wvz_st_form_ecum

$dataPhase8 = wvz_st_f_ecum(
        $dataPhase7, 
        DB_CONFIG_PORTAL, 
        TBL_INFO_WÄHLENDENGRUPPE
    );

// --- 9 --- liefert wvz_st_form_ecum_2020

$dataPhase9_1 = hilf_degree(DB_CONFIG_PORTAL);

$dataPhase9_2 = hilf_degree_dis($dataPhase9_1);

$dataPhase9 = wvz_st_f_ecum_2020(
    $dataPhase8,
    DB_CONFIG_PORTAL,
    $dataPhase9_2,
    $stichtag
);
// --- 10 --- liefert wvz_befüllen
// überflüssig, da diese Abfrage nur eine neue Tabelle anlegt und nach Nachname sortiert (ist schon danach sortiert)

// --- 11 --- liefert fin_wvz_promprob
$dataPhase11 = fin_wvz_promprob(
    $dataPhase9);

// --- 12 --- liefert fin_wvz_promprob_akad
$dataPhase12 = fin_wvz_promprob_akad(
    $dataPhase9, 
    $dataPhase11
);

// --- 13 --- liefert fin_wvz_promZdel
$dataPhase13 = fin_wvz_promZdel(
    $dataPhase9, // Das zu filternde Original-Array
    $dataPhase12 // Die IDs/Kriterien zum Löschen
);

// -- 14 --- liefert FachschaftLückenFürPromovierendeFüllen
$dataPhase14 = updateFakultaetPromovierende(
    $dataPhase13);

// --- 15 --- liefert Fin_WVZ
$dataPhase15 = fin_wvz(
    $dataPhase14);

// Output
$buffered_output = ob_get_clean();

if (!empty($buffered_output)) {
    error_log("!!! Unerwartete Ausgabe VOR dem JSON-Header (HEX): " . bin2hex($buffered_output));
    error_log("!!! Unerwartete Ausgabe VOR dem JSON-Header (STRING): '" . $buffered_output . "'");
    http_response_code(500);
    echo json_encode(['error' => 'Interner Serverfehler: Unerwartete Ausgabe vor JSON-Daten. Prüfen Sie das PHP-Error-Log für Details.']);
    exit();
}

// Anzahl Datensätze ermitteln
$row_count = count($dataPhase15);

echo json_encode([
    'wvz'     => $dataPhase15,
    'rowCount' => $row_count
]);