<?php
session_start();
include_once 'includes/db_connect.php';
include_once 'includes/functions.php';

// Check if user is logged in, redirect to appropriate dashboard
if (isset($_SESSION['user_id']) && isset($_SESSION['user_type'])) {
    $user_type = $_SESSION['user_type'];
    
    // Redirect based on user type
    if ($user_type == 'customer') {
        header("Location: customer/dashboard.php");
        exit();
    } elseif ($user_type == 'coach') {
        header("Location: coach/dashboard.php");
        exit();
    } elseif ($user_type == 'admin') {
        header("Location: admin/dashboard.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OpFit - Gym Progress Tracking System</title>
    <link rel="stylesheet" href="css/style.css">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 text-gray-800">
    <nav class="bg-gray-800 text-white">
        <div class="container mx-auto px-4 py-3">
            <div class="flex justify-between items-center">
                <a class="text-xl font-bold" href="#">OpFit</a>
                <button class="md:hidden" type="button" id="navbarToggle">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>
                <div class="hidden md:flex items-center" id="navbarMenu">
                    <ul class="flex space-x-4">
                        <li>
                            <a class="hover:text-gray-300" href="login.php">Login</a>
                        </li>
                        <li>
                            <a class="hover:text-gray-300" href="register.php">Register</a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <div class="container mx-auto px-4 py-8">
        <div class="flex flex-col md:flex-row gap-8">
            <div class="w-full md:w-1/2">
                <h1 class="text-3xl font-bold mb-4">Track Your Fitness Journey</h1>
                <p class="text-lg mb-6">OpFit helps you monitor your progress, connect with your personal coach, and achieve your fitness goals.</p>
                <div class="flex gap-3">
                    <a href="register.php" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded">Get Started</a>
                    <a href="login.php" class="border border-gray-300 hover:bg-gray-100 font-medium py-2 px-4 rounded">Login</a>
                </div>
            </div>
            <div class="w-full md:w-1/2">
                <img src="img/fitness.jpg" alt="Fitness" class="rounded w-full">
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-12">
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="p-6 text-center">
                    <h3 class="text-xl font-semibold mb-2">Track Progress</h3>
                    <p>Monitor your workout history, achievements, and body metrics.</p>
                </div>
            </div>
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="p-6 text-center">
                    <h3 class="text-xl font-semibold mb-2">Personal Coach</h3>
                    <p>Get guidance from your dedicated personal trainer.</p>
                </div>
            </div>
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="p-6 text-center">
                    <h3 class="text-xl font-semibold mb-2">Custom Programs</h3>
                    <p>Choose the programs you want and get coach approval.</p>
                </div>
            </div>
        </div>
    </div>

    <?php include_once 'includes/footer.php'; ?>

    <script>
        // Simple toggle for mobile menu
        document.getElementById('navbarToggle').addEventListener('click', function() {
            const menu = document.getElementById('navbarMenu');
            menu.classList.toggle('hidden');
        });
    </script>
    <script src="js/script.js"></script>
</body>
</html> 