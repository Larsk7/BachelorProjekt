<?php
ob_start();

header('Content-Type: application/json'); 
header('Access-Control-Allow-Origin: *'); 
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once '2_SQL-Abfragen.php';


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

$buffered_output = ob_get_clean();

if (!empty($buffered_output)) {
    error_log("!!! Unerwartete Ausgabe vor dem JSON-Header: '" . $buffered_output . "'");
}

echo json_encode($combined_hisrm_portal);
?>