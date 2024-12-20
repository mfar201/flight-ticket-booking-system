<?php
// delete_airline.php
session_start();
require 'config.php';
require 'csrf_helper.php'; // Include CSRF helper functions

// Check if user is logged in and is an Admin
if (!isset($_SESSION['user_id']) || $_SESSION['role_name'] != 'Admin') {
    header("Location: login.php");
    exit();
}

// Validate CSRF token
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['csrf_token']) || !validateCsrfToken($_POST['csrf_token'])) {
    die('Invalid CSRF token.');
}

// Check if airline_id is set
if (!isset($_POST['airline_id']) || !is_numeric($_POST['airline_id'])) {
    die('Invalid or missing airline ID.');
}

$airline_id = intval($_POST['airline_id']);
$errors = [];
$success = [];

// Delete the airline
$stmt = $conn->prepare("DELETE FROM airlines WHERE airline_id = ?");
if ($stmt) {
    $stmt->bind_param("i", $airline_id);
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            $success[] = "Airline deleted successfully.";
        } else {
            $errors[] = "Airline not found.";
        }
    } else {
        // Check for foreign key constraint violation
        if ($conn->errno == 1451) { // Cannot delete or update a parent row: a foreign key constraint fails
            $errors[] = "Cannot delete airline. It is associated with existing flights.";
        } else {
            $errors[] = "Error deleting airline: " . $stmt->error;
        }
    }
    $stmt->close();
} else {
    $errors[] = "Error preparing statement: " . $conn->error;
}

// Redirect back to manage_airlines.php with status
if (!empty($errors)) {
    $_SESSION['errors'] = $errors;
}
if (!empty($success)) {
    $_SESSION['success'] = $success;
}

header("Location: manage_airlines.php");
exit();
?>
