<?php

$localEnv = __DIR__ . '/local.env.php';
if (file_exists($localEnv)) {
    require_once $localEnv;
}

$host = getenv('DB_HOST') ?: 'localhost';
$port = (int) (getenv('DB_PORT') ?: 3306);
$username = getenv('DB_USER') ?: 'root';
$password = getenv('DB_PASS') ?: '';
$database = getenv('DB_NAME') ?: 'student_portal';
$sslMode = strtolower(trim((string) (getenv('DB_SSL_MODE') ?: 'disabled'))); // disabled|preferred|required|verify_ca
$sslCa = trim((string) (getenv('DB_SSL_CA') ?: ''));

/**
 * Helper to open a mysqli connection supporting optional TLS for Azure Database for MySQL.
 */
function epOpenDatabaseConnection(
    string $host,
    int $port,
    string $username,
    string $password,
    string $database,
    string $sslMode = 'disabled',
    string $sslCa = ''
): mysqli {
    $mysqli = mysqli_init();
    if (!$mysqli) {
        throw new RuntimeException('Failed to initialize mysqli.');
    }

    mysqli_options($mysqli, MYSQLI_OPT_CONNECT_TIMEOUT, 10);

    $flags = 0;
    if ($sslMode !== '' && $sslMode !== 'disabled') {
        $caPath = null;
        if ($sslCa !== '' && is_file($sslCa)) {
            $caPath = $sslCa;
        } elseif (is_file('/etc/ssl/certs/ca-certificates.crt')) {
            $caPath = '/etc/ssl/certs/ca-certificates.crt';
        }

        mysqli_ssl_set($mysqli, null, null, $caPath, null, null);
        $flags = MYSQLI_CLIENT_SSL;
        if ($sslMode === 'preferred' || ($sslMode === 'required' && $sslCa === '')) {
            $flags |= MYSQLI_CLIENT_SSL_DONT_VERIFY_SERVER_CERT;
        }
    }

    $connected = @mysqli_real_connect($mysqli, $host, $username, $password, $database, $port, null, $flags);
    if (!$connected) {
        $err = mysqli_connect_error() ?: 'Connection refused';
        throw new RuntimeException('Database connection failed: ' . $err);
    }

    mysqli_set_charset($mysqli, 'utf8mb4');
    return $mysqli;
}

try {
    $conn = epOpenDatabaseConnection($host, $port, $username, $password, $database, $sslMode, $sslCa);
} catch (Throwable $e) {
    error_log('[EduPulse DB Error] ' . $e->getMessage());
    http_response_code(503);
    die('Database connection failed.');
}

