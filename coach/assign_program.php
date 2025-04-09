<?php
session_start();

include_once '../includes/db_connect.php';
include_once '../includes/functions.php';

// Check if user is logged in and is a coach
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'coach') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

// Get program ID from URL
if (isset($_GET['program_id'])) {
    $program_id = (int)$_GET['program_id'];
    
    // Get program details
    $program_query = "SELECT * FROM programs WHERE id = $program_id";
    $program_result = mysqli_query($conn, $program_query);
    
    if (mysqli_num_rows($program_result) > 0) {
        $program = mysqli_fetch_assoc($program_result);
        
        // Get program steps
        $program_steps = get_program_steps($program_id);
    } else {
        header("Location: programs.php");
        exit();
    }
} else {
    header("Location: programs.php");
    exit();
}

// Get coach's customers
$customers = get_coach_customers($user_id);

// Handle form submission to assign program
if (isset($_POST['assign_program'])) {
    $customer_id = (int)$_POST['customer_id'];
    
    // Check if customer is already assigned to this program
    $check_query = "SELECT * FROM customer_programs 
                   WHERE customer_id = $customer_id 
                   AND program_id = $program_id
                   AND status IN ('pending', 'approved')";
    $check_result = mysqli_query($conn, $check_query);
    
    if (mysqli_num_rows($check_result) > 0) {
        $assign_error = "This customer is already assigned to this program";
    } else {
        // Assign program to customer
        $assign_query = "INSERT INTO customer_programs (customer_id, program_id, status) 
                        VALUES ($customer_id, $program_id, 'approved')";
        
        if (mysqli_query($conn, $assign_query)) {
            // Initialize step progress for this customer
            initialize_step_progress($customer_id, $program_id);
            $assign_success = "Program assigned successfully with step-by-step progress tracking";
        } else {
            $assign_error = "Failed to assign program: " . mysqli_error($conn);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assign Program - OpFit Coach</title>
    <link rel="stylesheet" href="../css/style.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body class="bg-gray-100">
    <nav class="bg-gray-800 text-white">
        <div class="container mx-auto px-4 py-3">
            <div class="flex flex-wrap justify-between items-center">
                <a class="text-xl font-bold" href="dashboard.php">OpFit Coach</a>
                <button class="md:hidden" type="button" id="navbarToggle">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>
                <div class="hidden md:flex flex-col md:flex-row w-full md:w-auto mt-4 md:mt-0" id="navbarMenu">
                    <ul class="flex flex-col md:flex-row md:mr-6 space-y-2 md:space-y-0 md:space-x-4">
                        <li>
                            <a class="text-gray-300 hover:text-white block py-2" href="dashboard.php">Dashboard</a>
                        </li>
                        <li>
                            <a class="text-gray-300 hover:text-white block py-2" href="customers.php">My Customers</a>
                        </li>
                        <li>
                            <a class="text-white font-medium block py-2" href="programs.php">Programs</a>
                        </li>
                    </ul>
                    <div class="relative mt-4 md:mt-0 md:ml-4" id="userDropdownContainer">
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
        <div class="flex flex-wrap mb-4">
            <div class="w-full">
                <h1 class="text-2xl font-bold mb-4">Assign Program</h1>
                
                <?php if (isset($assign_success)): ?>
                    <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4" role="alert">
                        <p><?php echo $assign_success; ?></p>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($assign_error)): ?>
                    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">
                        <p><?php echo $assign_error; ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="flex flex-wrap -mx-2">
            <div class="w-full md:w-1/2 px-2 mb-4">
                <div class="bg-white rounded-lg shadow-md mb-4">
                    <div class="bg-blue-600 text-white py-3 px-4 rounded-t-lg">
                        <h5 class="font-semibold">Program Details</h5>
                    </div>
                    <div class="p-4">
                        <h4 class="text-xl font-semibold mb-2"><?php echo $program['name']; ?></h4>
                        <span class="inline-block px-3 py-1 text-sm rounded-full 
                            <?php 
                            if ($program['difficulty'] == 'beginner') echo 'bg-green-100 text-green-800';
                            else if ($program['difficulty'] == 'intermediate') echo 'bg-yellow-100 text-yellow-800';
                            else echo 'bg-red-100 text-red-800';
                            ?>">
                            <?php echo ucfirst($program['difficulty']); ?>
                        </span>
                        <p class="mt-3 text-gray-700"><?php echo $program['description']; ?></p>
                        <p class="mt-2"><strong>Duration:</strong> <?php echo $program['duration']; ?> weeks</p>
                    </div>
                </div>
                
                <!-- Program Steps -->
                <div class="bg-white rounded-lg shadow-md">
                    <div class="bg-blue-400 text-white py-3 px-4 rounded-t-lg">
                        <h5 class="font-semibold">Program Steps</h5>
                    </div>
                    <div class="p-4">
                        <?php if (count($program_steps) > 0): ?>
                            <ol class="border border-gray-200 rounded-md divide-y divide-gray-200">
                                <?php foreach ($program_steps as $step): ?>
                                    <li class="p-4 flex">
                                        <div class="ml-3">
                                            <div class="font-medium"><?php echo $step['title']; ?></div>
                                            <?php echo nl2br($step['description']); ?>
                                            <div class="mt-1 text-sm text-gray-500 flex items-center">
                                                <i class="fas fa-clock mr-1"></i> <?php echo $step['duration']; ?>
                                            </div>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ol>
                        <?php else: ?>
                            <p class="text-gray-500">No steps defined for this program.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="w-full md:w-1/2 px-2">
                <div class="bg-white rounded-lg shadow-md">
                    <div class="bg-blue-600 text-white py-3 px-4 rounded-t-lg">
                        <h5 class="font-semibold">Assign to Customer</h5>
                    </div>
                    <div class="p-4">
                        <?php if (count($customers) > 0): ?>
                            <form action="" method="post">
                                <div class="mb-4">
                                    <label for="customer_id" class="block text-sm font-medium text-gray-700 mb-1">Select Customer</label>
                                    <select name="customer_id" id="customer_id" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                                        <option value="">-- Select Customer --</option>
                                        <?php foreach ($customers as $customer): ?>
                                            <option value="<?php echo $customer['customer_id']; ?>">
                                                <?php echo $customer['name']; ?> (<?php echo $customer['email']; ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="flex space-x-2">
                                    <button type="submit" name="assign_program" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded inline-flex items-center">
                                        <i class="fas fa-check mr-2"></i> Assign Program
                                    </button>
                                    <a href="programs.php" class="bg-gray-500 hover:bg-gray-600 text-white font-medium py-2 px-4 rounded inline-flex items-center">
                                        <i class="fas fa-arrow-left mr-2"></i> Back to Programs
                                    </a>
                                </div>
                                
                                <div class="mt-6 bg-blue-50 border-l-4 border-blue-400 p-4 text-blue-700">
                                    <i class="fas fa-info-circle mr-2"></i>
                                    <strong>Note:</strong> When you assign this program, a step-by-step progress tracker will be created for the customer. They will start at step 1 and progress through each step in order.
                                </div>
                            </form>
                        <?php else: ?>
                            <p class="text-gray-500">You don't have any customers to assign programs to.</p>
                            <a href="programs.php" class="mt-4 bg-gray-500 hover:bg-gray-600 text-white font-medium py-2 px-4 rounded inline-flex items-center">
                                <i class="fas fa-arrow-left mr-2"></i> Back to Programs
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Toggle mobile menu
            const navbarToggle = document.getElementById('navbarToggle');
            const navbarMenu = document.getElementById('navbarMenu');
            
            navbarToggle.addEventListener('click', function() {
                navbarMenu.classList.toggle('hidden');
            });
            
            // Toggle user dropdown
            const userDropdownButton = document.getElementById('userDropdown');
            const userDropdownMenu = document.getElementById('userDropdownMenu');
            
            userDropdownButton.addEventListener('click', function(e) {
                e.stopPropagation();
                userDropdownMenu.classList.toggle('hidden');
            });
            
            // Close dropdown when clicking outside
            document.addEventListener('click', function(e) {
                if (!userDropdownButton.contains(e.target) && !userDropdownMenu.contains(e.target)) {
                    userDropdownMenu.classList.add('hidden');
                }
            });
            
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
        });
    </script>
</body>
</html> 