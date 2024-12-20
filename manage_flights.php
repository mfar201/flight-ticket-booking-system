<?php
// manage_flights.php
session_start();
require 'config.php';
require 'csrf_helper.php'; // Include CSRF helper

// Check if user is logged in and is an Admin
if (!isset($_SESSION['user_id']) || $_SESSION['role_name'] != 'Admin') {
    header("Location: login.php");
    exit();
}

// CSRF Protection: Generate CSRF token if not set
$csrf_token = generateCsrfToken();

// Initialize variables for error and success messages
$errors = isset($_SESSION['errors']) ? $_SESSION['errors'] : [];
$success = isset($_SESSION['success']) ? $_SESSION['success'] : [];

// Unset session messages after fetching
unset($_SESSION['errors']);
unset($_SESSION['success']);

// Handle Form Submissions
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['add_flight'])) {
        // Validate CSRF token
        if (!isset($_POST['csrf_token']) || !validateCsrfToken($_POST['csrf_token'])) {
            $errors[] = "Invalid CSRF token.";
        } else {
            // Handle Adding Flight
            $flight_num = strtoupper(trim($_POST['flight_num']));
            $departure_datetime = trim($_POST['departure_datetime']);
            $arrival_datetime = trim($_POST['arrival_datetime']);
            $airline_id = intval($_POST['airline']);
            $aircraft_id = intval($_POST['aircraft']);
            $route_id = intval($_POST['route']);
            $status = trim($_POST['status']);

            // Validate Inputs
            if (empty($flight_num)) {
                $errors[] = "Flight number is required.";
            }
            if (empty($departure_datetime)) {
                $errors[] = "Departure date and time are required.";
            }
            if (empty($arrival_datetime)) {
                $errors[] = "Arrival date and time are required.";
            }
            if (strtotime($departure_datetime) === false) {
                $errors[] = "Invalid departure date and time format.";
            }
            if (strtotime($arrival_datetime) === false) {
                $errors[] = "Invalid arrival date and time format.";
            }
            $current_time = time();
            if (strtotime($departure_datetime) < $current_time) {
                $errors[] = "Departure time must not be in the past.";
            }
            if (strtotime($arrival_datetime) <= strtotime($departure_datetime)) {
                $errors[] = "Arrival time must be after departure time.";
            }
            if (strtotime($arrival_datetime) < $current_time) {
                $errors[] = "Arrival time must not be in the past.";
            }
            if (empty($status) || !in_array($status, ['Scheduled', 'Delayed', 'Cancelled', 'Completed'])) {
                $errors[] = "Invalid flight status.";
            }

            // Check if airline, aircraft, and route exist
            // Also, ensure that the aircraft belongs to the selected airline
            $stmt_check = $conn->prepare("SELECT aircraft_id, seat_economy, seat_business, seat_first_class FROM aircrafts WHERE aircraft_id = ? AND airline_id = ?");
            if ($stmt_check) {
                $stmt_check->bind_param("ii", $aircraft_id, $airline_id);
                $stmt_check->execute();
                $result_check = $stmt_check->get_result();
                if ($result_check->num_rows != 1) {
                    $errors[] = "Selected aircraft does not exist or does not belong to the selected airline.";
                } else {
                    $aircraft = $result_check->fetch_assoc();
                }
                $stmt_check->close();
            } else {
                $errors[] = "Error preparing statement: " . $conn->error;
            }

            // Check if route exists
            $stmt_check = $conn->prepare("SELECT route_id FROM routes WHERE route_id = ?");
            if ($stmt_check) {
                $stmt_check->bind_param("i", $route_id);
                $stmt_check->execute();
                $result_check = $stmt_check->get_result();
                if ($result_check->num_rows != 1) {
                    $errors[] = "Selected route does not exist.";
                }
                $stmt_check->close();
            } else {
                $errors[] = "Error preparing statement: " . $conn->error;
            }

            // Check if flight number is unique
            $stmt_check = $conn->prepare("SELECT flight_id FROM flights WHERE flight_num = ?");
            if ($stmt_check) {
                $stmt_check->bind_param("s", $flight_num);
                $stmt_check->execute();
                $result_check = $stmt_check->get_result();
                if ($result_check->num_rows > 0) {
                    $errors[] = "Flight number already exists.";
                }
                $stmt_check->close();
            } else {
                $errors[] = "Error preparing statement: " . $conn->error;
            }

            // Insert into Database if no errors
            if (empty($errors)) {
                $stmt = $conn->prepare("INSERT INTO flights (flight_num, departure_datetime, arrival_datetime, airline_id, aircraft_id, route_id, status, seat_economy, seat_business, seat_first_class) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                if ($stmt) {
                    $stmt->bind_param(
                        "sssiiisiii",
                        $flight_num,
                        $departure_datetime,
                        $arrival_datetime,
                        $airline_id,
                        $aircraft_id,
                        $route_id,
                        $status,
                        $aircraft['seat_economy'],
                        $aircraft['seat_business'],
                        $aircraft['seat_first_class']
                    );
                    if ($stmt->execute()) {
                        $success[] = "Flight added successfully.";
                    } else {
                        if ($conn->errno == 1062) { // Duplicate entry
                            $errors[] = "Flight number already exists.";
                        } else {
                            $errors[] = "Error adding flight: " . $stmt->error;
                        }
                    }
                    $stmt->close();
                } else {
                    $errors[] = "Error preparing statement: " . $conn->error;
                }
            }
        }
    }
    // Handle Delete Flight
    elseif (isset($_POST['delete_flight'])) {
        // Validate CSRF token
        if (!isset($_POST['csrf_token']) || !validateCsrfToken($_POST['csrf_token'])) {
            $errors[] = "Invalid CSRF token.";
        } else {
            // Handle Deleting Flight
            $flight_id = intval($_POST['flight_id']);

            // Delete the flight
            $stmt = $conn->prepare("DELETE FROM flights WHERE flight_id = ?");
            if ($stmt) {
                $stmt->bind_param("i", $flight_id);
                if ($stmt->execute()) {
                    if ($stmt->affected_rows > 0) {
                        $success[] = "Flight deleted successfully.";
                    } else {
                        $errors[] = "Flight not found.";
                    }
                } else {
                    // Check for foreign key constraint violation
                    if ($conn->errno == 1451) { // Cannot delete or update a parent row: a foreign key constraint fails
                        $errors[] = "Cannot delete flight. It is associated with existing tickets or bookings.";
                    } else {
                        $errors[] = "Error deleting flight: " . $stmt->error;
                    }
                }
                $stmt->close();
            } else {
                $errors[] = "Error preparing statement: " . $conn->error;
            }
        }
    }

    // Store messages in session to persist after redirect
    $_SESSION['errors'] = $errors;
    $_SESSION['success'] = $success;

    // Redirect to the same page to prevent form resubmission
    header("Location: manage_flights.php");
    exit();
}

