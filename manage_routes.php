<?php
// manage_routes.php
session_start();
require 'config.php';
require 'csrf_helper.php';

// Check if user is logged in and is an Admin
if (!isset($_SESSION['user_id']) || $_SESSION['role_name'] != 'Admin') {
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

// Handle Form Submissions
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Determine which form was submitted
    if (isset($_POST['add_route'])) {
        // Validate CSRF token
        if (!isset($_POST['csrf_token']) || !validateCsrfToken($_POST['csrf_token'])) {
            $errors[] = "Invalid CSRF token.";
        } else {
            // Handle Adding Route
            $origin_airport_id = intval($_POST['origin_airport']);
            $destination_airport_id = intval($_POST['destination_airport']);
            $distance = intval($_POST['distance']);

            // New Price Fields Extraction
            $price_seat_economy = floatval($_POST['price_seat_economy']);
            $price_seat_business = floatval($_POST['price_seat_business']);
            $price_seat_first_class = floatval($_POST['price_seat_first_class']);

            // Validate Inputs
            if ($origin_airport_id == $destination_airport_id) {
                $errors[] = "Origin and destination airports must be different.";
            } else {
                // Check if both airports exist only if the IDs are different
                $stmt_check = $conn->prepare("SELECT airport_id FROM airports WHERE airport_id IN (?, ?)");
                if ($stmt_check) {
                    $stmt_check->bind_param("ii", $origin_airport_id, $destination_airport_id);
                    $stmt_check->execute();
                    $result_check = $stmt_check->get_result();
                    if ($result_check->num_rows != 2) {
                        $errors[] = "One or both selected airports do not exist.";
                    }
                    $stmt_check->close();
                } else {
                    $errors[] = "Error preparing statement: " . $conn->error;
                }
            }           
            if ($distance <= 0) {
                $errors[] = "Distance must be a positive integer.";
            }
            // Validate Price Fields
            if ($price_seat_economy < 0) {
                $errors[] = "Price for Economy seats cannot be negative.";
            }
            if ($price_seat_business < 0) {
                $errors[] = "Price for Business seats cannot be negative.";
            }
            if ($price_seat_first_class < 0) {
                $errors[] = "Price for First Class seats cannot be negative.";
            }

            // Insert into Database if no errors
            if (empty($errors)) {
                $stmt = $conn->prepare("INSERT INTO routes (origin_airport_id, destination_airport_id, distance, price_seat_economy, price_seat_business, price_seat_first_class) VALUES (?, ?, ?, ?, ?, ?)");
                if ($stmt) {
                    $stmt->bind_param("iiiddd", $origin_airport_id, $destination_airport_id, $distance, $price_seat_economy, $price_seat_business, $price_seat_first_class);
                    if ($stmt->execute()) {
                        $success[] = "Route added successfully.";
                    } else {
                        $errors[] = "Error adding route: " . $stmt->error;
                    }
                    $stmt->close();
                } else {
                    $errors[] = "Error preparing statement: " . $conn->error;
                }
            }
        }
    } elseif (isset($_POST['delete_route'])) {
        // Handle Delete Route Form Submission
        // Validate CSRF token
        if (!isset($_POST['csrf_token']) || !validateCsrfToken($_POST['csrf_token'])) {
            $errors[] = "Invalid CSRF token.";
        } else {
            // Retrieve and sanitize route_id
            if (!isset($_POST['route_id'])) {
                $errors[] = "Route ID not provided.";
            } else {
                $route_id = intval($_POST['route_id']);
                
                // Delete the route
                $stmt = $conn->prepare("DELETE FROM routes WHERE route_id = ?");
                if ($stmt) {
                    $stmt->bind_param("i", $route_id);
                    if ($stmt->execute()) {
                        if ($stmt->affected_rows > 0) {
                            $success[] = "Route deleted successfully.";
                        } else {
                            $errors[] = "Route not found.";
                        }
                    } else {
                        // Check for foreign key constraint violation
                        if ($conn->errno == 1451) { // Cannot delete or update a parent row: a foreign key constraint fails
                            $errors[] = "Cannot delete route. It is associated with existing flights.";
                        } else {
                            $errors[] = "Error deleting route: " . $stmt->error;
                        }
                    }
                    $stmt->close();
                } else {
                    $errors[] = "Error preparing statement: " . $conn->error;
                }
            }
        }
    }

    // Store messages in session to persist after redirect
    $_SESSION['errors'] = $errors;
    $_SESSION['success'] = $success;

    // Redirect to the same page to prevent form resubmission
    header("Location: manage_routes.php");
    exit();
}

