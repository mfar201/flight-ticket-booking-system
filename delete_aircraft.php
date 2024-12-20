<?php
// delete_aircraft.php
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

// Check if the request method is POST
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !validateCsrfToken($_POST['csrf_token'])) {
        $errors[] = "Invalid CSRF token.";
    } else {
        // Check if aircraft_id is set
        if (!isset($_POST['aircraft_id'])) {
            $errors[] = "Aircraft ID not provided.";
        } else {
            $aircraft_id = intval($_POST['aircraft_id']);

            // Delete the aircraft
            $stmt = $conn->prepare("DELETE FROM aircrafts WHERE aircraft_id = ?");
            if ($stmt) {
                $stmt->bind_param("i", $aircraft_id);
                if ($stmt->execute()) {
                    if ($stmt->affected_rows > 0) {
                        $success[] = "Aircraft deleted successfully.";
                    } else {
                        $errors[] = "Aircraft not found.";
                    }
                } else {
                    // Check for foreign key constraint violation
                    if ($conn->errno == 1451) { // Cannot delete or update a parent row: a foreign key constraint fails
                        $errors[] = "Cannot delete aircraft. It is associated with existing flights.";
                    } else {
                        $errors[] = "Error deleting aircraft: " . $stmt->error;
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

// Redirect back to manage_aircrafts.php with status
if (!empty($errors)) {
    $_SESSION['errors'] = $errors;
}
if (!empty($success)) {
    $_SESSION['success'] = $success;
}

header("Location: manage_aircrafts.php");
exit();
?>
