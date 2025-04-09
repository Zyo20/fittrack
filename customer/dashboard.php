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

include_once '../includes/db_connect.php';
include_once '../includes/functions.php';
include_once '../includes/feature_announcements.php';

// Check if user is logged in and is a customer
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'customer') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

// Get customer's coach
$coach = get_coach_for_customer($user_id);

// If no coach is assigned, redirect to coach selection page
if (!$coach) {
    header("Location: choose_coach.php");
    exit();
}

// Prepare data arrays
$progress_records = [];
$workout_logs = [];
$progress_history = [];
$weight_data = [];
$height_data = [];
$dates = [];
$programs = [];
$latest_progress = null;

// Get unread messages count 
$unread_count = count_unread_messages($user_id);

// Get customer details
$customer_query = "SELECT * FROM users WHERE id = ?";
$stmt = mysqli_prepare($conn, $customer_query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$customer_result = mysqli_stmt_get_result($stmt);
$customer = mysqli_fetch_assoc($customer_result);

// Get customer's programs
$programs = get_customer_programs($user_id);

// Calculate progress percentages for active programs
foreach ($programs as &$program) {
    if ($program['status'] == 'approved') {
        $program['progress_percentage'] = calculate_program_progress($user_id, $program['program_id']);
    }
}

// Get progress history, recent workout logs, and recent progress updates in a transaction
mysqli_autocommit($conn, false);
try {
    // Get progress history
    $progress_query = "SELECT * FROM progress WHERE customer_id = ? ORDER BY record_date DESC";
    $stmt = mysqli_prepare($conn, $progress_query);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $progress_result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($progress_result)) {
        $progress_history[] = $row;
    }
    
    // Get recent workout logs
    $logs_query = "SELECT wl.*, p.name as program_name 
                  FROM workout_logs wl
                  JOIN programs p ON wl.program_id = p.id
                  WHERE wl.customer_id = ?
                  ORDER BY wl.workout_date DESC, wl.created_at DESC
                  LIMIT 5";
    $stmt = mysqli_prepare($conn, $logs_query);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $logs_result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($logs_result)) {
        $workout_logs[] = $row;
    }
    
    mysqli_commit($conn);
} catch (Exception $e) {
    mysqli_rollback($conn);
    // Log error here
}
mysqli_autocommit($conn, true);

// Set recent progress updates
$progress_records = array_slice($progress_history, 0, 3);

// Get latest progress record if available
$latest_progress = !empty($progress_history) ? $progress_history[0] : null;

// Prepare data for charts - Get the last 10 progress records
$chart_data = array_slice(array_reverse($progress_history), 0, 10);
foreach ($chart_data as $record) {
    $weight_data[] = $record['weight'];
    $height_data[] = $record['height'];
    $dates[] = date('M d', strtotime($record['record_date']));
}

// Convert to JSON for chart.js
$weight_json = json_encode($weight_data);
$height_json = json_encode($height_data);
$dates_json = json_encode($dates);

// Calculate BMI and ideal weight ranges for latest record
$bmi = $bmi_class = $ideal_weight_lower = $ideal_weight_upper = $weight_min = $weight_max = $weight_percent = $height_min = $height_max = $height_percent = null;
if ($latest_progress && $latest_progress['height'] > 0 && $latest_progress['weight'] > 0) {
    $height_m = $latest_progress['height'] / 100;
    $bmi = round($latest_progress['weight'] / ($height_m * $height_m), 1);
    
    $bmi_class = 'text-primary';
    if ($bmi < 18.5) $bmi_class = 'text-warning';
    else if ($bmi >= 25) $bmi_class = 'text-warning';
    else if ($bmi >= 30) $bmi_class = 'text-danger';

    // Define weight ranges based on BMI and height
    $ideal_weight_lower = round(18.5 * $height_m * $height_m, 1);
    $ideal_weight_upper = round(24.9 * $height_m * $height_m, 1);
    // For scale visualization
    $weight_min = max(round($ideal_weight_lower * 0.7), 40);
    $weight_max = round($ideal_weight_upper * 1.3);
    $weight_percent = min(100, max(0, (($latest_progress['weight'] - $weight_min) / ($weight_max - $weight_min)) * 100));

    // Define height ranges (for adults)
    $height_min = 150;
    $height_max = 200;
    $height_percent = min(100, max(0, (($latest_progress['height'] - $height_min) / ($height_max - $height_min)) * 100));
}

