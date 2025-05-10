<?php
session_start();
require_once('../database/connection.php');
require_once('../utils/auth.php');
require_once('../utils/csrf.php');
require_once('../utils/validation.php');

// Require admin
requireAdmin();

// Get all users
$stmt = $db->query("SELECT * FROM users ORDER BY created_at DESC");
$users = $stmt->fetchAll();

// Handle form submissions
$errors = [];
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Validate CSRF token
  if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
    $errors[] = "Invalid form submission";
  } else {
    // Edit user
    if (isset($_POST['action']) && $_POST['action'] === 'edit') {
      $id = $_POST['id'];
      $name = trim($_POST['name']);
      $email = trim($_POST['email']);
      $username = trim($_POST['username']);
      $is_admin = isset($_POST['is_admin']) ? 1 : 0;
      
      // Validate inputs
      $nameError = validateName($name);
      if ($nameError) $errors[] = $nameError;
      
      $emailError = validateEmail($email);
      if ($emailError) $errors[] = $emailError;
      
      $usernameError = validateUsername($username);
      if ($usernameError) $errors[] = $usernameError;
      
      // Check if username or email already exists (excluding current user)
      $stmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE username = ? AND id != ?");
      $stmt->execute([$username, $id]);
      if ($stmt->fetch()['count'] > 0) {
        $errors[] = "Username already taken";
      }
      
      $stmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE email = ? AND id != ?");
      $stmt->execute([$email, $id]);
      if ($stmt->fetch()['count'] > 0) {
        $errors[] = "Email already registered";
      }
      
      // If no errors, update the user
      if (empty($errors)) {
        $stmt = $db->prepare("
          UPDATE users 
          SET name = ?, email = ?, username = ?, is_admin = ?
          WHERE id = ?
        ");
        
        if ($stmt->execute([$name, $email, $username, $is_admin, $id])) {
          $success = "User updated successfully";
          
          // Refresh users list
          $stmt = $db->query("SELECT * FROM users ORDER BY created_at DESC");
          $users = $stmt->fetchAll();
        } else {
          $errors[] = "Failed to update user";
        }
      }
    }
    
    // Change password
    if (isset($_POST['action']) && $_POST['action'] === 'change_password') {
      $id = $_POST['id'];
      $password = $_POST['password'];
      $confirm_password = $_POST['confirm_password'];
      
      // Validate password
      $passwordError = validatePassword($password);
      if ($passwordError) $errors[] = $passwordError;
      
      if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match";
      }
      
      // If no errors, update the password
      if (empty($errors)) {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
        
        if ($stmt->execute([$hashedPassword, $id])) {
          $success = "Password updated successfully";
        } else {
          $errors[] = "Failed to update password";
        }
      }
    }
    
    // Delete user
    if (isset($_POST['action']) && $_POST['action'] === 'delete') {
      $id = $_POST['id'];
      
      // Check if user is the current admin
      if ($id == $_SESSION['user_id']) {
        $errors[] = "You cannot delete your own account";
      } else {
        try {
          $db->beginTransaction();
          
          // Delete user's services
          $stmt = $db->prepare("SELECT id FROM services WHERE freelancer_id = ?");
          $stmt->execute([$id]);
          $services = $stmt->fetchAll();
          
          foreach ($services as $service) {
            // Delete service images
            $stmt = $db->prepare("DELETE FROM service_images WHERE service_id = ?");
            $stmt->execute([$service['id']]);
            
            // Delete service reviews
            $stmt = $db->prepare("
              DELETE FROM reviews 
              WHERE order_id IN (SELECT id FROM orders WHERE service_id = ?)
            ");
            $stmt->execute([$service['id']]);
            
            // Delete service messages
            $stmt = $db->prepare("
              DELETE FROM messages 
              WHERE order_id IN (SELECT id FROM orders WHERE service_id = ?)
            ");
            $stmt->execute([$service['id']]);
            
            // Delete service orders
            $stmt = $db->prepare("DELETE FROM orders WHERE service_id = ?");
            $stmt->execute([$service['id']]);
          }
          
          // Delete user's services
          $stmt = $db->prepare("DELETE FROM services WHERE freelancer_id = ?");
          $stmt->execute([$id]);
          
          // Delete user's orders
          $stmt = $db->prepare("DELETE FROM orders WHERE client_id = ?");
          $stmt->execute([$id]);
          
          // Delete user's messages
          $stmt = $db->prepare("DELETE FROM messages WHERE sender_id = ? OR receiver_id = ?");
          $stmt->execute([$id, $id]);
          
          // Finally, delete the user
          $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
          $stmt->execute([$id]);
          
          $db->commit();
          
          $success = "User deleted successfully";
          
          // Refresh users list
          $stmt = $db->query("SELECT * FROM users ORDER BY created_at DESC");
          $users = $stmt->fetchAll();
        } catch (Exception $e) {
          $db->rollBack();
          $errors[] = "Error: " . $e->getMessage();
        }
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
  <title>Manage Users - FreelanceHub</title>
  <link rel="stylesheet" href="../css/styles.css">
  <style>
    .admin-container {
      display: grid;
      grid-template-columns: 1fr 4fr;
      gap: 30px;
      margin: 30px 0;
    }
    
    .admin-sidebar {
      background-color: white;
      border-radius: 8px;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
      padding: 20px;
    }
    
    .admin-nav {
      margin-top: 20px;
    }
    
    .admin-nav a {
      display: block;
      padding: 10px 15px;
      margin-bottom: 5px;
      border-radius: 4px;
      color: #333;
      transition: background-color 0.3s;
    }
    
    .admin-nav a:hover, .admin-nav a.active {
      background-color: #f0f7f4;
      color: #1dbf73;
    }
    
    .admin-content {
      background-color: white;
      border-radius: 8px;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
      padding: 20px;
    }
    
    .admin-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 20px;
      padding-bottom: 20px;
      border-bottom: 1px solid #eee;
    }
    
    .table {
      width: 100%;
      border-collapse: collapse;
    }
    
    .table th, .table td {
      padding: 10px;
      text-align: left;
      border-bottom: 1px solid #eee;
    }
    
    .table th {
      font-weight: 600;
      color: #555;
    }
    
    .table tr:last-child td {
      border-bottom: none;
    }
    
    .badge-admin {
      background-color: #d4edda;
      color: #155724;
      padding: 3px 8px;
      border-radius: 4px;
      font-size: 0.8rem;
      font-weight: 500;
    }
    
    .modal {
      display: none;
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background-color: rgba(0, 0, 0, 0.5);
      z-index: 1000;
      overflow: auto;
    }
    
    .modal-content {
      background-color: white;
      margin: 100px auto;
      padding: 20px;
      border-radius: 8px;
      max-width: 500px;
      box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
    }
    
    .modal-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 20px;
      padding-bottom: 10px;
      border-bottom: 1px solid #eee;
    }
    
    .modal-close {
      font-size: 1.5rem;
      cursor: pointer;
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
    
    .success-message {
      background-color: #d4edda;
      color: #155724;
      padding: 15px;
      border-radius: 4px;
      margin-bottom: 20px;
    }
    
    .action-buttons {
      display: flex;
      gap: 5px;
    }
    
    @media (max-width: 768px) {
      .admin-container {
        grid-template-columns: 1fr;
      }
      
      .table {
        display: block;
        overflow-x: auto;
      }
      
      .action-buttons {
        flex-direction: column;
      }
    }
  </style>
</head>
<body>
  <?php include('../templates/header.php'); ?>
  
  <main>
    <div class="container">
      <div class="admin-container">
        <div class="admin-sidebar">
          <h2>Admin Panel</h2>
          
          <div class="admin-nav">
            <a href="index.php">Dashboard</a>
            <a href="users.php" class="active">Manage Users</a>
            <a href="categories.php">Manage Categories</a>
            <a href="services.php">Manage Services</a>
            <a href="orders.php">Manage Orders</a>
          </div>
        </div>
        
        <div class="admin-content">
          <div class="admin-header">
            <h1>Manage Users</h1>
          </div>
          
          <?php if (!empty($errors)): ?>
            <div class="error-list">
              <ul>
                <?php foreach ($errors as $error): ?>
                  <li><?= $error ?></li>
                <?php endforeach; ?>
              </ul>
            </div>
          <?php endif; ?>
          
          <?php if ($success): ?>
            <div class="success-message">
              <?= $success ?>
            </div>
          <?php endif; ?>
          
          <table class="table">
            <thead>
              <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Username</th>
                <th>Email</th>
                <th>Role</th>
                <th>Joined</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($users as $user): ?>
                <tr>
                  <td><?= $user['id'] ?></td>
                  <td><?= htmlspecialchars($user['name']) ?></td>
                  <td><?= htmlspecialchars($user['username']) ?></td>
                  <td><?= htmlspecialchars($user['email']) ?></td>
                  <td>
                    <?php if ($user['is_admin']): ?>
                      <span class="badge-admin">Admin</span>
                    <?php else: ?>
                      User
                    <?php endif; ?>
                  </td>
                  <td><?= date('M d, Y', strtotime($user['created_at'])) ?></td>
                  <td>
                    <div class="action-buttons">
                      <button class="btn-secondary edit-user-btn" 
                              data-id="<?= $user['id'] ?>" 
                              data-name="<?= htmlspecialchars($user['name']) ?>" 
                              data-username="<?= htmlspecialchars($user['username']) ?>" 
                              data-email="<?= htmlspecialchars($user['email']) ?>" 
                              data-is-admin="<?= $user['is_admin'] ?>">
                        Edit
                      </button>
                      <button class="btn-secondary change-password-btn" 
                              data-id="<?= $user['id'] ?>" 
                              data-name="<?= htmlspecialchars($user['name']) ?>">
                        Change Password
                      </button>
                      <?php if ($user['id'] != $_SESSION['user_id']): ?>
                        <button class="btn-danger delete-user-btn" 
                                data-id="<?= $user['id'] ?>" 
                                data-name="<?= htmlspecialchars($user['name']) ?>">
                          Delete
                        </button>
                      <?php endif; ?>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </main>
  
  &lt;!-- Edit User Modal -->
  <div id="editUserModal" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <h2>Edit User</h2>
        <span class="modal-close">&times;</span>
      </div>
      
      <form action="users.php" method="POST">
        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="id" id="edit-id">
        
        <div class="form-group">
          <label for="edit-name">Name</label>
          <input type="text" id="edit-name" name="name" required>
        </div>
        
        <div class="form-group">
          <label for="edit-username">Username</label>
          <input type="text" id="edit-username" name="username" required>
        </div>
        
        <div class="form-group">
          <label for="edit-email">Email</label>
          <input type="email" id="edit-email" name="email" required>
        </div>
        
        <div class="form-group">
          <label>
            <input type="checkbox" id="edit-is-admin" name="is_admin" value="1">
            Admin Privileges
          </label>
        </div>
        
        <button type="submit" class="btn-primary">Update User</button>
      </form>
    </div>
  </div>
  
  &lt;!-- Change Password Modal -->
  <div id="changePasswordModal" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <h2>Change Password</h2>
        <span class="modal-close">&times;</span>
      </div>
      
      <p>Change password for <strong id="password-user-name"></strong></p>
      
      <form action="users.php" method="POST">
        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
        <input type="hidden" name="action" value="change_password">
        <input type="hidden" name="id" id="password-id">
        
        <div class="form-group">
          <label for="password">New Password</label>
          <input type="password" id="password" name="password" required>
          <small>Password must be at least 8 characters long</small>
        </div>
        
        <div class="form-group">
          <label for="confirm_password">Confirm Password</label>
          <input type="password" id="confirm_password" name="confirm_password" required>
        </div>
        
        <button type="submit" class="btn-primary">Change Password</button>
      </form>
    </div>
  </div>
  
  &lt;!-- Delete User Modal -->
  <div id="deleteUserModal" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <h2>Delete User</h2>
        <span class="modal-close">&times;</span>
      </div>
      
      <p>Are you sure you want to delete the user <strong id="delete-user-name"></strong>?</p>
      <p>This will also delete all their services, orders, and messages. This action cannot be undone.</p>
      
      <form action="users.php" method="POST">
        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" id="delete-id">
        
        <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px;">
          <button type="button" class="btn-secondary modal-close-btn">Cancel</button>
          <button type="submit" class="btn-danger">Delete</button>
        </div>
      </form>
    </div>
  </div>
  
  <?php include('../templates/footer.php'); ?>
  
  <script src="../js/main.js"></script>
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      // Modal functionality
      const editUserModal = document.getElementById('editUserModal');
      const changePasswordModal = document.getElementById('changePasswordModal');
      const deleteUserModal = document.getElementById('deleteUserModal');
      const modalCloseButtons = document.querySelectorAll('.modal-close, .modal-close-btn');
      
      // Open edit user modal
      const editButtons = document.querySelectorAll('.edit-user-btn');
      editButtons.forEach(button => {
        button.addEventListener('click', function() {
          const id = this.getAttribute('data-id');
          const name = this.getAttribute('data-name');
          const username = this.getAttribute('data-username');
          const email = this.getAttribute('data-email');
          const isAdmin = this.getAttribute('data-is-admin') === '1';
          
          document.getElementById('edit-id').value = id;
          document.getElementById('edit-name').value = name;
          document.getElementById('edit-username').value = username;
          document.getElementById('edit-email').value = email;
          document.getElementById('edit-is-admin').checked = isAdmin;
          
          editUserModal.style.display = 'block';
        });
      });
      
      // Open change password modal
      const changePasswordButtons = document.querySelectorAll('.change-password-btn');
      changePasswordButtons.forEach(button => {
        button.addEventListener('click', function() {
          const id = this.getAttribute('data-id');
          const name = this.getAttribute('data-name');
          
          document.getElementById('password-id').value = id;
          document.getElementById('password-user-name').textContent = name;
          
          changePasswordModal.style.display = 'block';
        });
      });
      
      // Open delete user modal
      const deleteButtons = document.querySelectorAll('.delete-user-btn');
      deleteButtons.forEach(button => {
        button.addEventListener('click', function() {
          const id = this.getAttribute('data-id');
          const name = this.getAttribute('data-name');
          
          document.getElementById('delete-id').value = id;
          document.getElementById('delete-user-name').textContent = name;
          
          deleteUserModal.style.display = 'block';
        });
      });
      
      // Close modals
      modalCloseButtons.forEach(button => {
        button.addEventListener('click', function() {
          editUserModal.style.display = 'none';
          changePasswordModal.style.display = 'none';
          deleteUserModal.style.display = 'none';
        });
      });
      
      // Close modal when clicking outside
      window.addEventListener('click', function(event) {
        if (event.target === editUserModal) {
          editUserModal.style.display = 'none';
        }
        if (event.target === changePasswordModal) {
          changePasswordModal.style.display = 'none';
        }
        if (event.target === deleteUserModal) {
          deleteUserModal.style.display = 'none';
        }
      });
    });
  </script>
</body>
</html>
