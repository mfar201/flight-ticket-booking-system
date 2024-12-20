<?php
// delete_route.php
session_start();
require 'config.php';
require 'csrf_helper.php';

// Check if user is logged in and is an Admin
if (!isset($_SESSION['user_id']) || $_SESSION['role_name'] != 'Admin') {
    header("Location: login.php");
    exit();
}

// Initialize variables for error and success messages
$errors = [];
$success = [];

// Handle only POST requests
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !validateCsrfToken($_POST['csrf_token'])) {
        $errors[] = "Invalid CSRF token.";
    } else {
        // Retrieve and sanitize route_id
        if (!isset($_POST['route_id'])) {
            $errors[] = "Route ID not provided.";
        } else {
            $route_id = intval($_POST['route_id']);
            
            // Delete the route
            $stmt = $conn->prepare("DELETE FROM routes WHERE route_id = ?");
            if ($stmt) {
                $stmt->bind_param("i", $route_id);
                if ($stmt->execute()) {
                    if ($stmt->affected_rows > 0) {
                        $success[] = "Route deleted successfully.";
                    } else {
                        $errors[] = "Route not found.";
                    }
                } else {
                    // Check for foreign key constraint violation
                    if ($conn->errno == 1451) { // Cannot delete or update a parent row: a foreign key constraint fails
                        $errors[] = "Cannot delete route. It is associated with existing flights.";
                    } else {
                        $errors[] = "Error deleting route: " . $stmt->error;
                    }
                }
                $stmt->close();
            } else {
                $errors[] = "Error preparing statement: " . $conn->error;
            }
        }
    }
} else {
    $errors[] = "Invalid request method.";
}

// Store messages in session to persist after redirect
if (!empty($errors)) {
    $_SESSION['errors'] = $errors;
}
if (!empty($success)) {
    $_SESSION['success'] = $success;
}

// Redirect back to manage_routes.php
header("Location: manage_routes.php");
exit();
?>
