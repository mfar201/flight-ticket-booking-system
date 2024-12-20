<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCsrfToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// UPDATED!!!
// function validateCsrfToken($token) {
//     if (isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token)) {
//         // Regenerate token after successful validation
//         unset($_SESSION['csrf_token']);
//         return true;
//     }
//     return false;
// }

?>
