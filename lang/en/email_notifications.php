<?php

/*
 * Templates of the email notifications. The key of the first level is the value of
 * App\Enums\EmailNotificationAction, the key of the second level is the outcome of the action.
 */
return [
    'statuses' => [
        'success' => 'Success',
        'failed' => 'Failed',
    ],

    'fields' => [
        'weekday' => 'Weekday',
        'service' => 'Service',
        'name' => 'Event name',
        'comment' => 'Comment',
        'operator_1' => 'Operator 1 (sheet)',
        'operator_2' => 'Operator 2 (sheet)',
        'htl_1' => 'HTL technician 1',
        'htl_2' => 'HTL technician 2',
    ],

    'plan_event_change' => [
        'empty' => '(empty)',
        'change' => ':field: :old -> :new',
        'changed' => [
            'subject' => 'The event on :date changed',
            'body' => "The event \":name\" on :date, where you are assigned, was changed in the Google Sheet.\n\n"
                .':changes',
        ],
    ],

    'import_google_sheet' => [
        'success' => [
            'subject' => 'Google Sheet import of :sheet finished',
            'body' => "The import of the tab \":sheet\" from the Google Sheet finished.\n\n"
                ."Import status: :status\n"
                ."New rows: :created\n"
                ."Changed rows: :changed\n"
                ."Unchanged rows: :unchanged\n"
                .':skipped',
            'skipped' => 'Skipped rows without a valid or unique date: :rows',
        ],
        'failed' => [
            'subject' => 'Google Sheet import of :sheet failed',
            'body' => "The import of the tab \":sheet\" from the Google Sheet failed.\n\n"
                ."Import status: :status\n"
                .'Error: :message',
        ],
    ],
];
