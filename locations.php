<?php
require_once __DIR__ . '/includes/helpers.php';

$pdo = get_db();

$q = trim($_GET['q'] ?? '');
$provinceId = isset($_GET['province_id']) && $_GET['province_id'] !== '' ? (int)$_GET['province_id'] : 0;
$type = $_GET['type'] ?? '';
$categoryId = isset($_GET['category_id']) && $_GET['category_id'] !== '' ? (int)$_GET['category_id'] : 0;

$provinces = $pdo->query("
    SELECT
        p.*,
        SUM(CASE WHEN l.location_type = 'attraction' AND l.status = 'active' THEN 1 ELSE 0 END) AS attraction_count,
        SUM(CASE WHEN l.location_type = 'food' AND l.status = 'active' THEN 1 ELSE 0 END) AS food_count,
        SUM(CASE WHEN l.location_type = 'stay' AND l.status = 'active' THEN 1 ELSE 0 END) AS stay_count
    FROM provinces p
    LEFT JOIN locations l ON l.province_id = p.id
    WHERE p.is_active = 1
    GROUP BY p.id
    ORDER BY p.id ASC
")->fetchAll();
$categories = $pdo->query('SELECT * FROM categories WHERE is_active = 1 ORDER BY location_type ASC, id ASC')->fetchAll();

$where = ["l.status = 'active'"];
$params = [];

if ($q !== '') {
    $where[] = '(l.name LIKE ? OR l.short_desc LIKE ? OR l.address LIKE ? OR l.signature_dishes LIKE ?)';
    $kw = '%' . $q . '%';
    array_push($params, $kw, $kw, $kw, $kw);
}
if ($provinceId > 0) {
    $where[] = 'l.province_id = ?';
    $params[] = $provinceId;
}
if (in_array($type, ['attraction', 'food', 'stay'], true)) {
    $where[] = 'l.location_type = ?';
    $params[] = $type;
}
if ($categoryId > 0) {
    $where[] = 'l.category_id = ?';
    $params[] = $categoryId;
}

$whereSql = implode(' AND ', $where);
$stmt = $pdo->prepare("
    SELECT
        l.*,
        p.name AS province_name,
        c.name AS category_name,
        COALESCE(ROUND(AVG(CASE WHEN ep.status = 'approved' THEN ep.rating END), 1), 0) AS avg_rating,
        COUNT(CASE WHEN ep.status = 'approved' THEN 1 END) AS review_count
    FROM locations l
    JOIN provinces p ON p.id = l.province_id
    JOIN categories c ON c.id = l.category_id
    LEFT JOIN experience_posts ep ON ep.location_id = l.id
    WHERE {$whereSql}
    GROUP BY l.id
    ORDER BY l.province_id ASC, l.location_type ASC, l.id ASC
");
$stmt->execute($params);
$locations = $stmt->fetchAll();

// Danh sách yêu thích của user hiện tại
$userFavIds = [];
if (is_logged_in()) {
    $stmtF = $pdo->prepare('SELECT location_id FROM favorite_locations WHERE user_id = ?');
    $stmtF->execute([current_user()['id']]);
    $userFavIds = array_map('intval', $stmtF->fetchAll(PDO::FETCH_COLUMN));
}

// Chuẩn bị dữ liệu điểm bản đồ Leaflet
$mapPoints = [];
foreach ($locations as $loc) {
    $mapPoints[] = [
        'location_id'     => (int)$loc['id'],
        'title'           => $loc['name'],
        'subtitle'        => $loc['province_name'] . ' · ' . format_vnd($loc['ref_price'], true) . ((float)$loc['ref_price'] > 0 ? '/' . $loc['price_unit'] : ''),
        'type'            => $loc['location_type'],
        'lat'             => (float)$loc['latitude'],
        'lng'             => (float)$loc['longitude'],
        'google_maps_url' => google_maps_view_url($loc['latitude'], $loc['longitude'], $loc['google_maps_url']),
    ];
}

$pageTitle = 'Khám phá Địa điểm Tham quan, Ăn uống, Lưu trú & Bản đồ';
require_once __DIR__ . '/includes/header.php';
?>

<section class="cin-subhero">
  <div class="container position-relative" style="z-index: 2;">
    <div class="d-flex flex-wrap justify-content-between align-items-end gap-3">
      <div>
        <div class="cin-subhero-meta">
          <span><i class="bi bi-geo-alt-fill me-1"></i>LƯU TRỮ THỰC ĐỊA MIỀN BẮC</span>
          <span>·</span>
          <span>TAM GIÁC GIÁC QUAN · MODULE M5</span>
        </div>
        <h1 class="cin-subhero-title">
          Điểm tham quan, <span class="sw">Hương vị & Hiên nhà</span> miền Bắc
        </h1>
        <p class="cin-subhero-desc">
          Dữ liệu chuẩn hóa theo 3 nhóm: Điểm tham quan, Ăn uống và Lưu trú kèm tọa độ GPS thực địa, giá tham khảo cập nhật và chỉ đường Google Maps.
        </p>
      </div>
      <div>
        <span class="slip-tag slip-tag-jade px-3 py-2">
          ĐANG HIỂN THỊ: <?= count($locations) ?> TỌA ĐỘ
        </span>
      </div>
    </div>
  </div>
</section>

<main class="container pb-5" id="mainContent">
  <!-- BẢNG ĐIỀU PHỐI LỌC ĐỊA ĐIỂM (UC02.3 -> UC02.7) -->
  <div class="cin-filter-console">
    <form action="locations.php" method="get" class="row g-3 align-items-end">
      <div class="col-md-3">
        <label class="form-label">01 · Từ khóa / Món ăn / Địa chỉ</label>
        <div class="input-group">
          <span class="input-group-text bg-light border-secondary-subtle"><i class="bi bi-search"></i></span>
          <input type="text" name="q" value="<?= e($q) ?>" class="form-control" placeholder="VD: Nà Ka, cá hồi, Tràng An...">
        </div>
      </div>

      <div class="col-md-3">
        <label class="form-label">02 · Tỉnh / Thành miền Bắc</label>
        <select name="province_id" class="form-select">
          <option value="">Tất cả tỉnh/thành</option>
          <?php foreach ($provinces as $prov): ?>
            <option value="<?= (int)$prov['id'] ?>" <?= $provinceId === (int)$prov['id'] ? 'selected' : '' ?>>
              <?= e($prov['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-md-2">
        <label class="form-label">03 · Nhóm trải nghiệm</label>
        <select name="type" class="form-select">
          <option value="">Tất cả 3 nhóm</option>
          <option value="attraction" <?= $type === 'attraction' ? 'selected' : '' ?>>Tham quan</option>
          <option value="food" <?= $type === 'food' ? 'selected' : '' ?>>Ăn uống</option>
          <option value="stay" <?= $type === 'stay' ? 'selected' : '' ?>>Lưu trú</option>
        </select>
      </div>

      <div class="col-md-2">
        <label class="form-label">04 · Danh mục chi tiết</label>
        <select name="category_id" class="form-select">
          <option value="">Mọi danh mục</option>
          <?php foreach ($categories as $cat): ?>
            <option value="<?= (int)$cat['id'] ?>" <?= $categoryId === (int)$cat['id'] ? 'selected' : '' ?>>
              <?= e($cat['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-md-2 d-flex gap-2">
        <button type="submit" class="btn-cin-ink flex-grow-1"><i class="bi bi-funnel"></i> Lọc</button>
        <a href="locations.php" class="btn-cin-outline px-3" title="Đặt lại"><i class="bi bi-arrow-counterclockwise"></i></a>
      </div>
    </form>

    <!-- DẢI PHÂN BỔ ĐỊA ĐIỂM THEO 8 KHU VỰC MIỀN BẮC -->
    <div class="pt-3 mt-3 border-top">
      <div class="d-flex flex-wrap justify-content-between align-items-center mb-2">
        <span class="cin-eyebrow mb-0" style="color: var(--cin-jade);">CHỌN NHANH THEO 8 KHU VỰC MIỀN BẮC (THAM QUAN · ĂN UỐNG · LƯU TRÚ)</span>
        <?php if ($provinceId > 0 || $type !== ''): ?>
          <a href="locations.php" class="font-monospace small fw-bold" style="color: var(--cin-amber); font-size: 0.74rem;">
            <i class="bi bi-x-circle me-1"></i>Xem lại toàn miền Bắc
          </a>
        <?php endif; ?>
      </div>
      <div class="row g-2">
        <?php foreach ($provinces as $provItem): ?>
          <?php $isActiveProv = ($provinceId === (int)$provItem['id']); ?>
          <div class="col-6 col-md-4 col-lg-3">
            <a href="locations.php?province_id=<?= (int)$provItem['id'] ?><?= $type !== '' ? '&type=' . urlencode($type) : '' ?>"
               class="d-block p-2 text-decoration-none h-100"
               style="border-radius: 3px; border: 1px solid <?= $isActiveProv ? 'var(--cin-amber)' : 'rgba(20,34,27,0.16)' ?>; background: <?= $isActiveProv ? 'var(--cin-jade-deep)' : 'var(--cin-paper)' ?>; color: <?= $isActiveProv ? 'var(--cin-cream)' : 'var(--cin-ink)' ?>;">
              <div class="fw-bold small text-truncate"><?= e($provItem['name']) ?></div>
              <div class="font-monospace mt-1 d-flex flex-wrap gap-2" style="font-size: 0.68rem; color: <?= $isActiveProv ? 'rgba(246,238,224,0.82)' : 'var(--cin-ink-muted)' ?>;">
                <span><i class="bi bi-compass text-success"></i> <?= (int)$provItem['attraction_count'] ?> điểm</span>
                <span><i class="bi bi-cup-hot text-warning"></i> <?= (int)$provItem['food_count'] ?> quán</span>
                <span><i class="bi bi-house-heart text-info"></i> <?= (int)$provItem['stay_count'] ?> nghỉ</span>
              </div>
            </a>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- BẢN ĐỒ TỔNG HỢP OPENSTREETMAP + LEAFLET (UC04.1 -> UC04.3) -->
  <div class="cin-map-frame mb-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-3 gap-2">
      <div>
        <span class="cin-eyebrow text-dark">Bản đồ thực địa</span>
        <h4 class="field-slip-title mb-0">
          Bản đồ tổng hợp các địa điểm đang hiển thị
        </h4>
        <small class="text-muted">Nhấp vào từng ký hiệu trên bản đồ để xem thông tin nhanh, mở hồ sơ chi tiết hoặc chỉ đường Google Maps.</small>
      </div>
      <div class="d-flex flex-wrap gap-2">
        <button type="button" class="btn-cin-ink py-1 px-3" onclick="BacDuMap.renderMarkers('all', 'all')">
          Tất cả (<?= count($mapPoints) ?>)
        </button>
        <button type="button" class="btn-cin-outline py-1 px-3" onclick="BacDuMap.renderMarkers('all', 'attraction')">
          <i class="bi bi-compass-fill text-success me-1"></i>Tham quan
        </button>
        <button type="button" class="btn-cin-outline py-1 px-3" onclick="BacDuMap.renderMarkers('all', 'food')">
          <i class="bi bi-cup-hot-fill text-warning me-1"></i>Ăn uống
        </button>
        <button type="button" class="btn-cin-outline py-1 px-3" onclick="BacDuMap.renderMarkers('all', 'stay')">
          <i class="bi bi-house-heart-fill text-primary me-1"></i>Lưu trú
        </button>
      </div>
    </div>

    <div id="locationsOverviewMap" class="leaflet-map-box"></div>
  </div>

  <!-- DANH SÁCH THẺ FIELD SLIP ĐỊA ĐIỂM (UC02.1) -->
  <?php if (empty($locations)): ?>
    <div class="almanac-dossier-box text-center py-5">
      <i class="bi bi-geo-alt fs-1 text-muted"></i>
      <h4 class="cin-display-h2 fs-3 mt-2">Không tìm thấy tọa độ phù hợp</h4>
      <p class="text-muted small mb-3">Thử thay đổi từ khóa tìm kiếm hoặc chọn "Tất cả tỉnh/thành".</p>
      <a href="locations.php" class="btn-cin-amber">Xem toàn bộ địa điểm</a>
    </div>
  <?php else: ?>
    <div class="row g-4">
      <?php foreach ($locations as $loc): ?>
        <?php
          $meta = location_type_meta($loc['location_type']);
          $isFav = in_array((int)$loc['id'], $userFavIds, true);
        ?>
        <div class="col-md-6 col-lg-4">
          <article class="field-slip">
            <div class="field-slip-media">
              <img src="<?= e($loc['thumbnail_url']) ?>" alt="<?= e($loc['name']) ?>">
              <div class="field-slip-badges">
                <span class="slip-tag">
                  <i class="bi <?= e($meta['icon']) ?> me-1"></i><?= e($meta['label']) ?>
                </span>
                <span class="slip-tag slip-tag-jade">
                  <?= e($loc['province_name']) ?>
                </span>
              </div>
              <div class="field-slip-fav">
                <form action="favorites.php" method="post" class="m-0">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="toggle">
                  <input type="hidden" name="location_id" value="<?= (int)$loc['id'] ?>">
                  <input type="hidden" name="redirect" value="<?= e($_SERVER['REQUEST_URI'] ?? 'locations.php') ?>">
                  <button type="submit" title="<?= $isFav ? 'Bỏ yêu thích' : 'Lưu yêu thích' ?>">
                    <i class="bi <?= $isFav ? 'bi-heart-fill text-danger' : 'bi-heart' ?>"></i>
                  </button>
                </form>
              </div>
            </div>

            <div class="field-slip-body">
              <div class="loc-coord-strip">
                <span>GPS <?= number_format((float)$loc['latitude'], 2) ?>°N · <?= number_format((float)$loc['longitude'], 2) ?>°E</span>
                <?php if ($loc['review_count'] > 0): ?>
                  <span class="text-warning-emphasis fw-bold">
                    ★ <?= number_format((float)$loc['avg_rating'], 1) ?> (<?= (int)$loc['review_count'] ?>)
                  </span>
                <?php else: ?>
                  <span><?= e($loc['category_name']) ?></span>
                <?php endif; ?>
              </div>

              <h3 class="field-slip-title">
                <a href="location_detail.php?id=<?= (int)$loc['id'] ?>"><?= e($loc['name']) ?></a>
              </h3>

              <p class="small text-muted mb-3"><?= e($loc['short_desc']) ?></p>

              <!-- Điểm nhấn đặc thù theo từng nhóm địa điểm (Mục 4.6) -->
              <div class="field-spec-box">
                <?php if ($loc['location_type'] === 'attraction'): ?>
                  <div><i class="bi bi-clock-history text-success me-1"></i><strong>Thời lượng:</strong> <?= e($loc['visit_duration'] ?: '1.5 - 2 giờ') ?></div>
                  <div class="text-truncate" title="<?= e($loc['seasonal_note']) ?>">
                    <i class="bi bi-flower1 text-warning me-1"></i><strong>Theo mùa:</strong> <?= e($loc['seasonal_note'] ?: 'Đẹp quanh năm') ?>
                  </div>
                <?php elseif ($loc['location_type'] === 'food'): ?>
                  <div class="text-truncate" title="<?= e($loc['signature_dishes']) ?>">
                    <i class="bi bi-stars text-warning me-1"></i><strong>Món nổi bật:</strong> <?= e($loc['signature_dishes']) ?>
                  </div>
                  <div><i class="bi bi-cash-coin text-success me-1"></i><strong>Khoảng giá:</strong> <?= e($loc['price_range_per_person']) ?></div>
                <?php else: ?>
                  <div><i class="bi bi-building text-primary me-1"></i><strong>Loại hình:</strong> <?= e($loc['stay_type']) ?></div>
                  <div><i class="bi bi-door-open text-success me-1"></i><strong>Giờ:</strong> <?= e($loc['checkin_checkout_hours']) ?></div>
                <?php endif; ?>
              </div>

              <div class="field-slip-footer">
                <div>
                  <span class="small text-muted d-block" style="font-size:0.72rem;">Giá tham khảo</span>
                  <span class="price-mono">
                    <?= format_vnd($loc['ref_price'], true) ?>
                  </span>
                  <?php if ((float)$loc['ref_price'] > 0): ?>
                    <small class="text-muted">/<?= e($loc['price_unit']) ?></small>
                  <?php endif; ?>
                </div>
                <div class="d-flex gap-1">
                  <a href="<?= e(google_maps_view_url($loc['latitude'], $loc['longitude'], $loc['google_maps_url'])) ?>"
                     target="_blank" rel="noopener" class="btn-cin-outline px-2 py-1" title="Xem trên Google Maps">
                    <i class="bi bi-geo-alt-fill text-danger"></i>
                  </a>
                  <a href="<?= e(google_maps_directions_url($loc['latitude'], $loc['longitude'])) ?>"
                     target="_blank" rel="noopener" class="btn-cin-outline px-2 py-1" title="Chỉ đường Google Maps">
                    <i class="bi bi-sign-turn-right-fill text-primary"></i>
                  </a>
                  <a href="location_detail.php?id=<?= (int)$loc['id'] ?>" class="btn-cin-ink py-1 px-3">
                    Hồ sơ
                  </a>
                </div>
              </div>
            </div>
          </article>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</main>

<?php
$mapCenterLat = 21.35;
$mapCenterLng = 105.15;
$mapZoom = 7;
if ($provinceId > 0) {
    foreach ($provinces as $pr) {
        if ((int)$pr['id'] === $provinceId && !empty($pr['latitude']) && !empty($pr['longitude'])) {
            $mapCenterLat = (float)$pr['latitude'];
            $mapCenterLng = (float)$pr['longitude'];
            $mapZoom = 11;
            break;
        }
    }
}
?>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const mapPoints = <?= json_encode($mapPoints, JSON_UNESCAPED_UNICODE) ?>;
  BacDuMap.init('locationsOverviewMap', mapPoints, [<?= $mapCenterLat ?>, <?= $mapCenterLng ?>], <?= $mapZoom ?>);
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
