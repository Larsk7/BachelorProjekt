<?php

function aggregate_info(array $transformedDataPhase2): array {
    $groupedFinalData = [];

    foreach ($transformedDataPhase2 as $row) {
        // Definieren des Group By Schlüssels - alle Nicht-Aggregat-Spalten
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

        if (!isset($groupedFinalData[$group_key])) {
            $groupedFinalData[$group_key] = $row;
            // Initialisiere Aggregationsfelder
            $groupedFinalData[$group_key]['institut'] = $row['institut'] ?? null;
            $groupedFinalData[$group_key]['abteilung'] = $row['abteilungs_name'] ?? null;
        } else {
            // Aggregation: Finde das Minimum für 'institut'
            if (($row['institut'] ?? null) !== null) {
                if ($groupedFinalData[$group_key]['institut'] === null || (string)($row['institut']) < (string)($groupedFinalData[$group_key]['institut'])) {
                    $groupedFinalData[$group_key]['institut'] = $row['institut'];
                }
            }
            // Aggregation: Finde das Minimum für 'abteilung'
            if (($row['abteilungs_name'] ?? null) !== null) {
                if ($groupedFinalData[$group_key]['abteilung'] === null || (string)($row['abteilungs_name']) < (string)($groupedFinalData[$group_key]['abteilung'])) {
                    $groupedFinalData[$group_key]['abteilung'] = $row['abteilungs_name'];
                }
            }
        }
    }

    $finalOutputData = array_values($groupedFinalData);
    error_log("Endgültiges Endergebnis nach Aggregation. Anzahl Zeilen: " . count($finalOutputData));
    return $finalOutputData;
}