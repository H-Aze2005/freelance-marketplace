<?php
// Include auth utilities if not already included
if (!function_exists('isLoggedIn')) {
    require_once(__DIR__ . '/../utils/auth.php');
}
?>
<header>
  <div class="container">
    <div class="logo">
      <a href="index.php">FreelanceHub</a>
    </div>
    
    <nav>
      <ul class="main-nav">
        <li><a href="search.php">Browse Services</a></li>
        <?php if (isLoggedIn()): ?>
          <li><a href="dashboard.php">Dashboard</a></li>
          <?php if (isAdmin()): ?>
            <li><a href="admin/index.php">Admin Panel</a></li>
          <?php endif; ?>
        <?php endif; ?>
      </ul>
      
      <ul class="user-nav">
        <?php if (isLoggedIn()): ?>
          <li class="messages">
            <a href="messages.php">
              Messages
              <?php
                $stmt = $db->prepare("SELECT COUNT(*) as count FROM messages WHERE receiver_id = ? AND is_read = 0");
                $stmt->execute([$_SESSION['user_id']]);
                $unreadCount = $stmt->fetch()['count'];
                
                if ($unreadCount > 0) {
                  echo '<span class="badge">' . $unreadCount . '</span>';
                }
              ?>
            </a>
          </li>
          <li class="user-dropdown">
            <a href="javascript:void(0);" class="dropdown-toggle">
              <?php
                $user = getCurrentUser($db);
                echo htmlspecialchars($user['name']);
              ?>
              <span class="arrow">▼</span>
            </a>
            <ul class="dropdown-menu">
              <li><a href="profile.php">My Profile</a></li>
              <li><a href="services/my-services.php">My Services</a></li>
              <li><a href="orders/my-orders.php">My Orders</a></li>
              <li><a href="logout.php">Logout</a></li>
            </ul>
          </li>
        <?php else: ?>
          <li><a href="login.php">Login</a></li>
          <li><a href="register.php" class="btn-primary">Join</a></li>
        <?php endif; ?>
      </ul>
    </nav>
    
    <button class="mobile-menu-toggle">
      <span></span>
      <span></span>
      <span></span>
    </button>
  </div>
</header>

<?php if (isset($_SESSION['success'])): ?>
  <div class="alert alert-success">
    <?= $_SESSION['success'] ?>
    <?php unset($_SESSION['success']); ?>
  </div>
<?php endif; ?>

<?php if (isset($_SESSION['error'])): ?>
  <div class="alert alert-error">
    <?= $_SESSION['error'] ?>
    <?php unset($_SESSION['error']); ?>
  </div>
<?php endif; ?>
