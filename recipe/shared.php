<?php
namespace Deployer;

require_once __DIR__ . '/docker.php';

use Deployer\Exception\Exception;

desc('Creating symlinks for shared files and dirs');
task('docker:deploy:shared', function () {

    $sharedPath = "{{docker_compose_working_path}}/shared";

    // Validate shared_dir, find duplicates
    writeln('Validate shared_dir, find duplicates');
    foreach (get('shared_dirs') as $a) {
        foreach (get('shared_dirs') as $b) {
            if ($a !== $b && strpos(rtrim($a, '/') . '/', rtrim($b, '/') . '/') === 0) {
                throw new Exception("Can not share same dirs `$a` and `$b`.");
            }
        }
    }

    writeln('Create shared dirs');
    foreach (get('shared_dirs') as $dir) {
        // Check if shared dir does not exist.
        if (!testInTheDocker("[ -d $sharedPath/$dir ]")) {
            // Create shared dir if it does not exist.
            docker("mkdir -p $sharedPath/$dir");

            // If release contains shared dir, copy that dir from release to shared.
            if (testInTheDocker("[ -d $(echo {{docker_release_path}}/$dir) ]")) {
                docker("cp -rv {{docker_release_path}}/$dir $sharedPath/" . dirname(parse($dir)));
            }
        }

        // Remove from source.
        docker("rm -rf {{docker_release_path}}/$dir");

        // Create path to shared dir in release dir if it does not exist.
        // Symlink will not create the path and will fail otherwise.
        docker("mkdir -p `dirname {{docker_release_path}}/$dir`");

        // Symlink shared dir to release dir
        docker("{{docker/bin/symlink}} $sharedPath/$dir {{docker_release_path}}/$dir");
    }

    writeln('Create shared files');
    foreach (get('shared_files') as $file) {
        $dirname = dirname(parse($file));

        // Create dir of shared file if not existing
        if (!testInTheDocker("[ -d {$sharedPath}/{$dirname} ]")) {
            docker("mkdir -p {$sharedPath}/{$dirname}");
        }

        // Check if shared file does not exist in shared.
        // and file exist in release
        if (!testInTheDocker("[ -f $sharedPath/$file ]") && testInTheDocker("[ -f {{docker_release_path}}/$file ]")) {
            // Copy file in shared dir if not present
            docker("cp -rv {{docker_release_path}}/$file $sharedPath/$file");
        }

        // Remove from source.
        writeln('Remove from source');
        docker("if [ -f $(echo {{docker_release_path}}/$file) ]; then rm -rf {{docker_release_path}}/$file; fi");

        // Ensure dir is available in release
        writeln('Ensure dir is available in release');
        docker("if [ ! -d $(echo {{docker_release_path}}/$dirname) ]; then mkdir -p {{docker_release_path}}/$dirname;fi");

        // Touch shared
        writeln('Touch shared');
        docker("touch $sharedPath/$file");

        // Symlink shared dir to release dir
        writeln('Symlink shared dir to release dir');
        docker("{{docker/bin/symlink}} $sharedPath/$file {{docker_release_path}}/$file");
    }

});
