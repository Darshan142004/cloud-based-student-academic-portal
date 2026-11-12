<?php
/**
 * EduPulse Telemetry & Azure Application Insights Integration
 * Env-driven HTTP ingestion to Azure Monitor (/v2/track) with strict PII/secret redaction
 * and optional local NDJSON trace logging (APP_TELEMETRY_LOG) for offline verification.
 */

/**
 * Parse APPINSIGHTS_CONNECTION_STRING into InstrumentationKey + IngestionEndpoint.
 */
function epParseAppInsightsConnectionString(?string $connStr = null): ?array {
    $raw = $connStr !== null
        ? $connStr
        : (getenv('APPINSIGHTS_CONNECTION_STRING') ?: getenv('APPLICATIONINSIGHTS_CONNECTION_STRING') ?: '');
    $raw = trim((string) $raw);
    if ($raw === '') {
        return null;
    }

    $ikey = '';
    $endpoint = 'https://dc.services.visualstudio.com';
    foreach (explode(';', $raw) as $part) {
        $pos = strpos($part, '=');
        if ($pos === false) {
            continue;
        }
        $k = trim(substr($part, 0, $pos));
        $v = trim(substr($part, $pos + 1));
        if (strcasecmp($k, 'InstrumentationKey') === 0) {
            $ikey = $v;
        } elseif (strcasecmp($k, 'IngestionEndpoint') === 0 && $v !== '') {
            $endpoint = rtrim($v, '/');
        }
    }

    if ($ikey === '') {
        return null;
    }

    return [
        'instrumentation_key' => $ikey,
        'ingestion_endpoint' => $endpoint,
        'track_url' => $endpoint . '/v2/track',
    ];
}

/**
 * Recursively scrub PII and credentials before sending telemetry properties.
 */
function epTelemetryRedact(array $props): array {
    $sensitivePattern = '/(pass|pwd|secret|token|key|conn|auth|cookie|email|phone|mobile|address|ssn|dob)/i';
    $clean = [];
    foreach ($props as $k => $v) {
        $keyStr = (string) $k;
        if (preg_match($sensitivePattern, $keyStr)) {
            $clean[$keyStr] = '[REDACTED]';
            continue;
        }
        if (is_array($v)) {
            $clean[$keyStr] = epTelemetryRedact($v);
        } elseif (is_bool($v) || is_int($v) || is_float($v)) {
            $clean[$keyStr] = $v;
        } elseif ($v === null) {
            $clean[$keyStr] = null;
        } else {
            $str = (string) $v;
            $str = preg_replace('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', '[REDACTED_EMAIL]', $str);
            $str = preg_replace('/AccountKey=[^;]+/i', 'AccountKey=[REDACTED]', $str);
            $clean[$keyStr] = mb_strlen($str) > 300 ? (mb_substr($str, 0, 297) . '...') : $str;
        }
    }
    return $clean;
}

/**
 * Build a valid Application Insights v2 telemetry envelope.
 */
function epTelemetryBuildEnvelope(string $name, string $baseType, array $baseData, string $ikey = '00000000-0000-0000-0000-000000000000'): array {
    return [
        'name' => $name,
        'time' => gmdate('Y-m-d\TH:i:s.000\Z'),
        'iKey' => $ikey,
        'tags' => [
            'ai.cloud.role' => getenv('APP_SERVICE_NAME') ?: 'edupulse-web',
            'ai.cloud.roleInstance' => gethostname() ?: 'container',
        ],
        'data' => [
            'baseType' => $baseType,
            'baseData' => $baseData,
        ],
    ];
}

/**
 * Send or buffer telemetry envelope cleanly without blocking user traffic.
 */
function epTelemetryEmitEnvelope(array $envelope): bool {
    $envJson = json_encode($envelope, JSON_UNESCAPED_SLASHES);
    if ($envJson === false) {
        return false;
    }

    $localLog = trim((string) (getenv('APP_TELEMETRY_LOG') ?: ''));
    if ($localLog !== '') {
        @file_put_contents($localLog, $envJson . "\n", FILE_APPEND | LOCK_EX);
    }

    $cfg = epParseAppInsightsConnectionString();
    if (!$cfg || !function_exists('curl_init')) {
        return $localLog !== '';
    }

    $envelope['iKey'] = $cfg['instrumentation_key'];
    $payload = json_encode($envelope, JSON_UNESCAPED_SLASHES);
    $ch = @curl_init($cfg['track_url']);
    if (!$ch) {
        return false;
    }
    @curl_setopt($ch, CURLOPT_POST, true);
    @curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    @curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    @curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    @curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, 800);
    @curl_setopt($ch, CURLOPT_TIMEOUT_MS, 1500);
    @curl_exec($ch);
    $status = (int) @curl_getinfo($ch, CURLINFO_HTTP_CODE);
    @curl_close($ch);

    return $status >= 200 && $status < 300;
}

