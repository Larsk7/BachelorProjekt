<?php
ob_start();

header('Content-Type: application/json'); 
header('Access-Control-Allow-Origin: *'); 
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once '2_SQL-Abfragen.php';
require_once 'Info_Tabellen.php';
require_once 'funktionen.php';

$stichtag = $_POST['stichtag'] ?? null;
$wahl = $_POST['wahl'] ?? null;

if (!$stichtag || !$wahl) {
    echo json_encode(['error' => 'Missing Stichtag or Wahl parameter.']);
    exit();
}

$db_config_hisrm = [
    'host'     => 'localhost', 
    'port'     => '33008',
    'dbname'   => 'hisrm',
    'user'     => 'wahlenlesen',
    'password' => '6e354d16e4', 
];

$db_config_portal = [
    'host'     => 'localhost', 
    'port'     => '33007',
    'dbname'   => 'portal2_test_202412',
    'user'     => 'wahlenlesen', 
    'password' => '6e354d16e4',
];

$hisrm_data = [];
$portal_data = [];
$pbv_lookup_data = [];
$pbl_lookup_data = []; 
$pbu_lookup_data = []; 

//---------------------------------------------------
//DB-Zugriffe für Hilfsfunktionen
try {
    $dsn_hisrm = "pgsql:host={$db_config_hisrm['host']};port={$db_config_hisrm['port']};dbname={$db_config_hisrm['dbname']}";
    $pdo_hisrm_raw = new PDO($dsn_hisrm, $db_config_hisrm['user'], $db_config_hisrm['password']);
    $pdo_hisrm_raw->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt_pbv = $pdo_hisrm_raw->query("SELECT pbv_pgd_join_id, pbv_nr, pbv_von, pbv_bis, pbv_status FROM sva4.pbv;");
    $pbv_lookup_data = $stmt_pbv->fetchAll(PDO::FETCH_ASSOC);

    $stmt_pbl = $pdo_hisrm_raw->query("SELECT pbl_pgd_join_id, pbl_pbv_nr, pbl_adt_bez, pbl_von, pbl_bis, pbl_status FROM sva4.pbl;");
    $pbl_lookup_data = $stmt_pbl->fetchAll(PDO::FETCH_ASSOC);

    $stmt_pbu = $pdo_hisrm_raw->query("SELECT pbu_pgd_join_id, pbu_von, pbu_bis, pbu_status, pbu_art FROM sva4.pbu;");
    $pbu_lookup_data = $stmt_pbu->fetchAll(PDO::FETCH_ASSOC);

    $stmt_inst = $pdo_hisrm_raw->query("SELECT inst_nr, uebinst_nr FROM sva4.inst;");
    $sva4_inst_data = $stmt_inst->fetchAll(PDO::FETCH_ASSOC);
    foreach ($sva4_inst_data as $inst_row) {
        $sva4_inst_lookup_map[(string)$inst_row['inst_nr']] = $inst_row;
    }
    error_log("sva4.inst Lookup Map erstellt mit " . count($sva4_inst_lookup_map) . " Einträgen.");

} catch (PDOException $e) {
    error_log("hisrm DB Error (Rohdaten oder Inst-Lookup): " . $e->getMessage());
    ob_clean();
    echo json_encode(['error' => 'Fehler beim Laden von Rohdaten oder Instanz-Lookups: ' . $e->getMessage()]);
    exit();
} finally {
    $pdo_hisrm_raw = null;
}

//SVA: DB-Zugriff
try {
    $dsn_hisrm = "pgsql:host={$db_config_hisrm['host']};port={$db_config_hisrm['port']};dbname={$db_config_hisrm['dbname']}";
    $pdo_hisrm = new PDO($dsn_hisrm, $db_config_hisrm['user'], $db_config_hisrm['password']);
    $pdo_hisrm->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt_hisrm = $pdo_hisrm->prepare(SQL_SVA);
    $stmt_hisrm->bindParam(':stichtag', $stichtag);
    $stmt_hisrm->execute();
    $hisrm_data = $stmt_hisrm->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("hisrm DB Error: " . $e->getMessage()); 
    echo json_encode(['error' => 'Fehler bei der hisrm-Datenbankverbindung oder Abfrage. Details: ' . $e->getMessage()]);
    exit();
} finally {
    $pdo_hisrm = null; // Close connection
}

//Portal2: DB-Zugriff
try {
    $dsn_portal = "pgsql:host={$db_config_portal['host']};port={$db_config_portal['port']};dbname={$db_config_portal['dbname']}";
    $pdo_portal = new PDO($dsn_portal, $db_config_portal['user'], $db_config_portal['password']);
    $pdo_portal->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt_portal = $pdo_portal->prepare(SQL_PORTAL);
    $stmt_portal->bindParam(':stichtag', $stichtag);
    $stmt_portal->execute();
    $portal_data = $stmt_portal->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Portal DB Error: " . $e->getMessage());
    echo json_encode(['error' => 'Fehler bei der portal2_test_202412-Datenbankverbindung oder Abfrage. Details: ' . $e->getMessage()]);
    exit();
} finally {
    $pdo_portal = null; // Close connection
}

