<?php

function getConnection()
{
    static $conn = null;

    if ($conn !== null) {
        return $conn;
    }

    $serverName = getenv('TASK_DB_SERVER') ?: 'localhost';

    $connectionOptions = [
        'Database' => getenv('TASK_DB_NAME') ?: 'task_management_db',
        'Uid' => getenv('TASK_DB_USER') ?: 'your_sql_user',
        'PWD' => getenv('TASK_DB_PASSWORD') ?: 'your_sql_password',
        'CharacterSet' => 'UTF-8',
        'TrustServerCertificate' => true,
    ];

    $conn = sqlsrv_connect($serverName, $connectionOptions);

    if ($conn === false) {
        error_log('SQL Server connection failed: ' . print_r(sqlsrv_errors(), true));
        die('<h3>Cannot connect to the database.</h3><p>Check the connection settings.</p>');
    }

    return $conn;
}
