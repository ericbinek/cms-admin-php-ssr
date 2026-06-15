<?php
declare(strict_types=1);

namespace Cms\Views\WebPage;

use Cms\ApiClient;
use Cms\Views\Layout;

final class DeleteView
{
    public const ENTITY = 'WebPage';
    public const BASE = '/web-pages';

    public static function renderForm(array $opts): array
    {
        $id = $opts['id'];
        $user = $opts['user'] ?? null;
        $csrf = $opts['csrf'] ?? null;
        $r = ApiClient::get(self::ENTITY, $id);
        if ($r['status'] === 404) return Layout::errorPage(404, self::ENTITY . ' not found.', $user);
        if ($r['status'] !== 200) return Layout::errorPage($r['status'], $r['body']['message'] ?? 'Failed to load.', $user);
        return [
            'status' => 200,
            'html' => Layout::layout([
                'title' => 'Delete ' . self::ENTITY,
                'currentEntity' => self::ENTITY,
                'user' => $user,
                'csrf' => $csrf,
                'body' => '
<form method="POST" action="' . self::BASE . '/' . Layout::escapeHtml($id) . '/delete">
' . Layout::csrfField($csrf) . '
<p>Delete <strong>' . Layout::escapeHtml(Layout::displayName($r['body'], self::ENTITY)) . '</strong>? This cannot be undone.</p>
<p><button type="submit">Confirm Delete</button> · <a href="' . self::BASE . '/' . Layout::escapeHtml($id) . '">Cancel</a></p>
</form>',
            ]),
        ];
    }

    public static function handleSubmit(array $opts): array
    {
        $user = $opts['user'] ?? null;
        $r = ApiClient::remove(self::ENTITY, $opts['id']);
        if ($r['status'] === 204 || $r['status'] === 404) {
            return ['status' => 303, 'redirect' => self::BASE];
        }
        return Layout::errorPage($r['status'], 'Delete failed.', $user);
    }
}
