<?php
session_start();
require_once('../database/connection.php');
require_once('../utils/auth.php');
require_once('../utils/csrf.php');

// Require login
requireLogin();

// Get order ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
  $_SESSION['error'] = "Invalid order ID";
  header("Location: my-orders.php");
  exit();
}

$orderId = $_GET['id'];

// Get order details
$stmt = $db->prepare("
  SELECT o.*, s.title as service_title, s.description as service_description, s.delivery_time,
         s.freelancer_id, c.name as category_name,
         f.name as freelancer_name, f.username as freelancer_username,
         cl.name as client_name, cl.username as client_username,
         (SELECT image_path FROM service_images WHERE service_id = s.id AND is_primary = 1 LIMIT 1) as image
  FROM orders o
  JOIN services s ON o.service_id = s.id
  JOIN categories c ON s.category_id = c.id
  JOIN users f ON s.freelancer_id = f.id
  JOIN users cl ON o.client_id = cl.id
  WHERE o.id = ?
");
$stmt->execute([$orderId]);
$order = $stmt->fetch();

if (!$order) {
  $_SESSION['error'] = "Order not found";
  header("Location: my-orders.php");
  exit();
}

// Check if user is authorized to view this order
if ($order['client_id'] != $_SESSION['user_id'] && $order['freelancer_id'] != $_SESSION['user_id'] && !isAdmin()) {
  $_SESSION['error'] = "You don't have permission to view this order";
  header("Location: my-orders.php");
  exit();
}

// Get messages for this order
$stmt = $db->prepare("
  SELECT m.*, u.name as sender_name, u.username as sender_username
  FROM messages m
  JOIN users u ON m.sender_id = u.id
  WHERE m.order_id = ?
  ORDER BY m.created_at ASC
");
$stmt->execute([$orderId]);
$messages = $stmt->fetchAll();

// Mark messages as read
if ($messages) {
  $stmt = $db->prepare("
    UPDATE messages
    SET is_read = 1
    WHERE order_id = ? AND receiver_id = ?
  ");
  $stmt->execute([$orderId, $_SESSION['user_id']]);
}

// Get review if exists
$stmt = $db->prepare("
  SELECT r.*, u.name as reviewer_name, u.username as reviewer_username
  FROM reviews r
  JOIN orders o ON r.order_id = o.id
  JOIN users u ON o.client_id = u.id
  WHERE r.order_id = ?
");
$stmt->execute([$orderId]);
$review = $stmt->fetch();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Validate CSRF token
  if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
    $_SESSION['error'] = "Invalid form submission";
    header("Location: view.php?id=$orderId");
    exit();
  }
  
  // Message form
  if (isset($_POST['action']) && $_POST['action'] === 'message') {
    $content = trim($_POST['content']);
    
    if (empty($content)) {
      $_SESSION['error'] = "Message cannot be empty";
    } else {
      $receiverId = ($_SESSION['user_id'] == $order['client_id']) ? $order['freelancer_id'] : $order['client_id'];
      
      $stmt = $db->prepare("
        INSERT INTO messages (sender_id, receiver_id, order_id, content)
        VALUES (?, ?, ?, ?)
      ");
      
      if ($stmt->execute([$_SESSION['user_id'], $receiverId, $orderId, $content])) {
        // Refresh to show new message
        header("Location: view.php?id=$orderId");
        exit();
      } else {
        $_SESSION['error'] = "Failed to send message";
      }
    }
  }
  
  // Update order status
  if (isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $newStatus = $_POST['status'];
    $validStatuses = ['pending', 'in_progress', 'completed', 'cancelled'];
    
    if (!in_array($newStatus, $validStatuses)) {
      $_SESSION['error'] = "Invalid status";
    } else {
      // Check permissions
      $canUpdate = false;
      
      if ($newStatus === 'in_progress' && $order['status'] === 'pending' && $_SESSION['user_id'] == $order['freelancer_id']) {
        $canUpdate = true;
      } else if ($newStatus === 'completed' && $order['status'] === 'in_progress' && $_SESSION['user_id'] == $order['freelancer_id']) {
        $canUpdate = true;
      } else if ($newStatus === 'cancelled' && $order['status'] !== 'completed' && 
                ($_SESSION['user_id'] == $order['client_id'] || $_SESSION['user_id'] == $order['freelancer_id'])) {
        $canUpdate = true;
      } else if (isAdmin()) {
        $canUpdate = true;
      }
      
      if ($canUpdate) {
        $stmt = $db->prepare("
          UPDATE orders
          SET status = ?, completed_at = ?
          WHERE id = ?
        ");
        
        $completedAt = ($newStatus === 'completed') ? date('Y-m-d H:i:s') : null;
        
        if ($stmt->execute([$newStatus, $completedAt, $orderId])) {
          // Add system message
          $statusMessage = "Order status updated to " . ucfirst(str_replace('_', ' ', $newStatus));
          
          $stmt = $db->prepare("
            INSERT INTO messages (sender_id, receiver_id, order_id, content)
            VALUES (?, ?, ?, ?)
          ");
          
          $stmt->execute([
            $_SESSION['user_id'],
            ($_SESSION['user_id'] == $order['client_id']) ? $order['freelancer_id'] : $order['client_id'],
            $orderId,
            $statusMessage
          ]);
          
          $_SESSION['success'] = "Order status updated successfully";
          header("Location: view.php?id=$orderId");
          exit();
        } else {
          $_SESSION['error'] = "Failed to update order status";
        }
      } else {
        $_SESSION['error'] = "You don't have permission to update the order status";
      }
    }
  }
  
  // Add review
  if (isset($_POST['action']) && $_POST['action'] === 'review') {
    $rating = $_POST['rating'];
    $comment = trim($_POST['comment']);
    
    if (!is_numeric($rating) || $rating < 1 || $rating > 5) {
      $_SESSION['error'] = "Invalid rating";
    } else if ($_SESSION['user_id'] != $order['client_id']) {
      $_SESSION['error'] = "Only the client can leave a review";
    } else if ($order['status'] !== 'completed') {
      $_SESSION['error'] = "You can only review completed orders";
    } else if ($review) {
      $_SESSION['error'] = "You have already reviewed this order";
    } else {
      $stmt = $db->prepare("
        INSERT INTO reviews (order_id, rating, comment)
        VALUES (?, ?, ?)
      ");
      
      if ($stmt->execute([$orderId, $rating, $comment])) {
        $_SESSION['success'] = "Review submitted successfully";
        header("Location: view.php?id=$orderId");
        exit();
      } else {
        $_SESSION['error'] = "Failed to submit review";
      }
    }
  }
}

// Update order status after fetching
$stmt = $db->prepare("SELECT * FROM orders WHERE id = ?");
$stmt->execute([$orderId]);
$order = $stmt->fetch();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Order #<?= $orderId ?> - GigAnt</title>
  <link rel="stylesheet" href="../css/styles.css">
  <style>
    .order-container {
      display: grid;
      grid-template-columns: 2fr 1fr;
      gap: 30px;
      margin: 30px 0;
    }
    
    .order-details {
      background-color: white;
      border-radius: 8px;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
      padding: 30px;
    }
    
    .order-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 20px;
      padding-bottom: 20px;
      border-bottom: 1px solid #eee;
    }
    
    .order-id {
      font-size: 1.5rem;
      font-weight: 600;
    }
    
    .order-status {
      padding: 5px 10px;
      border-radius: 4px;
      font-weight: 500;
    }
    
    .status-pending {
      background-color: #ffeeba;
      color: #856404;
    }
    
    .status-in-progress {
      background-color: #b8daff;
      color: #004085;
    }
    
    .status-completed {
      background-color: #c3e6cb;
      color: #155724;
    }
    
    .status-cancelled {
      background-color: #f5c6cb;
      color: #721c24;
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
    
    .requirements {
      margin-bottom: 30px;
    }
    
    .requirements h3 {
      margin-bottom: 10px;
    }
    
    .requirements p {
      background-color: #f9f9f9;
      padding: 15px;
      border-radius: 4px;
      white-space: pre-line;
    }
    
    .messages-section {
      margin-top: 30px;
    }
    
    .messages-list {
      margin-bottom: 20px;
      max-height: 400px;
      overflow-y: auto;
      padding: 15px;
      background-color: #f9f9f9;
      border-radius: 4px;
    }
    
    .message {
      margin-bottom: 15px;
      display: flex;
    }
    
    .message:last-child {
      margin-bottom: 0;
    }
    
    .message-sender {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      background-color: #ddd;
      margin-right: 10px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 600;
      color: #555;
      flex-shrink: 0;
    }
    
    .message-content {
      background-color: white;
      padding: 10px 15px;
      border-radius: 4px;
      box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
      max-width: 80%;
    }
    
    .message.outgoing {
      flex-direction: row-reverse;
    }
    
    .message.outgoing .message-sender {
      margin-right: 0;
      margin-left: 10px;
      background-color: #1dbf73;
      color: white;
    }
    
    .message.outgoing .message-content {
      background-color: #e6f7ef;
    }
    
    .message-header {
      display: flex;
      justify-content: space-between;
      margin-bottom: 5px;
      font-size: 0.9rem;
    }
    
    .message-name {
      font-weight: 600;
    }
    
    .message-time {
      color: #777;
    }
    
    .message-text {
      word-break: break-word;
    }
    
    .message-form textarea {
      resize: vertical;
    }
    
    .order-sidebar {
      position: sticky;
      top: 90px;
    }
    
    .order-actions, .order-summary, .review-section {
      background-color: white;
      border-radius: 8px;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
      padding: 20px;
      margin-bottom: 20px;
    }
    
    .order-actions h3, .order-summary h3, .review-section h3 {
      margin-bottom: 15px;
      padding-bottom: 10px;
      border-bottom: 1px solid #eee;
    }
    
    .action-buttons {
      display: flex;
      flex-direction: column;
      gap: 10px;
    }
    
    .summary-item {
      display: flex;
      justify-content: space-between;
      margin-bottom: 10px;
    }
    
    .summary-item:last-child {
      margin-bottom: 0;
      padding-top: 10px;
      border-top: 1px solid #eee;
      font-weight: 600;
    }
    
    .star-rating {
      display: flex;
      flex-direction: row-reverse;
      justify-content: flex-end;
      margin-bottom: 15px;
    }
    
    .star-rating input {
      display: none;
    }
    
    .star-rating label {
      cursor: pointer;
      width: 30px;
      height: 30px;
      background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>');
      background-repeat: no-repeat;
      background-position: center;
      background-size: 24px;
      color: #ddd;
    }
    
    .star-rating input:checked ~ label,
    .star-rating label:hover,
    .star-rating label:hover ~ label {
      background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="%23ffb33e" stroke="%23ffb33e" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>');
    }
    
    .review-display {
      margin-top: 15px;
    }
    
    .review-rating {
      color: #ffb33e;
      font-size: 1.2rem;
      margin-bottom: 10px;
    }
    
    .review-comment {
      background-color: #f9f9f9;
      padding: 15px;
      border-radius: 4px;
      white-space: pre-line;
    }
    
    @media (max-width: 768px) {
      .order-container {
        grid-template-columns: 1fr;
      }
      
      .order-sidebar {
        position: static;
      }
      
      .message-content {
        max-width: 70%;
      }
    }
  </style>
</head>
<body>
  <?php include('../templates/header.php'); ?>
  
  <main>
    <div class="container">
      <div class="order-container">
        <div class="order-details">
          <div class="order-header">
            <div class="order-id">Order #<?= $orderId ?></div>
            <?php
              $statusClass = '';
              switch ($order['status']) {
                case 'pending':
                  $statusClass = 'status-pending';
                  break;
                case 'in_progress':
                  $statusClass = 'status-in-progress';
                  break;
                case 'completed':
                  $statusClass = 'status-completed';
                  break;
                case 'cancelled':
                  $statusClass = 'status-cancelled';
                  break;
              }
            ?>
            <div class="order-status <?= $statusClass ?>">
              <?= ucfirst(str_replace('_', ' ', $order['status'])) ?>
            </div>
          </div>
          
          <div class="service-preview">
            <div class="service-image">
              <?php if (!empty($order['image'])): ?>
                <img src="../<?= htmlspecialchars($order['image']) ?>" alt="<?= htmlspecialchars($order['service_title']) ?>">
              <?php else: ?>
                <img src="../images/placeholder-service.jpg" alt="<?= htmlspecialchars($order['service_title']) ?>">
              <?php endif; ?>
            </div>
            <div class="service-info">
              <h3><?= htmlspecialchars($order['service_title']) ?></h3>
              <div class="service-meta">
                <div>
                  <?php if ($_SESSION['user_id'] == $order['client_id']): ?>
                    Freelancer: <?= htmlspecialchars($order['freelancer_name']) ?>
                  <?php else: ?>
                    Client: <?= htmlspecialchars($order['client_name']) ?>
                  <?php endif; ?>
                </div>
                <div>Category: <?= htmlspecialchars($order['category_name']) ?></div>
                <div>Delivery Time: <?= $order['delivery_time'] ?> day<?= $order['delivery_time'] > 1 ? 's' : '' ?></div>
              </div>
            </div>
          </div>
          
          <div class="requirements">
            <h3>Requirements</h3>
            <p><?= nl2br(htmlspecialchars($order['requirements'])) ?></p>
          </div>
          
          <div class="messages-section">
            <h3>Messages</h3>
            
            <div class="messages-list" id="messagesList">
              <?php if (count($messages) > 0): ?>
                <?php foreach ($messages as $message): ?>
                  <div class="message <?= $message['sender_id'] == $_SESSION['user_id'] ? 'outgoing' : 'incoming' ?>">
                    <div class="message-sender">
                      <?= strtoupper(substr($message['sender_name'], 0, 1)) ?>
                    </div>
                    <div class="message-content">
                      <div class="message-header">
                        <span class="message-name"><?= htmlspecialchars($message['sender_name']) ?></span>
                        <span class="message-time"><?= date('M d, Y H:i', strtotime($message['created_at'])) ?></span>
                      </div>
                      <div class="message-text"><?= nl2br(htmlspecialchars($message['content'])) ?></div>
                    </div>
                  </div>
                <?php endforeach; ?>
              <?php else: ?>
                <p class="text-center">No messages yet</p>
              <?php endif; ?>
            </div>
            
            <?php if ($order['status'] !== 'cancelled'): ?>
              <div class="message-form">
                <form action="view.php?id=<?= $orderId ?>" method="POST">
                  <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                  <input type="hidden" name="action" value="message">
                  
                  <div class="form-group">
                    <label for="content">Send a message</label>
                    <textarea id="content" name="content" rows="3" required></textarea>
                  </div>
                  
                  <button type="submit" class="btn-primary">Send</button>
                </form>
              </div>
            <?php endif; ?>
          </div>
        </div>
        
        <div class="order-sidebar">
          <div class="order-actions">
            <h3>Order Actions</h3>
            
            <div class="action-buttons">
              <?php if ($order['status'] === 'pending' && $_SESSION['user_id'] == $order['freelancer_id']): ?>
                <form action="view.php?id=<?= $orderId ?>" method="POST">
                  <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                  <input type="hidden" name="action" value="update_status">
                  <input type="hidden" name="status" value="in_progress">
                  <button type="submit" class="btn-primary">Accept Order</button>
                </form>
              <?php endif; ?>
              
              <?php if ($order['status'] === 'in_progress' && $_SESSION['user_id'] == $order['freelancer_id']): ?>
                <form action="view.php?id=<?= $orderId ?>" method="POST">
                  <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                  <input type="hidden" name="action" value="update_status">
                  <input type="hidden" name="status" value="completed">
                  <button type="submit" class="btn-primary">Mark as Completed</button>
                </form>
              <?php endif; ?>
              
              <?php if ($order['status'] !== 'completed' && $order['status'] !== 'cancelled'): ?>
                <form action="view.php?id=<?= $orderId ?>" method="POST" onsubmit="return confirm('Are you sure you want to cancel this order?');">
                  <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                  <input type="hidden" name="action" value="update_status">
                  <input type="hidden" name="status" value="cancelled">
                  <button type="submit" class="btn-danger">Cancel Order</button>
                </form>
              <?php endif; ?>
              
              <a href="../services/view.php?id=<?= $order['service_id'] ?>" class="btn-secondary">View Service</a>
            </div>
          </div>
          
          <div class="order-summary">
            <h3>Order Summary</h3>
            
            <div class="summary-item">
              <span>Order Date</span>
              <span><?= date('M d, Y', strtotime($order['created_at'])) ?></span>
            </div>
            
            <?php if ($order['status'] === 'completed' && !empty($order['completed_at'])): ?>
              <div class="summary-item">
                <span>Completion Date</span>
                <span><?= date('M d, Y', strtotime($order['completed_at'])) ?></span>
              </div>
            <?php endif; ?>
            
            <div class="summary-item">
              <span>Service Price</span>
              <span>$<?= number_format($order['price'], 2) ?></span>
            </div>
            
            <div class="summary-item">
              <span>Total</span>
              <span>$<?= number_format($order['price'], 2) ?></span>
            </div>
          </div>
          
          <?php if ($order['status'] === 'completed' && $_SESSION['user_id'] == $order['client_id']): ?>
            <div class="review-section">
              <h3>Review</h3>
              
              <?php if ($review): ?>
                <div class="review-display">
                  <div class="review-rating">
                    <?php
                      for ($i = 1; $i <= 5; $i++) {
                        echo ($i <= $review['rating']) ? '★' : '☆';
                      }
                    ?>
                    <span><?= $review['rating'] ?>.0</span>
                  </div>
                  
                  <?php if (!empty($review['comment'])): ?>
                    <div class="review-comment">
                      <?= nl2br(htmlspecialchars($review['comment'])) ?>
                    </div>
                  <?php endif; ?>
                </div>
              <?php else: ?>
                <form action="view.php?id=<?= $orderId ?>" method="POST">
                  <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                  <input type="hidden" name="action" value="review">
                  
                  <div class="form-group">
                    <label>Rating</label>
                    <div class="star-rating">
                      <input type="radio" id="star5" name="rating" value="5" required />
                      <label for="star5" title="5 stars"></label>
                      <input type="radio" id="star4" name="rating" value="4" />
                      <label for="star4" title="4 stars"></label>
                      <input type="radio" id="star3" name="rating" value="3" />
                      <label for="star3" title="3 stars"></label>
                      <input type="radio" id="star2" name="rating" value="2" />
                      <label for="star2" title="2 stars"></label>
                      <input type="radio" id="star1" name="rating" value="1" />
                      <label for="star1" title="1 star"></label>
                    </div>
                  </div>
                  
                  <div class="form-group">
                    <label for="comment">Comment (optional)</label>
                    <textarea id="comment" name="comment" rows="4"></textarea>
                  </div>
                  
                  <button type="submit" class="btn-primary">Submit Review</button>
                </form>
              <?php endif; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </main>
  
  <?php include('../templates/footer.php'); ?>
  
  <script src="../js/main.js"></script>
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      // Scroll to bottom of messages list
      const messagesList = document.getElementById('messagesList');
      messagesList.scrollTop = messagesList.scrollHeight;
    });
  </script>
</body>
</html>
