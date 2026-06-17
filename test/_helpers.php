<?php
declare(strict_types=1);

const CMS_PLURALS = [
    'BlogPosting' => 'blog-postings',
    'Person' => 'persons',
    'Organization' => 'organizations',
    'WebPage' => 'web-pages',
    'ImageObject' => 'image-objects',
    'VideoObject' => 'video-objects',
    'AudioObject' => 'audio-objects',
    'CategoryCode' => 'category-codes',
    'CategoryCodeSet' => 'category-code-sets',
    'DefinedTerm' => 'defined-terms',
    'DefinedTermSet' => 'defined-term-sets',
    'Comment' => 'comments',
    'WebSite' => 'web-sites',
    'SiteNavigationElement' => 'site-navigation-elements',
];

const CMS_SAMPLES = [
    'BlogPosting' => [
        'headline' => 'sample',
        'articleBody' => 'sample',
        'author' => ['__ref' => 'Person'],
    ],
    'Person' => [
        'name' => 'sample',
    ],
    'Organization' => [
        'name' => 'sample',
    ],
    'WebPage' => [
        'headline' => 'sample',
    ],
    'ImageObject' => [
        'contentUrl' => 'https://example.com/x',
    ],
    'VideoObject' => [
        'contentUrl' => 'https://example.com/x',
    ],
    'AudioObject' => [
        'contentUrl' => 'https://example.com/x',
    ],
    'CategoryCode' => [
        'name' => 'sample',
        'codeValue' => 'sample',
        'inCodeSet' => ['__ref' => 'CategoryCodeSet'],
    ],
    'CategoryCodeSet' => [
        'name' => 'sample',
    ],
    'DefinedTerm' => [
        'name' => 'sample',
        'termCode' => 'sample',
        'inDefinedTermSet' => ['__ref' => 'DefinedTermSet'],
    ],
    'DefinedTermSet' => [
        'name' => 'sample',
    ],
    'Comment' => [
        'text' => 'sample',
        'author' => ['__ref' => 'Person'],
        'about' => ['__ref' => 'BlogPosting'],
    ],
    'WebSite' => [
        'name' => 'sample',
        'url' => 'https://example.com/x',
    ],
    'SiteNavigationElement' => [
        'name' => 'sample',
        'url' => 'https://example.com/x',
    ],
];

const CMS_ENTITIES = ['BlogPosting', 'Person', 'Organization', 'WebPage', 'ImageObject', 'VideoObject', 'AudioObject', 'CategoryCode', 'CategoryCodeSet', 'DefinedTerm', 'DefinedTermSet', 'Comment', 'WebSite', 'SiteNavigationElement'];

// Cookie names the admin server sets — kept in sync with src/Auth.php.
const CMS_SESSION_COOKIE = 'cms_session';
const CMS_CSRF_COOKIE = 'cms_csrf';

const CMS_ADMIN_USERNAME = 'admin';
const CMS_ADMIN_PASSWORD = 'admin-password';

$CMS_SEEDED = [];

function cms_free_port(): int
{
    // Ask the OS for a free port instead of guessing one. Tests run in
    // parallel; a guessed port from a fixed range collides (EADDRINUSE).
    $sock = @stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
    if ($sock === false) {
        throw new RuntimeException("Cannot allocate a free port: $errstr ($errno)");
    }
    $name = stream_socket_get_name($sock, false);
    fclose($sock);
    return (int) substr($name, strrpos($name, ':') + 1);
}

