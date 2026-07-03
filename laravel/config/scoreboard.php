<?php

return [
    'version' => env('SCOREBOARD_VERSION', '1.1.6'),
    'version_code' => env('SCOREBOARD_VERSION_CODE', 116),
    'download_url' => env('SCOREBOARD_DOWNLOAD_URL', 'https://staging.judotournament.org/downloads/judoscoreboard.apk'),
    'force_update' => env('SCOREBOARD_FORCE_UPDATE', false),
    'release_notes' => env('SCOREBOARD_RELEASE_NOTES', 'IPPON-indicatie bij osaekomi, verbeterd wisselgedrag en compactere ronde-labels.'),
];
