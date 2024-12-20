<?php
// cancel_booking.php

session_start();
require 'config.php';
require 'csrf_helper.php'; // Include the CSRF helper functions

// Redirect to login if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Initialize arrays for error and success messages
$errors = [];
$success = [];

// Check if booking_id is provided via POST
if (!isset($_POST['booking_id'])) {
    $errors[] = "No booking specified for cancellation.";
    $_SESSION['errors'] = $errors;
    header("Location: view_bookings.php");
    exit();
}

// Validate CSRF token
if (!isset($_POST['csrf_token']) || !validateCsrfToken($_POST['csrf_token'])) {
    $errors[] = "Invalid CSRF token.";
    $_SESSION['errors'] = $errors;
    header("Location: view_bookings.php");
    exit();
}

$booking_id = intval($_POST['booking_id']);

// Validate booking_id
if ($booking_id <= 0) {
    $errors[] = "Invalid booking ID.";
    $_SESSION['errors'] = $errors;
    header("Location: view_bookings.php");
    exit();
}

// Fetch booking details including seat_num
$query = "
    SELECT 
        b.booking_id,
        b.status,
        b.flight_id,
        b.seat_type,
        b.seat_num
    FROM bookings b
    WHERE b.booking_id = ? AND b.user_id = ?
    LIMIT 1
";

$stmt = $conn->prepare($query);
if ($stmt) {
    $stmt->bind_param("ii", $booking_id, $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows != 1) {
        $errors[] = "Booking not found or does not belong to you.";
        $stmt->close();
        $_SESSION['errors'] = $errors;
        header("Location: view_bookings.php");
        exit();
    }
    
    $booking = $result->fetch_assoc();
    $stmt->close();
} else {
    $errors[] = "Error fetching booking details: " . htmlspecialchars($conn->error);
    $_SESSION['errors'] = $errors;
    header("Location: view_bookings.php");
    exit();
}

// Define eligible statuses for cancellation
$eligible_statuses = ['Pending', 'Confirmed']; // Adjust based on business rules

// Check if booking is eligible for cancellation
if (!in_array($booking['status'], $eligible_statuses)) {
    $errors[] = "This booking cannot be canceled as it is currently '" . htmlspecialchars($booking['status']) . "'.";
    $_SESSION['errors'] = $errors;
    header("Location: view_bookings.php");
    exit();
}

// Begin Transaction
$conn->begin_transaction();

try {
    // Update booking status to 'Cancelled'
    $update_booking_query = "
        UPDATE bookings 
        SET status = 'Cancelled' 
        WHERE booking_id = ?
    ";
    $stmt_update = $conn->prepare($update_booking_query);
    if (!$stmt_update) {
        throw new Exception("Error preparing booking update: " . htmlspecialchars($conn->error));
    }
    $stmt_update->bind_param("i", $booking_id);
    if (!$stmt_update->execute()) {
        throw new Exception("Error updating booking status: " . htmlspecialchars($stmt_update->error));
    }
    $stmt_update->close();
    
    // Retrieve seat_type to determine which seat count to increment
    $seat_type = $booking['seat_type'];
    
    // Map seat_type to corresponding column in flights table
    $seat_column_mapping = [
        'Economy' => 'seat_economy',
        'Business' => 'seat_business',
        'First Class' => 'seat_first_class'
    ];
    
    if (!array_key_exists($seat_type, $seat_column_mapping)) {
        throw new Exception("Invalid seat type associated with the booking.");
    }
    
    $seat_column = $seat_column_mapping[$seat_type];
    
    // Increment the seat count in flights table
    $update_seat_query = "
        UPDATE flights 
        SET $seat_column = $seat_column + 1 
        WHERE flight_id = ?
    ";
    $stmt_seat = $conn->prepare($update_seat_query);
    if (!$stmt_seat) {
        throw new Exception("Error preparing seat count update: " . htmlspecialchars($conn->error));
    }
    $stmt_seat->bind_param("i", $booking['flight_id']);
    if (!$stmt_seat->execute()) {
        throw new Exception("Error updating seat count: " . htmlspecialchars($stmt_seat->error));
    }
    $stmt_seat->close();
    
    // Commit Transaction
    $conn->commit();
    
    $success[] = "Booking ID " . htmlspecialchars($booking_id) . " has been successfully canceled.";
    $_SESSION['success'] = $success;
    header("Location: view_bookings.php");
    exit();
    
} catch (Exception $e) {
    // Rollback Transaction
    $conn->rollback();
    
    $errors[] = "Failed to cancel booking: " . htmlspecialchars($e->getMessage());
    $_SESSION['errors'] = $errors;
    header("Location: view_bookings.php");
    exit();
}
?>
