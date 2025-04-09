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

// Process approve/reject request
if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $request_id = (int)$_GET['id'];
    
    // Get request details to find customer and program
    $check_query = "SELECT customer_id, program_id FROM customer_programs WHERE id = $request_id";
    $check_result = mysqli_query($conn, $check_query);
    
    if (mysqli_num_rows($check_result) > 0) {
        $request = mysqli_fetch_assoc($check_result);
        $customer_id = $request['customer_id'];
        $program_id = $request['program_id'];
        
        if ($action == 'approve') {
            $status = 'approved';
            $success_msg = "Request approved successfully";
        } elseif ($action == 'reject') {
            $status = 'rejected';
            $success_msg = "Request rejected successfully";
        } elseif ($action == 'complete') {
            $status = 'completed';
            $success_msg = "Program marked as completed";
        } else {
            header("Location: requests.php");
            exit();
        }
        
        // Update status
        if (update_program_status($program_id, $customer_id, $status)) {
            $success = $success_msg;
        } else {
            $error = "Failed to update request status";
        }
    } else {
        $error = "Invalid request ID";
    }
}

// Process filter
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Build query based on filters
$query = "SELECT cp.*, u.name as customer_name, u.email as customer_email, p.name as program_name, p.difficulty
          FROM customer_programs cp
          JOIN users u ON cp.customer_id = u.id
          JOIN programs p ON cp.program_id = p.id";

$where_clauses = [];
if (!empty($status_filter)) {
    $where_clauses[] = "cp.status = '$status_filter'";
}

if (!empty($search)) {
    $where_clauses[] = "(u.name LIKE '%$search%' OR u.email LIKE '%$search%' OR p.name LIKE '%$search%')";
}

if (!empty($where_clauses)) {
    $query .= " WHERE " . implode(' AND ', $where_clauses);
}

$query .= " ORDER BY cp.created_at DESC";
$result = mysqli_query($conn, $query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Program Requests - OpFit Admin</title>
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
                            <a class="text-gray-300 hover:text-white block py-2" href="users.php">Users</a>
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
                            <a href="profile.php" class="block px-4 py-2 text-gray-700 hover:bg-gray-100">Profile</a>
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
                <h1 class="text-2xl font-bold">Program Requests</h1>
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
                <form action="requests.php" method="get" class="grid grid-cols-1 md:grid-cols-12 gap-4 items-end">
                    <div class="md:col-span-4">
                        <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                        <select name="status" id="status" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">All Statuses</option>
                            <option value="pending" <?php echo ($status_filter == 'pending') ? 'selected' : ''; ?>>Pending</option>
                            <option value="approved" <?php echo ($status_filter == 'approved') ? 'selected' : ''; ?>>Approved</option>
                            <option value="rejected" <?php echo ($status_filter == 'rejected') ? 'selected' : ''; ?>>Rejected</option>
                            <option value="completed" <?php echo ($status_filter == 'completed') ? 'selected' : ''; ?>>Completed</option>
                        </select>
                    </div>
                    <div class="md:col-span-6">
                        <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                        <input type="text" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" id="search" name="search" placeholder="Search by customer or program" value="<?php echo $search; ?>">
                    </div>
                    <div class="md:col-span-2">
                        <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded">Filter</button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Requests Table -->
        <div class="bg-white rounded-lg shadow-md">
            <div class="p-4">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Program</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Difficulty</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Request Date</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if (mysqli_num_rows($result) > 0): ?>
                                <?php while ($request = mysqli_fetch_assoc($result)): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 whitespace-nowrap"><?php echo $request['id']; ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-medium text-gray-900"><?php echo $request['customer_name']; ?></div>
                                            <div class="text-sm text-gray-500"><?php echo $request['customer_email']; ?></div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo $request['program_name']; ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php if ($request['difficulty'] == 'beginner'): ?>
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Beginner</span>
                                            <?php elseif ($request['difficulty'] == 'intermediate'): ?>
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">Intermediate</span>
                                            <?php elseif ($request['difficulty'] == 'advanced'): ?>
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">Advanced</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php if ($request['status'] == 'pending'): ?>
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">Pending</span>
                                            <?php elseif ($request['status'] == 'approved'): ?>
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Approved</span>
                                            <?php elseif ($request['status'] == 'rejected'): ?>
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">Rejected</span>
                                            <?php elseif ($request['status'] == 'completed'): ?>
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">Completed</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo date('M d, Y', strtotime($request['created_at'])); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <?php if ($request['status'] == 'pending'): ?>
                                                <a href="requests.php?action=approve&id=<?php echo $request['id']; ?>" class="bg-green-600 hover:bg-green-700 text-white text-xs font-medium py-1 px-2 rounded inline-flex items-center mr-1">
                                                    <i class="fas fa-check mr-1"></i> Approve
                                                </a>
                                                <a href="requests.php?action=reject&id=<?php echo $request['id']; ?>" class="bg-red-600 hover:bg-red-700 text-white text-xs font-medium py-1 px-2 rounded inline-flex items-center">
                                                    <i class="fas fa-times mr-1"></i> Reject
                                                </a>
                                            <?php elseif ($request['status'] == 'approved'): ?>
                                                <a href="requests.php?action=complete&id=<?php echo $request['id']; ?>" class="bg-blue-500 hover:bg-blue-600 text-white text-xs font-medium py-1 px-2 rounded inline-flex items-center">
                                                    <i class="fas fa-check-double mr-1"></i> Mark Completed
                                                </a>
                                            <?php else: ?>
                                                <button class="bg-gray-400 text-white text-xs font-medium py-1 px-2 rounded opacity-50 cursor-not-allowed" disabled>No Actions</button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="px-6 py-4 text-center text-sm text-gray-500">No requests found</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
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