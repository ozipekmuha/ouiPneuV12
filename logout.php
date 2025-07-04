<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Unset all of the session variables.
$_SESSION = array();

// If it's desired to kill the session, also delete the session cookie.
// Note: This will destroy the session, and not just the session data!
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Finally, destroy the session.
session_destroy();

// Set a logout message to be displayed on the login page
session_start(); // Need to start a new session to store the message
$_SESSION['logout_message'] = "Vous avez été déconnecté avec succès.";

// Redirect to the login page (or home page)
header("Location: login.php");
exit;
?>
