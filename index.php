<?php
  session_start();
  require_once('database/connection.php');
  require_once('utils/auth.php');
  require_once('utils/csrf.php');
  
  // Generate CSRF token if not exists
  if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
  }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>FreelanceHub - Find & Hire Top Freelancers</title>
  <link rel="stylesheet" href="css/styles.css">
  <link rel="stylesheet" href="css/home.css">
</head>
<body>
  <?php include('templates/header.php'); ?>
  
  <main>
    <section class="hero">
      <div class="container">
        <h1>Find the perfect freelance services for your business</h1>
        <p>Connect with talented freelancers to get your projects done quickly and efficiently</p>
        
        <form action="search.php" method="GET" class="search-form">
          <input type="text" name="query" placeholder="What service are you looking for today?">
          <button type="submit">Search</button>
        </form>
        
        <div class="popular-categories">
          <span>Popular:</span>
          <?php
            $stmt = $db->query("SELECT id, name FROM categories LIMIT 5");
            $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($categories as $category) {
              echo '<a href="search.php?category=' . $category['id'] . '">' . htmlspecialchars($category['name']) . '</a>';
            }
          ?>
        </div>
      </div>
    </section>
    
    <section class="featured-services">
      <div class="container">
        <h2>Featured Services</h2>
        <div class="services-grid">
          <?php
            $stmt = $db->query("
              SELECT s.id, s.title, s.price, s.delivery_time, u.name as freelancer_name, 
                     (SELECT image_path FROM service_images WHERE service_id = s.id AND is_primary = 1 LIMIT 1) as image
              FROM services s
              JOIN users u ON s.freelancer_id = u.id
              WHERE s.is_featured = 1
              LIMIT 6
            ");
            $services = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($services as $service) {
              include('templates/service-card.php');
            }
            
            // If no featured services, show some regular services
            if (count($services) == 0) {
              $stmt = $db->query("
                SELECT s.id, s.title, s.price, s.delivery_time, u.name as freelancer_name, 
                       (SELECT image_path FROM service_images WHERE service_id = s.id AND is_primary = 1 LIMIT 1) as image
                FROM services s
                JOIN users u ON s.freelancer_id = u.id
                LIMIT 6
              ");
              $services = $stmt->fetchAll(PDO::FETCH_ASSOC);
              
              foreach ($services as $service) {
                include('templates/service-card.php');
              }
            }
          ?>
        </div>
      </div>
    </section>
    
    <section class="how-it-works">
      <div class="container">
        <h2>How FreelanceHub Works</h2>
        <div class="steps">
          <div class="step">
            <div class="step-icon">1</div>
            <h3>Find the perfect service</h3>
            <p>Browse through thousands of services in various categories</p>
          </div>
          <div class="step">
            <div class="step-icon">2</div>
            <h3>Contact the freelancer</h3>
            <p>Discuss your requirements with the freelancer before ordering</p>
          </div>
          <div class="step">
            <div class="step-icon">3</div>
            <h3>Place your order</h3>
            <p>Confirm and pay for your order securely</p>
          </div>
          <div class="step">
            <div class="step-icon">4</div>
            <h3>Receive your work</h3>
            <p>Get your completed work and leave a review</p>
          </div>
        </div>
      </div>
    </section>
    
    <section class="categories">
      <div class="container">
        <h2>Browse Services by Category</h2>
        <div class="categories-grid">
          <?php
            $stmt = $db->query("SELECT id, name FROM categories");
            $allCategories = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($allCategories as $category) {
              echo '
                <a href="search.php?category=' . $category['id'] . '" class="category-card">
                  <h3>' . htmlspecialchars($category['name']) . '</h3>
                </a>
              ';
            }
          ?>
        </div>
      </div>
    </section>
  </main>
  
  <?php include('templates/footer.php'); ?>
  
  <script src="js/main.js"></script>
</body>
</html>
