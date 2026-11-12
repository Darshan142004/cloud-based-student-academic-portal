<?php
/**
 * EduPulse Storage Abstraction Layer
 * Supports both local filesystem storage (STORAGE_DRIVER=local, default for dev)
 * and Azure Blob Storage via REST API with SharedKey HMAC-SHA256 signing (STORAGE_DRIVER=azure).
 * Zero third-party SDK dependencies; all secrets read exclusively from environment variables.
 */

if (!defined('EP_AZURE_API_VERSION')) {
    define('EP_AZURE_API_VERSION', '2021-12-02');
}

/**
 * Resolve normalized active storage driver ('local' or 'azure').
 */
function epStorageDriver(): string {
    $driver = strtolower(trim((string) (getenv('STORAGE_DRIVER') ?: 'local')));
    return $driver === 'azure' ? 'azure' : 'local';
}

/**
 * Project root directory containing `uploads/`.
 */
function epStorageProjectRoot(): string {
    return realpath(__DIR__ . '/../..') ?: (__DIR__ . '/../..');
}

/**
 * Parse an Azure Storage connection string and/or separate env vars into structured config.
 * Never embeds credentials in code; returns null if incomplete.
 */
function epParseAzureStorageConfig(): ?array {
    $connStr = trim((string) (getenv('AZURE_STORAGE_CONNECTION_STRING') ?: ''));
    $account = trim((string) (getenv('AZURE_STORAGE_ACCOUNT') ?: ''));
    $key = trim((string) (getenv('AZURE_STORAGE_KEY') ?: ''));
    $container = trim((string) (getenv('AZURE_STORAGE_CONTAINER') ?: 'edupulse-files'));
    $endpoint = trim((string) (getenv('AZURE_STORAGE_BLOB_ENDPOINT') ?: ''));
    $protocol = 'https';
    $suffix = 'core.windows.net';

    if ($connStr !== '') {
        foreach (explode(';', $connStr) as $part) {
            $pos = strpos($part, '=');
            if ($pos === false) {
                continue;
            }
            $k = trim(substr($part, 0, $pos));
            $v = trim(substr($part, $pos + 1));
            if (strcasecmp($k, 'AccountName') === 0) {
                $account = $v;
            } elseif (strcasecmp($k, 'AccountKey') === 0) {
                $key = $v;
            } elseif (strcasecmp($k, 'EndpointSuffix') === 0 && $v !== '') {
                $suffix = $v;
            } elseif (strcasecmp($k, 'DefaultEndpointsProtocol') === 0 && $v !== '') {
                $protocol = strtolower($v);
            } elseif (strcasecmp($k, 'BlobEndpoint') === 0 && $v !== '') {
                $endpoint = rtrim($v, '/');
            }
        }
    }

    if ($account === '' || $key === '') {
        return null;
    }

    $container = preg_replace('/[^a-z0-9-]/', '', strtolower($container)) ?: 'edupulse-files';
    if ($endpoint === '') {
        $endpoint = "{$protocol}://{$account}.blob.{$suffix}";
    }

    return [
        'account' => $account,
        'key' => $key,
        'container' => $container,
        'endpoint' => rtrim($endpoint, '/'),
    ];
}

/**
 * Return safe, secret-free configuration diagnostics for health checks.
 */
function epStorageStatus(): array {
    $driver = epStorageDriver();
    $cfg = $driver === 'azure' ? epParseAzureStorageConfig() : null;
    return [
        'driver' => $driver,
        'configured' => $driver === 'local' ? is_dir(epStorageProjectRoot() . '/uploads') : ($cfg !== null),
        'container' => $cfg['container'] ?? null,
    ];
}

/**
 * Validate uploaded file against allowed extensions, detected MIME types, and max bytes.
 * Returns ['ok' => bool, 'error' => ?string, 'ext' => string, 'mime' => string, 'size' => int, 'original_name' => string].
 */
