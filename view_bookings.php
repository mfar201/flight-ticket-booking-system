<?php
// view_bookings.php
session_start();
require 'config.php';
require 'csrf_helper.php'; // Include the CSRF helper functions

// Redirect to login if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Initialize variables for error and success messages
$errors = isset($_SESSION['errors']) ? $_SESSION['errors'] : [];
$success = isset($_SESSION['success']) ? $_SESSION['success'] : [];

// Unset session messages after fetching
unset($_SESSION['errors']);
unset($_SESSION['success']);

// Generate CSRF token using helper function
$csrf_token = generateCsrfToken();

// Pagination settings
$limit = 5; // Number of bookings per page
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $limit;

// Fetch total number of bookings for the user
$count_query = "
    SELECT COUNT(*) AS total
    FROM bookings b
    WHERE b.user_id = ?
";
$stmt_count = $conn->prepare($count_query);
if ($stmt_count) {
    $stmt_count->bind_param("i", $_SESSION['user_id']);
    $stmt_count->execute();
    $result_count = $stmt_count->get_result();
    $row_count = $result_count->fetch_assoc();
    $total_bookings = intval($row_count['total']);
    $stmt_count->close();
} else {
    $errors[] = "Error fetching booking count: " . htmlspecialchars($conn->error);
    $total_bookings = 0;
}

// Calculate total pages
$total_pages = ceil($total_bookings / $limit);

// Fetch user's bookings with pagination
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
    WHERE b.user_id = ?
    ORDER BY b.book_date DESC
    LIMIT ? OFFSET ?
";

