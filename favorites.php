<?php
require_once __DIR__ . '/includes/helpers.php';

$user = require_login('favorites.php');
$pdo = get_db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        set_flash('danger', 'Phiên làm việc không hợp lệ.');
        header('Location: favorites.php');
        exit;
    }

    $action = $_POST['action'] ?? '';
    $locationId = (int)($_POST['location_id'] ?? 0);
    $redirect = $_POST['redirect'] ?? 'favorites.php';
    $safeRedirect = (str_starts_with($redirect, 'http') || str_starts_with($redirect, '//')) ? 'favorites.php' : $redirect;

    if ($action === 'toggle' && $locationId > 0) {
        $chk = $pdo->prepare('SELECT id FROM favorite_locations WHERE user_id = ? AND location_id = ? LIMIT 1');
        $chk->execute([$user['id'], $locationId]);
        $existing = $chk->fetch();

        if ($existing) {
            $del = $pdo->prepare('DELETE FROM favorite_locations WHERE id = ?');
            $del->execute([$existing['id']]);
            set_flash('info', 'Đã bỏ lưu địa điểm khỏi danh sách yêu thích.');
        } else {
            $ins = $pdo->prepare('INSERT INTO favorite_locations (user_id, location_id) VALUES (?, ?)');
            $ins->execute([$user['id'], $locationId]);
            set_flash('success', 'Đã lưu địa điểm vào danh sách Yêu thích của bạn!');
        }
        header('Location: ' . $safeRedirect);
        exit;
    } elseif ($action === 'add_to_trip' && $locationId > 0) {
        $tripDayId = (int)($_POST['trip_day_id'] ?? 0);
        $startTime = trim($_POST['start_time'] ?? '09:00');
        $endTime = trim($_POST['end_time'] ?? '11:00');
        $customNote = trim($_POST['notes'] ?? '');

        // Kiểm tra quyền sở hữu ngày chuyến đi
        $stmtDay = $pdo->prepare('
            SELECT td.*, t.user_id, t.pax_count, t.id AS parent_trip_id
            FROM trip_days td
            JOIN trips t ON t.id = td.trip_id
            WHERE td.id = ? AND t.user_id = ?
            LIMIT 1
        ');
        $stmtDay->execute([$tripDayId, $user['id']]);
        $dayRow = $stmtDay->fetch();

        $stmtLoc = $pdo->prepare('SELECT * FROM locations WHERE id = ? LIMIT 1');
        $stmtLoc->execute([$locationId]);
        $locRow = $stmtLoc->fetch();

        if ($dayRow && $locRow) {
            $actType = 'attraction';
            if ($locRow['location_type'] === 'food') $actType = 'food';
            elseif ($locRow['location_type'] === 'stay') $actType = 'checkin_checkout';

            $estCost = (float)$locRow['ref_price'];
            if ($locRow['price_unit'] === 'người') {
                $estCost *= max(1, (int)$dayRow['pax_count']);
            }

            $insAct = $pdo->prepare('
                INSERT INTO activities (trip_day_id, location_id, title, activity_type, start_time, end_time, description, estimated_cost, notes, sort_order)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 99)
            ');
            $insAct->execute([
                $tripDayId,
                $locationId,
                $locRow['name'],
                $actType,
                $startTime,
                $endTime,
                $locRow['short_desc'],
                $estCost,
                $customNote ?: 'Thêm từ Địa điểm yêu thích / Khám phá',
            ]);

            set_flash('success', 'Đã thêm <strong>' . e($locRow['name']) . '</strong> vào Ngày ' . (int)$dayRow['day_number'] . ' của chuyến đi!');
            header('Location: trip_detail.php?id=' . (int)$dayRow['parent_trip_id']);
            exit;
        } else {
            set_flash('danger', 'Không tìm thấy ngày lịch trình hợp lệ để thêm địa điểm.');
            header('Location: ' . $safeRedirect);
            exit;
        }
    }
}

$filterType = $_GET['type'] ?? 'all';
$params = [$user['id']];
$whereType = '';
if (in_array($filterType, ['attraction', 'food', 'stay'], true)) {
    $whereType = ' AND l.location_type = ?';
    $params[] = $filterType;
}

