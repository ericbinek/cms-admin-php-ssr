<?php
declare(strict_types=1);

namespace Cms\Views\BlogPosting;

use Cms\ApiClient;
use Cms\Views\Layout;

final class ListView
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
    public const EXTRA_COLS = ['datePublished'];

    public static function render(array $opts): array
    {
        $user = $opts['user'] ?? null;
        $csrf = $opts['csrf'] ?? null;
        $url = $opts['url'] ?? self::BASE;
        $qs = parse_url($url, PHP_URL_QUERY);
        $query = [];
        if (is_string($qs)) parse_str($qs, $query);
        $allowed = array_intersect_key($query, array_flip(['limit', 'offset', 'sort', 'order']));
        $r = ApiClient::list(self::ENTITY, $allowed);
        if ($r['status'] !== 200) {
            return [
                'status' => $r['status'],
                'html' => Layout::layout([
                    'title' => self::ENTITY . 's',
                    'currentEntity' => self::ENTITY,
                    'user' => $user,
                    'csrf' => $csrf,
                    'body' => '<p role="alert">Failed to load: ' . Layout::escapeHtml($r['body']['message'] ?? 'unknown error') . '</p>',
                ]),
            ];
        }
        $headers = '';
        foreach (array_merge(['Name', 'Created'], self::EXTRA_COLS, ['Actions']) as $h) {
            $headers .= '<th scope="col">' . Layout::escapeHtml($h) . '</th>';
        }
        $rows = '';
        foreach ($r['body']['items'] as $item) {
            $extras = '';
            foreach (self::EXTRA_COLS as $col) {
                $prop = null;
                foreach (self::PROPERTIES as $p) if ($p['name'] === $col) { $prop = $p; break; }
                $extras .= '<td>' . ($prop ? Layout::formatValue($item[$col] ?? null, $prop) : Layout::escapeHtml((string) ($item[$col] ?? ''))) . '</td>';
            }
            $rows .= '<tr>
<td><a href="' . self::BASE . '/' . Layout::escapeHtml($item['id']) . '">' . Layout::escapeHtml(Layout::displayName($item, self::ENTITY)) . '</a></td>
<td><time datetime="' . Layout::escapeHtml($item['dateCreated'] ?? '') . '">' . Layout::escapeHtml($item['dateCreated'] ?? '') . '</time></td>
' . $extras . '
<td><a href="' . self::BASE . '/' . Layout::escapeHtml($item['id']) . '/edit">Edit</a> · <a href="' . self::BASE . '/' . Layout::escapeHtml($item['id']) . '/delete">Delete</a></td>
</tr>';
        }
        if ($rows === '') {
            $cols = 3 + count(self::EXTRA_COLS);
            $rows = '<tr><td colspan="' . $cols . '"><em>No items.</em></td></tr>';
        }
        $total = $r['body']['total'];
        $limit = max(1, (int) ($query['limit'] ?? 20));
        $offset = max(0, (int) ($query['offset'] ?? 0));
        // Clone every incoming query parameter and only swap offset, so the page
        // links carry the active limit, sort, order and any filters forward.
        $pageHref = static function (int $nextOffset) use ($query): string {
            $next = array_merge($query, ['offset' => (string) $nextOffset]);
            return self::BASE . '?' . http_build_query($next);
        };
        $prevLink = $offset > 0
            ? '<a href="' . Layout::escapeHtml($pageHref(max(0, $offset - $limit))) . '" rel="prev">Previous</a>'
            : '';
        $nextLink = $offset + $limit < $total
            ? '<a href="' . Layout::escapeHtml($pageHref($offset + $limit)) . '" rel="next">Next</a>'
            : '';
        $pagination = ($prevLink !== '' || $nextLink !== '')
            ? '<nav aria-label="Pagination">' . $prevLink . $nextLink . '</nav>'
            : '';
        return [
            'status' => 200,
            'html' => Layout::layout([
                'title' => self::ENTITY . 's',
                'currentEntity' => self::ENTITY,
                'user' => $user,
                'csrf' => $csrf,
                'body' => '
<p><a href="' . self::BASE . '/new">New ' . Layout::escapeHtml(self::ENTITY) . '</a></p>
<p>Showing ' . count($r['body']['items']) . ' of ' . $total . '.</p>
<table>
<caption>' . Layout::escapeHtml(self::ENTITY) . ' list</caption>
<thead><tr>' . $headers . '</tr></thead>
<tbody>' . $rows . '</tbody>
</table>
' . $pagination,
            ]),
        ];
    }
}
