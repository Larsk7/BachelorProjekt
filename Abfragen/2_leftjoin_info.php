<?php

function leftjoin_info(
    array $dataPhase1,
    string $stichtag,
    array $infoAbtTable,
    array $pbvLookupData,
    array $pblLookupData,
    array $pbuLookupData
): array {
    $infoAbtMap = create_lookup_map($infoAbtTable, 'Kennung');
    error_log("Info Abteilungen Lookup erstellt mit " . count($infoAbtMap) . " Einträgen.");

    $resultData = [];

    foreach ($dataPhase1 as $mitarbeitende) {
        $processedRow = $mitarbeitende;

        // LEFT JOIN info_abteilungen
        $bereich_kennung = $processedRow['bereich_kennung'] ?? null;
        $abteilung_info = $infoAbtMap[$bereich_kennung] ?? null;

        if ($abteilung_info) {
            $processedRow['abteilungs_name'] = $abteilung_info['name'] ?? null;
            $processedRow['abteilung_matched'] = true;
        } else {
            $processedRow['abteilungs_name'] = null;
            $processedRow['abteilung_matched'] = false;
        }

        // Zeilenweise Berechnungen (IIf, FullMonthDiff, DateAdd, Custom Functions)

        // 1. registrationnumber
        $processedRow['registrationnumber_final'] = ($processedRow['student'] == 1) ?
                                                 ($processedRow['registrationnumber'] ?? null) :
                                                 null;

        // 2. pbv_monate
        $pbv_bis_plus_1_day = php_DateAddDay($processedRow['pbv_bis'] ?? null, 1);
        $processedRow['pbv_monate'] = php_FullMonthDiff($processedRow['pbv_von'] ?? null, $pbv_bis_plus_1_day);

        // 3. pbv_sum
        $pbv_monate_val = $processedRow['pbv_monate'];
        if ($pbv_monate_val !== null && $pbv_monate_val >= 0 && $pbv_monate_val <= 5) {
            $processedRow['pbv_sum'] = php_MultiplePbvsOver6Months(
                (int)($processedRow['personalnr'] ?? 0),
                (int)($processedRow['pbv_nr'] ?? 0),
                $stichtag,
                $pbvLookupData,
                $pblLookupData
            );
        } else {
            $processedRow['pbv_sum'] = null;
        }

        // 4. pbu_monate
        $pbu_bis_plus_1_day = php_DateAddDay($processedRow['pbu_bis'] ?? null, 1);
        $processedRow['pbu_monate'] = php_FullMonthDiff($processedRow['pbu_von'] ?? null, $pbu_bis_plus_1_day);

        // 5. pbu_sum
        $pbu_monate_val = $processedRow['pbu_monate'];
        if ($pbu_monate_val !== null && $pbu_monate_val >= 0 && $pbu_monate_val <= 5) {
            $processedRow['pbu_sum'] = php_MultiplePbusOver6Months(
                (int)($processedRow['personalnr'] ?? 0),
                (string)($processedRow['pbv_nr'] ?? null), // ACHTUNG: Hier wurde pbv_nr übergeben, nicht pbu_art
                $stichtag,
                $pbuLookupData
            );
        } else {
            $processedRow['pbu_sum'] = null;
        }

        // 6. final_abteilung_id (aus GetParentAbt)
        $processedRow['final_abteilung_id'] = php_GetParentAbt($processedRow['institut'] ?? null);

        $resultData[] = $processedRow;
    }
    error_log("Nach Phase 2 (Join info_abteilungen & Zeilen-Transformationen). Anzahl Zeilen: " . count($resultData));
    return $resultData;
}