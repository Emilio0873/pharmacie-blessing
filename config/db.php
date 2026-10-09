<?php
/**
 * Database Connection using PDO
 * Supports local XAMPP and Railway (MYSQL* / MYSQL_URL env vars).
 */

$charset = 'utf8mb4';

$mysqlUrl = getenv('MYSQL_URL') ?: getenv('DATABASE_URL') ?: '';
if ($mysqlUrl && preg_match('#mysql://([^:]+):([^@]*)@([^:/]+):?(\d+)?/([^?]+)#', $mysqlUrl, $m)) {
    $user = urldecode($m[1]);
    $pass = urldecode($m[2]);
    $host = $m[3];
    $port = $m[4] ?: '3306';
    $db   = urldecode($m[5]);
} else {
    $host = getenv('MYSQLHOST') ?: getenv('DB_HOST') ?: '127.0.0.1';
    $port = getenv('MYSQLPORT') ?: getenv('DB_PORT') ?: '3306';
    $db   = getenv('MYSQLDATABASE') ?: getenv('DB_NAME') ?: 'pharmacie_blessing';
    $user = getenv('MYSQLUSER') ?: getenv('DB_USER') ?: 'root';
    $pass = getenv('MYSQLPASSWORD') ?: getenv('DB_PASS');
    if ($pass === false) {
        $pass = '';
    }
}

$dsn = "mysql:host=$host;port=$port;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    throw new \PDOException($e->getMessage(), (int)$e->getCode());
}
?>
