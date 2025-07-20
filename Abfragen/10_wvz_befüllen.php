<?php
function NachnameSort(array $inputData): array {
    $sortedData = $inputData; // Beginne mit dem Input-Array

    // --- ORDER BY Klausel: wählendenverzeichnis_stichtag_form_ecum_2020.nachname ASC ---
    usort($sortedData, function($a, $b) {
        $nachnameA = (string)($a['nachname'] ?? '');
        $nachnameB = (string)($b['nachname'] ?? '');
        return $nachnameA <=> $nachnameB; // Sortiert aufsteigend (ASC)
    });
    error_log("Phase 19: Finale Daten nach ORDER BY (nachname) sortiert. Finale Zeilen: " . count($sortedData));

    return $sortedData;
}