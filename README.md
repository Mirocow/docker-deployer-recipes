## Deployer Recipes

This repository contains third party recipes to integrate with deployer.

## Installing

~~~json
    "repositories": [
        {
            "type": "composer",
            "url": "https://packagist.org"
        },
        {
            "type":"vcs",
            "url": "https://github.com/Mirocow/docker-deployer-recipes.git"
        }
    ],
~~~

~~~sh
composer require mirocow/docker-deployer-recipes --dev
~~~

## Usage

```php
<?php
namespace Deployer;

use Deployer\Task\Context;

require './vendor/mirocow/docker-deployer-recipes/recipe/cleanup.php';
require './vendor/mirocow/docker-deployer-recipes/recipe/lock.php';
require './vendor/mirocow/docker-deployer-recipes/recipe/prepare.php';
require './vendor/mirocow/docker-deployer-recipes/recipe/release.php';
require './vendor/mirocow/docker-deployer-recipes/recipe/shared.php';
require './vendor/mirocow/docker-deployer-recipes/recipe/writable.php';
require './vendor/mirocow/docker-deployer-recipes/recipe/laravel.php';

task('docker:deploy:update_code', [
    'docker:deploy:prepare_application',  //
    'docker:deploy:vendors',              //
]);

task('deploy', [
    'docker:deploy:prepare',       // Run in the docker
    'docker:deploy:release',       // Run in the docker
    'docker:deploy:update_code',   // Run in the remote docker container
    'docker:deploy:shared',        // Run in the docker
    'docker:artisan:key:generate', // |
    'docker:artisan:view:clear',   // |
    'docker:artisan:cache:clear',  // |
    'docker:artisan:config:clear', // |
    'docker:artisan:storage:link', // |
    'docker:artisan:config:cache', // | Laravel specific steps
    'docker:artisan:route:clear',  // |
    //'docker:artisan:route:cache',  // |
    'docker:artisan:view:cache',   // |
    //'docker:artisan:optimize',     // |
    'docker:artisan:migrate',      // |
    //'docker:artisan:db:seed',      // |
    'docker:deploy:writable',      // Run in the docker
    'docker:deploy:symlink',
    'docker:deploy:unlock',        // Run in the docker
    'docker:deploy:cleanup',       // Run in the docker
]);
```
