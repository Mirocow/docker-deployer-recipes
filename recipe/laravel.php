<?php
namespace Deployer;

require_once __DIR__ . '/docker.php';

set('shared_dirs', ['storage']);
set('shared_files', ['.env']);
set('writable_dirs', [
    'bootstrap/cache',
    'storage',
    'storage/app',
    'storage/app/public',
    'storage/framework',
    'storage/framework/cache',
    'storage/framework/sessions',
    'storage/framework/views',
    'storage/logs',
]);
//set('log_files', 'storage/logs/*.log');
set('laravel_version', function () {
    $result = docker('{{docker/bin/php}} {{docker_compose_working_path}}/release/artisan --version');
    preg_match_all('/(\d+\.?)+/', $result, $matches);
    return $matches[0][0] ?? 5.5;
});

function artisan($command, $options = [])
{
    return function () use ($command, $options) {

        // Ensure the artisan command is available on the current version.
        $versionTooEarly = array_key_exists('min', $options)
            && laravel_version_compare($options['min'], '<');

        $versionTooLate = array_key_exists('max', $options)
            && laravel_version_compare($options['max'], '>');

        if ($versionTooEarly || $versionTooLate) {
            return;
        }

        // Ensure we warn or fail when a command relies on the ".env" file.
        if (in_array('failIfNoEnv', $options) && !testInTheDocker('[ -s {{docker_compose_working_path}}/release/.env ]')) {
            throw new \Exception('Your .env file is empty! Cannot proceed.');
        }

        if (in_array('skipIfNoEnv', $options) && !testInTheDocker('[ -s {{docker_compose_working_path}}/release/.env ]')) {
            writeln("Your .env file is empty! Skipping...</>");
            return;
        }

        $artisan = '{{docker_compose_working_path}}/release/artisan';

        // Run the artisan command.
        $output = docker("{{docker/bin/php}} $artisan $command");

        // Output the results when appropriate.
        if (in_array('showOutput', $options)) {
            writeln("<info>$output</info>");
        }
    };
}

function laravel_version_compare($version, $comparator)
{
    return version_compare(get('laravel_version'), $version, $comparator);
}

/*
 * Maintenance mode.
 */

desc('Puts the application into maintenance / demo mode');
task('docker:artisan:down', artisan('down', ['showOutput']));

desc('Brings the application out of maintenance mode');
task('docker:artisan:up', artisan('up', ['showOutput']));

/*
 * Generate keys.
 */

desc('Sets the application key');
task('docker:artisan:key:generate', artisan('key:generate'));

desc('Creates the encryption keys for API authentication');
task('docker:artisan:passport:keys', artisan('passport:keys'));

/*
 * Database and migrations.
 */

desc('Seeds the database with records');
task('docker:artisan:db:seed', artisan('db:seed --force', ['showOutput']));

desc('Runs the database migrations');
task('docker:artisan:migrate', artisan('migrate --force', ['skipIfNoEnv']));

desc('Drops all tables and re-run all migrations');
task('docker:artisan:migrate:fresh', artisan('migrate:fresh --force'));

desc('Rollbacks the last database migration');
task('docker:artisan:migrate:rollback', artisan('migrate:rollback --force', ['showOutput']));

desc('Shows the status of each migration');
task('docker:artisan:migrate:status', artisan('migrate:status', ['showOutput']));

/*
 * Cache and optimizations.
 */

desc('Flushes the application cache');
task('docker:artisan:cache:clear', artisan('cache:clear'));

desc('Creates a cache file for faster configuration loading');
task('docker:artisan:config:cache', artisan('config:cache'));

desc('Removes the configuration cache file');
task('docker:artisan:config:clear', artisan('config:clear'));

desc('Discovers and cache the application\'s events and listeners');
task('docker:artisan:event:cache', artisan('event:cache', ['min' => '5.8.9']));

desc('Clears all cached events and listeners');
task('docker:artisan:event:clear', artisan('event:clear', ['min' => '5.8.9']));

desc('Lists the application\'s events and listeners');
task('docker:artisan:event:list', artisan('event:list', ['showOutput', 'min' => '5.8.9']));

desc('Cache the framework bootstrap files');
task('docker:artisan:optimize', artisan('optimize'));

desc('Removes the cached bootstrap files');
task('docker:artisan:optimize:clear', artisan('optimize:clear'));

desc('Creates a route cache file for faster route registration');
task('docker:artisan:route:cache', artisan('route:cache'));

desc('Removes the route cache file');
task('docker:artisan:route:clear', artisan('route:clear'));

desc('Lists all registered routes');
task('docker:artisan:route:list', artisan('route:list', ['showOutput']));

desc('Creates the symbolic links configured for the application');
task('docker:artisan:storage:link', artisan('storage:link', ['min' => 5.3]));

desc('Compiles all of the application\'s Blade templates');
task('docker:artisan:view:cache', artisan('view:cache', ['min' => 5.6]));

desc('Clears all compiled view files');
task('docker:artisan:view:clear', artisan('view:clear'));

/**
 * Queue and Horizon.
 */

desc('Lists all of the failed queue jobs');
task('docker:artisan:queue:failed', artisan('queue:failed', ['showOutput']));

desc('Flushes all of the failed queue jobs');
task('docker:artisan:queue:flush', artisan('queue:flush'));

desc('Restarts queue worker daemons after their current job');
task('docker:artisan:queue:restart', artisan('queue:restart'));

desc('Starts a master supervisor in the foreground');
task('docker:artisan:horizon', artisan('horizon'));

desc('Deletes all of the jobs from the specified queue');
task('docker:artisan:horizon:clear', artisan('horizon:clear --force'));

desc('Instructs the master supervisor to continue processing jobs');
task('docker:artisan:horizon:continue', artisan('horizon:continue'));

desc('Lists all of the deployed machines');
task('docker:artisan:horizon:list', artisan('horizon:list', ['showOutput']));

desc('Pauses the master supervisor');
task('docker:artisan:horizon:pause', artisan('horizon:pause'));

desc('Terminates any rogue Horizon processes');
task('docker:artisan:horizon:purge', artisan('horizon:purge'));

desc('Gets the current status of Horizon');
task('docker:artisan:horizon:status', artisan('horizon:status', ['showOutput']));

desc('Terminates the master supervisor so it can be restarted');
task('docker:artisan:horizon:terminate', artisan('horizon:terminate'));

/*
 * Telescope.
 */

desc('Clears all entries from Telescope');
task('docker:artisan:telescope:clear', artisan('telescope:clear'));

desc('Prunes stale entries from the Telescope database');
task('docker:artisan:telescope:prune', artisan('telescope:prune'));

task('docker:deploy:prepare_application', function () {
    docker('mkdir -p {{docker_release_path}}/bootstrap/cache');
    docker('mkdir -p {{docker_release_path}}/storage/app/public');
    docker('mkdir -p {{docker_release_path}}/storage/framework/cache');
    docker('mkdir -p {{docker_release_path}}/storage/framework/sessions');
    docker('mkdir -p {{docker_release_path}}/storage/framework/views');
    docker('mkdir -p {{docker_release_path}}/storage/framework/cache/data');
    docker('mv -f {{docker_release_path}}/.env.dist {{docker_compose_working_path}}/shared/.env');
});
