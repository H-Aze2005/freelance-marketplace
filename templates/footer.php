<footer>
  <div class="container">
    <div class="footer-grid">
      <div class="footer-column">
        <h3>FreelanceHub</h3>
        <p>Connect with talented freelancers to get your projects done quickly and efficiently.</p>
      </div>
      
      <div class="footer-column">
        <h3>Categories</h3>
        <ul>
          <?php
            $stmt = $db->query("SELECT id, name FROM categories LIMIT 5");
            $footerCategories = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($footerCategories as $category) {
              echo '<li><a href="search.php?category=' . $category['id'] . '">' . htmlspecialchars($category['name']) . '</a></li>';
            }
          ?>
        </ul>
      </div>
      
      <div class="footer-column">
        <h3>About</h3>
        <ul>
          <li><a href="about.php">About Us</a></li>
          <li><a href="terms.php">Terms of Service</a></li>
          <li><a href="privacy.php">Privacy Policy</a></li>
        </ul>
      </div>
      
      <div class="footer-column">
        <h3>Support</h3>
        <ul>
          <li><a href="help.php">Help & Support</a></li>
          <li><a href="contact.php">Contact Us</a></li>
          <li><a href="faq.php">FAQ</a></li>
        </ul>
      </div>
    </div>
    
    <div class="footer-bottom">
      <p>&copy; <?= date('Y') ?> FreelanceHub. All rights reserved.</p>
    </div>
  </div>
</footer>