// Fetch all airlines for dropdowns
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

// Fetch all aircrafts for dropdowns
$aircrafts = [];
$stmt = $conn->prepare("SELECT aircraft_id, model, seat_economy, seat_business, seat_first_class, airline_id FROM aircrafts ORDER BY model ASC");
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $aircrafts[] = $row;
    }
    $stmt->close();
} else {
    $errors[] = "Error fetching aircrafts: " . htmlspecialchars($conn->error);
}

// Fetch all routes for dropdowns
$routes = [];
$stmt = $conn->prepare("
    SELECT 
        r.route_id, 
        origin.name AS origin_name, 
        origin.iata_code AS origin_iata, 
        origin.location AS origin_location,
        origin.city AS origin_city,
        origin.country AS origin_country,
        destination.name AS destination_name, 
        destination.iata_code AS destination_iata,
        destination.location AS destination_location,
        destination.city AS destination_city,
        destination.country AS destination_country,
        r.distance,
        r.price_seat_economy,
        r.price_seat_business,
        r.price_seat_first_class
    FROM routes r
    JOIN airports origin ON r.origin_airport_id = origin.airport_id
    JOIN airports destination ON r.destination_airport_id = destination.airport_id
    ORDER BY origin.name ASC, destination.name ASC
");
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $routes[] = $row;
    }
    $stmt->close();
} else {
    $errors[] = "Error fetching routes: " . htmlspecialchars($conn->error);
}

// Fetch all airports for search filters (origin and destination)
$airports = [];
$stmt = $conn->prepare("SELECT airport_id, name, iata_code, location, city, country FROM airports ORDER BY name ASC");
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

// Handle Search Functionalities
$search_airline = isset($_GET['search_airline']) ? intval($_GET['search_airline']) : 0;
$search_origin_location = isset($_GET['search_origin_location']) ? trim($_GET['search_origin_location']) : '';
$search_origin_city = isset($_GET['search_origin_city']) ? trim($_GET['search_origin_city']) : '';
$search_origin_country = isset($_GET['search_origin_country']) ? trim($_GET['search_origin_country']) : '';
$search_destination_location = isset($_GET['search_destination_location']) ? trim($_GET['search_destination_location']) : '';
$search_destination_city = isset($_GET['search_destination_city']) ? trim($_GET['search_destination_city']) : '';
$search_destination_country = isset($_GET['search_destination_country']) ? trim($_GET['search_destination_country']) : '';
$search_status = isset($_GET['status']) && is_array($_GET['status']) ? $_GET['status'] : [];

