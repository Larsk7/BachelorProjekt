<?php

function combineHisrmPortalData(array $hisrmData, array $portalData): array {
    $combinedData = [];
    $portalLookup = [];
    $seenCombinations = [];

    foreach ($portalData as $p_row) {
        if (isset($p_row['personalnr'])) {
            $portalLookup[(string)$p_row['personalnr']] = $p_row;
        }
    }
    error_log("Portal-Lookup erstellt mit " . count($portalLookup) . " Einträgen für PHP-Join.");

    foreach ($hisrmData as $h_row) {
        $hisrmJoinKey = (string)($h_row['hisrm_join_key_id'] ?? null);

        if ($hisrmJoinKey !== null && isset($portalLookup[$hisrmJoinKey])) {
            $matchedPortalRow = $portalLookup[$hisrmJoinKey];

            $currentPersonalnr = (string)($matchedPortalRow['personalnr'] ?? null);
            $currentPbvNr = (string)($h_row['pbv_nr'] ?? null);

            $compositeDistinctKey = $currentPersonalnr . '_' . $currentPbvNr;

            if (!isset($seenCombinations[$compositeDistinctKey])) {
                $combinedData[] = [
                    'person_id' => $matchedPortalRow['person_id'] ?? null,
                    'firstname' => $matchedPortalRow['firstname'] ?? null,
                    'surname' => $matchedPortalRow['surname'] ?? null,
                    'birthdate' => $matchedPortalRow['birthdate'] ?? null,
                    'personalnr' => $currentPersonalnr,
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

                    'Quelle' => 'Kombiniert'
                ];
                $seenCombinations[$compositeDistinctKey] = true;

                error_log("PHP-Match gefunden & Hinzugefügt (DISTINCT auf personalnr, pbv_nr): HISRM Schlüssel=" . $hisrmJoinKey . " mit PORTAL PersonalNr=" . $hisrmJoinKey);
            } else {
                error_log("PHP-Match gefunden, aber Kombination (personalnr: " . $currentPersonalnr . ", pbv_nr: " . $currentPbvNr . ") ist bereits in der Liste (DISTINCT).");
            }
        } else {
            error_log("Kein PHP-Match für HISRM Schlüssel=" . ($hisrmJoinKey ?? 'NULL') . " in Portal-Daten gefunden.");
        }
    }
    error_log("Kombinierte Daten (nach HISRM-PORTAL-Join & Distinct) Anzahl Zeilen: " . count($combinedData));
    return $combinedData;
}