<?php

namespace Deployer;

require 'recipe/symfony.php';

// Config
set('repository', 'git@github-oss-complexity-report:chr-hertel/oss-complexity-report.git');
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
    run('ASSET_BASE=/oss-complexity-report/build/ yarn build');
    run('{{bin/console}} dotenv:dump {{console_options}}');
});

// Moving the symlink is not what puts a release live: php-fpm reaches the application through that
// symlink, so opcache keeps serving the previous release compiled under the very same path - for as long
// as the pool runs. Reloading it is the actual switch.
task('php-fpm', function () {
    run('sudo systemctl reload php8.4-fpm');
});

// Workers keep running against the old release until they are restarted onto the new symlink.
task('worker', function () {
    run('sudo supervisorctl restart oss_complexity_report_consumer:*');
    run('sudo supervisorctl restart oss_complexity_report_scheduler:*');
});

after('deploy:cache:clear', 'build');

// Schema changes go live with the release that needs them, so migrate right before the symlink switches.
before('deploy:symlink', 'database:migrate');
after('deploy:symlink', 'php-fpm');
after('php-fpm', 'worker');

after('deploy:failed', 'deploy:unlock');