//---------------------------------------------------
// JOIN der Zwischentabellen der beiden Datenbanken 
$combined_hisrm_portal = [];
$portal_lookup = [];
$seen_person_pbv_combinations = [];

foreach ($portal_data as $p_row) {
    if (isset($p_row['personalnr'])) {
        $portal_lookup[$p_row['personalnr']] = $p_row;
    }
}
error_log("Portal-Lookup erstellt mit " . count($portal_lookup) . " Einträgen für PHP-Join.");

foreach ($hisrm_data as $h_row) {
    $hisrm_join_key = $h_row['hisrm_join_key_id'] ?? null;

    if ($hisrm_join_key !== null && isset($portal_lookup[$hisrm_join_key])) {
        $matched_portal_row = $portal_lookup[$hisrm_join_key];

        $current_personalnr = $matched_portal_row['personalnr'] ?? null;
        $current_pbv_nr = $h_row['pbv_nr'] ?? null;

        $composite_distinct_key = $current_personalnr . '_' . $current_pbv_nr;

        if (!isset($seen_person_pbv_combinations[$composite_distinct_key])) {
            $combined_hisrm_portal[] = [
                'person_id' => $matched_portal_row['person_id'] ?? null,
                'firstname' => $matched_portal_row['firstname'] ?? null,
                'surname' => $matched_portal_row['surname'] ?? null,
                'birthdate' => $matched_portal_row['birthdate'] ?? null,
                'personalnr' => $current_personalnr,
                'registrationnumber' => $matched_portal_row['registrationnumber'] ?? null,
                'student' => $matched_portal_row['student'] ?? null,

                'pbv_nr' => $current_pbv_nr,
                'pbv_von' => $h_row['pbv_von'] ?? null,
                'pbv_bis' => $h_row['pbv_bis'] ?? null,
                'pbv_art' => $h_row['pbv_art'] ?? null,
                'pbu_von' => $h_row['pbu_von'] ?? null,
                'pbu_bis' => $h_row['pbu_bis'] ?? null,
                'pbu_art' => $h_row['pbu_art'] ?? null,
                'proz' => $h_row['proz'] ?? null,
                'adbz' => $h_row['adbz'] ?? null,
                'institut' => $h_row['institut'] ?? null,
                'bereich_kennung' => $h_row['bereich_kennung'] ?? null,
            ];
            $seen_person_pbv_combinations[$composite_distinct_key] = true;

            error_log("PHP-Match gefunden & Hinzugefügt (DISTINCT auf person_id, pbv_nr): HISRM Schlüssel=" . $hisrm_join_key . " mit PORTAL PersonalNr=" . $hisrm_join_key);
        } else {
            error_log("PHP-Match gefunden, aber Kombination (person_id: " . $current_personalnr . ", pbv_nr: " . $current_pbv_nr . ") ist bereits in der Liste (DISTINCT).");
        }
    } else {
        error_log("Kein PHP-Match für HISRM Schlüssel=" . ($hisrm_join_key ?? 'NULL') . " in Portal-Daten gefunden.");
    }
}
error_log("Kombinierte Daten (final - DISTINCT auf person_id, pbv_nr) Anzahl Zeilen: " . count($combined_hisrm_portal));
if (count($combined_hisrm_portal) > 0) {
    error_log("Erste kombinierte Zeile (Beispiel - DISTINCT auf person_id, pbv_nr): " . json_encode($combined_hisrm_portal[0]));
} else {
    error_log("Kombinierte Liste ist leer nach PHP-Join und DISTINCT! Prüfen Sie die Join-Schlüssel und Daten.");
}

//---------------------------------------------------
//LEFT JOIN mit Info_Abteilungen
$info_abteilungen_map = create_lookup_map(TBL_INFO_ABTEILUNGEN, 'Kennung');
error_log("Info Abteilungen Lookup erstellt mit " . count($info_abteilungen_map) . " Einträgen.");

$final_transformed_data_phase2 = []; 

