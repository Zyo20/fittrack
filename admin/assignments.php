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

// Process assign coach
if (isset($_POST['assign_coach'])) {
    $coach_id = (int)$_POST['coach_id'];
    $customer_id = (int)$_POST['customer_id'];
    
    // Validate input
    if (empty($coach_id) || empty($customer_id)) {
        $error = "Please select both coach and customer";
    } else {
        // Check if assignment already exists
        $check_query = "SELECT * FROM coach_customer WHERE coach_id = $coach_id AND customer_id = $customer_id";
        $check_result = mysqli_query($conn, $check_query);
        
        if (mysqli_num_rows($check_result) > 0) {
            $error = "This customer is already assigned to this coach";
        } else {
            // Add new assignment
            $query = "INSERT INTO coach_customer (coach_id, customer_id) VALUES ($coach_id, $customer_id)";
            
            if (mysqli_query($conn, $query)) {
                $success = "Coach assigned successfully";
            } else {
                $error = "Failed to assign coach: " . mysqli_error($conn);
            }
        }
    }
}

// Process remove assignment
if (isset($_GET['remove'])) {
    $assignment_id = (int)$_GET['remove'];
    
    $delete_query = "DELETE FROM coach_customer WHERE id = $assignment_id";
    if (mysqli_query($conn, $delete_query)) {
        $success = "Assignment removed successfully";
    } else {
        $error = "Failed to remove assignment";
    }
}

// Get all coaches
$coaches_query = "SELECT * FROM users WHERE user_type = 'coach' ORDER BY name";
$coaches_result = mysqli_query($conn, $coaches_query);

// Get all customers
$customers_query = "SELECT * FROM users WHERE user_type = 'customer' ORDER BY name";
$customers_result = mysqli_query($conn, $customers_query);

// Get all assignments with details
$assignments_query = "SELECT cc.*, coach.name as coach_name, customer.name as customer_name
                     FROM coach_customer cc
                     JOIN users coach ON cc.coach_id = coach.id
                     JOIN users customer ON cc.customer_id = customer.id
                     ORDER BY coach.name, customer.name";
$assignments_result = mysqli_query($conn, $assignments_query);

// Get unassigned customers
$unassigned_query = "SELECT * FROM users c WHERE c.user_type = 'customer' 
                    AND NOT EXISTS (SELECT 1 FROM coach_customer cc WHERE cc.customer_id = c.id)
                    ORDER BY c.name";
