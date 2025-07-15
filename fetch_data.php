<?php
header('Content-Type: application/json'); // Tell the browser to expect JSON
header('Access-Control-Allow-Origin: *'); // IMPORTANT for local development with different ports/origins. Remove or restrict this in production!
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// --- 1. Get parameters from the frontend (if any) ---
// For a POST request (recommended for data submission)
$stichtag = $_POST['stichtag'] ?? null;
$wahl = $_POST['wahl'] ?? null;

// For a GET request (simpler for initial testing, but less secure for sensitive data)
// $stichtag = $_GET['stichtag'] ?? null;
// $wahl = $_GET['wahl'] ?? null;

// Validate parameters if necessary
if (!$stichtag || !$wahl) {
    echo json_encode(['error' => 'Missing Stichtag or Wahl parameter.']);
    exit();
}

// --- 2. Database Configuration (REPLACE WITH YOUR ACTUAL CREDENTIALS AND TUNNEL SETUP) ---
$db_config_hisrm = [
    'host'     => 'localhost', // Or the IP your SSH tunnel binds to
    'port'     => '33008',
    'dbname'   => 'hisrm',
    'user'     => 'wahlenlesen', // Replace with your actual user
    'password' => '6e354d16e4', // Replace with your actual password
];

$db_config_portal = [
    'host'     => 'localhost', // Or the IP your SSH tunnel binds to
    'port'     => '33007',
    'dbname'   => 'portal2_test_202412',
    'user'     => 'wahlenlesen', // Replace with your actual user
    'password' => '6e354d16e4', // Replace with your actual password
];

$hisrm_data = [];
$portal_data = [];
$combined_voter_list = []; // This will hold your final combined data

// --- 3. Connect to hisrm and fetch data ---
try {
    $dsn_hisrm = "pgsql:host={$db_config_hisrm['host']};port={$db_config_hisrm['port']};dbname={$db_config_hisrm['dbname']}";
    $pdo_hisrm = new PDO($dsn_hisrm, $db_config_hisrm['user'], $db_config_hisrm['password']);
    $pdo_hisrm->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Example query for 'hisrm'. Adjust based on your schema and logic.
    // Use prepared statements for parameters to prevent SQL injection!
    $stmt_hisrm = $pdo_hisrm->prepare("SELECT inst_nr FROM sva4.inst LIMIT 50");
    //$stmt_hisrm->bindParam(':stichtag', $stichtag);
    $stmt_hisrm->execute();
    $hisrm_data = $stmt_hisrm->fetchAll(PDO::FETCH_ASSOC);

    error_log("hisrm_data Anzahl Zeilen: " . count($hisrm_data));
if (count($hisrm_data) > 0) {
    error_log("hisrm_data Beispiel (erste Zeile): " . json_encode($hisrm_data[0]));
} else {
    error_log("hisrm_data ist leer!");
}

} catch (PDOException $e) {
    error_log("hisrm DB Error: " . $e->getMessage()); // Log error to server error log
    echo json_encode(['error' => 'Fehler bei der hisrm-Datenbankverbindung oder Abfrage. Details: ' . $e->getMessage()]);
    exit();
} finally {
    $pdo_hisrm = null; // Close connection
}

// --- 4. Connect to portal2_test_202412 and fetch data ---
try {
    $dsn_portal = "pgsql:host={$db_config_portal['host']};port={$db_config_portal['port']};dbname={$db_config_portal['dbname']}";
    $pdo_portal = new PDO($dsn_portal, $db_config_portal['user'], $db_config_portal['password']);
    $pdo_portal->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Example query for 'portal2_test_202412'. Adjust based on your schema and view.
    // Again, use prepared statements for parameters.
    // The 'wahl' parameter might influence which view or data is pulled.
    $stmt_portal = $pdo_portal->prepare("SELECT personalnr FROM mannheim.wahlen LIMIT 50"); // Replace <your_view_name>
    //$stmt_portal->bindParam(':wahl', $wahl);
    $stmt_portal->execute();
    $portal_data = $stmt_portal->fetchAll(PDO::FETCH_ASSOC);

    error_log("portal_data Anzahl Zeilen: " . count($portal_data));
if (count($portal_data) > 0) {
    error_log("portal_data Beispiel (erste Zeile): " . json_encode($portal_data[0]));
} else {
    error_log("portal_data ist leer!");
}

} catch (PDOException $e) {
    error_log("Portal DB Error: " . $e->getMessage()); // Log error to server error log
    echo json_encode(['error' => 'Fehler bei der portal2_test_202412-Datenbankverbindung oder Abfrage. Details: ' . $e->getMessage()]);
    exit();
} finally {
    $pdo_portal = null; // Close connection
}

// --- 5. Combine and process data in PHP ---

// 1. Extrahieren Sie nur die 'inst_nr' Werte aus hisrm_data
$hisrm_inst_nrs = [];
foreach ($hisrm_data as $row) {
    if (isset($row['inst_nr'])) {
        $hisrm_inst_nrs[] = $row['inst_nr'];
    }
}
error_log("Extrahierte HISRM Inst Nrs (erste 5): " . json_encode(array_slice($hisrm_inst_nrs, 0, 5)));


// 2. Extrahieren Sie nur die 'personalnr' Werte aus portal_data
$portal_personalnrs = [];
foreach ($portal_data as $row) {
    if (isset($row['personalnr'])) {
        $portal_personalnrs[] = $row['personalnr'];
    }
}
error_log("Extrahierte PORTAL Personal Nrs (erste 5): " . json_encode(array_slice($portal_personalnrs, 0, 5)));


// 3. Fügen Sie die beiden Listen einfach zusammen
// Die erste Liste ($hisrm_inst_nrs) wird die zweite Liste ($portal_personalnrs) angehängt.
$combined_voter_list = array_merge($hisrm_inst_nrs, $portal_personalnrs);

error_log("Kombinierte Daten (combined_voter_list) Anzahl Zeilen: " . count($combined_voter_list));
if (count($combined_voter_list) > 0) {
    error_log("Kombinierte Daten Beispiel (erste 5): " . json_encode(array_slice($combined_voter_list, 0, 5)));
} else {
    error_log("Kombinierte Datenliste ist leer, obwohl einzelne Abfragen Daten lieferten. Problem beim Extrahieren oder Mergen.");
}


// --- 6. Return combined data as JSON ---
echo json_encode($combined_voter_list);
?>