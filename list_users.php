<?php
// list_users.php
session_start();
require 'config.php';
require 'csrf_helper.php';

// Check if user is logged in and is an Admin
if (!isset($_SESSION['user_id']) || $_SESSION['role_name'] != 'Admin') {
    header("Location: login.php");
    exit();
}

// Initialize variables
$search = "";
$search_query = "";
$search_param = [];

// Handle search functionality
if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['search'])) {
    $search = trim($_GET['search']);
    if (!empty($search)) {
        // Prepare search query with placeholders
        $search_query = "WHERE (users.first_name LIKE ? 
                             OR users.last_name LIKE ? 
                             OR users.email LIKE ?
                             OR roles_manager.role_name LIKE ?)";
        $search_param = ["%$search%", "%$search%", "%$search%", "%$search%"];
    }
}

// Pagination settings
$limit = 10; // Users per page
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int) $_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Count total users for pagination
$count_sql = "SELECT COUNT(*) AS count
              FROM users
              JOIN roles AS roles_user ON users.role_id = roles_user.role_id
              LEFT JOIN roles AS roles_manager ON users.managed_by = roles_manager.role_id
              $search_query";

$count_stmt = $conn->prepare($count_sql);
if (!empty($search_query)) {
    // Bind parameters for search
    $types = str_repeat("s", count($search_param));
    $count_stmt->bind_param($types, ...$search_param);
}
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total_users = $count_result->fetch_assoc()['count'];
$total_pages = ceil($total_users / $limit);
$count_stmt->close();

// Fetch users for the current page
$sql = "SELECT 
            users.user_id, 
            users.first_name, 
            users.last_name, 
            users.address, 
            users.registration_date, 
            users.email, 
            users.phone_num, 
            roles_user.role_name AS user_role, 
            roles_manager.role_name AS manager_role
        FROM users
        JOIN roles AS roles_user ON users.role_id = roles_user.role_id
        LEFT JOIN roles AS roles_manager ON users.managed_by = roles_manager.role_id
        $search_query
        ORDER BY users.user_id ASC
        LIMIT ? OFFSET ?";

$stmt = $conn->prepare($sql);

if (!empty($search_query)) {
    $types = str_repeat("s", count($search_param)) . "ii";
    $params = array_merge($search_param, [$limit, $offset]);
    $stmt->bind_param($types, ...$params);
} else {
    $stmt->bind_param("ii", $limit, $offset);
}

