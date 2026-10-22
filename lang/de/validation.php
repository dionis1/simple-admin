<?php

return [
    'required' => ':attribute ist erforderlich.', 'string' => ':attribute muss eine Zeichenkette sein.',
    'email' => ':attribute muss eine gültige E-Mail-Adresse sein.', 'integer' => ':attribute muss eine ganze Zahl sein.',
    'array' => ':attribute muss eine Liste sein.', 'confirmed' => 'Die Bestätigung von :attribute stimmt nicht überein.',
    'unique' => ':attribute ist bereits vergeben.', 'exists' => ':attribute ist ungültig.', 'in' => ':attribute ist ungültig.',
    'enum' => ':attribute ist ungültig.', 'distinct' => ':attribute darf nicht mehrfach vorkommen.',
    'current_password' => 'Das aktuelle Passwort ist falsch.', 'lowercase' => ':attribute muss kleingeschrieben sein.',
    'min' => ['string' => ':attribute muss mindestens :min Zeichen enthalten.', 'numeric' => ':attribute muss mindestens :min sein.', 'array' => ':attribute muss mindestens :min Eintrag enthalten.'],
    'max' => ['string' => ':attribute darf höchstens :max Zeichen enthalten.', 'numeric' => ':attribute darf höchstens :max sein.', 'array' => ':attribute darf höchstens :max Einträge enthalten.'],
    'between' => ['numeric' => ':attribute muss zwischen :min und :max liegen.'],
    'password' => ['letters' => ':attribute muss Buchstaben enthalten.', 'mixed' => ':attribute muss Groß- und Kleinbuchstaben enthalten.', 'numbers' => ':attribute muss Zahlen enthalten.', 'symbols' => ':attribute muss Sonderzeichen enthalten.', 'uncompromised' => ':attribute wurde in einem Datenleck gefunden. Bitte wählen Sie ein anderes Passwort.'],
    'attributes' => ['name' => 'Name', 'email' => 'E-Mail-Adresse', 'password' => 'Passwort', 'currentPassword' => 'Aktuelles Passwort', 'current_password' => 'Aktuelles Passwort', 'roleId' => 'Rolle', 'editRoleId' => 'Rolle', 'status' => 'Status', 'teamName' => 'Teamname', 'departmentName' => 'Abteilungsname', 'displayName' => 'Anzeigename', 'jobTitle' => 'Position', 'days' => 'Gültigkeit', 'abilities' => 'Berechtigungen', 'ids' => 'Auswahl', 'locale' => 'Sprache'],
];
