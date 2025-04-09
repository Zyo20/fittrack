<?php
session_start();

include_once '../includes/db_connect.php';
include_once '../includes/functions.php';

// Check if user is logged in and is a customer
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'customer') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

// Check if customer already has a coach
$check_query = "SELECT * FROM coach_customer WHERE customer_id = ?";
$stmt = mysqli_prepare($conn, $check_query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$check_result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($check_result) > 0) {
    // Customer already has a coach, redirect to dashboard
    header("Location: dashboard.php");
    exit();
}

// Initialize variables
$error = '';
$success = '';

// Check for session messages
if (isset($_SESSION['coach_error'])) {
    $error = $_SESSION['coach_error'];
    unset($_SESSION['coach_error']);
}
if (isset($_SESSION['coach_success'])) {
    $success = $_SESSION['coach_success'];
    unset($_SESSION['coach_success']);
}

// Process coach selection
if (isset($_POST['select_coach'])) {
    $coach_id = isset($_POST['coach_id']) ? (int)$_POST['coach_id'] : 0;
    
    if (empty($coach_id)) {
        $error = "Please select a coach";
    } else {
        // Assign coach to customer using prepared statement
        $query = "INSERT INTO coach_customer (coach_id, customer_id) VALUES (?, ?)";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "ii", $coach_id, $user_id);
        
        if (mysqli_stmt_execute($stmt)) {
            $success = "Coach selected successfully!";
            // Add a small delay before redirecting to allow the success message to be seen
            header("Refresh: 2; URL=dashboard.php");
        } else {
            $error = "Failed to select coach. Please try again.";
        }
    }
}

// Get all available coaches with prepared statement
$coaches_query = "SELECT id, name, email FROM users WHERE user_type = 'coach' ORDER BY name";
$stmt = mysqli_prepare($conn, $coaches_query);
mysqli_stmt_execute($stmt);
$coaches_result = mysqli_stmt_get_result($stmt);
$coaches = [];
while ($coach = mysqli_fetch_assoc($coaches_result)) {
    $coaches[] = $coach;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Choose Your Coach - FitTrack</title>
    <link rel="stylesheet" href="../css/style.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body class="bg-gray-100">
    <nav class="bg-gray-800 text-white">
        <div class="container mx-auto px-4 py-3">
            <a class="text-xl font-bold" href="#">FitTrack</a>
        </div>
    </nav>

    <div class="container mx-auto px-4 py-8">
        <div class="flex justify-center">
            <div class="w-full max-w-4xl">
                <div class="bg-white rounded-lg shadow-lg overflow-hidden">
                    <div class="bg-blue-600 text-white px-6 py-4">
                        <h3 class="text-xl font-semibold">Welcome to FitTrack!</h3>
                    </div>
                    <div class="p-6">
                        <div class="mb-6">
                            <h4 class="text-xl font-medium text-gray-800">Choose Your Coach</h4>
                            <p class="text-gray-600 mt-1">Please select a coach to get started with your fitness journey. Your coach will guide you through personalized programs and help you achieve your fitness goals.</p>
                        </div>
                        
                        <?php if ($error): ?>
                            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6">
                                <?php echo htmlspecialchars($error); ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6">
                                <?php echo htmlspecialchars($success); ?>
                                <div class="flex items-center mt-2">
                                    <div class="animate-spin h-5 w-5 border-t-2 border-b-2 border-green-600 rounded-full mr-2"></div>
                                    <span>Redirecting to your dashboard...</span>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <form id="coachSelectionForm" action="choose_coach.php" method="post">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <?php if (count($coaches) > 0): ?>
                                    <?php foreach ($coaches as $coach): ?>
                                        <div class="mb-4">
                                            <div class="h-full border border-gray-200 rounded-lg p-4 hover:shadow-md transition-all duration-200 hover:-translate-y-1 cursor-pointer coach-card" onclick="selectCoach(<?php echo $coach['id']; ?>)">
                                                <div class="hidden">
                                                    <input class="form-check-input visually-hidden" type="radio" name="coach_id" 
                                                           id="coach<?php echo $coach['id']; ?>" 
                                                           value="<?php echo $coach['id']; ?>">
                                                </div>
                                                <div class="flex mb-3">
                                                    <div class="w-20 h-20 rounded-full bg-gray-200 flex items-center justify-center mr-4 flex-shrink-0">
                                                        <i class="fas fa-user-tie text-2xl text-gray-600"></i>
                                                    </div>
                                                    <div>
                                                        <h5 class="text-lg font-medium text-gray-800"><?php echo htmlspecialchars($coach['name']); ?></h5>
                                                        <div class="text-gray-600 mb-2 text-sm"><?php echo htmlspecialchars($coach['email']); ?></div>
                                                    </div>
                                                </div>
                                                <p class="text-sm text-gray-600">Professional fitness coach ready to help you reach your fitness goals.</p>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="col-span-2">
                                        <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4">
                                            <div class="flex items-center">
                                                <i class="fas fa-exclamation-triangle mr-2"></i>
                                                <span>No coaches are available at the moment. Please contact the admin for assistance.</span>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="mt-6">
                                <button type="submit" name="select_coach" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-3 px-4 rounded transition">
                                    <i class="fas fa-check-circle mr-2"></i>Confirm Coach Selection
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include_once '../includes/footer.php'; ?>

    <script>
        function selectCoach(coachId) {
            // Clear previous selection - visually
            document.querySelectorAll('.coach-card').forEach(function(card) {
                card.classList.remove('ring-2', 'ring-blue-500', 'bg-blue-50');
            });
            
            // Select the coach - update radio button
            const radioButton = document.getElementById('coach' + coachId);
            if (radioButton) {
                radioButton.checked = true;
                // Add selected class to parent card - visually
                radioButton.closest('.coach-card').classList.add('ring-2', 'ring-blue-500', 'bg-blue-50');
            }
        }
        
        // Check if there's a pre-selected coach (e.g., from validation error)
        document.addEventListener('DOMContentLoaded', function() {
            const checkedCoach = document.querySelector('input[name="coach_id"]:checked');
            if (checkedCoach) {
                selectCoach(checkedCoach.value);
            }
        });
    </script>
</body>
</html> 