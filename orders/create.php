<?php
session_start();
require_once('../database/connection.php');
require_once('../utils/auth.php');
require_once('../utils/csrf.php');

// Require login
requireLogin();

// Get service ID
if (!isset($_GET['service_id']) || !is_numeric($_GET['service_id'])) {
  $_SESSION['error'] = "Invalid service ID";
  header("Location: ../index.php");
  exit();
}

$serviceId = $_GET['service_id'];

// Get service details
$stmt = $db->prepare("
  SELECT s.*, u.name as freelancer_name, u.username as freelancer_username, c.name as category_name,
         (SELECT image_path FROM service_images WHERE service_id = s.id AND is_primary = 1 LIMIT 1) as image
  FROM services s
  JOIN users u ON s.freelancer_id = u.id
  JOIN categories c ON s.category_id = c.id
  WHERE s.id = ?
");
$stmt->execute([$serviceId]);
$service = $stmt->fetch();

if (!$service) {
  $_SESSION['error'] = "Service not found";
  header("Location: ../index.php");
  exit();
}

// Check if user is trying to order their own service
if ($service['freelancer_id'] == $_SESSION['user_id']) {
  $_SESSION['error'] = "You cannot order your own service";
  header("Location: ../services/view.php?id=$serviceId");
  exit();
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Validate CSRF token
  if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
    $errors[] = "Invalid form submission";
  } else {
    $requirements = trim($_POST['requirements']);
    
    // Create the order
    try {
      $stmt = $db->prepare("
        INSERT INTO orders (service_id, client_id, status, price, requirements)
        VALUES (?, ?, 'pending', ?, ?)
      ");
      
      $stmt->execute([
        $serviceId,
        $_SESSION['user_id'],
        $service['price'],
        $requirements
      ]);
      
      $orderId = $db->lastInsertId();
      
      // Send notification message to freelancer
      $message = "You have received a new order for your service: " . $service['title'];
      
      $stmt = $db->prepare("
        INSERT INTO messages (sender_id, receiver_id, order_id, content)
        VALUES (?, ?, ?, ?)
      ");
      
      $stmt->execute([
        $_SESSION['user_id'],
        $service['freelancer_id'],
        $orderId,
        $message
      ]);
      
      $_SESSION['success'] = "Order placed successfully!";
      header("Location: view.php?id=$orderId");
      exit();
    } catch (Exception $e) {
      $errors[] = "Error: " . $e->getMessage();
    }
  }
}
?>

&lt;!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Place Order - FreelanceHub</title>
  <link rel="stylesheet" href="../css/styles.css">
  <style>
    .order-container {
      display: grid;
      grid-template-columns: 2fr 1fr;
      gap: 30px;
      margin: 30px 0;
    }
    
    .order-form {
      background-color: white;
      border-radius: 8px;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
      padding: 30px;
    }
    
    .order-summary {
      background-color: white;
      border-radius: 8px;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
      padding: 20px;
      position: sticky;
      top: 90px;
    }
    
    .service-preview {
      display: flex;
      margin-bottom: 20px;
      padding-bottom: 20px;
      border-bottom: 1px solid #eee;
    }
    
    .service-image {
      width: 100px;
      height: 80px;
      border-radius: 4px;
      overflow: hidden;
      margin-right: 15px;
    }
    
    .service-image img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }
    
    .service-info h3 {
      margin-bottom: 5px;
      font-size: 1.1rem;
    }
    
    .service-meta {
      color: #777;
      font-size: 0.9rem;
    }
    
    .order-details {
      margin-bottom: 20px;
    }
    
    .order-detail {
      display: flex;
      justify-content: space-between;
      padding: 10px 0;
      border-bottom: 1px solid #eee;
    }
    
    .order-detail:last-child {
      border-bottom: none;
    }
    
    .order-total {
      font-size: 1.2rem;
      font-weight: 600;
      margin-top: 20px;
      text-align: right;
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
    
    @media (max-width: 768px) {
      .order-container {
        grid-template-columns: 1fr;
      }
      
      .order-summary {
        position: static;
        order: -1;
      }
    }
  </style>
</head>
<body>
  <?php include('../templates/header.php'); ?>
  
  <main>
    <div class="container">
      <div class="order-container">
        <div class="order-form">
          <h1>Place Your Order</h1>
          
          <?php if (!empty($errors)): ?>
            <div class="error-list">
              <ul>
                <?php foreach ($errors as $error): ?>
                  <li><?= $error ?></li>
                <?php endforeach; ?>
              </ul>
            </div>
          <?php endif; ?>
          
          <form action="create.php?service_id=<?= $serviceId ?>" method="POST">
            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
            
            <div class="form-group">
              <label for="requirements">Requirements</label>
              <textarea id="requirements" name="requirements" rows="8" placeholder="Provide details about what you need for this order..."><?= isset($_POST['requirements']) ? htmlspecialchars($_POST['requirements']) : '' ?></textarea>
              <small>Be specific about what you need to help the freelancer deliver exactly what you want.</small>
            </div>
            
            <button type="submit" class="btn-primary">Place Order</button>
          </form>
        </div>
        
        <div class="order-summary">
          <h2>Order Summary</h2>
          
          <div class="service-preview">
            <div class="service-image">
              <?php if (!empty($service['image'])): ?>
                <img src="../<?= htmlspecialchars($service['image']) ?>" alt="<?= htmlspecialchars($service['title']) ?>">
              <?php else: ?>
                <img src="../images/placeholder-service.jpg" alt="<?= htmlspecialchars($service['title']) ?>">
              <?php endif; ?>
            </div>
            <div class="service-info">
              <h3><?= htmlspecialchars($service['title']) ?></h3>
              <div class="service-meta">
                <div>by <?= htmlspecialchars($service['freelancer_name']) ?></div>
                <div>Category: <?= htmlspecialchars($service['category_name']) ?></div>
              </div>
            </div>
          </div>
          
          <div class="order-details">
            <div class="order-detail">
              <span>Service Price</span>
              <span>$<?= number_format($service['price'], 2) ?></span>
            </div>
            <div class="order-detail">
              <span>Delivery Time</span>
              <span><?= $service['delivery_time'] ?> day<?= $service['delivery_time'] > 1 ? 's' : '' ?></span>
            </div>
          </div>
          
          <div class="order-total">
            Total: $<?= number_format($service['price'], 2) ?>
          </div>
        </div>
      </div>
    </div>
  </main>
  
  <?php include('../templates/footer.php'); ?>
  
  <script src="../js/main.js"></script>
</body>
</html>
