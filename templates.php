<?php
require_once __DIR__ . '/includes/helpers.php';

$pdo = get_db();
$monthsCatalog = get_months_catalog();

$month = isset($_GET['month']) && $_GET['month'] !== '' ? (int)$_GET['month'] : 0;
if ($month < 0 || $month > 12) {
    $month = 0;
}
$provinceId = isset($_GET['province_id']) && $_GET['province_id'] !== '' ? (int)$_GET['province_id'] : 0;
$duration = isset($_GET['duration']) && $_GET['duration'] !== '' ? (int)$_GET['duration'] : 0;
$maxBudget = isset($_GET['max_budget']) && $_GET['max_budget'] !== '' ? (float)$_GET['max_budget'] : 0;

$provinces = $pdo->query('SELECT * FROM provinces WHERE is_active = 1 ORDER BY id ASC')->fetchAll();

$where = ["it.status = 'published'"];
$params = [];
$joinMonthSql = 'LEFT JOIN template_months tm ON tm.template_id = it.id';

if ($month >= 1 && $month <= 12) {
    $joinMonthSql = 'JOIN template_months tm ON tm.template_id = it.id AND tm.month_number = ?';
    $params[] = $month;
}
if ($provinceId > 0) {
    $where[] = 'it.province_id = ?';
    $params[] = $provinceId;
}
if ($duration > 0) {
    $where[] = 'it.duration_days = ?';
    $params[] = $duration;
}
if ($maxBudget > 0) {
    $where[] = 'it.ref_budget_per_person <= ?';
    $params[] = $maxBudget;
}

