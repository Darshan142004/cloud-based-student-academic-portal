<?php
require_once '../includes/telemetry.php';
epRegisterTelemetryHooks();
require_once '../includes/storage_service.php';
require_once '../config/database.php';

header('Content-Type: application/json');

$dbOk = false;
$tablesOk = false;
$errorMsg = null;

if (isset($conn) && $conn instanceof mysqli) {
    $ping = @mysqli_query($conn, 'SELECT 1');
    if ($ping) {
        $dbOk = true;
        mysqli_free_result($ping);
    }
    $chk = @mysqli_query($conn, "SHOW TABLES LIKE 'students'");
    if ($chk) {
        $tablesOk = mysqli_num_rows($chk) > 0;
        mysqli_free_result($chk);
    }
    @mysqli_close($conn);
} else {
    $errorMsg = 'Database handle unavailable';
}

$storage = epStorageStatus();
$telemetry = epTelemetryStatus();
$healthy = $dbOk && $tablesOk && $storage['configured'];

http_response_code($healthy ? 200 : 503);
echo json_encode([
    'success' => $healthy,
    'status' => $healthy ? 'healthy' : 'degraded',
    'service' => getenv('APP_SERVICE_NAME') ?: 'edupulse-web',
    'environment' => getenv('APP_ENV') ?: 'development',
    'php_version' => PHP_VERSION,
    'checks' => [
        'database' => [
            'connected' => $dbOk,
            'schema_ready' => $tablesOk,
            'ssl_mode' => strtolower(trim((string) (getenv('DB_SSL_MODE') ?: 'disabled'))),
        ],
        'storage' => $storage,
        'telemetry' => $telemetry,
    ],
    'error' => $errorMsg,
    'timestamp_utc' => gmdate('Y-m-d\TH:i:s\Z'),
]);