$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>List Users - Flight Ticket Booking System</title>
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
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="mb-6 px-4 py-3 rounded-md bg-green-100 text-green-700">
                <div class="flex justify-between items-center">
                    <span><?php echo htmlspecialchars($_SESSION['success_message']); ?></span>
                    <button onclick="this.parentElement.parentElement.style.display='none';" class="text-green-700 focus:outline-none">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="mb-6 px-4 py-3 rounded-md bg-red-100 text-red-700">
                <div class="flex justify-between items-center">
                    <span><?php echo htmlspecialchars($_SESSION['error_message']); ?></span>
                    <button onclick="this.parentElement.parentElement.style.display='none';" class="text-red-700 focus:outline-none">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['info_message'])): ?>
            <div class="mb-6 px-4 py-3 rounded-md bg-blue-100 text-blue-700">
                <div class="flex justify-between items-center">
                    <span><?php echo htmlspecialchars($_SESSION['info_message']); ?></span>
                    <button onclick="this.parentElement.parentElement.style.display='none';" class="text-blue-700 focus:outline-none">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
            <?php unset($_SESSION['info_message']); ?>
        <?php endif; ?>


        <!-- Users List -->
        <div class="bg-white shadow-md rounded-lg p-6">
            <h2 class="text-2xl font-semibold mb-6 text-indigo-600">List of Users</h2>
            
            <!-- Search Form -->
            <form method="GET" action="list_users.php" class="mb-6">
                <div class="flex flex-wrap md:flex-nowrap items-center gap-4 w-full">
                    <input
                        type="text"
                        name="search"
                        placeholder="Search by First Name, Last Name, Email, or Manager Role"
                        value="<?php echo htmlspecialchars($search); ?>"
                        class="flex-grow px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    />
                    <button
                        type="submit"
                        class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 focus:outline-none"
                    >
                        Search
                    </button>
                    <a
                        href="list_users.php"
                        class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400"
                    >
                        Reset
                    </a>
                </div>
            </form>

            
            <!-- Users Table -->
            <div class="overflow-x-auto">
                <table class="min-w-full bg-white">
                    <thead>
                        <tr>
                            <th class="px-6 py-3 border-b-2 border-gray-300 text-left text-sm font-semibold text-gray-700 uppercase tracking-wider">User ID</th>
                            <th class="px-6 py-3 border-b-2 border-gray-300 text-left text-sm font-semibold text-gray-700 uppercase tracking-wider">First Name</th>
                            <th class="px-6 py-3 border-b-2 border-gray-300 text-left text-sm font-semibold text-gray-700 uppercase tracking-wider">Last Name</th>
                            <th class="px-6 py-3 border-b-2 border-gray-300 text-left text-sm font-semibold text-gray-700 uppercase tracking-wider">Address</th>
                            <th class="px-6 py-3 border-b-2 border-gray-300 text-left text-sm font-semibold text-gray-700 uppercase tracking-wider">Registration Date</th>
                            <th class="px-6 py-3 border-b-2 border-gray-300 text-left text-sm font-semibold text-gray-700 uppercase tracking-wider">Email</th>
                            <th class="px-6 py-3 border-b-2 border-gray-300 text-left text-sm font-semibold text-gray-700 uppercase tracking-wider">Phone Number</th>
                            <th class="px-6 py-3 border-b-2 border-gray-300 text-left text-sm font-semibold text-gray-700 uppercase tracking-wider">Role</th>
                            <th class="px-6 py-3 border-b-2 border-gray-300 text-left text-sm font-semibold text-gray-700 uppercase tracking-wider">Managed By</th>
                            <th class="px-6 py-3 border-b-2 border-gray-300 text-center text-sm font-semibold text-gray-700 uppercase tracking-wider">Modify</th>
                            <th class="px-6 py-3 border-b-2 border-gray-300 text-center text-sm font-semibold text-gray-700 uppercase tracking-wider">Delete</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if ($result->num_rows > 0) {
                            // Output data of each row
                            while($row = $result->fetch_assoc()) {
                                echo "<tr class='hover:bg-gray-100'>";
                                echo "<td class='px-6 py-4 whitespace-nowrap border-b border-gray-200'>" . htmlspecialchars($row['user_id']) . "</td>";
                                echo "<td class='px-6 py-4 whitespace-nowrap border-b border-gray-200'>" . htmlspecialchars($row['first_name']) . "</td>";
                                echo "<td class='px-6 py-4 whitespace-nowrap border-b border-gray-200'>" . htmlspecialchars($row['last_name']) . "</td>";
                                echo "<td class='px-6 py-4 whitespace-nowrap border-b border-gray-200'>" . htmlspecialchars($row['address']) . "</td>";
                                echo "<td class='px-6 py-4 whitespace-nowrap border-b border-gray-200'>" . htmlspecialchars($row['registration_date']) . "</td>";
                                echo "<td class='px-6 py-4 whitespace-nowrap border-b border-gray-200'>" . htmlspecialchars($row['email']) . "</td>";
                                echo "<td class='px-6 py-4 whitespace-nowrap border-b border-gray-200'>" . htmlspecialchars($row['phone_num']) . "</td>";
                                echo "<td class='px-6 py-4 whitespace-nowrap border-b border-gray-200'>" . htmlspecialchars($row['user_role']) . "</td>";
                                
                                // Display Managed By Role
                                if (!empty($row['manager_role'])) {
                                    echo "<td class='px-6 py-4 whitespace-nowrap border-b border-gray-200'>" . htmlspecialchars($row['manager_role']) . "</td>";
                                } else {
                                    echo "<td class='px-6 py-4 whitespace-nowrap border-b border-gray-200'>None</td>";
                                }
                                
                                // If the user is Admin, do not show Edit/Delete options
                                if ($row['user_role'] == 'Admin') {
                                    echo "<td class='px-6 py-4 whitespace-nowrap border-b border-gray-200 text-center text-gray-400'>N/A</td>";
                                    echo "<td class='px-6 py-4 whitespace-nowrap border-b border-gray-200 text-center text-gray-400'>N/A</td>";
                                } else {
                                    // Modify Button
                                    echo "<td class='px-6 py-4 whitespace-nowrap border-b border-gray-200 text-center'>
                                            <a href='edit_user.php?user_id=" . urlencode($row['user_id']) . "' class='px-3 py-1 bg-blue-600 text-white rounded-md hover:bg-blue-700'>Edit</a>
                                          </td>";
                                    // Delete Button with CSRF Protection
                                    echo "<td class='px-6 py-4 whitespace-nowrap border-b border-gray-200 text-center'>
                                            <form method='POST' action='delete_user.php' onsubmit=\"return confirm('Are you sure you want to delete this user?');\">
                                                <input type='hidden' name='user_id' value='" . htmlspecialchars($row['user_id']) . "'>
                                                <input type='hidden' name='csrf_token' value='" . htmlspecialchars(generateCsrfToken()) . "'>
                                                <button type='submit' class='px-3 py-1 bg-red-600 text-white rounded-md hover:bg-red-700'>Delete</button>
                                            </form>
                                          </td>";
                                }
                                
                                echo "</tr>";
                            }
                        } else {
                            echo "<tr><td colspan='11' class='px-6 py-4 whitespace-nowrap border-b border-gray-200 text-center text-gray-500'>No users found.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="mt-6 flex justify-center">
                    <nav class="inline-flex rounded-md shadow-sm" aria-label="Pagination">
                        <!-- Previous Page Link -->
                        <?php if ($page > 1): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" class="relative inline-flex items-center px-3 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                <span class="sr-only">Previous</span>
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        <?php else: ?>
                            <span class="relative inline-flex items-center px-3 py-2 rounded-l-md border border-gray-300 bg-gray-200 text-sm font-medium text-gray-500 cursor-not-allowed">
                                <span class="sr-only">Previous</span>
                                <i class="fas fa-chevron-left"></i>
                            </span>
                        <?php endif; ?>

                        <!-- Page Number Links -->
                        <?php
                        // Determine the range of pages to show
                        $range = 2; // Number of pages to show on either side of current page
                        $start = max(1, $page - $range);
                        $end = min($total_pages, $page + $range);

                        if ($start > 1) {
                            echo '<a href="?'. http_build_query(array_merge($_GET, ['page' => 1])) .'" class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">1</a>';
                            if ($start > 2) {
                                echo '<span class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700">...</span>';
                            }
                        }

                        for ($i = $start; $i <= $end; $i++) {
                            if ($i == $page) {
                                echo '<span class="z-10 bg-indigo-50 border-indigo-500 text-indigo-600 relative inline-flex items-center px-4 py-2 border text-sm font-medium">'.$i.'</span>';
                            } else {
                                echo '<a href="?'. http_build_query(array_merge($_GET, ['page' => $i])) .'" class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">'.$i.'</a>';
                            }
                        }

                        if ($end < $total_pages) {
                            if ($end < $total_pages - 1) {
                                echo '<span class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700">...</span>';
                            }
                            echo '<a href="?'. http_build_query(array_merge($_GET, ['page' => $total_pages])) .'" class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">'.$total_pages.'</a>';
                        }
                        ?>

                        <!-- Next Page Link -->
                        <?php if ($page < $total_pages): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" class="relative inline-flex items-center px-3 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                <span class="sr-only">Next</span>
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php else: ?>
                            <span class="relative inline-flex items-center px-3 py-2 rounded-r-md border border-gray-300 bg-gray-200 text-sm font-medium text-gray-500 cursor-not-allowed">
                                <span class="sr-only">Next</span>
                                <i class="fas fa-chevron-right"></i>
                            </span>
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
