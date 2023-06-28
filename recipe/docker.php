<?php
namespace Deployer;

use function Deployer\Support\array_to_string;

set('docker_compose_working_path', '/app');
/*
 * Specify a directory containing docker-compose.yml file(s)
 */
set('docker_compose_dir', function(){
    return get('docker_compose_working_path');
});
set('bin/docker', 'docker');
set('bin/docker-compose', 'docker-compose');
set('docker_compose_php_service', 'php');

set('bin/docker', function () {
    $dockerPath = which('docker');

    if(!$dockerPath){
        throw new \RuntimeException('Docker command not found');
    }

    return $dockerPath;
});

/**
 * Return Docker container id by service name
 *
 * @param $service
 * @return string
 */
function dockerGetContainerId($service)
{
    return run('{{bin/docker}} ps -qf "name='.$service.'"');
}

/**
 * Return true if a given service is running in Docker, otherwise false
 *
 * @param $service
 * @return bool
 */
function dockerServiceExists($service)
{
    return !!dockerGetContainerId($service);
}

/**
 * Remove container from Docker by service name
 *
 * @param $service
 * @return string
 */
function dockerRemoveService($service)
{
    $containerId = dockerGetContainerId($service);

    if (!$containerId)
    {
        throw new \InvalidArgumentException(`Container {$service} not found.`);
    }

    return docker('{{bin/docker}} rm -f ' . $containerId);
}

/**
 * Prepare a command to be executed in Docker container
 *
 * @param $command
 * @param array $options
 * @return string
 */
function dockerPrepareCommand($command, $options = [])
{
    $command = parse($command);

    $workingPath = get('working_path', '');

    if (!empty($workingPath))
    {
        $command = "cd $workingPath && ($command)";
    }

    $env = get('env', []) + ($options['env'] ?? []);
    if (!empty($env))
    {
        $env = array_to_string($env);
        $command = "export $env; $command";
    }

    return $command;
}

/**
 * Run a command using service, specified in docker-compose.yml file using docker exec
 *
 * @param $service
 * @param $command
 * @param array $options
 */
function dockerExec($service, $command, $options = [])
{
    $containerId = dockerGetContainerId($service);

    if (!$containerId)
    {
        throw new \InvalidArgumentException(`Container {$service} not found.`);
    }

    $command = dockerPrepareCommand($command, $options);

    $opts = [];
    if (isset($options['user']))
    {
        $opts[] = '-u ' . $options['user'];
    }

    $command = sprintf('{{bin/docker}} exec %s %s sh -c "%s"', implode(' ', $opts), $service, $command);

    writeln("<info>Run:$command</info>");

    return run($command, $options);
}

/**
 * Run a command using service, specified in docker-compose.yml file using docker run
 *
 * @param $service
 * @param $command
 * @param array $options
 */
function dockerRun($service, $command, $options = [])
{
    $containerId = dockerGetContainerId($service);

    if (!$containerId)
    {
        throw new \InvalidArgumentException(`Container {$service} not found.`);
    }

    $command = dockerPrepareCommand($command, $options);

    $opts = [];
    if (isset($options['user']))
    {
        $opts[] = '-u ' . $options['user'];
    }

    $command = sprintf('{{bin/docker}} run %s %s sh -c "%s"', implode(' ', $opts), $service, $command);

    return run($command);
}

set('use_relative_symlink', function () {
    return commandSupportsOptionInTheDocker('ln', '--relative');
});

set('use_atomic_symlink', function () {
    return commandSupportsOptionInTheDocker('mv', '--no-target-directory');
});

/**
 * Run test command.
 * Example:
 *
 * ```php
 * if (test('[ -d {{docker_release_path}} ]')) {
 * ...
 * }
 * ```
 *
 */
function testInTheDocker(string $command): bool
{
    $true = '+' . array_rand(array_flip(['accurate', 'appropriate', 'correct', 'legitimate', 'precise', 'right', 'true', 'yes', 'indeed']));
    return docker("if $command; then echo $true; fi") === $true;
}

/**
 * @param $command
 * @param $option
 *
 * @return bool
 */
function commandSupportsOptionInTheDocker($command, $option)
{
    $man = docker("(man $command 2>&1 || $command -h 2>&1 || $command --help 2>&1) | grep -- $option || true");
    if (empty($man)) {
        return false;
    }
    return str_contains($man, $option);
}

/**
 * @param $command
 *
 * @return bool
 */
function commandExistInTheDocker($command)
{
    return testInTheDocker("hash $command 2>/dev/null");
}

/**
 * @param $name
 *
 * @return string
 */
function locateBinaryPathInTheDocker($name)
{
    $nameEscaped = escapeshellarg($name);

    // Try `command`, should cover all Bourne-like shells
    // Try `which`, should cover most other cases
    // Fallback to `type` command, if the rest fails
    $path = docker("command -v $nameEscaped || which $nameEscaped || type -p $nameEscaped");
    if ($path) {
        // Deal with issue when `type -p` outputs something like `type -ap` in some implementations
        return trim(str_replace("$name is", "", $path));
    }

    throw new \RuntimeException("Can't locate [$nameEscaped] - neither of [command|which|type] commands are available");
}

set('user_deploy', function () {
    return run('echo $(id -u):$(id -g)');
});

/**
 * @param $command
 */
function docker($command, $options = [])
{
    $options = array_merge($options, ['user' => get('user_deploy')]);
    return dockerExec(get('docker_compose_php_service'), $command, $options);
}

set('docker/bin/composer', function () {
    if (commandExistInTheDocker('composer')) {
        $composer = locateBinaryPathInTheDocker('composer');
    }
    if (empty($composer)) {
        throw new \RuntimeException("Can't locate composer. Please install curl -sS https://getcomposer.org/installer | {{docker/bin/php}}");
    }

    return 'cd {{docker_compose_dir}}/release && {{docker/bin/php}} ' . $composer;
});

set('docker/bin/php', function () {
    return locateBinaryPathInTheDocker('php');
});

set('docker/bin/symlink', function () {
    return get('use_relative_symlink') ? 'ln -nfs --relative' : 'ln -nfs';
});

desc('Installing vendors');
set('composer_action', 'install');
set('composer_options', '--verbose --no-interaction');
task('docker:deploy:vendors', function () {
    docker( '{{docker/bin/composer}} {{composer_action}} {{composer_options}}');
});
