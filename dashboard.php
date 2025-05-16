<?php
session_start();
require_once('database/connection.php');
require_once('utils/auth.php');

// Require login
requireLogin();

// Get user data
$user = getCurrentUser($db);

// Get user's services
$stmt = $db->prepare("
  SELECT s.*, c.name as category_name,
         (SELECT COUNT(*) FROM orders WHERE service_id = s.id) as order_count,
         (SELECT image_path FROM service_images WHERE service_id = s.id AND is_primary = 1 LIMIT 1) as image
  FROM services s
  JOIN categories c ON s.category_id = c.id
  WHERE s.freelancer_id = ?
  ORDER BY s.created_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$services = $stmt->fetchAll();

// Get user's orders as client
$stmt = $db->prepare("
  SELECT o.*, s.title as service_title, u.name as freelancer_name,
         (SELECT image_path FROM service_images WHERE service_id = s.id AND is_primary = 1 LIMIT 1) as image
  FROM orders o
  JOIN services s ON o.service_id = s.id
  JOIN users u ON s.freelancer_id = u.id
  WHERE o.client_id = ?
  ORDER BY o.created_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$clientOrders = $stmt->fetchAll();

// Get user's orders as freelancer
$stmt = $db->prepare("
  SELECT o.*, s.title as service_title, u.name as client_name,
         (SELECT image_path FROM service_images WHERE service_id = s.id AND is_primary = 1 LIMIT 1) as image
  FROM orders o
  JOIN services s ON o.service_id = s.id
  JOIN users u ON o.client_id = u.id
  WHERE s.freelancer_id = ?
  ORDER BY o.created_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$freelancerOrders = $stmt->fetchAll();

// Get unread messages count
$stmt = $db->prepare("SELECT COUNT(*) as count FROM messages WHERE receiver_id = ? AND is_read = 0");
$stmt->execute([$_SESSION['user_id']]);
$unreadMessages = $stmt->fetch()['count'];
?>

&lt;!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard - GigAnt</title>
  <link rel="stylesheet" href="css/styles.css">
  <style>
    .dashboard-container {
      display: grid;
      grid-template-columns: 1fr 3fr;
      gap: 30px;
      margin: 30px 0;
    }
    
    .dashboard-sidebar {
      background-color: white;
      border-radius: 8px;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
      padding: 20px;
    }
    
    .user-info {
      text-align: center;
      margin-bottom: 30px;
    }
    
    .user-avatar {
      width: 100px;
      height: 100px;
      border-radius: 50%;
      margin: 0 auto 15px;
      background-color: #f0f0f0;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 2rem;
      color: #777;
    }
    
    .user-avatar img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      border-radius: 50%;
    }
    
    .dashboard-nav {
      margin-top: 20px;
    }
    
    .dashboard-nav a {
      display: block;
      padding: 10px 15px;
      margin-bottom: 5px;
      border-radius: 4px;
      color: #333;
      transition: background-color 0.3s;
    }
    
    .dashboard-nav a:hover, .dashboard-nav a.active {
      background-color: #f0f7f4;
      color: #1dbf73;
    }
    
    .dashboard-content {
      background-color: white;
      border-radius: 8px;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
      padding: 30px;
    }
    
    .dashboard-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 30px;
    }
    
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 20px;
      margin-bottom: 30px;
    }
    
    .stat-card {
      background-color: #f9f9f9;
      border-radius: 8px;
      padding: 20px;
      text-align: center;
    }
    
    .stat-card h3 {
      font-size: 2rem;
      margin-bottom: 10px;
      color: #1dbf73;
    }
    
    .stat-card p {
      color: #666;
      margin-bottom: 0;
    }
    
    .section-title {
      margin-top: 30px;
      margin-bottom: 20px;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    
    .orders-list, .services-list {
      margin-top: 20px;
    }
    
    .order-item, .service-item {
      display: flex;
      align-items: center;
      padding: 15px;
      border-bottom: 1px solid #eee;
    }
    
    .order-item:last-child, .service-item:last-child {
      border-bottom: none;
    }
    
    .order-image, .service-image {
      width: 80px;
      height: 60px;
      border-radius: 4px;
      overflow: hidden;
      margin-right: 15px;
    }
    
    .order-image img, .service-image img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }
    
    .order-details, .service-details {
      flex: 1;
    }
    
    .order-title, .service-title {
      font-weight: 600;
      margin-bottom: 5px;
    }
    
    .order-meta, .service-meta {
      font-size: 0.9rem;
      color: #777;
    }
    
    .order-actions, .service-actions {
      margin-left: 15px;
    }
    
    .badge-status {
      display: inline-block;
      padding: 3px 8px;
      border-radius: 4px;
      font-size: 0.8rem;
      font-weight: 500;
    }
    
    .badge-pending {
      background-color: #ffeeba;
      color: #856404;
    }
    
    .badge-in-progress {
      background-color: #b8daff;
      color: #004085;
    }
    
    .badge-completed {
      background-color: #c3e6cb;
      color: #155724;
    }
    
    .badge-cancelled {
      background-color: #f5c6cb;
      color: #721c24;
    }
    
    .empty-state {
      text-align: center;
      padding: 30px;
      color: #777;
    }
    
    .empty-state p {
      margin-bottom: 20px;
    }
    
    @media (max-width: 768px) {
      .dashboard-container {
        grid-template-columns: 1fr;
      }
      
      .stats-grid {
        grid-template-columns: 1fr;
      }
    }
  </style>
