<?php
session_start();
require_once('database/connection.php');
require_once('utils/validation.php');
require_once('utils/csrf.php');

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
  header("Location: index.php");
  exit();
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Validate CSRF token
  if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
    $errors[] = "Invalid form submission";
  } else {
    // Validate inputs
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirm_password'];
    $name = trim($_POST['name']);
    
    $usernameError = validateUsername($username);
    if ($usernameError) $errors[] = $usernameError;
    
    $emailError = validateEmail($email);
    if ($emailError) $errors[] = $emailError;
    
    $passwordError = validatePassword($password);
    if ($passwordError) $errors[] = $passwordError;
    
    $nameError = validateName($name);
    if ($nameError) $errors[] = $nameError;
    
    if ($password !== $confirmPassword) {
      $errors[] = "Passwords do not match";
    }
    
    // Check if username or email already exists
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE username = ?");
    $stmt->execute([$username]);
    if ($stmt->fetch()['count'] > 0) {
      $errors[] = "Username already taken";
    }
    
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()['count'] > 0) {
      $errors[] = "Email already registered";
    }
    
    // If no errors, create the user
    if (empty($errors)) {
      $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
      
      $stmt = $db->prepare("
        INSERT INTO users (username, password, email, name) 
        VALUES (?, ?, ?, ?)
      ");
      
      if ($stmt->execute([$username, $hashedPassword, $email, $name])) {
        $userId = $db->lastInsertId();
        
        // Log the user in
        $_SESSION['user_id'] = $userId;
        $_SESSION['username'] = $username;
        $_SESSION['name'] = $name;
        $_SESSION['is_admin'] = 0;
        
        $_SESSION['success'] = "Registration successful! Welcome to FreelanceHub.";
        
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
        $errors[] = "Registration failed. Please try again.";
      }
    }
  }
}
?>

&lt;!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Register - FreelanceHub</title>
  <link rel="stylesheet" href="css/styles.css">
  <style>
    .register-container {
      max-width: 500px;
      margin: 50px auto;
      padding: 30px;
      background-color: white;
      border-radius: 8px;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    }
    
    .register-container h1 {
      text-align: center;
      margin-bottom: 30px;
    }
    
    .form-group {
      margin-bottom: 20px;
    }
    
    .error-list {
      background-color: #f8d7da;
      color: #721c24;
      padding: 15px;
      border-radius: 4px;
      margin-bottom: 20px;
    }
    
    .error-list ul {
      margin: 0;
      padding-left: 20px;
    }
    
    .login-link {
      text-align: center;
      margin-top: 20px;
    }
  </style>
</head>
<body>
  <?php include('templates/header.php'); ?>
  
  <main>
    <div class="container">
      <div class="register-container">
        <h1>Create an Account</h1>
        
        <?php if (!empty($errors)): ?>
          <div class="error-list">
            <ul>
              <?php foreach ($errors as $error): ?>
                <li><?= $error ?></li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>
        
        <form action="register.php" method="POST">
          <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
          
          <div class="form-group">
            <label for="name">Full Name</label>
            <input type="text" id="name" name="name" value="<?= isset($_POST['name']) ? htmlspecialchars($_POST['name']) : '' ?>" required>
          </div>
          
          <div class="form-group">
            <label for="username">Username</label>
            <input type="text" id="username" name="username" value="<?= isset($_POST['username']) ? htmlspecialchars($_POST['username']) : '' ?>" required>
          </div>
          
          <div class="form-group">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>" required>
          </div>
          
          <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" required>
          </div>
          
          <div class="form-group">
            <label for="confirm_password">Confirm Password</label>
            <input type="password" id="confirm_password" name="confirm_password" required>
          </div>
          
          <button type="submit" class="btn-primary" style="width: 100%;">Register</button>
        </form>
        
        <div class="login-link">
          Already have an account? <a href="login.php">Login</a>
        </div>
      </div>
    </div>
  </main>
  
  <?php include('templates/footer.php'); ?>
  
  <script src="js/main.js"></script>
</body>
</html>
