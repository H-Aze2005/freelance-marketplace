<?php
session_start();
require_once('database/connection.php');
require_once('utils/csrf.php');

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
  header("Location: index.php");
  exit();
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Validate CSRF token
  if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
    $error = "Invalid form submission";
  } else {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    // Check if input is email or username
    $isEmail = filter_var($username, FILTER_VALIDATE_EMAIL);
    
    if ($isEmail) {
      $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
    } else {
      $stmt = $db->prepare("SELECT * FROM users WHERE username = ?");
    }
    
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password'])) {
      // Login successful
      $_SESSION['user_id'] = $user['id'];
      $_SESSION['username'] = $user['username'];
      $_SESSION['name'] = $user['name'];
      $_SESSION['is_admin'] = $user['is_admin'];
      
      $_SESSION['success'] = "Login successful! Welcome back, " . $user['name'] . ".";
      
      // Redirect to dashboard or intended page
      if (isset($_SESSION['redirect_after_login'])) {
        $redirect = $_SESSION['redirect_after_login'];
        unset($_SESSION['redirect_after_login']);
        header("Location: $redirect");
      } else {
        header("Location: dashboard.php");
      }
      exit();
    } else {
      $error = "Invalid username/email or password";
    }
  }
}
?>

&lt;!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login - GigAnt</title>
  <link rel="stylesheet" href="css/styles.css">
  <style>
    .login-container {
      max-width: 400px;
      margin: 50px auto;
      padding: 30px;
      background-color: white;
      border-radius: 8px;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    }
    
    .login-container h1 {
      text-align: center;
      margin-bottom: 30px;
    }
    
    .form-group {
      margin-bottom: 20px;
    }
    
    .error-message {
      background-color: #f8d7da;
      color: #721c24;
      padding: 15px;
      border-radius: 4px;
      margin-bottom: 20px;
    }
    
    .register-link {
      text-align: center;
      margin-top: 20px;
    }
  </style>
</head>
<body>
  <?php include('templates/header.php'); ?>
  
  <main>
    <div class="container">
      <div class="login-container">
        <h1>Login</h1>
        
        <?php if ($error): ?>
          <div class="error-message">
            <?= $error ?>
          </div>
        <?php endif; ?>
        
        <form action="login.php" method="POST">
          <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
          
          <div class="form-group">
            <label for="username">Username or Email</label>
            <input type="text" id="username" name="username" value="<?= isset($_POST['username']) ? htmlspecialchars($_POST['username']) : '' ?>" required>
          </div>
          
          <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" required>
          </div>
          
          <button type="submit" class="btn-primary" style="width: 100%;">Login</button>
        </form>
        
        <div class="register-link">
          Don't have an account? <a href="register.php">Register</a>
        </div>
      </div>
    </div>
  </main>
  
  <?php include('templates/footer.php'); ?>
  
  <script src="js/main.js"></script>
</body>
</html>
