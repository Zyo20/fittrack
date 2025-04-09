<?php
session_start();

// Session timeout functionality - 5 minutes
$session_timeout = 300; // 5 minutes in seconds
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $session_timeout)) {
    // Last activity was more than 5 minutes ago
    session_unset();     // Unset all session variables
    session_destroy();   // Destroy the session
    header("Location: ../login.php?timeout=1");
    exit();
}
// Update last activity time
$_SESSION['last_activity'] = time();

include_once 'includes/db_connect.php';
include_once 'includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name']; 
$user_type = $_SESSION['user_type'];
$error = null;
$success = null;

// Get user details
$stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) == 0) {
    header("Location: login.php");
    exit();
}

$user = mysqli_fetch_assoc($result);

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = sanitize_input($_POST['name']);
    $email = sanitize_input($_POST['email']);
    
    // Check if email exists (if changed)
    if ($email != $user['email']) {
        $check_stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE email = ? AND id != ?");
        mysqli_stmt_bind_param($check_stmt, "si", $email, $user_id);
        mysqli_stmt_execute($check_stmt);
        $check_result = mysqli_stmt_get_result($check_stmt);
        
        if (mysqli_num_rows($check_result) > 0) {
            $error = "Email already exists";
        }
        mysqli_stmt_close($check_stmt);
    }
    
    if (!isset($error)) {
        // Update user information
        $update_stmt = mysqli_prepare($conn, "UPDATE users SET name = ?, email = ?, notifications_enabled = ? WHERE id = ?");
        mysqli_stmt_bind_param($update_stmt, "ssii", $name, $email, $notifications_enabled, $user_id);
        
        // Update password if provided and confirmed
        if (!empty($_POST['password']) && !empty($_POST['confirm_password'])) {
            $password = $_POST['password'];
            $confirm_password = $_POST['confirm_password'];
            
            if ($password !== $confirm_password) {
                $error = "Passwords do not match";
            } else if (strlen($password) < 8) {
                $error = "Password must be at least 8 characters";
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $update_stmt = mysqli_prepare($conn, "UPDATE users SET name = ?, email = ?, password = ?, notifications_enabled = ? WHERE id = ?");
                mysqli_stmt_bind_param($update_stmt, "sssii", $name, $email, $hashed_password, $notifications_enabled, $user_id);
            }
        }
        
        if (!isset($error)) {
            if (mysqli_stmt_execute($update_stmt)) {
                // Update session variables
                $_SESSION['user_name'] = $name;
                $_SESSION['user_email'] = $email;
                
                $success = "Profile updated successfully";
                
                // Refresh user data
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                $user = mysqli_fetch_assoc($result);
            } else {
                $error = "Failed to update profile: " . mysqli_error($conn);
            }
        }
        
        mysqli_stmt_close($update_stmt);
    }
}

