<?php
// edit_flight.php
session_start();
require 'config.php';
require 'csrf_helper.php'; // Include CSRF helper

// Check if user is logged in and is an Admin
if (!isset($_SESSION['user_id']) || $_SESSION['role_name'] != 'Admin') {
    header("Location: login.php");
    exit();
}

// CSRF Protection: Generate CSRF token
$csrf_token = generateCsrfToken();

// Initialize variables for error and success messages
$errors = isset($_SESSION['errors']) ? $_SESSION['errors'] : [];
$success = isset($_SESSION['success']) ? $_SESSION['success'] : [];

// Unset session messages after fetching
unset($_SESSION['errors']);
unset($_SESSION['success']);

// Get the flight_id from GET parameters
if (!isset($_GET['flight_id']) || !filter_var($_GET['flight_id'], FILTER_VALIDATE_INT)) {
    echo "Invalid Flight ID.";
    exit();
}

$flight_id = intval($_GET['flight_id']);

// Fetch all airlines for the dropdown
$airlines = [];
$stmt = $conn->prepare("SELECT airline_id, name, country, iata_code FROM airlines ORDER BY name ASC");
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

// Fetch all routes for the dropdown
$routes = [];
$stmt = $conn->prepare("
    SELECT 
        r.route_id, 
        origin.name AS origin_name, 
        origin.iata_code AS origin_iata, 
        destination.name AS destination_name, 
        destination.iata_code AS destination_iata, 
        r.distance
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

// Fetch existing flight details
$stmt = $conn->prepare("SELECT f.flight_id, f.flight_num, f.departure_datetime, f.arrival_datetime, f.airline_id, f.aircraft_id, f.route_id, f.status, f.seat_economy, f.seat_business, f.seat_first_class FROM flights f WHERE f.flight_id = ?");
if ($stmt) {
    $stmt->bind_param("i", $flight_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows != 1) {
        echo "Flight not found.";
        exit();
    }
    $flight = $result->fetch_assoc();
    $stmt->close();
} else {
    echo "Error fetching flight: " . htmlspecialchars($conn->error);
    exit();
}

// Handle Form Submission (Processing after form is submitted)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !validateCsrfToken($_POST['csrf_token'])) {
        $errors[] = "Invalid CSRF token.";
    } else {
        // Retrieve and sanitize inputs
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
        if (strtotime($arrival_datetime) <= strtotime($departure_datetime)) {
            $errors[] = "Arrival time must be after departure time.";
        }
        if (empty($status) || !in_array($status, ['Scheduled', 'Delayed', 'Cancelled', 'Completed'])) {
            $errors[] = "Invalid flight status.";
        }

        // Check if airline exists
        $stmt_check = $conn->prepare("SELECT airline_id FROM airlines WHERE airline_id = ?");
        if ($stmt_check) {
            $stmt_check->bind_param("i", $airline_id);
            $stmt_check->execute();
            $result_check = $stmt_check->get_result();
            if ($result_check->num_rows != 1) {
                $errors[] = "Selected airline does not exist.";
            }
            $stmt_check->close();
        } else {
            $errors[] = "Error preparing statement: " . $conn->error;
        }

        // Check if aircraft exists and belongs to the selected airline
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

        // Check if flight number is unique (excluding current flight)
        $stmt_check = $conn->prepare("SELECT flight_id FROM flights WHERE flight_num = ? AND flight_id != ?");
        if ($stmt_check) {
            $stmt_check->bind_param("si", $flight_num, $flight_id);
            $stmt_check->execute();
            $result_check = $stmt_check->get_result();
            if ($result_check->num_rows > 0) {
                $errors[] = "Flight number already exists.";
            }
            $stmt_check->close();
        } else {
            $errors[] = "Error preparing statement: " . $conn->error;
        }

        // Update Database if no errors
        if (empty($errors)) {
            // Begin Transaction
            $conn->begin_transaction();
            try {
                // Update flight details
                $stmt = $conn->prepare("UPDATE flights SET flight_num = ?, departure_datetime = ?, arrival_datetime = ?, airline_id = ?, aircraft_id = ?, route_id = ?, status = ?, seat_economy = ?, seat_business = ?, seat_first_class = ? WHERE flight_id = ?");
                if ($stmt) {
                    $stmt->bind_param(
                        "sssiiisiiii",
                        $flight_num,
                        $departure_datetime,
                        $arrival_datetime,
                        $airline_id,
                        $aircraft_id,
                        $route_id,
                        $status,
                        $aircraft['seat_economy'],
                        $aircraft['seat_business'],
                        $aircraft['seat_first_class'],
                        $flight_id
                    );
                    if (!$stmt->execute()) {
                        if ($conn->errno == 1062) { // Duplicate entry
                            throw new Exception("Flight number already exists.");
                        } else {
                            throw new Exception("Error updating flight: " . $stmt->error);
                        }
                    }
                    $stmt->close();
                } else {
                    throw new Exception("Error preparing statement: " . $conn->error);
                }

                // If the flight status is being changed to 'Cancelled', update all associated bookings
                if ($status == 'Cancelled' && $flight['status'] != 'Cancelled') {
                    // Fetch all bookings for this flight with status 'Confirmed' or 'Pending'
                    $stmt = $conn->prepare("SELECT booking_id, seat_type FROM bookings WHERE flight_id = ? AND status IN ('Confirmed', 'Pending')");
                    if ($stmt) {
                        $stmt->bind_param("i", $flight_id);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        $bookings_to_cancel = $result->fetch_all(MYSQLI_ASSOC);
                        $stmt->close();

                        if (!empty($bookings_to_cancel)) {
                            foreach ($bookings_to_cancel as $booking) {
                                $booking_id = $booking['booking_id'];
                                $seat_type = $booking['seat_type'];

                                // Update booking status to 'Cancelled'
                                $update_booking_stmt = $conn->prepare("UPDATE bookings SET status = 'Cancelled' WHERE booking_id = ?");
                                if ($update_booking_stmt) {
                                    $update_booking_stmt->bind_param("i", $booking_id);
                                    if (!$update_booking_stmt->execute()) {
                                        throw new Exception("Error cancelling booking ID $booking_id: " . $update_booking_stmt->error);
                                    }
                                    $update_booking_stmt->close();
                                } else {
                                    throw new Exception("Error preparing booking update statement: " . $conn->error);
                                }

                                // Increment seat count in flights table based on seat_type
                                $seat_column_mapping = [
                                    'Economy' => 'seat_economy',
                                    'Business' => 'seat_business',
                                    'First Class' => 'seat_first_class'
                                ];

                                if (!array_key_exists($seat_type, $seat_column_mapping)) {
                                    throw new Exception("Invalid seat type '$seat_type' for booking ID $booking_id.");
                                }

                                $seat_column = $seat_column_mapping[$seat_type];

                                $update_seat_stmt = $conn->prepare("UPDATE flights SET $seat_column = $seat_column + 1 WHERE flight_id = ?");
                                if ($update_seat_stmt) {
                                    $update_seat_stmt->bind_param("i", $flight_id);
                                    if (!$update_seat_stmt->execute()) {
                                        throw new Exception("Error updating seat count for flight ID $flight_id: " . $update_seat_stmt->error);
                                    }
                                    $update_seat_stmt->close();
                                } else {
                                    throw new Exception("Error preparing seat count update statement: " . $conn->error);
                                }
                            }
                        }
                    } else {
                        throw new Exception("Error fetching bookings for cancellation: " . $conn->error);
                    }
                }

                // Commit Transaction
                $conn->commit();
                $success[] = "Flight updated successfully.";

                // Refresh the flight data after successful update
                $flight['flight_num'] = $flight_num;
                $flight['departure_datetime'] = $departure_datetime;
                $flight['arrival_datetime'] = $arrival_datetime;
                $flight['airline_id'] = $airline_id;
                $flight['aircraft_id'] = $aircraft_id;
                $flight['route_id'] = $route_id;
                $flight['status'] = $status;
                $flight['seat_economy'] = $aircraft['seat_economy'];
                $flight['seat_business'] = $aircraft['seat_business'];
                $flight['seat_first_class'] = $aircraft['seat_first_class'];
            } catch (Exception $e) {
                // Rollback Transaction
                $conn->rollback();
                $errors[] = "Failed to update flight: " . htmlspecialchars($e->getMessage());
            }

            // Store messages back to session and reload the page to display them
            if (!empty($errors)) {
                $_SESSION['errors'] = $errors;
            }
            if (!empty($success)) {
                $_SESSION['success'] = $success;
            }

            // Redirect back to the same page to display messages
            header("Location: edit_flight.php?flight_id=" . urlencode($flight_id));
            exit();
        }
    }
}
// Function to build query parameters for links, excluding specified keys
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
    <title>Edit Flight - Flight Ticket Booking System</title>
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

        <!-- Edit Flight Form -->
        <div class="bg-white shadow-md rounded-lg p-6">
            <h2 class="text-2xl font-semibold mb-4 text-indigo-600">Edit Flight</h2>
            <form method="POST" action="edit_flight.php?flight_id=<?php echo urlencode($flight_id); ?>" class="space-y-4">
                <!-- CSRF Token -->
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">

                <div>
                    <label for="flight_num" class="block text-sm font-medium text-gray-700">Flight Number<span class="text-red-500">*</span></label>
                    <input type="text" id="flight_num" name="flight_num" required value="<?php echo htmlspecialchars($flight['flight_num']); ?>" class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 uppercase">
                </div>

                <div>
                    <label for="departure_datetime" class="block text-sm font-medium text-gray-700">Departure Date & Time<span class="text-red-500">*</span></label>
                    <input type="datetime-local" id="departure_datetime" name="departure_datetime" required value="<?php echo date('Y-m-d\TH:i', strtotime($flight['departure_datetime'])); ?>" class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                </div>

                <div>
                    <label for="arrival_datetime" class="block text-sm font-medium text-gray-700">Arrival Date & Time<span class="text-red-500">*</span></label>
                    <input type="datetime-local" id="arrival_datetime" name="arrival_datetime" required value="<?php echo date('Y-m-d\TH:i', strtotime($flight['arrival_datetime'])); ?>" class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                </div>

                <div>
                    <label for="airline" class="block text-sm font-medium text-gray-700">Airline<span class="text-red-500">*</span></label>
                    <select id="airline" name="airline" required class="mt-1 block w-full px-4 py-2 border border-gray-300 bg-white rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="">--Select Airline--</option>
                        <?php
                        foreach ($airlines as $airline) {
                            // Preserve selected airline after form submission
                            $selected = ($flight['airline_id'] == $airline['airline_id']) ? "selected" : "";
                            echo "<option value='" . htmlspecialchars($airline['airline_id']) . "' $selected>" . htmlspecialchars($airline['name']) . " (" . htmlspecialchars($airline['iata_code']) . ")</option>";
                        }
                        ?>
                    </select>
                </div>

                <div>
                    <label for="aircraft" class="block text-sm font-medium text-gray-700">Aircraft<span class="text-red-500">*</span></label>
                    <select id="aircraft" name="aircraft" required class="mt-1 block w-full px-4 py-2 border border-gray-300 bg-white rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="">--Select Aircraft--</option>
                        <!-- Aircraft options will be populated based on selected airline -->
                        <?php
                        // Fetch aircrafts belonging to the selected airline
                        $aircrafts = [];
                        if ($flight['airline_id'] > 0) {
                            $stmt_aircraft = $conn->prepare("SELECT aircraft_id, model, seat_economy, seat_business, seat_first_class FROM aircrafts WHERE airline_id = ? ORDER BY model ASC");
                            if ($stmt_aircraft) {
                                $stmt_aircraft->bind_param("i", $flight['airline_id']);
                                $stmt_aircraft->execute();
                                $result_aircraft = $stmt_aircraft->get_result();
                                while ($aircraft = $result_aircraft->fetch_assoc()) {
                                    $aircrafts[] = $aircraft;
                                }
                                $stmt_aircraft->close();
                            }
                        }

                        foreach ($aircrafts as $aircraft) {
                            $selected = ($flight['aircraft_id'] == $aircraft['aircraft_id']) ? "selected" : "";
                            echo "<option value='" . htmlspecialchars($aircraft['aircraft_id']) . "' $selected>" . htmlspecialchars($aircraft['model']) . " (Economy: " . htmlspecialchars($aircraft['seat_economy']) . ", Business: " . htmlspecialchars($aircraft['seat_business']) . ", First Class: " . htmlspecialchars($aircraft['seat_first_class']) . ")</option>";
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
                            $selected = ($flight['route_id'] == $route['route_id']) ? "selected" : "";
                            echo "<option value='" . htmlspecialchars($route['route_id']) . "' $selected>" . htmlspecialchars($route['origin_name']) . " (" . htmlspecialchars($route['origin_iata']) . ") &rarr; " . htmlspecialchars($route['destination_name']) . " (" . htmlspecialchars($route['destination_iata']) . ")</option>";
                        }
                        ?>
                    </select>
                </div>

                <div>
                    <label for="status" class="block text-sm font-medium text-gray-700">Status<span class="text-red-500">*</span></label>
                    <select id="status" name="status" required class="mt-1 block w-full px-4 py-2 border border-gray-300 bg-white rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="">--Select Status--</option>
                        <option value="Scheduled" <?php echo ($flight['status'] == 'Scheduled') ? 'selected' : ''; ?>>Scheduled</option>
                        <option value="Delayed" <?php echo ($flight['status'] == 'Delayed') ? 'selected' : ''; ?>>Delayed</option>
                        <option value="Cancelled" <?php echo ($flight['status'] == 'Cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                        <option value="Completed" <?php echo ($flight['status'] == 'Completed') ? 'selected' : ''; ?>>Completed</option>
                    </select>
                </div>

                <div>
                    <button type="submit" class="w-full px-4 py-2 bg-indigo-600 text-white font-medium rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500">Update Flight</button>
                </div>
            </form>
        </div>

        <div class="mt-6">
            <a href="manage_flights.php" class="inline-flex items-center px-4 py-2 bg-gray-200 text-gray-700 font-medium rounded-md hover:bg-gray-300">
                <i class="fas fa-arrow-left mr-2"></i> Back to Manage Flights
            </a>
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

        // Fetch Aircrafts based on selected Airline in Edit Flight Form
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
            var selectedAircraft = "<?php echo htmlspecialchars($flight['aircraft_id']); ?>";
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
    </script>

</body>
</html>
<?php
$conn->close();
?>
