<?php
declare(strict_types=1);

namespace Cms;

// Cookie names are admin-frontend internal; the API never reads them. The session
// cookie carries the API bearer token, the csrf cookie the synchronizer token
// rendered into every form.
final class Auth
{
    public const SESSION_COOKIE = 'cms_session';
    public const CSRF_COOKIE = 'cms_csrf';

    // Both cookies live at most as long as the API session cap (8h). Secure is on
    // only behind HTTPS; set COOKIE_SECURE=true in production. SameSite=Strict and
    // HttpOnly are always on — the server renders the csrf token into forms itself,
    // so no client script needs to read either cookie.
    private const MAX_AGE = 28800;

    private static function cookieSecure(): bool
    {
        return strtolower((string) (getenv('COOKIE_SECURE') ?: '')) === 'true';
    }

    /**
     * Parses a Cookie request header into a name -> value map.
     *
     * @return array<string, string>
     */
    public static function parseCookies(?string $header): array
    {
        $out = [];
        if ($header === null || $header === '') {
            return $out;
        }
        foreach (explode(';', $header) as $part) {
            $idx = strpos($part, '=');
            if ($idx === false) {
                continue;
            }
            $name = trim(substr($part, 0, $idx));
            if ($name === '') {
                continue;
            }
            $out[$name] = urldecode(trim(substr($part, $idx + 1)));
        }
        return $out;
    }

    private static function serialize(string $name, string $value, int $maxAge): string
    {
        $parts = [
            $name . '=' . rawurlencode($value),
            'Path=/',
            'HttpOnly',
            'SameSite=Strict',
            'Max-Age=' . $maxAge,
        ];
        if (self::cookieSecure()) {
            $parts[] = 'Secure';
        }
        return implode('; ', $parts);
    }

    public static function setSessionCookie(string $token): string
    {
        return self::serialize(self::SESSION_COOKIE, $token, self::MAX_AGE);
    }

    public static function clearSessionCookie(): string
    {
        return self::serialize(self::SESSION_COOKIE, '', 0);
    }

    public static function setCsrfCookie(string $token): string
    {
        return self::serialize(self::CSRF_COOKIE, $token, self::MAX_AGE);
    }

    public static function randomToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    // Constant-time comparison of the cookie token against the submitted form
    // token. A missing or empty cookie token fails closed; hash_equals does not
    // leak length-independent timing.
    public static function csrfValid(?string $cookieToken, ?string $formToken): bool
    {
        if (!is_string($cookieToken) || !is_string($formToken)) {
            return false;
        }
        if ($cookieToken === '') {
            return false;
        }
        return hash_equals($cookieToken, $formToken);
    }
}
