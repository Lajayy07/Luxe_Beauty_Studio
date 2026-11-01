
<?php
// Start the session if it has not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Require the customer to be logged in
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

// Set session timeout to 30 minutes
$timeout = 1800;

// Check for inactivity
if (
    isset($_SESSION["last_activity"]) &&
    (time() - $_SESSION["last_activity"]) > $timeout
) {
    // Clear session data
    $_SESSION = [];

    // Remove session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();

        setcookie(
            session_name(),
            "",
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }

    session_destroy();

    header("Location: login.php?expired=1");
    exit;
}

// Update last activity time
$_SESSION["last_activity"] = time();
?>
