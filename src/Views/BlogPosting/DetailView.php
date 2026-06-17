<?php
declare(strict_types=1);

namespace Cms\Views\BlogPosting;

use Cms\ApiClient;
use Cms\Views\Layout;

final class DetailView
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
        'name' => 'publisher',
        'kind' => 'Ref',
        'targets' => ['Organization'],
        'cardinality' => 'one',
        'required' => false,
    ],
    [
        'name' => 'image',
        'kind' => 'Ref',
        'targets' => ['ImageObject'],
        'cardinality' => 'many',
        'required' => false,
    ],
    [
        'name' => 'video',
        'kind' => 'Ref',
        'targets' => ['VideoObject'],
        'cardinality' => 'many',
        'required' => false,
    ],
    [
        'name' => 'audio',
        'kind' => 'Ref',
        'targets' => ['AudioObject'],
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
