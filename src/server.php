<?php
declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('log_errors', '1');

require_once __DIR__ . '/autoload.php';

use Cms\ApiClient;
use Cms\SessionExpiredException;
use Cms\Auth;
use Cms\Views\Layout;
use Cms\Views\Login;
use Cms\Views\BlogPosting\ListView as BlogPostingList;
use Cms\Views\BlogPosting\DetailView as BlogPostingDetail;
use Cms\Views\BlogPosting\CreateView as BlogPostingCreate;
use Cms\Views\BlogPosting\EditView as BlogPostingEdit;
use Cms\Views\BlogPosting\DeleteView as BlogPostingDelete;
use Cms\Views\Person\ListView as PersonList;
use Cms\Views\Person\DetailView as PersonDetail;
use Cms\Views\Person\CreateView as PersonCreate;
use Cms\Views\Person\EditView as PersonEdit;
use Cms\Views\Person\DeleteView as PersonDelete;
use Cms\Views\WebPage\ListView as WebPageList;
use Cms\Views\WebPage\DetailView as WebPageDetail;
use Cms\Views\WebPage\CreateView as WebPageCreate;
use Cms\Views\WebPage\EditView as WebPageEdit;
use Cms\Views\WebPage\DeleteView as WebPageDelete;
use Cms\Views\ImageObject\ListView as ImageObjectList;
use Cms\Views\ImageObject\DetailView as ImageObjectDetail;
use Cms\Views\ImageObject\CreateView as ImageObjectCreate;
use Cms\Views\ImageObject\EditView as ImageObjectEdit;
use Cms\Views\ImageObject\DeleteView as ImageObjectDelete;
use Cms\Views\CategoryCode\ListView as CategoryCodeList;
use Cms\Views\CategoryCode\DetailView as CategoryCodeDetail;
use Cms\Views\CategoryCode\CreateView as CategoryCodeCreate;
use Cms\Views\CategoryCode\EditView as CategoryCodeEdit;
use Cms\Views\CategoryCode\DeleteView as CategoryCodeDelete;
use Cms\Views\CategoryCodeSet\ListView as CategoryCodeSetList;
use Cms\Views\CategoryCodeSet\DetailView as CategoryCodeSetDetail;
use Cms\Views\CategoryCodeSet\CreateView as CategoryCodeSetCreate;
use Cms\Views\CategoryCodeSet\EditView as CategoryCodeSetEdit;
use Cms\Views\CategoryCodeSet\DeleteView as CategoryCodeSetDelete;
use Cms\Views\DefinedTerm\ListView as DefinedTermList;
use Cms\Views\DefinedTerm\DetailView as DefinedTermDetail;
use Cms\Views\DefinedTerm\CreateView as DefinedTermCreate;
use Cms\Views\DefinedTerm\EditView as DefinedTermEdit;
use Cms\Views\DefinedTerm\DeleteView as DefinedTermDelete;
use Cms\Views\DefinedTermSet\ListView as DefinedTermSetList;
use Cms\Views\DefinedTermSet\DetailView as DefinedTermSetDetail;
use Cms\Views\DefinedTermSet\CreateView as DefinedTermSetCreate;
use Cms\Views\DefinedTermSet\EditView as DefinedTermSetEdit;
use Cms\Views\DefinedTermSet\DeleteView as DefinedTermSetDelete;
use Cms\Views\Comment\ListView as CommentList;
use Cms\Views\Comment\DetailView as CommentDetail;
use Cms\Views\Comment\CreateView as CommentCreate;
use Cms\Views\Comment\EditView as CommentEdit;
use Cms\Views\Comment\DeleteView as CommentDelete;
use Cms\Views\WebSite\ListView as WebSiteList;
use Cms\Views\WebSite\DetailView as WebSiteDetail;
use Cms\Views\WebSite\CreateView as WebSiteCreate;
use Cms\Views\WebSite\EditView as WebSiteEdit;
use Cms\Views\WebSite\DeleteView as WebSiteDelete;

const UUID_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