function epStorageValidateUpload(array $file, array $allowedExts, array $allowedMimes, int $maxBytes): array {
    if (!isset($file['error']) || (int) $file['error'] !== UPLOAD_ERR_OK) {
        $code = (int) ($file['error'] ?? -1);
        $msg = match ($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Uploaded file exceeds server upload size limit.',
            UPLOAD_ERR_NO_FILE => 'No file was selected.',
            default => 'File upload failed with error code ' . $code . '.',
        };
        return ['ok' => false, 'error' => $msg];
    }

    $size = (int) ($file['size'] ?? 0);
    if ($size <= 0 || $size > $maxBytes) {
        $mb = round($maxBytes / (1024 * 1024), 1);
        return ['ok' => false, 'error' => "File size must be between 1 byte and {$mb} MB."];
    }

    $tmpPath = (string) ($file['tmp_name'] ?? '');
    if ($tmpPath === '' || !is_file($tmpPath)) {
        return ['ok' => false, 'error' => 'Uploaded temporary file is missing.'];
    }

    $origName = basename((string) ($file['name'] ?? 'upload.bin'));
    $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExts, true)) {
        return ['ok' => false, 'error' => 'Unsupported file extension. Allowed: ' . implode(', ', $allowedExts) . '.'];
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $detectedMime = $finfo ? (string) finfo_file($finfo, $tmpPath) : '';
    if ($finfo) {
        finfo_close($finfo);
    }

    $blockedMimes = [
        'text/x-php',
        'application/x-httpd-php',
        'text/html',
        'application/javascript',
        'text/javascript',
        'application/x-executable',
        'application/x-dosexec',
        'application/x-sh',
    ];
    if ($detectedMime !== '' && in_array($detectedMime, $blockedMimes, true)) {
        return ['ok' => false, 'error' => 'Executable or script file content is not allowed (' . $detectedMime . ').'];
    }

    $genericOkExts = ['txt', 'doc', 'docx', 'zip'];
    $allowGenericFallback = ($detectedMime === 'application/octet-stream' && in_array($ext, $genericOkExts, true));

    if ($detectedMime === '' || (!$allowGenericFallback && !in_array($detectedMime, $allowedMimes, true))) {
        return ['ok' => false, 'error' => 'Uploaded file content type is not allowed (' . ($detectedMime ?: 'unknown') . ').'];
    }

    return [
        'ok' => true,
        'error' => null,
        'ext' => $ext === 'jpeg' ? 'jpg' : $ext,
        'mime' => $detectedMime,
        'size' => $size,
        'original_name' => preg_replace('/[^A-Za-z0-9._-]/', '_', $origName),
    ];
}

/**
 * Generate a collision-resistant, path-traversal-safe storage key within a category folder.
 */
function epStorageGenerateKey(string $category, string $prefix, string $ext): string {
    $safeCategory = preg_replace('/[^a-z0-9_]/', '', strtolower($category)) ?: 'documents';
    $safePrefix = preg_replace('/[^A-Za-z0-9_-]/', '_', $prefix) ?: 'file';
    $safeExt = preg_replace('/[^a-z0-9]/', '', strtolower($ext)) ?: 'bin';
    $rand = bin2hex(random_bytes(4));
    return "{$safeCategory}/{$safePrefix}_" . time() . "_{$rand}.{$safeExt}";
}

/**
 * Build Azure Storage SharedKey authorization header for Blob REST API requests.
 */
function epAzureBuildSharedKeyAuth(
    string $account,
    string $base64Key,
    string $method,
    string $canonicalResource,
    array $headers,
    int $contentLength = 0,
    string $contentType = ''
): string {
    $msHeaders = [];
    foreach ($headers as $k => $v) {
        $lk = strtolower(trim($k));
        if (str_starts_with($lk, 'x-ms-')) {
            $msHeaders[$lk] = preg_replace('/\s+/', ' ', trim((string) $v));
        }
    }
    ksort($msHeaders);
    $canonicalizedHeaders = '';
    foreach ($msHeaders as $k => $v) {
        $canonicalizedHeaders .= "{$k}:{$v}\n";
    }

    $lenStr = $contentLength > 0 ? (string) $contentLength : '';
    $stringToSign = strtoupper($method) . "\n"
        . "\n" // Content-Encoding
        . "\n" // Content-Language
        . $lenStr . "\n" // Content-Length
        . "\n" // Content-MD5
        . $contentType . "\n" // Content-Type
        . "\n" // Date
        . "\n" // If-Modified-Since
        . "\n" // If-Match
        . "\n" // If-None-Match
        . "\n" // If-Unmodified-Since
        . "\n" // Range
        . $canonicalizedHeaders
        . $canonicalResource;

    $decodedKey = base64_decode($base64Key, true);
    $sig = base64_encode(hash_hmac('sha256', $stringToSign, $decodedKey !== false ? $decodedKey : $base64Key, true));
    return "SharedKey {$account}:{$sig}";
}

