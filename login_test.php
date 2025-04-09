<?php
// Start with a clean script with no output
session_start();

// Clear any previous session data
session_unset();

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get credentials
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    // Simple validation
    if (!empty($email) && !empty($password)) {
        // Connect to database
        $conn = mysqli_connect('localhost', 'root', '', 'fittrack');
        
        if (!$conn) {
            $error = "Database connection failed: " . mysqli_connect_error();
        } else {
            // Get user
            $email = mysqli_real_escape_string($conn, $email);
            $query = "SELECT * FROM users WHERE email = '$email'";
            $result = mysqli_query($conn, $query);
            
            if ($result && mysqli_num_rows($result) > 0) {
                $user = mysqli_fetch_assoc($result);
                
                if (password_verify($password, $user['password'])) {
                    // Set session variables
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_name'] = $user['name'];
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['user_type'] = $user['user_type'];
                    
                    // Redirect based on user type
                    if ($_SESSION['user_type'] == 'customer') {
                        header("Location: customer/dashboard.php");
                        exit();
                    } elseif ($_SESSION['user_type'] == 'coach') {
                        header("Location: coach/dashboard.php");
                        exit();
                    } elseif ($_SESSION['user_type'] == 'admin') {
                        header("Location: admin/dashboard.php");
                        exit();
                    }
                } else {
                    $error = "Invalid password";
                }
            } else {
                $error = "User not found";
            }
            
            mysqli_close($conn);
        }
    } else {
        $error = "Please enter email and password";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Simple Login Test</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .container { max-width: 400px; margin: 0 auto; }
        .error { color: red; margin-bottom: 15px; }
        .success { color: green; margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; }
        input[type="email"], input[type="password"] { width: 100%; padding: 8px; margin-bottom: 15px; }
        button { background: #4285f4; color: white; border: none; padding: 10px 15px; cursor: pointer; }
        .debug { margin-top: 20px; padding: 10px; background: #f5f5f5; border: 1px solid #ddd; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Simple Login Test</h1>
        
        <?php if (isset($error)): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <form method="post" action="">
            <div>
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" required>
            </div>
            
            <div>
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <button type="submit">Login</button>
        </form>
        
        <div class="debug">
            <h3>Session Debugging</h3>
            <p>Session ID: <?php echo session_id(); ?></p>
            <p>Session Status: <?php echo session_status(); ?> (2 = active)</p>
            <pre><?php var_dump($_SESSION); ?></pre>
        </div>
    </div>
</body>
</html> 