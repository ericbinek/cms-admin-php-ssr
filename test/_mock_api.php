<?php
// Auth-aware mock of the CMS API for admin conformance tests. It mirrors the real
// API's wire envelope ({items,total}, {id,...item}, {status,error,message,
// details,path}) AND its auth contract: /auth/login issues an opaque bearer
// token, /auth/me and /auth/logout validate it, and every entity route requires
// a live session (401 without). RBAC is the real API's job; here the seeded admin
// has full access, which is enough to prove the admin frontend's cookie-to-bearer
// proxy and CSRF handling. The PHP cli-server re-runs this script per request, so
// store, sessions and the seeded admin account live as JSON under $MOCK_DATA_DIR.

declare(strict_types=1);

const SCHEMAS = [
    'BlogPosting' => ['plural' => 'blog-postings', 'required' => ['headline', 'articleBody', 'author']],
    'Person' => ['plural' => 'persons', 'required' => ['name']],
    'Organization' => ['plural' => 'organizations', 'required' => ['name']],
    'WebPage' => ['plural' => 'web-pages', 'required' => ['headline']],
    'ImageObject' => ['plural' => 'image-objects', 'required' => ['contentUrl']],
    'VideoObject' => ['plural' => 'video-objects', 'required' => ['contentUrl']],
    'AudioObject' => ['plural' => 'audio-objects', 'required' => ['contentUrl']],
    'CategoryCode' => ['plural' => 'category-codes', 'required' => ['name', 'codeValue', 'inCodeSet']],
    'CategoryCodeSet' => ['plural' => 'category-code-sets', 'required' => ['name']],
    'DefinedTerm' => ['plural' => 'defined-terms', 'required' => ['name', 'termCode', 'inDefinedTermSet']],
    'DefinedTermSet' => ['plural' => 'defined-term-sets', 'required' => ['name']],
    'Comment' => ['plural' => 'comments', 'required' => ['text', 'author', 'about']],
    'WebSite' => ['plural' => 'web-sites', 'required' => ['name', 'url']],
    'SiteNavigationElement' => ['plural' => 'site-navigation-elements', 'required' => ['name', 'url']],
];

const ADMIN_USERNAME = 'admin';
const ADMIN_PASSWORD = 'admin-password';

function mock_data_dir(): string
{
    $dir = getenv('MOCK_DATA_DIR') ?: (sys_get_temp_dir() . '/cms-admin-mock-' . posix_getpid());
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    return $dir;
}

function mock_uuid(): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    $hex = bin2hex($bytes);
    return sprintf('%s-%s-%s-%s-%s', substr($hex, 0, 8), substr($hex, 8, 4), substr($hex, 12, 4), substr($hex, 16, 4), substr($hex, 20, 12));
}

// The seeded admin account, persisted so its id is stable across requests.
function mock_admin_account(): array
{
    $path = mock_data_dir() . '/account.json';
    if (file_exists($path)) {
        $decoded = json_decode((string) @file_get_contents($path), true);
        if (is_array($decoded) && isset($decoded['id'])) return $decoded;
    }
    $account = ['id' => mock_uuid(), 'username' => ADMIN_USERNAME, 'role' => 'admin'];
    file_put_contents($path, json_encode($account, JSON_UNESCAPED_SLASHES));
    return $account;
}

function mock_sessions_read(): array
{
    $path = mock_data_dir() . '/sessions.json';
    if (!file_exists($path)) return [];
    $decoded = json_decode((string) @file_get_contents($path), true);
    return is_array($decoded) ? $decoded : [];
}

function mock_sessions_write(array $sessions): void
{
    file_put_contents(mock_data_dir() . '/sessions.json', json_encode($sessions, JSON_UNESCAPED_SLASHES));
}

function mock_entity_by_plural(string $plural): ?string
{
    foreach (SCHEMAS as $name => $s) {
        if ($s['plural'] === $plural) return $name;
    }
    return null;
}

function mock_read(string $entity): array
{
    $path = mock_data_dir() . '/' . SCHEMAS[$entity]['plural'] . '.json';
    if (!file_exists($path)) return [];
    $content = @file_get_contents($path);
    if ($content === false || $content === '') return [];
    $decoded = json_decode($content, true);
    return is_array($decoded) ? $decoded : [];
}

