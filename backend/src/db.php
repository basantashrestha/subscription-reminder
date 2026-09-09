<?php

function getDbConnection(): PDO
{
    $host = getenv('DB_HOST') ?: 'db';
    $port = getenv('DB_PORT') ?: '5432';
    $name = getenv('DB_NAME') ?: 'subscription_reminder';
    $user = getenv('DB_USER') ?: 'subrem_user';
    $pass = getenv('DB_PASS') ?: 'subrem_pass';

    $dsn = "pgsql:host={$host};port={$port};dbname={$name}";

    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }

    return $pdo;
}
