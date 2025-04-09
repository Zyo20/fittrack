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

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">OpFit Admin</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="users.php">Users</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="programs.php">Programs</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="assignments.php">Coach Assignments</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="reports.php">Reports</a>
                    </li>
                </ul>
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <?php echo $user_name; ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="profile.php">Profile</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../logout.php">Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row mb-4">
            <div class="col-md-6">
                <h1>Edit User</h1>
            </div>
            <div class="col-md-6 text-end">
                <a href="users.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Users
                </a>
            </div>
        </div>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Edit User Details</h5>
            </div>
            <div class="card-body">
                <form action="edit_user.php?id=<?php echo $edit_id; ?>" method="post">
                    <div class="row mb-3">
                        <label for="id" class="col-sm-3 col-form-label">User ID</label>
                        <div class="col-sm-9">
                            <input type="text" class="form-control-plaintext" id="id" value="<?php echo $user['id']; ?>" readonly>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <label for="name" class="col-sm-3 col-form-label">Name</label>
                        <div class="col-sm-9">
                            <input type="text" class="form-control" id="name" name="name" value="<?php echo $user['name']; ?>" required>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <label for="email" class="col-sm-3 col-form-label">Email</label>
                        <div class="col-sm-9">
                            <input type="email" class="form-control" id="email" name="email" value="<?php echo $user['email']; ?>" required>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <label for="password" class="col-sm-3 col-form-label">Password</label>
                        <div class="col-sm-9">
                            <input type="password" class="form-control" id="password" name="password" placeholder="Leave blank to keep current password">
                            <div class="form-text">Leave blank to keep current password</div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <label for="user_type" class="col-sm-3 col-form-label">User Type</label>
                        <div class="col-sm-9">
                            <select class="form-select" id="user_type" name="user_type" required>
                                <option value="customer" <?php echo ($user['user_type'] == 'customer') ? 'selected' : ''; ?>>Customer</option>
                                <option value="coach" <?php echo ($user['user_type'] == 'coach') ? 'selected' : ''; ?>>Coach</option>
                                <option value="admin" <?php echo ($user['user_type'] == 'admin') ? 'selected' : ''; ?>>Admin</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <label for="created_at" class="col-sm-3 col-form-label">Joined</label>
                        <div class="col-sm-9">
                            <input type="text" class="form-control-plaintext" id="created_at" value="<?php echo date('F d, Y h:i A', strtotime($user['created_at'])); ?>" readonly>
                        </div>
                    </div>
                    
                    <div class="text-end">
                        <a href="users.php" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">Update User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php include_once '../includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 