function mock_write(string $entity, array $items): void
{
    $path = mock_data_dir() . '/' . SCHEMAS[$entity]['plural'] . '.json';
    file_put_contents($path, json_encode(array_values($items), JSON_UNESCAPED_SLASHES));
}

function mock_json(int $status, mixed $data): void
{
    if ($status === 204) { http_response_code(204); return; }
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    if ($data !== null) echo json_encode($data, JSON_UNESCAPED_SLASHES);
}

function mock_error(int $status, string $code, string $message, array $details, string $path): array
{
    return ['status' => $status, 'error' => $code, 'message' => $message, 'details' => $details, 'path' => $path];
}

function mock_unauthorized(string $path): array
{
    return mock_error(401, 'UNAUTHORIZED', 'Authentication is required, or the session is invalid or expired.', [], $path);
}

function mock_bearer_token(): ?string
{
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? null;
    if ($header === null) return null;
    return preg_match('/^Bearer (.+)$/', trim($header), $m) === 1 ? $m[1] : null;
}

function mock_account_for_request(): ?array
{
    $token = mock_bearer_token();
    if ($token === null) return null;
    $sessions = mock_sessions_read();
    return isset($sessions[$token]) ? $sessions[$token] : null;
}

function mock_validate_required(string $entity, array $data, bool $partial): array
{
    if ($partial) return [];
    $missing = [];
    foreach (SCHEMAS[$entity]['required'] as $f) {
        $v = $data[$f] ?? null;
        if ($v === null || $v === '' || (is_array($v) && count($v) === 0)) {
            $missing[] = "Field \"$f\" is required.";
        }
    }
    return $missing;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$requestPath = "$method $path";

if ($method === 'GET' && $path === '/health') {
    mock_json(200, ['status' => 'ok']);
    return;
}

try {
    if ($path === '/auth/login') {
        if ($method !== 'POST') { mock_json(405, mock_error(405, 'METHOD_NOT_ALLOWED', 'Method not allowed.', [], $requestPath)); return; }
        $raw = file_get_contents('php://input') ?: '';
        $data = $raw !== '' ? json_decode($raw, true) : [];
        if (!is_array($data) || !is_string($data['username'] ?? null) || !is_string($data['password'] ?? null)) {
            mock_json(400, mock_error(400, 'VALIDATION_ERROR', 'Invalid request data.', ['Fields "username" and "password" are required.'], $requestPath));
            return;
        }
        if ($data['username'] !== ADMIN_USERNAME || $data['password'] !== ADMIN_PASSWORD) {
            mock_json(401, mock_unauthorized($requestPath));
            return;
        }
        $admin = mock_admin_account();
        $token = mock_uuid();
        $sessions = mock_sessions_read();
        $sessions[$token] = $admin;
        mock_sessions_write($sessions);
        mock_json(200, [
            'token' => $token,
            'account' => ['id' => $admin['id'], 'username' => $admin['username'], 'role' => $admin['role']],
            'expiresAt' => gmdate('Y-m-d\TH:i:s\Z', time() + 8 * 3600),
        ]);
        return;
    }

    if ($path === '/auth/logout') {
        if ($method !== 'POST') { mock_json(405, mock_error(405, 'METHOD_NOT_ALLOWED', 'Method not allowed.', [], $requestPath)); return; }
        $token = mock_bearer_token();
        $sessions = mock_sessions_read();
        if ($token === null || !isset($sessions[$token])) { mock_json(401, mock_unauthorized($requestPath)); return; }
        unset($sessions[$token]);
        mock_sessions_write($sessions);
        mock_json(204, null);
        return;
    }

    if ($path === '/auth/me') {
        if ($method !== 'GET') { mock_json(405, mock_error(405, 'METHOD_NOT_ALLOWED', 'Method not allowed.', [], $requestPath)); return; }
        $account = mock_account_for_request();
        if ($account === null) { mock_json(401, mock_unauthorized($requestPath)); return; }
        mock_json(200, ['account' => ['id' => $account['id'], 'username' => $account['username'], 'role' => $account['role']]]);
        return;
    }

    // Every entity route requires a live session.
    $account = mock_account_for_request();
    if ($account === null) { mock_json(401, mock_unauthorized($requestPath)); return; }

    $seg = array_values(array_filter(explode('/', $path), static fn ($s) => $s !== ''));
    if (count($seg) < 1 || count($seg) > 2) {
        mock_json(404, mock_error(404, 'ROUTE_NOT_FOUND', 'No route matches this request.', [], $requestPath));
        return;
    }
    $entity = mock_entity_by_plural($seg[0]);
    if ($entity === null) {
        mock_json(404, mock_error(404, 'ROUTE_NOT_FOUND', 'No route matches this request.', [], $requestPath));
        return;
    }

    if (count($seg) === 1) {
        if ($method === 'GET') {
            $items = mock_read($entity);
            $sort = $_GET['sort'] ?? 'dateCreated';
            $dir = ($_GET['order'] ?? 'desc') === 'asc' ? 1 : -1;
            usort($items, static function ($a, $b) use ($sort, $dir) {
                $va = $a[$sort] ?? ''; $vb = $b[$sort] ?? '';
                if ($va === $vb) return 0;
                return ($va < $vb ? -1 : 1) * $dir;
            });
            $total = count($items);
            $limit = min((int) ($_GET['limit'] ?? 20), 100);
            $offset = (int) ($_GET['offset'] ?? 0);
            mock_json(200, ['items' => array_slice($items, $offset, $limit), 'total' => $total]);
            return;
        }
        if ($method === 'POST') {
            $raw = file_get_contents('php://input') ?: '';
            $data = $raw !== '' ? json_decode($raw, true) : [];
            if (!is_array($data)) { mock_json(400, mock_error(400, 'INVALID_JSON', 'Request body is not valid JSON.', [], $requestPath)); return; }
            $errors = mock_validate_required($entity, $data, false);
            if (count($errors)) { mock_json(400, mock_error(400, 'VALIDATION_ERROR', 'Invalid request data.', $errors, $requestPath)); return; }
            $now = gmdate('Y-m-d\TH:i:s\Z');
            $item = array_merge(['@context' => 'https://schema.org', '@type' => $entity], $data, [
                'id' => mock_uuid(),
                'dateCreated' => $now,
                'dateModified' => $now,
            ]);
            $items = mock_read($entity);
            $items[] = $item;
            mock_write($entity, $items);
            mock_json(201, $item);
            return;
        }
        mock_json(405, mock_error(405, 'METHOD_NOT_ALLOWED', 'Method not allowed.', [], $requestPath));
        return;
    }

    $id = strtolower($seg[1]);
    $items = mock_read($entity);
    $idx = null;
    foreach ($items as $i => $item) {
        if (($item['id'] ?? null) === $id) { $idx = $i; break; }
    }
    $current = $idx !== null ? $items[$idx] : null;

    if ($method === 'GET') {
        if ($current === null) { mock_json(404, mock_error(404, 'NOT_FOUND', "$entity not found.", [], $requestPath)); return; }
        mock_json(200, $current);
        return;
    }
    if ($method === 'PUT') {
        if ($current === null) { mock_json(404, mock_error(404, 'NOT_FOUND', "$entity not found.", [], $requestPath)); return; }
        $raw = file_get_contents('php://input') ?: '';
        $data = $raw !== '' ? json_decode($raw, true) : [];
        if (!is_array($data)) { mock_json(400, mock_error(400, 'INVALID_JSON', 'Request body is not valid JSON.', [], $requestPath)); return; }
        $errors = mock_validate_required($entity, $data, true);
        if (count($errors)) { mock_json(400, mock_error(400, 'VALIDATION_ERROR', 'Invalid request data.', $errors, $requestPath)); return; }
        $updated = array_merge($current, $data, [
            'id' => $current['id'],
            'dateCreated' => $current['dateCreated'],
            'dateModified' => gmdate('Y-m-d\TH:i:s\Z'),
            '@context' => $current['@context'] ?? 'https://schema.org',
            '@type' => $current['@type'] ?? $entity,
        ]);
        $items[$idx] = $updated;
        mock_write($entity, $items);
        mock_json(200, $updated);
        return;
    }
    if ($method === 'DELETE') {
        if ($current === null) { mock_json(404, mock_error(404, 'NOT_FOUND', "$entity not found.", [], $requestPath)); return; }
        array_splice($items, $idx, 1);
        mock_write($entity, $items);
        mock_json(204, null);
        return;
    }
    mock_json(405, mock_error(405, 'METHOD_NOT_ALLOWED', 'Method not allowed.', [], $requestPath));
} catch (\Throwable $e) {
    mock_json(500, mock_error(500, 'INTERNAL_ERROR', 'Internal server error: ' . $e->getMessage(), [], $requestPath));
}
