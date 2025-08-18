<?php

function mit_pbv_raw($dbConfSva, $dbConfPortal, $stichtag):array {

    // Unterabfrage 'hilf_stichtag_max_prio'
    try {
        $dsnSva = "pgsql:host={$dbConfSva['host']};port={$dbConfSva['port']};dbname={$dbConfSva['dbname']}";
        $dsnSva = new PDO($dsnSva, $dbConfSva['user'], $dbConfSva['password']);
        $dsnSva->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // vorbereitete SQL-Abfrage ausführen
        $stmtHilfStMaxPrio = $dsnSva->prepare(SQL_SVA2);

        // Parameter binden und ausführen
        $stmtHilfStMaxPrio->execute([
            ':stichtag' => $stichtag
        ]);

        //Ergebnis holen
        $hilfStMaxPrio = $stmtHilfStMaxPrio->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        error_log("DB Error (SVA - Hilf): " . $e->getMessage());
        throw $e;
    } finally {
        $pdoPortal = null;
    }
    error_log("hilf_st_max_prio Daten geladen. Anzahl Zeilen: " . count($hilfStMaxPrio));

    // Unterabfrage 'mannheim_wahlen_personen'
    try {
        $dsnPortal = "pgsql:host={$dbConfPortal['host']};port={$dbConfPortal['port']};dbname={$dbConfPortal['dbname']}";
        $pdoPortal = new PDO($dsnPortal, $dbConfPortal['user'], $dbConfPortal['password']);
        $pdoPortal->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // vorbereitete SQL-Abfrage ausführen
        $stmtMaWahlPers = $pdoPortal->prepare(SQL_PORTAL);

        // Parameter binden und ausführen
        $stmtMaWahlPers->execute([
            ':stichtag' => $stichtag
        ]);

        //Ergebnis holen
        $maWahlPers = $stmtMaWahlPers->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        error_log("DB Error (Portal): " . $e->getMessage());
        throw $e;
    } finally {
        $pdoPortal = null;
    }
    error_log("ma_wahl_pers Daten geladen. Anzahl Zeilen: " . count($maWahlPers));

    // Paz-Daten 
    try {
        $dsnSva = "pgsql:host={$dbConfSva['host']};port={$dbConfSva['port']};dbname={$dbConfSva['dbname']}";
        $dsnSva = new PDO($dsnSva, $dbConfSva['user'], $dbConfSva['password']);
        $dsnSva->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // SQL-Abfrage ausführen
        $stmtPaz = $dsnSva->query("SELECT paz_tz_proz, paz_pbv_nr, paz_pgd_join_id, paz_status, paz_von, paz_bis FROM sva4.paz");
        $paz = $stmtPaz->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        error_log("DB Error (SVA - paz): " . $e->getMessage());
        throw $e;
    } finally {
        $pdoPortal = null;
    }
    error_log("paz Daten geladen. Anzahl Zeilen: " . count($paz));

    // Pbv-Daten 
    try {
        $dsnSva = "pgsql:host={$dbConfSva['host']};port={$dbConfSva['port']};dbname={$dbConfSva['dbname']}";
        $dsnSva = new PDO($dsnSva, $dbConfSva['user'], $dbConfSva['password']);
        $dsnSva->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // SQL-Abfrage ausführen
        $stmtPbv = $dsnSva->query("SELECT pbv_nr, pbv_von, pbv_bis, pbv_art, pbv_pgd_join_id, pbv_status FROM sva4.pbv");
        $pbv = $stmtPbv->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        error_log("DB Error (SVA - pbv): " . $e->getMessage());
        throw $e;
    } finally {
        $pdoPortal = null;
    }
    error_log("pbv Daten geladen. Anzahl Zeilen: " . count($pbv));

    // Pbl-Daten 
    try {
        $dsnSva = "pgsql:host={$dbConfSva['host']};port={$dbConfSva['port']};dbname={$dbConfSva['dbname']}";
        $dsnSva = new PDO($dsnSva, $dbConfSva['user'], $dbConfSva['password']);
        $dsnSva->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // SQL-Abfrage ausführen
        $stmtPbl = $dsnSva->query("SELECT pbl_adt_bez, pbl_pgd_join_id, pbl_pbv_nr, pbl_status, pbl_von, pbl_bis FROM sva4.pbl");
        $pbl = $stmtPbl->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        error_log("DB Error (SVA - pbl): " . $e->getMessage());
        throw $e;
    } finally {
        $pdoPortal = null;
    }
    error_log("pbl Daten geladen. Anzahl Zeilen: " . count($pbl));

    // Pbu-Daten 
    try {
        $dsnSva = "pgsql:host={$dbConfSva['host']};port={$dbConfSva['port']};dbname={$dbConfSva['dbname']}";
        $dsnSva = new PDO($dsnSva, $dbConfSva['user'], $dbConfSva['password']);
        $dsnSva->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // SQL-Abfrage ausführen
        $stmtPbu = $dsnSva->query("SELECT pbu_art, pbu_von, pbu_bis, pbu_pgd_join_id, pbu_pbv_nr FROM sva4.pbu");
        $pbu = $stmtPbu->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        error_log("DB Error (SVA - pbl): " . $e->getMessage());
        throw $e;
    } finally {
        $pdoPortal = null;
    }
    error_log("pbu Daten geladen. Anzahl Zeilen: " . count($pbu));

    return [
        'hilfStMaxPrio' => $hilfStMaxPrio,
        'maWahlPers'    => $maWahlPers,
        'paz'           => $paz,
        'pbu'           => $pbu,
        'pbv'           => $pbv,
        'pbl'           => $pbl
    ];
}