// Store success/error messages from session and then clear them
$success_message = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : null;
$error_message = isset($_SESSION['error_message']) ? $_SESSION['error_message'] : null;
unset($_SESSION['success_message'], $_SESSION['error_message']);

// Helper function to get BMI category
function getBmiCategory($bmi) {
    if ($bmi < 18.5) return ['Underweight', 'warning'];
    if ($bmi < 25) return ['Normal', 'success'];
    if ($bmi < 30) return ['Overweight', 'warning'];
    return ['Obese', 'danger'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Dashboard - OpFit</title>
    <link rel="stylesheet" href="../css/style.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-annotation@2.1.0" defer></script>
</head>
<body class="bg-gray-100 text-gray-800">

    <nav class="bg-gray-800 text-white">
        <div class="container mx-auto px-4 py-3">
            <div class="flex flex-wrap justify-between items-center">
                <a class="text-xl font-bold" href="dashboard.php">OpFit Customer</a>
                <button class="md:hidden" type="button" id="navbarToggle">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>
                <div class="hidden md:flex flex-col md:flex-row w-full md:w-auto mt-4 md:mt-0 items-center" id="navbarMenu">
                    <ul class="flex flex-col md:flex-row md:mr-6 space-y-2 md:space-y-0 md:space-x-6">
                        <li>
                            <a class="text-white font-medium block py-2" href="dashboard.php">Dashboard</a>
                        </li>
                        <li>
                            <a class="text-gray-300 hover:text-white block py-2" href="programs.php">My Programs</a>
                        </li>
                        <li>
                            <a class="text-gray-300 hover:text-white block py-2" href="progress.php">Progress Tracker</a>
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

    <div class="container mx-auto px-4 py-6">
        <div class="mb-6">
            <div class="w-full">
                <h1 class="text-2xl font-bold mb-4">Welcome, <?php echo htmlspecialchars($customer['name']); ?>!</h1>
                
                <?php if ($success_message): ?>
                    <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4">
                        <?php echo htmlspecialchars($success_message); ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($error_message): ?>
                    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4">
                        <?php echo htmlspecialchars($error_message); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Stats Widgets -->
        <div class="flex flex-wrap -mx-2 mb-4">
            <!-- Feature Announcements -->
            <?php display_feature_announcements(); ?>
            
            <!-- Status cards -->
            <div class="w-full md:w-1/3 px-2 mb-4">
                <a href="coach_details.php?id=<?php echo $coach ? htmlspecialchars($coach['id']) : ''; ?>" class="block">
                    <div class="bg-blue-600 text-white rounded-lg shadow-md p-6">
                        <div class="flex justify-between items-center">
                            <div>
                                <h6 class="text-sm font-semibold uppercase">My Coach</h6>
                                <h2 class="text-3xl font-bold"><?php echo $coach ? htmlspecialchars($coach['name']) : 'Not assigned'; ?></h2>
                            </div>
                            <i class="fas fa-user-tie text-4xl opacity-50"></i>
                        </div>
                    </div>
                </a>
            </div>
            <div class="w-full md:w-1/3 px-2 mb-4">
                <div class="bg-green-600 text-white rounded-lg shadow-md p-6">
                    <div class="flex justify-between items-center">
                        <div>
                            <h6 class="text-sm font-semibold uppercase">Active Programs</h6>
                            <h2 class="text-3xl font-bold">
                                <?php 
                                echo count(array_filter($programs, function($p) { 
                                    return $p['status'] == 'approved'; 
                                }));
                                ?>
                            </h2>
                        </div>
                        <i class="fas fa-dumbbell text-4xl opacity-50"></i>
                    </div>
                </div>
            </div>
            <div class="w-full md:w-1/3 px-2 mb-4">
                <div class="bg-cyan-600 text-white rounded-lg shadow-md p-6">
                    <div class="flex justify-between items-center">
                        <div>
                            <h6 class="text-sm font-semibold uppercase">Progress Records</h6>
                            <h2 class="text-3xl font-bold"><?php echo count($progress_records); ?></h2>
                        </div>
                        <i class="fas fa-chart-line text-4xl opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <!-- Active Programs -->
            <div>
                <div class="bg-white rounded-lg shadow-md h-full">
                    <div class="bg-blue-600 text-white px-4 py-3 rounded-t-lg">
                        <h5 class="font-medium">My Programs</h5>
                    </div>
                    <div class="p-4">
                        <?php if (count($programs) > 0): ?>
                            <div class="space-y-3">
                                <?php foreach ($programs as $program): ?>
                                    <div class="border border-gray-200 rounded-lg p-4 hover:bg-gray-50 transition">
                                        <div class="flex justify-between items-start">
                                            <h5 class="font-medium"><?php echo htmlspecialchars($program['name']); ?></h5>
                                            <span class="inline-block px-2 py-1 text-xs font-medium rounded-full
                                                <?php 
                                                if ($program['status'] == 'approved') echo 'bg-green-100 text-green-800';
                                                else if ($program['status'] == 'pending') echo 'bg-yellow-100 text-yellow-800';
                                                else if ($program['status'] == 'completed') echo 'bg-blue-100 text-blue-800';
                                                else echo 'bg-red-100 text-red-800';
                                                ?>">
                                                <?php echo ucfirst(htmlspecialchars($program['status'])); ?>
                                            </span>
                                        </div>
                                        <p class="text-gray-600 text-sm mt-1 mb-2"><?php echo htmlspecialchars(substr($program['description'], 0, 100)); ?>...</p>
                                        
                                        <?php if ($program['status'] == 'approved'): ?>
                                            <div class="w-full bg-gray-200 rounded-full h-2 mb-1">
                                                <div class="bg-green-500 h-2 rounded-full" style="width: <?php echo $program['progress_percentage']; ?>%"></div>
                                            </div>
                                            <div class="text-xs text-gray-500 mb-3"><?php echo $program['progress_percentage']; ?>% Complete</div>
                                        <?php endif; ?>
                                        
                                        <a href="program_progress.php?id=<?php echo htmlspecialchars($program['program_id']); ?>" 
                                           class="inline-block mt-2 px-3 py-1 bg-white border border-blue-600 text-blue-600 rounded hover:bg-blue-50 text-sm">
                                            <?php echo ($program['status'] == 'approved') ? 'View Progress' : 'View Details'; ?>
                                        </a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <a href="programs.php" class="inline-block mt-4 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded text-sm">
                                View All Programs
                            </a>
                        <?php else: ?>
                            <p class="text-gray-500">You don't have any programs assigned yet.</p>
                            <a href="programs.php" class="inline-block mt-3 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded text-sm">
                                Browse Available Programs
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Recent Workout Logs -->
            <div>
                <div class="bg-white rounded-lg shadow-md h-full">
                    <div class="bg-blue-600 text-white px-4 py-3 rounded-t-lg">
                        <h5 class="font-medium">Recent Workouts</h5>
                    </div>
                    <div class="p-4">
                        <?php if (count($workout_logs) > 0): ?>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead>
                                        <tr>
                                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Program</th>
                                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Duration</th>
                                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Notes</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200">
                                        <?php foreach ($workout_logs as $log): ?>
                                            <tr class="hover:bg-gray-50">
                                                <td class="px-3 py-2 whitespace-nowrap text-sm"><?php echo date('M d, Y', strtotime($log['workout_date'])); ?></td>
                                                <td class="px-3 py-2 whitespace-nowrap text-sm"><?php echo htmlspecialchars($log['program_name']); ?></td>
                                                <td class="px-3 py-2 whitespace-nowrap text-sm"><?php echo htmlspecialchars($log['duration']); ?> min</td>
                                                <td class="px-3 py-2 whitespace-nowrap text-sm truncate max-w-[200px]"><?php echo $log['notes'] ? htmlspecialchars(substr($log['notes'], 0, 30)) . '...' : 'No notes'; ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <a href="progress.php" class="inline-block mt-4 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded text-sm">
                                View All Workout Logs
                            </a>
                        <?php else: ?>
                            <p class="text-gray-500">No workout logs recorded yet.</p>
                            <a href="program_progress.php?id=<?php echo isset($programs[0]) ? htmlspecialchars($programs[0]['program_id']) : ''; ?>" 
                               class="inline-block mt-3 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded text-sm <?php echo empty($programs) ? 'opacity-50 cursor-not-allowed' : ''; ?>"
                               <?php echo empty($programs) ? 'disabled' : ''; ?>>
                                Log Your First Workout
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Progress Updates -->
        <div class="mb-6">
            <div class="bg-white rounded-lg shadow-md">
                <div class="bg-blue-600 text-white px-4 py-3 rounded-t-lg">
                    <h5 class="font-medium">Progress Tracker</h5>
                </div>
                <div class="p-4">
                    <?php if (!empty($progress_history)): ?>
                        <!-- Current Stats Cards -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                            <div class="text-center">
                                <div class="bg-white rounded-lg shadow-sm border p-4">
                                    <h4 class="text-xl font-medium text-blue-600"><?php echo htmlspecialchars($latest_progress['weight']); ?> kg</h4>
                                    <p class="text-gray-500 mb-2">Current Weight</p>
                                    <?php if (isset($progress_history[1])): 
                                        $weight_change = $latest_progress['weight'] - $progress_history[1]['weight'];
                                        $change_text = $weight_change > 0 ? "+$weight_change kg" : "$weight_change kg";
                                    ?>
                                        <span class="inline-block px-2 py-1 text-xs font-medium rounded-full
                                            <?php echo $weight_change <= 0 ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'; ?>">
                                            <?php echo htmlspecialchars($change_text); ?> since last record
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="text-center">
                                <div class="bg-white rounded-lg shadow-sm border p-4">
                                    <h4 class="text-xl font-medium text-blue-600"><?php echo htmlspecialchars($latest_progress['height']); ?> cm</h4>
                                    <p class="text-gray-500">Current Height</p>
                                </div>
                            </div>
                            <div class="text-center">
                                <div class="bg-white rounded-lg shadow-sm border p-4">
                                    <h4 class="text-xl font-medium
                                        <?php 
                                        if (isset($bmi_class)) {
                                            if ($bmi_class == 'text-primary') echo 'text-blue-600';
                                            else if ($bmi_class == 'text-warning') echo 'text-yellow-600';
                                            else if ($bmi_class == 'text-danger') echo 'text-red-600';
                                        } else {
                                            echo 'text-blue-600';
                                        } 
                                        ?>">
                                        <?php echo isset($bmi) ? $bmi : 'N/A'; ?>
                                    </h4>
                                    <p class="text-gray-500 mb-2">Current BMI</p>
                                    <?php if (isset($bmi)):
                                        list($bmi_category, $category_class) = getBmiCategory($bmi);
                                    ?>
                                        <span class="inline-block px-2 py-1 text-xs font-medium rounded-full bg-gray-200 text-gray-800">
                                            <?php echo $bmi_category; ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Progress History Table -->
                        <h6 class="font-medium text-gray-800 mb-3">Progress History</h6>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead>
                                    <tr>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Weight (kg)</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Height (cm)</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Notes</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    <?php foreach (array_slice($progress_history, 0, 5) as $idx => $progress): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-3 py-2 whitespace-nowrap text-sm"><?php echo date('M d, Y', strtotime($progress['record_date'])); ?></td>
                                            <td class="px-3 py-2 whitespace-nowrap text-sm">
                                                <?php echo htmlspecialchars($progress['weight']); ?>
                                                <?php 
                                                // Show weight change compared to previous record
                                                if (isset($progress_history[$idx+1])) {
                                                    $change = $progress['weight'] - $progress_history[$idx+1]['weight'];
                                                    if ($change != 0) {
                                                        $badge_color = $change < 0 ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800';
                                                        echo ' <span class="inline-block px-1.5 py-0.5 text-xs font-medium rounded ' . $badge_color . '">';
                                                        echo ($change < 0 ? '' : '+') . number_format($change, 1);
                                                        echo '</span>';
                                                    }
                                                }
                                                ?>
                                            </td>
                                            <td class="px-3 py-2 whitespace-nowrap text-sm"><?php echo htmlspecialchars($progress['height']); ?></td>
                                            <td class="px-3 py-2 whitespace-nowrap text-sm truncate max-w-[200px]">
                                                <?php echo substr(htmlspecialchars($progress['notes']), 0, 30) . (strlen($progress['notes']) > 30 ? '...' : ''); ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="text-center mt-6">
                            <a href="progress.php" class="inline-block px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded text-sm">
                                View Detailed Progress
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-12">
                            <i class="fas fa-weight text-4xl text-gray-400 mb-4"></i>
                            <p class="text-gray-500 mb-4">No progress data recorded yet.</p>
                            <a href="progress.php" class="inline-block px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded text-sm">
                                Add Your First Progress Record
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <?php include_once '../includes/footer.php'; ?>
    
    <script src="../js/unread-messages.js"></script>
    
    <?php echo modal_fix_script(); ?>
    
    <?php if (!empty($progress_history) && count($progress_history) > 1): ?>
    <script>
        // Initialize charts when DOM is fully loaded
        document.addEventListener('DOMContentLoaded', function() {
            // Register the annotation plugin
            Chart.register(ChartAnnotation);
            
            const weightData = <?php echo $weight_json; ?>;
            const heightData = <?php echo $height_json; ?>;
            const dates = <?php echo $dates_json; ?>;
            
            // Chart configuration
            const chartOptions = {
                weight: {
                    responsive: true,
                    plugins: {
                        annotation: {
                            annotations: {
                                box1: {
                                    type: 'box',
                                    drawTime: 'beforeDatasetsDraw',
                                    xMin: -0.5,
                                    xMax: dates.length - 0.5,
                                    yMin: <?php echo isset($ideal_weight_lower) ? $ideal_weight_lower : 0; ?>,
                                    yMax: <?php echo isset($ideal_weight_upper) ? $ideal_weight_upper : 0; ?>,
                                    backgroundColor: 'rgba(75, 192, 192, 0.2)',
                                    borderColor: 'rgba(75, 192, 192, 1)',
                                    borderWidth: 1,
                                    label: {
                                        display: true,
                                        content: 'Ideal Range',
                                        position: 'start'
                                    }
                                },
                                line1: {
                                    type: 'line',
                                    scaleID: 'y',
                                    value: <?php echo isset($latest_progress) ? $latest_progress['weight'] : 0; ?>,
                                    borderColor: 'rgba(255, 99, 132, 1)',
                                    borderWidth: 2,
                                    label: {
                                        display: true,
                                        content: 'Current: <?php echo isset($latest_progress) ? $latest_progress['weight'] : 0; ?> kg',
                                        position: 'end'
                                    }
                                }
                            }
                        },
                        tooltip: {
                            callbacks: {
                                title: function(tooltipItems) {
                                    return 'Date: ' + dates[tooltipItems[0].dataIndex];
                                },
                                label: function(context) {
                                    return 'Weight: ' + context.raw + ' kg';
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            title: {
                                display: true,
                                text: 'Weight (kg)'
                            },
                            suggestedMin: <?php echo isset($weight_min) ? $weight_min : 40; ?>,
                            suggestedMax: <?php echo isset($weight_max) ? $weight_max : 100; ?>
                        }
                    }
                },
                height: {
                    responsive: true,
                    plugins: {
                        annotation: {
                            annotations: {
                                line1: {
                                    type: 'line',
                                    scaleID: 'y',
                                    value: <?php echo isset($latest_progress) ? $latest_progress['height'] : 0; ?>,
                                    borderColor: 'rgba(255, 99, 132, 1)',
                                    borderWidth: 2,
                                    label: {
                                        display: true,
                                        content: 'Current: <?php echo isset($latest_progress) ? $latest_progress['height'] : 0; ?> cm',
                                        position: 'end'
                                    }
                                }
                            }
                        },
                        tooltip: {
                            callbacks: {
                                title: function(tooltipItems) {
                                    return 'Date: ' + dates[tooltipItems[0].dataIndex];
                                },
                                label: function(context) {
                                    return 'Height: ' + context.raw + ' cm';
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            title: {
                                display: true,
                                text: 'Height (cm)'
                            },
                            suggestedMin: <?php echo isset($height_min) ? $height_min : 150; ?>,
                            suggestedMax: <?php echo isset($height_max) ? $height_max : 200; ?>
                        }
                    }
                }
            };
            
            // Initialize charts if elements exist
            const weightCtx = document.getElementById('weightChart');
            const heightCtx = document.getElementById('heightChart');
            const progressCtx = document.getElementById('progressChart');
            
            if (weightCtx) {
                new Chart(weightCtx, {
                    type: 'line',
                    data: {
                        labels: dates,
                        datasets: [{
                            label: 'Weight (kg)',
                            data: weightData,
                            borderColor: 'rgba(54, 162, 235, 1)',
                            backgroundColor: 'rgba(54, 162, 235, 0.2)',
                            tension: 0.1,
                            fill: false
                        }]
                    },
                    options: chartOptions.weight
                });
            }
            
            if (heightCtx) {
                new Chart(heightCtx, {
                    type: 'line',
                    data: {
                        labels: dates,
                        datasets: [{
                            label: 'Height (cm)',
                            data: heightData,
                            borderColor: 'rgba(255, 99, 132, 1)',
                            backgroundColor: 'rgba(255, 99, 132, 0.2)', 
                            tension: 0.1,
                            fill: false
                        }]
                    },
                    options: chartOptions.height
                });
            }
            
            if (progressCtx) {
                new Chart(progressCtx, {
                    type: 'line',
                    data: {
                        labels: dates,
                        datasets: [
                            {
                                label: 'Weight (kg)',
                                data: weightData,
                                borderColor: 'rgba(54, 162, 235, 1)',
                                backgroundColor: 'rgba(54, 162, 235, 0.2)',
                                tension: 0.1,
                                yAxisID: 'y'
                            },
                            {
                                label: 'Height (cm)',
                                data: heightData,
                                borderColor: 'rgba(255, 99, 132, 1)',
                                backgroundColor: 'rgba(255, 99, 132, 0.2)',
                                tension: 0.1,
                                yAxisID: 'y1'
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        scales: {
                            y: {
                                type: 'linear',
                                display: true,
                                position: 'left',
                                title: {
                                    display: true,
                                    text: 'Weight (kg)'
                                }
                            },
                            y1: {
                                type: 'linear',
                                display: true,
                                position: 'right',
                                title: {
                                    display: true,
                                    text: 'Height (cm)'
                                },
                                grid: {
                                    drawOnChartArea: false
                                }
                            }
                        }
                    }
                });
            }
        });
    </script>
    <?php endif; ?>
    
    <script>
        // Mobile menu toggle
        document.getElementById('navbarToggle').addEventListener('click', function() {
            const menu = document.getElementById('navbarMenu');
            menu.classList.toggle('hidden');
        });
        
        // User dropdown toggle
        const userDropdown = document.getElementById('userDropdown');
        const userDropdownMenu = document.getElementById('userDropdownMenu');
        
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
    </script>
</body>
</html> 