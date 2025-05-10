<?php
session_start();
require_once('database/connection.php');
require_once('utils/auth.php');

// Get all categories
$stmt = $db->query("SELECT * FROM categories ORDER BY name");
$categories = $stmt->fetchAll();

// Build query based on filters
$query = "
  SELECT s.*, u.name as freelancer_name, c.name as category_name,
         (SELECT COUNT(*) FROM orders o WHERE o.service_id = s.id) as order_count,
         (SELECT AVG(r.rating) FROM reviews r JOIN orders o ON r.order_id = o.id WHERE o.service_id = s.id) as avg_rating,
         (SELECT image_path FROM service_images WHERE service_id = s.id AND is_primary = 1 LIMIT 1) as image
  FROM services s
  JOIN users u ON s.freelancer_id = u.id
  JOIN categories c ON s.category_id = c.id
  WHERE 1=1
";

$params = [];

// Search query
if (isset($_GET['query']) && !empty($_GET['query'])) {
  $searchQuery = '%' . $_GET['query'] . '%';
  $query .= " AND (s.title LIKE ? OR s.description LIKE ?)";
  $params[] = $searchQuery;
  $params[] = $searchQuery;
}

// Category filter
if (isset($_GET['category']) && !empty($_GET['category'])) {
  $query .= " AND s.category_id = ?";
  $params[] = $_GET['category'];
}

// Price range filter
if (isset($_GET['min_price']) && is_numeric($_GET['min_price'])) {
  $query .= " AND s.price >= ?";
  $params[] = $_GET['min_price'];
}

if (isset($_GET['max_price']) && is_numeric($_GET['max_price'])) {
  $query .= " AND s.price <= ?";
  $params[] = $_GET['max_price'];
}

// Delivery time filter
if (isset($_GET['max_delivery']) && is_numeric($_GET['max_delivery'])) {
  $query .= " AND s.delivery_time <= ?";
  $params[] = $_GET['max_delivery'];
}

// Sort options
$sortOption = isset($_GET['sort']) ? $_GET['sort'] : 'newest';

switch ($sortOption) {
  case 'price_low':
    $query .= " ORDER BY s.price ASC";
    break;
  case 'price_high':
    $query .= " ORDER BY s.price DESC";
    break;
  case 'rating':
    $query .= " ORDER BY avg_rating DESC";
    break;
  case 'popular':
    $query .= " ORDER BY order_count DESC";
    break;
  case 'newest':
  default:
    $query .= " ORDER BY s.created_at DESC";
    break;
}

// Execute query
$stmt = $db->prepare($query);
$stmt->execute($params);
$services = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Search Services - FreelanceHub</title>
  <link rel="stylesheet" href="css/styles.css">
  <style>
    .search-container {
      display: grid;
      grid-template-columns: 1fr 3fr;
      gap: 30px;
      margin: 30px 0;
    }
    
    .filters {
      background-color: white;
      border-radius: 8px;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
      padding: 20px;
      position: sticky;
      top: 90px;
      max-height: calc(100vh - 120px);
      overflow-y: auto;
    }
    
    .filter-section {
      margin-bottom: 20px;
      padding-bottom: 20px;
      border-bottom: 1px solid #eee;
    }
    
    .filter-section:last-child {
      border-bottom: none;
      margin-bottom: 0;
      padding-bottom: 0;
    }
    
    .filter-section h3 {
      margin-bottom: 15px;
      font-size: 1.1rem;
    }
    
    .category-list label {
      display: block;
      margin-bottom: 10px;
      font-weight: normal;
    }
    
    .price-range {
      display: flex;
      gap: 10px;
    }
    
    .price-range input {
      width: 50%;
    }
    
    .search-results {
      display: flex;
      flex-direction: column;
    }
    
    .search-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 20px;
    }
    
    .sort-options {
      display: flex;
      align-items: center;
    }
    
    .sort-options label {
      margin-right: 10px;
      font-weight: normal;
    }
    
    .services-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
      gap: 20px;
    }
    
    .no-results {
      text-align: center;
      padding: 50px 0;
      color: #777;
    }
    
    .mobile-filter-toggle {
      display: none;
      margin-bottom: 20px;
    }
    
    @media (max-width: 768px) {
      .search-container {
        grid-template-columns: 1fr;
      }
      
      .filters {
        position: fixed;
        left: -100%;
        top: 0;
        width: 80%;
        height: 100vh;
        z-index: 1000;
        transition: left 0.3s;
        max-height: none;
      }
      
      .filters.active {
        left: 0;
      }
      
      .mobile-filter-toggle {
        display: block;
      }
      
      .filter-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: rgba(0, 0, 0, 0.5);
        z-index: 999;
        display: none;
      }
      
      .filter-overlay.active {
        display: block;
      }
      
      .close-filters {
        display: block;
        text-align: right;
        margin-bottom: 15px;
        font-size: 1.5rem;
        cursor: pointer;
      }
    }
  </style>