foreach ($combined_hisrm_portal as $mitarbeitende) {
    $processed_row = $mitarbeitende;

    $bereich_kennung = $processed_row['bereich_kennung'] ?? null;
    $abteilung_info = $info_abteilungen_map[$bereich_kennung] ?? null; 

    if ($abteilung_info) {
        $processed_row['abteilungs_name'] = $abteilung_info['name'] ?? null;
    } else {
        $processed_row['abteilungs_name'] = null; 
    }

    $processed_row['registrationnumber_final'] = ($processed_row['student'] == 1) ?
                                             ($processed_row['registrationnumber'] ?? null) :
                                             null;

    $pbv_bis_plus_1_day = php_DateAddDay($processed_row['pbv_bis'] ?? null, 1);
    $processed_row['pbv_monate'] = php_FullMonthDiff($processed_row['pbv_von'] ?? null, $pbv_bis_plus_1_day);

    $pbv_monate_val = $processed_row['pbv_monate'];
    if ($pbv_monate_val !== null && $pbv_monate_val >= 0 && $pbv_monate_val <= 5) {
        $processed_row['pbv_sum'] = php_MultiplePbvsOver6Months(
            (int)($processed_row['personalnr'] ?? 0), 
            (int)($processed_row['pbv_nr'] ?? 0),   
            $stichtag,
            $pbv_lookup_data,
            $pbl_lookup_data
        );
    } else {
        $processed_row['pbv_sum'] = null;
    }

    $pbu_bis_plus_1_day = php_DateAddDay($processed_row['pbu_bis'] ?? null, 1);
    $processed_row['pbu_monate'] = php_FullMonthDiff($processed_row['pbu_von'] ?? null, $pbu_bis_plus_1_day);

    $pbu_monate_val = $processed_row['pbu_monate'];
    if ($pbu_monate_val !== null && $pbu_monate_val >= 0 && $pbu_monate_val <= 5) {
        $processed_row['pbu_sum'] = php_MultiplePbusOver6Months(
            (int)($processed_row['personalnr'] ?? 0),
            (string)($processed_row['pbv_nr'] ?? null),
            $stichtag,
            $pbu_lookup_data
        );
    } else {
        $processed_row['pbu_sum'] = null;
    }

    $processed_row['final_abteilung_id'] = php_GetParentAbt($processed_row['institut'] ?? null);


    $final_transformed_data_phase2[] = $processed_row;
}
error_log("Nach Phase 2 (Join info_abteilungen & Zeilen-Transformationen). Anzahl Zeilen: " . count($final_transformed_data_phase2));

$grouped_final_data = [];

foreach ($final_transformed_data_phase2 as $row) {
    $group_key = implode('||', [
        (string)($row['person_id'] ?? ''),
        (string)($row['firstname'] ?? ''),
        (string)($row['surname'] ?? ''),
        (string)($row['personalnr'] ?? ''),
        (string)($row['student'] ?? ''),
        (string)($row['registrationnumber_final'] ?? ''),
        (string)($row['pbv_nr'] ?? ''),
        (string)($row['pbv_von'] ?? ''),
        (string)($row['pbv_bis'] ?? ''),
        (string)($row['pbv_art'] ?? ''),
        (string)($row['pbv_monate'] ?? ''), 
        (string)($row['pbv_sum'] ?? ''), 
        (string)($row['pbu_von'] ?? ''),
        (string)($row['pbu_bis'] ?? ''),
        (string)($row['pbu_monate'] ?? ''), 
        (string)($row['pbu_sum'] ?? ''), 
        (string)($row['pbu_art'] ?? ''),
        (string)($row['proz'] ?? ''),
        (string)($row['adbz'] ?? '')
    ]);

    if (!isset($grouped_final_data[$group_key])) {
        $grouped_final_data[$group_key] = $row;
        $grouped_final_data[$group_key]['institut'] = $row['institut'] ?? null;
        $grouped_final_data[$group_key]['abteilung'] = $row['abteilungs_name'] ?? null; 
    } else {
        if (($row['institut'] ?? null) !== null) {
            if ($grouped_final_data[$group_key]['institut'] === null || (string)($row['institut']) < (string)($grouped_final_data[$group_key]['institut'])) {
                $grouped_final_data[$group_key]['institut'] = $row['institut'];
            }
        }
        if (($row['abteilungs_name'] ?? null) !== null) {
            if ($grouped_final_data[$group_key]['abteilung'] === null || (string)($row['abteilungs_name']) < (string)($grouped_final_data[$group_key]['abteilung'])) {
                $grouped_final_data[$group_key]['abteilung'] = $row['abteilungs_name'];
            }
        }
    }
}

$final_output_data = array_values($grouped_final_data); 


error_log("Endgültiges Endergebnis nach Aggregation. Anzahl Zeilen: " . count($final_output_data));
if (count($final_output_data) > 0) {
    error_log("Beispiel erste Zeile (final): " . json_encode($final_output_data[0]));
} else {
    error_log("Finale Liste ist leer nach Aggregation! Prüfen Sie die Daten.");
}


//---------------------------------------------------
// Output Buffering
$buffered_output = ob_get_clean(); 
if (!empty($buffered_output)) {
    error_log("!!! Unerwartete Ausgabe VOR dem JSON-Header (HEX): " . bin2hex($buffered_output));
    error_log("!!! Unerwartete Ausgabe VOR dem JSON-Header (STRING): '" . $buffered_output . "'");

    http_response_code(500); 
    echo json_encode(['error' => 'Interner Serverfehler: Unerwartete Ausgabe vor JSON-Daten. Prüfen Sie das PHP-Error-Log für Details.']);
    exit(); 
}
//Ausgabe
 echo json_encode($final_output_data);

?>