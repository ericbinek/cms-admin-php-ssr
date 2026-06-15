<?php
declare(strict_types=1);

$ENTITY = 'BlogPosting';
$BASE = '/blog-postings';

test("unauthenticated dashboard redirects to login", function (array $stack) {
    $r = cms_admin_get($stack, '/');
    cms_assert_equal(303, $r['status']);
    cms_assert_equal('/login', $r['headers']['location'] ?? '');
});

test("unauthenticated entity route redirects to login", function (array $stack) use ($BASE) {
    $r = cms_admin_get($stack, $BASE);
    cms_assert_equal(303, $r['status']);
    cms_assert_equal('/login', $r['headers']['location'] ?? '');
});

test("GET /login renders a sign-in form", function (array $stack) {
    $r = cms_admin_get($stack, '/login');
    cms_assert_equal(200, $r['status']);
    cms_assert_match('/<form[^>]+method="POST"[^>]+action="\/login"/', $r['body']);
    cms_assert_match('/type="password"/', $r['body']);
    cms_assert_match('/name="_csrf"/', $r['body']);
});

test("login with wrong credentials returns 401 with an alert", function (array $stack) {
    $jar = [];
    cms_admin_get($stack, '/login', $jar);
    $r = cms_admin_post_form($stack, '/login', 'username=admin&password=wrong', $jar);
    cms_assert_equal(401, $r['status']);
    cms_assert_match('/role="alert"/', $r['body']);
});

test("login sets an HttpOnly, SameSite=Strict session cookie and redirects to dashboard", function (array $stack) {
    $jar = [];
    cms_admin_get($stack, '/login', $jar);
    $r = cms_admin_post_form($stack, '/login', 'username=admin&password=admin-password', $jar);
    cms_assert_equal(303, $r['status']);
    cms_assert_equal('/', $r['headers']['location'] ?? '');
    $setCookies = implode("\n", $r['setCookies']);
    cms_assert(str_contains($setCookies, CMS_SESSION_COOKIE . '='), 'expected a session cookie');
    cms_assert(stripos($setCookies, 'HttpOnly') !== false, 'session cookie must be HttpOnly');
    cms_assert(stripos($setCookies, 'SameSite=Strict') !== false, 'session cookie must be SameSite=Strict');
});

test("authenticated dashboard renders after login", function (array $stack) {
    $jar = cms_login_admin($stack);
    $r = cms_admin_get($stack, '/', $jar);
    cms_assert_equal(200, $r['status']);
    cms_assert_match('/Dashboard/', $r['body']);
    cms_assert_match('/Sign out/', $r['body']);
});

test("state-changing POST without a CSRF token is rejected with 403", function (array $stack) use ($ENTITY, $BASE) {
    $jar = cms_login_admin($stack);
    $body = cms_form_body_for($stack, $ENTITY, $jar);
    $r = cms_admin_post_form($stack, $BASE . '/new', $body, $jar, false);
    cms_assert_equal(403, $r['status']);
});

test("state-changing POST with a wrong CSRF token is rejected with 403", function (array $stack) use ($ENTITY, $BASE) {
    $jar = cms_login_admin($stack);
    $body = cms_form_body_for($stack, $ENTITY, $jar) . '&_csrf=not-the-real-token';
    $r = cms_admin_post_form($stack, $BASE . '/new', $body, $jar, false);
    cms_assert_equal(403, $r['status']);
});

test("logout clears the session and protected routes redirect to login again", function (array $stack) {
    $jar = cms_login_admin($stack);
    $out = cms_admin_post_form($stack, '/logout', '', $jar);
    cms_assert_equal(303, $out['status']);
    cms_assert_equal('/login', $out['headers']['location'] ?? '');
    $after = cms_admin_get($stack, '/', $jar);
    cms_assert_equal(303, $after['status']);
    cms_assert_equal('/login', $after['headers']['location'] ?? '');
});
