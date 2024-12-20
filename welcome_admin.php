<?php
// welcome_admin.php
session_start();
require 'config.php';
require 'csrf_helper.php'; // Include CSRF helper for token generation

// Generate a CSRF token for this session
$csrf_token = generateCsrfToken();

// Check if user is logged in and is an Admin
if (!isset($_SESSION['user_id']) || $_SESSION['role_name'] != 'Admin') {
    header("Location: login.php");
    exit();
}

// Retrieve admin information from the database using prepared statements
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT user_id, first_name, last_name, address, registration_date, email, phone_num 
                        FROM users 
                        WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 1) {
    $admin = $result->fetch_assoc();
} else {
    echo "Admin not found.";
    exit();
}

// Function to get counts with prepared statements
function getCount($conn, $query, $types = "", $params = []) {
    $stmt = $conn->prepare($query);
    if ($stmt === false) {
        // Log the error or handle it as needed
        return 0;
    }
    if ($types && $params) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        return intval($row['count']);
    }
    return 0;
}

// Function to get flight counts by status
function getFlightStatusCounts($conn) {
    $statuses = ['Scheduled', 'Delayed', 'Cancelled', 'Completed'];
    $counts = [];
    foreach ($statuses as $status) {
        $query = "SELECT COUNT(*) AS count FROM flights WHERE status = ?";
        $counts[$status] = getCount($conn, $query, "s", [$status]);
    }
    return $counts;
}

// Fetch various counts

// Corrected Query: Join users and roles tables
$user_count = getCount(
    $conn,
    "SELECT COUNT(*) AS count FROM users u JOIN roles r ON u.role_id = r.role_id WHERE r.role_name = ?",
    "s",
    ["User"]
);

// Other counts without JOINs
$airline_count = getCount($conn, "SELECT COUNT(*) AS count FROM airlines");
$aircraft_count = getCount($conn, "SELECT COUNT(*) AS count FROM aircrafts");
$airport_count = getCount($conn, "SELECT COUNT(*) AS count FROM airports");
$route_count = getCount($conn, "SELECT COUNT(*) AS count FROM routes");

// Flight Status Counts
$flight_status_counts = getFlightStatusCounts($conn);

// Calculate Upcoming Flights (Scheduled + Delayed)
$upcoming_flights = $flight_status_counts['Scheduled'] + $flight_status_counts['Delayed'];