/**
 * Generate a short-lived read-only Service SAS URL for an Azure Blob object.
 */
function epAzureGenerateBlobSasUrl(array $cfg, string $blobKey, int $ttlSeconds = 900): string {
    $blobKey = ltrim($blobKey, '/');
    $now = gmdate('Y-m-d\TH:i:s\Z', time() - 60);
    $expiry = gmdate('Y-m-d\TH:i:s\Z', time() + $ttlSeconds);
    $canonicalResource = "/blob/{$cfg['account']}/{$cfg['container']}/{$blobKey}";

    $stringToSign = "r\n" // sp (signed permissions)
        . "{$now}\n"        // st
        . "{$expiry}\n"     // se
        . "{$canonicalResource}\n"
        . "\n"              // signedIdentifier
        . "\n"              // signedIP
        . "https\n"         // spr
        . EP_AZURE_API_VERSION . "\n" // sv
        . "b\n"             // sr (blob)
        . "\n"              // snapshot
        . "\n\n\n\n\n";     // rscc, rscd, rsce, rscl, rsct

    $decodedKey = base64_decode($cfg['key'], true);
    $sig = base64_encode(hash_hmac('sha256', $stringToSign, $decodedKey !== false ? $decodedKey : $cfg['key'], true));

    $query = http_build_query([
        'sv' => EP_AZURE_API_VERSION,
        'sr' => 'b',
        'sp' => 'r',
        'st' => $now,
        'se' => $expiry,
        'spr' => 'https',
        'sig' => $sig,
    ]);

    return "{$cfg['endpoint']}/{$cfg['container']}/{$blobKey}?{$query}";
}

/**
 * Execute an authenticated HTTP call against Azure Blob REST API.
 */
function epAzureBlobRequest(array $cfg, string $method, string $blobKey, string $body = '', string $contentType = ''): array {
    $blobKey = ltrim($blobKey, '/');
    $encodedKey = implode('/', array_map('rawurlencode', explode('/', $blobKey)));
    $url = "{$cfg['endpoint']}/{$cfg['container']}/{$encodedKey}";
    $date = gmdate('D, d M Y H:i:s \G\M\T');
    $len = strlen($body);

    $msHeaders = [
        'x-ms-date' => $date,
        'x-ms-version' => EP_AZURE_API_VERSION,
    ];
    if (strtoupper($method) === 'PUT') {
        $msHeaders['x-ms-blob-type'] = 'BlockBlob';
    }

    // Extract path for Azurite / custom BlobEndpoint compatibility
    $endpointPath = parse_url($cfg['endpoint'], PHP_URL_PATH) ?: '';
    $canonicalResource = "/{$cfg['account']}{$endpointPath}/{$cfg['container']}/{$blobKey}";

    $authHeader = epAzureBuildSharedKeyAuth(
        $cfg['account'],
        $cfg['key'],
        $method,
        $canonicalResource,
        $msHeaders,
        $len,
        $contentType
    );

    $httpHeaders = [
        'Authorization: ' . $authHeader,
        'x-ms-date: ' . $date,
        'x-ms-version: ' . EP_AZURE_API_VERSION,
    ];
    if (strtoupper($method) === 'PUT') {
        $httpHeaders[] = 'x-ms-blob-type: BlockBlob';
        $httpHeaders[] = 'Content-Length: ' . $len;
        if ($contentType !== '') {
            $httpHeaders[] = 'Content-Type: ' . $contentType;
        }
    }

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $httpHeaders);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    if (strtoupper($method) === 'PUT') {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }

    $respBody = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    return [
        'ok' => $curlErr === '' && $status >= 200 && $status < 300,
        'status' => $status,
        'body' => is_string($respBody) ? $respBody : '',
        'error' => $curlErr !== '' ? $curlErr : ($status >= 400 ? "HTTP {$status}" : null),
    ];
}

