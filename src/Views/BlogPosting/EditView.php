<?php
declare(strict_types=1);

namespace Cms\Views\BlogPosting;

use Cms\ApiClient;
use Cms\Views\Layout;

final class EditView
{
    public const ENTITY = 'BlogPosting';
    public const BASE = '/blog-postings';
    public const PROPERTIES = [
    [
        'name' => 'headline',
        'kind' => 'InlineScalar',
        'use' => 'Text',
        'cardinality' => 'one',
        'required' => true,
    ],
    [
        'name' => 'alternativeHeadline',
        'kind' => 'InlineScalar',
        'use' => 'Text',
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
        'name' => 'articleBody',
        'kind' => 'InlineScalar',
        'use' => 'Text',
        'cardinality' => 'one',
        'required' => true,
    ],
    [
        'name' => 'author',
        'kind' => 'Ref',
        'targets' => ['Person'],
        'cardinality' => 'one',
        'required' => true,
    ],
    [
        'name' => 'image',
        'kind' => 'Ref',
        'targets' => ['ImageObject'],
        'cardinality' => 'many',
        'required' => false,
    ],
    [
        'name' => 'keywords',
        'kind' => 'Ref',
        'targets' => ['DefinedTerm'],
        'cardinality' => 'many',
        'required' => false,
    ],
    [
        'name' => 'about',
        'kind' => 'Ref',
        'targets' => ['CategoryCode'],
        'cardinality' => 'many',
        'required' => false,
    ],
    [
        'name' => 'datePublished',
        'kind' => 'InlineScalar',
        'use' => 'DateTime',
        'cardinality' => 'one',
        'required' => false,
    ],
    [
        'name' => 'dateModified',
        'kind' => 'InlineScalar',
        'use' => 'DateTime',
        'cardinality' => 'one',
        'required' => false,
    ],
    [
        'name' => 'dateCreated',
        'kind' => 'InlineScalar',
        'use' => 'DateTime',
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
        'name' => 'inLanguage',
        'kind' => 'Embed',
        'use' => 'Language',
        'cardinality' => 'one',
        'required' => false,
    ],
    [
        'name' => 'isAccessibleForFree',
        'kind' => 'InlineScalar',
        'use' => 'Boolean',
        'cardinality' => 'one',
        'required' => false,
    ],
    [
        'name' => 'wordCount',
        'kind' => 'InlineScalar',
        'use' => 'Integer',
        'cardinality' => 'one',
        'required' => false,
    ],
    [
        'name' => 'creativeWorkStatus',
        'kind' => 'Enum',
        'values' => ['Draft', 'Pending', 'Published', 'Archived'],
        'cardinality' => 'one',
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
