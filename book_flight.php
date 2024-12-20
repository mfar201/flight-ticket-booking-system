<?php 
// book_flight.php

session_start();
require 'config.php';
require 'csrf_helper.php'; // Include CSRF helper

// Redirect to login if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Generate CSRF token
$csrf_token = generateCsrfToken();

// Initialize variables for error and success messages
$errors = isset($_SESSION['errors']) ? $_SESSION['errors'] : [];
$success = isset($_SESSION['success']) ? $_SESSION['success'] : [];

// Unset session messages after fetching
unset($_SESSION['errors']);
unset($_SESSION['success']);

// Fetch all airlines for search filters
$airlines = [];
$stmt = $conn->prepare("SELECT airline_id, name, iata_code FROM airlines ORDER BY name ASC");
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $airlines[] = $row;
    }
    $stmt->close();
} else {
    $errors[] = "Error fetching airlines: " . htmlspecialchars($conn->error);
}

// Fetch all airports for search filters
$airports = [];
$stmt = $conn->prepare("SELECT airport_id, name, location, city, country FROM airports ORDER BY name ASC");
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $airports[] = $row;
    }
    $stmt->close();
} else {
    $errors[] = "Error fetching airports: " . htmlspecialchars($conn->error);
}

// Handle Search Functionality
$search_airline = isset($_GET['search_airline']) ? intval($_GET['search_airline']) : 0;
$search_origin_location = isset($_GET['search_origin_location']) ? trim($_GET['search_origin_location']) : '';
$search_origin_city = isset($_GET['search_origin_city']) ? trim($_GET['search_origin_city']) : '';
$search_origin_country = isset($_GET['search_origin_country']) ? trim($_GET['search_origin_country']) : '';
$search_destination_location = isset($_GET['search_destination_location']) ? trim($_GET['search_destination_location']) : '';
$search_destination_city = isset($_GET['search_destination_city']) ? trim($_GET['search_destination_city']) : '';
$search_destination_country = isset($_GET['search_destination_country']) ? trim($_GET['search_destination_country']) : '';

// Pagination Setup
$limit = 5; // Flights per page
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? intval($_GET['page']) : 1;
$page = max($page, 1); // Ensure page is at least 1
$offset = ($page - 1) * $limit;

// Build dynamic query based on search criteria
$query = "
    SELECT 
        f.flight_id,
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
        f.seat_economy,
        f.seat_business,
        f.seat_first_class,
        f.status
    FROM flights f
    JOIN airlines a ON f.airline_id = a.airline_id
    JOIN aircrafts ac ON f.aircraft_id = ac.aircraft_id
    JOIN routes r ON f.route_id = r.route_id
    JOIN airports origin ON r.origin_airport_id = origin.airport_id
    JOIN airports destination ON r.destination_airport_id = destination.airport_id
    WHERE f.status IN ('Scheduled', 'Delayed') 
";

// Apply search filters
$params = [];
$types = "";

if ($search_airline > 0) {
    $query .= " AND f.airline_id = ?";
    $params[] = $search_airline;
    $types .= "i";
}
if (!empty($search_origin_location)) {
    $query .= " AND origin.location LIKE ?";
    $params[] = "%" . $search_origin_location . "%";
    $types .= "s";
}
if (!empty($search_origin_city)) {
    $query .= " AND origin.city LIKE ?";
    $params[] = "%" . $search_origin_city . "%";
    $types .= "s";
}
if (!empty($search_origin_country)) {
    $query .= " AND origin.country LIKE ?";
    $params[] = "%" . $search_origin_country . "%";
    $types .= "s";
}
if (!empty($search_destination_location)) {
    $query .= " AND destination.location LIKE ?";
    $params[] = "%" . $search_destination_location . "%";
    $types .= "s";
}
if (!empty($search_destination_city)) {
    $query .= " AND destination.city LIKE ?";
    $params[] = "%" . $search_destination_city . "%";
    $types .= "s";
}
if (!empty($search_destination_country)) {
    $query .= " AND destination.country LIKE ?";
    $params[] = "%" . $search_destination_country . "%";
    $types .= "s";
}

