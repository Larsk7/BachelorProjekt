<?php

function updateFakultaetPromovierende(array $wahlenverzeichnisData): array {
    // Erstelle eine Kopie des Arrays, um das Original nicht direkt zu modifizieren,
    // es sei denn, die In-Place-Modifikation ist explizit gewünscht.
    // PHP-Arrays werden per Wert übergeben, wenn sie nicht mit '&' übergeben werden.
    // D.h., $wahlenverzeichnisData wird hier bereits als Kopie übergeben.
    $updatedData = $wahlenverzeichnisData;

    $target_waehlendengruppe = "Promovierende";
    $target_fachschaft = "Soziologie und Politikwissenschaft";
    $new_fakultaet_value = "Fakultät für Sozialwissenschaften";

    // --- Iteration und Update ---
    // UPDATE wählendenverzeichnis SET wählendenverzeichnis.fakultät = ... WHERE ...
    foreach ($updatedData as $key => $row) {
        $waehlendengruppe = $row['wählendengruppe'] ?? null;
        $fachschaft = $row['fachschaft'] ?? null;

        // Bedingungen der WHERE-Klausel:
        // ((wählendenverzeichnis.wählendengruppe)="Promovierende") AND ((wählendenverzeichnis.fachschaft)="Soziologie und Politikwissenschaft")
        $cond_group = ($waehlendengruppe === $target_waehlendengruppe);
        $cond_fachschaft = ($fachschaft === $target_fachschaft);

        // Wenn BEIDE Bedingungen WAHR sind, aktualisiere die Zeile.
        if ($cond_group && $cond_fachschaft) {
            $updatedData[$key]['fakultät'] = $new_fakultaet_value;
            error_log("Phase 23: Zeile aktualisiert: Person ID " . ($row['personid'] ?? 'N/A') . ", neue Fakultät: " . $new_fakultaet_value);
        }
    }
    error_log("Phase 23: Finale Zeilen nach 'UPDATE'-Modifikation: " . count($updatedData));

    return $updatedData;
}