// Pagination Parameters
$limit = 10; // Number of records per page
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? intval($_GET['page']) : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// Build dynamic WHERE clause based on search criteria
$where = "WHERE 1=1";
$params = [];
$types = "";

// Apply search filters
if ($search_airline > 0) {
    $where .= " AND f.airline_id = ?";
    $params[] = $search_airline;
    $types .= "i";
}
if (!empty($search_origin_location)) {
    $where .= " AND origin.location LIKE ?";
    $params[] = "%" . $search_origin_location . "%";
    $types .= "s";
}
if (!empty($search_origin_city)) {
    $where .= " AND origin.city LIKE ?";
    $params[] = "%" . $search_origin_city . "%";
    $types .= "s";
}
if (!empty($search_origin_country)) {
    $where .= " AND origin.country LIKE ?";
    $params[] = "%" . $search_origin_country . "%";
    $types .= "s";
}
if (!empty($search_destination_location)) {
    $where .= " AND destination.location LIKE ?";
    $params[] = "%" . $search_destination_location . "%";
    $types .= "s";
}
if (!empty($search_destination_city)) {
    $where .= " AND destination.city LIKE ?";
    $params[] = "%" . $search_destination_city . "%";
    $types .= "s";
}
if (!empty($search_destination_country)) {
    $where .= " AND destination.country LIKE ?";
    $params[] = "%" . $search_destination_country . "%";
    $types .= "s";
}
if (!empty($search_status)) {
    // Dynamically create placeholders for the IN clause
    $placeholders = implode(',', array_fill(0, count($search_status), '?'));
    $where .= " AND f.status IN ($placeholders)";
    foreach ($search_status as $status) {
        $params[] = $status;
        $types .= "s";
    }
}

// First, get the total number of records for pagination
$count_query = "
    SELECT COUNT(*) as total
    FROM flights f
    JOIN airlines a ON f.airline_id = a.airline_id
    JOIN aircrafts ac ON f.aircraft_id = ac.aircraft_id
    JOIN routes r ON f.route_id = r.route_id
    JOIN airports origin ON r.origin_airport_id = origin.airport_id
    JOIN airports destination ON r.destination_airport_id = destination.airport_id
    $where
";
$stmt_count = $conn->prepare($count_query);
if ($stmt_count) {
    if (!empty($params)) {
        $stmt_count->bind_param($types, ...$params);
    }
    $stmt_count->execute();
    $result_count = $stmt_count->get_result();
    $total_records = intval($result_count->fetch_assoc()['total']);
    $stmt_count->close();
} else {
    $errors[] = "Error counting flights: " . $conn->error;
    $total_records = 0;
}

// Calculate total pages
$total_pages = ceil($total_records / $limit);
if ($page > $total_pages && $total_pages > 0) {
    $page = $total_pages;
    $offset = ($page - 1) * $limit;
}

// Now, fetch the required records with LIMIT and OFFSET
$query = "
    SELECT 
        f.flight_id,
        f.flight_num,
        f.departure_datetime,
        f.arrival_datetime,
        a.name AS airline_name,
        ac.model AS aircraft_model,
        f.seat_economy,
        f.seat_business,
        f.seat_first_class,
        r.route_id,
        origin.name AS origin_name,
        origin.iata_code AS origin_iata,
        origin.location AS origin_location,
        origin.city AS origin_city,
        origin.country AS origin_country,
        destination.name AS destination_name,
        destination.iata_code AS destination_iata,
        destination.location AS destination_location,
        destination.city AS destination_city,
        destination.country AS destination_country,
        f.status
    FROM flights f
    JOIN airlines a ON f.airline_id = a.airline_id
    JOIN aircrafts ac ON f.aircraft_id = ac.aircraft_id
    JOIN routes r ON f.route_id = r.route_id
    JOIN airports origin ON r.origin_airport_id = origin.airport_id
    JOIN airports destination ON r.destination_airport_id = destination.airport_id
    $where
    ORDER BY f.departure_datetime DESC
    LIMIT ? OFFSET ?
";

$stmt = $conn->prepare($query);
$filtered_flights = [];