// Pagination Settings
$limit = 5; // Number of entries per page
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $limit;

// Fetch all airports for dropdowns
$airports = [];
$stmt = $conn->prepare("SELECT airport_id, name, iata_code FROM airports ORDER BY name ASC");
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

// Fetch all routes for display and search
$filtered_routes = [];

// Modify search parameters to accept text inputs
$search_route_origin = isset($_GET['search_route_origin']) ? trim($_GET['search_route_origin']) : '';
$search_route_destination = isset($_GET['search_route_destination']) ? trim($_GET['search_route_destination']) : '';

// Build query based on search criteria
$query = "
    SELECT 
        r.route_id, 
        origin.name AS origin_name, 
        origin.iata_code AS origin_iata, 
        destination.name AS destination_name, 
        destination.iata_code AS destination_iata, 
        r.distance,
        r.price_seat_economy,
        r.price_seat_business,
        r.price_seat_first_class
    FROM routes r
    JOIN airports origin ON r.origin_airport_id = origin.airport_id
    JOIN airports destination ON r.destination_airport_id = destination.airport_id
    WHERE 1=1
";
$params = [];
$types = "";

if (!empty($search_route_origin)) {
    $query .= " AND (origin.name LIKE ? OR origin.location LIKE ? OR origin.city LIKE ? OR origin.country LIKE ? OR origin.iata_code LIKE ?)";
    $search_param_origin = '%' . $search_route_origin . '%';
    $params = array_merge($params, [$search_param_origin, $search_param_origin, $search_param_origin, $search_param_origin, $search_param_origin]);
    $types .= "sssss";
}
if (!empty($search_route_destination)) {
    $query .= " AND (destination.name LIKE ? OR destination.location LIKE ? OR destination.city LIKE ? OR destination.country LIKE ? OR destination.iata_code LIKE ?)";
    $search_param_destination = '%' . $search_route_destination . '%';
    $params = array_merge($params, [$search_param_destination, $search_param_destination, $search_param_destination, $search_param_destination, $search_param_destination]);
    $types .= "sssss";
}

// Count total records for pagination
$count_query = "
    SELECT COUNT(*) as total
    FROM routes r
    JOIN airports origin ON r.origin_airport_id = origin.airport_id
    JOIN airports destination ON r.destination_airport_id = destination.airport_id
    WHERE 1=1
";
$count_params = [];
$count_types = "";

if (!empty($search_route_origin)) {
    $count_query .= " AND (origin.name LIKE ? OR origin.location LIKE ? OR origin.city LIKE ? OR origin.country LIKE ? OR origin.iata_code LIKE ?)";
    $search_param_origin = '%' . $search_route_origin . '%';
    $count_params = array_merge($count_params, [$search_param_origin, $search_param_origin, $search_param_origin, $search_param_origin, $search_param_origin]);
    $count_types .= "sssss";
}
if (!empty($search_route_destination)) {
    $count_query .= " AND (destination.name LIKE ? OR destination.location LIKE ? OR destination.city LIKE ? OR destination.country LIKE ? OR destination.iata_code LIKE ?)";
    $search_param_destination = '%' . $search_route_destination . '%';
    $count_params = array_merge($count_params, [$search_param_destination, $search_param_destination, $search_param_destination, $search_param_destination, $search_param_destination]);
    $count_types .= "sssss";
}

