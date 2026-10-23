<?php

/*
 * Šablony e-mailových notifikací. Klíč první úrovně je hodnota App\Enums\EmailNotificationAction,
 * klíč druhé úrovně je výsledek akce.
 */
return [
    'statuses' => [
        'success' => 'Úspěch',
        'failed' => 'Chyba',
    ],

    'fields' => [
        'weekday' => 'Den v týdnu',
        'service' => 'Služba',
        'name' => 'Název akce',
        'comment' => 'Komentář',
        'operator_1' => 'Operátor 1 (tabulka)',
        'operator_2' => 'Operátor 2 (tabulka)',
        'htl_1' => 'Technik HTL 1',
        'htl_2' => 'Technik HTL 2',
    ],

    'plan_event_change' => [
        'empty' => '(prázdné)',
        'change' => ':field: :old -> :new',
        'changed' => [
            'subject' => 'Akce dne :date byla změněna',
            'body' => "Akce \":name\" dne :date, na kterou jste přiřazeni, byla změněna v Google Sheet.\n\n"
                .':changes',
        ],
    ],

    'import_google_sheet' => [
        'success' => [
            'subject' => 'Import z Google Sheet (:sheet) byl dokončen',
            'body' => "Import záložky \":sheet\" z Google Sheet byl dokončen.\n\n"
                ."Stav importu: :status\n"
                ."Nové řádky: :created\n"
                ."Změněné řádky: :changed\n"
                ."Beze změny: :unchanged\n"
                .':skipped',
            'skipped' => 'Přeskočené řádky bez platného nebo jedinečného data: :rows',
        ],
        'failed' => [
            'subject' => 'Import z Google Sheet (:sheet) selhal',
            'body' => "Import záložky \":sheet\" z Google Sheet selhal.\n\n"
                ."Stav importu: :status\n"
                .'Chyba: :message',
        ],
    ],
];
