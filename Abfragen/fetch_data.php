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

// --- 6 --- liefert wvz_st

$dataWvzSt = wvz_st(
    $dataLehrDisDt,
    $dataWissDisDt,
    $dataStudierende,
    $dataPromovierende,
    $dataSonsDisDt
);

// --- 7 --- liefert wvz_st_form
$dataWvzStF = wvz_st_f(
    $dataWvzSt,
    TBL_INFO_WÄHLENDENGRUPPE,
    TBL_INFO_ABTEILUNGEN,
    TBL_INFO_FAKULTÄTEN,
    TBL_INFO_FACHSCHAFTEN
);

// --- 8 --- liefert wvz_st_form_ecum

$dataWvzStFEcum = wvz_st_f_ecum(
        $dataWvzStF, 
        DB_CONFIG_PORTAL, 
        TBL_INFO_WÄHLENDENGRUPPE
    );

// --- 9 --- liefert wvz_st_form_ecum_2020

$dataHilfDegree = hilf_degree(DB_CONFIG_PORTAL);

$dataHilfDegreeDis = hilf_degree_dis($dataHilfDegree);

$dataWvzStFEcum2020 = wvz_st_f_ecum_2020(
    $dataWvzStFEcum,
    DB_CONFIG_PORTAL,
    $dataHilfDegreeDis,
    $stichtag
);
// --- 10 --- liefert wvz_befüllen
// überflüssig, da diese Abfrage nur eine neue Tabelle anlegt und nach Nachname sortiert (ist schon danach sortiert)

// --- 11 --- liefert fin_wvz_promprob
$fin_wvz_promprob = fin_wvz_promprob(
    $dataWvzStFEcum2020);

// --- 12 --- liefert fin_wvz_promprob_akad
$fin_wvz_promprob_akad = fin_wvz_promprob_akad(
    $dataWvzStFEcum2020, 
    $fin_wvz_promprob
);

// --- 13 --- liefert fin_wvz_promZdel
$fin_wvz_promZdel = fin_wvz_promZdel(
    $dataWvzStFEcum2020, // Das zu filternde Original-Array
    $fin_wvz_promprob_akad // Die IDs/Kriterien zum Löschen
);

// -- 14 --- liefert FachschaftLückenFürPromovierendeFüllen
$FSLueckFuerPromFuell = updateFakultaetPromovierende(
    $fin_wvz_promZdel);

// --- 15 --- liefert Fin_WVZ
$finalesWaehlerverzeichnis = fin_wvz(
    $FSLueckFuerPromFuell);

// Output
$buffered_output = ob_get_clean();

if (!empty($buffered_output)) {
    error_log("!!! Unerwartete Ausgabe VOR dem JSON-Header (HEX): " . bin2hex($buffered_output));
    error_log("!!! Unerwartete Ausgabe VOR dem JSON-Header (STRING): '" . $buffered_output . "'");
    http_response_code(500);
    echo json_encode(['error' => 'Interner Serverfehler: Unerwartete Ausgabe vor JSON-Daten. Prüfen Sie das PHP-Error-Log für Details.']);
    exit();
}

echo json_encode($finalesWaehlerverzeichnis);