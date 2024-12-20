<?php
// logout.php
session_start();
require 'csrf_helper.php'; // Include the CSRF helper functions

// Check if the request is POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate the CSRF token
    if (!isset($_POST['csrf_token']) || !validateCsrfToken($_POST['csrf_token'])) {
        // Token is invalid, terminate the session and show an error message
        die('Invalid CSRF token. Please try again.');
    }

    // Destroy all session data
    $_SESSION = array();
    session_destroy();

    // Redirect to login page
    header("Location: login.php");
    exit();
} else {
    // If the request is not POST, reject it
    http_response_code(405); // Method Not Allowed
    echo "This action is not allowed.";
    exit();
}
