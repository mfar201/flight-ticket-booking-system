<?php
// admin_cancelled_bookings.php

session_start();
require 'config.php';
require 'csrf_helper.php';

// Check if user is logged in and is an Admin
if (!isset($_SESSION['user_id']) || $_SESSION['role_name'] != 'Admin') {
    header("Location: login.php");
    exit();
}

// Initialize arrays for error and success messages
$errors = isset($_SESSION['errors']) ? $_SESSION['errors'] : [];
$success = isset($_SESSION['success']) ? $_SESSION['success'] : [];

// Unset session messages after fetching
unset($_SESSION['errors']);
unset($_SESSION['success']);

// Pagination Variables
$limit = 10; // Number of bookings per page
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $limit;

// Fetch Cancelled Bookings with Pagination
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
    WHERE b.status = 'Cancelled'
    ORDER BY b.book_date DESC
    LIMIT ? OFFSET ?
";

$stmt = $conn->prepare($query);
if ($stmt) {
    $stmt->bind_param("ii", $limit, $offset);
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

// Get Total Number of Cancelled Bookings for Pagination
$count_query = "SELECT COUNT(*) as total FROM bookings b WHERE b.status = 'Cancelled'";
$count_stmt = $conn->prepare($count_query);
if ($count_stmt) {
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
    $row_count = $count_result->fetch_assoc();
    $total_records = intval($row_count['total']);
    $count_stmt->close();
} else {
    $errors[] = "Error fetching booking count: " . htmlspecialchars($conn->error);
    $total_records = 0;
}

$total_pages = ceil($total_records / $limit);

// Function to build query parameters for pagination links
function build_query_params($exclude = []) {
    $params = $_GET;
    foreach ($exclude as $key) {
        unset($params[$key]);
    }
    return http_build_query($params) ? '&' . http_build_query($params) : '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Cancelled Bookings - Flight Ticket Booking System</title>
    <link rel="icon" type="image/x-icon" href="fav.png">
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100 flex flex-col min-h-screen">

    <!-- Navbar -->
    <nav class="bg-white shadow">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <!-- Logo -->
                <div class="flex-shrink-0 flex items-center">
                    <a href="welcome_admin.php" class="text-xl font-bold text-indigo-600">Flight Ticket Booking System (Admin)</a>
                </div>
                <!-- Navigation Links -->
                <div class="hidden md:flex space-x-4 items-center">
                    <a href="list_users.php" class="text-gray-700 hover:text-indigo-600 px-3 py-2 rounded-md text-sm font-medium">List Users</a>
                    
                    <!-- Manage Dropdown -->
                    <div class="relative dropdown">
                        <button class="flex items-center text-gray-700 hover:text-indigo-600 px-3 py-2 rounded-md text-sm font-medium focus:outline-none" aria-haspopup="true" aria-expanded="false">
                            Manage
                            <svg class="ml-1 h-4 w-4 fill-current" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                <path d="M5.516 7.548a.75.75 0 011.06.02L10 10.939l3.424-3.37a.75.75 0 111.06 1.06l-4 3.92a.75.75 0 01-1.06 0l-4-3.92a.75.75 0 01.02-1.06z" />
                            </svg>
                        </button>
                        <div class="dropdown-menu absolute left-0 mt-2 w-48 bg-white border border-gray-200 rounded-md shadow-lg hidden">
                            <a href="manage_airlines.php" class="block px-4 py-2 text-gray-700 hover:bg-gray-100">Manage Airlines</a>
                            <a href="manage_aircrafts.php" class="block px-4 py-2 text-gray-700 hover:bg-gray-100">Manage Aircrafts</a>
                            <a href="manage_airports.php" class="block px-4 py-2 text-gray-700 hover:bg-gray-100">Manage Airports</a>
                            <a href="manage_routes.php" class="block px-4 py-2 text-gray-700 hover:bg-gray-100">Manage Routes</a>
                            <a href="manage_flights.php" class="block px-4 py-2 text-gray-700 hover:bg-gray-100">Manage Flights</a>
                        </div>
                    </div>

                    <a href="admin_confirmed_bookings.php" class="text-gray-700 hover:text-indigo-600 px-3 py-2 rounded-md text-sm font-medium">Confirmed Bookings</a>
                    <a href="admin_pending_bookings.php" class="text-gray-700 hover:text-indigo-600 px-3 py-2 rounded-md text-sm font-medium">Pending Bookings</a>
                    <a href="admin_cancelled_bookings.php" class="text-gray-700 hover:text-indigo-600 px-3 py-2 rounded-md text-sm font-medium">Cancelled Bookings</a>
                    
                    <form action="logout.php" method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCsrfToken()); ?>">
                        <button type="submit" class="text-gray-700 hover:text-indigo-600 px-3 py-2 rounded-md text-sm font-medium">Logout</button>
                    </form>
                </div>
                <!-- Mobile Menu Button -->
                <div class="flex items-center md:hidden">
                    <button id="mobile-menu-button" class="text-gray-700 hover:text-indigo-600 focus:outline-none focus:text-indigo-600" aria-label="Toggle Menu">
                        <i class="fas fa-bars fa-lg"></i>
                    </button>
                </div>
            </div>
        </div>
        <!-- Mobile Menu -->
        <div id="mobile-menu" class="hidden md:hidden bg-white shadow-md">
            <a href="list_users.php" class="block px-4 py-2 text-gray-700 hover:bg-indigo-50">List Users</a>
            
            <!-- Manage Dropdown for Mobile -->
            <div class="block px-4 py-2 text-gray-700 hover:bg-indigo-50">
                <span class="flex items-center justify-between">
                    <span>Manage</span>
                    <svg class="h-4 w-4 fill-current" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                        <path d="M5.516 7.548a.75.75 0 011.06.02L10 10.939l3.424-3.37a.75.75 0 111.06 1.06l-4 3.92a.75.75 0 01-1.06 0l-4-3.92a.75.75 0 01.02-1.06z" />
                    </svg>
                </span>
                <div class="mt-2 ml-4 space-y-1">
                    <a href="manage_airlines.php" class="block px-2 py-1 text-gray-700 hover:bg-gray-100">Manage Airlines</a>
                    <a href="manage_aircrafts.php" class="block px-2 py-1 text-gray-700 hover:bg-gray-100">Manage Aircrafts</a>
                    <a href="manage_airports.php" class="block px-2 py-1 text-gray-700 hover:bg-gray-100">Manage Airports</a>
                    <a href="manage_routes.php" class="block px-2 py-1 text-gray-700 hover:bg-gray-100">Manage Routes</a>
                    <a href="manage_flights.php" class="block px-2 py-1 text-gray-700 hover:bg-gray-100">Manage Flights</a>
                </div>
            </div>
            
            <a href="admin_confirmed_bookings.php" class="block px-4 py-2 text-gray-700 hover:bg-indigo-50">Confirmed Bookings</a>
            <a href="admin_pending_bookings.php" class="block px-4 py-2 text-gray-700 hover:bg-indigo-50">Pending Bookings</a>
            <a href="admin_cancelled_bookings.php" class="block px-4 py-2 text-gray-700 hover:bg-indigo-50">Cancelled Bookings</a>
            <form action="logout.php" method="POST" class="px-4 py-2">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCsrfToken()); ?>">
                <button type="submit" class="w-full text-left text-gray-700 hover:bg-indigo-50">Logout</button>
            </form>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="flex-grow container mx-auto px-4 py-8 max-w-7xl">
        <!-- Flash Messages -->
        <?php if (!empty($errors)): ?>
            <div class="mb-6 px-4 py-3 rounded-md bg-red-100 text-red-700">
                <ul class="list-disc pl-5">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="mb-6 px-4 py-3 rounded-md bg-green-100 text-green-700">
                <ul class="list-disc pl-5">
                    <?php foreach ($success as $msg): ?>
                        <li><?php echo htmlspecialchars($msg); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if (!empty($bookings)): ?>
            <!-- Cancelled Bookings Table -->
            <div class="bg-white shadow-md rounded-lg p-6">
                <h2 class="text-2xl font-semibold mb-4 text-indigo-600">Cancelled Bookings</h2>
                <!-- Table Container with Horizontal Scroll -->
                <div class="overflow-x-auto">
                    <!-- Compact Table with Tailwind CSS -->
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Booking ID</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Flight Number</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Departure</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Arrival</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Passenger Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Seat #</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fare ($)</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($bookings as $booking): ?>
                                <!-- Main Row -->
                                <tr class="border-b border-gray-200 hover:bg-gray-100 cursor-pointer main-row" tabindex="0" aria-expanded="false">
                                    <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($booking['booking_id']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($booking['flight_num']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($booking['departure_datetime']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($booking['arrival_datetime']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($booking['passenger_name']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($booking['seat_num']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap"><?php echo number_format($booking['fare'], 2); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($booking['status']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-center">
                                        <!-- Action Column: N/A for Cancelled Bookings -->
                                        <span class="text-gray-500">N/A</span>
                                    </td>
                                </tr>

                                <!-- Detailed Row (Initially Hidden) -->
                                <tr class="bg-gray-100 detail-row hidden">
                                    <td colspan="9" class="px-6 py-4">
                                        <div class="p-4 bg-white rounded-lg shadow-md">
                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                <!-- Flight Details -->
                                                <div>
                                                    <h3 class="text-sm font-semibold text-indigo-600 mb-2">Flight Details</h3>
                                                    <p class="text-gray-700"><strong>Airline:</strong> <?php echo htmlspecialchars($booking['airline_name']); ?></p>
                                                    <p class="text-gray-700"><strong>Aircraft:</strong> <?php echo htmlspecialchars($booking['aircraft_model']); ?></p>
                                                    <p class="text-gray-700"><strong>Route ID:</strong> <?php echo htmlspecialchars($booking['route_id']); ?></p>
                                                </div>
                                                <!-- Origin Details -->
                                                <div>
                                                    <h3 class="text-sm font-semibold text-indigo-600 mb-2">Origin</h3>
                                                    <p class="text-gray-700"><strong>Name:</strong> <?php echo htmlspecialchars($booking['origin_name']); ?></p>
                                                    <p class="text-gray-700"><strong>Location:</strong> <?php echo htmlspecialchars($booking['origin_location']); ?></p>
                                                    <p class="text-gray-700"><strong>City:</strong> <?php echo htmlspecialchars($booking['origin_city']); ?></p>
                                                    <p class="text-gray-700"><strong>Country:</strong> <?php echo htmlspecialchars($booking['origin_country']); ?></p>
                                                </div>
                                                <!-- Destination Details -->
                                                <div>
                                                    <h3 class="text-sm font-semibold text-indigo-600 mb-2">Destination</h3>
                                                    <p class="text-gray-700"><strong>Name:</strong> <?php echo htmlspecialchars($booking['destination_name']); ?></p>
                                                    <p class="text-gray-700"><strong>Location:</strong> <?php echo htmlspecialchars($booking['destination_location']); ?></p>
                                                    <p class="text-gray-700"><strong>City:</strong> <?php echo htmlspecialchars($booking['destination_city']); ?></p>
                                                    <p class="text-gray-700"><strong>Country:</strong> <?php echo htmlspecialchars($booking['destination_country']); ?></p>
                                                </div>
                                                <!-- Passenger Details -->
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
                        <a href="?page=<?php echo max($page - 1, 1); ?><?php echo build_query_params(['page']); ?>" class="px-4 py-2 bg-indigo-500 text-white rounded hover:bg-indigo-700 <?php echo ($page <= 1) ? 'opacity-50 cursor-not-allowed' : ''; ?>">
                            Previous
                        </a>

                        <!-- Page Numbers (Max 5 visible) -->
                        <?php
                        $max_visible_pages = 5;
                        $start_page = max(1, $page - floor($max_visible_pages / 2));
                        $end_page = min($total_pages, $start_page + $max_visible_pages - 1);

                        if ($end_page - $start_page + 1 < $max_visible_pages) {
                            $start_page = max(1, $end_page - $max_visible_pages + 1);
                        }

                        for ($i = $start_page; $i <= $end_page; $i++): ?>
                            <?php if ($i == $page): ?>
                                <span class="px-4 py-2 bg-indigo-700 text-white rounded"><?php echo $i; ?></span>
                            <?php else: ?>
                                <a href="?page=<?php echo $i; ?><?php echo build_query_params(['page']); ?>" class="px-4 py-2 bg-indigo-500 text-white rounded hover:bg-indigo-700"><?php echo $i; ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>

                        <!-- Next Page Button -->
                        <a href="?page=<?php echo min($page + 1, $total_pages); ?><?php echo build_query_params(['page']); ?>" class="px-4 py-2 bg-indigo-500 text-white rounded hover:bg-indigo-700 <?php echo ($page >= $total_pages) ? 'opacity-50 cursor-not-allowed' : ''; ?>">
                            Next
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <p class="text-gray-700">No cancelled bookings found.</p>
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

        // Toggle Manage Dropdown in Navbar
        const manageDropdown = document.querySelectorAll('.dropdown');
        manageDropdown.forEach(dropdown => {
            const button = dropdown.querySelector('button');
            const dropdownMenu = dropdown.querySelector('.dropdown-menu');

            button.addEventListener('click', (e) => {
                e.stopPropagation();
                dropdownMenu.classList.toggle('hidden');
            });

            // Close dropdown when clicking outside
            document.addEventListener('click', (e) => {
                if (!dropdown.contains(e.target)) {
                    dropdownMenu.classList.add('hidden');
                }
            });
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
<?php
$conn->close();
?>
