<?php
declare(strict_types=1);

namespace Cms\Views\DefinedTermSet;

use Cms\ApiClient;
use Cms\Views\Layout;

final class CreateView
{
    public const ENTITY = 'DefinedTermSet';
    public const BASE = '/defined-term-sets';
    public const PROPERTIES = [
    [
        'name' => 'name',
        'kind' => 'InlineScalar',
        'use' => 'Text',
        'cardinality' => 'one',
        'required' => true,
    ],
    [
        'name' => 'description',
        'kind' => 'InlineScalar',
        'use' => 'Text',
        'cardinality' => 'one',
        'required' => false,
    ],
    [
        'name' => 'url',
        'kind' => 'InlineScalar',
        'use' => 'URL',
        'cardinality' => 'one',
        'required' => false,
    ],
];

    private static function loadRefOptions(): array { return []; }

    private static function extractErrorList(?array $body): array
    {
        if ($body === null) return ['Request failed.'];
        if (isset($body['details']) && is_array($body['details']) && count($body['details'])) return $body['details'];
        if (isset($body['message']) && is_string($body['message'])) return [$body['message']];
        return ['Request failed.'];
    }

    public static function renderForm(array $opts): array
    {
        $user = $opts['user'] ?? null;
        $csrf = $opts['csrf'] ?? null;
        $values = $opts['values'] ?? [];
        $errors = $opts['errors'] ?? [];
        $fieldErrors = $opts['fieldErrors'] ?? [];
        $refOptions = self::loadRefOptions();
        $fields = '';
        foreach (self::PROPERTIES as $p) {
            $fields .= Layout::renderField([
                'prop' => $p,
                'value' => $values[$p['name']] ?? null,
                'refOptions' => $refOptions,
                'errors' => $fieldErrors[$p['name']] ?? [],
            ]) . "\n";
        }
        $errorBlock = '';
        if (count($errors)) {
            $items = implode('', array_map(static fn ($e) => '<li>' . Layout::escapeHtml($e) . '</li>', $errors));
            $errorBlock = '<div role="alert"><p>Could not save:</p><ul>' . $items . '</ul></div>';
        }
        return [
            'status' => count($errors) ? 400 : 200,
            'html' => Layout::layout([
                'title' => 'New ' . self::ENTITY,
                'currentEntity' => self::ENTITY,
                'user' => $user,
                'csrf' => $csrf,
                'body' => '
' . $errorBlock . '
<form method="POST" action="' . self::BASE . '/new">
' . Layout::csrfField($csrf) . '
' . $fields . '
<p><button type="submit">Create</button> · <a href="' . self::BASE . '">Cancel</a></p>
</form>',
            ]),
        ];
    }

    public static function handleSubmit(array $opts): array
    {
        $payload = Layout::parseFormBody($opts['form'] ?? '', self::PROPERTIES);
        $r = ApiClient::create(self::ENTITY, $payload);
        if ($r['status'] === 201 && isset($r['body']['id'])) {
            return ['status' => 303, 'redirect' => self::BASE . '/' . $r['body']['id']];
        }
        return ['status' => 400, 'errors' => self::extractErrorList($r['body']), 'values' => $payload];
    }
}
