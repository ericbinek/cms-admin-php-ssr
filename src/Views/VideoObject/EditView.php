<?php
declare(strict_types=1);

namespace Cms\Views\VideoObject;

use Cms\ApiClient;
use Cms\Views\Layout;

final class EditView
{
    public const ENTITY = 'VideoObject';
    public const BASE = '/video-objects';
    public const PROPERTIES = [
    [
        'name' => 'name',
        'kind' => 'InlineScalar',
        'use' => 'Text',
        'maxLength' => 256,
        'cardinality' => 'one',
        'required' => false,
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
        'name' => 'contentUrl',
        'kind' => 'InlineScalar',
        'use' => 'URL',
        'maxLength' => 2048,
        'cardinality' => 'one',
        'required' => true,
    ],
    [
        'name' => 'embedUrl',
        'kind' => 'InlineScalar',
        'use' => 'URL',
        'maxLength' => 2048,
        'cardinality' => 'one',
        'required' => false,
    ],
    [
        'name' => 'encodingFormat',
        'kind' => 'InlineScalar',
        'use' => 'Text',
        'maxLength' => 128,
        'cardinality' => 'one',
        'required' => false,
    ],
    [
        'name' => 'duration',
        'kind' => 'InlineScalar',
        'use' => 'Duration',
        'cardinality' => 'one',
        'required' => false,
    ],
    [
        'name' => 'videoQuality',
        'kind' => 'InlineScalar',
        'use' => 'Text',
        'maxLength' => 128,
        'cardinality' => 'one',
        'required' => false,
    ],
    [
        'name' => 'transcript',
        'kind' => 'InlineScalar',
        'use' => 'Text',
        'maxLength' => 65536,
        'multiline' => true,
        'cardinality' => 'one',
        'required' => false,
    ],
    [
        'name' => 'caption',
        'kind' => 'InlineScalar',
        'use' => 'Text',
        'maxLength' => 1024,
        'cardinality' => 'one',
        'required' => false,
    ],
    [
        'name' => 'uploadDate',
        'kind' => 'InlineScalar',
        'use' => 'DateTime',
        'cardinality' => 'one',
        'required' => false,
    ],
    [
        'name' => 'creator',
        'kind' => 'Ref',
        'targets' => ['Person'],
        'cardinality' => 'one',
        'required' => false,
    ],
    [
        'name' => 'thumbnail',
        'kind' => 'Ref',
        'targets' => ['ImageObject'],
        'cardinality' => 'one',
        'required' => false,
    ],
    [
        'name' => 'productionCompany',
        'kind' => 'Ref',
        'targets' => ['Organization'],
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
