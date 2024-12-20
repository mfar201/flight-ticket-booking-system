<?php
// edit_airline.php
session_start();
require 'config.php';
require 'csrf_helper.php'; // Include CSRF helper functions

// Check if user is logged in and is an Admin
if (!isset($_SESSION['user_id']) || $_SESSION['role_name'] != 'Admin') {
    header("Location: login.php");
    exit();
}

// Check if airline_id is set
if (!isset($_GET['airline_id'])) {
    $_SESSION['error_message'] = "Airline ID not provided.";
    header("Location: manage_airlines.php");
    exit();
}

$airline_id = intval($_GET['airline_id']);
$errors = isset($_SESSION['errors']) ? $_SESSION['errors'] : [];
$success = isset($_SESSION['success']) ? $_SESSION['success'] : [];

// Unset session messages after fetching
unset($_SESSION['errors']);
unset($_SESSION['success']);

// Fetch existing airline data
$stmt = $conn->prepare("SELECT * FROM airlines WHERE airline_id = ?");
if ($stmt) {
    $stmt->bind_param("i", $airline_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows == 1) {
        $airline = $result->fetch_assoc();
        $name = $airline['name'];
        $country = $airline['country'];
        $iata_code = $airline['iata_code'];
    } else {
        $_SESSION['error_message'] = "Airline not found.";
        header("Location: manage_airlines.php");
        exit();
    }
    $stmt->close();
} else {
    $_SESSION['error_message'] = "Error preparing statement: " . $conn->error;
    header("Location: manage_airlines.php");
    exit();
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate CSRF Token
    if (!isset($_POST['csrf_token']) || !validateCsrfToken($_POST['csrf_token'])) {
        $errors[] = "Invalid CSRF token.";
    } else {
        $name = trim($_POST['airline_name']);
        $country = trim($_POST['airline_country']);
        $iata_code_new = strtoupper(trim($_POST['airline_iata']));

        // Validate Inputs
        if (empty($name)) {
            $errors[] = "Airline name is required.";
        }
        if (empty($iata_code_new) || strlen($iata_code_new) != 2) {
            $errors[] = "IATA code must be exactly 2 characters.";
        }

        // Check for duplicate IATA code
        $stmt = $conn->prepare("SELECT airline_id FROM airlines WHERE iata_code = ? AND airline_id != ?");
        if ($stmt) {
            $stmt->bind_param("si", $iata_code_new, $airline_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $errors[] = "IATA code already exists for another airline.";
            }
            $stmt->close();
        } else {
            $errors[] = "Error preparing statement: " . $conn->error;
        }

        // Update if no errors
        if (empty($errors)) {
            $stmt = $conn->prepare("UPDATE airlines SET name = ?, country = ?, iata_code = ? WHERE airline_id = ?");
            if ($stmt) {
                $stmt->bind_param("sssi", $name, $country, $iata_code_new, $airline_id);
                if ($stmt->execute()) {
                    $_SESSION['success'] = ["Airline updated successfully."];
                    header("Location: edit_airline.php?airline_id=" . urlencode($airline_id));
                    exit(); // Ensure no further processing happens
                } else {
                    $errors[] = "Error updating airline: " . $stmt->error;
                }
                $stmt->close();
            } else {
                $errors[] = "Error preparing statement: " . $conn->error;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Airline - Flight Ticket Booking System</title>
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

        <!-- Edit Airline Form -->
        <div class="bg-white shadow-md rounded-lg p-6">
            <h2 class="text-2xl font-semibold mb-4 text-indigo-600">Edit Airline</h2>
            <form method="POST" action="edit_airline.php?airline_id=<?php echo urlencode($airline_id); ?>" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCsrfToken()); ?>">

                <div>
                    <label for="airline_name" class="block text-sm font-medium text-gray-700">Airline Name<span class="text-red-500">*</span></label>
                    <input type="text" id="airline_name" name="airline_name" value="<?php echo htmlspecialchars($name); ?>" required class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                </div>

                <div>
                    <label for="airline_country" class="block text-sm font-medium text-gray-700">Country</label>
                    <input type="text" id="airline_country" name="airline_country" value="<?php echo htmlspecialchars($country); ?>" class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                </div>

                <div>
                    <label for="airline_iata" class="block text-sm font-medium text-gray-700">IATA Code (2 Characters)<span class="text-red-500">*</span></label>
                    <input type="text" id="airline_iata" name="airline_iata" maxlength="2" value="<?php echo htmlspecialchars($iata_code); ?>" required class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 uppercase">
                </div>

                <div>
                    <button type="submit" class="w-full px-4 py-2 bg-indigo-600 text-white font-medium rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500">Update Airline</button>
                </div>
            </form>

            <div class="mt-6">
                <a href="manage_airlines.php" class="inline-flex items-center px-4 py-2 bg-gray-200 text-gray-700 font-medium rounded-md hover:bg-gray-300">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Manage Airlines
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
