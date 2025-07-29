<?php

$pdo = new PDO(
    "pgsql:host=localhost;port=5432;dbname=meine_datenbank",
    "benutzername",
    "passwort"
);

$stmt = $pdo->prepare(
    "SELECT id, vorname, nachmane 
            FROM nutzer 
            WHERE geburtstag <= TO_DATE('01.01.2004', 'DD.MM.YYYY')
            ORDER BY nachname");
$stmt->execute();
$result = $stmt->fetchAll(
    PDO::FETCH_ASSOC);