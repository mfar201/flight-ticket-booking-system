<?php
// manage_airlines.php
session_start();
require 'config.php';
require 'csrf_helper.php'; // Include CSRF helper functions

// Check if user is logged in and is an Admin
if (!isset($_SESSION['user_id']) || $_SESSION['role_name'] != 'Admin') {
    header("Location: login.php");
    exit();
}

// Initialize variables for error and success messages
$errors = isset($_SESSION['errors']) ? $_SESSION['errors'] : [];
$success = isset($_SESSION['success']) ? $_SESSION['success'] : [];

// Unset session messages after fetching
unset($_SESSION['errors']);
unset($_SESSION['success']);

// Handle Form Submissions
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate CSRF Token
    if (!isset($_POST['csrf_token']) || !validateCsrfToken($_POST['csrf_token'])) {
        $errors[] = 'Invalid CSRF token.';
    } else {
        if (isset($_POST['add_airline'])) {
            // Handle Adding Airline
            $airline_name = trim($_POST['airline_name']);
            $airline_country = trim($_POST['airline_country']);
            $airline_iata = strtoupper(trim($_POST['airline_iata']));

            // Validate Inputs
            if (empty($airline_name)) {
                $errors[] = "Airline name is required.";
            }
            if (empty($airline_iata) || strlen($airline_iata) != 2) {
                $errors[] = "IATA code must be exactly 2 characters.";
            }
            // Add this validation for the "Country" field
            if (empty($airline_country)) {
                $errors[] = "Country is required.";
            }

            // Insert into Database if no errors
            if (empty($errors)) {
                $stmt = $conn->prepare("INSERT INTO airlines (name, country, iata_code) VALUES (?, ?, ?)");
                if ($stmt) {
                    $stmt->bind_param("sss", $airline_name, $airline_country, $airline_iata);
                    if ($stmt->execute()) {
                        $success[] = "Airline added successfully.";
                    } else {
                        if ($conn->errno == 1062) { // Duplicate entry
                            $errors[] = "IATA code already exists.";
                        } else {
                            $errors[] = "Error adding airline: " . $stmt->error;
                        }
                    }
                    $stmt->close();
                } else {
                    $errors[] = "Error preparing statement: " . $conn->error;
                }
            }
        }
    }
}

// Pagination Settings
$limit = 5; // Number of entries per page
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $limit;

// Fetch all airlines for display and search
$filtered_airlines = [];
$search_airline_name = isset($_GET['search_airline_name']) ? trim($_GET['search_airline_name']) : '';
$search_airline_country = isset($_GET['search_airline_country']) ? trim($_GET['search_airline_country']) : '';
$search_airline_iata = isset($_GET['search_airline_iata']) ? strtoupper(trim($_GET['search_airline_iata'])) : '';

// Build query based on search criteria
$query = "SELECT airline_id, name, country, iata_code FROM airlines WHERE 1=1";
$params = [];
$types = "";

// Apply search filters
if (!empty($search_airline_name)) {
    $query .= " AND name LIKE ?";
    $params[] = "%" . $search_airline_name . "%";
    $types .= "s";
}
if (!empty($search_airline_country)) {
    $query .= " AND country LIKE ?";
    $params[] = "%" . $search_airline_country . "%";
    $types .= "s";
}
if (!empty($search_airline_iata)) {
    $query .= " AND iata_code LIKE ?";
    $params[] = "%" . $search_airline_iata . "%";
    $types .= "s";
}

// Get total number of records for pagination
$count_query = "SELECT COUNT(*) as total FROM airlines WHERE 1=1";
$count_params = [];
$count_types = "";

