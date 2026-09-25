<?php

$globalKilobytes = (int) env('MEDIA_MAX_KILOBYTES', 10240);

return [

    'disk' => env('MEDIA_DISK', 'public'),

    /*
    | Hard ceilings for one user. A collection can be stricter, not looser.
    */
    'max_kilobytes' => $globalKilobytes,

    'max_files' => (int) env('MEDIA_MAX_FILES', 200),

    /*
    | Each collection owns its MIME list, per-file size and how many files
    | one user may keep in it. Avatar replacement does not consume an extra slot.
    */
    'collections' => [
        'default' => [
            'mimes' => [
                'image/jpeg',
                'image/png',
                'image/webp',
                'image/gif',
                'application/pdf',
            ],
            'max_kilobytes' => $globalKilobytes,
            'max_files' => 40,
        ],
        'images' => [
            'mimes' => [
                'image/jpeg',
                'image/png',
                'image/webp',
                'image/gif',
            ],
            'max_kilobytes' => 5120,
            'max_files' => 80,
        ],
        'avatar' => [
            'mimes' => [
                'image/jpeg',
                'image/png',
                'image/webp',
            ],
            'max_kilobytes' => 2048,
            'max_files' => 1,
        ],
        'documents' => [
            'mimes' => [
                'application/pdf',
                'text/plain',
            ],
            'max_kilobytes' => $globalKilobytes,
            'max_files' => 30,
        ],
    ],

];