</head>
<body>
  <?php include('templates/header.php'); ?>
  
  <main>
    <div class="container">
      <div class="mobile-filter-toggle">
        <button class="btn-secondary" id="show-filters">Show Filters</button>
      </div>
      
      <div class="filter-overlay" id="filter-overlay"></div>
      
      <div class="search-container">
        <div class="filters" id="filters">
          <div class="close-filters" id="close-filters">×</div>
          
          <form action="search.php" method="GET" id="filter-form">
            <?php if (isset($_GET['query'])): ?>
              <input type="hidden" name="query" value="<?= htmlspecialchars($_GET['query']) ?>">
            <?php endif; ?>
            
            <div class="filter-section">
              <h3>Search</h3>
              <input type="text" name="query" placeholder="What service are you looking for?" value="<?= isset($_GET['query']) ? htmlspecialchars($_GET['query']) : '' ?>">
            </div>
            
            <div class="filter-section">
              <h3>Categories</h3>
              <div class="category-list">
                <?php foreach ($categories as $category): ?>
                  <label>
                    <input type="radio" name="category" value="<?= $category['id'] ?>" <?= (isset($_GET['category']) && $_GET['category'] == $category['id']) ? 'checked' : '' ?>>
                    <?= htmlspecialchars($category['name']) ?>
                  </label>
                <?php endforeach; ?>
              </div>
            </div>
            
            <div class="filter-section">
              <h3>Price Range</h3>
              <div class="price-range">
                <input type="number" name="min_price" placeholder="Min" min="0" value="<?= isset($_GET['min_price']) ? htmlspecialchars($_GET['min_price']) : '' ?>">
                <input type="number" name="max_price" placeholder="Max" min="0" value="<?= isset($_GET['max_price']) ? htmlspecialchars($_GET['max_price']) : '' ?>">
              </div>
            </div>
            
            <div class="filter-section">
              <h3>Delivery Time</h3>
              <select name="max_delivery">
                <option value="">Any</option>
                <option value="1" <?= (isset($_GET['max_delivery']) && $_GET['max_delivery'] == '1') ? 'selected' : '' ?>>Up to 1 day</option>
                <option value="3" <?= (isset($_GET['max_delivery']) && $_GET['max_delivery'] == '3') ? 'selected' : '' ?>>Up to 3 days</option>
                <option value="7" <?= (isset($_GET['max_delivery']) && $_GET['max_delivery'] == '7') ? 'selected' : '' ?>>Up to 7 days</option>
                <option value="14" <?= (isset($_GET['max_delivery']) && $_GET['max_delivery'] == '14') ? 'selected' : '' ?>>Up to 14 days</option>
                <option value="30" <?= (isset($_GET['max_delivery']) && $_GET['max_delivery'] == '30') ? 'selected' : '' ?>>Up to 30 days</option>
              </select>
            </div>
            
            <button type="submit" class="btn-primary" style="width: 100%;">Apply Filters</button>
          </form>
        </div>
        
        <div class="search-results">
          <div class="search-header">
            <h1>
              <?php if (isset($_GET['query']) && !empty($_GET['query'])): ?>
                Search results for "<?= htmlspecialchars($_GET['query']) ?>"
              <?php elseif (isset($_GET['category']) && !empty($_GET['category'])): ?>
                <?php
                  $categoryName = '';
                  foreach ($categories as $cat) {
                    if ($cat['id'] == $_GET['category']) {
                      $categoryName = $cat['name'];
                      break;
                    }
                  }
                ?>
                Services in <?= htmlspecialchars($categoryName) ?>
              <?php else: ?>
                All Services
              <?php endif; ?>
            </h1>
            
            <div class="sort-options">
              <label for="sort">Sort by:</label>
              <select name="sort" id="sort">
                <option value="newest" <?= (!isset($_GET['sort']) || $_GET['sort'] == 'newest') ? 'selected' : '' ?>>Newest</option>
                <option value="price_low" <?= (isset($_GET['sort']) && $_GET['sort'] == 'price_low') ? 'selected' : '' ?>>Price: Low to High</option>
                <option value="price_high" <?= (isset($_GET['sort']) && $_GET['sort'] == 'price_high') ? 'selected' : '' ?>>Price: High to Low</option>
                <option value="rating" <?= (isset($_GET['sort']) && $_GET['sort'] == 'rating') ? 'selected' : '' ?>>Top Rated</option>
                <option value="popular" <?= (isset($_GET['sort']) && $_GET['sort'] == 'popular') ? 'selected' : '' ?>>Most Popular</option>
              </select>
            </div>
          </div>
          
          <?php if (count($services) > 0): ?>
            <div class="services-grid">
              <?php foreach ($services as $service): ?>
                <div class="service-card">
                  <a href="services/view.php?id=<?= $service['id'] ?>">
                    <div class="service-image">
                      <?php if (!empty($service['image'])): ?>
                        <img src="<?= htmlspecialchars($service['image']) ?>" alt="<?= htmlspecialchars($service['title']) ?>">
                      <?php else: ?>
                        <img src="images/placeholder-service.jpg" alt="<?= htmlspecialchars($service['title']) ?>">
                      <?php endif; ?>
                    </div>
                    <div class="service-info">
                      <h3><?= htmlspecialchars($service['title']) ?></h3>
                      <p class="service-freelancer">by <?= htmlspecialchars($service['freelancer_name']) ?></p>
                      <div class="service-meta">
                        <span class="service-price">$<?= number_format($service['price'], 2) ?></span>
                        <span class="service-delivery"><?= $service['delivery_time'] ?> day<?= $service['delivery_time'] > 1 ? 's' : '' ?></span>
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
                        </div>
                      <?php endif; ?>
                    </div>
                  </a>
                </div>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <div class="no-results">
              <h2>No services found</h2>
              <p>Try adjusting your search criteria or browse all services.</p>
              <a href="search.php" class="btn-primary">Browse All Services</a>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </main>
  
  <?php include('templates/footer.php'); ?>
  
  <script src="js/main.js"></script>
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      // Sort change handler
      const sortSelect = document.getElementById('sort');
      sortSelect.addEventListener('change', function() {
        const currentUrl = new URL(window.location.href);
        currentUrl.searchParams.set('sort', this.value);
        window.location.href = currentUrl.toString();
      });
      
      // Mobile filters
      const showFilters = document.getElementById('show-filters');
      const closeFilters = document.getElementById('close-filters');
      const filters = document.getElementById('filters');
      const filterOverlay = document.getElementById('filter-overlay');
      
      showFilters.addEventListener('click', function() {
        filters.classList.add('active');
        filterOverlay.classList.add('active');
        document.body.style.overflow = 'hidden';
      });
      
      closeFilters.addEventListener('click', function() {
        filters.classList.remove('active');
        filterOverlay.classList.remove('active');
        document.body.style.overflow = '';
      });
      
      filterOverlay.addEventListener('click', function() {
        filters.classList.remove('active');
        filterOverlay.classList.remove('active');
        document.body.style.overflow = '';
      });
    });
  </script>
</body>
</html>
