<?php
namespace Deployer;

require_once __DIR__ . '/docker.php';

use Deployer\Type\Csv;

set('release_name', function () {
    $list = get('releases_list');

    // Filter out anything that does not look like a release.
    $list = array_filter($list, function ($release) {
        return preg_match('/^[\d\.]+$/', $release);
    });

    $nextReleaseNumber = 1;
    if (count($list) > 0) {
        $nextReleaseNumber = (int)max($list) + 1;
    }

    return (string)$nextReleaseNumber;
}); // name of folder in releases

/**
 * Return list of releases on host.
 */
set('releases_list', function () {

    // If there is no releases return empty list.
    if (!testInTheDocker('[ -d {{docker_compose_working_path}}/releases ] && [ "$(ls -A {{docker_compose_working_path}}/releases)" ]')) {
        return [];
    }

    // Will list only dirs in releases.
    $list = explode("\n", docker('cd {{docker_compose_working_path}}/releases && ls -t -1 -d */'));

    // Prepare list.
    $list = array_map(function ($release) {
        return basename(rtrim(trim($release), '/'));
    }, $list);

    $releases = []; // Releases list.

    // Collect releases based on .dep/releases info.
    // Other will be ignored.

    if (testInTheDocker('[ -f {{docker_compose_working_path}}/.dep/releases ]')) {
        $keepReleases = get('keep_releases');
        if ($keepReleases === -1) {
            $csv = docker('cat {{docker_compose_working_path}}/.dep/releases');
        } else {
            // Instead of `tail -n` call here can be `cat` call,
            // but on hosts with a lot of deploys (more 1k) it
            // will output a really big list of previous releases.
            // It spoils appearance of output log, to make it pretty,
            // we limit it to `n*2 + 5` lines from end of file (15 lines).
            // Always read as many lines as there are release directories.
            $csv = docker("tail -n " . max(count($releases), ($keepReleases * 2 + 5)) . " {{docker_compose_working_path}}/.dep/releases");
        }

        $metainfo = Csv::parse($csv);

        for ($i = count($metainfo) - 1; $i >= 0; --$i) {
            if (is_array($metainfo[$i]) && count($metainfo[$i]) >= 2) {
                list(, $release) = $metainfo[$i];
                $index = array_search($release, $list, true);
                if ($index !== false) {
                    $releases[] = $release;
                    unset($list[$index]);
                }
            }
        }
    }

    return $releases;
});

set('docker_current_path', function () {
    $link = docker("readlink {{docker_compose_working_path}}/current");
    return substr($link, 0, 1) === '/' ? $link : get('docker_compose_working_path') . '/' . $link;
});

/**
 * Return release path.
 */
set('docker_release_path', function () {
    $releaseExists = testInTheDocker('[ -h {{docker_compose_working_path}}/release ]');
    if ($releaseExists) {
        $link = docker("readlink {{docker_compose_working_path}}/release");
        return substr($link, 0, 1) === '/' ? $link : get('docker_compose_working_path') . '/' . $link;
    } else {
        return get('docker_current_path');
    }
});

set('release_path', function () {
    $releaseNumber = get('release_name');
    $deployPath = get('deploy_path');
    $currentReleasePath = "$deployPath/releases/$releaseNumber";
    $releaseExists = test("[ -d $currentReleasePath ]");
    if ($releaseExists) {
        return $currentReleasePath;
    } else {
        return get('current_path');
    }
});

desc('Prepare release. Clean up unfinished releases and prepare next release');
task('docker:deploy:release', function () {

    // Clean up if there is unfinished release
    writeln('Clean up if there is unfinished release');
    $previousReleaseExist = testInTheDocker('[ -h {{docker_compose_working_path}}/release ]');
    if ($previousReleaseExist) {
        docker('cd {{docker_compose_working_path}} && rm -rf "$(readlink release)"'); // Delete release
        docker('cd {{docker_compose_working_path}} && rm release'); // Delete symlink
    }

    // We need to get releases_list at same point as release_name,
    // as standard release_name's implementation depends on it and,
    // if user overrides it, we need to get releases_list manually.
    $releasesList = get('releases_list');
    $releaseName = get('release_name');

    // Fix collisions
    $i = 0;
    while (testInTheDocker("[ -d {{docker_compose_working_path}}/releases/$releaseName ]")) {
        $releaseName .= '.' . ++$i;
        set('release_name', $releaseName);
    }

    $releasePath = parse("{{docker_compose_working_path}}/releases/{{release_name}}");

    // Metainfo.
    $date = docker('date +"%Y%m%d%H%M%S"');

    // Save metainfo about release
    writeln('Save metainfo about release');
    docker("echo '$date,{{release_name}}' >> {{docker_compose_working_path}}/.dep/releases");

    // Make new release
    writeln('Make new release');
    docker("mkdir $releasePath");
    docker("{{docker/bin/symlink}} $releasePath {{docker_compose_working_path}}/release");

    // Add to releases list
    array_unshift($releasesList, $releaseName);
    set('releases_list', $releasesList);

    // Set previous_release
    if (isset($releasesList[1])) {
        set('previous_release', "{{docker_compose_working_path}}/releases/{$releasesList[1]}");
    }

});
