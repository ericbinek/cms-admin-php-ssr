<?php
declare(strict_types=1);

$ENTITY = 'DefinedTermSet';
$BASE = '/defined-term-sets';

test("$ENTITY: unauthenticated list redirects to login", function (array $stack) use ($ENTITY, $BASE) {
    $r = cms_admin_get($stack, $BASE); // no jar -> no session cookie
    cms_assert_equal(303, $r['status']);
    cms_assert_equal('/login', $r['headers']['location'] ?? '');
});

test("$ENTITY: GET list renders semantic page", function (array $stack) use ($ENTITY, $BASE) {
    $jar = cms_admin_jar($stack);
    cms_ensure_entity($stack, $ENTITY, $jar);
    $r = cms_admin_get($stack, $BASE, $jar);
    cms_assert_equal(200, $r['status']);
    cms_assert_match('/<table\b/', $r['body']);
    cms_assert_match('/<caption>/', $r['body']);
    cms_assert_match('/' . preg_quote($ENTITY, '/') . '/', $r['body']);
});

test("$ENTITY: GET /new renders a form with a CSRF field", function (array $stack) use ($ENTITY, $BASE) {
    $jar = cms_admin_jar($stack);
    $r = cms_admin_get($stack, $BASE . '/new', $jar);
    cms_assert_equal(200, $r['status']);
    cms_assert_match('/<form[^>]+method="POST"/', $r['body']);
    cms_assert_match('/name="_csrf"/', $r['body']);
    cms_assert_match('/action="' . preg_quote($BASE, '/') . '\/new"/', $r['body']);
});

test("$ENTITY: POST /new with valid form redirects to detail", function (array $stack) use ($ENTITY, $BASE) {
    $jar = cms_admin_jar($stack);
    $body = cms_form_body_for($stack, $ENTITY, $jar);
    $r = cms_admin_post_form($stack, $BASE . '/new', $body, $jar);
    cms_assert_equal(303, $r['status']);
    $loc = $r['headers']['location'] ?? '';
    cms_assert(str_starts_with($loc, $BASE . '/'), "expected redirect to $BASE/<id>, got $loc");
});

test("$ENTITY: POST /new with empty form returns 400 or 303", function (array $stack) use ($ENTITY, $BASE) {
    $jar = cms_admin_jar($stack);
    $r = cms_admin_post_form($stack, $BASE . '/new', '', $jar);
    // No required fields -> an empty create is valid and the mock returns 201.
    if ($r['status'] === 303) return;
    cms_assert_equal(400, $r['status']);
    cms_assert_match('/role="alert"/', $r['body']);
});

test("$ENTITY: GET detail returns 200 with article markup", function (array $stack) use ($ENTITY, $BASE) {
    $jar = cms_admin_jar($stack);
    $id = cms_ensure_entity($stack, $ENTITY, $jar);
    $r = cms_admin_get($stack, $BASE . '/' . $id, $jar);
    cms_assert_equal(200, $r['status']);
    cms_assert_match('/<article\b/', $r['body']);
    cms_assert_match('/<dl>/', $r['body']);
    cms_assert_match('/' . preg_quote($id, '/') . '/', $r['body']);
});

test("$ENTITY: GET edit renders pre-filled form", function (array $stack) use ($ENTITY, $BASE) {
    $jar = cms_admin_jar($stack);
    $id = cms_ensure_entity($stack, $ENTITY, $jar);
    $r = cms_admin_get($stack, $BASE . '/' . $id . '/edit', $jar);
    cms_assert_equal(200, $r['status']);
    cms_assert_match('/<form[^>]+method="POST"/', $r['body']);
    cms_assert_match('/name="_csrf"/', $r['body']);
});

test("$ENTITY: POST edit redirects back to detail", function (array $stack) use ($ENTITY, $BASE) {
    $jar = cms_admin_jar($stack);
    $id = cms_ensure_entity($stack, $ENTITY, $jar);
    $body = cms_form_body_for($stack, $ENTITY, $jar);
    $r = cms_admin_post_form($stack, $BASE . '/' . $id . '/edit', $body, $jar);
    cms_assert_equal(303, $r['status']);
    cms_assert_equal($BASE . '/' . $id, $r['headers']['location'] ?? '');
});

