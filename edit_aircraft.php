<?php
// edit_aircraft.php
session_start();
require 'config.php';
require 'csrf_helper.php'; // Include CSRF helper functions

// Check if user is logged in and is an Admin
if (!isset($_SESSION['user_id']) || $_SESSION['role_name'] != 'Admin') {
    header("Location: login.php");
    exit();
}

// Check if aircraft_id is set and valid
if (!isset($_GET['aircraft_id']) || !filter_var($_GET['aircraft_id'], FILTER_VALIDATE_INT)) {
    $_SESSION['error_message'] = "Invalid Aircraft ID.";
    header("Location: manage_aircrafts.php");
    exit();
}

$aircraft_id = intval($_GET['aircraft_id']);
$errors = isset($_SESSION['errors']) ? $_SESSION['errors'] : [];
$success = isset($_SESSION['success']) ? $_SESSION['success'] : [];

// Unset session messages after fetching
unset($_SESSION['errors']);
unset($_SESSION['success']);

// Fetch existing aircraft details
$stmt = $conn->prepare("SELECT aircraft_id, model, seat_economy, seat_business, seat_first_class, airline_id FROM aircrafts WHERE aircraft_id = ?");
if ($stmt) {
    $stmt->bind_param("i", $aircraft_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows != 1) {
        $_SESSION['error_message'] = "Aircraft not found.";
        header("Location: manage_aircrafts.php");
        exit();
    }
    $aircraft = $result->fetch_assoc();
    $stmt->close();
} else {
    $_SESSION['error_message'] = "Error fetching aircraft: " . htmlspecialchars($conn->error);
    header("Location: manage_aircrafts.php");
    exit();
}

// Fetch all airlines for the dropdown
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
    $_SESSION['error_message'] = "Error fetching airlines: " . htmlspecialchars($conn->error);
    header("Location: manage_aircrafts.php");
    exit();
}

// Handle Form Submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate CSRF Token
    if (!isset($_POST['csrf_token']) || !validateCsrfToken($_POST['csrf_token'])) {
        $errors[] = "Invalid CSRF token.";
    } else {
        // Retrieve and sanitize inputs
        $model = trim($_POST['model']);
        $seat_economy = intval($_POST['seat_economy']);
        $seat_business = intval($_POST['seat_business']);
        $seat_first_class = intval($_POST['seat_first_class']);
        $airline_id_post = intval($_POST['airline']);

        // Validate Inputs
        if (empty($model)) {
            $errors[] = "Aircraft model is required.";
        }
        if ($seat_economy < 0) {
            $errors[] = "Seats economy cannot be negative.";
        }
        if ($seat_business < 0) {
            $errors[] = "Seats business cannot be negative.";
        }
        if ($seat_first_class < 0) {
            $errors[] = "Seats first class cannot be negative.";
        }
        if ($airline_id_post <= 0) {
            $errors[] = "Valid Airline must be selected.";
        }

        // Check if airline exists
        $stmt_check = $conn->prepare("SELECT airline_id FROM airlines WHERE airline_id = ?");
        if ($stmt_check) {
            $stmt_check->bind_param("i", $airline_id_post);
            $stmt_check->execute();
            $result_check = $stmt_check->get_result();
            if ($result_check->num_rows != 1) {
                $errors[] = "Selected airline does not exist.";
            }
            $stmt_check->close();
        } else {
            $errors[] = "Error preparing statement: " . $conn->error;
        }

        // Update Database if no errors
        if (empty($errors)) {
            $stmt = $conn->prepare("UPDATE aircrafts SET model = ?, seat_economy = ?, seat_business = ?, seat_first_class = ?, airline_id = ? WHERE aircraft_id = ?");
            if ($stmt) {
                $stmt->bind_param(
                    "siiiii",
                    $model,
                    $seat_economy,
                    $seat_business,
                    $seat_first_class,
                    $airline_id_post,
                    $aircraft_id
                );
                if ($stmt->execute()) {
                    $success[] = "Aircraft updated successfully.";
                    // Update the $aircraft array to reflect changes
                    $aircraft['model'] = $model;
                    $aircraft['seat_economy'] = $seat_economy;
                    $aircraft['seat_business'] = $seat_business;
                    $aircraft['seat_first_class'] = $seat_first_class;
                    $aircraft['airline_id'] = $airline_id_post;
                } else {
                    $errors[] = "Error updating aircraft: " . $stmt->error;
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
    header("Location: edit_aircraft.php?aircraft_id=" . urlencode($aircraft_id));
    exit();
}

// Generate a new CSRF token for the form
$csrf_token = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Aircraft - Flight Ticket Booking System</title>
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
                    
                    <!-- Logout Form -->
                    <form action="logout.php" method="POST">
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
            <!-- Logout Form -->
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

        <!-- Edit Aircraft Form -->
        <div class="bg-white shadow-md rounded-lg p-6">
            <h2 class="text-2xl font-semibold mb-4 text-indigo-600">Edit Aircraft</h2>
            <form method="POST" action="edit_aircraft.php?aircraft_id=<?php echo urlencode($aircraft_id); ?>" class="space-y-4">
                <!-- CSRF Token -->
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">

                <div>
                    <label for="model" class="block text-sm font-medium text-gray-700">Aircraft Model<span class="text-red-500">*</span></label>
                    <input type="text" id="model" name="model" value="<?php echo htmlspecialchars($aircraft['model']); ?>" required class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                </div>

                <div>
                    <label for="seat_economy" class="block text-sm font-medium text-gray-700">Seats Economy<span class="text-red-500">*</span></label>
                    <input type="number" id="seat_economy" name="seat_economy" min="0" value="<?php echo htmlspecialchars($aircraft['seat_economy']); ?>" required class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                </div>

                <div>
                    <label for="seat_business" class="block text-sm font-medium text-gray-700">Seats Business<span class="text-red-500">*</span></label>
                    <input type="number" id="seat_business" name="seat_business" min="0" value="<?php echo htmlspecialchars($aircraft['seat_business']); ?>" required class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                </div>

                <div>
                    <label for="seat_first_class" class="block text-sm font-medium text-gray-700">Seats First Class<span class="text-red-500">*</span></label>
                    <input type="number" id="seat_first_class" name="seat_first_class" min="0" value="<?php echo htmlspecialchars($aircraft['seat_first_class']); ?>" required class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                </div>

                <div>
                    <label for="airline" class="block text-sm font-medium text-gray-700">Airline<span class="text-red-500">*</span></label>
                    <select id="airline" name="airline" required class="mt-1 block w-full px-4 py-2 border border-gray-300 bg-white rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="">--Select Airline--</option>
                        <?php
                        foreach ($airlines as $airline) {
                            $selected = ($aircraft['airline_id'] == $airline['airline_id']) ? "selected" : "";
                            echo "<option value='" . htmlspecialchars($airline['airline_id']) . "' $selected>" . htmlspecialchars($airline['name']) . " (" . htmlspecialchars($airline['iata_code']) . ")</option>";
                        }
                        ?>
                    </select>
                </div>

                <div>
                    <button type="submit" class="w-full px-4 py-2 bg-indigo-600 text-white font-medium rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500">Update Aircraft</button>
                </div>
            </form>

            <div class="mt-6">
                <a href="manage_aircrafts.php" class="inline-flex items-center px-4 py-2 bg-gray-200 text-gray-700 font-medium rounded-md hover:bg-gray-300">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Manage Aircrafts
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