// Determine redirect URL based on user type
$home_url = "dashboard.php";
if ($user_type == "admin") {
    $home_url = "admin/dashboard.php";
} else if ($user_type == "coach") {
    $home_url = "coach/dashboard.php";
} else {
    $home_url = "customer/dashboard.php";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - OpFit</title>
    <link rel="stylesheet" href="css/style.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body class="bg-gray-100">
    <nav class="bg-gray-800 text-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <div class="flex items-center">
                    <a class="text-xl font-bold" href="<?php echo $home_url; ?>">OpFit</a>
                    <div class="hidden md:block">
                        <div class="ml-10 flex items-baseline space-x-4">
                            <a class="text-gray-300 hover:text-white px-3 py-2 rounded-md text-sm font-medium" href="<?php echo $home_url; ?>">Dashboard</a>
                            <?php if ($user_type == 'admin'): ?>
                            <a class="text-gray-300 hover:text-white px-3 py-2 rounded-md text-sm font-medium" href="admin/users.php">Users</a>
                            <a class="text-gray-300 hover:text-white px-3 py-2 rounded-md text-sm font-medium" href="admin/programs.php">Programs</a>
                            <?php elseif ($user_type == 'coach'): ?>
                            <a class="text-gray-300 hover:text-white px-3 py-2 rounded-md text-sm font-medium" href="coach/customers.php">My Customers</a>
                            <a class="text-gray-300 hover:text-white px-3 py-2 rounded-md text-sm font-medium" href="coach/programs.php">Programs</a>
                            <?php else: ?>
                            <a class="text-gray-300 hover:text-white px-3 py-2 rounded-md text-sm font-medium" href="customer/programs.php">My Programs</a>
                            <a class="text-gray-300 hover:text-white px-3 py-2 rounded-md text-sm font-medium" href="customer/progress.php">Progress Tracker</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="hidden md:block">
                    <div class="relative">
                        <div class="group">
                            <button class="flex items-center text-sm px-4 py-2 leading-none rounded text-white hover:text-white hover:bg-gray-700 focus:outline-none" id="user-menu-button">
                                <?php echo htmlspecialchars($user_name); ?>
                                <svg class="ml-2 h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </button>
                            <div class="origin-top-right absolute right-0 mt-2 w-48 rounded-md shadow-lg py-1 bg-white ring-1 ring-black ring-opacity-5 hidden group-hover:block">
                                <a href="profile.php" class="block px-4 py-2 text-sm text-gray-700 bg-gray-100">Profile</a>
                                <a href="logout.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Logout</a>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="-mr-2 flex md:hidden">
                    <button type="button" class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-white hover:bg-gray-700 focus:outline-none" id="mobile-menu-button">
                        <span class="sr-only">Open main menu</span>
                        <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Mobile menu -->
        <div class="hidden" id="mobile-menu">
            <div class="px-2 pt-2 pb-3 space-y-1 sm:px-3">
                <a class="text-gray-300 hover:text-white block px-3 py-2 rounded-md text-base font-medium" href="<?php echo $home_url; ?>">Dashboard</a>
                <?php if ($user_type == 'admin'): ?>
                <a class="text-gray-300 hover:text-white block px-3 py-2 rounded-md text-base font-medium" href="admin/users.php">Users</a>
                <a class="text-gray-300 hover:text-white block px-3 py-2 rounded-md text-base font-medium" href="admin/programs.php">Programs</a>
                <?php elseif ($user_type == 'coach'): ?>
                <a class="text-gray-300 hover:text-white block px-3 py-2 rounded-md text-base font-medium" href="coach/customers.php">My Customers</a>
                <a class="text-gray-300 hover:text-white block px-3 py-2 rounded-md text-base font-medium" href="coach/programs.php">Programs</a>
                <?php else: ?>
                <a class="text-gray-300 hover:text-white block px-3 py-2 rounded-md text-base font-medium" href="customer/programs.php">My Programs</a>
                <a class="text-gray-300 hover:text-white block px-3 py-2 rounded-md text-base font-medium" href="customer/progress.php">Progress Tracker</a>
                <?php endif; ?>
                <a href="profile.php" class="text-gray-300 hover:text-white block px-3 py-2 rounded-md text-base font-medium">Profile</a>
                <a href="logout.php" class="text-gray-300 hover:text-white block px-3 py-2 rounded-md text-base font-medium">Logout</a>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-10">
        <div class="flex justify-center">
            <div class="w-full md:w-4/5 lg:w-3/4">
                <div class="bg-white rounded-lg shadow-lg overflow-hidden mb-8">
                    <div class="bg-blue-600 text-white p-6 rounded-t-lg">
                        <div class="flex items-center">
                            <div class="w-24 h-24 bg-white text-blue-600 rounded-full flex items-center justify-center text-4xl border-4 border-white/50 mr-6">
                                <i class="fas fa-user"></i>
                            </div>
                            <div>
                                <h2 class="text-2xl font-bold mb-1"><?php echo htmlspecialchars($user['name']); ?></h2>
                                <div class="flex items-center flex-wrap">
                                    <span class="text-white/75 mr-3 flex items-center">
                                        <i class="fas fa-envelope mr-1"></i> <?php echo htmlspecialchars($user['email']); ?>
                                    </span>
                                    <span class="text-xs uppercase tracking-wider bg-white/20 rounded-full px-3 py-1">
                                        <?php echo ucfirst(htmlspecialchars($user['user_type'])); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="p-6">
                        <?php if ($error): ?>
                            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                                <span class="block sm:inline"><?php echo htmlspecialchars($error); ?></span>
                                <button type="button" class="absolute top-0 right-0 px-4 py-3" aria-label="Close" onclick="this.parentElement.style.display='none';">
                                    <span class="text-red-500">&times;</span>
                                </button>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
                                <span class="block sm:inline"><?php echo htmlspecialchars($success); ?></span>
                                <button type="button" class="absolute top-0 right-0 px-4 py-3" aria-label="Close" onclick="this.parentElement.style.display='none';">
                                    <span class="text-green-500">&times;</span>
                                </button>
                            </div>
                        <?php endif; ?>
                        
                        <h4 class="text-xl font-semibold mb-6">Edit Profile</h4>
                        
                        <form action="profile.php" method="post" id="profileForm">
                            <div class="mb-6 grid grid-cols-1 md:grid-cols-3 gap-4 items-center">
                                <label for="id" class="md:col-span-1 text-gray-700">User ID</label>
                                <div class="md:col-span-2">
                                    <span class="text-gray-500"><?php echo htmlspecialchars($user['id']); ?></span>
                                </div>
                            </div>
                            
                            <div class="mb-6 grid grid-cols-1 md:grid-cols-3 gap-4 items-center">
                                <label for="name" class="md:col-span-1 text-gray-700">Name</label>
                                <div class="md:col-span-2">
                                    <input type="text" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" id="name" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" required>
                                </div>
                            </div>
                            
                            <div class="mb-6 grid grid-cols-1 md:grid-cols-3 gap-4 items-center">
                                <label for="email" class="md:col-span-1 text-gray-700">Email</label>
                                <div class="md:col-span-2">
                                    <input type="email" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                </div>
                            </div>
                            
                            <div class="mb-6 grid grid-cols-1 md:grid-cols-3 gap-4 items-center">
                                <label for="user_type" class="md:col-span-1 text-gray-700">User Type</label>
                                <div class="md:col-span-2">
                                    <span class="text-gray-500"><?php echo ucfirst(htmlspecialchars($user['user_type'])); ?></span>
                                </div>
                            </div>
                            
                            <div class="mb-6 grid grid-cols-1 md:grid-cols-3 gap-4 items-center">
                                <label for="created_at" class="md:col-span-1 text-gray-700">Member Since</label>
                                <div class="md:col-span-2">
                                    <span class="text-gray-500"><?php echo date('F d, Y', strtotime($user['created_at'])); ?></span>
                                </div>
                            </div>
                            
                            <hr class="my-8 border-gray-200">
                            
                            <h5 class="text-lg font-semibold mb-3">Change Password</h5>
                            <p class="text-sm text-gray-500 mb-4">Leave blank if you don't want to change your password</p>
                            
                            <div class="mb-6 grid grid-cols-1 md:grid-cols-3 gap-4 items-center">
                                <label for="password" class="md:col-span-1 text-gray-700">New Password</label>
                                <div class="md:col-span-2">
                                    <input type="password" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" id="password" name="password" minlength="8">
                                    <p class="text-xs text-gray-500 mt-1">At least 8 characters</p>
                                </div>
                            </div>
                            
                            <div class="mb-6 grid grid-cols-1 md:grid-cols-3 gap-4 items-center">
                                <label for="confirm_password" class="md:col-span-1 text-gray-700">Confirm Password</label>
                                <div class="md:col-span-2">
                                    <input type="password" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" id="confirm_password" name="confirm_password">
                                </div>
                            </div>

                            <div class="flex justify-end mt-8">
                                <a href="<?php echo $home_url; ?>" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded mr-2">Cancel</a>
                                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">Save Changes</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include_once 'includes/footer.php'; ?>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Mobile menu toggle
            const mobileMenuButton = document.getElementById('mobile-menu-button');
            const mobileMenu = document.getElementById('mobile-menu');
            
            mobileMenuButton.addEventListener('click', function() {
                mobileMenu.classList.toggle('hidden');
            });
            
            // Form validation
            const form = document.getElementById('profileForm');
            const password = document.getElementById('password');
            const confirmPassword = document.getElementById('confirm_password');
            
            form.addEventListener('submit', function(event) {
                // Check if password fields match if either is filled
                if ((password.value || confirmPassword.value) && password.value !== confirmPassword.value) {
                    event.preventDefault();
                    alert('Passwords do not match');
                    return false;
                }
                
                // Check password length if provided
                if (password.value && password.value.length < 8) {
                    event.preventDefault();
                    alert('Password must be at least 8 characters');
                    return false;
                }
            });
            
            // Auto-hide alerts after 5 seconds
            const alerts = document.querySelectorAll('[role="alert"]');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.display = 'none';
                }, 5000);
            });
        });
    </script>
</body>
</html> 