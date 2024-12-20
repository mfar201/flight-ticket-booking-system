<?php
session_start();
require 'csrf_helper.php';
$csrf_token = generateCsrfToken();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Flight Ticket Booking System</title>
    <link rel="icon" type="image/x-icon" href="fav.png">
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 flex flex-col min-h-screen">

    <!-- Navbar -->
    <nav class="bg-white shadow">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex-shrink-0 flex items-center">
                    <a href="index.php" class="text-xl font-bold text-indigo-600">Flight Ticket Booking System</a>
                </div>
                <div class="hidden md:flex space-x-4 items-center">
                    <a href="login.php" class="text-gray-700 hover:text-indigo-600 px-3 py-2 rounded-md text-sm font-medium">Login</a>
                    <a href="register.php" class="text-indigo-600 font-medium px-3 py-2 rounded-md bg-gray-200 hover:bg-gray-300">Register</a>
                </div>
                <div class="flex items-center md:hidden">
                    <button id="mobile-menu-button" class="text-gray-700 hover:text-indigo-600 focus:outline-none">
                        <i class="fas fa-bars fa-lg"></i>
                    </button>
                </div>
            </div>
        </div>
        <div id="mobile-menu" class="hidden md:hidden bg-white shadow-md">
            <a href="login.php" class="block px-4 py-2 text-gray-700 hover:bg-indigo-50">Login</a>
            <a href="register.php" class="block px-4 py-2 text-indigo-600 font-medium bg-gray-200 hover:bg-gray-300">Register</a>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="flex-grow container mx-auto px-4 py-8 max-w-lg">
        <div class="bg-white shadow-md rounded-lg p-6">
            <h2 class="text-2xl font-semibold text-indigo-600 mb-4 text-center">User Registration</h2>
            
            <!-- Registration Form -->
            <form action="register_process.php" method="POST" class="space-y-4">
                <!-- CSRF Token -->
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">

                <!-- First Name -->
                <div>
                    <label for="first_name" class="block text-sm font-medium text-gray-700">First Name<span class="text-red-500">*</span></label>
                    <input type="text" id="first_name" name="first_name" required 
                        class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                </div>

                <!-- Last Name -->
                <div>
                    <label for="last_name" class="block text-sm font-medium text-gray-700">Last Name<span class="text-red-500">*</span></label>
                    <input type="text" id="last_name" name="last_name" required 
                        class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                </div>

                <!-- Address -->
                <div>
                    <label for="address" class="block text-sm font-medium text-gray-700">Address<span class="text-red-500">*</span></label>
                    <textarea id="address" name="address" rows="3" required
                        class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500"></textarea>
                </div>

                <!-- Email -->
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700">Email<span class="text-red-500">*</span></label>
                    <input type="email" id="email" name="email" required 
                        class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                </div>

                <!-- Phone Number -->
                <div>
                    <label for="phone_num" class="block text-sm font-medium text-gray-700">Phone Number<span class="text-red-500">*</span></label>
                    <input type="text" id="phone_num" name="phone_num" required 
                        class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                </div>

                <!-- Password -->
                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700">Password<span class="text-red-500">*</span></label>
                    <input type="password" id="password" name="password" required 
                        class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                </div>

                <!-- Submit Button -->
                <div>
                    <button type="submit" 
                        class="w-full px-4 py-2 bg-indigo-600 text-white font-medium rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        Register
                    </button>
                </div>
            </form>

            <p class="text-sm text-center text-gray-500 mt-4">
                Already have an account? <a href="login.php" class="text-indigo-600 hover:underline">Login here</a>.
            </p>
        </div>
    </main>

    <!-- Include Footer -->
    <?php include 'footer.php'; ?>

    <!-- Tailwind CSS Mobile Menu Toggle Script -->
    <script>
        const btn = document.getElementById('mobile-menu-button');
        const menu = document.getElementById('mobile-menu');

        btn.addEventListener('click', () => {
            menu.classList.toggle('hidden');
        });
    </script>
</body>
</html>
