<?php
namespace Deployer;

require 'recipe/laravel.php';

// Project name
set('application', 'websocket-server');

// Project repository
set('repository', 'https://github.com/kkbonsu/ayuda_websocket.git');

// Shared files/dirs between deploys
add('shared_files', ['.env']);
add('shared_dirs', ['storage', 'bootstrap/cache']);

// Writable dirs by web server
add('writable_dirs', ['bootstrap/cache', 'storage']);

// Hosts
host('production')
    ->set('remote_user', 'ubuntu')
    ->set('hostname', '18.171.56.31')
    ->set('deploy_path', '/var/www/websocket-server')
    ->set('identity_file', '~/.ssh/AYUDA.pem');

// Tasks
task('deploy', [
    'deploy:prepare',
    'deploy:vendors',
    'artisan:storage:link',
    'artisan:config:cache',
    'artisan:route:cache',
    'artisan:view:cache',
    'artisan:migrate',
    'deploy:publish',
]);

// Custom migrate task that explicitly loads .env
task('deploy:migrate', function () {
    cd('{{release_path}}');
    // Load .env variables into shell environment
    run('export $(cat .env | xargs) && php artisan migrate --force --no-interaction');
})->desc('Run migrations with .env loaded');

// Restart Reverb after successful deploy
after('deploy:publish', 'reverb:restart');

task('reverb:restart', function () {
    // Go to the CURRENT release (not release_path) to restart Reverb properlyy
    run('cd {{deploy_path}}/current && php artisan reverb:restart');
});

