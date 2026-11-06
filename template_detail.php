<?php
require_once __DIR__ . '/includes/helpers.php';

$pdo = get_db();
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$selectedMonth = isset($_GET['month']) ? (int)$_GET['month'] : 0;

$stmt = $pdo->prepare('
    SELECT
        it.*,
        p.name AS province_name,
        p.latitude AS province_lat,
        p.longitude AS province_lng,
        u.full_name AS author_name
    FROM itinerary_templates it
    JOIN provinces p ON p.id = it.province_id
    LEFT JOIN users u ON u.id = it.author_id
    WHERE it.id = ?
    LIMIT 1
');
$stmt->execute([$id]);
$template = $stmt->fetch();

if (!$template || ($template['status'] !== 'published' && !is_admin())) {
    set_flash('warning', 'Không tìm thấy lịch trình mẫu bạn yêu cầu.');
    header('Location: templates.php');
    exit;
}

// Xử lý POST khi người dùng bấm "Dùng lịch trình này" (Mục 4.3 Đặc tả)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'clone_template') {
    $user = require_login('template_detail.php?id=' . $id);
    if (!verify_csrf()) {
        set_flash('danger', 'Phiên làm việc không hợp lệ.');
        header('Location: template_detail.php?id=' . $id);
        exit;
    }

    $tripTitle = trim($_POST['trip_title'] ?? '');
    $startDate = trim($_POST['start_date'] ?? date('Y-m-d', strtotime('+7 days')));
    $paxCount = max(1, (int)($_POST['pax_count'] ?? 2));
    $departurePoint = trim($_POST['departure_point'] ?? 'Hà Nội');
    $plannedBudget = (float)($_POST['planned_budget'] ?? 0);

    if ($tripTitle === '') {
        $tripTitle = 'Chuyến đi ' . $template['main_destination'] . ' (' . $template['duration_days'] . 'N' . $template['duration_nights'] . 'Đ)';
    }

    try {
        $newTripId = clone_template_to_trip($pdo, (int)$user['id'], $id, $tripTitle, $startDate, $paxCount, $departurePoint, $plannedBudget);
        set_flash('success', 'Đã khởi tạo chuyến đi cá nhân độc lập từ lịch trình mẫu! Bạn có thể tùy chỉnh hoạt động, quán ăn, nơi ở và ngân sách ngay dưới đây.');
        header('Location: trip_detail.php?id=' . $newTripId);
        exit;
    } catch (Throwable $err) {
        set_flash('danger', 'Lỗi khi sao chép lịch trình mẫu: ' . e($err->getMessage()));
        header('Location: template_detail.php?id=' . $id);
        exit;
    }
}

// Tăng lượt xem lịch trình mẫu (Product Insights - Thành viên 5)
$pdo->prepare('UPDATE itinerary_templates SET views_count = views_count + 1 WHERE id = ?')->execute([$id]);
$template['views_count'] = (int)$template['views_count'] + 1;

// Các tháng phù hợp của mẫu
$stmtMonths = $pdo->prepare('SELECT * FROM template_months WHERE template_id = ? ORDER BY month_number ASC');
$stmtMonths->execute([$id]);
$templateMonths = $stmtMonths->fetchAll();

// Các ngày và hoạt động trong mẫu
$stmtDays = $pdo->prepare('SELECT * FROM template_days WHERE template_id = ? ORDER BY day_number ASC');
$stmtDays->execute([$id]);
$days = $stmtDays->fetchAll();

