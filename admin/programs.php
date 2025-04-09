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

// Process add program
if (isset($_POST['add_program'])) {
    $name = sanitize_input($_POST['name']);
    $description = sanitize_input($_POST['description']);
    $difficulty = sanitize_input($_POST['difficulty']);
    $duration = (int)$_POST['duration'];
    
    // Validate input
    if (empty($name) || empty($description) || empty($difficulty) || empty($duration)) {
        $error = "All fields are required";
    } else {
        // Add new program using prepared statement
        $stmt = mysqli_prepare($conn, "INSERT INTO programs (name, description, difficulty, duration) 
                                      VALUES (?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "sssi", $name, $description, $difficulty, $duration);
        
        if (mysqli_stmt_execute($stmt)) {
            $success = "Program added successfully";
        } else {
            $error = "Failed to add program: " . mysqli_error($conn);
        }
        mysqli_stmt_close($stmt);
    }
}

// Process delete program
if (isset($_GET['delete'])) {
    $delete_id = (int)$_GET['delete'];
    
    // Check if program is assigned to any customers using prepared statement
    $stmt = mysqli_prepare($conn, "SELECT COUNT(*) FROM customer_programs WHERE program_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $delete_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $count);
    mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);
    
    if ($count > 0) {
        $error = "Cannot delete: Program is assigned to one or more customers";
    } else {
        $stmt = mysqli_prepare($conn, "DELETE FROM programs WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $delete_id);
        
        if (mysqli_stmt_execute($stmt)) {
            $success = "Program deleted successfully";
        } else {
            $error = "Failed to delete program";
        }
        mysqli_stmt_close($stmt);
    }
}

// Get all programs
$query = "SELECT * FROM programs ORDER BY name";
$result = mysqli_query($conn, $query);

// Fetch all program steps in a single query
$steps_query = "SELECT * FROM program_steps ORDER BY program_id, step_number";
$steps_result = mysqli_query($conn, $steps_query);

// Group steps by program_id for efficient access
$program_steps = [];
while ($step = mysqli_fetch_assoc($steps_result)) {
    $program_id = $step['program_id'];
    if (!isset($program_steps[$program_id])) {
        $program_steps[$program_id] = [];
    }
    $program_steps[$program_id][] = $step;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Programs Management - OpFit Admin</title>
    <link rel="stylesheet" href="../css/style.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Custom styles that can't be easily replaced by Tailwind */
        .step-timeline {
            max-height: 400px;
            overflow-y: auto;
            padding-right: 10px;
        }
        
        .step-container {
            border-left: 3px solid #e5e7eb;
            padding-left: 20px;
            margin-bottom: 15px;
            position: relative;
        }
        
        .step-container:before {
            content: '';
            position: absolute;
            left: -10px;
            top: 15px;
            width: 17px;
            height: 17px;
            border-radius: 50%;
            background-color: #f9fafb;
            border: 3px solid #e5e7eb;
        }
        
        .program-card:hover {
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            transition: all 0.3s;
        }
        
        .badge {
            font-size: 0.85em;
        }
        
        .table th {
            position: sticky;
            top: 0;
            background: white;
            box-shadow: 0 1px 1px rgba(0,0,0,0.1);
            z-index: 10;
        }
        
        /* Modal optimizations */
        .modal {
            will-change: transform;
            transition: opacity 0.2s ease-out;
        }
        
        .modal-content {
            border: none;
            border-radius: 0.5rem;
            box-shadow: 0 0.5rem 2rem rgba(0, 0, 0, 0.15);
        }
        
        .modal-backdrop.show {
            opacity: 0.7;
        }
        
        .modal-header, .modal-footer {
            border: none;
        }
        
        /* Lazy loading spinner */
        .spinner-border {
            width: 3rem;
            height: 3rem;
        }
        
        /* Prevent content shift */
        .modal-dialog {
            margin: 1.75rem auto;
            max-width: 90%;
        }
        
        @media (min-width: 768px) {
            .modal-dialog {
                max-width: 700px;
            }
        }
        
        /* Improve scrolling performance */
        .modal-body {
            overflow-y: auto;
            -webkit-overflow-scrolling: touch;
            overscroll-behavior: contain;
        }
        
        /* Add transition for smoother opening/closing */
        .fade.modal.show {
            transition: opacity 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
    </style>
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
                            <a class="text-white font-medium block py-2" href="programs.php">Programs</a>
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
                    <div class="hidden md:block">
                        <div class="relative">
                            <div class="group">
                                <button class="flex items-center text-sm px-4 py-2 leading-none rounded text-white hover:text-white hover:bg-gray-700 focus:outline-none" id="userDropdown">
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
                <h1 class="text-2xl font-bold">Programs Management</h1>
            </div>
            <div class="w-full md:w-1/2 px-2 flex justify-end">
                <button type="button" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded flex items-center" id="addProgramBtn">
                    <i class="fas fa-plus mr-2"></i> Add New Program
                </button>
            </div>
        </div>
        
        <?php if ($error): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">
                <p><?php echo htmlspecialchars($error); ?></p>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4" role="alert">
                <p><?php echo htmlspecialchars($success); ?></p>
            </div>
        <?php endif; ?>
        
        <!-- Programs Table -->
        <div class="bg-white rounded-lg shadow-sm overflow-hidden">
            <div class="p-4">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200" id="programsTable">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Difficulty</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Duration (weeks)</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date Added</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php while ($program = mysqli_fetch_assoc($result)): ?>
                                <tr class="hover:bg-gray-50 transition duration-150">
                                    <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($program['name']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php if ($program['difficulty'] == 'beginner'): ?>
                                            <span class="inline-flex items-center px-3 py-1.5 text-sm font-medium rounded-md bg-green-100 text-green-800">
                                                <svg class="mr-1.5 h-4 w-4 text-green-500" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                                </svg>
                                                Beginner
                                            </span>
                                        <?php elseif ($program['difficulty'] == 'intermediate'): ?>
                                            <span class="inline-flex items-center px-3 py-1.5 text-sm font-medium rounded-md bg-yellow-100 text-yellow-800">
                                                <svg class="mr-1.5 h-4 w-4 text-yellow-500" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                                                    <path fill-rule="evenodd" d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" clip-rule="evenodd"></path>
                                                </svg>
                                                Intermediate
                                            </span>
                                        <?php elseif ($program['difficulty'] == 'advanced'): ?>
                                            <span class="inline-flex items-center px-3 py-1.5 text-sm font-medium rounded-md bg-red-100 text-red-800">
                                                <svg class="mr-1.5 h-4 w-4 text-red-500" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                                                </svg>
                                                Advanced
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($program['duration']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap"><?php echo date('M d, Y', strtotime($program['created_at'])); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                                        <button class="view-program-btn text-blue-600 hover:text-blue-900" data-id="<?php echo $program['id']; ?>">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <a href="edit_program.php?id=<?php echo $program['id']; ?>" class="text-amber-600 hover:text-amber-900">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button class="delete-program text-red-600 hover:text-red-900" data-id="<?php echo $program['id']; ?>">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Program View Modal (Tailwind CSS) -->
    <div id="programViewModal" class="fixed inset-0 z-50 overflow-y-auto hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <!-- Background overlay -->
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity modal-backdrop"></div>
            
            <!-- Modal panel -->
            <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-3xl sm:w-full">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                            <h3 class="text-lg leading-6 font-medium text-gray-900" id="programModalTitle">Program Details</h3>
                            <div class="mt-4" id="programModalContent">
                                <div class="flex justify-center items-center py-5">
                                    <svg class="animate-spin -ml-1 mr-3 h-10 w-10 text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    <p class="mt-1">Loading program details...</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <a href="#" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm" id="editProgramBtn">
                        <i class="fas fa-edit mr-2"></i> Edit Program
                    </a>
                    <button type="button" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm close-modal">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Program Modal (Tailwind CSS) -->
    <div id="addProgramModal" class="fixed inset-0 z-50 overflow-y-auto hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <!-- Background overlay -->
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity add-modal-backdrop"></div>
            
            <!-- Modal panel -->
            <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6">
                    <div class="sm:flex sm:items-start">
                        <div class="mt-3 text-center sm:mt-0 sm:text-left w-full">
                            <h3 class="text-lg leading-6 font-medium text-gray-900" id="addProgramModalLabel">Add New Program</h3>
                            <div class="mt-4">
                                <form action="programs.php" method="post" id="addProgramForm">
                                    <div class="mb-4">
                                        <label for="name" class="block text-sm font-medium text-gray-700">Program Name</label>
                                        <input type="text" class="mt-1 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md" id="name" name="name" required>
                                    </div>
                                    <div class="mb-4">
                                        <label for="description" class="block text-sm font-medium text-gray-700">Description</label>
                                        <textarea class="mt-1 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md" id="description" name="description" rows="4" required></textarea>
                                    </div>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                        <div>
                                            <label for="difficulty" class="block text-sm font-medium text-gray-700">Difficulty</label>
                                            <select class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" id="difficulty" name="difficulty" required>
                                                <option value="">Select Difficulty</option>
                                                <option value="beginner">Beginner</option>
                                                <option value="intermediate">Intermediate</option>
                                                <option value="advanced">Advanced</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label for="duration" class="block text-sm font-medium text-gray-700">Duration (weeks)</label>
                                            <input type="number" class="mt-1 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md" id="duration" name="duration" min="1" max="52" required>
                                        </div>
                                    </div>
                                    <div class="mt-5 sm:mt-4 sm:flex sm:flex-row-reverse">
                                        <button type="submit" name="add_program" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm">
                                            Add Program
                                        </button>
                                        <button type="button" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:w-auto sm:text-sm close-add-modal">
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
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
            
            // Toggle mobile navigation
            const navbarToggle = document.getElementById('navbarToggle');
            const navbarMenu = document.getElementById('navbarMenu');
            
            navbarToggle.addEventListener('click', function() {
                navbarMenu.classList.toggle('hidden');
            });

            // Delete confirmation
            const deleteButtons = document.querySelectorAll('.delete-program');
            deleteButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const programId = this.getAttribute('data-id');
                    if (confirm('Are you sure you want to delete this program?')) {
                        window.location.href = 'programs.php?delete=' + programId;
                    }
                });
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
            
            // Program details modal handling
            const modal = document.getElementById('programViewModal');
            const modalTitle = document.getElementById('programModalTitle');
            const modalContent = document.getElementById('programModalContent');
            const editProgramBtn = document.getElementById('editProgramBtn');
            const closeButtons = document.querySelectorAll('.close-modal');
            const modalBackdrop = document.querySelector('.modal-backdrop');
            
            // Add program modal handling
            const addModal = document.getElementById('addProgramModal');
            const addProgramBtn = document.getElementById('addProgramBtn');
            const closeAddModalButtons = document.querySelectorAll('.close-add-modal');
            const addModalBackdrop = document.querySelector('.add-modal-backdrop');
            
            // Program data cache
            const programCache = {};
            
            // Open modal function for program details
            function openModal() {
                document.body.classList.add('overflow-hidden');
                modal.classList.remove('hidden');
            }
            
            // Close modal function for program details
            function closeModal() {
                document.body.classList.remove('overflow-hidden');
                modal.classList.add('hidden');
            }
            
            // Open modal function for add program
            function openAddModal() {
                document.body.classList.add('overflow-hidden');
                addModal.classList.remove('hidden');
            }
            
            // Close modal function for add program
            function closeAddModal() {
                document.body.classList.remove('overflow-hidden');
                addModal.classList.add('hidden');
            }
            
            // Add program button click
            addProgramBtn.addEventListener('click', openAddModal);
            
            // Close add program modal when clicking close buttons
            closeAddModalButtons.forEach(button => {
                button.addEventListener('click', closeAddModal);
            });
            
            // Close add program modal when clicking outside
            if (addModalBackdrop) {
                addModalBackdrop.addEventListener('click', function(e) {
                    if (e.target === addModalBackdrop) {
                        closeAddModal();
                    }
                });
            }
            
            // Add event listeners to view buttons
            document.querySelectorAll('.view-program-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const programId = this.getAttribute('data-id');
                    
                    // Show loading state
                    modalTitle.textContent = 'Loading...';
                    modalContent.innerHTML = `
                        <div class="flex justify-center items-center py-5">
                            <svg class="animate-spin -ml-1 mr-3 h-10 w-10 text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <p class="mt-1">Loading program details...</p>
                        </div>
                    `;
                    
                    // Update edit button link
                    editProgramBtn.href = `edit_program.php?id=${programId}`;
                    
                    // Show modal
                    openModal();
                    
                    // Check if we have cached data
                    if (programCache[programId]) {
                        renderProgramData(programCache[programId]);
                        return;
                    }
                    
                    // Fetch program data
                    fetchProgramData(programId);
                });
            });
            
            // Close program details modal when clicking close buttons
            closeButtons.forEach(button => {
                button.addEventListener('click', closeModal);
            });
            
            // Close program details modal when clicking outside
            if (modalBackdrop) {
                modalBackdrop.addEventListener('click', function(e) {
                    if (e.target === modalBackdrop) {
                        closeModal();
                    }
                });
            }
            
            // Close modals with Escape key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    if (!modal.classList.contains('hidden')) {
                        closeModal();
                    }
                    if (!addModal.classList.contains('hidden')) {
                        closeAddModal();
                    }
                }
            });
            
            // Function to fetch program data
            function fetchProgramData(programId) {
                // Simulate network latency
                setTimeout(() => {
                    const programs = <?php 
                        $programsJson = [];
                        mysqli_data_seek($result, 0); // Reset result pointer
                        while ($p = mysqli_fetch_assoc($result)) {
                            $p['steps'] = isset($program_steps[$p['id']]) ? $program_steps[$p['id']] : [];
                            $programsJson[$p['id']] = $p;
                        }
                        echo json_encode($programsJson);
                    ?>;
                    
                    const programData = programs[programId];
                    
                    // Cache the data
                    programCache[programId] = programData;
                    
                    // Render the data
                    renderProgramData(programData);
                }, 300);
            }
            
            // Function to render program data
            function renderProgramData(program) {
                // Update modal title
                modalTitle.textContent = program.name;
                
                // Build HTML content
                let difficultyBadge = '';
                if (program.difficulty === 'beginner') {
                    difficultyBadge = `
                        <span class="inline-flex items-center px-3 py-1.5 text-sm font-medium rounded-md bg-green-100 text-green-800">
                            <svg class="mr-1.5 h-4 w-4 text-green-500" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                            </svg>
                            Beginner
                        </span>`;
                } else if (program.difficulty === 'intermediate') {
                    difficultyBadge = `
                        <span class="inline-flex items-center px-3 py-1.5 text-sm font-medium rounded-md bg-yellow-100 text-yellow-800">
                            <svg class="mr-1.5 h-4 w-4 text-yellow-500" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                                <path fill-rule="evenodd" d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" clip-rule="evenodd"></path>
                            </svg>
                            Intermediate
                        </span>`;
                } else if (program.difficulty === 'advanced') {
                    difficultyBadge = `
                        <span class="inline-flex items-center px-3 py-1.5 text-sm font-medium rounded-md bg-red-100 text-red-800">
                            <svg class="mr-1.5 h-4 w-4 text-red-500" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                            </svg>
                            Advanced
                        </span>`;
                }
                
                let stepsHtml = '';
                if (program.steps && program.steps.length > 0) {
                    stepsHtml = '<div class="max-h-96 overflow-y-auto pr-2 space-y-3">';
                    program.steps.forEach(step => {
                        stepsHtml += `
                            <div class="bg-white border border-gray-200 rounded-lg shadow-sm">
                                <div class="bg-gray-50 px-4 py-2 border-b border-gray-200">
                                    <strong class="text-gray-700">Step ${step.step_number}:</strong> ${step.title}
                                </div>
                                <div class="p-4">
                                    <p class="text-gray-700 mb-1">${step.description}</p>
                                    ${step.duration ? `<p class="text-gray-500 text-sm"><i class="far fa-clock mr-1"></i> ${step.duration}</p>` : ''}
                                </div>
                            </div>
                        `;
                    });
                    stepsHtml += '</div>';
                } else {
                    stepsHtml = `
                        <div class="bg-blue-50 border-l-4 border-blue-400 p-4">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-info-circle text-blue-400"></i>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm text-blue-700">No steps defined for this program yet.</p>
                                </div>
                            </div>
                        </div>
                    `;
                }
                
                // Construct and set content
                const content = `
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                        <div class="font-semibold text-gray-700">Difficulty:</div>
                        <div class="md:col-span-2">
                            ${difficultyBadge}
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                        <div class="font-semibold text-gray-700">Duration:</div>
                        <div class="md:col-span-2">${program.duration} weeks</div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                        <div class="font-semibold text-gray-700">Description:</div>
                        <div class="md:col-span-2">
                            <div class="max-h-40 overflow-y-auto">
                                <p class="text-gray-700">${program.description.replace(/(\r\n|\r|\n)/g, '<br>')}</p>
                            </div>
                        </div>
                    </div>
                    
                    <h5 class="font-medium text-gray-900 mb-3">Step-by-Step Process</h5>
                    ${stepsHtml}
                `;
                
                modalContent.innerHTML = content;
            }
            
            // Clean up when modal is hidden
            window.addEventListener('beforeunload', function() {
                // Clear memory/references
                programCache = {};
            });
        });
    </script>
</body>
</html> 