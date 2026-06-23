<?php
declare(strict_types=1);

namespace Cms\Views\CategoryCodeSet;

use Cms\ApiClient;
use Cms\Views\Layout;

final class EditView
{
    public const ENTITY = 'CategoryCodeSet';
    public const BASE = '/category-code-sets';
    public const PROPERTIES = [
    [
        'name' => 'name',
        'kind' => 'InlineScalar',
        'use' => 'Text',
        'maxLength' => 256,
        'cardinality' => 'one',
        'required' => true,
    ],
    [
        'name' => 'description',
        'kind' => 'InlineScalar',
        'use' => 'Text',
        'maxLength' => 5000,
        'multiline' => true,
        'cardinality' => 'one',
        'required' => false,
    ],
    [
        'name' => 'url',
        'kind' => 'InlineScalar',
        'use' => 'URL',
        'maxLength' => 2048,
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
        $id = $opts['id'];
        $user = $opts['user'] ?? null;
        $csrf = $opts['csrf'] ?? null;
        $values = $opts['values'] ?? null;
        $errors = $opts['errors'] ?? [];
        $fieldErrors = $opts['fieldErrors'] ?? [];
        if ($values === null) {
            $r = ApiClient::get(self::ENTITY, $id);
            if ($r['status'] === 404) return Layout::errorPage(404, self::ENTITY . ' not found.', $user);
            if ($r['status'] !== 200) return Layout::errorPage($r['status'], $r['body']['message'] ?? 'Failed to load.', $user);
            $values = Layout::formValuesFromItem($r['body'], self::PROPERTIES);
        }
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
                'title' => 'Edit ' . self::ENTITY,
                'currentEntity' => self::ENTITY,
                'user' => $user,
                'csrf' => $csrf,
                'body' => '
' . $errorBlock . '
<form method="POST" action="' . self::BASE . '/' . Layout::escapeHtml($id) . '/edit">
' . Layout::csrfField($csrf) . '
' . $fields . '
<p><button type="submit">Save</button> · <a href="' . self::BASE . '/' . Layout::escapeHtml($id) . '">Cancel</a></p>
</form>',
            ]),
        ];
    }

    public static function handleSubmit(array $opts): array
    {
        $id = $opts['id'];
        $user = $opts['user'] ?? null;
        $payload = Layout::parseFormBody($opts['form'] ?? '', self::PROPERTIES);
        $r = ApiClient::update(self::ENTITY, $id, $payload);
        if ($r['status'] === 200) {
            return ['status' => 303, 'redirect' => self::BASE . '/' . $id];
        }
        if ($r['status'] === 404) return Layout::errorPage(404, self::ENTITY . ' not found.', $user);
        return ['status' => 400, 'errors' => self::extractErrorList($r['body']), 'values' => $payload];
    }
}
