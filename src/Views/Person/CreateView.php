<?php
declare(strict_types=1);

namespace Cms\Views\Person;

use Cms\ApiClient;
use Cms\Views\Layout;

final class CreateView
{
    public const ENTITY = 'Person';
    public const BASE = '/persons';
    public const PROPERTIES = [
    [
        'name' => 'name',
        'kind' => 'InlineScalar',
        'use' => 'Text',
        'cardinality' => 'one',
        'required' => true,
    ],
    [
        'name' => 'givenName',
        'kind' => 'InlineScalar',
        'use' => 'Text',
        'cardinality' => 'one',
        'required' => false,
    ],
    [
        'name' => 'familyName',
        'kind' => 'InlineScalar',
        'use' => 'Text',
        'cardinality' => 'one',
        'required' => false,
    ],
    [
        'name' => 'alternateName',
        'kind' => 'InlineScalar',
        'use' => 'Text',
        'cardinality' => 'one',
        'required' => false,
    ],
    [
        'name' => 'email',
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
    [
        'name' => 'description',
        'kind' => 'InlineScalar',
        'use' => 'Text',
        'cardinality' => 'one',
        'required' => false,
    ],
    [
        'name' => 'image',
        'kind' => 'Ref',
        'targets' => ['ImageObject'],
        'cardinality' => 'one',
        'required' => false,
    ],
    [
        'name' => 'worksFor',
        'kind' => 'Ref',
        'targets' => ['Organization'],
        'cardinality' => 'one',
        'required' => false,
    ],
    [
        'name' => 'jobTitle',
        'kind' => 'InlineScalar',
        'use' => 'Text',
        'cardinality' => 'one',
        'required' => false,
    ],
    [
        'name' => 'sameAs',
        'kind' => 'InlineScalar',
        'use' => 'URL',
        'cardinality' => 'many',
        'required' => false,
    ],
];

    private static function loadRefOptions(): array
    {
        $out = [];
        foreach (self::PROPERTIES as $prop) {
            if ($prop['kind'] !== 'Ref') continue;
            $collected = [];
            foreach ($prop['targets'] as $target) {
                $r = ApiClient::list($target, ['limit' => 100]);
                if ($r['status'] === 200 && isset($r['body']['items'])) {
                    foreach ($r['body']['items'] as $item) {
                        $collected[] = ['value' => $item['id'], 'label' => $target . ': ' . Layout::displayName($item, $target)];
                    }
                }
            }
            $out[$prop['name']] = $collected;
        }
        return $out;
    }

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