if ($stmt) {
    // Bind parameters
    if (!empty($params)) {
        // Add types for LIMIT and OFFSET
        $types_with_limit = $types . "ii";
        $params_with_limit = array_merge($params, [$limit, $offset]);
        $stmt->bind_param($types_with_limit, ...$params_with_limit);
    } else {
        // Only LIMIT and OFFSET
        $stmt->bind_param("ii", $limit, $offset);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    while ($flight = $result->fetch_assoc()) {
        $filtered_flights[] = $flight;
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
    <title>Manage Flights - Flight Ticket Booking System</title>
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
                    
                    <form action="logout.php" method="POST" class="inline">
                        <!-- CSRF Token -->
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
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
            <form action="logout.php" method="POST" class="px-4 py-2 inline">
                <!-- CSRF Token -->
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

        <!-- Add Flight Form -->
        <div class="bg-white shadow-md rounded-lg p-6 mb-8">
            <h2 class="text-2xl font-semibold mb-4 text-indigo-600">Add New Flight</h2>
            <form method="POST" action="manage_flights.php" class="space-y-4">
                <!-- CSRF Token -->
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <input type="hidden" name="add_flight" value="1">

                <div>
                    <label for="flight_num" class="block text-sm font-medium text-gray-700">Flight Number<span class="text-red-500">*</span></label>
                    <input type="text" id="flight_num" name="flight_num" required value="<?php echo isset($_POST['flight_num']) ? htmlspecialchars($_POST['flight_num']) : ''; ?>" class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 uppercase">
                </div>

                <div>
                    <label for="departure_datetime" class="block text-sm font-medium text-gray-700">Departure Date & Time<span class="text-red-500">*</span></label>
                    <input 
                        type="datetime-local" 
                        id="departure_datetime" 
                        name="departure_datetime" 
                        required 
                        value="<?php echo isset($_POST['departure_datetime']) ? htmlspecialchars($_POST['departure_datetime']) : ''; ?>" 
                        class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500" 
                        min="<?php echo date('Y-m-d\TH:i'); ?>" 
                        oninput="validateDateTime()">
                </div>

                <div>
                    <label for="arrival_datetime" class="block text-sm font-medium text-gray-700">Arrival Date & Time<span class="text-red-500">*</span></label>
                    <input 
                        type="datetime-local" 
                        id="arrival_datetime" 
                        name="arrival_datetime" 
                        required 
                        value="<?php echo isset($_POST['arrival_datetime']) ? htmlspecialchars($_POST['arrival_datetime']) : ''; ?>" 
                        class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500" 
                        min="<?php echo date('Y-m-d\TH:i'); ?>" 
                        oninput="validateArrivalDateTime()">

                </div>

                <div>
                    <label for="airline" class="block text-sm font-medium text-gray-700">Airline<span class="text-red-500">*</span></label>
                    <select id="airline" name="airline" required class="mt-1 block w-full px-4 py-2 border border-gray-300 bg-white rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="">--Select Airline--</option>
                        <?php
                        foreach ($airlines as $airline) {
                            // Preserve selected airline after form submission
                            $selected = (isset($_POST['airline']) && $_POST['airline'] == $airline['airline_id']) ? "selected" : "";
                            echo "<option value='" . htmlspecialchars($airline['airline_id']) . "' $selected>" . htmlspecialchars($airline['name']) . " (" . htmlspecialchars($airline['iata_code']) . ")</option>";
                        }
                        ?>
                    </select>
                </div>

                <div>
                    <label for="aircraft" class="block text-sm font-medium text-gray-700">Aircraft<span class="text-red-500">*</span></label>
                    <select id="aircraft" name="aircraft" required class="mt-1 block w-full px-4 py-2 border border-gray-300 bg-white rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="">--Select Aircraft--</option>
                        <?php
                        if (isset($_POST['airline']) && intval($_POST['airline']) > 0) {
                            foreach ($aircrafts as $aircraft) {
                                if ($aircraft['airline_id'] == intval($_POST['airline'])) {
                                    $selected = (isset($_POST['aircraft']) && $_POST['aircraft'] == $aircraft['aircraft_id']) ? "selected" : "";
                                    echo "<option value='" . htmlspecialchars($aircraft['aircraft_id']) . "' $selected>" . htmlspecialchars($aircraft['model']) . " (Economy: " . htmlspecialchars($aircraft['seat_economy']) . ", Business: " . htmlspecialchars($aircraft['seat_business']) . ", First Class: " . htmlspecialchars($aircraft['seat_first_class']) . ")</option>";
                                }
                            }
                        }
                        ?>
                    </select>
                </div>

                <div>
                    <label for="route" class="block text-sm font-medium text-gray-700">Route<span class="text-red-500">*</span></label>
                    <select id="route" name="route" required class="mt-1 block w-full px-4 py-2 border border-gray-300 bg-white rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="">--Select Route--</option>
                        <?php
                        foreach ($routes as $route) {
                            // Preserve selected route after form submission
                            $selected = (isset($_POST['route']) && $_POST['route'] == $route['route_id']) ? "selected" : "";
                            echo "<option value='" . htmlspecialchars($route['route_id']) . "' $selected>" . htmlspecialchars($route['origin_name']) . " (" . htmlspecialchars($route['origin_iata']) . ") &rarr; " . htmlspecialchars($route['destination_name']) . " (" . htmlspecialchars($route['destination_iata']) . ")</option>";
                        }
                        ?>
                    </select>
                </div>

                <div>
                    <label for="status" class="block text-sm font-medium text-gray-700">Status<span class="text-red-500">*</span></label>
                    <select id="status" name="status" required class="mt-1 block w-full px-4 py-2 border border-gray-300 bg-white rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="">--Select Status--</option>
                        <option value="Scheduled" <?php echo (isset($_POST['status']) && $_POST['status'] == 'Scheduled') ? 'selected' : ''; ?>>Scheduled</option>
                        <option value="Delayed" <?php echo (isset($_POST['status']) && $_POST['status'] == 'Delayed') ? 'selected' : ''; ?>>Delayed</option>
                        <option value="Cancelled" <?php echo (isset($_POST['status']) && $_POST['status'] == 'Cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                        <option value="Completed" <?php echo (isset($_POST['status']) && $_POST['status'] == 'Completed') ? 'selected' : ''; ?>>Completed</option>
                    </select>
                </div>

                <div>
                    <button type="submit" class="w-full px-4 py-2 bg-indigo-600 text-white font-medium rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500">Add Flight</button>
                </div>
            </form>
        </div>

        <!-- Flight Search Form -->
        <div class="bg-white shadow-md rounded-lg p-6 mb-8">
            <h2 class="text-2xl font-semibold mb-4 text-indigo-600">Search Flights</h2>
            <form method="GET" action="manage_flights.php" class="space-y-4">
                <div>
                    <label for="search_airline" class="block text-sm font-medium text-gray-700">Airline:</label>
                    <select id="search_airline" name="search_airline" class="mt-1 block w-full px-4 py-2 border border-gray-300 bg-white rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="">--Any Airline--</option>
                        <?php
                        foreach ($airlines as $airline) {
                            $selected = ($search_airline == $airline['airline_id']) ? "selected" : "";
                            echo "<option value='" . htmlspecialchars($airline['airline_id']) . "' $selected>" . htmlspecialchars($airline['name']) . " (" . htmlspecialchars($airline['iata_code']) . ")</option>";
                        }
                        ?>
                    </select>
                </div>

                <div>
                    <label for="search_status" class="block text-sm font-medium text-gray-700">Flight Status:</label>
                    <select id="search_status" name="status[]" multiple class="mt-1 block w-full px-4 py-2 border border-gray-300 bg-white rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="Scheduled" <?php echo in_array('Scheduled', $search_status) ? 'selected' : ''; ?>>Scheduled</option>
                        <option value="Delayed" <?php echo in_array('Delayed', $search_status) ? 'selected' : ''; ?>>Delayed</option>
                        <option value="Cancelled" <?php echo in_array('Cancelled', $search_status) ? 'selected' : ''; ?>>Cancelled</option>
                        <option value="Completed" <?php echo in_array('Completed', $search_status) ? 'selected' : ''; ?>>Completed</option>
                    </select>
                    <p class="text-xs text-gray-500 mt-1">Hold down the Ctrl (windows) or Command (Mac) button to select multiple options.</p>
                </div>

                <h3 class="text-lg font-medium text-gray-700">Origin Filters</h3>
                <div>
                    <label for="search_origin_location" class="block text-sm font-medium text-gray-700">Origin Location:</label>
                    <input type="text" id="search_origin_location" name="search_origin_location" value="<?php echo htmlspecialchars($search_origin_location); ?>" class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                </div>

                <div>
                    <label for="search_origin_city" class="block text-sm font-medium text-gray-700">Origin City:</label>
                    <input type="text" id="search_origin_city" name="search_origin_city" value="<?php echo htmlspecialchars($search_origin_city); ?>" class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                </div>

                <div>
                    <label for="search_origin_country" class="block text-sm font-medium text-gray-700">Origin Country:</label>
                    <input type="text" id="search_origin_country" name="search_origin_country" value="<?php echo htmlspecialchars($search_origin_country); ?>" class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                </div>

                <h3 class="text-lg font-medium text-gray-700">Destination Filters</h3>
                <div>
                    <label for="search_destination_location" class="block text-sm font-medium text-gray-700">Destination Location:</label>
                    <input type="text" id="search_destination_location" name="search_destination_location" value="<?php echo htmlspecialchars($search_destination_location); ?>" class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                </div>

                <div>
                    <label for="search_destination_city" class="block text-sm font-medium text-gray-700">Destination City:</label>
                    <input type="text" id="search_destination_city" name="search_destination_city" value="<?php echo htmlspecialchars($search_destination_city); ?>" class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                </div>

                <div>
                    <label for="search_destination_country" class="block text-sm font-medium text-gray-700">Destination Country:</label>
                    <input type="text" id="search_destination_country" name="search_destination_country" value="<?php echo htmlspecialchars($search_destination_country); ?>" class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                </div>

                <div class="flex items-center space-x-4">
                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white font-medium rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500">Search Flights</button>
                    <a href="manage_flights.php" class="px-4 py-2 bg-gray-200 text-gray-700 font-medium rounded-md hover:bg-gray-300">Reset</a>
                </div>
            </form>
        </div>

        <!-- Existing Flights Table -->
        <div class="bg-white shadow-md rounded-lg p-6">
            <h2 class="text-2xl font-semibold mb-4 text-indigo-600">Existing Flights</h2>
            <div class="overflow-x-auto">
                <!-- Flights Table with Expandable Rows -->
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <!-- Removed Flight ID and Arrival Columns -->
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Flight Number</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Departure</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Airline</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aircraft</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Route</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Seats Economy</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Seats Business</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Seats First Class</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (!empty($filtered_flights)): ?>
                            <?php foreach ($filtered_flights as $flight): ?>
                                <!-- Main Row -->
                                <tr class="border-b border-gray-200 hover:bg-gray-100 cursor-pointer main-row" tabindex="0" aria-expanded="false">
                                    <!-- Removed Flight ID and Arrival Cells -->
                                    <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($flight['flight_num']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($flight['departure_datetime']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($flight['airline_name']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($flight['aircraft_model']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php 
                                            // Display Route in two lines without the arrow
                                            echo htmlspecialchars($flight['origin_name']) . " (" . htmlspecialchars($flight['origin_iata']) . ")<br>" . htmlspecialchars($flight['destination_name']) . " (" . htmlspecialchars($flight['destination_iata']) . ")";
                                        ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($flight['seat_economy']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($flight['seat_business']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($flight['seat_first_class']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($flight['status']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-center">
                                        <a href="edit_flight.php?flight_id=<?php echo urlencode($flight['flight_id']); ?>" class="inline-flex items-center px-3 py-1 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700">Edit</a>
                                        
                                        <!-- Delete Flight Form -->
                                        <form action="manage_flights.php" method="POST" class="inline-block" onsubmit="return confirm('Are you sure you want to delete this flight?');">
                                            <!-- CSRF Token -->
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                            <input type="hidden" name="delete_flight" value="1">
                                            <input type="hidden" name="flight_id" value="<?php echo htmlspecialchars($flight['flight_id']); ?>">
                                            <button type="submit" class="inline-flex items-center px-3 py-1 bg-red-600 text-white text-sm font-medium rounded-md hover:bg-red-700 ml-2">Delete</button>
                                        </form>
                                    </td>
                                </tr>

                                <!-- Detailed Row (Initially Hidden) -->
                                <tr class="bg-gray-100 detail-row hidden">
                                    <td colspan="10" class="px-6 py-4">
                                        <div class="p-4 bg-white rounded-lg shadow-md">
                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                <!-- Flight Details -->
                                                <div>
                                                    <h3 class="text-sm font-semibold text-indigo-600 mb-2">Flight Details</h3>
                                                    <p class="text-gray-700"><strong>Flight Number:</strong> <?php echo htmlspecialchars($flight['flight_num']); ?></p>
                                                    <p class="text-gray-700"><strong>Departure:</strong> <?php echo htmlspecialchars($flight['departure_datetime']); ?></p>
                                                    <p class="text-gray-700"><strong>Arrival:</strong> <?php echo htmlspecialchars($flight['arrival_datetime']); ?></p>
                                                    <p class="text-gray-700"><strong>Airline:</strong> <?php echo htmlspecialchars($flight['airline_name']); ?></p>
                                                    <p class="text-gray-700"><strong>Aircraft:</strong> <?php echo htmlspecialchars($flight['aircraft_model']); ?></p>
                                                    <p class="text-gray-700"><strong>Route ID:</strong> <?php echo htmlspecialchars($flight['route_id']); ?></p>
                                                </div>
                                                <!-- Origin Details -->
                                                <div>
                                                    <h3 class="text-sm font-semibold text-indigo-600 mb-2">Origin</h3>
                                                    <p class="text-gray-700"><strong>Name:</strong> <?php echo htmlspecialchars($flight['origin_name']); ?></p>
                                                    <p class="text-gray-700"><strong>IATA Code:</strong> <?php echo htmlspecialchars($flight['origin_iata']); ?></p>
                                                    <p class="text-gray-700"><strong>Location:</strong> <?php echo htmlspecialchars($flight['origin_location']); ?></p>
                                                    <p class="text-gray-700"><strong>City:</strong> <?php echo htmlspecialchars($flight['origin_city']); ?></p>
                                                    <p class="text-gray-700"><strong>Country:</strong> <?php echo htmlspecialchars($flight['origin_country']); ?></p>
                                                </div>
                                                <!-- Destination Details -->
                                                <div>
                                                    <h3 class="text-sm font-semibold text-indigo-600 mb-2">Destination</h3>
                                                    <p class="text-gray-700"><strong>Name:</strong> <?php echo htmlspecialchars($flight['destination_name']); ?></p>
                                                    <p class="text-gray-700"><strong>IATA Code:</strong> <?php echo htmlspecialchars($flight['destination_iata']); ?></p>
                                                    <p class="text-gray-700"><strong>Location:</strong> <?php echo htmlspecialchars($flight['destination_location']); ?></p>
                                                    <p class="text-gray-700"><strong>City:</strong> <?php echo htmlspecialchars($flight['destination_city']); ?></p>
                                                    <p class="text-gray-700"><strong>Country:</strong> <?php echo htmlspecialchars($flight['destination_country']); ?></p>
                                                </div>
                                                <!-- Seating Details -->
                                                <div>
                                                    <h3 class="text-sm font-semibold text-indigo-600 mb-2">Seating Details</h3>
                                                    <p class="text-gray-700"><strong>Economy Seats:</strong> <?php echo htmlspecialchars($flight['seat_economy']); ?></p>
                                                    <p class="text-gray-700"><strong>Business Seats:</strong> <?php echo htmlspecialchars($flight['seat_business']); ?></p>
                                                    <p class="text-gray-700"><strong>First Class Seats:</strong> <?php echo htmlspecialchars($flight['seat_first_class']); ?></p>
                                                </div>
                                                <!-- Status Details -->
                                                <div>
                                                    <h3 class="text-sm font-semibold text-indigo-600 mb-2">Status</h3>
                                                    <p class="text-gray-700"><strong>Current Status:</strong> <?php echo htmlspecialchars($flight['status']); ?></p>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="10" class="px-6 py-4 whitespace-nowrap text-center text-gray-500">No flights found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination Controls -->
            <?php if ($total_pages > 1): ?>
                <div class="mt-6 flex justify-center">
                    <nav class="inline-flex -space-x-px" aria-label="Pagination">
                        <!-- Previous Page Link -->
                        <?php if ($page > 1): ?>
                            <a href="<?php 
                                // Preserve search filters in the query string
                                $query_params = $_GET;
                                $query_params['page'] = $page - 1;
                                echo htmlspecialchars($_SERVER['PHP_SELF'] . '?' . http_build_query($query_params));
                            ?>" class="px-3 py-2 ml-0 leading-tight text-gray-500 bg-white border border-gray-300 rounded-l-lg hover:bg-gray-100 hover:text-gray-700">Previous</a>
                        <?php else: ?>
                            <span class="px-3 py-2 ml-0 leading-tight text-gray-400 bg-white border border-gray-300 rounded-l-lg cursor-not-allowed">Previous</span>
                        <?php endif; ?>

                        <!-- Page Number Links -->
                        <?php
                        // Determine the range of pages to display
                        $max_links = 5; // Maximum number of page links to show
                        $start = max(1, $page - floor($max_links / 2));
                        $end = min($total_pages, $start + $max_links - 1);
                        if ($end - $start + 1 < $max_links) {
                            $start = max(1, $end - $max_links + 1);
                        }

                        for ($i = $start; $i <= $end; $i++):
                            if ($i == $page):
                        ?>
                            <span class="px-3 py-2 leading-tight text-white bg-indigo-600 border border-gray-300 hover:bg-indigo-700"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="<?php 
                                $query_params = $_GET;
                                $query_params['page'] = $i;
                                echo htmlspecialchars($_SERVER['PHP_SELF'] . '?' . http_build_query($query_params));
                            ?>" class="px-3 py-2 leading-tight text-gray-500 bg-white border border-gray-300 hover:bg-gray-100 hover:text-gray-700"><?php echo $i; ?></a>
                        <?php
                            endif;
                        endfor;
                        ?>

                        <!-- Next Page Link -->
                        <?php if ($page < $total_pages): ?>
                            <a href="<?php 
                                $query_params = $_GET;
                                $query_params['page'] = $page + 1;
                                echo htmlspecialchars($_SERVER['PHP_SELF'] . '?' . http_build_query($query_params));
                            ?>" class="px-3 py-2 leading-tight text-gray-500 bg-white border border-gray-300 rounded-r-lg hover:bg-gray-100 hover:text-gray-700">Next</a>
                        <?php else: ?>
                            <span class="px-3 py-2 leading-tight text-gray-400 bg-white border border-gray-300 rounded-r-lg cursor-not-allowed">Next</span>
                        <?php endif; ?>
                    </nav>
                </div>
            <?php endif; ?>
        </div>
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

            // Fetch Aircrafts based on selected Airline in Add Flight Form
            function fetchAircrafts(airlineId, selectedAircraftId = '') {
                var xhr = new XMLHttpRequest();
                xhr.open('GET', 'get_aircrafts.php?airline_id=' + airlineId, true);
                xhr.onload = function() {
                    if (xhr.status === 200) {
                        try {
                            var response = JSON.parse(xhr.responseText);
                            var aircraftSelect = document.getElementById('aircraft');
                            aircraftSelect.innerHTML = '<option value="">--Select Aircraft--</option>'; // Reset options

                            if (response.status === 'success') {
                                response.data.forEach(function(aircraft) {
                                    var option = document.createElement('option');
                                    option.value = aircraft.aircraft_id;
                                    option.text = aircraft.model + ' (Economy: ' + aircraft.seat_economy + ', Business: ' + aircraft.seat_business + ', First Class: ' + aircraft.seat_first_class + ')';
                                    if (aircraft.aircraft_id == selectedAircraftId) {
                                        option.selected = true;
                                    }
                                    aircraftSelect.appendChild(option);
                                });
                            } else {
                                alert(response.message);
                            }
                        } catch (e) {
                            alert('Invalid response from server.');
                        }
                    } else {
                        alert('An error occurred while fetching aircrafts.');
                    }
                };
                xhr.send();
            }

            document.addEventListener('DOMContentLoaded', function() {
                var airlineSelect = document.getElementById('airline');
                var selectedAirline = airlineSelect.value;
                var selectedAircraft = "<?php echo isset($_POST['aircraft']) ? htmlspecialchars($_POST['aircraft']) : ''; ?>";
                if (selectedAirline) {
                    fetchAircrafts(selectedAirline, selectedAircraft);
                }

                airlineSelect.addEventListener('change', function() {
                    var selectedAirline = this.value;
                    if (selectedAirline) {
                        fetchAircrafts(selectedAirline);
                    } else {
                        var aircraftSelect = document.getElementById('aircraft');
                        aircraftSelect.innerHTML = '<option value="">--Select Aircraft--</option>'; // Reset if no airline selected
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
        <script>
            function validateDateTime() {
                const input = document.getElementById('departure_datetime');
                const selectedDateTime = new Date(input.value); // User-selected date and time
                const currentDateTime = new Date(); // Current date and time

                // Normalize currentDateTime to match the precision of the datetime-local input (to the minute)
                currentDateTime.setSeconds(0, 0);

                // Validate that the selected departure datetime is not in the past
                if (selectedDateTime < currentDateTime) {
                    input.setCustomValidity('Please select a date and time in the future.');
                } else {
                    input.setCustomValidity(''); // Clear the custom error
                }
            }
        </script>

        <script>
            function validateArrivalDateTime() {
                const arrivalInput = document.getElementById('arrival_datetime');
                const selectedArrivalDateTime = new Date(arrivalInput.value); // User-selected date and time
                const currentDateTime = new Date(); // Current date and time

                // Normalize currentDateTime to match the precision of the datetime-local input (to the minute)
                currentDateTime.setSeconds(0, 0);

                // Validate that the selected arrival datetime is not in the past
                if (selectedArrivalDateTime < currentDateTime) {
                    arrivalInput.setCustomValidity('Please select an arrival date and time in the future.');
                } else {
                    arrivalInput.setCustomValidity(''); // Clear the custom error
                }
            }
        </script>

</body>
</html>
<?php
$conn->close();
?>
