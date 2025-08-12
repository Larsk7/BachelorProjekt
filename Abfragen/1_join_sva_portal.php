<?php

function combineHisrmPortalData(array $hisrmData, array $portalData): array {
    
    // Arrays zur Datenspeicherung der Abfragen
    $resultData = [];
    $portalLookup = [];
    $seenCombs = [];

    // Portal-Daten füllen
    foreach ($portalData as $p_row) {
        if (isset($p_row['personalnr'])) {
            $portalLookup[(string)$p_row['personalnr']] = $p_row;
        }
    }
    error_log("Portal-Lookup erstellt mit " . count($portalLookup) . " Einträgen für PHP-Join.");

    // Iteration über hisrm-Daten
    foreach ($hisrmData as $h_row) {
        $hisrmKey = (string)($h_row['hisrm_join_key_id'] ?? null);

        // Join
        if ($hisrmKey !== null && isset($portalLookup[$hisrmKey])) {
            $matchedPortalRow = $portalLookup[$hisrmKey];

            $currentPersNr = (string)($matchedPortalRow['personalnr'] ?? null);
            $currentPbvNr = (string)($h_row['pbv_nr'] ?? null);

            $compositeDisKey = $currentPersNr . '_' . $currentPbvNr;

            // Select
            if (!isset($seenCombs[$compositeDisKey])) {
                $resultData[] = [
                    'person_id' => $matchedPortalRow['person_id'] ?? null,
                    'firstname' => $matchedPortalRow['firstname'] ?? null,
                    'surname' => $matchedPortalRow['surname'] ?? null,
                    'birthdate' => $matchedPortalRow['birthdate'] ?? null,
                    'personalnr' => $currentPersNr,
                    'registrationnumber' => $matchedPortalRow['registrationnumber'] ?? null,
                    'student' => $matchedPortalRow['student'] ?? null,

                    'pbv_nr' => $currentPbvNr,
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
                $seenCombs[$compositeDisKey] = true;

                error_log("PHP-Match gefunden & Hinzugefügt (DISTINCT auf personalnr, pbv_nr): HISRM Schlüssel=" . $hisrmKey . " mit PORTAL PersonalNr=" . $hisrmKey);
            } else {
                error_log("PHP-Match gefunden, aber Kombination (personalnr: " . $currentPersNr . ", pbv_nr: " . $currentPbvNr . ") ist bereits in der Liste (DISTINCT).");
            }
        } else {
            error_log("Kein PHP-Match für HISRM Schlüssel=" . ($hisrmKey ?? 'NULL') . " in Portal-Daten gefunden.");
        }
    }
    error_log("Kombinierte Daten (nach HISRM-PORTAL-Join & Distinct) Anzahl Zeilen: " . count($resultData));
    return $resultData;
}