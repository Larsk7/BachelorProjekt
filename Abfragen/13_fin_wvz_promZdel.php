<?php

function fin_wvz_promZdel(array $wvzData, array $dataPhase12): array {
    $personIdsDelete = [];

    // --- Schritt 1: Liste der zu löschenden personids vorbereiten (entspricht dem Subselect im IN) ---
    // SELECT personid FROM Finish_Waehlerverzeichnis_promoprob_akademischMitarbeiter
    foreach ($dataPhase12 as $row) {
        $personid = $row['personid'] ?? null;
        if ($personid !== null) {
            $personIdsDelete[(string)$personid] = true; // Verwende Hash-Map für effizienten Lookup
        }
    }
    error_log("Phase 22: Set der zu 'löschenden' Person IDs erstellt mit " . count($personIdsDelete) . " Einträgen.");


    $resultData = []; // Hier werden die nicht-gelöschten Zeilen gesammelt

    // --- Schritt 2: Array filtern (entspricht der DELETE ... WHERE ...) ---
    // Wir iterieren über die Originaldaten und kopieren nur die Zeilen, die NICHT gelöscht werden sollen.
    foreach ($wvzData as $row) {
        $personid = (string)($row['personid'] ?? null);
        $wählendengruppe = $row['wählendengruppe'] ?? null;

        // Bedingungen für die "Löschung" nach der WHERE-Klausel:
        // 1. wählendenverzeichnis.personid in (select personid from Finish_Waehlerverzeichnis_promoprob_akademischMitarbeiter)
        $condInDeleteList = isset($personIdsDelete[$personid]);

        // 2. and wählendenverzeichnis.wählendengruppe="Promovierende"
        $condGroupIsPromo = ($wählendengruppe === "Promovierende");

        // Wenn BEIDE Bedingungen WAHR sind, soll die Zeile "gelöscht" werden (d.h. nicht in $filteredWahlenverzeichnis kopiert).
        // Also kopieren wir die Zeile, wenn die Gesamtbedingung FALSCH ist.
        if (!($condInDeleteList && $condGroupIsPromo)) {
            $resultData[] = $row; // Zeile wird beibehalten
        } else {
            error_log("Phase 22: Zeile 'gelöscht' (nicht kopiert): Person ID " . $personid . ", Gruppe " . $wählendengruppe);
        }
    }
    error_log("Phase 22: Finale Zeilen nach 'DELETE'-Filterung: " . count($resultData));

    return $resultData;
}