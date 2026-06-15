<?php
declare(strict_types=1);

namespace Cms\Views;

final class Login
{
    public static function render(array $opts = []): array
    {
        $csrf = $opts['csrf'] ?? null;
        $error = $opts['error'] ?? null;
        $username = $opts['username'] ?? '';
        $errorBlock = $error !== null
            ? '<div role="alert"><p>' . Layout::escapeHtml($error) . '</p></div>'
            : '';
        return [
            'status' => $error !== null ? 401 : 200,
            'html' => Layout::layout([
                'title' => 'Sign in',
                'body' => '
' . $errorBlock . '
<form method="POST" action="/login">
' . Layout::csrfField($csrf) . '
<p>
<label for="field-username">Username</label><br>
<input id="field-username" name="username" type="text" value="' . Layout::escapeHtml($username) . '" required autocomplete="username">
</p>
<p>
<label for="field-password">Password</label><br>
<input id="field-password" name="password" type="password" required autocomplete="current-password">
</p>
<p><button type="submit">Sign in</button></p>
</form>',
            ]),
        ];
    }
}
