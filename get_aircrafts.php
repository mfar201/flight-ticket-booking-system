<?php
// get_aircrafts.php
session_start();
require 'config.php';

// Check if user is logged in and is an Admin
if (!isset($_SESSION['user_id']) || $_SESSION['role_name'] != 'Admin') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access.']);
    exit();
}

if (!isset($_GET['airline_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Airline ID not provided.']);
    exit();
}

$airline_id = intval($_GET['airline_id']);

// Fetch aircrafts for the given airline
$stmt = $conn->prepare("SELECT aircraft_id, model, seat_economy, seat_business, seat_first_class FROM aircrafts WHERE airline_id = ? ORDER BY model ASC");
if ($stmt) {
    $stmt->bind_param("i", $airline_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $aircrafts = [];
    while ($row = $result->fetch_assoc()) {
        $aircrafts[] = $row;
    }
    $stmt->close();
    echo json_encode(['status' => 'success', 'data' => $aircrafts]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Error fetching aircrafts.']);
}
?>
