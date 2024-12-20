<?php
// header.php
session_start();
require 'config.php';

// Redirect to login if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Flight Ticket Booking System</title>
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body>
    <header>
        <h1>Flight Ticket Booking System</h1>
        <nav>
            <a href="book_flight.php">Book Flights</a> |
            <a href="view_bookings.php">My Bookings</a> |
            <a href="logout.php">Logout</a>
        </nav>
        <hr>
    </header>