$ENTITY_ROUTES = [
    ['BlogPosting', 'blog-postings', BlogPostingList::class, BlogPostingDetail::class, BlogPostingCreate::class, BlogPostingEdit::class, BlogPostingDelete::class],
    ['Person', 'persons', PersonList::class, PersonDetail::class, PersonCreate::class, PersonEdit::class, PersonDelete::class],
    ['WebPage', 'web-pages', WebPageList::class, WebPageDetail::class, WebPageCreate::class, WebPageEdit::class, WebPageDelete::class],
    ['ImageObject', 'image-objects', ImageObjectList::class, ImageObjectDetail::class, ImageObjectCreate::class, ImageObjectEdit::class, ImageObjectDelete::class],
    ['CategoryCode', 'category-codes', CategoryCodeList::class, CategoryCodeDetail::class, CategoryCodeCreate::class, CategoryCodeEdit::class, CategoryCodeDelete::class],
    ['CategoryCodeSet', 'category-code-sets', CategoryCodeSetList::class, CategoryCodeSetDetail::class, CategoryCodeSetCreate::class, CategoryCodeSetEdit::class, CategoryCodeSetDelete::class],
    ['DefinedTerm', 'defined-terms', DefinedTermList::class, DefinedTermDetail::class, DefinedTermCreate::class, DefinedTermEdit::class, DefinedTermDelete::class],
    ['DefinedTermSet', 'defined-term-sets', DefinedTermSetList::class, DefinedTermSetDetail::class, DefinedTermSetCreate::class, DefinedTermSetEdit::class, DefinedTermSetDelete::class],
    ['Comment', 'comments', CommentList::class, CommentDetail::class, CommentCreate::class, CommentEdit::class, CommentDelete::class],
    ['WebSite', 'web-sites', WebSiteList::class, WebSiteDetail::class, WebSiteCreate::class, WebSiteEdit::class, WebSiteDelete::class],
];

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

$start = microtime(true);
register_shutdown_function(static function () use ($method, $path, $start): void {
    $code = http_response_code() ?: 200;
    $ms = (int) ((microtime(true) - $start) * 1000);
    error_log("$method $path $code {$ms}ms");
});

// Cookies queued by this request (a freshly issued csrf cookie, the session
// cookie after login, or its clearing on logout / expiry). Applied on every
// output path so a set never gets lost.
$SET_COOKIES = [];

function emit_set_cookies(array $cookies): void
{
    foreach ($cookies as $cookie) {
        header('Set-Cookie: ' . $cookie, false);
    }
}

function send_html(int $status, string $html, array $setCookies = []): void
{
    http_response_code($status);
    header('Content-Type: text/html; charset=utf-8');
    header('Content-Length: ' . strlen($html));
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: no-referrer');
    emit_set_cookies($setCookies);
    echo $html;
}

function send_redirect(string $location, int $status = 303, array $setCookies = []): void
{
    http_response_code($status);
    header("Location: $location");
    emit_set_cookies($setCookies);
}

function not_found_response(?array $user = null, ?string $csrf = null): array
{
    return [
        'status' => 404,
        'html' => Layout::layout([
            'title' => 'Not Found',
            'user' => $user,
            'csrf' => $csrf,
            'body' => '<p role="alert">Page not found.</p>',
        ]),
    ];
}

function invalid_id_response(?array $user = null, ?string $csrf = null): array
{
    return [
        'status' => 400,
        'html' => Layout::layout([
            'title' => 'Invalid ID',
            'user' => $user,
            'csrf' => $csrf,
            'body' => '<p role="alert">ID must be a valid UUID.</p>',
        ]),
    ];
}

function read_form_body(): string
{
    $raw = file_get_contents('php://input');
    return $raw === false ? '' : $raw;
}

// Resolves and validates the session by asking the API who we are. A 401 means
// the session is gone — surfaced as SessionExpiredException so the caller clears
// the cookie and redirects to login. Doubles as the per-request principal lookup
// for the layout header.
function require_user(string $token): array
{
    $r = ApiClient::me($token);
    if ($r['status'] === 401 || !isset($r['body']['account'])) {
        throw new SessionExpiredException('Session expired.');
    }
    return $r['body']['account'];
}

function match_entity_route(array $entityRoutes, string $path): ?array
{
    foreach ($entityRoutes as $route) {
        [$entity, $plural] = $route;
        $base = '/' . $plural;
        if ($path === $base) return ['route' => $route, 'kind' => 'list', 'id' => null];
        if ($path === $base . '/new') return ['route' => $route, 'kind' => 'new', 'id' => null];
        if (str_starts_with($path, $base . '/')) {
            $rest = substr($path, strlen($base) + 1);
            $slash = strpos($rest, '/');
            $id = $slash === false ? $rest : substr($rest, 0, $slash);
            $action = $slash === false ? 'detail' : substr($rest, $slash + 1);
            if ($action !== 'detail' && $action !== 'edit' && $action !== 'delete') {
                continue;
            }
            return ['route' => $route, 'kind' => $action, 'id' => $id];
        }
    }
    return null;
}