// Get total number of matching flights for pagination
$count_query = "SELECT COUNT(*) AS total FROM flights f
    JOIN airlines a ON f.airline_id = a.airline_id
    JOIN aircrafts ac ON f.aircraft_id = ac.aircraft_id
    JOIN routes r ON f.route_id = r.route_id
    JOIN airports origin ON r.origin_airport_id = origin.airport_id
    JOIN airports destination ON r.destination_airport_id = destination.airport_id
    WHERE f.status IN ('Scheduled', 'Delayed') 
";

if ($search_airline > 0) {
    $count_query .= " AND f.airline_id = ?";
}
if (!empty($search_origin_location)) {
    $count_query .= " AND origin.location LIKE ?";
}
if (!empty($search_origin_city)) {
    $count_query .= " AND origin.city LIKE ?";
}
if (!empty($search_origin_country)) {
    $count_query .= " AND origin.country LIKE ?";
}
if (!empty($search_destination_location)) {
    $count_query .= " AND destination.location LIKE ?";
}
if (!empty($search_destination_city)) {
    $count_query .= " AND destination.city LIKE ?";
}
if (!empty($search_destination_country)) {
    $count_query .= " AND destination.country LIKE ?";
}

$count_stmt = $conn->prepare($count_query);
if ($count_stmt) {
    if (!empty($params)) {
        $count_stmt->bind_param($types, ...$params);
    }
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
    $total_flights = $count_result->fetch_assoc()['total'];
    $total_pages = ceil($total_flights / $limit);
    $count_stmt->close();
} else {
    $errors[] = "Error counting flights: " . htmlspecialchars($conn->error);
    $total_flights = 0;
    $total_pages = 1;
}

// Append ORDER BY and LIMIT clauses for pagination
$query .= " ORDER BY f.departure_datetime DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$types .= "i";
$params[] = $offset;
$types .= "i";

