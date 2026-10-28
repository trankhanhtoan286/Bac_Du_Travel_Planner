<?php
require_once 'config/db.php';
require_once 'includes/header.php';
$db = get_db();
$locations = $db->query("SELECT * FROM locations LIMIT 20")->fetchAll();
?>
<div class="location-grid">
  <h1>Danh bạ địa điểm miền Bắc</h1>
  <?php foreach($locations as $loc): ?>
    <div class="loc-card">
      <h3><?= htmlspecialchars($loc['name']) ?></h3>
      <p><?= htmlspecialchars($loc['address']) ?></p>
    </div>
  <?php endforeach; ?>
</div>
<?php require_once 'includes/footer.php'; ?>