/**
 * GET routing. The csrf token (already ensured by the caller) is threaded into
 * every view so forms can carry it. Without a session cookie, protected routes
 * redirect to /login.
 */
function handle_get(array $entityRoutes, string $path, ?string $sessionToken, string $csrf): array
{
    if ($path === '/login') {
        // Already carrying a session: go to the dashboard. A stale cookie bounces
        // back here (cleared) on the first failing API call.
        if ($sessionToken !== null) return ['status' => 303, 'redirect' => '/'];
        return Login::render(['csrf' => $csrf]);
    }

    if ($sessionToken === null) return ['status' => 303, 'redirect' => '/login'];
    $user = require_user($sessionToken);

    if ($path === '/') {
        $items = '';
        foreach ($entityRoutes as [$entity, $plural]) {
            $items .= '<li><a href="/' . $plural . '">' . Layout::escapeHtml($entity) . '</a></li>';
        }
        return ['status' => 200, 'html' => Layout::layout([
            'title' => 'Dashboard',
            'user' => $user,
            'csrf' => $csrf,
            'body' => '<p>Manage content for ' . count($entityRoutes) . ' entity types.</p><ul>' . $items . '</ul>',
        ])];
    }

    $match = match_entity_route($entityRoutes, $path);
    if ($match === null) return not_found_response($user, $csrf);
    [$entity, $plural, $listCls, $detailCls, $createCls, $editCls, $deleteCls] = $match['route'];
    $kind = $match['kind'];
    $id = $match['id'];
    $idValid = $id === null || preg_match(UUID_PATTERN, $id) === 1;
    $ctx = ['user' => $user, 'csrf' => $csrf];

    if ($kind === 'list') return $listCls::render(array_merge($ctx, ['url' => $_SERVER['REQUEST_URI'] ?? '/']));
    if ($kind === 'new') return $createCls::renderForm($ctx);
    if ($kind === 'detail') {
        if (!$idValid) return invalid_id_response($user, $csrf);
        return $detailCls::render(array_merge($ctx, ['id' => $id]));
    }
    if ($kind === 'edit') {
        if (!$idValid) return invalid_id_response($user, $csrf);
        return $editCls::renderForm(array_merge($ctx, ['id' => $id]));
    }
    if ($kind === 'delete') {
        if (!$idValid) return invalid_id_response($user, $csrf);
        return $deleteCls::renderForm(array_merge($ctx, ['id' => $id]));
    }
    return not_found_response($user, $csrf);
}

/**
 * POST routing. The CSRF gate sits in dispatch() before this runs, so by here
 * the token is known good. login/logout own the cookie side effects.
 *
 * @param array<int, string> $setCookies cookies to carry on every reply
 */
function handle_post(array $entityRoutes, string $path, string $form, ?string $sessionToken, string $csrf, array $setCookies): array
{
    if ($path === '/login') {
        parse_str($form, $fields);
        $username = trim((string) ($fields['username'] ?? ''));
        $password = (string) ($fields['password'] ?? '');
        if ($username === '' || $password === '') {
            return Login::render(['csrf' => $csrf, 'error' => 'Username and password are required.', 'username' => $username]);
        }
        $r = ApiClient::login($username, $password);
        if ($r['status'] === 200 && isset($r['body']['token'])) {
            return [
                'status' => 303,
                'redirect' => '/',
                'setCookies' => array_merge($setCookies, [Auth::setSessionCookie($r['body']['token'])]),
            ];
        }
        return Login::render(['csrf' => $csrf, 'error' => 'Invalid username or password.', 'username' => $username]);
    }

    if ($path === '/logout') {
        if ($sessionToken !== null) {
            try { ApiClient::logout($sessionToken); } catch (\Throwable $e) { /* best effort, cookie is cleared anyway */ }
        }
        return [
            'status' => 303,
            'redirect' => '/login',
            'setCookies' => array_merge($setCookies, [Auth::clearSessionCookie()]),
        ];
    }

    if ($sessionToken === null) return ['status' => 303, 'redirect' => '/login'];
    $user = require_user($sessionToken);

    $match = match_entity_route($entityRoutes, $path);
    if ($match === null) return not_found_response($user, $csrf);
    [$entity, $plural, $listCls, $detailCls, $createCls, $editCls, $deleteCls] = $match['route'];
    $kind = $match['kind'];
    $id = $match['id'];
    $idValid = $id === null || preg_match(UUID_PATTERN, $id) === 1;
    $ctx = ['user' => $user, 'csrf' => $csrf];

    if ($kind === 'new') {
        $result = $createCls::handleSubmit(array_merge($ctx, ['form' => $form]));
        if (isset($result['redirect'])) return $result;
        if (isset($result['html'])) return $result;
        return $createCls::renderForm(array_merge($ctx, [
            'errors' => $result['errors'] ?? [],
            'values' => $result['values'] ?? [],
        ]));
    }
    if ($kind === 'edit') {
        if (!$idValid) return invalid_id_response($user, $csrf);
        $result = $editCls::handleSubmit(array_merge($ctx, ['id' => $id, 'form' => $form]));
        if (isset($result['redirect'])) return $result;
        if (isset($result['html'])) return $result;
        return $editCls::renderForm(array_merge($ctx, [
            'id' => $id,
            'errors' => $result['errors'] ?? [],
            'values' => $result['values'] ?? [],
        ]));
    }
    if ($kind === 'delete') {
        if (!$idValid) return invalid_id_response($user, $csrf);
        $result = $deleteCls::handleSubmit(array_merge($ctx, ['id' => $id]));
        if (isset($result['redirect'])) return $result;
        return $result;
    }
    return not_found_response($user, $csrf);
}

