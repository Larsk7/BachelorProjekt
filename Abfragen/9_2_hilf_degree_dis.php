<?php

function hilf_degree_dis(array $dataPhase9_1): array {
    $groupedData = [];

    foreach ($dataPhase9_1 as $row) {
        // GROUP BY Schlüssel: id, degree_program_progress_startdate
        $group_key = implode('||', [
            (string)($row['id'] ?? ''),
            (string)($row['degree_program_progress_startdate'] ?? '')
        ]);

        if (!isset($groupedData[$group_key])) {
            // Erste Zeile für diese Gruppe: Initialisiere Aggregationen
            $groupedData[$group_key] = [
                'id' => $row['id'] ?? null,
                //'degree_program_progress_startdate' => $row['degree_program_progress_startdate'] ?? null,
                'course_of_study_longtext_last' => $row['course_of_study_longtext'] ?? null
            ];
        } else {
        }
    }

    $resultData = array_values($groupedData);
    error_log("Phase 17: Daten nach GROUP BY aggregiert. Anzahl Gruppen: " . count($resultData));

    // --- ORDER BY Klausel ---
    usort($resultData, function($a, $b) {
        // hilf_degree.id ASC
        $cmp_id = ($a['id'] ?? PHP_INT_MAX) <=> ($b['id'] ?? PHP_INT_MAX);
        if ($cmp_id !== 0) return $cmp_id;

        // hilf_degree.degree_program_progress_startdate ASC
        $dateA = $a['degree_program_progress_startdate'] ?? null;
        $dateB = $b['degree_program_progress_startdate'] ?? null;
        $cmp_startdate = 0;
        if (!is_null($dateA) && !is_null($dateB)) {
            $cmp_startdate = $dateA <=> $dateB;
        } elseif (is_null($dateA) && !is_null($dateB)) {
            $cmp_startdate = 1; // NULLs kommen nach Werten
        } elseif (!is_null($dateA) && is_null($dateB)) {
            $cmp_startdate = -1;
        }
        if ($cmp_startdate !== 0) return $cmp_startdate;

        // Last(hilf_degree.course_of_study_longtext) ASC (Sortierung nach dem aggregierten Wert)
        $cmp_longtext = ($a['course_of_study_longtext_last'] ?? '') <=> ($b['course_of_study_longtext_last'] ?? '');
        return $cmp_longtext;
    });
    error_log("Phase 17: Finale Daten nach ORDER BY sortiert. Finale Zeilen: " . count($resultData));

    return $resultData;
}