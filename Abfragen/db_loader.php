<?php

function loadAllRawData(array $dbConfigHisrm, array $dbConfigPortal, string $stichtag): array {
    global $sva4_inst_lookup_map; 

    $rawData = [
        'hisrm_data' => [],
        'portal_data' => [],
        'pbv_lookup_data' => [],
        'pbl_lookup_data' => [],
        'pbu_lookup_data' => [],
    ];

    // DB-Zugriffe für SVA (Rohdaten und Hauptabfrage)
    $pdoHisrm = null;
    try {
        $dsnHisrm = "pgsql:host={$dbConfigHisrm['host']};port={$dbConfigHisrm['port']};dbname={$dbConfigHisrm['dbname']}";
        $pdoHisrm = new PDO($dsnHisrm, $dbConfigHisrm['user'], $dbConfigHisrm['password']);
        $pdoHisrm->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $rawData['pbv_lookup_data'] = $pdoHisrm->query("SELECT pbv_pgd_join_id, pbv_nr, pbv_von, pbv_bis, pbv_status FROM sva4.pbv;")->fetchAll(PDO::FETCH_ASSOC);
        $rawData['pbl_lookup_data'] = $pdoHisrm->query("SELECT pbl_pgd_join_id, pbl_pbv_nr, pbl_adt_bez, pbl_von, pbl_bis, pbl_status FROM sva4.pbl;")->fetchAll(PDO::FETCH_ASSOC);
        $rawData['pbu_lookup_data'] = $pdoHisrm->query("SELECT pbu_pgd_join_id, pbu_von, pbu_bis, pbu_status, pbu_art FROM sva4.pbu;")->fetchAll(PDO::FETCH_ASSOC);

        $sva4_inst_data = $pdoHisrm->query("SELECT inst_nr, uebinst_nr FROM sva4.inst;")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($sva4_inst_data as $inst_row) {
            $sva4_inst_lookup_map[(string)$inst_row['inst_nr']] = $inst_row;
        }
        error_log("sva4.inst Lookup Map erstellt mit " . count($sva4_inst_lookup_map) . " Einträgen.");

        // Haupt-SVA-Daten
        $stmtHisrm = $pdoHisrm->prepare(SQL_SVA);
        $stmtHisrm->bindParam(':stichtag', $stichtag);
        $stmtHisrm->execute();
        $rawData['hisrm_data'] = $stmtHisrm->fetchAll(PDO::FETCH_ASSOC);

    } finally {
        $pdoHisrm = null;
    }

    // DB-Zugriff für Portal-Daten
    $pdoPortal = null;
    try {
        $dsnPortal = "pgsql:host={$dbConfigPortal['host']};port={$dbConfigPortal['port']};dbname={$dbConfigPortal['dbname']}";
        $pdoPortal = new PDO($dsnPortal, $dbConfigPortal['user'], $dbConfigPortal['password']);
        $pdoPortal->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $stmtPortal = $pdoPortal->prepare(SQL_PORTAL);
        $stmtPortal->bindParam(':stichtag', $stichtag);
        $stmtPortal->execute();
        $rawData['portal_data'] = $stmtPortal->fetchAll(PDO::FETCH_ASSOC);

    } finally {
        $pdoPortal = null;
    }

    return $rawData;
}