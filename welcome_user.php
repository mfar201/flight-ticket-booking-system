<?php
// welcome_user.php
session_start();
require 'config.php';
require 'csrf_helper.php'; // Include CSRF helper

// Check if user is logged in and is a User
if (!isset($_SESSION['user_id']) || $_SESSION['role_name'] != 'User') {
    header("Location: login.php");
    exit();
}

// Retrieve user information from the database
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT user_id, first_name, last_name, address, registration_date, email, phone_num 
                        FROM users 
                        WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 1) {
    $user = $result->fetch_assoc();
} else {
    echo "User not found.";
    exit();
}

// Generate a CSRF token
$csrf_token = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome User - Flight Ticket Booking System</title>
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
                    <a href="welcome_user.php" class="text-xl font-bold text-indigo-600">Flight Ticket Booking System</a>
                </div>
                <!-- Navigation Links -->
                <div class="hidden md:flex space-x-4 items-center">
                    <a href="book_flight.php" class="text-gray-700 hover:text-indigo-600 px-3 py-2 rounded-md text-sm font-medium">Book Flights</a>
                    <a href="view_bookings.php" class="text-gray-700 hover:text-indigo-600 px-3 py-2 rounded-md text-sm font-medium">My Bookings</a>
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
            <a href="book_flight.php" class="block px-4 py-2 text-gray-700 hover:bg-indigo-50">Book Flights</a>
            <a href="view_bookings.php" class="block px-4 py-2 text-gray-700 hover:bg-indigo-50">My Bookings</a>
            <form action="logout.php" method="POST" class="px-4 py-2">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
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

        <!-- User Information Header with Edit Profile Button -->
        <div class="bg-white shadow-md rounded-lg p-6 mb-8">
        <div class="flex flex-col md:flex-row justify-between items-center mb-6">
            <h2 class="text-2xl font-semibold text-indigo-600 mb-4 md:mb-0">Welcome, <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>!</h2>
            <a href="edit_profile.php" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                <i class="fas fa-user-edit mr-2"></i> Edit Account
            </a>
        </div>

        <!-- User Information Table -->
        
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
                            <td class="px-6 py-4 whitespace-no-wrap border-b border-gray-200"><?php echo htmlspecialchars($user['user_id']); ?></td>
                        </tr>
                        <tr class="hover:bg-gray-100">
                            <td class="px-6 py-4 whitespace-no-wrap border-b border-gray-200">First Name</td>
                            <td class="px-6 py-4 whitespace-no-wrap border-b border-gray-200"><?php echo htmlspecialchars($user['first_name']); ?></td>
                        </tr>
                        <tr class="hover:bg-gray-100">
                            <td class="px-6 py-4 whitespace-no-wrap border-b border-gray-200">Last Name</td>
                            <td class="px-6 py-4 whitespace-no-wrap border-b border-gray-200"><?php echo htmlspecialchars($user['last_name']); ?></td>
                        </tr>
                        <tr class="hover:bg-gray-100">
                            <td class="px-6 py-4 whitespace-no-wrap border-b border-gray-200">Address</td>
                            <td class="px-6 py-4 whitespace-no-wrap border-b border-gray-200"><?php echo htmlspecialchars($user['address']); ?></td>
                        </tr>
                        <tr class="hover:bg-gray-100">
                            <td class="px-6 py-4 whitespace-no-wrap border-b border-gray-200">Registration Date</td>
                            <td class="px-6 py-4 whitespace-no-wrap border-b border-gray-200"><?php echo htmlspecialchars($user['registration_date']); ?></td>
                        </tr>
                        <tr class="hover:bg-gray-100">
                            <td class="px-6 py-4 whitespace-no-wrap border-b border-gray-200">Email</td>
                            <td class="px-6 py-4 whitespace-no-wrap border-b border-gray-200"><?php echo htmlspecialchars($user['email']); ?></td>
                        </tr>
                        <tr class="hover:bg-gray-100">
                            <td class="px-6 py-4 whitespace-no-wrap border-b border-gray-200">Phone Number</td>
                            <td class="px-6 py-4 whitespace-no-wrap border-b border-gray-200"><?php echo htmlspecialchars($user['phone_num']); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    
    </main>

    <!-- Include Footer -->
    <?php include 'footer.php'; ?>

    <!-- Tailwind CSS Mobile Menu Toggle Script -->
    <script>
        // Toggle Mobile Menu
        const btn = document.getElementById('mobile-menu-button');
        const menu = document.getElementById('mobile-menu');

        btn.addEventListener('click', () => {
            menu.classList.toggle('hidden');
        });
    </script>

</body>
</html>
<?php
$conn->close();
?>