$unassigned_result = mysqli_query($conn, $unassigned_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Coach Assignments - OpFit Admin</title>
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
                            <a class="text-white font-medium block py-2" href="assignments.php">Coach Assignments</a>
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
                <h1 class="text-2xl font-bold">Coach Assignments</h1>
            </div>
            <div class="w-full md:w-1/2 px-2 flex justify-end">
                <button type="button" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded flex items-center" id="assignCoachBtn">
                    <i class="fas fa-plus mr-2"></i> Assign Coach
                </button>
            </div>
        </div>
        
        <?php if (isset($error)): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">
                <p><?php echo $error; ?></p>
            </div>
        <?php endif; ?>
        
        <?php if (isset($success)): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4" role="alert">
                <p><?php echo $success; ?></p>
            </div>
        <?php endif; ?>
        
        <!-- Info alert about customer self-selection -->
        <div class="bg-blue-100 border-l-4 border-blue-500 text-blue-700 p-4 mb-4" role="alert">
            <div class="flex">
                <div class="flex-shrink-0">
                    <i class="fas fa-info-circle mt-0.5"></i>
                </div>
                <div class="ml-3">
                    <p>Customers can now select their own coach when they first log in. You can still manually assign or reassign coaches here if needed.</p>
                </div>
            </div>
        </div>
        
        <div class="flex flex-wrap -mx-2">
            <div class="w-full md:w-2/3 px-2 mb-4">
                <!-- Current Assignments -->
                <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                    <div class="bg-blue-600 text-white px-4 py-3">
                        <h5 class="font-medium">Current Assignments</h5>
                    </div>
                    <div class="p-4">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Coach</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date Assigned</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php if (mysqli_num_rows($assignments_result) > 0): ?>
                                        <?php while ($assignment = mysqli_fetch_assoc($assignments_result)): ?>
                                            <tr class="hover:bg-gray-50 transition duration-150">
                                                <td class="px-6 py-4 whitespace-nowrap"><?php echo $assignment['coach_name']; ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap"><?php echo $assignment['customer_name']; ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap"><?php echo date('M d, Y', strtotime($assignment['assigned_date'])); ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <a href="assignments.php?remove=<?php echo $assignment['id']; ?>" class="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500" onclick="return confirm('Are you sure you want to remove this assignment?')">
                                                        <i class="fas fa-times mr-1.5"></i> Remove
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="4" class="px-6 py-4 text-center text-sm text-gray-500">No assignments found</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="w-full md:w-1/3 px-2 mb-4">
                <!-- Unassigned Customers -->
                <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                    <div class="bg-amber-500 text-white px-4 py-3">
                        <h5 class="font-medium">Unassigned Customers</h5>
                    </div>
                    <div class="p-4">
                        <?php if (mysqli_num_rows($unassigned_result) > 0): ?>
                            <div class="space-y-2">
                                <?php while ($customer = mysqli_fetch_assoc($unassigned_result)): ?>
                                    <div class="border border-gray-200 rounded-md p-3 hover:bg-gray-50 transition duration-150">
                                        <div class="flex justify-between items-center">
                                            <h6 class="font-medium"><?php echo $customer['name']; ?></h6>
                                            <button type="button" class="inline-flex items-center p-1.5 border border-transparent text-xs font-medium rounded text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500" 
                                                    onclick="setCustomer(<?php echo $customer['id']; ?>, '<?php echo $customer['name']; ?>')" 
                                                    data-customer-id="<?php echo $customer['id']; ?>">
                                                <i class="fas fa-user-plus"></i>
                                            </button>
                                        </div>
                                        <p class="text-sm text-gray-500 mt-1"><?php echo $customer['email']; ?></p>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-center text-gray-500">All customers have been assigned to coaches</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Assign Coach Modal (Tailwind CSS) -->
    <div id="assignCoachModal" class="fixed inset-0 z-50 overflow-y-auto hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <!-- Background overlay -->
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity modal-backdrop"></div>
            
            <!-- Modal panel -->
            <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6">
                    <div class="sm:flex sm:items-start">
                        <div class="mt-3 text-center sm:mt-0 sm:text-left w-full">
                            <h3 class="text-lg leading-6 font-medium text-gray-900" id="assignCoachModalLabel">Assign Coach to Customer</h3>
                            <div class="mt-4">
                                <form action="assignments.php" method="post">
                                    <div class="mb-4">
                                        <label for="coach_id" class="block text-sm font-medium text-gray-700">Coach</label>
                                        <select class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" id="coach_id" name="coach_id" required>
                                            <option value="">Select Coach</option>
                                            <?php mysqli_data_seek($coaches_result, 0); // Reset pointer to beginning ?>
                                            <?php while ($coach = mysqli_fetch_assoc($coaches_result)): ?>
                                                <option value="<?php echo $coach['id']; ?>"><?php echo $coach['name']; ?> (<?php echo $coach['email']; ?>)</option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>
                                    <div class="mb-4">
                                        <label for="customer_id" class="block text-sm font-medium text-gray-700">Customer</label>
                                        <select class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" id="customer_id" name="customer_id" required>
                                            <option value="">Select Customer</option>
                                            <?php mysqli_data_seek($customers_result, 0); // Reset pointer to beginning ?>
                                            <?php while ($customer = mysqli_fetch_assoc($customers_result)): ?>
                                                <option value="<?php echo $customer['id']; ?>"><?php echo $customer['name']; ?> (<?php echo $customer['email']; ?>)</option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>
                                    <div class="mt-5 sm:mt-4 sm:flex sm:flex-row-reverse">
                                        <button type="submit" name="assign_coach" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm">
                                            Assign Coach
                                        </button>
                                        <button type="button" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:w-auto sm:text-sm close-modal">
                                            Cancel
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include_once '../includes/footer.php'; ?>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Navbar toggle for mobile
            const navbarToggle = document.getElementById('navbarToggle');
            const navbarMenu = document.getElementById('navbarMenu');
            
            if (navbarToggle) {
                navbarToggle.addEventListener('click', function() {
                    navbarMenu.classList.toggle('hidden');
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
            
            // Modal handling
            const modal = document.getElementById('assignCoachModal');
            const assignCoachBtn = document.getElementById('assignCoachBtn');
            const closeButtons = document.querySelectorAll('.close-modal');
            const modalBackdrop = document.querySelector('.modal-backdrop');
            
            // Open modal function
            function openModal() {
                document.body.classList.add('overflow-hidden');
                modal.classList.remove('hidden');
            }
            
            // Close modal function
            function closeModal() {
                document.body.classList.remove('overflow-hidden');
                modal.classList.add('hidden');
            }
            
            // Assign coach button click
            if (assignCoachBtn) {
                assignCoachBtn.addEventListener('click', openModal);
            }
            
            // Quick assign buttons for unassigned customers
            const quickAssignButtons = document.querySelectorAll('[data-customer-id]');
            quickAssignButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const customerId = this.getAttribute('data-customer-id');
                    document.getElementById('customer_id').value = customerId;
                    openModal();
                });
            });
            
            // Close modal when clicking close buttons
            closeButtons.forEach(button => {
                button.addEventListener('click', closeModal);
            });
            
            // Close modal when clicking outside
            if (modalBackdrop) {
                modalBackdrop.addEventListener('click', function(e) {
                    if (e.target === modalBackdrop) {
                        closeModal();
                    }
                });
            }
            
            // Close modal with Escape key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && !modal.classList.contains('hidden')) {
                    closeModal();
                }
            });
            
            // Form submission
            const form = document.querySelector('form');
            if (form) {
                form.addEventListener('submit', function() {
                    // You could add validation here if needed
                });
            }
            
            // Auto-hide alerts after 5 seconds
            const alerts = document.querySelectorAll('[role="alert"]');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.classList.add('opacity-0');
                    setTimeout(() => {
                        alert.remove();
                    }, 300);
                }, 5000);
            });
            
            // Function to set customer ID in the form
            window.setCustomer = function(customerId, customerName) {
                document.getElementById('customer_id').value = customerId;
                openModal();
            };
        });
    </script>
</body>
</html> 