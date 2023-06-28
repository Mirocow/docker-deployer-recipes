<?php
namespace Deployer;

require_once __DIR__ . '/docker.php';

use Deployer\Exception\GracefulShutdownException;

desc('Lock deploy');
task('docker:deploy:lock', function () {
    $locked = testInTheDocker("[ -f {{docker_compose_working_path}}/.dep/deploy.lock ]");

    if ($locked) {
        $stage = input()->hasArgument('stage') ? ' ' . input()->getArgument('stage') : '';

        throw new GracefulShutdownException(
            "Deploy locked.\n" .
            sprintf('Execute "dep docker:deploy:unlock%s" to unlock.', $stage)
        );
    } else {
        docker("touch {{docker_compose_working_path}}/.dep/deploy.lock");
    }
});

desc('Unlock deploy');
task('docker:deploy:unlock', function () {
    docker("rm -f {{docker_compose_working_path}}/.dep/deploy.lock");//always success
});