$stmt = $conn->prepare($query);
if ($stmt) {
    $stmt->bind_param("iii", $_SESSION['user_id'], $limit, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
    $bookings = [];
    while ($row = $result->fetch_assoc()) {
        $bookings[] = $row;
    }
    $stmt->close();
} else {
    $errors[] = "Error fetching bookings: " . htmlspecialchars($conn->error);
    $bookings = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Bookings - Flight Ticket Booking System</title>
    <link rel="icon" type="image/x-icon" href="fav.png">
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome for Icons (Optional) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100 min-h-screen flex flex-col">

    <!-- Navbar -->
    <nav class="bg-white shadow">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <!-- Logo -->
                <div class="flex-shrink-0 flex items-center">
                    <a href="welcome_user.php" class="text-xl font-bold text-indigo-600">Flight Ticket Booking System</a>
                </div>
                <!-- Navigation Links -->
                <div class="hidden md:flex space-x-4 items-center">
                    <a href="book_flight.php" class="text-gray-700 hover:text-indigo-600 px-3 py-2 rounded-md text-sm font-medium">Book Flights</a>
                    <a href="view_bookings.php" class="text-gray-700 hover:text-indigo-600 px-3 py-2 rounded-md text-sm font-medium">My Bookings</a>
                    <form action="logout.php" method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <button type="submit" class="text-gray-700 hover:text-indigo-600 px-3 py-2 rounded-md text-sm font-medium">Logout</button>
                    </form>
                </div>
                <!-- Mobile Menu Button -->
                <div class="flex items-center md:hidden">
                    <button id="mobile-menu-button" class="text-gray-700 hover:text-indigo-600 focus:outline-none focus:text-indigo-600">
                        <i class="fas fa-bars fa-lg"></i>
                    </button>
                </div>
            </div>
        </div>
        <!-- Mobile Menu -->
        <div id="mobile-menu" class="hidden md:hidden bg-white shadow-md">
            <a href="book_flight.php" class="block px-4 py-2 text-gray-700 hover:bg-indigo-50">Book Flights</a>
            <a href="view_bookings.php" class="block px-4 py-2 text-gray-700 hover:bg-indigo-50">My Bookings</a>
            <form action="logout.php" method="POST" class="px-4 py-2">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <button type="submit" class="w-full text-left text-gray-700 hover:bg-indigo-50">Logout</button>
            </form>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="flex-grow container mx-auto px-4 py-8">
        <h2 class="text-2xl font-semibold text-gray-800 mb-6">My Bookings</h2>

        <!-- Display Success and Error Messages -->
        <?php if (!empty($errors)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6" role="alert">
                <ul class="list-disc pl-5">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6" role="alert">
                <ul class="list-disc pl-5">
                    <?php foreach ($success as $msg): ?>
                        <li><?php echo htmlspecialchars($msg); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if (!empty($bookings)): ?>
            <!-- Table Container with Horizontal Scroll -->
            <div class="overflow-x-auto">
                <!-- Compact Table with Tailwind CSS -->
                <table class="min-w-max w-full table-auto bg-white shadow rounded-lg">
                    <thead>
                        <tr class="bg-gray-50 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                            <th class="py-3 px-4">Booking ID</th>
                            <th class="py-3 px-4">Flight Number</th>
                            <th class="py-3 px-4">Departure</th>
                            <th class="py-3 px-4">Arrival</th>
                            <th class="py-3 px-4">Passenger Name</th>
                            <th class="py-3 px-4">Seat #</th>
                            <th class="py-3 px-4">Fare ($)</th>
                            <th class="py-3 px-4">Status</th>
                            <th class="py-3 px-4 text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-600 text-sm">
                        <?php foreach ($bookings as $booking): ?>
                            <!-- Main Row -->
                            <tr class="border-b border-gray-200 hover:bg-gray-100 cursor-pointer main-row" tabindex="0" aria-expanded="false">
                                <td class="py-2 px-4"><?php echo htmlspecialchars($booking['booking_id']); ?></td>
                                <td class="py-2 px-4"><?php echo htmlspecialchars($booking['flight_num']); ?></td>
                                <td class="py-2 px-4"><?php echo htmlspecialchars($booking['departure_datetime']); ?></td>
                                <td class="py-2 px-4"><?php echo htmlspecialchars($booking['arrival_datetime']); ?></td>
                                <td class="py-2 px-4"><?php echo htmlspecialchars($booking['passenger_name']); ?></td>
                                <td class="py-2 px-4"><?php echo htmlspecialchars($booking['seat_num']); ?></td>
                                <td class="py-2 px-4"><?php echo number_format($booking['fare'], 2); ?></td>
                                <td class="py-2 px-4"><?php echo htmlspecialchars($booking['status']); ?></td>
                                <td class="py-2 px-4 text-center">
                                    <?php if ($booking['status'] == 'Confirmed'): ?>
                                        <a href="download_ticket.php?booking_id=<?php echo urlencode($booking['booking_id']); ?>" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-1 px-3 rounded mr-2 inline-flex items-center">
                                            <i class="fas fa-download mr-1"></i> Download
                                        </a>
                                    <?php endif; ?>

                                    <?php if ($booking['status'] != 'Cancelled'): ?>
                                        <form action="cancel_booking.php" method="POST" onsubmit="return confirm('Are you sure you want to cancel this booking?');" class="inline">
                                            <input type="hidden" name="booking_id" value="<?php echo htmlspecialchars($booking['booking_id']); ?>">
                                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                            <button type="submit" class="bg-red-500 hover:bg-red-700 text-white font-bold py-1 px-3 rounded inline-flex items-center">
                                                <i class="fas fa-times mr-1"></i> Cancel
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span class="text-gray-500">N/A</span>
                                    <?php endif; ?>
                                </td>
                            </tr>

                            <!-- Detailed Row (Initially Hidden) -->
                            <tr class="bg-gray-100 detail-row hidden">
                                <td colspan="9" class="py-2 px-4">
                                    <div class="p-4 bg-white rounded-lg shadow-md">
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            <!-- Left Column -->
                                            <div>
                                                <h3 class="text-sm font-semibold text-indigo-600 mb-2">Flight Details</h3>
                                                <p class="text-gray-700"><strong>Airline:</strong> <?php echo htmlspecialchars($booking['airline_name']); ?></p>
                                                <p class="text-gray-700"><strong>Aircraft:</strong> <?php echo htmlspecialchars($booking['aircraft_model']); ?></p>
                                                <p class="text-gray-700"><strong>Route ID:</strong> <?php echo htmlspecialchars($booking['route_id']); ?></p>
                                            </div>
                                            <!-- Right Column -->
                                            <div>
                                                <h3 class="text-sm font-semibold text-indigo-600 mb-2">Origin</h3>
                                                <p class="text-gray-700"><strong>Name:</strong> <?php echo htmlspecialchars($booking['origin_name']); ?></p>
                                                <p class="text-gray-700"><strong>Location:</strong> <?php echo htmlspecialchars($booking['origin_location']); ?></p>
                                                <p class="text-gray-700"><strong>City:</strong> <?php echo htmlspecialchars($booking['origin_city']); ?></p>
                                                <p class="text-gray-700"><strong>Country:</strong> <?php echo htmlspecialchars($booking['origin_country']); ?></p>
                                            </div>
                                            <!-- Additional Columns -->
                                            <div>
                                                <h3 class="text-sm font-semibold text-indigo-600 mb-2">Destination</h3>
                                                <p class="text-gray-700"><strong>Name:</strong> <?php echo htmlspecialchars($booking['destination_name']); ?></p>
                                                <p class="text-gray-700"><strong>Location:</strong> <?php echo htmlspecialchars($booking['destination_location']); ?></p>
                                                <p class="text-gray-700"><strong>City:</strong> <?php echo htmlspecialchars($booking['destination_city']); ?></p>
                                                <p class="text-gray-700"><strong>Country:</strong> <?php echo htmlspecialchars($booking['destination_country']); ?></p>
                                            </div>
                                            <div>
                                                <h3 class="text-sm font-semibold text-indigo-600 mb-2">Passenger Details</h3>
                                                <p class="text-gray-700"><strong>Phone:</strong> <?php echo htmlspecialchars($booking['phone_num']); ?></p>
                                                <p class="text-gray-700"><strong>Date of Birth:</strong> <?php echo htmlspecialchars($booking['dob']); ?></p>
                                                <p class="text-gray-700"><strong>Passport #:</strong> <?php echo htmlspecialchars($booking['passport_num']); ?></p>
                                                <p class="text-gray-700"><strong>Nationality:</strong> <?php echo htmlspecialchars($booking['nationality']); ?></p>
                                                <p class="text-gray-700"><strong>Gender:</strong> <?php echo htmlspecialchars($booking['gender']); ?></p>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination Controls -->
            <?php if ($total_pages > 1): ?>
                <div class="flex justify-center mt-6 space-x-2">
                    <!-- Previous Page Button -->
                    <a href="?page=<?php echo max($page - 1, 1); ?>" class="px-4 py-2 bg-indigo-500 text-white rounded hover:bg-indigo-700 <?php echo ($page <= 1) ? 'opacity-50 cursor-not-allowed' : ''; ?>">
                        Previous
                    </a>

                    <!-- Page Numbers -->
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?page=<?php echo $i; ?>" class="px-4 py-2 <?php echo ($i == $page) ? 'bg-indigo-700 text-white' : 'bg-indigo-500 text-white hover:bg-indigo-700'; ?> rounded">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>

                    <!-- Next Page Button -->
                    <a href="?page=<?php echo min($page + 1, $total_pages); ?>" class="px-4 py-2 bg-indigo-500 text-white rounded hover:bg-indigo-700 <?php echo ($page >= $total_pages) ? 'opacity-50 cursor-not-allowed' : ''; ?>">
                        Next
                    </a>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <p class="text-gray-700">You have no bookings.</p>
        <?php endif; ?>
    </main>

    <!-- Footer -->
    <?php include 'footer.php'; ?>

    <!-- Tailwind CSS Mobile Menu Toggle and Expandable Rows Script -->
    <script>
        // Toggle Mobile Menu
        const btn = document.getElementById('mobile-menu-button');
        const menu = document.getElementById('mobile-menu');

        btn.addEventListener('click', () => {
            menu.classList.toggle('hidden');
        });

        // JavaScript for Expandable Rows
        document.addEventListener('DOMContentLoaded', function() {
            const mainRows = document.querySelectorAll('.main-row');

            mainRows.forEach((row) => {
                // Click Event
                row.addEventListener('click', function() {
                    toggleDetailRow(this);
                });

                // Keyboard Event (Enter or Space)
                row.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        toggleDetailRow(this);
                    }
                });
            });

            function toggleDetailRow(row) {
                const detailRow = row.nextElementSibling;
                if (detailRow && detailRow.classList.contains('detail-row')) {
                    detailRow.classList.toggle('hidden');
                    // Update ARIA attribute
                    const isExpanded = !detailRow.classList.contains('hidden');
                    row.setAttribute('aria-expanded', isExpanded);
                }
            }
        });
    </script>
</body>
</html>