function cms_start_php_server(string $script, int $port, array $env, ?string $docRoot = null): array
{
    $repoRoot = realpath(__DIR__ . '/..');
    $docroot = $docRoot ?? ($repoRoot . '/public');
    $descriptors = [
        0 => ['file', '/dev/null', 'r'],
        1 => ['file', '/dev/null', 'w'],
        2 => ['file', '/dev/null', 'w'],
    ];
    $finalEnv = array_merge($_ENV, getenv(), $env);
    $cmd = sprintf('exec php -S 127.0.0.1:%d -t %s %s',
        $port, escapeshellarg($docroot), escapeshellarg($script));
    $proc = proc_open($cmd, $descriptors, $pipes, $repoRoot, $finalEnv);
    if (!is_resource($proc)) throw new RuntimeException("Failed to start: $cmd");

    $baseUrl = "http://127.0.0.1:$port";
    for ($i = 0; $i < 100; $i++) {
        $ch = curl_init($baseUrl . '/health');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 1);
        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($status === 200) {
            return ['proc' => $proc, 'pipes' => $pipes, 'baseUrl' => $baseUrl];
        }
        usleep(50_000);
    }
    proc_terminate($proc);
    proc_close($proc);
    throw new RuntimeException("Server at $baseUrl did not become healthy");
}

function cms_stop_php_server(array $server): void
{
    if (is_resource($server['proc'])) {
        proc_terminate($server['proc']);
        proc_close($server['proc']);
    }
    foreach ($server['pipes'] ?? [] as $pipe) {
        if (is_resource($pipe)) fclose($pipe);
    }
}

function cms_start_stack(): array
{
    $repoRoot = realpath(__DIR__ . '/..');
    $mockDocroot = sys_get_temp_dir() . '/cms-admin-mock-docroot-' . bin2hex(random_bytes(4));
    @mkdir($mockDocroot, 0755, true);
    $mockDataDir = sys_get_temp_dir() . '/cms-admin-mock-data-' . bin2hex(random_bytes(4));
    @mkdir($mockDataDir, 0755, true);

    $mockPort = cms_free_port();
    $mock = cms_start_php_server(
        $repoRoot . '/test/_mock_api.php',
        $mockPort,
        ['MOCK_DATA_DIR' => $mockDataDir],
        $mockDocroot,
    );

    $adminPort = cms_free_port();
    $admin = cms_start_php_server(
        $repoRoot . '/src/server.php',
        $adminPort,
        ['API_BASE_URL' => $mock['baseUrl']],
    );

    return [
        'mock' => $mock,
        'admin' => $admin,
        'mockDocroot' => $mockDocroot,
        'mockDataDir' => $mockDataDir,
        'apiBaseUrl' => $mock['baseUrl'],
        'adminBaseUrl' => $admin['baseUrl'],
    ];
}

function cms_stop_stack(array $stack): void
{
    cms_stop_php_server($stack['admin']);
    cms_stop_php_server($stack['mock']);
    if (isset($stack['mockDataDir']) && is_dir($stack['mockDataDir'])) {
        foreach (glob($stack['mockDataDir'] . '/*') as $f) @unlink($f);
        @rmdir($stack['mockDataDir']);
    }
    if (isset($stack['mockDocroot']) && is_dir($stack['mockDocroot'])) {
        @rmdir($stack['mockDocroot']);
    }
}

function cms_reset_seed_cache(): void
{
    global $CMS_SEEDED;
    $CMS_SEEDED = [];
}

// Low-level HTTP. Returns status, a lowercased single-value header map, an
// ordered list of every Set-Cookie line, and the raw body. Redirects are never
// followed so the tests can assert on 303 + Location.
function cms_http(string $method, string $url, ?string $body = null, array $headers = []): array
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }
    if (count($headers)) {
        $hdr = [];
        foreach ($headers as $k => $v) $hdr[] = "$k: $v";
        curl_setopt($ch, CURLOPT_HTTPHEADER, $hdr);
    }
    $response = curl_exec($ch);
    if ($response === false) {
        $err = curl_error($ch);
        throw new RuntimeException("HTTP request failed: $err");
    }
    $hsize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $rawHeaders = substr($response, 0, $hsize);
    $rawBody = substr($response, $hsize);
    $hdrs = [];
    $setCookies = [];
    foreach (explode("\r\n", $rawHeaders) as $line) {
        $parts = explode(': ', $line, 2);
        if (count($parts) !== 2) continue;
        $name = strtolower($parts[0]);
        if ($name === 'set-cookie') {
            $setCookies[] = $parts[1];
        } else {
            $hdrs[$name] = $parts[1];
        }
    }
    return ['status' => $status, 'headers' => $hdrs, 'setCookies' => $setCookies, 'body' => $rawBody];
}