function dispatch(array $entityRoutes, string $method, string $path): array
{
    global $SET_COOKIES;

    if ($method === 'GET' && $path === '/health') {
        return ['status' => 200, 'json' => ['status' => 'ok']];
    }

    $cookies = Auth::parseCookies($_SERVER['HTTP_COOKIE'] ?? null);
    $sessionToken = $cookies[Auth::SESSION_COOKIE] ?? null;
    if ($sessionToken === '') $sessionToken = null;
    // Issue a CSRF token if the browser has none yet; never rotate an existing one
    // (it would invalidate a form open in another tab).
    $csrf = $cookies[Auth::CSRF_COOKIE] ?? null;
    if ($csrf === null || $csrf === '') {
        $csrf = Auth::randomToken();
        $SET_COOKIES[] = Auth::setCsrfCookie($csrf);
    }

    // Bind the bearer token for every entity call made downstream this request.
    ApiClient::bindToken($sessionToken);

    if ($method === 'POST') {
        $form = read_form_body();
        $contentType = $_SERVER['CONTENT_TYPE'] ?? ($_SERVER['HTTP_CONTENT_TYPE'] ?? '');
        if (!str_starts_with(strtolower($contentType), 'application/x-www-form-urlencoded')) {
            return ['status' => 415, 'html' => Layout::layout(['title' => 'Unsupported', 'body' => '<p role="alert">Form encoding required.</p>'])];
        }
        parse_str($form, $submitted);
        // CSRF: the submitted token must match the cookie set on a prior GET.
        if (!Auth::csrfValid($cookies[Auth::CSRF_COOKIE] ?? null, $submitted['_csrf'] ?? null)) {
            return ['status' => 403, 'html' => Layout::layout(['title' => 'Forbidden', 'body' => '<p role="alert">Invalid or missing CSRF token. Reload the form and try again.</p>'])];
        }
        return handle_post($entityRoutes, $path, $form, $sessionToken, $csrf, $SET_COOKIES);
    }

    if ($method === 'GET') {
        return handle_get($entityRoutes, $path, $sessionToken, $csrf);
    }

    return not_found_response();
}

try {
    $response = dispatch($ENTITY_ROUTES, $method, $path);
    $setCookies = $response['setCookies'] ?? $SET_COOKIES;
    if (isset($response['redirect'])) {
        send_redirect($response['redirect'], $response['status'] ?? 303, $setCookies);
    } elseif (isset($response['json'])) {
        http_response_code($response['status']);
        header('Content-Type: application/json');
        echo json_encode($response['json']);
    } elseif (isset($response['html'])) {
        send_html($response['status'] ?? 200, $response['html'], $setCookies);
    }
} catch (SessionExpiredException $e) {
    send_redirect('/login', 303, array_merge($SET_COOKIES, [Auth::clearSessionCookie()]));
} catch (\Throwable $e) {
    error_log("[$method $path] " . $e->getMessage());
    send_html(500, Layout::layout(['title' => 'Error', 'body' => '<p role="alert">Internal server error.</p>']), $SET_COOKIES);
}
