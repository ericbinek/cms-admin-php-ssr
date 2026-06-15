<?php
declare(strict_types=1);

namespace Cms;

// Raised when a bound request gets 401 from the API — the session is invalid or
// expired upstream. The server catches it, clears the cookie, and redirects to
// the login page.
final class SessionExpiredException extends \RuntimeException
{
}

final class ApiClient
{
    public const PLURALS = [
        'BlogPosting' => 'blog-postings',
        'Person' => 'persons',
        'WebPage' => 'web-pages',
        'ImageObject' => 'image-objects',
        'CategoryCode' => 'category-codes',
        'CategoryCodeSet' => 'category-code-sets',
        'DefinedTerm' => 'defined-terms',
        'DefinedTermSet' => 'defined-term-sets',
        'Comment' => 'comments',
        'WebSite' => 'web-sites',
    ];

    // The session bearer token, bound once per request by the server before any
    // view runs. Null means no session (auth routes only).
    private static ?string $token = null;

    public static function bindToken(?string $token): void
    {
        self::$token = $token;
    }

    public static function baseUrl(): string
    {
        return rtrim(getenv('API_BASE_URL') ?: 'http://localhost:3002', '/');
    }

    public static function pluralOf(string $entity): string
    {
        if (!isset(self::PLURALS[$entity])) {
            throw new \InvalidArgumentException("Unknown entity for plural lookup: $entity");
        }
        return self::PLURALS[$entity];
    }

    private static function request(string $method, string $path, mixed $body = null, ?string $token = null): array
    {
        $ch = curl_init(self::baseUrl() . $path);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $hdr = ['Accept: application/json'];
        if ($token !== null && $token !== '') {
            $hdr[] = 'Authorization: Bearer ' . $token;
        }
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_SLASHES));
            $hdr[] = 'Content-Type: application/json';
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $hdr);
        $response = curl_exec($ch);
        if ($response === false) {
            $err = curl_error($ch);
            return ['status' => 0, 'body' => ['message' => "ApiClient request failed: $err"], 'etag' => null];
        }
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

        $rawHeaders = substr($response, 0, $headerSize);
        $rawBody = substr($response, $headerSize);
        $etag = null;
        foreach (explode("\r\n", $rawHeaders) as $line) {
            if (stripos($line, 'etag:') === 0) {
                $etag = trim(substr($line, 5));
                break;
            }
        }
        $parsed = $rawBody !== '' ? json_decode($rawBody, true) : null;
        return ['status' => $status, 'body' => $parsed, 'etag' => $etag];
    }

    // Auth routes — driven by the server's login/logout flow, not by the views.
    // They return the raw status so the server can map credentials to cookies.
    public static function login(string $username, string $password): array
    {
        return self::request('POST', '/auth/login', ['username' => $username, 'password' => $password]);
    }

    public static function logout(string $token): array
    {
        return self::request('POST', '/auth/logout', null, $token);
    }

    public static function me(string $token): array
    {
        return self::request('GET', '/auth/me', null, $token);
    }

    // Session-bound entity calls. Each carries the bound bearer token; a 401
    // becomes a SessionExpiredException.
    private static function authed(string $method, string $path, mixed $body = null): array
    {
        $r = self::request($method, $path, $body, self::$token);
        if ($r['status'] === 401) {
            throw new SessionExpiredException('Session expired.');
        }
        return $r;
    }

    public static function list(string $entity, array $query = []): array
    {
        $sp = http_build_query(array_filter($query, static fn ($v) => $v !== null && $v !== ''));
        $path = '/' . self::pluralOf($entity) . ($sp !== '' ? '?' . $sp : '');
        return self::authed('GET', $path);
    }

    public static function get(string $entity, string $id): array
    {
        return self::authed('GET', '/' . self::pluralOf($entity) . '/' . rawurlencode($id));
    }

    public static function create(string $entity, array $payload): array
    {
        return self::authed('POST', '/' . self::pluralOf($entity), $payload);
    }

    public static function update(string $entity, string $id, array $payload): array
    {
        return self::authed('PUT', '/' . self::pluralOf($entity) . '/' . rawurlencode($id), $payload);
    }

    public static function remove(string $entity, string $id): array
    {
        return self::authed('DELETE', '/' . self::pluralOf($entity) . '/' . rawurlencode($id));
    }
}
