<?php
/**
 * Session Management Helper
 * Use this to start sessions consistently across the application
 */

function startSession() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
        return true;
    }
    return false;
}

function isSessionActive() {
    return session_status() === PHP_SESSION_ACTIVE;
}

function destroySession() {
    $_SESSION = array();
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
}

// Start session when this file is included
startSession();
?>