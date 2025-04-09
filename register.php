<?php
session_start();
include_once 'includes/db_connect.php';
include_once 'includes/functions.php';

// Check if user is already logged in
if (isset($_SESSION['user_id']) && isset($_SESSION['user_type'])) {
    // Redirect based on user type
    if ($_SESSION['user_type'] == 'customer') {
        header("Location: customer/dashboard.php");
        exit();
    } elseif ($_SESSION['user_type'] == 'coach') {
        header("Location: coach/dashboard.php");
        exit();
    } elseif ($_SESSION['user_type'] == 'admin') {
        header("Location: admin/dashboard.php");
        exit();
    }
}

$error = '';
$success = '';

// Check for session messages
if (isset($_SESSION['register_error'])) {
    $error = $_SESSION['register_error'];
    unset($_SESSION['register_error']);
}
if (isset($_SESSION['register_success'])) {
    $success = $_SESSION['register_success'];
    unset($_SESSION['register_success']);
}

// Process registration form
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validate inputs
    if (empty($name) || empty($email) || empty($password) || empty($confirm_password)) {
        $_SESSION['register_error'] = "Please fill in all fields";
        header("Location: register.php");
        exit();
    } elseif ($password != $confirm_password) {
        $_SESSION['register_error'] = "Passwords do not match";
        header("Location: register.php");
        exit();
    } elseif (strlen($password) < 6) {
        $_SESSION['register_error'] = "Password must be at least 6 characters long";
        header("Location: register.php");
        exit();
    } elseif (user_exists($email)) {
        $_SESSION['register_error'] = "Email is already registered";
        header("Location: register.php");
        exit();
    } else {
        // Register new user as customer
        $user_id = register_user($name, $email, $password, 'customer');
        
        if ($user_id) {
            // No auto-assignment of coach - customer will select one after login
            $_SESSION['register_success'] = "Registration successful! You can now login to select your coach.";
            header("Location: register.php");
            exit();
        } else {
            $_SESSION['register_error'] = "Registration failed. Please try again.";
            header("Location: register.php");
            exit();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - OpFit</title>
    <link rel="stylesheet" href="css/style.css">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 text-gray-800">
    <nav class="bg-gray-800 text-white">
        <div class="container mx-auto px-4 py-3">
            <div class="flex justify-between items-center">
                <a class="text-xl font-bold" href="index.php">OpFit</a>
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
                            <a class="text-white font-medium" href="register.php">Register</a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <div class="container mx-auto px-4 py-8">
        <div class="flex justify-center">
            <div class="w-full max-w-md">
                <div class="bg-white rounded-lg shadow-md overflow-hidden">
                    <div class="bg-blue-600 text-white px-6 py-4">
                        <h4 class="text-xl font-semibold">Create Your Account</h4>
                    </div>
                    <div class="p-6">
                        <?php if (!empty($error)): ?>
                            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4"><?php echo $error; ?></div>
                        <?php endif; ?>
                        
                        <?php if (!empty($success)): ?>
                            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4"><?php echo $success; ?></div>
                        <?php endif; ?>
                        
                        <form action="register.php" method="post">
                            <div class="mb-4">
                                <label for="name" class="block text-gray-700 mb-2">Full Name</label>
                                <input type="text" class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500" id="name" name="name" required>
                            </div>
                            <div class="mb-4">
                                <label for="email" class="block text-gray-700 mb-2">Email Address</label>
                                <input type="email" class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500" id="email" name="email" required>
                            </div>
                            <div class="mb-4">
                                <label for="password" class="block text-gray-700 mb-2">Password</label>
                                <input type="password" class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500" id="password" name="password" required>
                                <p class="text-gray-600 text-sm mt-1">Password must be at least 6 characters long.</p>
                            </div>
                            <div class="mb-6">
                                <label for="confirm_password" class="block text-gray-700 mb-2">Confirm Password</label>
                                <input type="password" class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500" id="confirm_password" name="confirm_password" required>
                            </div>
                            <div>
                                <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded">Register</button>
                            </div>
                        </form>
                        
                        <div class="mt-4 text-center">
                            <p>Already have an account? <a href="login.php" class="text-blue-600 hover:text-blue-800">Login here</a></p>
                        </div>
                    </div>
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
</body>
</html> 