/**
 * Store a validated uploaded file under the active storage backend ('local' or 'azure').
 * Returns ['ok' => bool, 'provider' => 'local'|'azure_blob', 'storage_key' => string, 'local_path' => string, 'error' => ?string].
 */
function epStoragePutUploadedFile(string $tmpPath, string $storageKey, string $mimeType): array {
    $storageKey = ltrim($storageKey, '/');
    if (str_contains($storageKey, '..')) {
        return ['ok' => false, 'error' => 'Invalid storage key.'];
    }

    if (epStorageDriver() === 'azure') {
        $cfg = epParseAzureStorageConfig();
        if (!$cfg) {
            return ['ok' => false, 'error' => 'Azure Blob Storage credentials are not configured in environment variables.'];
        }
        $bytes = @file_get_contents($tmpPath);
        if ($bytes === false) {
            return ['ok' => false, 'error' => 'Unable to read uploaded temporary file.'];
        }
        $res = epAzureBlobRequest($cfg, 'PUT', $storageKey, $bytes, $mimeType);
        if (!$res['ok']) {
            return ['ok' => false, 'error' => 'Azure Blob upload failed: ' . ($res['error'] ?: 'Unknown error')];
        }
        return [
            'ok' => true,
            'provider' => 'azure_blob',
            'storage_key' => $storageKey,
            'local_path' => 'uploads/' . $storageKey,
            'error' => null,
        ];
    }

    $root = epStorageProjectRoot();
    $dest = $root . '/uploads/' . $storageKey;
    $dir = dirname($dest);
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
        return ['ok' => false, 'error' => 'Failed to create upload directory.'];
    }

    $moved = is_uploaded_file($tmpPath) ? @move_uploaded_file($tmpPath, $dest) : @rename($tmpPath, $dest);
    if (!$moved && @copy($tmpPath, $dest)) {
        $moved = true;
    }
    if (!$moved) {
        return ['ok' => false, 'error' => 'Failed to write file to local storage.'];
    }

    return [
        'ok' => true,
        'provider' => 'local',
        'storage_key' => $storageKey,
        'local_path' => 'uploads/' . $storageKey,
        'error' => null,
    ];
}

/**
 * Retrieve raw bytes from storage ('local' or 'azure_blob') for authenticated streaming.
 */
function epStorageReadObject(string $storageKey, ?string $provider = null): array {
    $storageKey = ltrim($storageKey, '/');
    if (str_starts_with($storageKey, 'uploads/')) {
        $storageKey = substr($storageKey, strlen('uploads/'));
    }
    if ($storageKey === '' || str_contains($storageKey, '..')) {
        return ['ok' => false, 'error' => 'Invalid storage key.'];
    }

    $useProvider = $provider ?: (epStorageDriver() === 'azure' ? 'azure_blob' : 'local');
    if ($useProvider === 'azure_blob') {
        $cfg = epParseAzureStorageConfig();
        if (!$cfg) {
            return ['ok' => false, 'error' => 'Azure Blob Storage is not configured.'];
        }
        $res = epAzureBlobRequest($cfg, 'GET', $storageKey);
        if (!$res['ok']) {
            return ['ok' => false, 'error' => 'Blob not found or inaccessible.'];
        }
        return ['ok' => true, 'bytes' => $res['body']];
    }

    $root = epStorageProjectRoot();
    $uploadsRoot = realpath($root . '/uploads');
    $full = realpath($root . '/uploads/' . $storageKey);
    if (!$uploadsRoot || !$full || !str_starts_with($full, $uploadsRoot . DIRECTORY_SEPARATOR) || !is_file($full)) {
        return ['ok' => false, 'error' => 'File not found on local storage.'];
    }
    $bytes = @file_get_contents($full);
    if ($bytes === false) {
        return ['ok' => false, 'error' => 'Failed to read local file.'];
    }
    return ['ok' => true, 'bytes' => $bytes];
}

/**
 * Delete a stored file safely from either local disk or Azure Blob Storage.
 */
