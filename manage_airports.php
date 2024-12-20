<?php
// manage_airports.php
session_start();
require 'config.php';
require 'csrf_helper.php';

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

// Handle Form Submissions
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['add_airport'])) {
        // Validate CSRF token
        if (!isset($_POST['csrf_token']) || !validateCsrfToken($_POST['csrf_token'])) {
            $errors[] = "Invalid CSRF token.";
        } else {
            // Handle Adding Airport
            $airport_name = trim($_POST['airport_name']);
            $airport_location = trim($_POST['airport_location']);
            $airport_city = trim($_POST['airport_city']);
            $airport_country = trim($_POST['airport_country']);
            $airport_iata = strtoupper(trim($_POST['airport_iata']));
    
            // Validate Inputs
            if (empty($airport_name)) {
                $errors[] = "Airport name is required.";
            }
            if (empty($airport_location)) {
                $errors[] = "Airport location is required.";
            }
            if (empty($airport_city)) {
                $errors[] = "Airport city is required.";
            }
            if (empty($airport_country)) {
                $errors[] = "Airport country is required.";
            }
            if (empty($airport_iata) || strlen($airport_iata) != 3) {
                $errors[] = "IATA code must be exactly 3 characters.";
            }
    
            // Insert into Database if no errors
            if (empty($errors)) {
                $stmt = $conn->prepare("INSERT INTO airports (name, location, city, country, iata_code) VALUES (?, ?, ?, ?, ?)");
                if ($stmt) {
                    $stmt->bind_param("sssss", $airport_name, $airport_location, $airport_city, $airport_country, $airport_iata);
                    if ($stmt->execute()) {
                        $success[] = "Airport added successfully.";
                    } else {
                        if ($conn->errno == 1062) { // Duplicate entry
                            $errors[] = "IATA code already exists.";
                        } else {
                            $errors[] = "Error adding airport: " . $stmt->error;
                        }
                    }
                    $stmt->close();
                } else {
                    $errors[] = "Error preparing statement: " . $conn->error;
                }
            }
        }
    } elseif (isset($_POST['delete_airport'])) {
        // Validate CSRF token
        if (!isset($_POST['csrf_token']) || !validateCsrfToken($_POST['csrf_token'])) {
            $errors[] = "Invalid CSRF token.";
        } else {
            // Handle Deleting Airport
            $airport_id = intval($_POST['airport_id']);
    
            // Delete the airport
            $stmt = $conn->prepare("DELETE FROM airports WHERE airport_id = ?");
            if ($stmt) {
                $stmt->bind_param("i", $airport_id);
                if ($stmt->execute()) {
                    if ($stmt->affected_rows > 0) {
                        $success[] = "Airport deleted successfully.";
                    } else {
                        $errors[] = "Airport not found.";
                    }
                } else {
                    // Check for foreign key constraint violation
                    if ($conn->errno == 1451) { // Cannot delete or update a parent row: a foreign key constraint fails
                        $errors[] = "Cannot delete airport. It is associated with existing routes or flights.";
                    } else {
                        $errors[] = "Error deleting airport: " . $stmt->error;
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
    header("Location: manage_airports.php");
    exit();
}

// Pagination Settings
$limit = 5; // Number of entries per page
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $limit;

// Fetch all airports for display and search
$filtered_airports = [];
$search_airport_name = isset($_GET['search_airport_name']) ? trim($_GET['search_airport_name']) : '';
$search_airport_location = isset($_GET['search_airport_location']) ? trim($_GET['search_airport_location']) : '';
$search_airport_city = isset($_GET['search_airport_city']) ? trim($_GET['search_airport_city']) : '';
$search_airport_country = isset($_GET['search_airport_country']) ? trim($_GET['search_airport_country']) : '';
$search_airport_iata = isset($_GET['search_airport_iata']) ? strtoupper(trim($_GET['search_airport_iata'])) : '';

// Build query based on search criteria
$query = "SELECT airport_id, name, location, city, country, iata_code FROM airports WHERE 1=1";
$params = [];
$types = "";

if (!empty($search_airport_name)) {
    $query .= " AND name LIKE ?";
    $params[] = "%" . $search_airport_name . "%";
    $types .= "s";
}
if (!empty($search_airport_location)) {
    $query .= " AND location LIKE ?";
    $params[] = "%" . $search_airport_location . "%";
    $types .= "s";
}
if (!empty($search_airport_city)) {
    $query .= " AND city LIKE ?";
    $params[] = "%" . $search_airport_city . "%";
    $types .= "s";
}
if (!empty($search_airport_country)) {
    $query .= " AND country LIKE ?";
    $params[] = "%" . $search_airport_country . "%";
    $types .= "s";
}
if (!empty($search_airport_iata)) {
    $query .= " AND iata_code LIKE ?";
    $params[] = "%" . $search_airport_iata . "%";
    $types .= "s";
}

// Count total records for pagination
$count_query = "SELECT COUNT(*) as total FROM airports WHERE 1=1";
$count_params = [];
$count_types = "";

if (!empty($search_airport_name)) {
    $count_query .= " AND name LIKE ?";
    $count_params[] = "%" . $search_airport_name . "%";
    $count_types .= "s";
}
if (!empty($search_airport_location)) {
    $count_query .= " AND location LIKE ?";
    $count_params[] = "%" . $search_airport_location . "%";
    $count_types .= "s";
}
if (!empty($search_airport_city)) {
    $count_query .= " AND city LIKE ?";
    $count_params[] = "%" . $search_airport_city . "%";
    $count_types .= "s";
}
if (!empty($search_airport_country)) {
    $count_query .= " AND country LIKE ?";
    $count_params[] = "%" . $search_airport_country . "%";
    $count_types .= "s";
}
if (!empty($search_airport_iata)) {
    $count_query .= " AND iata_code LIKE ?";
    $count_params[] = "%" . $search_airport_iata . "%";
    $count_types .= "s";
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

// Append LIMIT clause for pagination
$query .= " ORDER BY airport_id DESC LIMIT ? OFFSET ?";
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
    while ($row = $result->fetch_assoc()) {
        $filtered_airports[] = $row;
    }
    $stmt->close();
} else {
    $errors[] = "Error fetching airports: " . $conn->error;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Airports - Flight Ticket Booking System</title>
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
                        <!-- CSRF Token -->
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
            <form action="logout.php" method="POST" class="px-4 py-2">
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

        <!-- Add Airport Form -->
        <div class="bg-white shadow-md rounded-lg p-6 mb-8">
            <h2 class="text-2xl font-semibold mb-4 text-indigo-600">Add New Airport</h2>
            <form method="POST" action="manage_airports.php" class="space-y-4">
                <!-- CSRF Token -->
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <input type="hidden" name="add_airport" value="1">

                <div>
                    <label for="airport_name" class="block text-sm font-medium text-gray-700">Airport Name<span class="text-red-500">*</span></label>
                    <input type="text" id="airport_name" name="airport_name" required value="<?php echo isset($_POST['airport_name']) ? htmlspecialchars($_POST['airport_name']) : ''; ?>" class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                </div>

                <div>
                    <label for="airport_location" class="block text-sm font-medium text-gray-700">Location<span class="text-red-500">*</span></label>
                    <input type="text" id="airport_location" name="airport_location" required value="<?php echo isset($_POST['airport_location']) ? htmlspecialchars($_POST['airport_location']) : ''; ?>" class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                </div>

                <div>
                    <label for="airport_city" class="block text-sm font-medium text-gray-700">City<span class="text-red-500">*</span></label>
                    <input type="text" id="airport_city" name="airport_city" required value="<?php echo isset($_POST['airport_city']) ? htmlspecialchars($_POST['airport_city']) : ''; ?>" class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                </div>

                <div>
                    <label for="airport_country" class="block text-sm font-medium text-gray-700">Country<span class="text-red-500">*</span></label>
                    <input type="text" id="airport_country" name="airport_country" required value="<?php echo isset($_POST['airport_country']) ? htmlspecialchars($_POST['airport_country']) : ''; ?>" class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                </div>

                <div>
                    <label for="airport_iata" class="block text-sm font-medium text-gray-700">IATA Code (3 Characters)<span class="text-red-500">*</span></label>
                    <input type="text" id="airport_iata" name="airport_iata" maxlength="3" required value="<?php echo isset($_POST['airport_iata']) ? htmlspecialchars($_POST['airport_iata']) : ''; ?>" class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 uppercase">
                </div>

                <div>
                    <button type="submit" class="w-full px-4 py-2 bg-indigo-600 text-white font-medium rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500">Add Airport</button>
                </div>
            </form>
        </div>

        <!-- Airport Search Form -->
        <div class="bg-white shadow-md rounded-lg p-6 mb-8">
            <h2 class="text-2xl font-semibold mb-4 text-indigo-600">Search Airports</h2>
            <form method="GET" action="manage_airports.php" class="space-y-4">
                <div>
                    <label for="search_airport_name" class="block text-sm font-medium text-gray-700">Name</label>
                    <input type="text" id="search_airport_name" name="search_airport_name" value="<?php echo htmlspecialchars($search_airport_name); ?>" class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                </div>

                <div>
                    <label for="search_airport_location" class="block text-sm font-medium text-gray-700">Location</label>
                    <input type="text" id="search_airport_location" name="search_airport_location" value="<?php echo htmlspecialchars($search_airport_location); ?>" class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                </div>

                <div>
                    <label for="search_airport_city" class="block text-sm font-medium text-gray-700">City</label>
                    <input type="text" id="search_airport_city" name="search_airport_city" value="<?php echo htmlspecialchars($search_airport_city); ?>" class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                </div>

                <div>
                    <label for="search_airport_country" class="block text-sm font-medium text-gray-700">Country</label>
                    <input type="text" id="search_airport_country" name="search_airport_country" value="<?php echo htmlspecialchars($search_airport_country); ?>" class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                </div>

                <div>
                    <label for="search_airport_iata" class="block text-sm font-medium text-gray-700">IATA Code</label>
                    <input type="text" id="search_airport_iata" name="search_airport_iata" maxlength="3" value="<?php echo htmlspecialchars($search_airport_iata); ?>" class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 uppercase">
                </div>

                <div class="flex items-center space-x-4">
                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white font-medium rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500">Search Airports</button>
                    <a href="manage_airports.php" class="px-4 py-2 bg-gray-200 text-gray-700 font-medium rounded-md hover:bg-gray-300">Reset</a>
                </div>
            </form>
        </div>

        <!-- Existing Airports Table -->
        <div class="bg-white shadow-md rounded-lg p-6">
            <h2 class="text-2xl font-semibold mb-4 text-indigo-600">Existing Airports</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Airport ID</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Location</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">City</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Country</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">IATA Code</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Edit</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Delete</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php
                        if (!empty($filtered_airports)) {
                            foreach ($filtered_airports as $airport) {
                                echo "<tr>";
                                echo "<td class='px-6 py-4 whitespace-nowrap'>" . htmlspecialchars($airport['airport_id']) . "</td>";
                                echo "<td class='px-6 py-4 whitespace-nowrap'>" . htmlspecialchars($airport['name']) . "</td>";
                                echo "<td class='px-6 py-4 whitespace-nowrap'>" . htmlspecialchars($airport['location']) . "</td>";
                                echo "<td class='px-6 py-4 whitespace-nowrap'>" . htmlspecialchars($airport['city']) . "</td>";
                                echo "<td class='px-6 py-4 whitespace-nowrap'>" . htmlspecialchars($airport['country']) . "</td>";
                                echo "<td class='px-6 py-4 whitespace-nowrap'>" . htmlspecialchars($airport['iata_code']) . "</td>";
                                // Edit Button
                                echo "<td class='px-6 py-4 whitespace-nowrap'>";
                                echo "<a href='edit_airport.php?airport_id=" . urlencode($airport['airport_id']) . "' class='inline-flex items-center px-3 py-1 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700'>Edit</a>";
                                echo "</td>";
                                // Delete Button as Form
                                echo "<td class='px-6 py-4 whitespace-nowrap'>";
                                echo "<form method='POST' action='manage_airports.php' onsubmit=\"return confirm('Are you sure you want to delete this airport?');\">";
                                echo "<input type='hidden' name='csrf_token' value='" . htmlspecialchars($csrf_token) . "'>";
                                echo "<input type='hidden' name='delete_airport' value='1'>";
                                echo "<input type='hidden' name='airport_id' value='" . htmlspecialchars($airport['airport_id']) . "'>";
                                echo "<button type='submit' class='inline-flex items-center px-3 py-1 bg-red-600 text-white text-sm font-medium rounded-md hover:bg-red-700'>Delete</button>";
                                echo "</form>";
                                echo "</td>";
                                echo "</tr>";
                            }
                        } else {
                            echo "<tr><td colspan='8' class='px-6 py-4 whitespace-nowrap text-center text-gray-500'>No airports found.</td></tr>";
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
                            <a href="?page=<?php echo $page - 1; ?><?php echo (!empty($search_airport_name)) ? '&search_airport_name=' . urlencode($search_airport_name) : ''; ?><?php echo (!empty($search_airport_location)) ? '&search_airport_location=' . urlencode($search_airport_location) : ''; ?><?php echo (!empty($search_airport_city)) ? '&search_airport_city=' . urlencode($search_airport_city) : ''; ?><?php echo (!empty($search_airport_country)) ? '&search_airport_country=' . urlencode($search_airport_country) : ''; ?><?php echo (!empty($search_airport_iata)) ? '&search_airport_iata=' . urlencode($search_airport_iata) : ''; ?>" class="px-3 py-2 ml-0 leading-tight text-gray-500 bg-white border border-gray-300 rounded-l-lg hover:bg-gray-100 hover:text-gray-700">Previous</a>
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
                                <a href="?page=<?php echo $i; ?><?php echo (!empty($search_airport_name)) ? '&search_airport_name=' . urlencode($search_airport_name) : ''; ?><?php echo (!empty($search_airport_location)) ? '&search_airport_location=' . urlencode($search_airport_location) : ''; ?><?php echo (!empty($search_airport_city)) ? '&search_airport_city=' . urlencode($search_airport_city) : ''; ?><?php echo (!empty($search_airport_country)) ? '&search_airport_country=' . urlencode($search_airport_country) : ''; ?><?php echo (!empty($search_airport_iata)) ? '&search_airport_iata=' . urlencode($search_airport_iata) : ''; ?>" class="px-3 py-2 leading-tight text-gray-500 bg-white border border-gray-300 hover:bg-gray-100 hover:text-gray-700"><?php echo $i; ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>

                        <!-- Next Page Button -->
                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?php echo $page + 1; ?><?php echo (!empty($search_airport_name)) ? '&search_airport_name=' . urlencode($search_airport_name) : ''; ?><?php echo (!empty($search_airport_location)) ? '&search_airport_location=' . urlencode($search_airport_location) : ''; ?><?php echo (!empty($search_airport_city)) ? '&search_airport_city=' . urlencode($search_airport_city) : ''; ?><?php echo (!empty($search_airport_country)) ? '&search_airport_country=' . urlencode($search_airport_country) : ''; ?><?php echo (!empty($search_airport_iata)) ? '&search_airport_iata=' . urlencode($search_airport_iata) : ''; ?>" class="px-3 py-2 leading-tight text-gray-500 bg-white border border-gray-300 rounded-r-lg hover:bg-gray-100 hover:text-gray-700">Next</a>
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