// Prepare and execute the main query
$stmt = $conn->prepare($query);
$flights = [];
if ($stmt) {
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $flights[] = $row;
    }
    $stmt->close();
} else {
    $errors[] = "Error fetching flights: " . $conn->error;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Flights - Flight Ticket Booking System</title>
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
                    <a href="welcome_user.php" class="text-xl font-bold text-indigo-600">Flight Ticket Booking System</a>
                </div>
                <!-- Navigation Links -->
                <div class="hidden md:flex space-x-4 items-center">
                    <a href="book_flight.php" class="text-gray-700 hover:text-indigo-600 px-3 py-2 rounded-md text-sm font-medium">Book Flights</a>
                    <a href="view_bookings.php" class="text-gray-700 hover:text-indigo-600 px-3 py-2 rounded-md text-sm font-medium">My Bookings</a>
                    <form action="logout.php" method="POST">
                        <!-- Add CSRF token for logout -->
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
                <!-- Add CSRF token for logout -->
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <button type="submit" class="w-full text-left text-gray-700 hover:bg-indigo-50">Logout</button>
            </form>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="flex-grow container mx-auto px-4 py-8">
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

        <!-- Search Form -->
        <div class="bg-white shadow-md rounded-lg p-6 mb-8">
            <h3 class="text-xl font-semibold mb-4 text-indigo-600">Search Flights</h3>
            <form method="GET" action="book_flight.php" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- Airline Selection -->
                    <div>
                        <label for="search_airline" class="block text-sm font-medium text-gray-700">Airline</label>
                        <select id="search_airline" name="search_airline" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                            <option value="">--Any Airline--</option>
                            <?php foreach ($airlines as $airline): ?>
                                <option value="<?php echo htmlspecialchars($airline['airline_id']); ?>" <?php echo ($search_airline == $airline['airline_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($airline['name']) . " (" . htmlspecialchars($airline['iata_code']) . ")"; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Origin Location -->
                    <div>
                        <label for="search_origin_location" class="block text-sm font-medium text-gray-700">Origin Location</label>
                        <input type="text" id="search_origin_location" name="search_origin_location" value="<?php echo htmlspecialchars($search_origin_location); ?>" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500" placeholder="e.g., JFK">
                    </div>

                    <!-- Origin City -->
                    <div>
                        <label for="search_origin_city" class="block text-sm font-medium text-gray-700">Origin City</label>
                        <input type="text" id="search_origin_city" name="search_origin_city" value="<?php echo htmlspecialchars($search_origin_city); ?>" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500" placeholder="e.g., New York">
                    </div>

                    <!-- Origin Country -->
                    <div>
                        <label for="search_origin_country" class="block text-sm font-medium text-gray-700">Origin Country</label>
                        <input type="text" id="search_origin_country" name="search_origin_country" value="<?php echo htmlspecialchars($search_origin_country); ?>" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500" placeholder="e.g., USA">
                    </div>

                    <!-- Destination Location -->
                    <div>
                        <label for="search_destination_location" class="block text-sm font-medium text-gray-700">Destination Location</label>
                        <input type="text" id="search_destination_location" name="search_destination_location" value="<?php echo htmlspecialchars($search_destination_location); ?>" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500" placeholder="e.g., LAX">
                    </div>

                    <!-- Destination City -->
                    <div>
                        <label for="search_destination_city" class="block text-sm font-medium text-gray-700">Destination City</label>
                        <input type="text" id="search_destination_city" name="search_destination_city" value="<?php echo htmlspecialchars($search_destination_city); ?>" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500" placeholder="e.g., Los Angeles">
                    </div>

                    <!-- Destination Country -->
                    <div>
                        <label for="search_destination_country" class="block text-sm font-medium text-gray-700">Destination Country</label>
                        <input type="text" id="search_destination_country" name="search_destination_country" value="<?php echo htmlspecialchars($search_destination_country); ?>" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500" placeholder="e.g., USA">
                    </div>
                </div>

                <div class="flex items-center space-x-4">
                    <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700 transition duration-200">Search Flights</button>
                    <a href="book_flight.php" class="text-indigo-600 hover:text-indigo-500">Reset</a>
                </div>
            </form>
        </div>

        <!-- Flights Table -->
        <div class="bg-white shadow-md rounded-lg p-6">
            <h3 class="text-xl font-semibold mb-4 text-indigo-600">Available Flights</h3>
            <?php if (!empty($flights)): ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full bg-white">
                        <thead>
                            <tr>
                                <th class="px-4 py-2 border-b border-gray-300 text-left text-sm font-medium text-gray-700">Flight #</th>
                                <th class="px-4 py-2 border-b border-gray-300 text-left text-sm font-medium text-gray-700">Departure</th>
                                <th class="px-4 py-2 border-b border-gray-300 text-left text-sm font-medium text-gray-700">Arrival</th>
                                <th class="px-4 py-2 border-b border-gray-300 text-left text-sm font-medium text-gray-700">Airline</th>
                                <th class="px-4 py-2 border-b border-gray-300 text-left text-sm font-medium text-gray-700">Aircraft</th>
                                <th class="px-4 py-2 border-b border-gray-300 text-left text-sm font-medium text-gray-700">Route ID</th>
                                <th class="px-4 py-2 border-b border-gray-300 text-left text-sm font-medium text-gray-700">Origin</th>
                                <th class="px-4 py-2 border-b border-gray-300 text-left text-sm font-medium text-gray-700">Destination</th>
                                <th class="px-4 py-2 border-b border-gray-300 text-center text-sm font-medium text-gray-700">Seats</th>
                                <th class="px-4 py-2 border-b border-gray-300 text-left text-sm font-medium text-gray-700">Status</th>
                                <th class="px-4 py-2 border-b border-gray-300 text-center text-sm font-medium text-gray-700">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($flights as $flight): ?>
                                <tr class="hover:bg-gray-100">
                                    <td class="px-4 py-2 border-b border-gray-200"><?php echo htmlspecialchars($flight['flight_num']); ?></td>
                                    <td class="px-4 py-2 border-b border-gray-200"><?php echo htmlspecialchars(date('M d, Y H:i', strtotime($flight['departure_datetime']))); ?></td>
                                    <td class="px-4 py-2 border-b border-gray-200"><?php echo htmlspecialchars(date('M d, Y H:i', strtotime($flight['arrival_datetime']))); ?></td>
                                    <td class="px-4 py-2 border-b border-gray-200"><?php echo htmlspecialchars($flight['airline_name']); ?></td>
                                    <td class="px-4 py-2 border-b border-gray-200"><?php echo htmlspecialchars($flight['aircraft_model']); ?></td>
                                    <td class="px-4 py-2 border-b border-gray-200"><?php echo htmlspecialchars($flight['route_id']); ?></td>
                                    <td class="px-4 py-2 border-b border-gray-200">
                                        <?php 
                                            echo htmlspecialchars($flight['origin_location']) . ', ' . htmlspecialchars($flight['origin_city']) . ', ' . htmlspecialchars($flight['origin_country']); 
                                        ?>
                                    </td>
                                    <td class="px-4 py-2 border-b border-gray-200">
                                        <?php 
                                            echo htmlspecialchars($flight['destination_location']) . ', ' . htmlspecialchars($flight['destination_city']) . ', ' . htmlspecialchars($flight['destination_country']); 
                                        ?>
                                    </td>
                                    <td class="px-4 py-2 border-b border-gray-200 text-center">
                                        <div class="flex justify-center space-x-2">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                <i class="fas fa-chair mr-1"></i> Eco: <?php echo htmlspecialchars($flight['seat_economy']); ?>
                                            </span>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                <i class="fas fa-chair mr-1"></i> Bus: <?php echo htmlspecialchars($flight['seat_business']); ?>
                                            </span>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                                <i class="fas fa-chair mr-1"></i> First: <?php echo htmlspecialchars($flight['seat_first_class']); ?>
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-2 border-b border-gray-200"><?php echo htmlspecialchars($flight['status']); ?></td>
                                    <td class="px-4 py-2 border-b border-gray-200 text-center">
                                        <a href="booking_form.php?flight_id=<?php echo urlencode($flight['flight_id']); ?>" class="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                            Book Ticket(s)
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination Controls -->
                <?php if ($total_pages > 1): ?>
                    <div class="mt-6 flex justify-center">
                        <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                            <!-- Previous Button -->
                            <a href="?<?php 
                                // Ensure page doesn't go below 1
                                $prev_page = max(1, $page - 1);
                                echo http_build_query(array_merge($_GET, ['page' => $prev_page]));
                            ?>" class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                <span class="sr-only">Previous</span>
                                <i class="fas fa-chevron-left"></i>
                            </a>

                            <!-- Page Numbers -->
                            <?php 
                                // Determine the range of pages to display
                                $start = max(1, $page - 2);
                                $end = min($total_pages, $page + 2);
                                for ($i = $start; $i <= $end; $i++): 
                            ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" class="<?php echo ($i == $page) ? 'z-10 bg-indigo-50 border-indigo-500 text-indigo-600' : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50'; ?> relative inline-flex items-center px-4 py-2 border text-sm font-medium">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>

                            <!-- Next Button -->
                            <a href="?<?php 
                                // Ensure page doesn't exceed total_pages
                                $next_page = min($total_pages, $page + 1);
                                echo http_build_query(array_merge($_GET, ['page' => $next_page]));
                            ?>" class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                <span class="sr-only">Next</span>
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        </nav>
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <p class="text-gray-700">No flights found.</p>
            <?php endif; ?>
        </div>
   </main>

    <!-- Include Footer -->
    <?php include 'footer.php'; ?>

    <!-- Tailwind CSS Mobile Menu Toggle Script -->
    <script>
        const btn = document.getElementById('mobile-menu-button');
        const menu = document.getElementById('mobile-menu');

        btn.addEventListener('click', () => {
            menu.classList.toggle('hidden');
        });
    </script>

</body>
</html>