$stmtActs = $pdo->prepare("
    SELECT
        ta.*,
        l.name AS location_name,
        l.location_type,
        l.thumbnail_url AS location_thumb,
        l.latitude,
        l.longitude,
        l.google_maps_url,
        l.ref_price AS location_ref_price,
        l.price_unit AS location_price_unit,
        (SELECT COUNT(*) FROM experience_posts ep WHERE ep.location_id = l.id AND ep.status = 'approved') AS loc_reviews_count
    FROM template_activities ta
    LEFT JOIN locations l ON l.id = ta.location_id
    WHERE ta.template_day_id = ?
    ORDER BY ta.sort_order ASC, ta.id ASC
");

$daysWithActivities = [];
$mapPoints = [];
foreach ($days as $day) {
    $stmtActs->execute([$day['id']]);
    $acts = $stmtActs->fetchAll();
    $day['activities'] = $acts;
    $daysWithActivities[] = $day;

    foreach ($acts as $act) {
        if (!empty($act['latitude']) && !empty($act['longitude'])) {
            $mapPoints[] = [
                'day_number'      => (int)$day['day_number'],
                'location_id'     => (int)$act['location_id'],
                'title'           => $act['title'],
                'subtitle'        => $act['location_name'] . ' · ' . $act['start_time'] . ' - ' . $act['end_time'],
                'time'            => $act['start_time'],
                'type'            => $act['location_type'] ?: $act['activity_type'],
                'lat'             => (float)$act['latitude'],
                'lng'             => (float)$act['longitude'],
                'google_maps_url' => google_maps_view_url($act['latitude'], $act['longitude'], $act['google_maps_url']),
            ];
        }
    }
}

// Gợi ý lưu trú của mẫu
$stmtAccs = $pdo->prepare('
    SELECT ta.*, l.name AS loc_name, l.address AS loc_address, l.room_price_info, l.latitude, l.longitude, l.google_maps_url
    FROM template_accommodations ta
    LEFT JOIN locations l ON l.id = ta.location_id
    WHERE ta.template_id = ?
    ORDER BY ta.night_number ASC
');
$stmtAccs->execute([$id]);
$templateAccommodations = $stmtAccs->fetchAll();

// Các khoản dự toán của mẫu
$stmtExps = $pdo->prepare('SELECT * FROM template_expenses WHERE template_id = ? ORDER BY id ASC');
$stmtExps->execute([$id]);
$templateExpenses = $stmtExps->fetchAll();

$totalTemplateBaseCost = array_sum(array_map(fn($e) => (float)$e['estimated_amount'], $templateExpenses));

$pageTitle = $template['title'];
require_once __DIR__ . '/includes/header.php';
?>

<section class="cin-subhero py-3 mb-4">
  <div class="container position-relative" style="z-index: 2;">
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb small mb-0 font-monospace">
        <li class="breadcrumb-item"><a href="index.php" class="text-warning">TRANG CHỦ</a></li>
        <li class="breadcrumb-item"><a href="templates.php" class="text-warning">LỊCH TRÌNH 12 THÁNG</a></li>
        <li class="breadcrumb-item active text-white-50" aria-current="page">HỒ SƠ MẪU #<?= str_pad((string)$template['id'], 2, '0', STR_PAD_LEFT) ?></li>
      </ol>
    </nav>
  </div>
</section>

<main class="container pb-5" id="mainContent">
  <!-- TỔNG QUAN HỒ SƠ LỊCH TRÌNH MẪU (EXPEDITION DOSSIER HERO - UC03.4) -->
  <div class="cin-dossier-hero mb-4">
    <div class="row g-0">
      <div class="col-lg-5 position-relative" style="min-height: 360px; background: var(--cin-jade-deep);">
        <img src="<?= e($template['thumbnail_url']) ?>" alt="<?= e($template['title']) ?>" class="w-100 h-100 object-fit-cover">
        <div class="field-slip-badges">
          <span class="slip-tag">
            <i class="bi bi-clock me-1"></i><?= (int)$template['duration_days'] ?> NGÀY <?= (int)$template['duration_nights'] ?> ĐÊM
          </span>
          <span class="slip-tag slip-tag-jade">
            <i class="bi bi-geo-alt-fill me-1"></i><?= e($template['main_destination']) ?>
          </span>
        </div>
      </div>

      <div class="col-lg-7 p-4 p-lg-5 d-flex flex-column justify-content-between">
        <div>
          <div class="d-flex flex-wrap align-items-center gap-1 mb-2">
            <span class="cin-eyebrow text-dark me-2">Tháng khởi hành gợi ý:</span>
            <?php foreach ($templateMonths as $tm): ?>
              <a href="templates.php?month=<?= (int)$tm['month_number'] ?>"
                 class="slip-tag <?= (int)$tm['month_number'] === $selectedMonth ? 'bg-warning text-dark border-warning' : 'slip-tag-jade' ?>">
                THÁNG <?= (int)$tm['month_number'] ?>
              </a>
            <?php endforeach; ?>
          </div>

          <h1 class="cin-display-h2 mb-2" style="color: var(--cin-ink);"><?= e($template['title']) ?></h1>
          <p class="text-secondary mb-3"><?= e($template['summary']) ?></p>

          <div class="journal-tip-box mb-3">
            <strong><i class="bi bi-sun-fill text-warning me-1"></i>Vì sao nên đi trong thời điểm được gợi ý:</strong><br>
            <?= e($template['seasonal_reason']) ?>
          </div>

          <div class="row g-2 small p-3 rounded-1 border mb-3" style="background: var(--cin-paper-warm);">
            <div class="col-sm-6">
              <i class="bi bi-send-fill text-success me-1"></i><strong>Xuất phát giả định:</strong> <?= e($template['assumed_departure']) ?>
            </div>
            <div class="col-sm-6">
              <i class="bi bi-truck-front-fill text-primary me-1"></i><strong>Phương tiện gợi ý:</strong> <?= e($template['suggested_transport']) ?>
            </div>
            <div class="col-sm-6">
              <i class="bi bi-person-badge text-secondary me-1"></i><strong>Biên soạn:</strong> <?= e($template['author_name'] ?: 'Ban Biên Tập Bắc Du') ?>
            </div>
            <div class="col-sm-6 font-monospace" style="font-size: 0.78rem;">
              <i class="bi bi-calendar-check text-secondary me-1"></i><strong>Cập nhật giá:</strong> <?= format_date_vn($template['updated_date']) ?>
            </div>
          </div>
        </div>

        <div class="pt-3 border-top d-flex flex-wrap justify-content-between align-items-center gap-3">
          <div>
            <div class="small text-muted font-monospace">DỰ TOÁN THAM KHẢO (CƠ SỞ <?= (int)$template['base_pax_count'] ?> NGƯỜI)</div>
            <div class="price-mono fs-3 lh-1">
              <?= format_vnd($template['ref_budget_per_person']) ?><small class="fs-6 text-muted fw-normal">/người</small>
            </div>
          </div>

          <button type="button" class="btn-cin-amber px-4 py-2" data-bs-toggle="modal" data-bs-target="#useTemplateModal">
            <i class="bi bi-compass-fill"></i> Dùng lịch trình này
          </button>
        </div>
      </div>
    </div>
  </div>

  <?php if (!empty($template['essential_notes'])): ?>
    <div class="cin-season-callout mb-4">
      <div>
        <div class="fw-bold text-warning mb-1 font-monospace"><i class="bi bi-exclamation-diamond-fill me-2"></i>LƯU Ý THỰC ĐỊA TỪ BAN BIÊN TẬP:</div>
        <div class="small"><?= nl2br(e($template['essential_notes'])) ?></div>
      </div>
    </div>
  <?php endif; ?>

  <div class="row g-4">
    <!-- CỘT TRÁI: KẾ HOẠCH CHI TIẾT TỪNG NGÀY (UC03.5) -->
    <div class="col-lg-7">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
          <span class="cin-eyebrow text-dark">Hồ sơ từng chặng</span>
          <h3 class="cin-display-h2 fs-3 mb-0">Lộ trình chi tiết theo từng ngày</h3>
        </div>
        <span class="slip-tag"><?= count($daysWithActivities) ?> NGÀY</span>
      </div>

      <?php foreach ($daysWithActivities as $day): ?>
        <div class="cin-day-dossier">
          <div class="cin-day-header">
            <div>
              <span class="cin-day-stamp">NGÀY THỨ <?= str_pad((string)$day['day_number'], 2, '0', STR_PAD_LEFT) ?></span>
              <h4 class="field-slip-title mb-1"><?= e($day['title']) ?></h4>
              <?php if (!empty($day['summary'])): ?>
                <div class="small text-muted"><?= e($day['summary']) ?></div>
              <?php endif; ?>
            </div>
            <?php if (!empty($day['transport_note'])): ?>
              <span class="slip-tag slip-tag-jade">
                <i class="bi bi-signpost-split me-1"></i><?= e($day['transport_note']) ?>
              </span>
            <?php endif; ?>
          </div>

          <div class="timeline-container">
            <?php foreach ($day['activities'] as $act): ?>
              <?php
                $actMeta = activity_type_meta($act['activity_type']);
                $hasLocation = !empty($act['location_id']);
              ?>
              <div class="timeline-item <?= e($actMeta['class']) ?>">
                <div class="timeline-dot"><i class="bi <?= e($actMeta['icon']) ?>"></i></div>
                <div class="cin-activity-slip">
                  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-1">
                    <div class="d-flex flex-wrap align-items-center gap-2">
                      <span class="cin-act-time">
                        <i class="bi bi-clock me-1"></i><?= e($act['start_time']) ?> — <?= e($act['end_time']) ?>
                      </span>
                      <span class="status-mark status-info">
                        <i class="bi <?= e($actMeta['icon']) ?>"></i><?= e($actMeta['label']) ?>
                      </span>
                    </div>
                    <span class="price-mono small">
                      <?= format_vnd($act['estimated_cost'], true) ?><?= (float)$act['estimated_cost'] > 0 ? '/người' : '' ?>
                    </span>
                  </div>

                  <h5 class="fw-bold font-sans-title mt-2 mb-1 fs-6"><?= e($act['title']) ?></h5>
                  <p class="small text-secondary mb-2"><?= e($act['description']) ?></p>

                  <div class="row g-2 small text-muted mb-2">
                    <?php if (!empty($act['transport_note'])): ?>
                      <div class="col-md-6">
                        <i class="bi bi-truck text-primary me-1"></i><strong>Di chuyển:</strong> <?= e($act['transport_note']) ?>
                      </div>
                    <?php endif; ?>
                    <?php if (!empty($act['important_note'])): ?>
                      <div class="col-md-6 text-warning-emphasis">
                        <i class="bi bi-exclamation-circle-fill text-warning me-1"></i><strong>Lưu ý:</strong> <?= e($act['important_note']) ?>
                      </div>
                    <?php endif; ?>
                  </div>

                  <?php if ($hasLocation): ?>
                    <div class="mt-2 pt-2 border-top d-flex flex-wrap justify-content-between align-items-center gap-2">
                      <div class="d-flex align-items-center gap-2">
                        <?php if (!empty($act['location_thumb'])): ?>
                          <img src="<?= e($act['location_thumb']) ?>" alt="" class="rounded-1 object-fit-cover border" width="42" height="42">
                        <?php endif; ?>
                        <div>
                          <a href="location_detail.php?id=<?= (int)$act['location_id'] ?>" class="fw-bold small text-dark d-block">
                            <i class="bi bi-geo-alt-fill text-success me-1"></i><?= e($act['location_name']) ?>
                          </a>
                          <span class="text-muted font-monospace" style="font-size: 0.72rem;">
                            <?= (int)$act['loc_reviews_count'] ?> review thực địa
                          </span>
                        </div>
                      </div>
                      <div class="d-flex flex-wrap gap-1">
                        <a href="location_detail.php?id=<?= (int)$act['location_id'] ?>" class="btn-cin-outline py-1 px-2" style="font-size:0.76rem;">
                          <i class="bi bi-info-circle"></i> Chi tiết
                        </a>
                        <a href="<?= e(google_maps_view_url($act['latitude'], $act['longitude'], $act['google_maps_url'])) ?>"
                           target="_blank" rel="noopener" class="btn-cin-outline py-1 px-2" style="font-size:0.76rem;">
                          <i class="bi bi-geo-alt-fill text-danger"></i> Google Maps
                        </a>
                      </div>
                    </div>
                  <?php endif; ?>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- CỘT PHẢI: BẢN ĐỒ THEO NGÀY + GỢI Ý LƯU TRÚ + BẢNG DỰ TOÁN MẪU -->
    <div class="col-lg-5">
      <!-- BẢN ĐỒ LEAFLET LỌC THEO NGÀY (UC04.4) -->
      <div class="cin-map-frame mb-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
          <div>
            <span class="cin-eyebrow text-dark">Tọa độ tuyến đường</span>
            <h5 class="field-slip-title mb-0">Bản đồ lịch trình theo ngày</h5>
          </div>
          <div class="d-flex flex-wrap gap-1">
            <button type="button" class="btn-cin-ink py-1 px-2" style="font-size:0.75rem;" onclick="BacDuMap.renderMarkers('all', 'all')">Tất cả</button>
            <?php foreach ($daysWithActivities as $dBtn): ?>
              <button type="button" class="btn-cin-outline py-1 px-2" style="font-size:0.75rem;" onclick="BacDuMap.renderMarkers('<?= (int)$dBtn['day_number'] ?>', 'all')">
                N<?= (int)$dBtn['day_number'] ?>
              </button>
            <?php endforeach; ?>
          </div>
        </div>

        <div id="templateItineraryMap" class="leaflet-map-box mb-2" style="height: 350px;"></div>
        <div class="d-flex justify-content-between small font-monospace text-muted pt-1" style="font-size:0.72rem;">
          <span><i class="bi bi-circle-fill me-1" style="color:#0f766e;"></i>THAM QUAN</span>
          <span><i class="bi bi-circle-fill me-1" style="color:#d97706;"></i>ĂN UỐNG</span>
          <span><i class="bi bi-circle-fill me-1" style="color:#4f46e5;"></i>LƯU TRÚ</span>
        </div>
      </div>

      <!-- GỢI Ý LƯU TRÚ THEO ĐÊM (UC03.6) -->
      <div class="cin-day-dossier mb-4">
        <span class="cin-eyebrow text-dark">Nghỉ đêm bản địa</span>
        <h5 class="field-slip-title mb-1">Gợi ý nơi lưu trú trong mẫu</h5>
        <p class="small text-muted mb-3">Thông tin gợi ý phục vụ lập kế hoạch, sẽ được sao chép sang mục Lưu trú khi bạn dùng mẫu.</p>

        <?php if (empty($templateAccommodations)): ?>
          <p class="small text-muted mb-0">Chưa có gợi ý lưu trú riêng biệt.</p>
        <?php else: ?>
          <div class="d-flex flex-column gap-3">
            <?php foreach ($templateAccommodations as $acc): ?>
              <div class="cin-activity-slip">
                <div class="d-flex justify-content-between align-items-center mb-1">
                  <span class="slip-tag">ĐÊM <?= (int)$acc['night_number'] ?></span>
                  <span class="price-mono"><?= format_vnd($acc['base_cost']) ?>/đêm</span>
                </div>
                <h6 class="fw-bold font-sans-title mb-1 mt-2">
                  <?php if (!empty($acc['location_id'])): ?>
                    <a href="location_detail.php?id=<?= (int)$acc['location_id'] ?>" class="text-dark"><?= e($acc['stay_name']) ?></a>
                  <?php else: ?>
                    <?= e($acc['stay_name']) ?>
                  <?php endif; ?>
                </h6>
                <div class="small text-muted mb-2"><?= e($acc['notes']) ?></div>
                <?php if (!empty($acc['location_id'])): ?>
                  <div class="d-flex gap-2">
                    <a href="location_detail.php?id=<?= (int)$acc['location_id'] ?>" class="btn-cin-outline py-1 px-2" style="font-size:0.75rem;">Hồ sơ lưu trú</a>
                    <a href="<?= e(google_maps_view_url($acc['latitude'], $acc['longitude'], $acc['google_maps_url'])) ?>"
                       target="_blank" rel="noopener" class="btn-cin-outline py-1 px-2" style="font-size:0.75rem;">
                      <i class="bi bi-geo-alt-fill text-danger"></i> Bản đồ
                    </a>
                  </div>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <!-- BẢNG DỰ TOÁN THAM KHẢO CỦA MẪU (UC03.7) -->
      <div class="cin-day-dossier">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <div>
            <span class="cin-eyebrow text-dark">Sổ cái dự toán</span>
            <h5 class="field-slip-title mb-0">Bảng chi phí tham khảo</h5>
          </div>
          <span class="slip-tag slip-tag-jade">NHÓM <?= (int)$template['base_pax_count'] ?> NGƯỜI</span>
        </div>
        <p class="small text-muted mb-3">
          Các khoản chi đã liên kết chặt chẽ với hoạt động và lưu trú để không bị cộng trùng khi sao chép sang chuyến đi.
        </p>

        <?php if (!empty($templateExpenses)): ?>
          <div class="table-responsive mb-3">
            <table class="app-table">
              <thead>
                <tr>
                  <th>Hạng mục dự toán</th>
                  <th class="num-col">Số tiền (VNĐ)</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($templateExpenses as $te): ?>
                  <?php $catMeta = expense_category_meta($te['expense_category']); ?>
                  <tr>
                    <td>
                      <div class="fw-semibold"><i class="bi <?= e($catMeta['icon']) ?> me-1" style="color:<?= e($catMeta['color']) ?>"></i><?= e($te['title']) ?></div>
                      <div class="text-muted" style="font-size: 0.72rem;"><?= e($te['notes']) ?></div>
                    </td>
                    <td class="num-col fw-bold"><?= format_vnd($te['estimated_amount']) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
              <tfoot>
                <tr class="border-top">
                  <th class="pt-2">Tổng dự toán nhóm (<?= (int)$template['base_pax_count'] ?> người)</th>
                  <th class="num-col pt-2 text-success fs-6"><?= format_vnd($totalTemplateBaseCost) ?></th>
                </tr>
                <tr>
                  <th>Trung bình / 1 người</th>
                  <th class="num-col text-warning-emphasis fw-bold"><?= format_vnd($template['ref_budget_per_person']) ?></th>
                </tr>
              </tfoot>
            </table>
          </div>
        <?php endif; ?>

        <button type="button" class="btn-cin-amber w-100 py-2" data-bs-toggle="modal" data-bs-target="#useTemplateModal">
          <i class="bi bi-compass-fill"></i> Dùng lịch trình này làm kế hoạch của tôi
        </button>
      </div>
    </div>
  </div>
</main>

<!-- MODAL "DÙNG LỊCH TRÌNH NÀY" (Mục 4.3 Đặc tả) -->
<div class="modal fade" id="useTemplateModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <span class="cin-eyebrow" style="color: var(--cin-amber-bright); font-size: 0.65rem;">SAO CHÉP THÀNH CHUYẾN ĐI ĐỘC LẬP · UC07.2</span>
          <h5 class="modal-title mb-0">Sử dụng mẫu: <?= e($template['title']) ?></h5>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <?php if (!is_logged_in()): ?>
          <div class="cin-empty-state py-4">
            <i class="bi bi-person-lock fs-2 text-success"></i>
            <p class="small text-muted mt-2 mb-3">
              Vui lòng đăng nhập để hệ thống tạo chuyến đi cá nhân từ lịch trình mẫu này và lưu vào tài khoản của bạn.
            </p>
            <a href="auth.php?tab=login&redirect=<?= urlencode('template_detail.php?id=' . (int)$template['id']) ?>" class="btn-cin-amber w-100 justify-content-center">
              Đăng nhập để sử dụng lịch trình
            </a>
          </div>
        <?php else: ?>
          <form action="template_detail.php?id=<?= (int)$template['id'] ?>" method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="clone_template">

            <div class="mb-3">
              <label class="form-label">1. Tên chuyến đi của bạn</label>
              <input type="text" name="trip_title" class="form-control"
                     value="Chuyến đi <?= e($template['main_destination']) ?> <?= (int)$template['duration_days'] ?>N<?= (int)$template['duration_nights'] ?>Đ" required>
            </div>

            <div class="row g-3 mb-3">
              <div class="col-sm-6">
                <label class="form-label">2. Ngày khởi hành</label>
                <input type="date" name="start_date" class="form-control font-monospace" value="<?= date('Y-m-d', strtotime('+7 days')) ?>" required>
                <div class="form-text" style="font-size:0.72rem;">Hệ thống tự tính <?= (int)$template['duration_days'] ?> ngày cụ thể.</div>
              </div>
              <div class="col-sm-6">
                <label class="form-label">3. Số người tham gia</label>
                <input type="number" name="pax_count" id="clone_pax_count" class="form-control font-monospace"
                       min="1" max="50" value="<?= (int)$template['base_pax_count'] ?>"
                       data-ref-per-person="<?= (float)$template['ref_budget_per_person'] ?>" required>
              </div>
            </div>

            <div class="row g-3 mb-3">
              <div class="col-sm-6">
                <label class="form-label">4. Điểm xuất phát</label>
                <input type="text" name="departure_point" class="form-control" value="<?= e(current_user()['hometown'] ?: $template['assumed_departure']) ?>" required>
              </div>
              <div class="col-sm-6">
                <label class="form-label">5. Tổng ngân sách dự kiến (VNĐ)</label>
                <input type="number" name="planned_budget" id="clone_planned_budget" class="form-control font-monospace"
                       step="50000" value="<?= (float)$template['ref_budget_per_person'] * (int)$template['base_pax_count'] ?>" required>
              </div>
            </div>

            <div class="field-spec-box mb-3">
              <i class="bi bi-check2-circle text-success me-1"></i>
              Chuyến đi cá nhân sau khi tạo sẽ <strong>độc lập hoàn toàn</strong> với lịch trình mẫu. Bạn có thể tự do đổi quán ăn, thêm/xóa hoạt động, nơi lưu trú và điều chỉnh dự toán.
            </div>

            <button type="submit" class="btn-cin-amber w-100 justify-content-center py-2">
              <i class="bi bi-rocket-takeoff-fill"></i>Tạo chuyến đi cá nhân ngay
            </button>
          </form>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php
$defaultLat = !empty($mapPoints) ? $mapPoints[0]['lat'] : (float)($template['province_lat'] ?: 20.8448);
$defaultLng = !empty($mapPoints) ? $mapPoints[0]['lng'] : (float)($template['province_lng'] ?: 104.6391);
?>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const mapPoints = <?= json_encode($mapPoints, JSON_UNESCAPED_UNICODE) ?>;
  BacDuMap.init('templateItineraryMap', mapPoints, [<?= $defaultLat ?>, <?= $defaultLng ?>], 11);

  const paxInput = document.getElementById('clone_pax_count');
  const budgetInput = document.getElementById('clone_planned_budget');
  if (paxInput && budgetInput) {
    paxInput.addEventListener('input', () => {
      const pax = Math.max(1, parseInt(paxInput.value || '1', 10));
      const refPerPerson = parseFloat(paxInput.getAttribute('data-ref-per-person') || '0');
      if (refPerPerson > 0) {
        budgetInput.value = Math.round(pax * refPerPerson);
      }
    });
  }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