$stmt_count = $conn->prepare($count_query);
$total_records = 0;
if ($stmt_count) {
    if (!empty($count_params)) {
        $stmt_count->bind_param($count_types, ...$count_params);
    }
    $stmt_count->execute();
    $result_count = $stmt_count->get_result();
    if ($row_count = $result_count->fetch_assoc()) {
        $total_records = $row_count['total'];
    }
    $stmt_count->close();
} else {
    $errors[] = "Error preparing count statement: " . $conn->error;
}

// Calculate total pages
$total_pages = ceil($total_records / $limit);

// Append ORDER BY, LIMIT, and OFFSET for pagination
$query .= " ORDER BY origin.name ASC, destination.name ASC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$types .= "ii";

// Prepare and execute the main query
$stmt = $conn->prepare($query);
if ($stmt) {
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    while ($route = $result->fetch_assoc()) {
        $filtered_routes[] = $route;
    }
    $stmt->close();
} else {
    $errors[] = "Error fetching routes: " . $conn->error;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Routes - Flight Ticket Booking System</title>
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
                        <button class="flex items-center text-gray-700 hover:text-indigo-600 px-3 py-2 rounded-md text-sm font-medium focus:outline-none">
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
                    
                    <!-- Logout Form with CSRF Token -->
                    <form action="logout.php" method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
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
            <!-- Logout Form with CSRF Token for Mobile -->
            <form action="logout.php" method="POST" class="px-4 py-2">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
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

        <!-- Add Route Form -->
        <div class="bg-white shadow-md rounded-lg p-6 mb-8">
            <h2 class="text-2xl font-semibold mb-4 text-indigo-600">Add New Route</h2>
            <form method="POST" action="manage_routes.php" class="space-y-4">
                <!-- CSRF Token -->
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <input type="hidden" name="add_route" value="1">

                <div>
                    <label for="origin_airport" class="block text-sm font-medium text-gray-700">Origin Airport<span class="text-red-500">*</span></label>
                    <select id="origin_airport" name="origin_airport" required class="mt-1 block w-full px-4 py-2 border border-gray-300 bg-white rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="">--Select Origin Airport--</option>
                        <?php
                        foreach ($airports as $airport) {
                            // Retain selected value after submission
                            $selected = (isset($_POST['origin_airport']) && $_POST['origin_airport'] == $airport['airport_id']) ? "selected" : "";
                            echo "<option value='" . htmlspecialchars($airport['airport_id']) . "' $selected>" . htmlspecialchars($airport['name']) . " (" . htmlspecialchars($airport['iata_code']) . ")</option>";
                        }
                        ?>
                    </select>
                </div>

                <div>
                    <label for="destination_airport" class="block text-sm font-medium text-gray-700">Destination Airport<span class="text-red-500">*</span></label>
                    <select id="destination_airport" name="destination_airport" required class="mt-1 block w-full px-4 py-2 border border-gray-300 bg-white rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="">--Select Destination Airport--</option>
                        <?php
                        foreach ($airports as $airport) {
                            // Retain selected value after submission
                            $selected = (isset($_POST['destination_airport']) && $_POST['destination_airport'] == $airport['airport_id']) ? "selected" : "";
                            echo "<option value='" . htmlspecialchars($airport['airport_id']) . "' $selected>" . htmlspecialchars($airport['name']) . " (" . htmlspecialchars($airport['iata_code']) . ")</option>";
                        }
                        ?>
                    </select>
                </div>

                <div>
                    <label for="distance" class="block text-sm font-medium text-gray-700">Distance (km)<span class="text-red-500">*</span></label>
                    <input type="number" id="distance" name="distance" min="1" required value="<?php echo isset($_POST['distance']) ? htmlspecialchars($_POST['distance']) : ''; ?>" class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                </div>

                <!-- New Price Fields -->
                <div>
                    <label for="price_seat_economy" class="block text-sm font-medium text-gray-700">Price Seat Economy ($)<span class="text-red-500">*</span></label>
                    <input type="number" step="0.01" id="price_seat_economy" name="price_seat_economy" min="0" required value="<?php echo isset($_POST['price_seat_economy']) ? htmlspecialchars($_POST['price_seat_economy']) : ''; ?>" class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                </div>

                <div>
                    <label for="price_seat_business" class="block text-sm font-medium text-gray-700">Price Seat Business ($)<span class="text-red-500">*</span></label>
                    <input type="number" step="0.01" id="price_seat_business" name="price_seat_business" min="0" required value="<?php echo isset($_POST['price_seat_business']) ? htmlspecialchars($_POST['price_seat_business']) : ''; ?>" class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                </div>

                <div>
                    <label for="price_seat_first_class" class="block text-sm font-medium text-gray-700">Price Seat First Class ($)<span class="text-red-500">*</span></label>
                    <input type="number" step="0.01" id="price_seat_first_class" name="price_seat_first_class" min="0" required value="<?php echo isset($_POST['price_seat_first_class']) ? htmlspecialchars($_POST['price_seat_first_class']) : ''; ?>" class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                </div>
                <!-- End of New Price Fields -->

                <div>
                    <button type="submit" class="w-full px-4 py-2 bg-indigo-600 text-white font-medium rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500">Add Route</button>
                </div>
            </form>
        </div>

        <!-- Route Search Form -->
        <div class="bg-white shadow-md rounded-lg p-6 mb-8">
            <h2 class="text-2xl font-semibold mb-4 text-indigo-600">Search Routes</h2>
            <form method="GET" action="manage_routes.php" class="space-y-4">
                <div>
                    <label for="search_route_origin" class="block text-sm font-medium text-gray-700">Origin Airport</label>
                    <input type="text" id="search_route_origin" name="search_route_origin" value="<?php echo htmlspecialchars($search_route_origin); ?>" class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                </div>

                <div>
                    <label for="search_route_destination" class="block text-sm font-medium text-gray-700">Destination Airport</label>
                    <input type="text" id="search_route_destination" name="search_route_destination" value="<?php echo htmlspecialchars($search_route_destination); ?>" class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                </div>

                <div class="flex items-center space-x-4">
                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white font-medium rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500">Search Routes</button>
                    <a href="manage_routes.php" class="px-4 py-2 bg-gray-200 text-gray-700 font-medium rounded-md hover:bg-gray-300">Reset</a>
                </div>
            </form>
        </div>

        <!-- Existing Routes Table -->
        <div class="bg-white shadow-md rounded-lg p-6">
            <h2 class="text-2xl font-semibold mb-4 text-indigo-600">Existing Routes</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Route ID</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Origin</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Destination</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Distance (km)</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Price Economy ($)</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Price Business ($)</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Price First Class ($)</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Edit</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Delete</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php
                        if (!empty($filtered_routes)) {
                            foreach ($filtered_routes as $route) {
                                echo "<tr>";
                                echo "<td class='px-6 py-4 whitespace-nowrap'>" . htmlspecialchars($route['route_id']) . "</td>";
                                echo "<td class='px-6 py-4 whitespace-nowrap'>" . htmlspecialchars($route['origin_name']) . " (" . htmlspecialchars($route['origin_iata']) . ")</td>";
                                echo "<td class='px-6 py-4 whitespace-nowrap'>" . htmlspecialchars($route['destination_name']) . " (" . htmlspecialchars($route['destination_iata']) . ")</td>";
                                echo "<td class='px-6 py-4 whitespace-nowrap'>" . htmlspecialchars($route['distance']) . "</td>";
                                echo "<td class='px-6 py-4 whitespace-nowrap'>$" . htmlspecialchars(number_format($route['price_seat_economy'], 2)) . "</td>";
                                echo "<td class='px-6 py-4 whitespace-nowrap'>$" . htmlspecialchars(number_format($route['price_seat_business'], 2)) . "</td>";
                                echo "<td class='px-6 py-4 whitespace-nowrap'>$" . htmlspecialchars(number_format($route['price_seat_first_class'], 2)) . "</td>";
                                // Edit Button
                                echo "<td class='px-6 py-4 whitespace-nowrap'>";
                                echo "<a href='edit_route.php?route_id=" . urlencode($route['route_id']) . "' class='inline-flex items-center px-3 py-1 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700'>Edit</a>";
                                echo "</td>";
                                // Delete Form with CSRF Token
                                echo "<td class='px-6 py-4 whitespace-nowrap'>";
                                echo "<form method='POST' action='manage_routes.php' onsubmit=\"return confirm('Are you sure you want to delete this route?');\">";
                                echo "<input type='hidden' name='csrf_token' value='" . htmlspecialchars($csrf_token) . "'>";
                                echo "<input type='hidden' name='delete_route' value='1'>";
                                echo "<input type='hidden' name='route_id' value='" . htmlspecialchars($route['route_id']) . "'>";
                                echo "<button type='submit' class='inline-flex items-center px-3 py-1 bg-red-600 text-white text-sm font-medium rounded-md hover:bg-red-700'>Delete</button>";
                                echo "</form>";
                                echo "</td>";
                                echo "</tr>";
                            }
                        } else {
                            echo "<tr><td colspan='9' class='px-6 py-4 whitespace-nowrap text-center text-gray-500'>No routes found.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination Controls -->
            <?php if ($total_pages > 1): ?>
                <div class="mt-6 flex justify-center">
                    <nav class="inline-flex -space-x-px" aria-label="Pagination">
                        <!-- Previous Page Button -->
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page - 1; ?><?php echo ($search_route_origin > 0) ? '&search_route_origin=' . urlencode($search_route_origin) : ''; ?><?php echo ($search_route_destination > 0) ? '&search_route_destination=' . urlencode($search_route_destination) : ''; ?>" class="px-3 py-2 ml-0 leading-tight text-gray-500 bg-white border border-gray-300 rounded-l-lg hover:bg-gray-100 hover:text-gray-700">Previous</a>
                        <?php else: ?>
                            <span class="px-3 py-2 ml-0 leading-tight text-gray-400 bg-white border border-gray-300 rounded-l-lg">Previous</span>
                        <?php endif; ?>

                        <!-- Page Numbers -->
                        <?php
                        // Determine the range of pages to display
                        $max_visible_pages = 5;
                        $start_page = max(1, $page - floor($max_visible_pages / 2));
                        $end_page = min($total_pages, $start_page + $max_visible_pages - 1);

                        if ($end_page - $start_page + 1 < $max_visible_pages) {
                            $start_page = max(1, $end_page - $max_visible_pages + 1);
                        }

                        for ($i = $start_page; $i <= $end_page; $i++): ?>
                            <?php if ($i == $page): ?>
                                <span class="px-3 py-2 leading-tight text-white bg-indigo-600 border border-gray-300 hover:bg-indigo-700 hover:text-white"><?php echo $i; ?></span>
                            <?php else: ?>
                                <a href="?page=<?php echo $i; ?><?php echo ($search_route_origin > 0) ? '&search_route_origin=' . urlencode($search_route_origin) : ''; ?><?php echo ($search_route_destination > 0) ? '&search_route_destination=' . urlencode($search_route_destination) : ''; ?>" class="px-3 py-2 leading-tight text-gray-500 bg-white border border-gray-300 hover:bg-gray-100 hover:text-gray-700"><?php echo $i; ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>

                        <!-- Next Page Button -->
                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?php echo $page + 1; ?><?php echo ($search_route_origin > 0) ? '&search_route_origin=' . urlencode($search_route_origin) : ''; ?><?php echo ($search_route_destination > 0) ? '&search_route_destination=' . urlencode($search_route_destination) : ''; ?>" class="px-3 py-2 leading-tight text-gray-500 bg-white border border-gray-300 rounded-r-lg hover:bg-gray-100 hover:text-gray-700">Next</a>
                        <?php else: ?>
                            <span class="px-3 py-2 leading-tight text-gray-400 bg-white border border-gray-300 rounded-r-lg">Next</span>
                        <?php endif; ?>
                    </nav>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Footer -->
    <?php include 'footer.php'; ?>

    <!-- Tailwind CSS Mobile Menu Toggle and Dropdown Toggle Script -->
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
    </script>
</body>
</html>
<?php
$conn->close();
?>
