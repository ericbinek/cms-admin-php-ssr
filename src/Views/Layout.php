<?php
declare(strict_types=1);

namespace Cms\Views;

final class Layout
{
    public const ENTITIES = ['BlogPosting', 'Person', 'WebPage', 'ImageObject', 'CategoryCode', 'CategoryCodeSet', 'DefinedTerm', 'DefinedTermSet', 'Comment', 'WebSite'];

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

    public const DISPLAY_KEYS = [
        'BlogPosting' => ['headline', 'alternativeHeadline'],
        'Person' => ['name', 'givenName', 'familyName'],
        'WebPage' => ['headline'],
        'ImageObject' => ['name', 'caption', 'contentUrl'],
        'CategoryCode' => ['name', 'codeValue'],
        'CategoryCodeSet' => ['name'],
        'DefinedTerm' => ['name', 'termCode'],
        'DefinedTermSet' => ['name'],
        'Comment' => ['text'],
        'WebSite' => ['name'],
    ];

    private const LONG_TEXT_HINT = ['articleBody', 'description', 'text'];

    public static function pluralOf(string $entity): string
    {
        return self::PLURALS[$entity] ?? strtolower($entity) . 's';
    }

    public static function escapeHtml(mixed $s): string
    {
        if ($s === null || $s === '') return '';
        return htmlspecialchars((string) $s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    // The hidden CSRF synchronizer field carried by every state-changing form.
    public static function csrfField(?string $token): string
    {
        return '<input type="hidden" name="_csrf" value="' . self::escapeHtml($token) . '">';
    }

    // Only http(s), mailto and site-relative values may become clickable links.
    // A stored "javascript:" or "data:" URL is rendered as inert escaped text, so
    // a bad value in the data store cannot turn into stored XSS when a user clicks
    // it.
    private static function isSafeHref(mixed $value): bool
    {
        if (!is_string($value)) {
            return false;
        }
        $v = strtolower(trim($value));
        return str_starts_with($v, 'http://')
            || str_starts_with($v, 'https://')
            || str_starts_with($v, 'mailto:')
            || str_starts_with($v, '/');
    }

    public static function layout(array $opts): string
    {
        $title = $opts['title'] ?? 'CMS';
        $body = $opts['body'] ?? '';
        $currentEntity = $opts['currentEntity'] ?? null;
        $user = $opts['user'] ?? null;
        $csrf = $opts['csrf'] ?? null;
        $flash = $opts['flash'] ?? null;
        if ($user !== null) {
            $nav = '';
            foreach (self::ENTITIES as $e) {
                $current = $e === $currentEntity ? ' aria-current="page"' : '';
                $nav .= '<li><a href="/' . self::PLURALS[$e] . '"' . $current . '>' . self::escapeHtml($e) . '</a></li>';
            }
            $logout = '<form method="POST" action="/logout" class="logout">' . self::csrfField($csrf) . '<button type="submit">Sign out</button></form>';
            $header = '<header>
<nav aria-label="Primary">
<ul>' . $nav . '</ul>
</nav>
<p class="session">Signed in as <strong>' . self::escapeHtml($user['username'] ?? '') . '</strong> (' . self::escapeHtml($user['role'] ?? '') . ') ' . $logout . '</p>
</header>';
        } else {
            $header = '<header><p><strong>CMS Admin</strong></p></header>';
        }
        $flashEl = $flash !== null ? '<p role="status">' . self::escapeHtml($flash) . '</p>' : '';
        return '<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>' . self::escapeHtml($title) . ' — CMS Admin</title>
<link rel="stylesheet" href="/style.css">
</head>
<body>
' . $header . '
<main>
<h1>' . self::escapeHtml($title) . '</h1>
' . $flashEl . '
' . $body . '
</main>
</body>
</html>
';
    }

    public static function displayName(?array $item, string $entity): string
    {
        if ($item === null) return '';
        $keys = self::DISPLAY_KEYS[$entity] ?? ['name', 'headline'];
        foreach ($keys as $k) {
            if (isset($item[$k]) && is_string($item[$k]) && $item[$k] !== '') return $item[$k];
        }
        return $item['id'] ?? '';
    }

    public static function errorPage(int $status, string $message, ?array $user = null): array
    {
        return [
            'status' => $status,
            'html' => self::layout([
                'title' => $status === 404 ? 'Not Found' : 'Error',
                'user' => $user,
                'body' => '<p role="alert">' . self::escapeHtml($message) . '</p>',
            ]),
        ];
    }

    public static function formatScalar(mixed $value, string $use): string
    {
        if ($use === 'URL') {
            if (!self::isSafeHref($value)) {
                return self::escapeHtml($value);
            }
            return '<a href="' . self::escapeHtml($value) . '" rel="noopener noreferrer">' . self::escapeHtml($value) . '</a>';
        }
        if ($use === 'DateTime' || $use === 'Date' || $use === 'Time') {
            return '<time datetime="' . self::escapeHtml($value) . '">' . self::escapeHtml($value) . '</time>';
        }
        if ($use === 'Boolean') return $value ? 'Yes' : 'No';
        return self::escapeHtml((string) $value);
    }

    public static function formatValue(mixed $value, array $prop): string
    {
        if ($value === null || $value === '') return '<em>—</em>';
        if (is_array($value) && array_is_list($value)) {
            if (count($value) === 0) return '<em>—</em>';
            $items = array_map(fn ($v) => '<li>' . self::formatValue($v, array_merge($prop, ['cardinality' => 'one'])) . '</li>', $value);
            return '<ul>' . implode('', $items) . '</ul>';
        }
        if ($prop['kind'] === 'Ref') {
            $target = $prop['targets'][0];
            $plural = self::PLURALS[$target] ?? strtolower($target) . 's';
            return '<a href="/' . $plural . '/' . self::escapeHtml((string) $value) . '">' . self::escapeHtml($target) . ': ' . self::escapeHtml((string) $value) . '</a>';
        }
        if ($prop['kind'] === 'Embed') {
            if ($prop['use'] === 'Language' && is_array($value)) {
                $code = $value['alternateName'] ?? $value['name'] ?? '';
                return '<span lang="' . self::escapeHtml($code) . '">' . self::escapeHtml($code) . '</span>';
            }
            return '<code>' . self::escapeHtml(json_encode($value)) . '</code>';
        }
        if ($prop['kind'] === 'Enum') return self::escapeHtml((string) $value);
        return self::formatScalar($value, $prop['use']);
    }

    public static function renderField(array $opts): string
    {
        $prop = $opts['prop'];
        $value = $opts['value'] ?? null;
        $refOptions = $opts['refOptions'] ?? [];
        $errors = $opts['errors'] ?? [];
        $id = 'field-' . $prop['name'];
        $req = $prop['required'];
        $requiredAttr = $req ? ' required' : '';
        $requiredMark = $req ? ' <span aria-hidden="true">*</span>' : '';
        $ariaInvalid = count($errors) ? ' aria-invalid="true"' : '';
        $labelText = self::escapeHtml($prop['name']) . $requiredMark;
        $help = count($errors)
            ? '<small role="alert">' . implode('; ', array_map([self::class, 'escapeHtml'], $errors)) . '</small>'
            : '';
        $input = self::renderInput($prop, $value, $id, $requiredAttr, $ariaInvalid, $refOptions);
        return '<p>
<label for="' . $id . '">' . $labelText . '</label><br>
' . $input . '
' . $help . '
</p>';
    }

    private static function renderInput(array $prop, mixed $value, string $id, string $requiredAttr, string $ariaInvalid, array $refOptions): string
    {
        $name = self::escapeHtml($prop['name']);
        if ($prop['kind'] === 'Enum') {
            $opts = '';
            foreach ($prop['values'] as $v) {
                $sel = $v === $value ? ' selected' : '';
                $opts .= '<option value="' . self::escapeHtml($v) . '"' . $sel . '>' . self::escapeHtml($v) . '</option>';
            }
            $placeholder = $prop['required'] ? '' : '<option value="">—</option>';
            return '<select id="' . $id . '" name="' . $name . '"' . $requiredAttr . $ariaInvalid . '>' . $placeholder . $opts . '</select>';
        }
        if ($prop['kind'] === 'Ref') {
            $current = $prop['cardinality'] === 'many'
                ? (is_array($value) ? $value : ($value !== null ? [$value] : []))
                : (is_array($value) ? ($value[0] ?? null) : $value);
            $opts = '';
            foreach ($refOptions[$prop['name']] ?? [] as $o) {
                $selected = $prop['cardinality'] === 'many'
                    ? in_array($o['value'], $current, true)
                    : ($current === $o['value']);
                $opts .= '<option value="' . self::escapeHtml($o['value']) . '"' . ($selected ? ' selected' : '') . '>' . self::escapeHtml($o['label']) . '</option>';
            }
            $multiple = $prop['cardinality'] === 'many' ? ' multiple' : '';
            $placeholder = $prop['cardinality'] === 'one' && !$prop['required'] ? '<option value="">—</option>' : '';
            return '<select id="' . $id . '" name="' . $name . '"' . $multiple . $requiredAttr . $ariaInvalid . '>' . $placeholder . $opts . '</select>';
        }
        if ($prop['kind'] === 'Embed' && $prop['use'] === 'Language') {
            $v = is_array($value) ? ($value['alternateName'] ?? '') : ($value ?? '');
            return '<input id="' . $id . '" name="' . $name . '" type="text" value="' . self::escapeHtml($v) . '"' . $requiredAttr . $ariaInvalid . '>';
        }
        if ($prop['cardinality'] === 'many') {
            $v = is_array($value) ? implode("\n", $value) : ($value ?? '');
            return '<textarea id="' . $id . '" name="' . $name . '" rows="3"' . $requiredAttr . $ariaInvalid . '>' . self::escapeHtml($v) . '</textarea>';
        }
        if ($prop['use'] === 'Text' && in_array($prop['name'], self::LONG_TEXT_HINT, true)) {
            return '<textarea id="' . $id . '" name="' . $name . '" rows="6"' . $requiredAttr . $ariaInvalid . '>' . self::escapeHtml($value) . '</textarea>';
        }
        if ($prop['use'] === 'URL') {
            return '<input id="' . $id . '" name="' . $name . '" type="url" value="' . self::escapeHtml($value) . '"' . $requiredAttr . $ariaInvalid . '>';
        }
        if ($prop['use'] === 'Integer') {
            return '<input id="' . $id . '" name="' . $name . '" type="number" step="1" value="' . self::escapeHtml($value) . '"' . $requiredAttr . $ariaInvalid . '>';
        }
        if ($prop['use'] === 'Number') {
            return '<input id="' . $id . '" name="' . $name . '" type="number" step="any" value="' . self::escapeHtml($value) . '"' . $requiredAttr . $ariaInvalid . '>';
        }
        if ($prop['use'] === 'Boolean') {
            $checked = ($value === true || $value === 'true' || $value === 'on') ? ' checked' : '';
            return '<input id="' . $id . '" name="' . $name . '" type="checkbox" value="true"' . $checked . $ariaInvalid . '>';
        }
        if (in_array($prop['use'], ['DateTime', 'Date', 'Time'], true)) {
            $v = is_string($value) ? substr(rtrim($value, 'Z'), 0, 16) : '';
            return '<input id="' . $id . '" name="' . $name . '" type="datetime-local" value="' . self::escapeHtml($v) . '"' . $requiredAttr . $ariaInvalid . '>';
        }
        return '<input id="' . $id . '" name="' . $name . '" type="text" value="' . self::escapeHtml($value) . '"' . $requiredAttr . $ariaInvalid . '>';
    }

    private static function parseFormPairs(string $raw): array
    {
        $out = [];
        if ($raw === '') return $out;
        foreach (explode('&', $raw) as $pair) {
            if ($pair === '') continue;
            $kv = explode('=', $pair, 2);
            $k = urldecode($kv[0]);
            $v = isset($kv[1]) ? urldecode(str_replace('+', ' ', $kv[1])) : '';
            if (isset($out[$k])) {
                if (!is_array($out[$k])) $out[$k] = [$out[$k]];
                $out[$k][] = $v;
            } else {
                $out[$k] = $v;
            }
        }
        return $out;
    }

    private static function coerceFormValue(mixed $raw, array $prop): mixed
    {
        if ($raw === '' || $raw === null) return null;
        if ($prop['kind'] === 'Enum' || $prop['kind'] === 'Ref') return (string) $raw;
        if ($prop['kind'] === 'Embed' && $prop['use'] === 'Language') {
            return ['@type' => 'Language', 'alternateName' => (string) $raw];
        }
        return match ($prop['use']) {
            'Integer' => is_numeric($raw) ? (int) $raw : $raw,
            'Number' => is_numeric($raw) ? (float) $raw : $raw,
            'Boolean' => $raw === 'true' || $raw === 'on' || $raw === '1',
            'DateTime', 'Date', 'Time' => preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', (string) $raw) ? ($raw . ':00Z') : (string) $raw,
            default => (string) $raw,
        };
    }

    public static function parseFormBody(string $raw, array $properties): array
    {
        $pairs = self::parseFormPairs($raw);
        $out = [];
        foreach ($properties as $prop) {
            $name = $prop['name'];
            if ($prop['cardinality'] === 'many') {
                if ($prop['kind'] === 'Ref') {
                    $values = isset($pairs[$name]) ? (is_array($pairs[$name]) ? $pairs[$name] : [$pairs[$name]]) : [];
                    $values = array_values(array_filter($values, static fn ($v) => $v !== ''));
                } else {
                    $single = $pairs[$name] ?? '';
                    $raw = is_array($single) ? implode("\n", $single) : $single;
                    $values = array_values(array_filter(array_map('trim', preg_split('/\r?\n/', $raw) ?: []), static fn ($v) => $v !== ''));
                }
                $coerced = array_values(array_filter(array_map(fn ($v) => self::coerceFormValue($v, $prop), $values), static fn ($v) => $v !== null));
                if (count($coerced)) $out[$name] = $coerced;
            } elseif ($prop['kind'] === 'InlineScalar' && $prop['use'] === 'Boolean') {
                $out[$name] = isset($pairs[$name]);
            } else {
                $raw = $pairs[$name] ?? null;
                $v = self::coerceFormValue($raw, $prop);
                if ($v !== null) $out[$name] = $v;
            }
        }
        return $out;
    }

    public static function formValuesFromItem(?array $item, array $properties): array
    {
        $out = [];
        if ($item === null) return $out;
        foreach ($properties as $prop) {
            if (array_key_exists($prop['name'], $item)) {
                $out[$prop['name']] = $item[$prop['name']];
            }
        }
        return $out;
    }
}
