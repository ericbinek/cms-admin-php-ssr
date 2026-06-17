<?php
declare(strict_types=1);

namespace Cms\Views\VideoObject;

use Cms\ApiClient;
use Cms\Views\Layout;

final class DetailView
{
    public const ENTITY = 'VideoObject';
    public const BASE = '/video-objects';
    public const PROPERTIES = [
    [
        'name' => 'name',
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
        'name' => 'contentUrl',
        'kind' => 'InlineScalar',
        'use' => 'URL',
        'cardinality' => 'one',
        'required' => true,
    ],
    [
        'name' => 'embedUrl',
        'kind' => 'InlineScalar',
        'use' => 'URL',
        'cardinality' => 'one',
        'required' => false,
    ],
    [
        'name' => 'encodingFormat',
        'kind' => 'InlineScalar',
        'use' => 'Text',
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
        'cardinality' => 'one',
        'required' => false,
    ],
    [
        'name' => 'transcript',
        'kind' => 'InlineScalar',
        'use' => 'Text',
        'cardinality' => 'one',
        'required' => false,
    ],
    [
        'name' => 'caption',
        'kind' => 'InlineScalar',
        'use' => 'Text',
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

    public static function render(array $opts): array
    {
        $id = $opts['id'];
        $user = $opts['user'] ?? null;
        $csrf = $opts['csrf'] ?? null;
        $r = ApiClient::get(self::ENTITY, $id);
        if ($r['status'] === 404) return Layout::errorPage(404, self::ENTITY . ' not found.', $user);
        if ($r['status'] !== 200) return Layout::errorPage($r['status'], $r['body']['message'] ?? 'Failed to load.', $user);
        $item = $r['body'];
        $rows = '';
        foreach (self::PROPERTIES as $p) {
            $rows .= '<dt>' . Layout::escapeHtml($p['name']) . '</dt><dd>' . Layout::formatValue($item[$p['name']] ?? null, $p) . '</dd>';
        }
        $meta = '<dt>id</dt><dd><code>' . Layout::escapeHtml($item['id']) . '</code></dd>
<dt>dateCreated</dt><dd><time datetime="' . Layout::escapeHtml($item['dateCreated'] ?? '') . '">' . Layout::escapeHtml($item['dateCreated'] ?? '') . '</time></dd>
<dt>dateModified</dt><dd><time datetime="' . Layout::escapeHtml($item['dateModified'] ?? '') . '">' . Layout::escapeHtml($item['dateModified'] ?? '') . '</time></dd>';
        return [
            'status' => 200,
            'html' => Layout::layout([
                'title' => Layout::displayName($item, self::ENTITY),
                'currentEntity' => self::ENTITY,
                'user' => $user,
                'csrf' => $csrf,
                'body' => '
<article>
<dl>' . $rows . $meta . '</dl>
<p>
<a href="' . self::BASE . '/' . Layout::escapeHtml($item['id']) . '/edit">Edit</a> ·
<a href="' . self::BASE . '/' . Layout::escapeHtml($item['id']) . '/delete">Delete</a> ·
<a href="' . self::BASE . '">Back to list</a>
</p>
</article>',
            ]),
        ];
    }
}
