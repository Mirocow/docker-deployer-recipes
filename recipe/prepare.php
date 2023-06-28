<?php
namespace Deployer;

require_once __DIR__ . '/docker.php';

use Deployer\Exception\Exception;
use function Deployer\Support\str_contains;

desc('Preparing host for deploy');
task('docker:deploy:prepare', function () {
    // Check if shell is POSIX-compliant
    $result = docker('echo $0');

    if (!str_contains($result, 'bash') && !str_contains($result, 'sh')) {
        throw new \RuntimeException(
            'Shell on your server is not POSIX-compliant. Please change to sh, bash or similar.'
        );
    }

    docker('if [ ! -d {{docker_compose_working_path}} ]; then mkdir -p {{docker_compose_working_path}}; fi');

    // Check for existing /current directory (not symlink)
    $result = testInTheDocker('[ ! -L {{docker_compose_working_path}}/current ] && [ -d {{docker_compose_working_path}}/current ]');
    if ($result) {
        throw new Exception('There already is a directory (not symlink) named "current" in ' . get('docker_compose_working_path') . '. Remove this directory so it can be replaced with a symlink for atomic deployments.');
    }

    // Create metadata .dep dir.
    docker("cd {{docker_compose_working_path}} && if [ ! -d .dep ]; then mkdir .dep; fi");

    // Create releases dir.
    docker("cd {{docker_compose_working_path}} && if [ ! -d releases ]; then mkdir releases; fi");

    // Create shared dir.
    docker("cd {{docker_compose_working_path}} && if [ ! -d shared ]; then mkdir shared; fi");
});
