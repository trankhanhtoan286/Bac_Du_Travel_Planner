<?php
require_once 'config/db.php';
require_once 'includes/header.php';
$id = (int)($_GET['id'] ?? 1);
$loc = ['name' => 'Địa điểm thực địa', 'address' => 'Miền Bắc', 'lat' => 20.85, 'lng' => 106.68];
?>
<h1><?= $loc['name'] ?></h1>
<p><?= $loc['address'] ?></p>
<div id="map" style="height:300px;"></div>
<?php require_once 'includes/footer.php'; ?>