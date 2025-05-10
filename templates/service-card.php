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
    </div>
  </a>
</div>