// --- Cookie jar (a plain name -> value map) -------------------------------

function cms_apply_set_cookies(array &$jar, array $response): void
{
    foreach ($response['setCookies'] as $sc) {
        $pair = explode(';', $sc)[0];
        $idx = strpos($pair, '=');
        if ($idx === false) continue;
        $name = trim(substr($pair, 0, $idx));
        $value = trim(substr($pair, $idx + 1));
        if ($value === '') unset($jar[$name]); // Max-Age=0 clears with an empty value
        else $jar[$name] = $value;
    }
}

function cms_cookie_header(array $jar): string
{
    $pairs = [];
    foreach ($jar as $k => $v) $pairs[] = "$k=$v";
    return implode('; ', $pairs);
}

function cms_api_token(array $jar): ?string
{
    return $jar[CMS_SESSION_COOKIE] ?? null;
}

function cms_admin_get(array $stack, string $path, ?array &$jar = null): array
{
    $headers = ($jar !== null) ? ['Cookie' => cms_cookie_header($jar)] : [];
    $r = cms_http('GET', $stack['adminBaseUrl'] . $path, null, $headers);
    if ($jar !== null) cms_apply_set_cookies($jar, $r);
    return $r;
}

function cms_admin_post_form(array $stack, string $path, string $body, ?array &$jar = null, bool $withCsrf = true): array
{
    $finalBody = $body;
    if ($withCsrf && $jar !== null && isset($jar[CMS_CSRF_COOKIE])) {
        parse_str($finalBody, $parsed);
        if (!isset($parsed['_csrf'])) {
            $finalBody = ($finalBody !== '' ? $finalBody . '&' : '') . '_csrf=' . rawurlencode($jar[CMS_CSRF_COOKIE]);
        }
    }
    $headers = ['Content-Type' => 'application/x-www-form-urlencoded'];
    if ($jar !== null) $headers['Cookie'] = cms_cookie_header($jar);
    $r = cms_http('POST', $stack['adminBaseUrl'] . $path, $finalBody, $headers);
    if ($jar !== null) cms_apply_set_cookies($jar, $r);
    return $r;
}

// Full browser-like login: GET /login to obtain the csrf cookie, then POST the
// credentials. Returns a cookie jar carrying the session and csrf cookies.
function cms_login_admin(array $stack): array
{
    $jar = [];
    cms_admin_get($stack, '/login', $jar);
    $r = cms_admin_post_form(
        $stack, '/login',
        'username=' . rawurlencode(CMS_ADMIN_USERNAME) . '&password=' . rawurlencode(CMS_ADMIN_PASSWORD),
        $jar,
    );
    if ($r['status'] !== 303) {
        throw new RuntimeException('cms_login_admin failed: expected 303, got ' . $r['status']);
    }
    return $jar;
}

// A single logged-in cookie jar shared by every authenticated entity scenario.
// Built lazily on first use so the whole run logs in just once. Declared here
// (not per entity file) so the many *.admin.test.php files do not redeclare it.
$CMS_ADMIN_JAR = null;
function cms_admin_jar(array $stack): array
{
    global $CMS_ADMIN_JAR;
    if ($CMS_ADMIN_JAR === null) $CMS_ADMIN_JAR = cms_login_admin($stack);
    return $CMS_ADMIN_JAR;
}

// --- Seeding goes straight to the mock API with the admin bearer token -----