$whereSql = implode(' AND ', $where);
$stmt = $pdo->prepare("
    SELECT
        it.*,
        p.name AS province_name,
        u.full_name AS author_name,
        MAX(tm.month_reason) AS matched_month_reason
    FROM itinerary_templates it
    {$joinMonthSql}
    JOIN provinces p ON p.id = it.province_id
    LEFT JOIN users u ON u.id = it.author_id
    WHERE {$whereSql}
    GROUP BY it.id
    ORDER BY it.views_count DESC, it.id ASC
");
$stmt->execute($params);
$templates = $stmt->fetchAll();

// Lấy danh sách các tháng phù hợp của từng mẫu để hiển thị badge
$stmtAllMonths = $pdo->query('SELECT template_id, month_number FROM template_months ORDER BY month_number ASC');
$monthsByTemplate = [];
foreach ($stmtAllMonths->fetchAll() as $rm) {
    $monthsByTemplate[(int)$rm['template_id']][] = (int)$rm['month_number'];
}

$pageTitle = $month > 0
    ? 'Lịch trình mẫu ' . $monthsCatalog[$month]['name'] . ' — ' . $monthsCatalog[$month]['tag']
    : 'Khám phá Lịch trình mẫu theo 12 Tháng';
require_once __DIR__ . '/includes/header.php';
?>

<section class="cin-subhero">
  <div class="container position-relative" style="z-index: 2;">
    <div class="d-flex flex-wrap justify-content-between align-items-end gap-3">
      <div>
        <div class="cin-subhero-meta">
          <span><i class="bi bi-compass me-1"></i>SỔ ĐỊA DƯ 12 THÁNG</span>
          <span>·</span>
          <span>20°15'N — 23°22'N · MODULE M2</span>
        </div>
        <h1 class="cin-subhero-title">
          Lịch trình mẫu theo <span class="sw"><?= $month > 0 ? e($monthsCatalog[$month]['name']) : '12 tháng miền Bắc' ?></span>
        </h1>
        <p class="cin-subhero-desc">
          Do Ban biên tập Bắc Du biên soạn kèm điểm tham quan, quán ăn, lưu trú và bảng dự toán chống tính trùng. Chọn tháng khởi hành để mở hồ sơ lộ trình chuẩn mùa.
        </p>
      </div>
      <div>
        <a href="templates.php" class="<?= $month === 0 ? 'btn-cin-amber' : 'btn-cin-ghost' ?>">
          <i class="bi bi-grid-3x3-gap"></i> Hiển thị cả 12 tháng
        </a>
      </div>
    </div>
  </div>
</section>

<main class="container pb-5" id="mainContent">
  <!-- BÀN CHỌN 12 THÁNG (ALMANAC MONTHS STRIP) -->
  <div class="mb-4">
    <div class="d-flex justify-content-between align-items-center mb-2">
      <span class="cin-eyebrow text-dark">La bàn 12 tháng khởi hành (UC03.2)</span>
      <span class="small font-monospace text-muted">Nhấp chọn tháng để lọc lộ trình chuẩn mùa</span>
    </div>
    <div class="cin-months-strip">
      <?php foreach ($monthsCatalog as $mNum => $mData): ?>
        <a href="templates.php?month=<?= $mNum ?>&province_id=<?= $provinceId ?>&duration=<?= $duration ?>"
           class="almanac-month-cell <?= $month === $mNum ? 'active' : '' ?>">
          <div class="m-top">
            <span class="m-num">M<?= str_pad((string)$mNum, 2, '0', STR_PAD_LEFT) ?></span>
            <span class="m-season"><?= e($mData['season']) ?></span>
          </div>
          <div class="m-name"><?= e($mData['name']) ?></div>
          <div class="m-highlight"><?= e($mData['highlight']) ?></div>
        </a>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- BẢNG ĐIỀU PHỐI LỌC LỊCH TRÌNH (DISPATCH FILTER CONSOLE - UC03.3) -->
  <div class="cin-filter-console">
    <form action="templates.php" method="get" class="row g-3 align-items-end">
      <div class="col-md-3">
        <label class="form-label">01 · Tháng khởi hành</label>
        <select name="month" class="form-select">
          <option value="0">Tất cả 12 tháng</option>
          <?php foreach ($monthsCatalog as $mNum => $mData): ?>
            <option value="<?= $mNum ?>" <?= $month === $mNum ? 'selected' : '' ?>>
              <?= e($mData['name']) ?> (<?= e($mData['season']) ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-md-3">
        <label class="form-label">02 · Điểm đến chính</label>
        <select name="province_id" class="form-select">
          <option value="0">Tất cả điểm đến miền Bắc</option>
          <?php foreach ($provinces as $prov): ?>
            <option value="<?= (int)$prov['id'] ?>" <?= $provinceId === (int)$prov['id'] ? 'selected' : '' ?>>
              <?= e($prov['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-md-2">
        <label class="form-label">03 · Thời lượng</label>
        <select name="duration" class="form-select">
          <option value="0">Mọi thời lượng</option>
          <option value="2" <?= $duration === 2 ? 'selected' : '' ?>>2 Ngày 1 Đêm</option>
          <option value="3" <?= $duration === 3 ? 'selected' : '' ?>>3 Ngày 2 Đêm</option>
          <option value="4" <?= $duration === 4 ? 'selected' : '' ?>>4 Ngày 3 Đêm</option>
        </select>
      </div>

      <div class="col-md-2">
        <label class="form-label">04 · Ngân sách / người</label>
        <select name="max_budget" class="form-select">
          <option value="0">Mọi mức ngân sách</option>
          <option value="2000000" <?= (int)$maxBudget === 2000000 ? 'selected' : '' ?>>Dưới 2.000.000đ</option>
          <option value="2600000" <?= (int)$maxBudget === 2600000 ? 'selected' : '' ?>>Dưới 2.600.000đ</option>
          <option value="3500000" <?= (int)$maxBudget === 3500000 ? 'selected' : '' ?>>Dưới 3.500.000đ</option>
        </select>
      </div>

      <div class="col-md-2 d-flex gap-2">
        <button type="submit" class="btn-cin-ink flex-grow-1"><i class="bi bi-funnel"></i> Lọc mẫu</button>
        <a href="templates.php" class="btn-cin-outline px-3" title="Xóa bộ lọc"><i class="bi bi-arrow-counterclockwise"></i></a>
      </div>
    </form>
  </div>

  <?php if ($month > 0): ?>
    <div class="cin-season-callout">
      <div>
        <span class="slip-tag me-2"><?= e($monthsCatalog[$month]['name']) ?> · <?= e($monthsCatalog[$month]['season']) ?></span>
        <span>Chủ đề mùa vụ: <strong class="text-warning"><?= e($monthsCatalog[$month]['tag']) ?></strong> — Tâm điểm: <strong><?= e($monthsCatalog[$month]['highlight']) ?></strong></span>
      </div>
      <span class="font-monospace small text-warning"><?= count($templates) ?> HỒ SƠ LỊCH TRÌNH</span>
    </div>
  <?php endif; ?>

  <!-- DANH SÁCH THẺ FIELD SLIP LỊCH TRÌNH MẪU (UC03.1) -->
  <?php if (empty($templates)): ?>
    <div class="almanac-dossier-box text-center py-5">
      <i class="bi bi-calendar2-x fs-1 text-muted"></i>
      <h4 class="cin-display-h2 fs-3 mt-2">Chưa có hồ sơ lịch trình khớp bộ lọc</h4>
      <p class="text-muted small mb-3">Hãy thử chọn "Tất cả điểm đến" hoặc "Mọi thời lượng" để xem thêm gợi ý trong tháng.</p>
      <a href="templates.php?month=<?= $month ?>" class="btn-cin-amber">Xem tất cả mẫu trong tháng</a>
    </div>
  <?php else: ?>
    <div class="row g-4">
      <?php foreach ($templates as $tpl): ?>
        <?php $tplMonths = $monthsByTemplate[(int)$tpl['id']] ?? []; ?>
        <div class="col-md-6 col-lg-4">
          <article class="field-slip">
            <div class="field-slip-media">
              <img src="<?= e($tpl['thumbnail_url']) ?>" alt="<?= e($tpl['title']) ?>">
              <div class="field-slip-badges">
                <span class="slip-tag">
                  <i class="bi bi-clock me-1"></i><?= (int)$tpl['duration_days'] ?>N<?= (int)$tpl['duration_nights'] ?>Đ
                </span>
                <span class="slip-tag slip-tag-jade">
                  <i class="bi bi-geo-alt-fill me-1"></i><?= e($tpl['main_destination']) ?>
                </span>
              </div>
            </div>

            <div class="field-slip-body">
              <div class="loc-coord-strip">
                <span>MẪU #<?= str_pad((string)$tpl['id'], 2, '0', STR_PAD_LEFT) ?> · <?= e($tpl['province_name']) ?></span>
                <span>
                  <?php foreach ($tplMonths as $mBadge): ?>
                    <strong class="<?= $mBadge === $month ? 'text-warning-emphasis' : '' ?>">T<?= $mBadge ?></strong>
                  <?php endforeach; ?>
                </span>
              </div>

              <h3 class="field-slip-title">
                <a href="template_detail.php?id=<?= (int)$tpl['id'] ?><?= $month > 0 ? '&month=' . $month : '' ?>">
                  <?= e($tpl['title']) ?>
                </a>
              </h3>

              <div class="field-reason-box">
                <div class="fw-bold mb-1">
                  <i class="bi bi-sun-fill me-1"></i>Vì sao nên đi <?= $month > 0 ? 'Tháng ' . $month : 'theo mùa' ?>:
                </div>
                <div><?= e(($month > 0 && !empty($tpl['matched_month_reason'])) ? $tpl['matched_month_reason'] : $tpl['seasonal_reason']) ?></div>
              </div>

              <div class="small text-muted mb-3 font-monospace" style="font-size: 0.76rem;">
                <div><i class="bi bi-truck-front me-1 text-success"></i><?= e($tpl['suggested_transport']) ?></div>
                <div><i class="bi bi-eye me-1"></i><?= (int)$tpl['views_count'] ?> lượt xem · <?= (int)$tpl['copies_count'] ?> lượt sao chép</div>
              </div>

              <div class="field-slip-footer">
                <div>
                  <span class="small text-muted d-block" style="font-size: 0.72rem;">Dự toán tham khảo</span>
                  <span class="price-mono"><?= format_vnd($tpl['ref_budget_per_person']) ?></span>
                  <small class="text-muted">/người</small>
                </div>
                <a href="template_detail.php?id=<?= (int)$tpl['id'] ?><?= $month > 0 ? '&month=' . $month : '' ?>" class="btn-cin-ink">
                  Mở hồ sơ <i class="bi bi-arrow-right"></i>
                </a>
              </div>
            </div>
          </article>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
