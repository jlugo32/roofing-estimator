<?php
/**
 * Company session logout. Destroys the session, expires the session
 * cookie, and bounces to the homepage. Produces no output of its own.
 */

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

start_company_session();

$_SESSION = [];

// Expire the session cookie in the browser, not just the server-side data.
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', [
        'expires'  => time() - 86400,
        'path'     => $params['path'],
        'domain'   => $params['domain'],
        'secure'   => $params['secure'],
        'httponly' => $params['httponly'],
        'samesite' => $params['samesite'],
    ]);
}

session_destroy();
redirect('/');
