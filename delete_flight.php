<?php
// delete_flight.php
session_start();
require 'config.php';
require 'csrf_helper.php'; // Include CSRF helper

// Check if user is logged in and is an Admin
if (!isset($_SESSION['user_id']) || $_SESSION['role_name'] != 'Admin') {
    header("Location: login.php");
    exit();
}

// Only accept POST requests
if ($_SERVER["REQUEST_METHOD"] != "POST") {
    $_SESSION['errors'] = ["Invalid request method."];
    header("Location: manage_flights.php");
    exit();
}

// Validate CSRF token
if (!isset($_POST['csrf_token']) || !validateCsrfToken($_POST['csrf_token'])) {
    $_SESSION['errors'] = ["Invalid CSRF token."];
    header("Location: manage_flights.php");
    exit();
}

// Check if flight_id is set
if (!isset($_POST['flight_id'])) {
    $_SESSION['errors'] = ["Flight ID not provided."];
    header("Location: manage_flights.php");
    exit();
}

$flight_id = intval($_POST['flight_id']);
$errors = [];
$success = [];

// Delete the flight
$stmt = $conn->prepare("DELETE FROM flights WHERE flight_id = ?");
if ($stmt) {
    $stmt->bind_param("i", $flight_id);
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            $success[] = "Flight deleted successfully.";
        } else {
            $errors[] = "Flight not found.";
        }
    } else {
        // Check for foreign key constraint violation
        if ($conn->errno == 1451) { // Cannot delete or update a parent row: a foreign key constraint fails
            $errors[] = "Cannot delete flight. It is associated with existing tickets or bookings.";
        } else {
            $errors[] = "Error deleting flight: " . $stmt->error;
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

header("Location: manage_flights.php");
exit();
?>
