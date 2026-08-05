<?php
// small helper to enable Redis session if configured (included from api.php)
if (getenv('REDIS_HOST')) {
    $redisHost = getenv('REDIS_HOST');
    $redisPort = getenv('REDIS_PORT') ?: 6379;
    $redisPass = getenv('REDIS_PASSWORD');
    if (!empty($redisPass)) {
        ini_set('session.save_handler', 'redis');
        ini_set('session.save_path', "tcp://{$redisHost}:{$redisPort}?auth={$redisPass}");
    } else {
        ini_set('session.save_handler', 'redis');
        ini_set('session.save_path', "tcp://{$redisHost}:{$redisPort}");
    }
}