// Apply same search filters to count query
if (!empty($search_airline_name)) {
    $count_query .= " AND name LIKE ?";
    $count_params[] = "%" . $search_airline_name . "%";
    $count_types .= "s";
}
if (!empty($search_airline_country)) {
    $count_query .= " AND country LIKE ?";
    $count_params[] = "%" . $search_airline_country . "%";
    $count_types .= "s";
}
if (!empty($search_airline_iata)) {
    $count_query .= " AND iata_code LIKE ?";
    $count_params[] = "%" . $search_airline_iata . "%";
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
$query .= " ORDER BY airline_id DESC LIMIT ? OFFSET ?";
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
        $filtered_airlines[] = $row;
    }
    $stmt->close();
} else {
    $errors[] = "Error searching airlines: " . $conn->error;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Airlines - Flight Ticket Booking System</title>
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

        <!-- Add Airline Form -->
        <div class="bg-white shadow-md rounded-lg p-6 mb-8">
            <h2 class="text-2xl font-semibold mb-4 text-indigo-600">Add New Airline</h2>
            <form method="POST" action="manage_airlines.php" class="space-y-4">
                <input type="hidden" name="add_airline" value="1">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCsrfToken()); ?>">

                <div>
                    <label for="airline_name" class="block text-sm font-medium text-gray-700">Airline Name<span class="text-red-500">*</span></label>
                    <input type="text" id="airline_name" name="airline_name" required value="<?php echo isset($_POST['airline_name']) ? htmlspecialchars($_POST['airline_name']) : ''; ?>" class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                </div>

                <div>
                    <label for="airline_country" class="block text-sm font-medium text-gray-700">Country<span class="text-red-500">*</span></label>
                    <input type="text" id="airline_country" name="airline_country" required value="<?php echo isset($_POST['airline_country']) ? htmlspecialchars($_POST['airline_country']) : ''; ?>" class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                </div>


                <div>
                    <label for="airline_iata" class="block text-sm font-medium text-gray-700">IATA Code (2 Characters)<span class="text-red-500">*</span></label>
                    <input type="text" id="airline_iata" name="airline_iata" maxlength="2" required value="<?php echo isset($_POST['airline_iata']) ? htmlspecialchars($_POST['airline_iata']) : ''; ?>" class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 uppercase">
                </div>

                <div>
                    <button type="submit" class="w-full px-4 py-2 bg-indigo-600 text-white font-medium rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500">Add Airline</button>
                </div>
            </form>
        </div>

        <!-- Airline Search Form -->
        <div class="bg-white shadow-md rounded-lg p-6 mb-8">
            <h2 class="text-2xl font-semibold mb-4 text-indigo-600">Search Airlines</h2>
            <form method="GET" action="manage_airlines.php" class="space-y-4">
                <div>
                    <label for="search_airline_name" class="block text-sm font-medium text-gray-700">Name</label>
                    <input type="text" id="search_airline_name" name="search_airline_name" value="<?php echo htmlspecialchars($search_airline_name); ?>" class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                </div>

                <div>
                    <label for="search_airline_country" class="block text-sm font-medium text-gray-700">Country</label>
                    <input type="text" id="search_airline_country" name="search_airline_country" value="<?php echo htmlspecialchars($search_airline_country); ?>" class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                </div>

                <div>
                    <label for="search_airline_iata" class="block text-sm font-medium text-gray-700">IATA Code</label>
                    <input type="text" id="search_airline_iata" name="search_airline_iata" maxlength="2" value="<?php echo htmlspecialchars($search_airline_iata); ?>" class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 uppercase">
                </div>

                <div class="flex items-center space-x-4">
                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white font-medium rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500">Search Airlines</button>
                    <a href="manage_airlines.php" class="px-4 py-2 bg-gray-200 text-gray-700 font-medium rounded-md hover:bg-gray-300">Reset</a>
                </div>
            </form>
        </div>

        <!-- Existing Airlines Table -->
        <div class="bg-white shadow-md rounded-lg p-6">
            <h2 class="text-2xl font-semibold mb-4 text-indigo-600">Existing Airlines</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Airline ID</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Country</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">IATA Code</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Edit</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Delete</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php
                        if (!empty($filtered_airlines)) {
                            foreach ($filtered_airlines as $airline) {
                                echo "<tr>";
                                echo "<td class='px-6 py-4 whitespace-nowrap'>" . htmlspecialchars($airline['airline_id']) . "</td>";
                                echo "<td class='px-6 py-4 whitespace-nowrap'>" . htmlspecialchars($airline['name']) . "</td>";
                                echo "<td class='px-6 py-4 whitespace-nowrap'>" . htmlspecialchars($airline['country']) . "</td>";
                                echo "<td class='px-6 py-4 whitespace-nowrap'>" . htmlspecialchars($airline['iata_code']) . "</td>";
                                // Convert Edit and Delete links to buttons
                                echo "<td class='px-6 py-4 whitespace-nowrap'>";
                                echo "<a href='edit_airline.php?airline_id=" . urlencode($airline['airline_id']) . "' class='inline-flex items-center px-3 py-1 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700'>Edit</a>";
                                echo "</td>";
                                echo "<td class='px-6 py-4 whitespace-nowrap'>";
                                echo "<form method='POST' action='delete_airline.php' class='inline-block'>";
                                echo "<input type='hidden' name='airline_id' value='" . htmlspecialchars($airline['airline_id']) . "'>";
                                echo "<input type='hidden' name='csrf_token' value='" . htmlspecialchars(generateCsrfToken()) . "'>";
                                echo "<button type='submit' class='inline-flex items-center px-3 py-1 bg-red-600 text-white text-sm font-medium rounded-md hover:bg-red-700' onclick=\"return confirm('Are you sure you want to delete this airline?');\">Delete</button>";
                                echo "</form>";
                                echo "</td>";
                                echo "</tr>";
                            }
                        } else {
                            echo "<tr><td colspan='6' class='px-6 py-4 whitespace-nowrap text-center text-gray-500'>No airlines found.</td></tr>";
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
                            <a href="?page=<?php echo $page - 1; ?><?php echo (!empty($search_airline_name)) ? '&search_airline_name=' . urlencode($search_airline_name) : ''; ?><?php echo (!empty($search_airline_country)) ? '&search_airline_country=' . urlencode($search_airline_country) : ''; ?><?php echo (!empty($search_airline_iata)) ? '&search_airline_iata=' . urlencode($search_airline_iata) : ''; ?>" class="px-3 py-2 ml-0 leading-tight text-gray-500 bg-white border border-gray-300 rounded-l-lg hover:bg-gray-100 hover:text-gray-700">Previous</a>
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
                                <a href="?page=<?php echo $i; ?><?php echo (!empty($search_airline_name)) ? '&search_airline_name=' . urlencode($search_airline_name) : ''; ?><?php echo (!empty($search_airline_country)) ? '&search_airline_country=' . urlencode($search_airline_country) : ''; ?><?php echo (!empty($search_airline_iata)) ? '&search_airline_iata=' . urlencode($search_airline_iata) : ''; ?>" class="px-3 py-2 leading-tight text-gray-500 bg-white border border-gray-300 hover:bg-gray-100 hover:text-gray-700"><?php echo $i; ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>

                        <!-- Next Page Button -->
                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?php echo $page + 1; ?><?php echo (!empty($search_airline_name)) ? '&search_airline_name=' . urlencode($search_airline_name) : ''; ?><?php echo (!empty($search_airline_country)) ? '&search_airline_country=' . urlencode($search_airline_country) : ''; ?><?php echo (!empty($search_airline_iata)) ? '&search_airline_iata=' . urlencode($search_airline_iata) : ''; ?>" class="px-3 py-2 leading-tight text-gray-500 bg-white border border-gray-300 rounded-r-lg hover:bg-gray-100 hover:text-gray-700">Next</a>
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
