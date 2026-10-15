<?php
/* Logs out ONE role only (Logout.php?role=admin). The other roles stay
   logged in because each role has its own session (see Components/session.php). */
require_once __DIR__ . '/Components/session.php';

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $cookieParams = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $cookieParams['path'],
        $cookieParams['domain'],
        $cookieParams['secure'],
        $cookieParams['httponly']
    );
}

session_destroy();

header('Location: Index.php');
exit;
