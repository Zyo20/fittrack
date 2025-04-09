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

// Get unread messages count 
$unread_count = count_unread_messages($user_id);

// Process filter
$user_type_filter = isset($_GET['type']) ? $_GET['type'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Process add user
if (isset($_POST['add_user'])) {
    $name = sanitize_input($_POST['name']);
    $email = sanitize_input($_POST['email']);
    $password = $_POST['password'];
    $user_type = sanitize_input($_POST['user_type']);
    
    // Validate input
    if (empty($name) || empty($email) || empty($password) || empty($user_type)) {
        $error = "All fields are required";
    } elseif (user_exists($email)) {
        $error = "Email already exists";
    } else {
        // Register user
        if (register_user($name, $email, $password, $user_type)) {
            $success = "User added successfully";
        } else {
            $error = "Failed to add user";
        }
    }
}

// Process delete user
if (isset($_GET['delete'])) {
    $delete_id = (int)$_GET['delete'];
    
    // Don't delete self
    if ($delete_id == $user_id) {
        $error = "You cannot delete your own account";
    } else {
        $delete_query = "DELETE FROM users WHERE id = $delete_id";
        if (mysqli_query($conn, $delete_query)) {
            $success = "User deleted successfully";
        } else {
            $error = "Failed to delete user";
        }
    }
}

// Build query based on filters
$query = "SELECT * FROM users";

$where_clauses = [];
if (!empty($user_type_filter)) {
    $where_clauses[] = "user_type = '$user_type_filter'";
}

if (!empty($search)) {
    $where_clauses[] = "(name LIKE '%$search%' OR email LIKE '%$search%')";
}

if (!empty($where_clauses)) {
    $query .= " WHERE " . implode(' AND ', $where_clauses);
}

$query .= " ORDER BY created_at DESC";
$result = mysqli_query($conn, $query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - OpFit Admin</title>
    <link rel="stylesheet" href="../css/style.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body class="bg-gray-100 text-gray-800">
    <nav class="bg-gray-800 text-white">
        <div class="container mx-auto px-4 py-3">
            <div class="flex flex-wrap justify-between items-center">
                <a class="text-xl font-bold" href="dashboard.php">OpFit Admin</a>
                <button class="md:hidden" type="button" id="navbarToggle" onclick="toggleMobileMenu()"> 
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
                <h1 class="text-2xl font-bold">User Management</h1>
            </div>
            <div class="w-full md:w-1/2 px-2 flex justify-end">
                <button type="button" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded flex items-center" id="addUserBtn">
                    <i class="fas fa-plus mr-2"></i> Add New User
                </button>
            </div>
        </div>
        
        <?php if (isset($error)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if (isset($success)): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <!-- Filters -->
        <div class="bg-white rounded-lg shadow-md mb-6">
            <div class="p-4">
                <form action="users.php" method="get" class="flex flex-wrap -mx-2">
                    <div class="w-full md:w-1/3 px-2 mb-4 md:mb-0">
                        <label for="type" class="block text-gray-700 mb-2">User Type</label>
                        <select name="type" id="type" class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">All Types</option>
                            <option value="customer" <?php echo ($user_type_filter == 'customer') ? 'selected' : ''; ?>>Customers</option>
                            <option value="coach" <?php echo ($user_type_filter == 'coach') ? 'selected' : ''; ?>>Coaches</option>
                            <option value="admin" <?php echo ($user_type_filter == 'admin') ? 'selected' : ''; ?>>Admins</option>
                        </select>
                    </div>
                    <div class="w-full md:w-1/2 px-2 mb-4 md:mb-0">
                        <label for="search" class="block text-gray-700 mb-2">Search</label>
                        <input type="text" class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500" id="search" name="search" placeholder="Search by name or email" value="<?php echo $search; ?>">
                    </div>
                    <div class="w-full md:w-1/6 px-2 flex items-end">
                        <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded">Filter</button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Users Table -->
        <div class="bg-white rounded-lg shadow-md">
            <div class="p-4">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left">ID</th>
                                <th class="px-4 py-2 text-left">Name</th>
                                <th class="px-4 py-2 text-left">Email</th>
                                <th class="px-4 py-2 text-left">User Type</th>
                                <th class="px-4 py-2 text-left">Joined</th>
                                <th class="px-4 py-2 text-left">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($user = mysqli_fetch_assoc($result)): ?>
                                <tr class="hover:bg-gray-50 border-t">
                                    <td class="px-4 py-2"><?php echo $user['id']; ?></td>
                                    <td class="px-4 py-2"><?php echo $user['name']; ?></td>
                                    <td class="px-4 py-2"><?php echo $user['email']; ?></td>
                                    <td class="px-4 py-2">
                                        <?php if ($user['user_type'] == 'customer'): ?>
                                            <span class="bg-blue-500 text-white text-xs px-2 py-1 rounded-full uppercase">Customer</span>
                                        <?php elseif ($user['user_type'] == 'coach'): ?>
                                            <span class="bg-green-500 text-white text-xs px-2 py-1 rounded-full uppercase">Coach</span>
                                        <?php elseif ($user['user_type'] == 'admin'): ?>
                                            <span class="bg-red-500 text-white text-xs px-2 py-1 rounded-full uppercase">Admin</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-2"><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                    <td class="px-4 py-2">
                                        <a href="user_details.php?id=<?php echo $user['id']; ?>" class="text-blue-600 hover:text-blue-800 mr-2" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="edit_user.php?id=<?php echo $user['id']; ?>" class="text-yellow-600 hover:text-yellow-800 mr-2" title="Edit User">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <?php if ($user['id'] != $user_id): ?>
                                            <a href="#" onclick="confirmDelete(<?php echo $user['id']; ?>)" class="text-red-600 hover:text-red-800" title="Delete User">
                                                <i class="fas fa-trash-alt"></i>
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Add User Modal -->
    <div id="addUserModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-full max-w-md shadow-lg rounded-md bg-white">
            <div class="flex justify-between items-center pb-3">
                <h3 class="text-lg font-semibold">Add New User</h3>
                <button id="closeModal" class="text-gray-400 hover:text-gray-500">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <form action="users.php" method="post">
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
                </div>
                <div class="mb-4">
                    <label for="user_type" class="block text-gray-700 mb-2">User Type</label>
                    <select class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500" id="user_type" name="user_type" required>
                        <option value="customer">Customer</option>
                        <option value="coach">Coach</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                <div class="flex justify-end pt-2">
                    <button type="button" id="cancelButton" class="bg-white hover:bg-gray-100 text-gray-700 font-medium py-2 px-4 border border-gray-300 rounded mr-2">
                        Cancel
                    </button>
                    <button type="submit" name="add_user" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded">
                        Add User
                    </button>
                </div>
            </form>
        </div>
    </div>

    <?php include_once '../includes/footer.php'; ?>

    <script src="../js/tailwind-utilities.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
        // Toggle mobile navigation function
        function toggleMobileMenu() {
            console.log('Toggle function called directly');
            const menu = document.getElementById('navbarMenu');
            if (menu) {
                if (menu.classList.contains('hidden')) {
                    menu.classList.remove('hidden');
                    menu.classList.add('block');
                } else {
                    menu.classList.add('hidden');
                    menu.classList.remove('block');
                }
            }
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

            // Modal functionality
            const modal = document.getElementById('addUserModal');
            const addUserBtn = document.getElementById('addUserBtn');
            const closeModal = document.getElementById('closeModal');
            const cancelButton = document.getElementById('cancelButton');
            
            // Open modal
            addUserBtn.addEventListener('click', function() {
                modal.classList.remove('hidden');
            });
            
            // Close modal functions
            function closeModalFunc() {
                modal.classList.add('hidden');
            }
            
            closeModal.addEventListener('click', closeModalFunc);
            cancelButton.addEventListener('click', closeModalFunc);
            
            // Close modal when clicking outside
            modal.addEventListener('click', function(e) {
                if (e.target === modal) {
                    closeModalFunc();
                }
            });
        });
        
        // Confirm delete
        function confirmDelete(userId) {
            if (confirm('Are you sure you want to delete this user? This action cannot be undone.')) {
                window.location.href = 'users.php?delete=' + userId;
            }
        }
    </script>
</body>
</html> 