<?php

/**
 * filesystems.php — overrides Laravel's default "local" disk so it isn't served.
 *
 * Why: by default Laravel serves the files on the local disk (storage/app/private) at
 * /storage/{path} with temporary signed links. The API serves only the routes we write,
 * so the route is turned off (`serve` => false); everything else matches the default.
 *
 * How it connects: Laravel merges this file with its own config/filesystems.php,
 * disk by disk; SkeletonTest checks that no route is served.
 */

declare(strict_types=1);

return [
    'disks' => [
        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => false,
            'throw' => false,
            'report' => false,
        ],
    ],
];
