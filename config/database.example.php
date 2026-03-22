<?php

function getConnection()
{
    static $conn = null;

    if ($conn !== null) {
        return $conn;
    }

    $databaseName = getenv('TASK_DB_NAME') ?: 'task_management_db';
    $sqlUser = getenv('TASK_DB_USER') ?: 'your_sql_user';
    $sqlPassword = getenv('TASK_DB_PASSWORD') ?: 'your_sql_password';

    $connectionProfiles = [
        [
            'Database' => $databaseName,
            'Uid' => $sqlUser,
            'PWD' => $sqlPassword,
            'CharacterSet' => 'UTF-8',
            'TrustServerCertificate' => true,
        ],
        [
            'Database' => $databaseName,
            'CharacterSet' => 'UTF-8',
            'TrustServerCertificate' => true,
        ],
    ];

    $serverCandidates = array_values(array_filter(array_unique([
        getenv('TASK_DB_SERVER') ?: null,
        'localhost',
        '.\\SQLEXPRESS',
        getenv('COMPUTERNAME') ?: null,
    ])));

    $connectionErrors = [];

    foreach ($serverCandidates as $serverName) {
        foreach ($connectionProfiles as $connectionOptions) {
            $conn = @sqlsrv_connect($serverName, $connectionOptions);

            if ($conn !== false) {
                return $conn;
            }

            $profileKey = isset($connectionOptions['Uid']) ? 'sql_auth' : 'windows_auth';
            $connectionErrors[$serverName . ':' . $profileKey] = sqlsrv_errors();
        }
    }

    error_log('SQL Server connection failed: ' . print_r($connectionErrors, true));
    die('<h3>Cannot connect to the database.</h3><p>Check the connection settings.</p>');
}