// Bookings Counts
$booking_confirmed = getCount($conn, "SELECT COUNT(*) AS count FROM bookings WHERE status = 'Confirmed'");
$booking_pending = getCount($conn, "SELECT COUNT(*) AS count FROM bookings WHERE status = 'Pending'");
$booking_cancelled = getCount($conn, "SELECT COUNT(*) AS count FROM bookings WHERE status = 'Cancelled'");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome Admin - Flight Ticket Booking System</title>
    <link rel="icon" type="image/x-icon" href="fav.png">
    <link rel="icon" type="image/x-icon" href="fav.png">
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Custom styles for dropdown */
        .dropdown:hover .dropdown-menu {
            display: block;
        }
        /* Ensure dropdown-menu is above other elements */
        .dropdown-menu {
            z-index: 10;
        }
    </style>
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
                        <!-- Include CSRF token as a hidden input -->
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
            <form action="logout.php" method="POST" class="px-4 py-2 inline">
                <!-- Include CSRF token as a hidden input -->
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <button type="submit" class="w-full text-left text-gray-700 hover:bg-indigo-50">Logout</button>
            </form>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="flex-grow container mx-auto px-4 py-8 max-w-7xl">
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="mb-6 px-4 py-3 rounded-md bg-green-100 text-green-700">
            <div class="flex justify-between items-center">
                <span><?php echo htmlspecialchars($_SESSION['success_message']); ?></span>
                <button onclick="this.parentElement.parentElement.style.display='none';" class="text-green-700 focus:outline-none">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
    <?php unset($_SESSION['success_message']); // Only unset here after displaying ?>
    <?php endif; ?>

        <!-- Admin Information -->
        <div class="bg-white shadow-md rounded-lg p-6 mb-8">
            <!-- Flex container for heading and Edit button -->
            <div class="flex flex-col md:flex-row justify-between items-center mb-6">
                <h2 class="text-2xl font-semibold text-indigo-600 mb-4 md:mb-0">Welcome, <?php echo htmlspecialchars($admin['first_name'] . ' ' . $admin['last_name']); ?>!</h2>
                <a href="edit_user.php?user_id=<?php echo urlencode($user_id); ?>" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                    <i class="fas fa-user-edit mr-2"></i> Edit Account
                </a>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full bg-white">
                    <thead>
                        <tr>
                            <th class="px-6 py-3 border-b-2 border-gray-300 text-left text-sm font-semibold text-gray-700 uppercase tracking-wider">Field</th>
                            <th class="px-6 py-3 border-b-2 border-gray-300 text-left text-sm font-semibold text-gray-700 uppercase tracking-wider">Information</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="hover:bg-gray-100">
                            <td class="px-6 py-4 whitespace-no-wrap border-b border-gray-200">User ID</td>
                            <td class="px-6 py-4 whitespace-no-wrap border-b border-gray-200"><?php echo htmlspecialchars($admin['user_id']); ?></td>
                        </tr>
                        <tr class="hover:bg-gray-100">
                            <td class="px-6 py-4 whitespace-no-wrap border-b border-gray-200">First Name</td>
                            <td class="px-6 py-4 whitespace-no-wrap border-b border-gray-200"><?php echo htmlspecialchars($admin['first_name']); ?></td>
                        </tr>
                        <tr class="hover:bg-gray-100">
                            <td class="px-6 py-4 whitespace-no-wrap border-b border-gray-200">Last Name</td>
                            <td class="px-6 py-4 whitespace-no-wrap border-b border-gray-200"><?php echo htmlspecialchars($admin['last_name']); ?></td>
                        </tr>
                        <tr class="hover:bg-gray-100">
                            <td class="px-6 py-4 whitespace-no-wrap border-b border-gray-200">Address</td>
                            <td class="px-6 py-4 whitespace-no-wrap border-b border-gray-200"><?php echo htmlspecialchars($admin['address']); ?></td>
                        </tr>
                        <tr class="hover:bg-gray-100">
                            <td class="px-6 py-4 whitespace-no-wrap border-b border-gray-200">Registration Date</td>
                            <td class="px-6 py-4 whitespace-no-wrap border-b border-gray-200"><?php echo htmlspecialchars($admin['registration_date']); ?></td>
                        </tr>
                        <tr class="hover:bg-gray-100">
                            <td class="px-6 py-4 whitespace-no-wrap border-b border-gray-200">Email</td>
                            <td class="px-6 py-4 whitespace-no-wrap border-b border-gray-200"><?php echo htmlspecialchars($admin['email']); ?></td>
                        </tr>
                        <tr class="hover:bg-gray-100">
                            <td class="px-6 py-4 whitespace-no-wrap border-b border-gray-200">Phone Number</td>
                            <td class="px-6 py-4 whitespace-no-wrap border-b border-gray-200"><?php echo htmlspecialchars($admin['phone_num']); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- System Overview -->
        <div class="bg-white shadow-md rounded-lg p-6 mb-8">
            <h2 class="text-xl font-semibold mb-6 text-indigo-600">System Overview</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
                <!-- Total Users -->
                <div class="bg-blue-100 border-l-4 border-blue-500 text-blue-700 p-4 rounded">
                    <div class="flex items-center">
                        <div class="p-3 bg-blue-500 rounded-full text-white">
                            <i class="fas fa-users fa-2x"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm">Total Users</p>
                            <p class="text-2xl font-bold"><?php echo htmlspecialchars($user_count); ?></p>
                            <a href="list_users.php" class="text-blue-500 hover:text-blue-700">View Users</a>
                        </div>
                    </div>
                </div>
                <!-- Total Airlines -->
                <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded">
                    <div class="flex items-center">
                        <div class="p-3 bg-green-500 rounded-full text-white">
                            <i class="fas fa-plane fa-2x"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm">Total Airlines</p>
                            <p class="text-2xl font-bold"><?php echo htmlspecialchars($airline_count); ?></p>
                            <a href="manage_airlines.php" class="text-green-500 hover:text-green-700">Manage Airlines</a>
                        </div>
                    </div>
                </div>
                <!-- Total Aircrafts -->
                <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 rounded">
                    <div class="flex items-center">
                        <div class="p-3 bg-yellow-500 rounded-full text-white">
                            <i class="fas fa-fighter-jet fa-2x"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm">Total Aircrafts</p>
                            <p class="text-2xl font-bold"><?php echo htmlspecialchars($aircraft_count); ?></p>
                            <a href="manage_aircrafts.php" class="text-yellow-500 hover:text-yellow-700">Manage Aircrafts</a>
                        </div>
                    </div>
                </div>
                <!-- Total Airports -->
                <div class="bg-pink-100 border-l-4 border-pink-500 text-pink-700 p-4 rounded">
                    <div class="flex items-center">
                        <div class="p-3 bg-pink-500 rounded-full text-white">
                            <i class="fas fa-plane-arrival fa-2x"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm">Total Airports</p>
                            <p class="text-2xl font-bold"><?php echo htmlspecialchars($airport_count); ?></p>
                            <a href="manage_airports.php" class="text-pink-500 hover:text-pink-700">Manage Airports</a>
                        </div>
                    </div>
                </div>
                <!-- Total Routes -->
                <div class="bg-purple-100 border-l-4 border-purple-500 text-purple-700 p-4 rounded">
                    <div class="flex items-center">
                        <div class="p-3 bg-purple-500 rounded-full text-white">
                            <i class="fas fa-route fa-2x"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm">Total Routes</p>
                            <p class="text-2xl font-bold"><?php echo htmlspecialchars($route_count); ?></p>
                            <a href="manage_routes.php" class="text-purple-500 hover:text-purple-700">Manage Routes</a>
                        </div>
                    </div>
                </div>
                <!-- Upcoming Flights -->
                <div class="bg-blue-100 border-l-4 border-blue-500 text-blue-700 p-4 rounded">
                    <div class="flex items-center">
                        <div class="p-3 bg-blue-500 rounded-full text-white">
                            <i class="fas fa-plane-departure fa-2x"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm">Upcoming Flights</p>
                            <p class="text-2xl font-bold"><?php echo htmlspecialchars($upcoming_flights); ?></p>
                            <!-- Updated Link with Status Filters -->
                            <a href="manage_flights.php?status[]=Scheduled&status[]=Delayed" class="text-blue-500 hover:text-blue-700">View Upcoming</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Bookings Overview -->
        <div class="bg-white shadow-md rounded-lg p-6 mb-8">
            <h2 class="text-xl font-semibold mb-6 text-indigo-600">Bookings Overview</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-6">
                <!-- Confirmed Bookings -->
                <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded">
                    <div class="flex items-center">
                        <div class="p-3 bg-green-500 rounded-full text-white">
                            <i class="fas fa-check-circle fa-2x"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm">Confirmed Bookings</p>
                            <p class="text-2xl font-bold"><?php echo htmlspecialchars($booking_confirmed); ?></p>
                            <a href="admin_confirmed_bookings.php" class="text-green-500 hover:text-green-700">View Confirmed</a>
                        </div>
                    </div>
                </div>
                <!-- Pending Bookings -->
                <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 rounded">
                    <div class="flex items-center">
                        <div class="p-3 bg-yellow-500 rounded-full text-white">
                            <i class="fas fa-hourglass-half fa-2x"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm">Pending Bookings</p>
                            <p class="text-2xl font-bold"><?php echo htmlspecialchars($booking_pending); ?></p>
                            <a href="admin_pending_bookings.php" class="text-yellow-500 hover:text-yellow-700">View Pending</a>
                        </div>
                    </div>
                </div>
                <!-- Cancelled Bookings -->
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded">
                    <div class="flex items-center">
                        <div class="p-3 bg-red-500 rounded-full text-white">
                            <i class="fas fa-times-circle fa-2x"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm">Cancelled Bookings</p>
                            <p class="text-2xl font-bold"><?php echo htmlspecialchars($booking_cancelled); ?></p>
                            <a href="admin_cancelled_bookings.php" class="text-red-500 hover:text-red-700">View Cancelled</a>
                        </div>
                    </div>
                </div>
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
