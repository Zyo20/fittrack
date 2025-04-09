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

// Check if program ID is provided
if (!isset($_GET['id'])) {
    header("Location: programs.php");
    exit();
}

$program_id = (int)$_GET['id'];

// Get program details using prepared statement
$stmt = mysqli_prepare($conn, "SELECT * FROM programs WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $program_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) == 0) {
    header("Location: programs.php");
    exit();
}

$program = mysqli_fetch_assoc($result);

// Get program steps
$steps_stmt = mysqli_prepare($conn, "SELECT * FROM program_steps WHERE program_id = ? ORDER BY step_number");
mysqli_stmt_bind_param($steps_stmt, "i", $program_id);
mysqli_stmt_execute($steps_stmt);
$steps_result = mysqli_stmt_get_result($steps_stmt);
$steps = [];
while ($step = mysqli_fetch_assoc($steps_result)) {
    $steps[] = $step;
}

// Process program update
if (isset($_POST['update_program'])) {
    $name = sanitize_input($_POST['name']);
    $description = sanitize_input($_POST['description']);
    $difficulty = sanitize_input($_POST['difficulty']);
    $duration = (int)$_POST['duration'];
    
    // Validate input
    if (empty($name) || empty($description) || empty($difficulty) || empty($duration)) {
        $error = "All fields are required";
    } else {
        // Update program using prepared statement
        $update_stmt = mysqli_prepare($conn, "UPDATE programs SET 
                         name = ?, 
                         description = ?, 
                         difficulty = ?, 
                         duration = ?,
                         updated_at = NOW()
                         WHERE id = ?");
        
        mysqli_stmt_bind_param($update_stmt, "sssii", $name, $description, $difficulty, $duration, $program_id);
        
        if (mysqli_stmt_execute($update_stmt)) {
            $success = "Program updated successfully";
            
            // Refresh program data
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $program = mysqli_fetch_assoc($result);
        } else {
            $error = "Failed to update program: " . mysqli_error($conn);
        }
        mysqli_stmt_close($update_stmt);
    }
}

// Process steps update
if (isset($_POST['update_steps'])) {
    $step_ids = isset($_POST['step_id']) ? $_POST['step_id'] : [];
    $step_numbers = isset($_POST['step_number']) ? $_POST['step_number'] : [];
    $step_titles = isset($_POST['step_title']) ? $_POST['step_title'] : [];
    $step_descriptions = isset($_POST['step_description']) ? $_POST['step_description'] : [];
    $step_durations = isset($_POST['step_duration']) ? $_POST['step_duration'] : [];
    $delete_steps = isset($_POST['delete_step']) ? $_POST['delete_step'] : [];
    
    // Begin transaction
    mysqli_begin_transaction($conn);
    
    try {
        // Process deletes first
        if (!empty($delete_steps)) {
            foreach ($delete_steps as $step_id) {
                $delete_stmt = mysqli_prepare($conn, "DELETE FROM program_steps WHERE id = ? AND program_id = ?");
                mysqli_stmt_bind_param($delete_stmt, "ii", $step_id, $program_id);
                mysqli_stmt_execute($delete_stmt);
                mysqli_stmt_close($delete_stmt);
            }
        }
        
        // Process updates and inserts
        for ($i = 0; $i < count($step_titles); $i++) {
            $step_id = isset($step_ids[$i]) ? (int)$step_ids[$i] : 0;
            $step_number = (int)$step_numbers[$i];
            $step_title = sanitize_input($step_titles[$i]);
            $step_description = sanitize_input($step_descriptions[$i]);
            $step_duration = sanitize_input($step_durations[$i]);
            
            // Skip empty steps
            if (empty($step_title) && empty($step_description)) {
                continue;
            }
            
            if ($step_id > 0) {
                // Update existing step
                $update_step_stmt = mysqli_prepare($conn, "UPDATE program_steps SET 
                                    step_number = ?,
                                    title = ?,
                                    description = ?,
                                    duration = ?
                                    WHERE id = ? AND program_id = ?");
                mysqli_stmt_bind_param($update_step_stmt, "isssii", $step_number, $step_title, $step_description, $step_duration, $step_id, $program_id);
                mysqli_stmt_execute($update_step_stmt);
                mysqli_stmt_close($update_step_stmt);
            } else {
                // Insert new step
                $insert_step_stmt = mysqli_prepare($conn, "INSERT INTO program_steps 
                                   (program_id, step_number, title, description, duration) 
                                   VALUES (?, ?, ?, ?, ?)");
                mysqli_stmt_bind_param($insert_step_stmt, "iisss", $program_id, $step_number, $step_title, $step_description, $step_duration);
                mysqli_stmt_execute($insert_step_stmt);
                mysqli_stmt_close($insert_step_stmt);
            }
        }
        
        // Commit transaction
        mysqli_commit($conn);
        
        // Refresh steps data
        mysqli_stmt_execute($steps_stmt);
        $steps_result = mysqli_stmt_get_result($steps_stmt);
        $steps = [];
        while ($step = mysqli_fetch_assoc($steps_result)) {
            $steps[] = $step;
        }
        
        $success = "Program steps updated successfully";
        
    } catch (Exception $e) {
        // Rollback transaction on error
        mysqli_rollback($conn);
        $error = "Failed to update program steps: " . $e->getMessage();
    }
}

// Get usage statistics
$usage_query = "SELECT COUNT(*) as count FROM customer_programs WHERE program_id = $program_id";
$usage_result = mysqli_query($conn, $usage_query);
$usage_count = mysqli_fetch_assoc($usage_result)['count'];

$pending_query = "SELECT COUNT(*) as count FROM customer_programs WHERE program_id = $program_id AND status = 'pending'";
$pending_result = mysqli_query($conn, $pending_query);
$pending_count = mysqli_fetch_assoc($pending_result)['count'];

$approved_query = "SELECT COUNT(*) as count FROM customer_programs WHERE program_id = $program_id AND status = 'approved'";
$approved_result = mysqli_query($conn, $approved_query);
$approved_count = mysqli_fetch_assoc($approved_result)['count'];

$completed_query = "SELECT COUNT(*) as count FROM customer_programs WHERE program_id = $program_id AND status = 'completed'";
$completed_result = mysqli_query($conn, $completed_query);
$completed_count = mysqli_fetch_assoc($completed_result)['count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Program - OpFit Admin</title>
    <link rel="stylesheet" href="../css/style.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .step-card {
            position: relative;
            border: 1px solid #e5e7eb;
            border-radius: 0.375rem;
            padding: 1rem;
            margin-bottom: 1rem;
            background-color: #fff;
        }
        
        .step-card .card-actions {
            position: absolute;
            top: 0.5rem;
            right: 0.5rem;
        }
        
        .step-number {
            display: inline-block;
            width: 35px;
            height: 35px;
            line-height: 35px;
            text-align: center;
            background-color: #3b82f6;
            color: white;
            font-weight: bold;
            border-radius: 50%;
            margin-right: 10px;
        }
        
        .draggable-handle {
            cursor: move;
            padding: 5px;
            color: #6b7280;
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
                    <div class="relative mt-4 md:mt-0 md:ml-4">
                        <button id="userDropdown" class="flex items-center text-gray-300 hover:text-white py-2">
                            <?php echo htmlspecialchars($user_name); ?>
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
                <h1 class="text-2xl font-bold">Edit Program</h1>
            </div>
            <div class="w-full md:w-1/2 px-2 flex justify-end">
                <a href="programs.php" class="bg-gray-600 hover:bg-gray-700 text-white font-medium py-2 px-4 rounded flex items-center">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Programs
                </a>
            </div>
        </div>
        
        <?php if ($error): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4 rounded relative" role="alert">
                <?php echo htmlspecialchars($error); ?>
                <button type="button" class="absolute top-0 right-0 mt-2 mr-2 text-red-700" onclick="this.parentElement.remove()">
                    <span class="text-xl">&times;</span>
                </button>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4 rounded relative" role="alert">
                <?php echo htmlspecialchars($success); ?>
                <button type="button" class="absolute top-0 right-0 mt-2 mr-2 text-green-700" onclick="this.parentElement.remove()">
                    <span class="text-xl">&times;</span>
                </button>
            </div>
        <?php endif; ?>
        
        <div class="bg-white rounded-lg shadow-sm overflow-hidden mb-4">
            <div class="bg-blue-600 text-white px-4 py-3">
                <div class="flex flex-wrap border-b border-blue-500">
                    <button id="details-tab" 
                            class="px-4 py-2 text-white border-b-2 border-white font-medium tab-button active"
                            data-target="details">
                        Program Details
                    </button>
                    <button id="steps-tab" 
                            class="px-4 py-2 text-blue-200 hover:text-white border-b-2 border-transparent hover:border-blue-300 tab-button"
                            data-target="steps">
                        Step-by-Step Process
                    </button>
                </div>
            </div>
            <div class="p-4">
                <div class="tab-content">
                    <!-- Program Details Tab -->
                    <div id="details" class="tab-pane block">
                        <form action="edit_program.php?id=<?php echo $program_id; ?>" method="post">
                            <div class="mb-4 grid grid-cols-1 md:grid-cols-3 gap-4 items-center">
                                <label for="id" class="font-medium text-gray-700">Program ID</label>
                                <div class="md:col-span-2">
                                    <input type="text" class="bg-gray-100 w-full px-3 py-2 border border-gray-300 rounded-md text-gray-500" id="id" value="<?php echo htmlspecialchars($program['id']); ?>" readonly>
                                </div>
                            </div>
                            
                            <div class="mb-4 grid grid-cols-1 md:grid-cols-3 gap-4 items-center">
                                <label for="name" class="font-medium text-gray-700">Program Name</label>
                                <div class="md:col-span-2">
                                    <input type="text" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="name" name="name" value="<?php echo htmlspecialchars($program['name']); ?>" required>
                                </div>
                            </div>
                            
                            <div class="mb-4 grid grid-cols-1 md:grid-cols-3 gap-4 items-start">
                                <label for="description" class="font-medium text-gray-700 pt-2">Description</label>
                                <div class="md:col-span-2">
                                    <textarea class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="description" name="description" rows="4" required><?php echo htmlspecialchars($program['description']); ?></textarea>
                                </div>
                            </div>
                            
                            <div class="mb-4 grid grid-cols-1 md:grid-cols-3 gap-4 items-center">
                                <label for="difficulty" class="font-medium text-gray-700">Difficulty</label>
                                <div class="md:col-span-2">
                                    <select class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="difficulty" name="difficulty" required>
                                        <option value="beginner" <?php echo ($program['difficulty'] == 'beginner') ? 'selected' : ''; ?>>Beginner</option>
                                        <option value="intermediate" <?php echo ($program['difficulty'] == 'intermediate') ? 'selected' : ''; ?>>Intermediate</option>
                                        <option value="advanced" <?php echo ($program['difficulty'] == 'advanced') ? 'selected' : ''; ?>>Advanced</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="mb-4 grid grid-cols-1 md:grid-cols-3 gap-4 items-center">
                                <label for="duration" class="font-medium text-gray-700">Duration (weeks)</label>
                                <div class="md:col-span-2">
                                    <input type="number" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="duration" name="duration" min="1" max="52" value="<?php echo htmlspecialchars($program['duration']); ?>" required>
                                </div>
                            </div>
                            
                            <div class="mb-4 grid grid-cols-1 md:grid-cols-3 gap-4 items-center">
                                <label for="created_at" class="font-medium text-gray-700">Date Added</label>
                                <div class="md:col-span-2">
                                    <input type="text" class="bg-gray-100 w-full px-3 py-2 border border-gray-300 rounded-md text-gray-500" id="created_at" value="<?php echo date('F d, Y h:i A', strtotime($program['created_at'])); ?>" readonly>
                                </div>
                            </div>
                            
                            <div class="flex justify-end space-x-2">
                                <a href="programs.php" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-medium py-2 px-4 rounded">Cancel</a>
                                <button type="submit" name="update_program" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded">Update Program</button>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Step-by-Step Process Tab -->
                    <div id="steps" class="tab-pane hidden">
                        <div class="mb-4">
                            <div class="flex justify-between items-center">
                                <h5 class="font-medium text-lg">Program Steps</h5>
                                <button type="button" id="addStepBtn" class="bg-green-600 hover:bg-green-700 text-white text-sm font-medium py-1 px-3 rounded flex items-center">
                                    <i class="fas fa-plus mr-1"></i> Add New Step
                                </button>
                            </div>
                            <p class="text-gray-500 text-sm">Drag and drop steps to reorder. Steps will be automatically renumbered when saved.</p>
                        </div>
                        
                        <form id="stepsForm" action="edit_program.php?id=<?php echo $program_id; ?>" method="post">
                            <div id="stepsContainer">
                                <?php if (empty($steps)): ?>
                                    <div class="bg-blue-50 border-l-4 border-blue-500 text-blue-700 p-4 mb-4">
                                        <div class="flex">
                                            <i class="fas fa-info-circle mr-2 text-blue-500"></i>
                                            <p>No steps defined for this program yet. Click "Add New Step" to get started.</p>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($steps as $index => $step): ?>
                                        <div class="step-card" data-step-id="<?php echo $step['id']; ?>">
                                            <div class="card-actions">
                                                <span class="draggable-handle"><i class="fas fa-grip-vertical"></i></span>
                                                <button type="button" class="text-red-500 hover:text-red-700 delete-step-btn">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                            
                                            <input type="hidden" name="step_id[]" value="<?php echo $step['id']; ?>">
                                            
                                            <div class="mb-3">
                                                <div class="flex items-center">
                                                    <div class="step-number"><?php echo $step['step_number']; ?></div>
                                                    <input type="hidden" name="step_number[]" value="<?php echo $step['step_number']; ?>" class="step-number-input">
                                                    <input type="text" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" name="step_title[]" placeholder="Step Title" value="<?php echo htmlspecialchars($step['title']); ?>" required>
                                                </div>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <textarea class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" name="step_description[]" rows="2" placeholder="Step Description"><?php echo htmlspecialchars($step['description']); ?></textarea>
                                            </div>
                                            
                                            <div class="mb-2">
                                                <div class="flex">
                                                    <span class="inline-flex items-center px-3 py-2 rounded-l-md border border-r-0 border-gray-300 bg-gray-50 text-gray-500"><i class="far fa-clock"></i></span>
                                                    <input type="text" class="w-full px-3 py-2 border border-gray-300 rounded-r-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" name="step_duration[]" placeholder="Duration (e.g. 30 mins, 2 weeks)" value="<?php echo htmlspecialchars($step['duration']); ?>">
                                                </div>
                                                <p class="text-gray-500 text-sm mt-1">Optional: Specify how long this step should take</p>
                                            </div>
                                            
                                            <input type="checkbox" name="delete_step[]" value="<?php echo $step['id']; ?>" class="hidden delete-step-checkbox">
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            
                            <div class="flex justify-end space-x-2 mt-6">
                                <button type="button" class="bg-gray-500 hover:bg-gray-700 text-white font-medium py-2 px-4 rounded" data-target="details">Back to Details</button>
                                <button type="submit" name="update_steps" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded">Save All Steps</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-lg shadow-sm overflow-hidden mb-4">
            <div class="bg-cyan-600 text-white px-4 py-3">  
                <h5 class="font-medium">Program Statistics</h5>
            </div>
            <div class="p-4">
                <div class="mb-4">
                    <div class="flex justify-between items-center mb-2">
                        <h6 class="font-medium">Total Usage</h6>
                        <span class="font-medium"><?php echo $usage_count; ?></span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2.5">
                        <div class="bg-blue-600 h-2.5 rounded-full" style="width: 100%"></div>
                    </div>
                </div>
                
                <h6 class="font-medium mb-2">Status Breakdown</h6>
                <div class="mb-2">
                    <div class="flex justify-between text-sm">
                        <span>Pending</span>
                        <span><?php echo $pending_count; ?></span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2.5 mt-1">
                        <div class="bg-amber-500 h-2.5 rounded-full" 
                             style="width: <?php echo ($usage_count > 0) ? ($pending_count / $usage_count * 100) : 0; ?>%"></div>
                    </div>
                </div>
                
                <div class="mb-2">
                    <div class="flex justify-between text-sm">
                        <span>Approved</span>
                        <span><?php echo $approved_count; ?></span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2.5 mt-1">
                        <div class="bg-green-600 h-2.5 rounded-full" 
                             style="width: <?php echo ($usage_count > 0) ? ($approved_count / $usage_count * 100) : 0; ?>%"></div>
                    </div>
                </div>
                
                <div class="mb-2">
                    <div class="flex justify-between text-sm">
                        <span>Completed</span>
                        <span><?php echo $completed_count; ?></span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2.5 mt-1">
                        <div class="bg-cyan-600 h-2.5 rounded-full" 
                             style="width: <?php echo ($usage_count > 0) ? ($completed_count / $usage_count * 100) : 0; ?>%"></div>
                    </div>
                </div>
                
                <div class="mt-4">
                    <a href="#" class="block text-center bg-blue-50 hover:bg-blue-100 text-blue-600 font-medium py-2 px-4 rounded border border-blue-200 transition duration-150">View Users Enrolled in This Program</a>
                </div>
            </div>
        </div>
    </div>

    <?php include_once '../includes/footer.php'; ?>

    <!-- Step Template (hidden) -->
    <template id="stepTemplate">
        <div class="step-card" data-step-id="">
            <div class="card-actions">
                <span class="draggable-handle"><i class="fas fa-grip-vertical"></i></span>
                <button type="button" class="text-red-500 hover:text-red-700 delete-step-btn">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
            
            <input type="hidden" name="step_id[]" value="">
            
            <div class="mb-3">
                <div class="flex items-center">
                    <div class="step-number"></div>
                    <input type="hidden" name="step_number[]" value="" class="step-number-input">
                    <input type="text" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" name="step_title[]" placeholder="Step Title" required>
                </div>
            </div>
            
            <div class="mb-3">
                <textarea class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" name="step_description[]" rows="2" placeholder="Step Description"></textarea>
            </div>
            
            <div class="mb-2">
                <div class="flex">
                    <span class="inline-flex items-center px-3 py-2 rounded-l-md border border-r-0 border-gray-300 bg-gray-50 text-gray-500"><i class="far fa-clock"></i></span>
                    <input type="text" class="w-full px-3 py-2 border border-gray-300 rounded-r-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" name="step_duration[]" placeholder="Duration (e.g. 30 mins, 2 weeks)">
                </div>
                <p class="text-gray-500 text-sm mt-1">Optional: Specify how long this step should take</p>
            </div>
            
            <input type="checkbox" name="delete_step[]" value="" class="hidden delete-step-checkbox">
        </div>
    </template>

    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.14.0/Sortable.min.js"></script>
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
            
            // Tab functionality
            const tabButtons = document.querySelectorAll('.tab-button');
            const tabPanes = document.querySelectorAll('.tab-pane');
            
            tabButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const target = this.getAttribute('data-target');
                    
                    // Deactivate all tabs
                    tabButtons.forEach(btn => {
                        btn.classList.remove('active', 'border-white', 'text-white', 'font-medium');
                        btn.classList.add('text-blue-200', 'border-transparent');
                    });
                    
                    // Activate clicked tab
                    this.classList.add('active', 'border-white', 'text-white', 'font-medium');
                    this.classList.remove('text-blue-200', 'border-transparent');
                    
                    // Hide all panes
                    tabPanes.forEach(pane => {
                        pane.classList.add('hidden');
                        pane.classList.remove('block');
                    });
                    
                    // Show target pane
                    document.getElementById(target).classList.remove('hidden');
                    document.getElementById(target).classList.add('block');
                });
            });
            
            // Auto-hide alerts after 5 seconds
            const alerts = document.querySelectorAll('.bg-red-100, .bg-green-100');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.remove();
                }, 5000);
            });
            
            // Make steps sortable with drag-and-drop
            const stepsContainer = document.getElementById('stepsContainer');
            if (stepsContainer) {
                new Sortable(stepsContainer, {
                    handle: '.draggable-handle',
                    animation: 150,
                    onEnd: function() {
                        updateStepNumbers();
                    }
                });
            }
            
            // Add new step
            const addStepBtn = document.getElementById('addStepBtn');
            if (addStepBtn) {
                addStepBtn.addEventListener('click', function() {
                    addNewStep();
                });
            }
            
            // Delete step
            document.addEventListener('click', function(e) {
                if (e.target.closest('.delete-step-btn')) {
                    const stepCard = e.target.closest('.step-card');
                    const checkbox = stepCard.querySelector('.delete-step-checkbox');
                    
                    if (checkbox.value) {
                        // Existing step: mark for deletion
                        stepCard.style.display = 'none';
                        checkbox.checked = true;
                    } else {
                        // New step: remove from DOM
                        stepCard.remove();
                    }
                    
                    updateStepNumbers();
                }
            });
            
            // Function to add a new step
            function addNewStep() {
                const template = document.getElementById('stepTemplate');
                const clone = document.importNode(template.content, true);
                const stepsContainer = document.getElementById('stepsContainer');
                const stepNumber = document.querySelectorAll('.step-card:not([style*="display: none"])').length + 1;
                
                // Update step number
                clone.querySelector('.step-number').textContent = stepNumber;
                clone.querySelector('.step-number-input').value = stepNumber;
                
                // Append to container
                stepsContainer.appendChild(clone);
                
                // Focus on title field
                setTimeout(() => {
                    const newStep = stepsContainer.lastElementChild;
                    const titleInput = newStep.querySelector('input[name="step_title[]"]');
                    if (titleInput) {
                        titleInput.focus();
                    }
                }, 100);
            }
            
            // Function to update step numbers
            function updateStepNumbers() {
                const stepCards = document.querySelectorAll('.step-card:not([style*="display: none"])');
                stepCards.forEach((card, index) => {
                    const stepNumber = index + 1;
                    card.querySelector('.step-number').textContent = stepNumber;
                    card.querySelector('.step-number-input').value = stepNumber;
                });
            }
            
            // Initialize step numbers
            updateStepNumbers();
        });
    </script>
</body>
</html>