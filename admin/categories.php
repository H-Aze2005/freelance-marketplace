<?php
session_start();
require_once('../database/connection.php');
require_once('../utils/auth.php');
require_once('../utils/csrf.php');

// Require admin
requireAdmin();

// Get all categories
$stmt = $db->query("SELECT * FROM categories ORDER BY name");
$categories = $stmt->fetchAll();

// Handle form submissions
$errors = [];
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Validate CSRF token
  if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
    $errors[] = "Invalid form submission";
  } else {
    // Add category
    if (isset($_POST['action']) && $_POST['action'] === 'add') {
      $name = trim($_POST['name']);
      $description = trim($_POST['description']);
      
      if (empty($name)) {
        $errors[] = "Category name is required";
      } else {
        // Check if category already exists
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM categories WHERE name = ?");
        $stmt->execute([$name]);
        
        if ($stmt->fetch()['count'] > 0) {
          $errors[] = "Category with this name already exists";
        } else {
          $stmt = $db->prepare("INSERT INTO categories (name, description) VALUES (?, ?)");
          
          if ($stmt->execute([$name, $description])) {
            $success = "Category added successfully";
            // Refresh categories list
            $stmt = $db->query("SELECT * FROM categories ORDER BY name");
            $categories = $stmt->fetchAll();
          } else {
            $errors[] = "Failed to add category";
          }
        }
      }
    }
    
    // Edit category
    if (isset($_POST['action']) && $_POST['action'] === 'edit') {
      $id = $_POST['id'];
      $name = trim($_POST['name']);
      $description = trim($_POST['description']);
      
      if (empty($name)) {
        $errors[] = "Category name is required";
      } else {
        // Check if category already exists with this name (excluding current category)
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM categories WHERE name = ? AND id != ?");
        $stmt->execute([$name, $id]);
        
        if ($stmt->fetch()['count'] > 0) {
          $errors[] = "Category with this name already exists";
        } else {
          $stmt = $db->prepare("UPDATE categories SET name = ?, description = ? WHERE id = ?");
          
          if ($stmt->execute([$name, $description, $id])) {
            $success = "Category updated successfully";
            // Refresh categories list
            $stmt = $db->query("SELECT * FROM categories ORDER BY name");
            $categories = $stmt->fetchAll();
          } else {
            $errors[] = "Failed to update category";
          }
        }
      }
    }
    
    // Delete category
    if (isset($_POST['action']) && $_POST['action'] === 'delete') {
      $id = $_POST['id'];
      
      // Check if category is in use
      $stmt = $db->prepare("SELECT COUNT(*) as count FROM services WHERE category_id = ?");
      $stmt->execute([$id]);
      
      if ($stmt->fetch()['count'] > 0) {
        $errors[] = "Cannot delete category because it is being used by services";
      } else {
        $stmt = $db->prepare("DELETE FROM categories WHERE id = ?");
        
        if ($stmt->execute([$id])) {
          $success = "Category deleted successfully";
          // Refresh categories list
          $stmt = $db->query("SELECT * FROM categories ORDER BY name");
          $categories = $stmt->fetchAll();
        } else {
          $errors[] = "Failed to delete category";
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
  <title>Manage Categories - FreelanceHub</title>
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
    
    @media (max-width: 768px) {
      .admin-container {
        grid-template-columns: 1fr;
      }
      
      .table {
        display: block;
        overflow-x: auto;
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
            <a href="users.php">Manage Users</a>
            <a href="categories.php" class="active">Manage Categories</a>
            <a href="services.php">Manage Services</a>
            <a href="orders.php">Manage Orders</a>
          </div>
        </div>
        
        <div class="admin-content">
          <div class="admin-header">
            <h1>Manage Categories</h1>
            <button class="btn-primary" id="addCategoryBtn">Add Category</button>
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
                <th>Description</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($categories as $category): ?>
                <tr>
                  <td><?= $category['id'] ?></td>
                  <td><?= htmlspecialchars($category['name']) ?></td>
                  <td><?= htmlspecialchars($category['description'] ?? '') ?></td>
                  <td>
                    <button class="btn-secondary edit-category-btn" data-id="<?= $category['id'] ?>" data-name="<?= htmlspecialchars($category['name']) ?>" data-description="<?= htmlspecialchars($category['description'] ?? '') ?>">Edit</button>
                    <button class="btn-danger delete-category-btn" data-id="<?= $category['id'] ?>" data-name="<?= htmlspecialchars($category['name']) ?>">Delete</button>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </main>
  
  &lt;!-- Add Category Modal -->
  <div id="addCategoryModal" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <h2>Add Category</h2>
        <span class="modal-close">&times;</span>
      </div>
      
      <form action="categories.php" method="POST">
        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
        <input type="hidden" name="action" value="add">
        
        <div class="form-group">
          <label for="name">Category Name</label>
          <input type="text" id="name" name="name" required>
        </div>
        
        <div class="form-group">
          <label for="description">Description</label>
          <textarea id="description" name="description" rows="4"></textarea>
        </div>
        
        <button type="submit" class="btn-primary">Add Category</button>
      </form>
    </div>
  </div>
  
  &lt;!-- Edit Category Modal -->
  <div id="editCategoryModal" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <h2>Edit Category</h2>
        <span class="modal-close">&times;</span>
      </div>
      
      <form action="categories.php" method="POST">
        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="id" id="edit-id">
        
        <div class="form-group">
          <label for="edit-name">Category Name</label>
          <input type="text" id="edit-name" name="name" required>
        </div>
        
        <div class="form-group">
          <label for="edit-description">Description</label>
          <textarea id="edit-description" name="description" rows="4"></textarea>
        </div>
        
        <button type="submit" class="btn-primary">Update Category</button>
      </form>
    </div>
  </div>
  
  &lt;!-- Delete Category Modal -->
  <div id="deleteCategoryModal" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <h2>Delete Category</h2>
        <span class="modal-close">&times;</span>
      </div>
      
      <p>Are you sure you want to delete the category <strong id="delete-category-name"></strong>?</p>
      <p>This action cannot be undone.</p>
      
      <form action="categories.php" method="POST">
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
      const addCategoryBtn = document.getElementById('addCategoryBtn');
      const addCategoryModal = document.getElementById('addCategoryModal');
      const editCategoryModal = document.getElementById('editCategoryModal');
      const deleteCategoryModal = document.getElementById('deleteCategoryModal');
      const modalCloseButtons = document.querySelectorAll('.modal-close, .modal-close-btn');
      
      // Open add category modal
      addCategoryBtn.addEventListener('click', function() {
        addCategoryModal.style.display = 'block';
      });
      
      // Open edit category modal
      const editButtons = document.querySelectorAll('.edit-category-btn');
      editButtons.forEach(button => {
        button.addEventListener('click', function() {
          const id = this.getAttribute('data-id');
          const name = this.getAttribute('data-name');
          const description = this.getAttribute('data-description');
          
          document.getElementById('edit-id').value = id;
          document.getElementById('edit-name').value = name;
          document.getElementById('edit-description').value = description;
          
          editCategoryModal.style.display = 'block';
        });
      });
      
      // Open delete category modal
      const deleteButtons = document.querySelectorAll('.delete-category-btn');
      deleteButtons.forEach(button => {
        button.addEventListener('click', function() {
          const id = this.getAttribute('data-id');
          const name = this.getAttribute('data-name');
          
          document.getElementById('delete-id').value = id;
          document.getElementById('delete-category-name').textContent = name;
          
          deleteCategoryModal.style.display = 'block';
        });
      });
      
      // Close modals
      modalCloseButtons.forEach(button => {
        button.addEventListener('click', function() {
          addCategoryModal.style.display = 'none';
          editCategoryModal.style.display = 'none';
          deleteCategoryModal.style.display = 'none';
        });
      });
      
      // Close modal when clicking outside
      window.addEventListener('click', function(event) {
        if (event.target === addCategoryModal) {
          addCategoryModal.style.display = 'none';
        }
        if (event.target === editCategoryModal) {
          editCategoryModal.style.display = 'none';
        }
        if (event.target === deleteCategoryModal) {
          deleteCategoryModal.style.display = 'none';
        }
      });
    });
  </script>
</body>
</html>