function processMitPbvRaw(array $hilfStMaxPrio, array $maWahlPers, array $paz, array $pbv, array $pbl, array $pbu, string $stichtag): array {
    $results = [];
    $processedKeys = [];

    // Erstellen von assoziativen Maps für schnellen Zugriff
    $pazMap = createMap($paz, ['paz_pgd_join_id', 'paz_pbv_nr']);
    $pblMap = createMap($pbl, ['pbl_pgd_join_id', 'pbl_pbv_nr']);
    $pbuMap = createMap($pbu, ['pbu_pgd_join_id', 'pbu_pbv_nr']);
    $pfiMap = createMap($hilfStMaxPrio, ['pfi_pgd_join_id', 'pfi_pbv_nr']);
    $maWahlPersMap = createMap($maWahlPers, 'personalnr');

    // Filtern und Verknüpfen der Haupttabellen
    foreach ($pbv as $pbvRow) {
        $pbvPgdJoinId = $pbvRow['pbv_pgd_join_id'];
        $pbvNr = $pbvRow['pbv_nr'];

        // Haupt-WHERE-Klausel für PBV
        if (!($pbvRow['pbv_von'] <= $stichtag && ($pbvRow['pbv_bis'] === null || $pbvRow['pbv_bis'] >= $stichtag) && $pbvRow['pbv_status'] == 0)) {
            continue;
        }

        $pazKey = $pbvPgdJoinId . '_' . $pbvNr;
        $pblKey = $pbvPgdJoinId . '_' . $pbvNr;
        $pfiKey = $pbvPgdJoinId . '_' . $pbvNr;
        
        // Joins mit Pbl, Paz und Pfi (INNER JOINs)
        if (!isset($pblMap[$pblKey]) || !isset($pazMap[$pazKey]) || !isset($pfiMap[$pfiKey])) {
            continue;
        }

        $pblRow = $pblMap[$pblKey];
        $pazRow = $pazMap[$pazKey];
        $pfiRow = $pfiMap[$pfiKey];

        // WHERE-Klauseln für Pbl, Paz und Pfi
        if (!($pblRow['pbl_status'] == 0 && $pblRow['pbl_von'] <= $stichtag && ($pblRow['pbl_bis'] === null || $pblRow['pbl_bis'] >= $stichtag))) {
            continue;
        }
        if (!($pazRow['paz_status'] == 0 && $pazRow['paz_von'] <= $stichtag && ($pazRow['paz_bis'] === null || $pazRow['paz_bis'] >= $stichtag))) {
            continue;
        }
        if (!($pfiRow['pfi_status'] == 0 && $pfiRow['pfi_von'] <= $stichtag && ($pfiRow['pfi_bis'] === null || $pfiRow['pfi_bis'] >= $stichtag))) {
            continue;
        }

        // LEFT JOIN mit Pbu
        $pbuRow = null;
        if (isset($pbuMap[$pblKey])) {
            $currentPbu = $pbuMap[$pblKey];
            // Filtern der Pbu-Daten
            if ($currentPbu['pbu_von'] <= $stichtag && ($currentPbu['pbu_bis'] === null || $currentPbu['pbu_bis'] >= $stichtag)) {
                $pbuRow = $currentPbu;
            }
        }

        // JOIN mit Personen-Tabelle
        if (!isset($maWahlPersMap[$pbvPgdJoinId])) {
            continue;
        }
        $personenRow = $maWahlPersMap[$pbvPgdJoinId];

        // Duplikate vermeiden (DISTINCT)
        $uniqueKey = $personenRow['personalnr'] . '_' . $pbvNr;
        if (isset($processedKeys[$uniqueKey])) {
            continue;
        }
        $processedKeys[$uniqueKey] = true;

        // Erstellen des Ergebnis-Objekts (SELECT-Klausel)
        $resultRow = [
            'personalnr' => $personenRow['personalnr'],
            'person_id' => $personenRow['person_id'],
            'firstname' => $personenRow['firstname'],
            'surname' => $personenRow['surname'],
            'registrationnumber' => $personenRow['registrationnumber'],
            'student' => $personenRow['student'],

            'pbv_nr' => $pbvRow['pbv_nr'],
            'pbv_von' => $pbvRow['pbv_von'],
            'pbv_bis' => $pbvRow['pbv_bis'],
            'pbv_art' => $pbvRow['pbv_art'],
            'pbu_von' => $pbuRow ? $pbuRow['pbu_von'] : null,
            'pbu_bis' => $pbuRow ? $pbuRow['pbu_bis'] : null,
            'pbu_art' => $pbuRow ? $pbuRow['pbu_art'] : null,
            'proz' => $pazRow['paz_tz_proz'],
            'adbz' => $pblRow['pbl_adt_bez'],
            'institut' => $pfiRow['poz_institut'],
            'bereich_kennung' => substr($pfiRow['poz_institut'], 0, 3),
        ];

        $results[] = $resultRow;
    }

    return $results;
}

function createMap(array $data, $keyColumns): array {
    $map = [];
    foreach ($data as $row) {
        if (is_array($keyColumns)) {
            $key = '';
            foreach ($keyColumns as $col) {
                $key .= $row[$col] . '_';
            }
            $map[rtrim($key, '_')] = $row;
        } else {
            $map[$row[$keyColumns]] = $row;
        }
    }
    return $map;
}