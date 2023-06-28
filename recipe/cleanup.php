<?php
namespace Deployer;

require_once __DIR__ . '/docker.php';

desc('Cleaning up old releases');
task('docker:deploy:cleanup', function () {
    $releases = get('releases_list');
    $keep = get('keep_releases');
    $sudo = get('cleanup_use_sudo') ? 'sudo' : '';
    $runOpts = [];
    if ($sudo) {
        $runOpts['tty'] = get('cleanup_tty', false);
    }

    if ($keep === -1) {
        // Keep unlimited releases.
        return;
    }

    while ($keep > 0) {
        array_shift($releases);
        --$keep;
    }

    foreach ($releases as $release) {
        docker("$sudo rm -rf {{docker_compose_working_path}}/releases/$release", $runOpts);
    }

    docker("cd {{docker_compose_working_path}} && if [ -e release ]; then $sudo rm release; fi", $runOpts);
    docker("cd {{docker_compose_working_path}} && if [ -h release ]; then $sudo rm release; fi", $runOpts);
});
