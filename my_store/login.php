
<?php
// login/login.php
//
// This is a standalone login page for the Pharmacy Inventory System.
// It handles user authentication and session management.

// Start a session
session_start();

// Initialize error message variable
$login_error = '';

// Check for POST request (form submission)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    // Hardcoded credentials for demonstration as requested
    // In a real application, you would check a database for user credentials
    // and use secure password hashing.
    if ($username === 'Mihir' && $password === '1234') {
        // Successful login: set session variables
        $_SESSION['loggedin'] = true;
        $_SESSION['user'] = $username;
        
        // Redirect to the main inventory page
        // The path is now corrected to point to the 'inventory' directory
        header('Location: dashboard.php');
        exit();
    } else {
        // Failed login: set an error message
        $login_error = 'Invalid username or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Pharmacy Inventory</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            background: linear-gradient(to right, #6a11cb, #2575fc);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .login-card {
            background-color: rgba(255, 255, 255, 0.9);
            padding: 2.5rem;
            border-radius: 1rem;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
            max-width: 450px;
            width: 100%;
            text-align: center;
        }
        .login-card h2 {
            font-weight: 700;
            color: #333;
            margin-bottom: 2rem;
        }
        .form-control {
            border-radius: 0.5rem;
            padding: 0.75rem 1rem;
            font-size: 1.1rem;
            transition: box-shadow 0.3s;
        }
        .form-control:focus {
            box-shadow: 0 0 0 0.25rem rgba(37, 117, 252, 0.25);
        }
        .btn-primary {
            background: #2575fc;
            border-color: #2575fc;
            border-radius: 0.5rem;
            padding: 0.75rem 1rem;
            font-size: 1.1rem;
            font-weight: bold;
            transition: background 0.3s;
        }
        .btn-primary:hover {
            background: #6a11cb;
            border-color: #6a11cb;
        }
        .alert {
            border-radius: 0.5rem;
        }
    </style>
</head>
<body>
    <div class="login-card">
        <h2><i class="fas fa-lock text-primary"></i> Admin Login</h2>
        <?php if (!empty($login_error)): ?>
            <div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($login_error); ?></div>
        <?php endif; ?>
        <form action="login.php" method="POST">
            <div class="mb-4">
                <input type="text" class="form-control" name="username" placeholder="Username" required>
            </div>
            <div class="mb-4">
                <input type="password" class="form-control" name="password" placeholder="Password" required>
            </div>
            <button type="submit" class="btn btn-primary w-100">Login</button>
        </form>
    </div>
</body>
</html>
