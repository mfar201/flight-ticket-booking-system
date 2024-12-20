<?php
// delete_airport.php
session_start();
require 'config.php';
require 'csrf_helper.php';

// Check if user is logged in and is an Admin
if (!isset($_SESSION['user_id']) || $_SESSION['role_name'] != 'Admin') {
    header("Location: login.php");
    exit();
}

// Ensure the request method is POST
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    $_SESSION['errors'][] = "Invalid request method.";
    header("Location: manage_airports.php");
    exit();
}

// Validate CSRF token
if (!isset($_POST['csrf_token']) || !validateCsrfToken($_POST['csrf_token'])) {
    $_SESSION['errors'][] = "Invalid CSRF token.";
    header("Location: manage_airports.php");
    exit();
}

// Check if airport_id is set
if (!isset($_POST['airport_id'])) {
    $_SESSION['errors'][] = "Airport ID not provided.";
    header("Location: manage_airports.php");
    exit();
}

$airport_id = intval($_POST['airport_id']);
$errors = [];
$success = [];

// Delete the airport
$stmt = $conn->prepare("DELETE FROM airports WHERE airport_id = ?");
if ($stmt) {
    $stmt->bind_param("i", $airport_id);
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            $success[] = "Airport deleted successfully.";
        } else {
            $errors[] = "Airport not found.";
        }
    } else {
        // Check for foreign key constraint violation
        if ($conn->errno == 1451) { // Cannot delete or update a parent row: a foreign key constraint fails
            $errors[] = "Cannot delete airport. It is associated with existing routes or flights.";
        } else {
            $errors[] = "Error deleting airport: " . $stmt->error;
        }
    }
    $stmt->close();
} else {
    $errors[] = "Error preparing statement: " . $conn->error;
}

// Store messages in session to persist after redirect
if (!empty($errors)) {
    $_SESSION['errors'] = $errors;
}
if (!empty($success)) {
    $_SESSION['success'] = $success;
}

header("Location: manage_airports.php");
exit();
?>