function cms_resolve_refs(array $stack, array $jar, array $sample): array
{
    $resolved = [];
    foreach ($sample as $k => $v) {
        if (is_array($v) && array_is_list($v)) {
            $out = [];
            foreach ($v as $vv) {
                if (is_array($vv) && isset($vv['__ref'])) {
                    $out[] = cms_ensure_entity($stack, $vv['__ref'], $jar);
                } else {
                    $out[] = $vv;
                }
            }
            $resolved[$k] = $out;
        } elseif (is_array($v) && isset($v['__ref'])) {
            $resolved[$k] = cms_ensure_entity($stack, $v['__ref'], $jar);
        } else {
            $resolved[$k] = $v;
        }
    }
    return $resolved;
}

function cms_seed_to_mock(array $stack, array $jar, string $entity, array $payload): string
{
    $r = cms_http(
        'POST',
        $stack['apiBaseUrl'] . '/' . CMS_PLURALS[$entity],
        json_encode($payload, JSON_UNESCAPED_SLASHES),
        ['Content-Type' => 'application/json', 'Authorization' => 'Bearer ' . cms_api_token($jar)],
    );
    if ($r['status'] !== 201) {
        throw new RuntimeException("cms_seed($entity) failed: {$r['status']} " . $r['body']);
    }
    $item = json_decode($r['body'], true);
    return $item['id'];
}

function cms_ensure_entity(array $stack, string $entity, array $jar): string
{
    global $CMS_SEEDED;
    if (isset($CMS_SEEDED[$entity])) return $CMS_SEEDED[$entity];
    $sample = cms_resolve_refs($stack, $jar, CMS_SAMPLES[$entity]);
    return $CMS_SEEDED[$entity] = cms_seed_to_mock($stack, $jar, $entity, $sample);
}

// Seed one fresh entity with chosen field overrides, bypassing the seed cache.
// Used to plant a hostile field value (e.g. a "javascript:" URL) and check how
// the admin renders it back.
function cms_seed_with(array $stack, string $entity, array $overrides, array $jar): string
{
    $sample = array_merge(cms_resolve_refs($stack, $jar, CMS_SAMPLES[$entity]), $overrides);
    return cms_seed_to_mock($stack, $jar, $entity, $sample);
}

function cms_encode_one(mixed $v): string
{
    if ($v === null) return '';
    if (is_array($v)) {
        if (isset($v['@type']) && $v['@type'] === 'Language') return (string) ($v['alternateName'] ?? '');
        return json_encode($v);
    }
    if (is_bool($v)) return $v ? 'true' : 'false';
    return (string) $v;
}

function cms_form_body_for(array $stack, string $entity, array $jar): string
{
    $sample = cms_resolve_refs($stack, $jar, CMS_SAMPLES[$entity]);
    $pairs = [];
    foreach ($sample as $key => $value) {
        if (is_array($value) && array_is_list($value)) {
            foreach ($value as $vv) {
                $pairs[] = rawurlencode($key) . '=' . rawurlencode(cms_encode_one($vv));
            }
        } else {
            $pairs[] = rawurlencode($key) . '=' . rawurlencode(cms_encode_one($value));
        }
    }
    return implode('&', $pairs);
}

function cms_assert(bool $cond, string $msg = 'assertion failed'): void { if (!$cond) throw new RuntimeException($msg); }
function cms_assert_equal(mixed $expected, mixed $actual, string $msg = ''): void
{
    if ($expected !== $actual) {
        $e = var_export($expected, true);
        $a = var_export($actual, true);
        throw new RuntimeException(($msg !== '' ? "$msg: " : '') . "expected $e, got $a");
    }
}
function cms_assert_match(string $regex, string $haystack, string $msg = ''): void
{
    if (preg_match($regex, $haystack) !== 1) {
        throw new RuntimeException(($msg !== '' ? "$msg: " : '') . "expected $regex to match");
    }
}