/**
 * Safe telemetry status for health checks without exposing instrumentation keys.
 */
function epTelemetryStatus(): array {
    $cfg = epParseAppInsightsConnectionString();
    $localLog = trim((string) (getenv('APP_TELEMETRY_LOG') ?: ''));
    return [
        'provider' => $cfg ? 'azure_app_insights' : ($localLog !== '' ? 'local_ndjson' : 'disabled'),
        'configured' => $cfg !== null,
        'endpoint' => $cfg['ingestion_endpoint'] ?? null,
    ];
}

function epTelemetryTrackRequest(string $name, int $statusCode, float $durationMs, array $props = []): bool {
    $safeProps = epTelemetryRedact(array_merge([
        'env' => getenv('APP_ENV') ?: 'development',
        'method' => $_SERVER['REQUEST_METHOD'] ?? 'CLI',
    ], $props));

    $totalMs = max(0, (int) round($durationMs));
    $secs = intdiv($totalMs, 1000);
    $ms = $totalMs % 1000;
    $durationStr = sprintf('00:%02d:%02d.%03d', intdiv($secs, 60), $secs % 60, $ms);

    $env = epTelemetryBuildEnvelope('Microsoft.ApplicationInsights.Request', 'RequestData', [
        'ver' => 2,
        'id' => bin2hex(random_bytes(8)),
        'name' => $name,
        'duration' => $durationStr,
        'responseCode' => (string) $statusCode,
        'success' => $statusCode < 500,
        'properties' => $safeProps,
    ]);
    return epTelemetryEmitEnvelope($env);
}

function epTelemetryTrackTrace(string $message, int $severityLevel = 1, array $props = []): bool {
    $redacted = epTelemetryRedact(['msg' => $message] + $props);
    $safeMessage = (string) ($redacted['msg'] ?? $message);
    unset($redacted['msg']);

    $env = epTelemetryBuildEnvelope('Microsoft.ApplicationInsights.Message', 'MessageData', [
        'ver' => 2,
        'message' => $safeMessage,
        'severityLevel' => $severityLevel,
        'properties' => $redacted,
    ]);
    return epTelemetryEmitEnvelope($env);
}

function epTelemetryTrackException(Throwable $ex, array $props = []): bool {
    $safeProps = epTelemetryRedact($props);
    $env = epTelemetryBuildEnvelope('Microsoft.ApplicationInsights.Exception', 'ExceptionData', [
        'ver' => 2,
        'exceptions' => [[
            'typeName' => get_class($ex),
            'message' => mb_substr($ex->getMessage(), 0, 500),
            'hasFullStack' => false,
        ]],
        'severityLevel' => 3,
        'properties' => $safeProps,
    ]);
    return epTelemetryEmitEnvelope($env);
}

/**
 * Register lightweight per-request shutdown/exception telemetry when telemetry is configured.
 */
function epRegisterTelemetryHooks(): void {
    static $registered = false;
    if ($registered || php_sapi_name() === 'cli') {
        return;
    }
    $registered = true;

    $cfg = epParseAppInsightsConnectionString();
    $localLog = trim((string) (getenv('APP_TELEMETRY_LOG') ?: ''));
    if (!$cfg && $localLog === '') {
        return;
    }

    $start = microtime(true);
    set_exception_handler(static function (Throwable $ex): void {
        epTelemetryTrackException($ex, ['uri' => $_SERVER['SCRIPT_NAME'] ?? 'unknown']);
        error_log('[EduPulse Uncaught Exception] ' . $ex->getMessage());
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json');
        }
        echo json_encode(['success' => false, 'message' => 'Internal server error']);
    });

    register_shutdown_function(static function () use ($start): void {
        $code = http_response_code();
        if (!is_int($code) || $code <= 0) {
            $code = 200;
        }
        $lastErr = error_get_last();
        if ($lastErr && in_array($lastErr['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            epTelemetryTrackTrace('Fatal PHP error: ' . $lastErr['message'], 4, [
                'file' => basename($lastErr['file']),
                'line' => $lastErr['line'],
            ]);
            $code = 500;
        }
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? ($_SERVER['SCRIPT_NAME'] ?? '/'), PHP_URL_PATH) ?: '/';
        epTelemetryTrackRequest($uri, $code, (microtime(true) - $start) * 1000.0);
    });
}