function epStorageDeleteObject(string $storageKey, ?string $provider = null): bool {
    $storageKey = ltrim($storageKey, '/');
    if (str_starts_with($storageKey, 'uploads/')) {
        $storageKey = substr($storageKey, strlen('uploads/'));
    }
    if ($storageKey === '' || str_contains($storageKey, '..')) {
        return false;
    }

    $useProvider = $provider ?: (str_starts_with($storageKey, 'azure:') ? 'azure_blob' : (epStorageDriver() === 'azure' ? 'azure_blob' : 'local'));
    if (str_starts_with($storageKey, 'azure:')) {
        $storageKey = substr($storageKey, strlen('azure:'));
    }

    if ($useProvider === 'azure_blob') {
        $cfg = epParseAzureStorageConfig();
        if (!$cfg) {
            return false;
        }
        $res = epAzureBlobRequest($cfg, 'DELETE', $storageKey);
        return $res['ok'] || $res['status'] === 404;
    }

    $root = epStorageProjectRoot();
    $uploadsRoot = realpath($root . '/uploads');
    $full = realpath($root . '/uploads/' . $storageKey);
    if ($uploadsRoot && $full && str_starts_with($full, $uploadsRoot . DIRECTORY_SEPARATOR) && is_file($full)) {
        return @unlink($full);
    }
    return false;
}

/**
 * Check whether an object exists in the specified/inferred storage provider.
 */
function epStorageObjectExists(string $storageKey, ?string $provider = null): bool {
    $storageKey = ltrim($storageKey, '/');
    if (str_starts_with($storageKey, 'uploads/')) {
        $storageKey = substr($storageKey, strlen('uploads/'));
    }
    if ($storageKey === '' || str_contains($storageKey, '..')) {
        return false;
    }
    $useProvider = $provider ?: (str_starts_with($storageKey, 'azure:') ? 'azure_blob' : 'local');
    if (str_starts_with($storageKey, 'azure:')) {
        return true; // Remote Azure blob keys are validated on retrieval / SAS signing
    }
    if ($useProvider === 'azure_blob') {
        return true;
    }

    $root = epStorageProjectRoot();
    $uploadsRoot = realpath($root . '/uploads');
    $full = realpath($root . '/uploads/' . $storageKey);
    return (bool) ($uploadsRoot && $full && str_starts_with($full, $uploadsRoot . DIRECTORY_SEPARATOR) && is_file($full));
}

/**
 * Generate an inline SVG initials avatar data URI so fallback never breaks even offline.
 */
function epDefaultAvatarDataUri(string $name = 'Student'): string {
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $initials = '';
    foreach ($parts as $p) {
        if ($p !== '') {
            $initials .= mb_strtoupper(mb_substr($p, 0, 1));
        }
        if (mb_strlen($initials) >= 2) {
            break;
        }
    }
    if ($initials === '') {
        $initials = 'S';
    }
    $safe = htmlspecialchars($initials, ENT_QUOTES, 'UTF-8');
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="128" height="128" viewBox="0 0 128 128">'
        . '<rect width="128" height="128" rx="64" fill="#0F52BA"/>'
        . '<text x="50%" y="54%" dominant-baseline="middle" text-anchor="middle" '
        . 'fill="#FFFFFF" font-family="Arial,Helvetica,sans-serif" font-size="48" font-weight="700">'
        . $safe . '</text></svg>';
    return 'data:image/svg+xml;charset=UTF-8,' . rawurlencode($svg);
}

/**
 * Resolve a student's canonical authenticated profile photo URL across local and Azure storage,
 * returning null when no valid custom image exists.
 */
function epResolveProfilePhotoUrl(?string $profilePhoto): ?string {
    $pp = trim((string) $profilePhoto);
    if ($pp === '' || $pp === 'default-avatar.png') {
        return null;
    }
    if (str_starts_with($pp, 'azure:')) {
        return 'backend/api/download_document.php?type=profile&v=' . substr(md5($pp), 0, 10);
    }
    $storageKey = 'profile_photos/' . ltrim($pp, '/');
    if (!epStorageObjectExists($storageKey, 'local')) {
        return null;
    }
    return 'backend/api/download_document.php?type=profile&v=' . substr(md5($pp), 0, 10);
}

/**
 * Resolve either the authenticated profile photo endpoint or a crisp SVG default avatar fallback.
 */
function epGetStudentAvatarUrl(?string $profilePhoto, string $studentName = 'Student'): string {
    return epResolveProfilePhotoUrl($profilePhoto) ?? epDefaultAvatarDataUri($studentName);
}

