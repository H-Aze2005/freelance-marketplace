<?php
session_start();
require_once('../database/connection.php');
require_once('../utils/auth.php');
require_once('../utils/csrf.php');

// Require login
requireLogin();

// Get categories
$stmt = $db->query("SELECT * FROM categories ORDER BY name");
$categories = $stmt->fetchAll();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Validate CSRF token
  if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
    $errors[] = "Invalid form submission";
  } else {
    // Validate inputs
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $category_id = $_POST['category_id'];
    $price = $_POST['price'];
    $delivery_time = $_POST['delivery_time'];
    
    if (empty($title)) {
      $errors[] = "Title is required";
    } elseif (strlen($title) > 100) {
      $errors[] = "Title must be less than 100 characters";
    }
    
    if (empty($description)) {
      $errors[] = "Description is required";
    }
    
    if (empty($category_id)) {
      $errors[] = "Category is required";
    }
    
    if (!is_numeric($price) || $price <= 0) {
      $errors[] = "Price must be a positive number";
    }
    
    if (!is_numeric($delivery_time) || $delivery_time <= 0) {
      $errors[] = "Delivery time must be a positive number";
    }
    
    // Check if uploaded files are valid images
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
    $maxFileSize = 5 * 1024 * 1024; // 5MB
    
    $uploadedImages = [];
    
    if (isset($_FILES['images']) && !empty($_FILES['images']['name'][0])) {
      $fileCount = count($_FILES['images']['name']);
      
      for ($i = 0; $i < $fileCount; $i++) {
        if ($_FILES['images']['error'][$i] === UPLOAD_ERR_OK) {
          $tmpName = $_FILES['images']['tmp_name'][$i];
          $fileName = $_FILES['images']['name'][$i];
          $fileType = $_FILES['images']['type'][$i];
          $fileSize = $_FILES['images']['size'][$i];
          
          if (!in_array($fileType, $allowedTypes)) {
            $errors[] = "File '$fileName' is not a valid image type. Only JPG, PNG, and GIF are allowed.";
            continue;
          }
          
          if ($fileSize > $maxFileSize) {
            $errors[] = "File '$fileName' exceeds the maximum file size of 5MB.";
            continue;
          }
          
          $uploadedImages[] = [
            'tmp_name' => $tmpName,
            'name' => $fileName,
            'type' => $fileType
          ];
        } elseif ($_FILES['images']['error'][$i] !== UPLOAD_ERR_NO_FILE) {
          $errors[] = "Error uploading file: " . $_FILES['images']['error'][$i];
        }
      }
    }
    
    // If no errors, create the service
    if (empty($errors)) {
      try {
        $db->beginTransaction();
        
        // Insert service
        $stmt = $db->prepare("
          INSERT INTO services (title, description, price, delivery_time, freelancer_id, category_id)
          VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
          $title,
          $description,
          $price,
          $delivery_time,
          $_SESSION['user_id'],
          $category_id
        ]);
        
        $serviceId = $db->lastInsertId();
        
        // Upload images
        if (!empty($uploadedImages)) {
          $uploadDir = '../uploads/services/';
          
          // Create directory if it doesn't exist
          if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
          }
          
          $stmt = $db->prepare("
            INSERT INTO service_images (service_id, image_path, is_primary)
            VALUES (?, ?, ?)
          ");
          
          foreach ($uploadedImages as $index => $image) {
            $extension = pathinfo($image['name'], PATHINFO_EXTENSION);
            $newFileName = 'service_' . $serviceId . '_' . uniqid() . '.' . $extension;
            $filePath = $uploadDir . $newFileName;
            
            if (move_uploaded_file($image['tmp_name'], $filePath)) {
              $relativePath = 'uploads/services/' . $newFileName;
              $isPrimary = ($index === 0) ? 1 : 0; // First image is primary
              
              $stmt->execute([$serviceId, $relativePath, $isPrimary]);
            } else {
              throw new Exception("Failed to upload image: " . $image['name']);
            }
          }
        }
        
        $db->commit();
        
        $_SESSION['success'] = "Service created successfully!";
        header("Location: view.php?id=$serviceId");
        exit();
      } catch (Exception $e) {
        $db->rollBack();
        $errors[] = "Error: " . $e->getMessage();
      }
    }
  }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Create Service - FreelanceHub</title>
  <link rel="stylesheet" href="../css/styles.css">
  <style>
    .create-service-container {
      max-width: 800px;
      margin: 30px auto;
      background-color: white;
      border-radius: 8px;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
      padding: 30px;
    }
    
    .form-group {
      margin-bottom: 20px;
    }
    
    .form-group label {
      display: block;
      margin-bottom: 5px;
      font-weight: 500;
    }
    
    .image-preview {
      display: flex;
      flex-wrap: wrap;
      gap: 10px;
      margin-top: 10px;
    }
    
    .preview-item {
      width: 100px;
      height: 100px;
      border-radius: 4px;
      overflow: hidden;
      position: relative;
    }
    
    .preview-item img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }
    
    .remove-image {
      position: absolute;
      top: 5px;
      right: 5px;
      background-color: rgba(0, 0, 0, 0.5);
      color: white;
      width: 20px;
      height: 20px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      font-size: 12px;
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
  </style>
</head>
<body>
  <?php include('../templates/header.php'); ?>
  
  <main>
    <div class="container">
      <div class="create-service-container">
        <h1>Create a New Service</h1>
        
        <?php if (!empty($errors)): ?>
          <div class="error-list">
            <ul>
              <?php foreach ($errors as $error): ?>
                <li><?= $error ?></li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>
        
        <form action="create.php" method="POST" enctype="multipart/form-data">
          <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
          
          <div class="form-group">
            <label for="title">Service Title</label>
            <input type="text" id="title" name="title" value="<?= isset($_POST['title']) ? htmlspecialchars($_POST['title']) : '' ?>" required>
            <small>A clear, concise title that describes your service (max 100 characters)</small>
          </div>
          
          <div class="form-group">
            <label for="category_id">Category</label>
            <select id="category_id" name="category_id" required>
              <option value="">Select a category</option>
              <?php foreach ($categories as $category): ?>
                <option value="<?= $category['id'] ?>" <?= (isset($_POST['category_id']) && $_POST['category_id'] == $category['id']) ? 'selected' : '' ?>>
                  <?= htmlspecialchars($category['name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          
          <div class="form-group">
            <label for="description">Description</label>
            <textarea id="description" name="description" rows="8" required><?= isset($_POST['description']) ? htmlspecialchars($_POST['description']) : '' ?></textarea>
            <small>Detailed description of your service, what's included, and your process</small>
          </div>
          
          <div class="form-group">
            <label for="price">Price ($)</label>
            <input type="number" id="price" name="price" min="1" step="0.01" value="<?= isset($_POST['price']) ? htmlspecialchars($_POST['price']) : '' ?>" required>
          </div>
          
          <div class="form-group">
            <label for="delivery_time">Delivery Time (days)</label>
            <input type="number" id="delivery_time" name="delivery_time" min="1" value="<?= isset($_POST['delivery_time']) ? htmlspecialchars($_POST['delivery_time']) : '' ?>" required>
          </div>
          
          <div class="form-group">
            <label for="images">Service Images</label>
            <input type="file" id="images" name="images[]" accept="image/jpeg, image/png, image/gif" multiple>
            <small>Upload up to 5 images (JPG, PNG, GIF, max 5MB each). First image will be the main image.</small>
            
            <div class="image-preview" id="imagePreview"></div>
          </div>
          
          <button type="submit" class="btn-primary">Create Service</button>
        </form>
      </div>
    </div>
  </main>
  
  <?php include('../templates/footer.php'); ?>
  
  <script src="../js/main.js"></script>
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const imageInput = document.getElementById('images');
      const imagePreview = document.getElementById('imagePreview');
      
      imageInput.addEventListener('change', function() {
        imagePreview.innerHTML = '';
        
        if (this.files) {
          const files = Array.from(this.files);
          
          files.forEach((file, index) => {
            if (file.type.match('image.*')) {
              const reader = new FileReader();
              
              reader.onload = function(e) {
                const previewItem = document.createElement('div');
                previewItem.className = 'preview-item';
                
                const img = document.createElement('img');
                img.src = e.target.result;
                
                const removeBtn = document.createElement('div');
                removeBtn.className = 'remove-image';
                removeBtn.innerHTML = '×';
                removeBtn.addEventListener('click', function() {
                  // Create a new FileList without this file
                  const dt = new DataTransfer();
                  const input = document.getElementById('images');
                  
                  for (let i = 0; i < input.files.length; i++) {
                    if (i !== index) {
                      dt.items.add(input.files[i]);
                    }
                  }
                  
                  input.files = dt.files;
                  previewItem.remove();
                });
                
                previewItem.appendChild(img);
                previewItem.appendChild(removeBtn);
                imagePreview.appendChild(previewItem);
              };
              
              reader.readAsDataURL(file);
            }
          });
        }
      });
    });
  </script>
</body>
</html>
