<?php
require_once __DIR__ . '/includes/helpers.php';

$pdo = get_db();
$token = trim($_GET['token'] ?? '');

// UC05.1: Nếu truy cập share.php không truyền token -> Hiển thị danh sách các chuyến đi đang được bật chia sẻ công khai (cho cả Khách chưa đăng nhập)
if ($token === '') {
    $sharedTrips = $pdo->query('
        SELECT
            t.*,
            u.full_name AS owner_name,
            u.avatar_url AS owner_avatar,
            it.title AS source_template_title,
            (SELECT COUNT(*) FROM trip_days WHERE trip_id = t.id) AS days_count
        FROM trips t
        JOIN users u ON u.id = t.user_id
        LEFT JOIN itinerary_templates it ON it.id = t.source_template_id
        WHERE t.is_shared = 1
        ORDER BY t.updated_at DESC, t.id DESC
    ')->fetchAll();

    $pageTitle = 'Danh sách Chuyến đi được chia sẻ (UC05.1)';
    require_once __DIR__ . '/includes/header.php';
    ?>
    <header class="cin-subhero">
      <img src="https://images.unsplash.com/photo-1559592413-7cec4d0cae2b?w=1800&auto=format&fit=crop&q=82"
           alt="Hành trình cộng đồng chia sẻ" class="cin-subhero-bg">
      <div class="cin-subhero-overlay"></div>
      <div class="container cin-subhero-inner">
        <div class="d-flex flex-wrap justify-content-between align-items-end gap-3">
          <div>
            <span class="cin-eyebrow mb-2" style="color: var(--cin-amber-bright);">
              HỒ SƠ CHUYẾN ĐI CÔNG KHAI · UC05.1 (<?= count($sharedTrips) ?> KẾ HOẠCH)
            </span>
            <h1 class="cin-subhero-title">
              Kế Hoạch Chuyến Đi <span class="sw">Được Chia Sẻ</span> Bởi Cộng Đồng
            </h1>
            <p class="cin-subhero-sub">
              Khách chưa đăng nhập và thành viên có thể tham khảo các kịch bản thực tế đang bật liên kết chỉ đọc (ẩn toàn bộ thông tin nhạy cảm).
            </p>
          </div>
          <a href="trips.php" class="btn-cin-amber">
            <i class="bi bi-luggage"></i>Quản lý chuyến đi của tôi
          </a>
        </div>
      </div>
    </header>

    <main id="mainContent" class="container py-4">
      <?php if (empty($sharedTrips)): ?>
        <div class="cin-empty-state">
          <i class="bi bi-share fs-1" style="color: var(--cin-amber);"></i>
          <h3 class="h4 fw-bold mt-3" style="font-family: var(--font-display);">Hiện chưa có chuyến đi nào bật chia sẻ công khai</h3>
          <a href="templates.php" class="btn-cin-amber mt-2">Xem lịch trình mẫu 12 tháng</a>
        </div>
      <?php else: ?>
        <div class="row g-4">
          <?php foreach ($sharedTrips as $st): ?>
            <div class="col-md-6 col-lg-4">
              <article class="field-slip">
                <div class="field-slip-media" style="height: 205px;">
                  <img src="<?= e($st['cover_image_url']) ?>" alt="<?= e($st['title']) ?>">
                  <div class="field-slip-badges">
                    <span class="slip-tag">
                      <?= (int)$st['days_count'] ?> Ngày · <?= (int)$st['pax_count'] ?> người
                    </span>
                    <span class="slip-tag slip-tag-amber">
                      <i class="bi bi-geo-alt-fill me-1"></i><?= e($st['main_destination']) ?>
                    </span>
                  </div>
                </div>
                <div class="field-slip-body">
                  <div class="d-flex align-items-center gap-2 small mb-2" style="color: var(--cin-ink-muted);">
                    <img src="<?= e($st['owner_avatar'] ?: 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=80') ?>"
                         alt="" class="rounded-circle object-fit-cover" width="24" height="24">
                    <span>Chia sẻ bởi <strong><?= e($st['owner_name']) ?></strong></span>
                  </div>
                  <h3 class="field-slip-title mb-2">
                    <a href="share.php?token=<?= urlencode((string)$st['share_token']) ?>"><?= e($st['title']) ?></a>
                  </h3>
                  <p class="small mb-3" style="color: var(--cin-ink-muted);"><?= e($st['description']) ?></p>
                  <div class="field-slip-footer">
                    <div>
                      <span class="small d-block" style="color: var(--cin-ink-muted); font-size: 0.7rem;">Ngày khởi hành</span>
                      <span class="price-mono"><?= format_date_vn($st['start_date']) ?></span>
                    </div>
                    <a href="share.php?token=<?= urlencode((string)$st['share_token']) ?>" class="btn-cin-ink">
                      Mở kế hoạch &rarr;
                    </a>
                  </div>
                </div>
              </article>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </main>
    <?php
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

// UC05.2 -> UC05.8: Xem chi tiết chuyến đi qua link chia sẻ
$stmt = $pdo->prepare('
    SELECT
        t.*,
        u.full_name AS owner_name,
        u.avatar_url AS owner_avatar,
        it.title AS source_template_title
    FROM trips t
    JOIN users u ON u.id = t.user_id
    LEFT JOIN itinerary_templates it ON it.id = t.source_template_id
    WHERE t.share_token = ? AND t.is_shared = 1
    LIMIT 1
');
$stmt->execute([$token]);
$trip = $stmt->fetch();

$pageTitle = $trip ? 'Kế hoạch chia sẻ: ' . $trip['title'] : 'Liên kết chia sẻ không khả dụng';
require_once __DIR__ . '/includes/header.php';

if (!$trip):
?>
<main id="mainContent" class="container py-5">
  <div class="cin-empty-state mx-auto" style="max-width: 620px;">
    <i class="bi bi-shield-lock-fill fs-1" style="color: var(--cin-amber);"></i>
    <h3 class="h4 fw-bold mt-3" style="font-family: var(--font-display);">Chuyến đi này đang ở chế độ riêng tư</h3>
    <p class="small text-muted mb-4">
      Liên kết chia sẻ không tồn tại hoặc chủ sở hữu chuyến đi đã tắt quyền chia sẻ công khai.
    </p>
    <div class="d-flex justify-content-center gap-2">
      <a href="share.php" class="btn-cin-ink">Xem các chuyến đi công khai khác</a>
      <a href="templates.php" class="btn-cin-outline">Xem lịch trình mẫu</a>
    </div>
  </div>
</main>
<?php
require_once __DIR__ . '/includes/footer.php';
exit;
endif;

// Lấy lịch trình theo ngày & hoạt động (Ẩn ghi chú riêng tư a.notes)
$stmtDays = $pdo->prepare('SELECT * FROM trip_days WHERE trip_id = ? ORDER BY day_number ASC');
$stmtDays->execute([$trip['id']]);
$days = $stmtDays->fetchAll();

$stmtActs = $pdo->prepare('
    SELECT
        a.id, a.trip_day_id, a.location_id, a.title, a.activity_type,
        a.start_time, a.end_time, a.description, a.estimated_cost,
        l.name AS location_name, l.location_type, l.latitude, l.longitude, l.google_maps_url
    FROM activities a
    LEFT JOIN locations l ON l.id = a.location_id
    WHERE a.trip_day_id = ?
    ORDER BY a.sort_order ASC, a.start_time ASC, a.id ASC
');

$daysWithActs = [];
$mapPoints = [];
foreach ($days as $day) {
    $stmtActs->execute([$day['id']]);
    $acts = $stmtActs->fetchAll();
    $day['activities'] = $acts;
    $daysWithActs[] = $day;

    foreach ($acts as $act) {
        if (!empty($act['latitude']) && !empty($act['longitude'])) {
            $mapPoints[] = [
                'day_number'      => (int)$day['day_number'],
                'location_id'     => (int)$act['location_id'],
                'title'           => $act['title'],
                'subtitle'        => 'Ngày ' . (int)$day['day_number'] . ' · ' . $act['start_time'],
                'time'            => $act['start_time'],
                'type'            => $act['location_type'] ?: $act['activity_type'],
                'lat'             => (float)$act['latitude'],
                'lng'             => (float)$act['longitude'],
                'google_maps_url' => google_maps_view_url($act['latitude'], $act['longitude'], $act['google_maps_url']),
            ];
        }
    }
}

// Không hiển thị booking_code, contact_info, notes riêng tư (Mục 4.12)
$stmtAcc = $pdo->prepare('
    SELECT stay_name, address, checkin_date, checkin_time, checkout_date, checkout_time, room_count, estimated_cost, booking_status
    FROM accommodations WHERE trip_id = ? ORDER BY checkin_date ASC
');
$stmtAcc->execute([$trip['id']]);
$sharedAccommodations = $stmtAcc->fetchAll();

$stmtTr = $pdo->prepare('
    SELECT transport_type, departure_point, arrival_point, departure_datetime, arrival_datetime, estimated_cost
    FROM transports WHERE trip_id = ? ORDER BY id ASC
');
$stmtTr->execute([$trip['id']]);
$sharedTransports = $stmtTr->fetchAll();

$budgetSummary = calculate_trip_budget_summary($pdo, (int)$trip['id'], (float)$trip['total_budget']);
?>

<main id="mainContent" class="container py-4">
  <div class="cin-season-callout mb-4">
    <div>
      <span class="cin-eyebrow" style="color: var(--cin-amber-bright);">CHẾ ĐỘ CHỈ ĐỌC · BẢO MẬT THÔNG TIN RIÊNG TƯ</span>
      <div class="small mt-1" style="color: rgba(244,239,228,0.85);">
        Được chia sẻ bởi <strong><?= e($trip['owner_name']) ?></strong>. Mã đặt chỗ, thông tin liên hệ cá nhân và ghi chú riêng tư đã được ẩn tự động theo Mục 4.12 Đặc tả.
      </div>
    </div>
    <div class="d-flex flex-wrap gap-2">
      <a href="share.php" class="btn-cin-ghost">Danh sách chuyến đi chia sẻ</a>
      <?php if (!empty($trip['source_template_id'])): ?>
        <a href="template_detail.php?id=<?= (int)$trip['source_template_id'] ?>" class="btn-cin-amber">
          <i class="bi bi-magic"></i>Xem kịch bản mẫu gốc
        </a>
      <?php endif; ?>
    </div>
  </div>

  <!-- CINEMATIC DOSSIER HERO CHUYẾN ĐI CHIA SẺ -->
  <section class="cin-dossier-hero">
    <div class="cin-dossier-hero-main">
      <div class="row g-4 align-items-center">
        <div class="col-lg-5">
          <div class="cin-dossier-cover">
            <img src="<?= e($trip['cover_image_url']) ?>" alt="<?= e($trip['title']) ?>">
          </div>
        </div>
        <div class="col-lg-7">
          <div class="d-flex flex-wrap gap-2 mb-2">
            <span class="slip-tag slip-tag-amber">Kế hoạch chia sẻ · <?= count($daysWithActs) ?> Ngày</span>
            <span class="slip-tag"><i class="bi bi-person-check me-1"></i>Lữ khách: <?= e($trip['owner_name']) ?></span>
          </div>
          <h1 class="cin-display-h2 text-white mb-2"><?= e($trip['title']) ?></h1>
          <p class="mb-0" style="color: rgba(244,239,228,0.82); font-size: 0.94rem; line-height: 1.65;">
            <?= e($trip['description']) ?>
          </p>
        </div>
      </div>
    </div>
    <div class="cin-dossier-meta-strip">
      <div class="cin-dossier-meta-cell">
        <span class="meta-label">Điểm đến chính</span>
        <span class="meta-val"><?= e($trip['main_destination']) ?></span>
      </div>
      <div class="cin-dossier-meta-cell">
        <span class="meta-label">Khởi hành từ</span>
        <span class="meta-val"><?= e($trip['departure_point']) ?></span>
      </div>
      <div class="cin-dossier-meta-cell">
        <span class="meta-label">Thời gian</span>
        <span class="meta-val"><?= format_date_vn($trip['start_date']) ?> &rarr; <?= format_date_vn($trip['end_date']) ?></span>
      </div>
      <div class="cin-dossier-meta-cell">
        <span class="meta-label">Quy mô &amp; Tổng dự toán</span>
        <span class="meta-val" style="color: var(--cin-amber-bright);"><?= (int)$trip['pax_count'] ?> người · <?= format_vnd($budgetSummary['total_estimated']) ?></span>
      </div>
    </div>
  </section>

  <div class="row g-4">
    <div class="col-lg-7">
      <div class="d-flex justify-content-between align-items-end mb-3">
        <div>
          <span class="cin-eyebrow" style="color: var(--cin-jade);">Kịch bản di chuyển</span>
          <h2 class="h4 fw-bold mb-0" style="font-family: var(--font-display);">Lịch trình chi tiết theo ngày</h2>
        </div>
      </div>

      <?php foreach ($daysWithActs as $day): ?>
        <section class="cin-day-dossier">
          <div class="cin-day-header">
            <div class="d-flex align-items-center gap-2">
              <span class="cin-day-badge">NGÀY 0<?= (int)$day['day_number'] ?></span>
              <span class="font-monospace small text-muted"><?= format_date_vn($day['trip_date']) ?></span>
              <h3 class="h6 fw-bold mb-0" style="font-family: var(--font-display); font-size: 1.12rem;"><?= e($day['title']) ?></h3>
            </div>
            <span class="font-monospace small text-muted"><?= count($day['activities']) ?> HOẠT ĐỘNG</span>
          </div>

          <div class="cin-day-body">
            <?php foreach ($day['activities'] as $act): ?>
              <?php $actMeta = activity_type_meta($act['activity_type']); ?>
              <article class="cin-activity-slip">
                <div class="d-flex justify-content-between align-items-center mb-1">
                  <div class="d-flex align-items-center gap-2">
                    <span class="cin-act-time"><?= e($act['start_time']) ?> — <?= e($act['end_time']) ?></span>
                    <span class="status-mark status-info">
                      <i class="bi <?= e($actMeta['icon']) ?>"></i><?= e($actMeta['label']) ?>
                    </span>
                  </div>
                  <span class="price-mono"><?= format_vnd($act['estimated_cost'], true) ?></span>
                </div>
                <h4 class="h6 fw-bold mt-1 mb-1" style="font-family: var(--font-display); font-size: 1.06rem;"><?= e($act['title']) ?></h4>
                <?php if (!empty($act['description'])): ?>
                  <p class="small mb-2" style="color: var(--cin-ink-muted);"><?= e($act['description']) ?></p>
                <?php endif; ?>
                <?php if (!empty($act['location_id'])): ?>
                  <div class="pt-2 border-top d-flex justify-content-between align-items-center small">
                    <a href="location_detail.php?id=<?= (int)$act['location_id'] ?>" class="fw-bold" style="color: var(--cin-jade);">
                      <i class="bi bi-geo-alt-fill me-1"></i><?= e($act['location_name']) ?>
                    </a>
                    <a href="<?= e(google_maps_view_url($act['latitude'], $act['longitude'], $act['google_maps_url'])) ?>"
                       target="_blank" rel="noopener" class="btn-cin-outline py-1 px-2">
                      <i class="bi bi-geo-alt-fill text-danger"></i>Google Maps
                    </a>
                  </div>
                <?php endif; ?>
              </article>
            <?php endforeach; ?>
          </div>
        </section>
      <?php endforeach; ?>
    </div>

    <div class="col-lg-5">
      <div class="cin-map-frame mb-4">
        <div class="cin-map-header">
          <span><i class="bi bi-map-fill text-warning me-1"></i>BẢN ĐỒ ĐIỂM DỪNG CHUYẾN ĐI</span>
          <div class="d-flex gap-1">
            <button type="button" class="btn-cin-amber py-1 px-2" style="font-size: 0.68rem;" onclick="BacDuMap.renderMarkers('all', 'all')">Tất cả</button>
            <?php foreach ($daysWithActs as $dBtn): ?>
              <button type="button" class="btn-cin-ghost py-1 px-2" style="font-size: 0.68rem;" onclick="BacDuMap.renderMarkers('<?= (int)$dBtn['day_number'] ?>', 'all')">N<?= (int)$dBtn['day_number'] ?></button>
            <?php endforeach; ?>
          </div>
        </div>
        <div id="sharedTripMap" class="leaflet-map-box" style="height: 330px; border-radius: 0; border: 0;"></div>
      </div>

      <section class="cin-day-dossier">
        <div class="cin-day-header">
          <span class="fw-bold"><i class="bi bi-house-heart-fill text-primary me-1"></i>Lưu trú &amp; Phương tiện</span>
        </div>
        <div class="cin-day-body">
          <?php if (empty($sharedAccommodations) && empty($sharedTransports)): ?>
            <p class="small text-muted mb-0">Chưa cập nhật thông tin lưu trú/phương tiện công khai.</p>
          <?php endif; ?>
          <?php foreach ($sharedAccommodations as $acc): ?>
            <div class="cin-activity-slip">
              <div class="fw-bold"><i class="bi bi-house-door text-primary me-1"></i><?= e($acc['stay_name']) ?></div>
              <div class="small text-muted"><?= e($acc['address']) ?></div>
              <div class="font-monospace small mt-1">Check-in: <?= format_date_vn($acc['checkin_date']) ?> &rarr; Check-out: <?= format_date_vn($acc['checkout_date']) ?> (<?= (int)$acc['room_count'] ?> phòng)</div>
            </div>
          <?php endforeach; ?>
          <?php foreach ($sharedTransports as $tr): ?>
            <div class="cin-activity-slip">
              <div class="fw-bold"><i class="bi bi-bus-front text-info me-1"></i><?= e($tr['transport_type']) ?></div>
              <div class="small"><?= e($tr['departure_point']) ?> &rarr; <?= e($tr['arrival_point']) ?></div>
            </div>
          <?php endforeach; ?>
        </div>
      </section>

      <section class="cin-day-dossier mb-0">
        <div class="cin-day-header">
          <span class="fw-bold"><i class="bi bi-wallet2 text-warning me-1"></i>Sổ cái tổng hợp dự toán theo nhóm</span>
        </div>
        <div class="cin-day-body p-0">
          <table class="app-table">
            <thead>
              <tr>
                <th>Hạng mục</th>
                <th class="num-col">Dự toán</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($budgetSummary['by_category'] as $cat): ?>
                <?php if ($cat['sum_estimated'] > 0): ?>
                  <tr>
                    <td><i class="bi <?= e($cat['icon']) ?> me-1" style="color:<?= e($cat['color']) ?>"></i><?= e($cat['label']) ?></td>
                    <td class="num-col fw-bold"><?= format_vnd($cat['sum_estimated']) ?></td>
                  </tr>
                <?php endif; ?>
              <?php endforeach; ?>
              <tr style="background: rgba(217,119,6,0.08);">
                <td class="fw-bold">Tổng dự toán chuyến đi</td>
                <td class="num-col fw-bold" style="color: var(--cin-jade);"><?= format_vnd($budgetSummary['total_estimated']) ?></td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </div>
  </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const mapPoints = <?= json_encode($mapPoints, JSON_UNESCAPED_UNICODE) ?>;
  BacDuMap.init('sharedTripMap', mapPoints, [20.84, 104.64], 10);
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
