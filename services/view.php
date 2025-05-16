<?php
session_start();
require_once('../database/connection.php');
require_once('../utils/auth.php');
require_once('../utils/csrf.php');

// Get service ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
  $_SESSION['error'] = "Invalid service ID";
  header("Location: ../index.php");
  exit();
}

$serviceId = $_GET['id'];

// Get service details
$stmt = $db->prepare("
  SELECT s.*, u.name as freelancer_name, u.username as freelancer_username, c.name as category_name,
         (SELECT COUNT(*) FROM orders WHERE service_id = s.id) as order_count,
         (SELECT AVG(r.rating) FROM reviews r JOIN orders o ON r.order_id = o.id WHERE o.service_id = s.id) as avg_rating,
         (SELECT COUNT(*) FROM reviews r JOIN orders o ON r.order_id = o.id WHERE o.service_id = s.id) as review_count
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

// Get service images
$stmt = $db->prepare("SELECT * FROM service_images WHERE service_id = ? ORDER BY is_primary DESC");
$stmt->execute([$serviceId]);
$images = $stmt->fetchAll();

// Get reviews
$stmt = $db->prepare("
  SELECT r.*, o.client_id, u.name as client_name, u.username as client_username
  FROM reviews r
  JOIN orders o ON r.order_id = o.id
  JOIN users u ON o.client_id = u.id
  WHERE o.service_id = ?
  ORDER BY r.created_at DESC
");
$stmt->execute([$serviceId]);
$reviews = $stmt->fetchAll();

// Check if user has already ordered this service
$hasOrdered = false;
if (isLoggedIn()) {
  $stmt = $db->prepare("SELECT COUNT(*) as count FROM orders WHERE service_id = ? AND client_id = ?");
  $stmt->execute([$serviceId, $_SESSION['user_id']]);
  $hasOrdered = ($stmt->fetch()['count'] > 0);
}

// Handle contact form submission
$contactError = null;
$contactSuccess = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'contact') {
  // Validate CSRF token
  if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
    $contactError = "Invalid form submission";
  } else if (!isLoggedIn()) {
    $contactError = "You must be logged in to contact the freelancer";
  } else {
    $message = trim($_POST['message']);
    
    if (empty($message)) {
      $contactError = "Message cannot be empty";
    } else {
      // Insert message
      $stmt = $db->prepare("
        INSERT INTO messages (sender_id, receiver_id, content)
        VALUES (?, ?, ?)
      ");
      
      if ($stmt->execute([$_SESSION['user_id'], $service['freelancer_id'], $message])) {
        $contactSuccess = "Message sent successfully!";
      } else {
        $contactError = "Failed to send message. Please try again.";
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
  <title><?= htmlspecialchars($service['title']) ?> - GigAnt</title>
  <link rel="stylesheet" href="../css/styles.css">
  <style>
    .service-container {
      display: grid;
      grid-template-columns: 2fr 1fr;
      gap: 30px;
      margin: 30px 0;
    }
    
    .service-details {
      background-color: white;
      border-radius: 8px;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
      overflow: hidden;
    }
    
    .service-images {
      position: relative;
    }
    
    .main-image {
      width: 100%;
      height: 400px;
      overflow: hidden;
    }
    
    .main-image img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }
    
    .thumbnail-images {
      display: flex;
      gap: 10px;
      padding: 15px;
      background-color: #f9f9f9;
    }
    
    .thumbnail {
      width: 80px;
      height: 60px;
      border-radius: 4px;
      overflow: hidden;
      cursor: pointer;
      opacity: 0.7;
      transition: opacity 0.3s;
    }
    
    .thumbnail.active {
      opacity: 1;
      box-shadow: 0 0 0 2px #1dbf73;
    }
    
    .thumbnail:hover {
      opacity: 1;
    }
    
    .thumbnail img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }
    
    .service-info {
      padding: 20px;
    }
    
    .service-title {
      font-size: 1.8rem;
      margin-bottom: 10px;
    }
    
    .service-meta {
      display: flex;
      align-items: center;
      margin-bottom: 20px;
      color: #777;
    }
    
    .service-meta > * {
      margin-right: 15px;
    }
    
    .service-rating {
      display: flex;
      align-items: center;
    }
    
    .stars {
      color: #ffb33e;
      margin-right: 5px;
    }
    
    .service-description {
      margin-bottom: 30px;
      line-height: 1.7;
    }
    
    .service-description h2 {
      font-size: 1.3rem;
      margin: 20px 0 10px;
    }
    
    .freelancer-info {
      display: flex;
      align-items: center;
      margin-top: 30px;
      padding-top: 20px;
      border-top: 1px solid #eee;
    }
    
    .freelancer-avatar {
      width: 60px;
      height: 60px;
      border-radius: 50%;
      margin-right: 15px;
      background-color: #f0f0f0;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.5rem;
      color: #777;
    }
    
    .freelancer-details {
      flex: 1;
    }
    
    .freelancer-name {
      font-weight: 600;
      margin-bottom: 5px;
    }
    
    .contact-form {
      margin-top: 30px;
      padding-top: 20px;
      border-top: 1px solid #eee;
    }
    
    .order-card {
      background-color: white;
      border-radius: 8px;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
      padding: 20px;
      position: sticky;
      top: 90px;
    }
    
    .order-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 15px;
    }
    
    .order-price {
      font-size: 1.8rem;
      font-weight: 600;
      color: #1dbf73;
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
    
    .reviews-section {
      margin-top: 50px;
    }
    
    .reviews-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 20px;
    }
    
    .review-item {
      background-color: white;
      border-radius: 8px;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
      padding: 20px;
      margin-bottom: 20px;
    }
    
    .review-header {
      display: flex;
      justify-content: space-between;
      margin-bottom: 15px;
    }
    
    .reviewer-info {
      display: flex;
      align-items: center;
    }
    
    .reviewer-avatar {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      margin-right: 10px;
      background-color: #f0f0f0;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1rem;
      color: #777;
    }
    
    .reviewer-name {
      font-weight: 600;
    }
    
    .review-date {
      color: #777;
      font-size: 0.9rem;
    }
    
    .review-rating {
      margin-bottom: 10px;
    }
    
    .review-content {
      line-height: 1.6;
    }
    
    .no-reviews {
      text-align: center;
      padding: 30px;
      background-color: white;
      border-radius: 8px;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    }
    
    @media (max-width: 768px) {
      .service-container {
        grid-template-columns: 1fr;
      }
      
      .main-image {
        height: 300px;
      }
      
      .order-card {
        position: static;
        margin-bottom: 30px;
      }
    }
  </style>
</head>
<body>
  <?php include('../templates/header.php'); ?>
  
  <main>
    <div class="container">
      <div class="service-container">
        <div class="service-details">
          <div class="service-images">
            <div class="main-image" id="mainImage">
              <?php if (!empty($images)): ?>
                <img src="../<?= htmlspecialchars($images[0]['image_path']) ?>" alt="<?= htmlspecialchars($service['title']) ?>">
              <?php else: ?>
                <img src="../images/placeholder-service.jpg" alt="<?= htmlspecialchars($service['title']) ?>">
              <?php endif; ?>
            </div>
            
            <?php if (count($images) > 1): ?>
              <div class="thumbnail-images">
                <?php foreach ($images as $index => $image): ?>
                  <div class="thumbnail <?= $index === 0 ? 'active' : '' ?>" data-image="../<?= htmlspecialchars($image['image_path']) ?>">
                    <img src="../<?= htmlspecialchars($image['image_path']) ?>" alt="Thumbnail">
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
          
          <div class="service-info">
            <h1 class="service-title"><?= htmlspecialchars($service['title']) ?></h1>
            
            <div class="service-meta">
              <div class="service-category">
                <span>Category: <?= htmlspecialchars($service['category_name']) ?></span>
              </div>
              
              <?php if (!is_null($service['avg_rating'])): ?>
                <div class="service-rating">
                  <span class="stars">
                    <?php
                      $rating = round($service['avg_rating'] * 2) / 2;
                      for ($i = 1; $i <= 5; $i++) {
                        if ($i <= $rating) {
                          echo '★';
                        } elseif ($i - 0.5 == $rating) {
                          echo '⯨';
                        } else {
                          echo '☆';
                        }
                      }
                    ?>
                  </span>
                  <span class="rating-value"><?= number_format($service['avg_rating'], 1) ?></span>
                  <span>(<?= $service['review_count'] ?> reviews)</span>
                </div>
              <?php endif; ?>
              
              <div class="service-orders">
                <?= $service['order_count'] ?> orders completed
              </div>
            </div>
            
            <div class="service-description">
              <h2>About This Service</h2>
              <p><?= nl2br(htmlspecialchars($service['description'])) ?></p>
            </div>
            
            <div class="freelancer-info">
              <div class="freelancer-avatar">
                <?= strtoupper(substr($service['freelancer_name'], 0, 1)) ?>
              </div>
              <div class="freelancer-details">
                <div class="freelancer-name"><?= htmlspecialchars($service['freelancer_name']) ?></div>
                <div class="freelancer-username">@<?= htmlspecialchars($service['freelancer_username']) ?></div>
              </div>
            </div>
            
            <?php if (isLoggedIn() && $_SESSION['user_id'] != $service['freelancer_id']): ?>
              <div class="contact-form">
                <h2>Contact the Freelancer</h2>
                
                <?php if ($contactError): ?>
                  <div class="alert alert-error"><?= $contactError ?></div>
                <?php endif; ?>
                
                <?php if ($contactSuccess): ?>
                  <div class="alert alert-success"><?= $contactSuccess ?></div>
                <?php endif; ?>
                
                <form action="view.php?id=<?= $serviceId ?>" method="POST">
                  <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                  <input type="hidden" name="action" value="contact">
                  
                  <div class="form-group">
                    <label for="message">Message</label>
                    <textarea id="message" name="message" rows="4" required></textarea>
                  </div>
                  
                  <button type="submit" class="btn-primary">Send Message</button>
                </form>
              </div>
            <?php endif; ?>
          </div>
        </div>
        
        <div class="order-sidebar">
          <div class="order-card">
            <div class="order-header">
              <h2>Service Package</h2>
              <div class="order-price">$<?= number_format($service['price'], 2) ?></div>
            </div>
            
            <div class="order-details">
              <div class="order-detail">
                <span>Delivery Time</span>
                <span><?= $service['delivery_time'] ?> day<?= $service['delivery_time'] > 1 ? 's' : '' ?></span>
              </div>
              <div class="order-detail">
                <span>Revisions</span>
                <span>Unlimited</span>
              </div>
            </div>
            
            <?php if (isLoggedIn()): ?>
              <?php if ($_SESSION['user_id'] != $service['freelancer_id']): ?>
                <a href="../orders/create.php?service_id=<?= $serviceId ?>" class="btn-primary" style="display: block; text-align: center;">
                  Continue ($<?= number_format($service['price'], 2) ?>)
                </a>
              <?php else: ?>
                <a href="edit.php?id=<?= $serviceId ?>" class="btn-secondary" style="display: block; text-align: center; margin-bottom: 10px;">
                  Edit Service
                </a>
                <a href="my-services.php" class="btn-primary" style="display: block; text-align: center;">
                  Manage Services
                </a>
              <?php endif; ?>
            <?php else: ?>
              <a href="../login.php?redirect=services/view.php?id=<?= $serviceId ?>" class="btn-primary" style="display: block; text-align: center;">
                Login to Order
              </a>
            <?php endif; ?>
          </div>
        </div>
      </div>
      
      <div class="reviews-section">
        <div class="reviews-header">
          <h2>Reviews (<?= count($reviews) ?>)</h2>
        </div>
        
        <?php if (count($reviews) > 0): ?>
          <div class="reviews-list">
            <?php foreach ($reviews as $review): ?>
              <div class="review-item">
                <div class="review-header">
                  <div class="reviewer-info">
                    <div class="reviewer-avatar">
                      <?= strtoupper(substr($review['client_name'], 0, 1)) ?>
                    </div>
                    <div>
                      <div class="reviewer-name"><?= htmlspecialchars($review['client_name']) ?></div>
                      <div class="reviewer-username">@<?= htmlspecialchars($review['client_username']) ?></div>
                    </div>
                  </div>
                  <div class="review-date">
                    <?= date('M d, Y', strtotime($review['created_at'])) ?>
                  </div>
                </div>
                
                <div class="review-rating">
                  <span class="stars">
                    <?php
                      for ($i = 1; $i <= 5; $i++) {
                        echo ($i <= $review['rating']) ? '★' : '☆';
                      }
                    ?>
                  </span>
                  <span class="rating-value"><?= $review['rating'] ?>.0</span>
                </div>
                
                <div class="review-content">
                  <?= nl2br(htmlspecialchars($review['comment'])) ?>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div class="no-reviews">
            <h3>No reviews yet</h3>
            <p>Be the first to review this service!</p>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </main>
  
  <?php include('../templates/footer.php'); ?>
  
  <script src="../js/main.js"></script>
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      // Image gallery
      const mainImage = document.getElementById('mainImage');
      const thumbnails = document.querySelectorAll('.thumbnail');
      
      thumbnails.forEach(thumbnail => {
        thumbnail.addEventListener('click', function() {
          // Update main image
          const imagePath = this.getAttribute('data-image');
          mainImage.querySelector('img').src = imagePath;
          
          // Update active thumbnail
          thumbnails.forEach(thumb => thumb.classList.remove('active'));
          this.classList.add('active');
        });
      });
    });
  </script>
</body>
</html>
