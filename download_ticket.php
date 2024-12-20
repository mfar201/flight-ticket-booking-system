<?php
// download_ticket.php
session_start();
require 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Validate and sanitize the booking_id
if (!isset($_GET['booking_id']) || !filter_var($_GET['booking_id'], FILTER_VALIDATE_INT)) {
    echo "Invalid Booking ID.";
    exit();
}

$booking_id = intval($_GET['booking_id']);

// Fetch the booking details ensuring it belongs to the logged-in user and is confirmed
$query = "
    SELECT 
        b.booking_id,
        b.status,
        b.book_date,
        b.fare,
        f.flight_num,
        f.departure_datetime,
        f.arrival_datetime,
        a.name AS airline_name,
        ac.model AS aircraft_model,
        r.route_id,
        origin.name AS origin_name,
        origin.location AS origin_location,
        origin.city AS origin_city,
        origin.country AS origin_country,
        destination.name AS destination_name,
        destination.location AS destination_location,
        destination.city AS destination_city,
        destination.country AS destination_country,
        p.name AS passenger_name,
        p.phone_num,
        p.dob,
        p.passport_num,
        p.nationality,
        p.gender,
        b.seat_type,
        b.seat_num
    FROM bookings b
    JOIN flights f ON b.flight_id = f.flight_id
    JOIN airlines a ON f.airline_id = a.airline_id
    JOIN aircrafts ac ON f.aircraft_id = ac.aircraft_id
    JOIN routes r ON f.route_id = r.route_id
    JOIN airports origin ON r.origin_airport_id = origin.airport_id
    JOIN airports destination ON r.destination_airport_id = destination.airport_id
    JOIN passengers p ON b.passenger_id = p.passenger_id
    WHERE b.booking_id = ? AND b.user_id = ? AND b.status = 'Confirmed'
    LIMIT 1
";

$stmt = $conn->prepare($query);
if ($stmt) {
    $stmt->bind_param("ii", $booking_id, $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows != 1) {
        echo "Booking not found or you do not have permission to download this ticket.";
        exit();
    }
    $booking = $result->fetch_assoc();
    $stmt->close();
} else {
    echo "Error fetching booking: " . htmlspecialchars($conn->error);
    exit();
}

// Generate the ticket content
$ticket_content = "";
$ticket_content .= "===== Flight Ticket =====\n\n";
$ticket_content .= "Booking ID: " . $booking['booking_id'] . "\n";
$ticket_content .= "Flight Number: " . $booking['flight_num'] . "\n";
$ticket_content .= "Departure: " . $booking['departure_datetime'] . "\n";
$ticket_content .= "Arrival: " . $booking['arrival_datetime'] . "\n";
$ticket_content .= "Airline: " . $booking['airline_name'] . "\n";
$ticket_content .= "Aircraft: " . $booking['aircraft_model'] . "\n";
$ticket_content .= "Route ID: " . $booking['route_id'] . "\n\n";

$ticket_content .= "Origin Details:\n";
$ticket_content .= "  Name: " . $booking['origin_name'] . "\n";
$ticket_content .= "  Location: " . $booking['origin_location'] . "\n";
$ticket_content .= "  City: " . $booking['origin_city'] . "\n";
$ticket_content .= "  Country: " . $booking['origin_country'] . "\n\n";

$ticket_content .= "Destination Details:\n";
$ticket_content .= "  Name: " . $booking['destination_name'] . "\n";
$ticket_content .= "  Location: " . $booking['destination_location'] . "\n";
$ticket_content .= "  City: " . $booking['destination_city'] . "\n";
$ticket_content .= "  Country: " . $booking['destination_country'] . "\n\n";

$ticket_content .= "Passenger Details:\n";
$ticket_content .= "  Name: " . $booking['passenger_name'] . "\n";
$ticket_content .= "  Phone Number: " . $booking['phone_num'] . "\n";
$ticket_content .= "  Date of Birth: " . $booking['dob'] . "\n";
$ticket_content .= "  Passport Number: " . $booking['passport_num'] . "\n";
$ticket_content .= "  Nationality: " . $booking['nationality'] . "\n";
$ticket_content .= "  Gender: " . $booking['gender'] . "\n\n";

$ticket_content .= "Seat Details:\n";
$ticket_content .= "  Seat Type: " . $booking['seat_type'] . "\n";
$ticket_content .= "  Seat Number: " . $booking['seat_num'] . "\n\n";

$ticket_content .= "Fare: $" . number_format($booking['fare'], 2) . "\n";
$ticket_content .= "Booking Date: " . $booking['book_date'] . "\n";
$ticket_content .= "==========================\n";

// Set headers to prompt file download
$filename = "ticket_booking_" . $booking['booking_id'] . ".txt";

header('Content-Type: text/plain');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($ticket_content));

// Output the ticket content
echo $ticket_content;
exit();
?>