</head>
<body>
  <?php include('templates/header.php'); ?>
  
  <main>
    <div class="container">
      <div class="dashboard-container">
        <div class="dashboard-sidebar">
          <div class="user-info">
            <div class="user-avatar">
              <?php if (!empty($user['profile_image'])): ?>
                <img src="<?= htmlspecialchars($user['profile_image']) ?>" alt="<?= htmlspecialchars($user['name']) ?>">
              <?php else: ?>
                <?= strtoupper(substr($user['name'], 0, 1)) ?>
              <?php endif; ?>
            </div>
            <h3><?= htmlspecialchars($user['name']) ?></h3>
            <p>@<?= htmlspecialchars($user['username']) ?></p>
          </div>
          
          <div class="dashboard-nav">
            <a href="dashboard.php" class="active">Dashboard</a>
            <a href="profile.php">Edit Profile</a>
            <a href="services/my-services.php">My Services</a>
            <a href="services/create.php">Create New Service</a>
            <a href="orders/my-orders.php">My Orders</a>
            <a href="messages.php">
              Messages
              <?php if ($unreadMessages > 0): ?>
                <span class="badge"><?= $unreadMessages ?></span>
              <?php endif; ?>
            </a>
          </div>
        </div>
        
        <div class="dashboard-content">
          <div class="dashboard-header">
            <h1>Dashboard</h1>
            <a href="services/create.php" class="btn-primary">Create New Service</a>
          </div>
          
          <div class="stats-grid">
            <div class="stat-card">
              <h3><?= count($services) ?></h3>
              <p>Active Services</p>
            </div>
            <div class="stat-card">
              <h3><?= count($freelancerOrders) ?></h3>
              <p>Orders Received</p>
            </div>
            <div class="stat-card">
              <h3><?= count($clientOrders) ?></h3>
              <p>Orders Placed</p>
            </div>
          </div>
          
          <div class="section-title">
            <h2>Recent Orders as Freelancer</h2>
            <a href="orders/my-orders.php?type=freelancer">View All</a>
          </div>
          
          <div class="orders-list">
            <?php if (count($freelancerOrders) > 0): ?>
              <?php foreach (array_slice($freelancerOrders, 0, 3) as $order): ?>
                <div class="order-item">
                  <div class="order-image">
                    <?php if (!empty($order['image'])): ?>
                      <img src="<?= htmlspecialchars($order['image']) ?>" alt="<?= htmlspecialchars($order['service_title']) ?>">
                    <?php else: ?>
                      <img src="images/placeholder-service.jpg" alt="<?= htmlspecialchars($order['service_title']) ?>">
                    <?php endif; ?>
                  </div>
                  <div class="order-details">
                    <div class="order-title"><?= htmlspecialchars($order['service_title']) ?></div>
                    <div class="order-meta">
                      <span>Order #<?= $order['id'] ?></span> • 
                      <span>Client: <?= htmlspecialchars($order['client_name']) ?></span> • 
                      <span>$<?= number_format($order['price'], 2) ?></span>
                    </div>
                  </div>
                  <div class="order-status">
                    <?php
                      $statusClass = '';
                      switch ($order['status']) {
                        case 'pending':
                          $statusClass = 'badge-pending';
                          break;
                        case 'in_progress':
                          $statusClass = 'badge-in-progress';
                          break;
                        case 'completed':
                          $statusClass = 'badge-completed';
                          break;
                        case 'cancelled':
                          $statusClass = 'badge-cancelled';
                          break;
                      }
                    ?>
                    <span class="badge-status <?= $statusClass ?>">
                      <?= ucfirst(str_replace('_', ' ', $order['status'])) ?>
                    </span>
                  </div>
                  <div class="order-actions">
                    <a href="orders/view.php?id=<?= $order['id'] ?>" class="btn-secondary">View</a>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php else: ?>
              <div class="empty-state">
                <p>You haven't received any orders yet.</p>
                <a href="services/create.php" class="btn-primary">Create a Service</a>
              </div>
            <?php endif; ?>
          </div>
          
          <div class="section-title">
            <h2>Recent Orders as Client</h2>
            <a href="orders/my-orders.php?type=client">View All</a>
          </div>
          
          <div class="orders-list">
            <?php if (count($clientOrders) > 0): ?>
              <?php foreach (array_slice($clientOrders, 0, 3) as $order): ?>
                <div class="order-item">
                  <div class="order-image">
                    <?php if (!empty($order['image'])): ?>
                      <img src="<?= htmlspecialchars($order['image']) ?>" alt="<?= htmlspecialchars($order['service_title']) ?>">
                    <?php else: ?>
                      <img src="images/placeholder-service.jpg" alt="<?= htmlspecialchars($order['service_title']) ?>">
                    <?php endif; ?>
                  </div>
                  <div class="order-details">
                    <div class="order-title"><?= htmlspecialchars($order['service_title']) ?></div>
                    <div class="order-meta">
                      <span>Order #<?= $order['id'] ?></span> • 
                      <span>Freelancer: <?= htmlspecialchars($order['freelancer_name']) ?></span> • 
                      <span>$<?= number_format($order['price'], 2) ?></span>
                    </div>
                  </div>
                  <div class="order-status">
                    <?php
                      $statusClass = '';
                      switch ($order['status']) {
                        case 'pending':
                          $statusClass = 'badge-pending';
                          break;
                        case 'in_progress':
                          $statusClass = 'badge-in-progress';
                          break;
                        case 'completed':
                          $statusClass = 'badge-completed';
                          break;
                        case 'cancelled':
                          $statusClass = 'badge-cancelled';
                          break;
                      }
                    ?>
                    <span class="badge-status <?= $statusClass ?>">
                      <?= ucfirst(str_replace('_', ' ', $order['status'])) ?>
                    </span>
                  </div>
                  <div class="order-actions">
                    <a href="orders/view.php?id=<?= $order['id'] ?>" class="btn-secondary">View</a>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php else: ?>
              <div class="empty-state">
                <p>You haven't placed any orders yet.</p>
                <a href="search.php" class="btn-primary">Browse Services</a>
              </div>
            <?php endif; ?>
          </div>
          
          <div class="section-title">
            <h2>My Services</h2>
            <a href="services/my-services.php">View All</a>
          </div>
          
          <div class="services-list">
            <?php if (count($services) > 0): ?>
              <?php foreach (array_slice($services, 0, 3) as $service): ?>
                <div class="service-item">
                  <div class="service-image">
                    <?php if (!empty($service['image'])): ?>
                      <img src="<?= htmlspecialchars($service['image']) ?>" alt="<?= htmlspecialchars($service['title']) ?>">
                    <?php else: ?>
                      <img src="images/placeholder-service.jpg" alt="<?= htmlspecialchars($service['title']) ?>">
                    <?php endif; ?>
                  </div>
                  <div class="service-details">
                    <div class="service-title"><?= htmlspecialchars($service['title']) ?></div>
                    <div class="service-meta">
                      <span>Category: <?= htmlspecialchars($service['category_name']) ?></span> • 
                      <span>$<?= number_format($service['price'], 2) ?></span> • 
                      <span><?= $service['order_count'] ?> orders</span>
                    </div>
                  </div>
                  <div class="service-actions">
                    <a href="services/edit.php?id=<?= $service['id'] ?>" class="btn-secondary">Edit</a>
                    <a href="services/view.php?id=<?= $service['id'] ?>" class="btn-primary">View</a>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php else: ?>
              <div class="empty-state">
                <p>You haven't created any services yet.</p>
                <a href="services/create.php" class="btn-primary">Create a Service</a>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </main>
  
  <?php include('templates/footer.php'); ?>
  
  <script src="js/main.js"></script>
</body>
</html>
