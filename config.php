<?php
// config.php

$servername = "mysql"; // Use the name of the MySQL service in your Docker Compose file
$username = "deucalion"; // MySQL username defined in your Compose file
$password = "rmaftbs"; // MySQL password defined in your Compose file
$dbname = "FlightTicketBookingSystem"; // Database name defined in your Compose file

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
