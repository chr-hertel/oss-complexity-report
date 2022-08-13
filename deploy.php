<?php

namespace Deployer;

require 'recipe/symfony.php';

// Config
set('repository', 'git@github.com:chr-hertel/oss-complexity-report.git');
set('composer_options', '--no-dev --verbose --prefer-dist --classmap-authoritative --no-progress --no-interaction --no-scripts');
set('console_options', '--no-interaction --env=prod');
set('shared_dirs', [
    'var/log',
    'repositories',
]);

// Hosts
host('christopher-hertel.de')
    ->set('remote_user', 'deployer')
    ->set('deploy_path', '/var/www/oss-complexity-report');

// Tasks
task('build', function () {
    cd('{{release_path}}');
    run('yarn install --frozen-lockfile');
    run('yarn build');
    run('{{bin/console}} dotenv:dump {{console_options}}');
});

after('deploy:cache:clear', 'build');
after('deploy:failed', 'deploy:unlock');
