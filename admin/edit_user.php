<?php
session_start();

include_once '../includes/db_connect.php';
include_once '../includes/functions.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
$error = null;
$success = null;

// Get unread messages count 
$unread_count = count_unread_messages($user_id);

// Check if user ID is provided
if (!isset($_GET['id'])) {
    header("Location: users.php");
    exit();
}

$edit_id = (int)$_GET['id'];

// Get user details
$query = "SELECT * FROM users WHERE id = $edit_id";
$result = mysqli_query($conn, $query);

if (mysqli_num_rows($result) == 0) {
    header("Location: users.php");
    exit();
}

$user = mysqli_fetch_assoc($result);

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = sanitize_input($_POST['name']);
    $email = sanitize_input($_POST['email']);
    $user_type = sanitize_input($_POST['user_type']);
    
    // Check if email exists (if changed)
    if ($email != $user['email']) {
        $check_query = "SELECT * FROM users WHERE email = '$email' AND id != $edit_id";
        $check_result = mysqli_query($conn, $check_query);
        
        if (mysqli_num_rows($check_result) > 0) {
            $error = "Email already exists";
        }
    }
    
    if (!isset($error)) {
        // Update user information
        $update_query = "UPDATE users SET name = '$name', email = '$email', user_type = '$user_type' WHERE id = $edit_id";
        
        // Update password if provided
        if (!empty($_POST['password'])) {
            $password = $_POST['password'];
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $update_query = "UPDATE users SET name = '$name', email = '$email', user_type = '$user_type', password = '$hashed_password' WHERE id = $edit_id";
        }
        
        if (mysqli_query($conn, $update_query)) {
            $success = "User updated successfully";
            
            // Refresh user data
            $result = mysqli_query($conn, "SELECT * FROM users WHERE id = $edit_id");
            $user = mysqli_fetch_assoc($result);
        } else {
            $error = "Failed to update user";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit User - OpFit Admin</title>
    <link rel="stylesheet" href="../css/style.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body class="bg-gray-100 text-gray-800">
    <nav class="bg-gray-800 text-white">
        <div class="container mx-auto px-4 py-3">
            <div class="flex flex-wrap justify-between items-center">
                <a class="text-xl font-bold" href="dashboard.php">OpFit Admin</a>
                <button class="md:hidden" type="button" id="navbarToggle">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>
                <div class="hidden md:flex flex-col md:flex-row w-full md:w-auto mt-4 md:mt-0 items-center" id="navbarMenu">
                    <ul class="flex flex-col md:flex-row md:mr-6 space-y-2 md:space-y-0 md:space-x-6">
                        <li>
                            <a class="text-gray-300 hover:text-white block py-2" href="dashboard.php">Dashboard</a>
                        </li>
                        <li>
                            <a class="text-white font-medium block py-2" href="users.php">Users</a>
                        </li>
                        <li>
                            <a class="text-gray-300 hover:text-white block py-2" href="programs.php">Programs</a>
                        </li>
                        <li>
                            <a class="text-gray-300 hover:text-white block py-2" href="assignments.php">Coach Assignments</a>
                        </li>
                        <li>
                            <a class="text-gray-300 hover:text-white block py-2" href="reports.php">Reports</a>
                        </li>
                        <li>
                            <a class="text-gray-300 hover:text-white block py-2 flex items-center" href="messages.php">
                                Messages
                                <?php if ($unread_count > 0): ?>
                                    <span class="ml-1 px-2 py-0.5 text-xs rounded-full bg-red-600"><?php echo $unread_count; ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                    </ul>
                    <div class="relative mt-4 md:mt-0 md:ml-4">
                        <button id="userDropdown" class="flex items-center text-gray-300 hover:text-white py-2">
                            <?php echo $user_name; ?>
                            <svg class="ml-1 h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                            </svg>
                        </button>
                        <div id="userDropdownMenu" class="absolute right-0 mt-2 py-2 w-48 bg-white rounded-md shadow-lg hidden z-10">
                            <a href="../profile.php" class="block px-4 py-2 text-gray-700 hover:bg-gray-100">Profile</a>
                            <div class="border-t border-gray-100"></div>
                            <a href="../logout.php" class="block px-4 py-2 text-gray-700 hover:bg-gray-100">Logout</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <div class="container mx-auto px-4 py-4">
        <div class="flex flex-wrap -mx-2 mb-4">
            <div class="w-full md:w-1/2 px-2">
                <h1 class="text-2xl font-bold">Edit User</h1>
            </div>
            <div class="w-full md:w-1/2 px-2 flex justify-end">
                <a href="users.php" class="bg-gray-500 hover:bg-gray-600 text-white font-medium py-2 px-4 rounded flex items-center">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Users
                </a>
            </div>
        </div>
        
        <?php if (isset($error)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if (isset($success)): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <div class="bg-white rounded-lg shadow-md">
            <div class="bg-blue-600 text-white px-4 py-3 rounded-t-lg">
                <h5 class="text-lg font-medium">Edit User Details</h5>
            </div>
            <div class="p-4">
                <form action="edit_user.php?id=<?php echo $edit_id; ?>" method="post">
                    <div class="mb-4 grid grid-cols-1 md:grid-cols-3 gap-4 items-center">
                        <label for="id" class="font-medium text-gray-700">User ID</label>
                        <div class="md:col-span-2">
                            <input type="text" class="bg-gray-100 w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" id="id" value="<?php echo $user['id']; ?>" readonly>
                        </div>
                    </div>
                    
                    <div class="mb-4 grid grid-cols-1 md:grid-cols-3 gap-4 items-center">
                        <label for="name" class="font-medium text-gray-700">Name</label>
                        <div class="md:col-span-2">
                            <input type="text" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" id="name" name="name" value="<?php echo $user['name']; ?>" required>
                        </div>
                    </div>
                    
                    <div class="mb-4 grid grid-cols-1 md:grid-cols-3 gap-4 items-center">
                        <label for="email" class="font-medium text-gray-700">Email</label>
                        <div class="md:col-span-2">
                            <input type="email" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" id="email" name="email" value="<?php echo $user['email']; ?>" required>
                        </div>
                    </div>
                    
                    <div class="mb-4 grid grid-cols-1 md:grid-cols-3 gap-4 items-center">
                        <label for="password" class="font-medium text-gray-700">Password</label>
                        <div class="md:col-span-2">
                            <input type="password" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" id="password" name="password" placeholder="Leave blank to keep current password">
                            <p class="text-gray-500 text-sm mt-1">Leave blank to keep current password</p>
                        </div>
                    </div>
                    
                    <div class="mb-4 grid grid-cols-1 md:grid-cols-3 gap-4 items-center">
                        <label for="user_type" class="font-medium text-gray-700">User Type</label>
                        <div class="md:col-span-2">
                            <select class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" id="user_type" name="user_type" required>
                                <option value="customer" <?php echo ($user['user_type'] == 'customer') ? 'selected' : ''; ?>>Customer</option>
                                <option value="coach" <?php echo ($user['user_type'] == 'coach') ? 'selected' : ''; ?>>Coach</option>
                                <option value="admin" <?php echo ($user['user_type'] == 'admin') ? 'selected' : ''; ?>>Admin</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-4 grid grid-cols-1 md:grid-cols-3 gap-4 items-center">
                        <label for="created_at" class="font-medium text-gray-700">Joined</label>
                        <div class="md:col-span-2">
                            <input type="text" class="bg-gray-100 w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none" id="created_at" value="<?php echo date('F d, Y h:i A', strtotime($user['created_at'])); ?>" readonly>
                        </div>
                    </div>
                    
                    <div class="flex justify-end space-x-2">
                        <a href="users.php" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-medium py-2 px-4 rounded">Cancel</a>
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded">Update User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php include_once '../includes/footer.php'; ?>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Mobile menu toggle
            const navbarToggle = document.getElementById('navbarToggle');
            if (navbarToggle) {
                navbarToggle.addEventListener('click', function() {
                    const menu = document.getElementById('navbarMenu');
                    menu.classList.toggle('hidden');
                });
            }
            
            // User dropdown toggle
            const userDropdown = document.getElementById('userDropdown');
            const userDropdownMenu = document.getElementById('userDropdownMenu');
            
            if (userDropdown && userDropdownMenu) {
                userDropdown.addEventListener('click', function(e) {
                    e.stopPropagation();
                    userDropdownMenu.classList.toggle('hidden');
                });
                
                // Close dropdown when clicking outside
                document.addEventListener('click', function() {
                    if (!userDropdownMenu.classList.contains('hidden')) {
                        userDropdownMenu.classList.add('hidden');
                    }
                });
            }
        });
    </script>
</body>
</html> 