test("$ENTITY: GET delete renders confirmation form", function (array $stack) use ($ENTITY, $BASE) {
    $jar = cms_admin_jar($stack);
    $id = cms_ensure_entity($stack, $ENTITY, $jar);
    $r = cms_admin_get($stack, $BASE . '/' . $id . '/delete', $jar);
    cms_assert_equal(200, $r['status']);
    cms_assert_match('/<form[^>]+method="POST"/', $r['body']);
    cms_assert_match('/Confirm Delete/', $r['body']);
});

test("$ENTITY: POST delete redirects to list", function (array $stack) use ($ENTITY, $BASE) {
    $jar = cms_admin_jar($stack);
    $id = cms_ensure_entity($stack, $ENTITY, $jar);
    $r = cms_admin_post_form($stack, $BASE . '/' . $id . '/delete', '', $jar);
    cms_assert_equal(303, $r['status']);
    cms_assert_equal($BASE, $r['headers']['location'] ?? '');
});

test("$ENTITY: GET detail with non-UUID id returns 400 with alert", function (array $stack) use ($ENTITY, $BASE) {
    $jar = cms_admin_jar($stack);
    $r = cms_admin_get($stack, $BASE . '/not-a-uuid', $jar);
    cms_assert_equal(400, $r['status']);
    cms_assert_match('/role="alert"/', $r['body']);
});

test("$ENTITY: GET detail of missing id renders 404 page", function (array $stack) use ($ENTITY, $BASE) {
    $jar = cms_admin_jar($stack);
    $r = cms_admin_get($stack, $BASE . '/00000000-0000-0000-0000-000000000000', $jar);
    cms_assert_equal(404, $r['status']);
    cms_assert_match('/role="alert"/', $r['body']);
});

test("$ENTITY: navigation includes self link with aria-current", function (array $stack) use ($ENTITY, $BASE) {
    $jar = cms_admin_jar($stack);
    cms_ensure_entity($stack, $ENTITY, $jar);
    $r = cms_admin_get($stack, $BASE, $jar);
    cms_assert_match('/aria-current="page"/', $r['body']);
});

test("$ENTITY: list view paginates with previous and next navigation", function (array $stack) use ($ENTITY, $BASE) {
    $jar = cms_admin_jar($stack);
    cms_seed_with($stack, $ENTITY, [], $jar);
    cms_seed_with($stack, $ENTITY, [], $jar);
    cms_seed_with($stack, $ENTITY, [], $jar);
    $first = cms_admin_get($stack, $BASE . '?limit=2&offset=0', $jar);
    cms_assert_equal(200, $first['status']);
    cms_assert(str_contains($first['body'], 'rel="next"'), 'expected a next link on page one');
    cms_assert(str_contains($first['body'], 'offset=2'), 'expected next link to advance offset to 2');
    cms_assert(!str_contains($first['body'], 'rel="prev"'), 'page one must not have a previous link');

    $second = cms_admin_get($stack, $BASE . '?limit=2&offset=2', $jar);
    cms_assert_equal(200, $second['status']);
    cms_assert(str_contains($second['body'], 'rel="prev"'), 'expected a previous link on page two');
});

test("$ENTITY: stored javascript: and data: URLs render as inert text, never as links", function (array $stack) use ($ENTITY, $BASE) {
    $jar = cms_admin_jar($stack);
    $jsId = cms_seed_with($stack, $ENTITY, ['url' => 'javascript:alert(1)'], $jar);
    $jsHtml = cms_admin_get($stack, $BASE . '/' . $jsId, $jar)['body'];
    cms_assert(str_contains($jsHtml, 'javascript:alert(1)'), 'expected inert javascript: text');
    cms_assert(!str_contains($jsHtml, 'href="javascript:'), 'javascript: must not become a link');

    $dataId = cms_seed_with($stack, $ENTITY, ['url' => 'data:text/html,x'], $jar);
    $dataHtml = cms_admin_get($stack, $BASE . '/' . $dataId, $jar)['body'];
    cms_assert(str_contains($dataHtml, 'data:text/html,x'), 'expected inert data: text');
    cms_assert(!str_contains($dataHtml, 'href="data:'), 'data: must not become a link');
});

test("$ENTITY: stored http(s) URL renders as a clickable link", function (array $stack) use ($ENTITY, $BASE) {
    $jar = cms_admin_jar($stack);
    $id = cms_seed_with($stack, $ENTITY, ['url' => 'https://example.com/profile'], $jar);
    $html = cms_admin_get($stack, $BASE . '/' . $id, $jar)['body'];
    cms_assert(str_contains($html, 'href="https://example.com/profile"'), 'expected clickable https link');
});
