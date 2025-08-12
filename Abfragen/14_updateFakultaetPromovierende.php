<?php

function updateFakultaetPromovierende(array $wvzData): array {
    $resultData = $wvzData;

    $targetWählGr = "Promovierende";
    $targetFach = "Soziologie und Politikwissenschaft";
    $newFakValue = "Fakultät für Sozialwissenschaften";

    // --- Iteration und Update ---
    // UPDATE wählendenverzeichnis SET wählendenverzeichnis.fakultät = ... WHERE ...
    foreach ($resultData as $key => $row) {
        $waehlendengruppe = $row['wählendengruppe'] ?? null;
        $fachschaft = $row['fachschaft'] ?? null;

        // Bedingungen der WHERE-Klausel:
        // ((wählendenverzeichnis.wählendengruppe)="Promovierende") AND ((wählendenverzeichnis.fachschaft)="Soziologie und Politikwissenschaft")
        $condGroup = ($waehlendengruppe === $targetWählGr);
        $condFach = ($fachschaft === $targetFach);

        // Wenn BEIDE Bedingungen WAHR sind, aktualisiere die Zeile.
        if ($condGroup && $condFach) {
            $resultData[$key]['fakultät'] = $newFakValue;
            error_log("Phase 23: Zeile aktualisiert: Person ID " . ($row['personid'] ?? 'N/A') . ", neue Fakultät: " . $newFakValue);
        }
    }
    error_log("Phase 23: Finale Zeilen nach 'UPDATE'-Modifikation: " . count($resultData));

    return $resultData;
}