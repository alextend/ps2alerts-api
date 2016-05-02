<?php

include __DIR__ . '/../vendor/autoload.php';

use Predis\Client;

// ENV loading
josegonzalez\Dotenv\Loader::load([
    'filepath' => __DIR__ . '/../.env',
    'toEnv'    => true
]);

$redis = new Client([
    'host'     => $_ENV['REDIS_HOST'],
    'database' => intval($_ENV['REDIS_DB']),
    'scheme'   => 'tcp'
]);

$pdo = new \PDO(
    "mysql:host={$_ENV['DB_HOST']};dbname={$_ENV['DB_NAME']}",
    $_ENV['DB_USER'],
    $_ENV['DB_PASS']
);