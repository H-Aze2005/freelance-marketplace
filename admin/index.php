<?php
session_start();
require_once('../database/connection.php');
require_once('../utils/auth.php');

// Require admin
requireAdmin();

// Get stats
$stmt = $db->query("SELECT COUNT(*) as count FROM users");
$userCount = $stmt->fetch()['count'];

$stmt = $db->query("SELECT COUNT(*) as count FROM services");
$serviceCount = $stmt->fetch()['count'];

$stmt = $db->query("SELECT COUNT(*) as count FROM orders");
$orderCount = $stmt->fetch()['count'];

$stmt = $db->query("SELECT COUNT(*) as count FROM categories");
$categoryCount = $stmt->fetch()['count'];

// Get recent users
$stmt = $db->query("SELECT * FROM users ORDER BY created_at DESC LIMIT 5");
$recentUsers = $stmt->fetchAll();

// Get recent orders
$stmt = $db->query("
  SELECT o.*, s.title as service_title, u.name as client_name, f.name as freelancer_name
  FROM orders o
  JOIN services s ON o.service_id = s.id
  JOIN users u ON o.client_id = u.id
  JOIN users f ON s.freelancer_id = f.id
  ORDER BY o.created_at DESC
  LIMIT 5
");
$recentOrders = $stmt->fetchAll();
?>

&lt;!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Dashboard - GigAnt</title>
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
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
      gap: 20px;
      margin-bottom: 30px;
    }
    
    .stat-card {
      background-color: white;
      border-radius: 8px;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
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
    
    .recent-section {
      background-color: white;
      border-radius: 8px;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
      padding: 20px;
      margin-bottom: 30px;
    }
    
    .recent-section h2 {
      margin-bottom: 20px;
      padding-bottom: 10px;
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
    
    .badge-admin {
      background-color: #d4edda;
      color: #155724;
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
            <a href="index.php" class="active">Dashboard</a>
            <a href="users.php">Manage Users</a>
            <a href="categories.php">Manage Categories</a>
            <a href="services.php">Manage Services</a>
            <a href="orders.php">Manage Orders</a>
          </div>
        </div>
        
        <div>
          <h1>Admin Dashboard</h1>
          
          <div class="admin-content">
            <div class="stat-card">
              <h3><?= $userCount ?></h3>
              <p>Total Users</p>
            </div>
            
            <div class="stat-card">
              <h3><?= $serviceCount ?></h3>
              <p>Total Services</p>
            </div>
            
            <div class="stat-card">
              <h3><?= $orderCount ?></h3>
              <p>Total Orders</p>
            </div>
            
            <div class="stat-card">
              <h3><?= $categoryCount ?></h3>
              <p>Categories</p>
            </div>
          </div>
          
          <div class="recent-section">
            <h2>Recent Users</h2>
            
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
                <?php foreach ($recentUsers as $user): ?>
                  <tr>
                    <td><?= $user['id'] ?></td>
                    <td><?= htmlspecialchars($user['name']) ?></td>
                    <td><?= htmlspecialchars($user['username']) ?></td>
                    <td><?= htmlspecialchars($user['email']) ?></td>
                    <td>
                      <?php if ($user['is_admin']): ?>
                        <span class="badge-status badge-admin">Admin</span>
                      <?php else: ?>
                        User
                      <?php endif; ?>
                    </td>
                    <td><?= date('M d, Y', strtotime($user['created_at'])) ?></td>
                    <td>
                      <a href="users.php?action=edit&id=<?= $user['id'] ?>" class="btn-secondary">Edit</a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
            
            <div style="text-align: right; margin-top: 15px;">
              <a href="users.php" class="btn-primary">View All Users</a>
            </div>
          </div>
          
          <div class="recent-section">
            <h2>Recent Orders</h2>
            
            <table class="table">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Service</th>
                  <th>Client</th>
                  <th>Freelancer</th>
                  <th>Price</th>
                  <th>Status</th>
                  <th>Date</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($recentOrders as $order): ?>
                  <tr>
                    <td><?= $order['id'] ?></td>
                    <td><?= htmlspecialchars($order['service_title']) ?></td>
                    <td><?= htmlspecialchars($order['client_name']) ?></td>
                    <td><?= htmlspecialchars($order['freelancer_name']) ?></td>
                    <td>$<?= number_format($order['price'], 2) ?></td>
                    <td>
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
                    </td>
                    <td><?= date('M d, Y', strtotime($order['created_at'])) ?></td>
                    <td>
                      <a href="../orders/view.php?id=<?= $order['id'] ?>" class="btn-secondary">View</a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
            
            <div style="text-align: right; margin-top: 15px;">
              <a href="orders.php" class="btn-primary">View All Orders</a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </main>
  
  <?php include('../templates/footer.php'); ?>
  
  <script src="../js/main.js"></script>
</body>
</html>
