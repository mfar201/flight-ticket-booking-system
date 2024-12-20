<?php
// edit_route.php
session_start();
require 'config.php';
require 'csrf_helper.php'; // Include CSRF helper

// Check if user is logged in and is an Admin
if (!isset($_SESSION['user_id']) || $_SESSION['role_name'] != 'Admin') {
    header("Location: login.php");
    exit();
}

// Check if route_id is set and valid
if (!isset($_GET['route_id']) || !filter_var($_GET['route_id'], FILTER_VALIDATE_INT)) {
    $_SESSION['error_message'] = "Invalid Route ID.";
    header("Location: manage_routes.php");
    exit();
}

$route_id = intval($_GET['route_id']);
$errors = isset($_SESSION['errors']) ? $_SESSION['errors'] : [];
$success = isset($_SESSION['success']) ? $_SESSION['success'] : [];

// Unset session messages after fetching
unset($_SESSION['errors']);
unset($_SESSION['success']);

// Fetch existing route data, including price fields
$stmt = $conn->prepare("
    SELECT 
        r.route_id,
        r.origin_airport_id,
        r.destination_airport_id,
        r.distance,
        r.price_seat_economy,
        r.price_seat_business,
        r.price_seat_first_class,
        origin.name AS origin_name,
        origin.iata_code AS origin_iata,
        destination.name AS destination_name,
        destination.iata_code AS destination_iata
    FROM routes r
    JOIN airports origin ON r.origin_airport_id = origin.airport_id
    JOIN airports destination ON r.destination_airport_id = destination.airport_id
    WHERE r.route_id = ?
");
if ($stmt) {
    $stmt->bind_param("i", $route_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows == 1) {
        $route = $result->fetch_assoc();
        $origin_airport_id = $route['origin_airport_id'];
        $destination_airport_id = $route['destination_airport_id'];
        $distance = $route['distance'];
        $price_seat_economy = $route['price_seat_economy'];
        $price_seat_business = $route['price_seat_business'];
        $price_seat_first_class = $route['price_seat_first_class'];
    } else {
        $_SESSION['error_message'] = "Route not found.";
        header("Location: manage_routes.php");
        exit();
    }
    $stmt->close();
} else {
    $_SESSION['error_message'] = "Error preparing statement: " . $conn->error;
    header("Location: manage_routes.php");
    exit();
}

// Fetch all airports for dropdowns
$airports = [];
$stmt_airports = $conn->prepare("SELECT airport_id, name, iata_code FROM airports ORDER BY name ASC");
if ($stmt_airports) {
    $stmt_airports->execute();
    $result_airports = $stmt_airports->get_result();
    while ($row = $result_airports->fetch_assoc()) {
        $airports[] = $row;
    }
    $stmt_airports->close();
} else {
    $errors[] = "Error fetching airports: " . htmlspecialchars($conn->error);
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !validateCsrfToken($_POST['csrf_token'])) {
        $errors[] = "Invalid CSRF token.";
    } else {
        $origin_airport_new = intval($_POST['origin_airport']);
        $destination_airport_new = intval($_POST['destination_airport']);
        $distance_new = intval($_POST['distance']);
        
        // **New Price Fields Extraction**
        $price_seat_economy_new = floatval($_POST['price_seat_economy']);
        $price_seat_business_new = floatval($_POST['price_seat_business']);
        $price_seat_first_class_new = floatval($_POST['price_seat_first_class']);

        // Validate Inputs
        if ($origin_airport_new == $destination_airport_new) {
            $errors[] = "Origin and destination airports must be different.";
        }
        if ($distance_new <= 0) {
            $errors[] = "Distance must be a positive integer.";
        }
        // **Validate Price Fields**
        if ($price_seat_economy_new < 0) {
            $errors[] = "Price for Economy seats cannot be negative.";
        }
        if ($price_seat_business_new < 0) {
            $errors[] = "Price for Business seats cannot be negative.";
        }
        if ($price_seat_first_class_new < 0) {
            $errors[] = "Price for First Class seats cannot be negative.";
        }

        // Check if selected airports exist
        $stmt_check = $conn->prepare("SELECT airport_id FROM airports WHERE airport_id IN (?, ?)");
        if ($stmt_check) {
            $stmt_check->bind_param("ii", $origin_airport_new, $destination_airport_new);
            $stmt_check->execute();
            $result_check = $stmt_check->get_result();
            if ($result_check->num_rows != 2) {
                $errors[] = "One or both selected airports do not exist.";
            }
            $stmt_check->close();
        } else {
            $errors[] = "Error preparing statement: " . $conn->error;
        }

        // Update if no errors
        if (empty($errors)) {
            // **Updated UPDATE Statement with Price Fields**
            $stmt_update = $conn->prepare("
                UPDATE routes 
                SET origin_airport_id = ?, 
                    destination_airport_id = ?, 
                    distance = ?, 
                    price_seat_economy = ?, 
                    price_seat_business = ?, 
                    price_seat_first_class = ?
                WHERE route_id = ?
            ");
            if ($stmt_update) {
                $stmt_update->bind_param(
                    "iiidddi",
                    $origin_airport_new,
                    $destination_airport_new,
                    $distance_new,
                    $price_seat_economy_new,
                    $price_seat_business_new,
                    $price_seat_first_class_new,
                    $route_id
                );
                if ($stmt_update->execute()) {
                    $success[] = "Route updated successfully.";
                    
                    // Update local variables to reflect changes in the form
                    $origin_airport_id = $origin_airport_new;
                    $destination_airport_id = $destination_airport_new;
                    $distance = $distance_new;
                    $price_seat_economy = $price_seat_economy_new;
                    $price_seat_business = $price_seat_business_new;
                    $price_seat_first_class = $price_seat_first_class_new;
                } else {
                    $errors[] = "Error updating route: " . $stmt_update->error;
                }
                $stmt_update->close();
            } else {
                $errors[] = "Error preparing statement: " . $conn->error;
            }
        }
    }

    // Store messages in session to persist after redirect
    $_SESSION['errors'] = $errors;
    $_SESSION['success'] = $success;

    // Redirect to the same page to prevent form resubmission
    header("Location: edit_route.php?route_id=" . urlencode($route_id));
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Route - Flight Ticket Booking System</title>
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
                    
                    <form action="logout.php" method="POST" class="inline">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCsrfToken()); ?>">
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

        <!-- Edit Route Form -->
        <div class="bg-white shadow-md rounded-lg p-6">
            <h2 class="text-2xl font-semibold mb-4 text-indigo-600">Edit Route</h2>
            <form method="POST" action="edit_route.php?route_id=<?php echo urlencode($route_id); ?>" class="space-y-4">
                <!-- CSRF Token -->
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCsrfToken()); ?>">

                <div>
                    <label for="origin_airport" class="block text-sm font-medium text-gray-700">Origin Airport<span class="text-red-500">*</span></label>
                    <select id="origin_airport" name="origin_airport" required class="mt-1 block w-full px-4 py-2 border border-gray-300 bg-white rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="">--Select Origin Airport--</option>
                        <?php
                        foreach ($airports as $airport) {
                            $selected = ($airport['airport_id'] == $origin_airport_id) ? "selected" : "";
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
                            $selected = ($airport['airport_id'] == $destination_airport_id) ? "selected" : "";
                            echo "<option value='" . htmlspecialchars($airport['airport_id']) . "' $selected>" . htmlspecialchars($airport['name']) . " (" . htmlspecialchars($airport['iata_code']) . ")</option>";
                        }
                        ?>
                    </select>
                </div>

                <div>
                    <label for="distance" class="block text-sm font-medium text-gray-700">Distance (km)<span class="text-red-500">*</span></label>
                    <input type="number" id="distance" name="distance" min="1" required value="<?php echo htmlspecialchars($distance); ?>" class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                </div>

                <!-- **New Price Fields Added Below** -->
                <div>
                    <label for="price_seat_economy" class="block text-sm font-medium text-gray-700">Price Seat Economy ($)<span class="text-red-500">*</span></label>
                    <input type="number" step="0.01" id="price_seat_economy" name="price_seat_economy" min="0" required value="<?php echo htmlspecialchars($price_seat_economy); ?>" class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                </div>

                <div>
                    <label for="price_seat_business" class="block text-sm font-medium text-gray-700">Price Seat Business ($)<span class="text-red-500">*</span></label>
                    <input type="number" step="0.01" id="price_seat_business" name="price_seat_business" min="0" required value="<?php echo htmlspecialchars($price_seat_business); ?>" class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                </div>

                <div>
                    <label for="price_seat_first_class" class="block text-sm font-medium text-gray-700">Price Seat First Class ($)<span class="text-red-500">*</span></label>
                    <input type="number" step="0.01" id="price_seat_first_class" name="price_seat_first_class" min="0" required value="<?php echo htmlspecialchars($price_seat_first_class); ?>" class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                </div>
                <!-- **End of New Price Fields** -->

                <div>
                    <button type="submit" class="w-full px-4 py-2 bg-indigo-600 text-white font-medium rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500">Update Route</button>
                </div>
            </form>

            <div class="mt-6">
                <a href="manage_routes.php" class="inline-flex items-center px-4 py-2 bg-gray-200 text-gray-700 font-medium rounded-md hover:bg-gray-300">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Manage Routes
                </a>
            </div>
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