$stmtFavs = $pdo->prepare("
    SELECT
        l.*,
        p.name AS province_name,
        c.name AS category_name,
        fl.created_at AS saved_at
    FROM favorite_locations fl
    JOIN locations l ON l.id = fl.location_id
    JOIN provinces p ON p.id = l.province_id
    JOIN categories c ON c.id = l.category_id
    WHERE fl.user_id = ? {$whereType}
    ORDER BY fl.created_at DESC
");
$stmtFavs->execute($params);
$favorites = $stmtFavs->fetchAll();

// Lấy danh sách các ngày trong chuyến đi của user để hỗ trợ "Thêm vào chuyến đi"
$stmtTripDays = $pdo->prepare('
    SELECT td.id AS trip_day_id, td.day_number, td.trip_date, t.title AS trip_title
    FROM trip_days td
    JOIN trips t ON t.id = td.trip_id
    WHERE t.user_id = ?
    ORDER BY t.start_date DESC, td.day_number ASC
');
$stmtTripDays->execute([$user['id']]);
$userTripDays = $stmtTripDays->fetchAll();

$pageTitle = 'Địa điểm yêu thích đã lưu';
require_once __DIR__ . '/includes/header.php';
?>

<!-- SUBPAGE CINEMATIC HERO -->
<header class="cin-subhero">
  <img src="https://images.unsplash.com/photo-1508009603885-50cf7c579365?w=1800&auto=format&fit=crop&q=82"
       alt="Sổ tay tọa độ yêu thích" class="cin-subhero-bg">
  <div class="cin-subhero-overlay"></div>
  <div class="container cin-subhero-inner">
    <div class="d-flex flex-wrap justify-content-between align-items-end gap-3">
      <div>
        <span class="cin-eyebrow mb-2" style="color: var(--cin-amber-bright);">
          SỔ TAY TỌA ĐỘ CÁ NHÂN · MODULE M7 (<?= count($favorites) ?> MỤC)
        </span>
        <h1 class="cin-subhero-title">
          Tọa Độ <span class="sw">Đã Lưu</span> Cho Hành Trình Sắp Tới
        </h1>
        <p class="cin-subhero-sub">
          Phân nhóm theo Điểm tham quan, Quán ăn đặc sản và Lưu trú để chèn trực tiếp vào từng ngày trong kịch bản chuyến đi của bạn.
        </p>
      </div>
      <div class="d-flex flex-wrap gap-2">
        <a href="locations.php" class="btn-cin-amber">
          <i class="bi bi-compass"></i>Khám phá thêm địa điểm
        </a>
        <a href="trips.php" class="btn-cin-ghost">
          <i class="bi bi-luggage"></i>Chuyến đi của tôi
        </a>
      </div>
    </div>
  </div>
</header>

<main id="mainContent" class="container py-4">
  <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
    <div class="app-view-tabs" role="tablist">
      <a href="favorites.php?type=all" class="app-view-tab <?= $filterType === 'all' ? 'active' : '' ?>" data-key="1">
        Tất cả nhóm
      </a>
      <a href="favorites.php?type=attraction" class="app-view-tab <?= $filterType === 'attraction' ? 'active' : '' ?>" data-key="2">
        <i class="bi bi-compass me-1"></i>Tham quan
      </a>
      <a href="favorites.php?type=food" class="app-view-tab <?= $filterType === 'food' ? 'active' : '' ?>" data-key="3">
        <i class="bi bi-cup-hot me-1"></i>Ăn uống
      </a>
      <a href="favorites.php?type=stay" class="app-view-tab <?= $filterType === 'stay' ? 'active' : '' ?>" data-key="4">
        <i class="bi bi-house-heart me-1"></i>Lưu trú
      </a>
    </div>
    <span class="font-monospace small text-muted">ĐANG HIỂN THỊ: <strong><?= count($favorites) ?></strong> TỌA ĐỘ</span>
  </div>

  <?php if (empty($favorites)): ?>
    <div class="cin-empty-state">
      <i class="bi bi-heart fs-1 text-danger"></i>
      <h3 class="h4 fw-bold mt-3" style="font-family: var(--font-display);">Sổ tay tọa độ yêu thích đang trống</h3>
      <p class="small text-muted mb-4">Hãy khám phá các điểm tham quan, quán ăn đặc sản và homestay miền Bắc để lưu lại cho chuyến đi sắp tới.</p>
      <a href="locations.php" class="btn-cin-amber">Khám phá địa điểm ngay</a>
    </div>
  <?php else: ?>
    <div class="row g-4">
      <?php foreach ($favorites as $loc): ?>
        <?php $meta = location_type_meta($loc['location_type']); ?>
        <div class="col-md-6 col-lg-4">
          <article class="field-slip" data-kb-row="1">
            <div class="field-slip-media" style="height: 200px;">
              <img src="<?= e($loc['thumbnail_url']) ?>" alt="<?= e($loc['name']) ?>">
              <div class="field-slip-badges">
                <span class="slip-tag">
                  <i class="bi <?= e($meta['icon']) ?> me-1"></i><?= e($meta['label']) ?>
                </span>
              </div>
              <div class="card-fav-overlay">
                <form action="favorites.php" method="post" class="m-0">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="toggle">
                  <input type="hidden" name="location_id" value="<?= (int)$loc['id'] ?>">
                  <button type="submit" class="btn btn-light btn-sm rounded-circle shadow-sm" title="Bỏ lưu yêu thích">
                    <i class="bi bi-heart-fill text-danger"></i>
                  </button>
                </form>
              </div>
            </div>

            <div class="field-slip-body">
              <div class="loc-coord-strip">
                <span><i class="bi bi-geo-alt-fill text-success me-1"></i><?= e($loc['province_name']) ?> · <?= e($loc['category_name']) ?></span>
                <span><?= number_format((float)$loc['latitude'], 2) ?>°N · <?= number_format((float)$loc['longitude'], 2) ?>°E</span>
              </div>

              <h3 class="field-slip-title mb-2">
                <a href="location_detail.php?id=<?= (int)$loc['id'] ?>" data-kb-primary><?= e($loc['name']) ?></a>
              </h3>
              <p class="small mb-3" style="color: var(--cin-ink-muted);"><?= e($loc['short_desc']) ?></p>

              <div class="mt-auto">
                <?php if (!empty($userTripDays)): ?>
                  <form action="favorites.php" method="post" class="field-spec-box mb-3">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="add_to_trip">
                    <input type="hidden" name="location_id" value="<?= (int)$loc['id'] ?>">
                    <div class="small fw-bold mb-1" style="color: var(--cin-jade-deep);">
                      <i class="bi bi-plus-circle-fill text-warning me-1"></i>Chèn vào ngày chuyến đi:
                    </div>
                    <div class="d-flex gap-1">
                      <select name="trip_day_id" class="form-select form-select-sm" style="border-radius: 3px; font-size: 0.76rem;" required>
                        <?php foreach ($userTripDays as $td): ?>
                          <option value="<?= (int)$td['trip_day_id'] ?>">
                            <?= e(mb_strimwidth($td['trip_title'], 0, 20, '...')) ?> - Ngày <?= (int)$td['day_number'] ?> (<?= format_date_vn($td['trip_date']) ?>)
                          </option>
                        <?php endforeach; ?>
                      </select>
                      <button type="submit" class="btn-cin-ink py-1 px-2">Chèn</button>
                    </div>
                  </form>
                <?php endif; ?>

                <div class="field-slip-footer">
                  <div>
                    <span class="small d-block" style="color: var(--cin-ink-muted); font-size: 0.7rem;">Giá tham khảo</span>
                    <span class="price-mono">
                      <?= format_vnd($loc['ref_price'], true) ?><?= (float)$loc['ref_price'] > 0 ? '<small class="text-muted fw-normal">/' . e($loc['price_unit']) . '</small>' : '' ?>
                    </span>
                  </div>
                  <div class="d-flex gap-2">
                    <a href="<?= e(google_maps_view_url($loc['latitude'], $loc['longitude'], $loc['google_maps_url'])) ?>"
                       target="_blank" rel="noopener" class="btn-cin-outline px-2" title="Xem trên Google Maps">
                      <i class="bi bi-geo-alt-fill text-danger"></i>
                    </a>
                    <a href="location_detail.php?id=<?= (int)$loc['id'] ?>" class="btn-cin-ink">
                      Hồ sơ &rarr;
                    </a>
                  </div>
                </div>
              </div>
            </div>
          </article>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
