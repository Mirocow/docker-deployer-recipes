<?php

namespace Deployer;

require_once __DIR__ . '/recipe/cleanup.php';
require_once __DIR__ . '/recipe/laravel.php';
require_once __DIR__ . '/recipe/lock.php';
require_once __DIR__ . '/recipe/prepare.php';
require_once __DIR__ . '/recipe/release.php';
require_once __DIR__ . '/recipe/shared.php';
require_once __DIR__ . '/recipe/writable.php';

after('deploy:failed', 'docker:deploy:unlock');

task('deploy:success', [
    'success',
]);

task('docker:deploy:update_code', [
    'rsync',                              // Copy from local docker container to host
    'docker:deploy:prepare_application',  //
    'docker:deploy:vendors',              //
]);

task('deploy', [
    'deploy:info',                 // Default recipe
    'docker:deploy:prepare',       // Run in the docker
    'docker:deploy:release',       // Run in the docker
    'docker:deploy:update_code',   // Run in the remote docker container
    'docker:deploy:shared',        // Run in the docker
    'docker:artisan:view:clear',   // |
    'docker:artisan:cache:clear',  // |
    'docker:artisan:config:clear', // |
    'docker:artisan:storage:link', // |
    'docker:artisan:config:cache', // | Laravel specific steps
    //'docker:artisan:route:cache',  // |
    'docker:artisan:view:cache',   // |
    'docker:artisan:optimize',     // |
    'docker:artisan:migrate',      // |
    'docker:artisan:db:seed',      // |
    'docker:deploy:writable',      // Run in the docker
    'docker:deploy:unlock',        // Run in the docker
    'docker:deploy:cleanup',       // Run in the docker
    'deploy:success',              // Default recipe
]);