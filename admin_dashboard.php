<?php
// admin_dashboard.php

session_start();
require 'config.php';

// Check if user is logged in and is an Admin
if (!isset($_SESSION['user_id']) || $_SESSION['role_name'] != 'Admin') {
    header("Location: login.php");
    exit();
}

// Fetch summary counts for each booking status
$status_counts = [
    'Confirmed' => 0,
    'Pending' => 0,
    'Cancelled' => 0
];

foreach ($status_counts as $status => &$count) {
    $stmt = $conn->prepare("SELECT COUNT(*) AS count FROM bookings WHERE status = ?");
    if ($stmt) {
        $stmt->bind_param("s", $status);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $count = $row['count'];
        $stmt->close();
    } else {
        $count = "Error";
    }
}
unset($count); // Break the reference

?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin Dashboard - Flight Ticket Booking System</title>
    <link rel="icon" type="image/x-icon" href="fav.png">
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .summary { display: flex; gap: 20px; }
        .card { border: 1px solid #ccc; padding: 20px; border-radius: 5px; width: 200px; text-align: center; }
        .card h3 { margin-bottom: 10px; }
        .card p { font-size: 24px; margin: 0; }
        nav a { margin-right: 15px; text-decoration: none; color: blue; }
    </style>
</head>
<body>
    <h2>Admin Dashboard</h2>
    <nav>
        <a href="admin_confirmed_bookings.php">Confirmed Bookings</a> |
        <a href="admin_pending_bookings.php">Pending Bookings</a> |
        <a href="admin_cancelled_bookings.php">Cancelled Bookings</a> |
        <a href="admin_dashboard.php">Dashboard</a> |
        <a href="logout.php">Logout</a>
    </nav>
    <hr>
    
    <div class="summary">
        <div class="card">
            <h3>Confirmed Bookings</h3>
            <p><?php echo htmlspecialchars($status_counts['Confirmed']); ?></p>
            <a href="admin_confirmed_bookings.php">View</a>
        </div>
        <div class="card">
            <h3>Pending Bookings</h3>
            <p><?php echo htmlspecialchars($status_counts['Pending']); ?></p>
            <a href="admin_pending_bookings.php">View</a>
        </div>
        <div class="card">
            <h3>Cancelled Bookings</h3>
            <p><?php echo htmlspecialchars($status_counts['Cancelled']); ?></p>
            <a href="admin_cancelled_bookings.php">View</a>
        </div>
    </div>
</body>
</html>
<?php
// Optional: Include a footer if you have one
// require 'footer.php';
?>
