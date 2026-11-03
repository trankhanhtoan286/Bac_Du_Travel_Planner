<?php
require_once __DIR__ . '/includes/helpers.php';

$user = require_login('trips.php');
$pdo = get_db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        set_flash('danger', 'Phiên làm việc không hợp lệ.');
        header('Location: trips.php');
        exit;
    }

    $action = $_POST['action'] ?? '';
    if ($action === 'create_trip') {
        $title = trim($_POST['title'] ?? '');
        $provinceId = !empty($_POST['province_id']) ? (int)$_POST['province_id'] : null;
        $mainDest = trim($_POST['main_destination'] ?? '');
        $startDate = trim($_POST['start_date'] ?? date('Y-m-d', strtotime('+7 days')));
        $endDate = trim($_POST['end_date'] ?? date('Y-m-d', strtotime('+9 days')));
        $paxCount = max(1, (int)($_POST['pax_count'] ?? 2));
        $departurePoint = trim($_POST['departure_point'] ?? 'Hà Nội');
        $totalBudget = max(0, (float)($_POST['total_budget'] ?? 4000000));
        $coverUrl = trim($_POST['cover_image_url'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if (strtotime($endDate) < strtotime($startDate)) {
            $endDate = $startDate;
        }

        if ($title === '' || $mainDest === '') {
            set_flash('danger', 'Vui lòng nhập Tên chuyến đi và Điểm đến chính.');
            header('Location: trips.php?action=new');
            exit;
        }

        if ($coverUrl === '') {
            $coverUrl = 'https://images.unsplash.com/photo-1528127269322-539801943592?w=900&auto=format&fit=crop&q=80';
        }

        $pdo->beginTransaction();
        try {
            $shareToken = 'bacdu-' . substr(bin2hex(random_bytes(6)), 0, 10);
            $ins = $pdo->prepare('
                INSERT INTO trips (
                    user_id, source_template_id, province_id, title, main_destination,
                    start_date, end_date, pax_count, departure_point, total_budget,
                    description, cover_image_url, share_token, is_shared
                ) VALUES (?, NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)
            ');
            $ins->execute([
                $user['id'],
                $provinceId,
                $title,
                $mainDest,
                $startDate,
                $endDate,
                $paxCount,
                $departurePoint,
                $totalBudget,
                $description,
                $coverUrl,
                $shareToken,
            ]);
            $tripId = (int)$pdo->lastInsertId();

            // Tự động khởi tạo các ngày của chuyến đi (tối đa 14 ngày khởi tạo ban đầu)
            $daysCount = min(14, max(1, (int)round((strtotime($endDate) - strtotime($startDate)) / 86400) + 1));
            $insDay = $pdo->prepare('INSERT INTO trip_days (trip_id, day_number, trip_date, title, notes) VALUES (?, ?, ?, ?, ?)');
            for ($d = 1; $d <= $daysCount; $d++) {
                $dDate = date('Y-m-d', strtotime('+' . ($d - 1) . ' days', strtotime($startDate)));
                $insDay->execute([$tripId, $d, $dDate, "Ngày {$d}: Khám phá {$mainDest}", '']);
            }

            // Tạo checklist mặc định
            $defaultCheck = [
                ['Căn cước công dân (CCCD) & Giấy tờ tùy thân', 'Giấy tờ & Tiền mặt', 1],
                ['Trang phục phù hợp thời tiết miền Bắc', 'Trang phục', 2],
                ['Sạc dự phòng & thiết bị chụp ảnh', 'Thiết bị điện tử', 3],
            ];
            $insCk = $pdo->prepare('INSERT INTO checklists (trip_id, item_name, category, is_completed, sort_order) VALUES (?, ?, ?, 0, ?)');
            foreach ($defaultCheck as $ck) {
                $insCk->execute([$tripId, $ck[0], $ck[1], $ck[2]]);
            }

            $pdo->commit();
            set_flash('success', 'Đã tạo chuyến đi mới thành công! Hãy bắt đầu thêm hoạt động và dự toán chi phí.');
            header('Location: trip_detail.php?id=' . $tripId);
            exit;
        } catch (Throwable $err) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            set_flash('danger', 'Không thể tạo chuyến đi: ' . e($err->getMessage()));
            header('Location: trips.php');
            exit;
        }
    } elseif ($action === 'delete_trip') {
        $tripId = (int)($_POST['trip_id'] ?? 0);
        $del = $pdo->prepare('DELETE FROM trips WHERE id = ? AND user_id = ?');
        $del->execute([$tripId, $user['id']]);
        set_flash('info', 'Đã xóa chuyến đi khỏi danh sách cá nhân.');
        header('Location: trips.php');
        exit;
    }
}

$provinces = $pdo->query('SELECT * FROM provinces WHERE is_active = 1 ORDER BY id ASC')->fetchAll();
$statusFilter = $_GET['status'] ?? 'all';
$openNewModal = ($_GET['action'] ?? '') === 'new';

$stmt = $pdo->prepare('
    SELECT
        t.*,
        it.title AS template_title,
        (SELECT COUNT(*) FROM trip_days WHERE trip_id = t.id) AS days_count,
        (SELECT COUNT(*) FROM activities a JOIN trip_days td ON td.id = a.trip_day_id WHERE td.trip_id = t.id) AS activities_count,
        (SELECT COUNT(*) FROM checklists WHERE trip_id = t.id) AS checklist_total,
        (SELECT COUNT(*) FROM checklists WHERE trip_id = t.id AND is_completed = 1) AS checklist_done
    FROM trips t
    LEFT JOIN itinerary_templates it ON it.id = t.source_template_id
    WHERE t.user_id = ?
    ORDER BY t.start_date DESC, t.id DESC
');
$stmt->execute([$user['id']]);
$allTrips = $stmt->fetchAll();

$trips = [];
foreach ($allTrips as $tr) {
    $st = trip_timeline_status($tr['start_date'], $tr['end_date']);
    $tr['timeline_status'] = $st;
    if ($statusFilter === 'all' || $statusFilter === $st['code']) {
        $trips[] = $tr;
    }
}

$pageTitle = 'Quản lý Chuyến đi cá nhân';
require_once __DIR__ . '/includes/header.php';
?>

<header class="app-shift-bar mb-4">
  <div class="container">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
      <div>
        <div class="app-shift-eyebrow">
          <span><i class="bi bi-compass me-1 text-warning"></i>BÀN ĐIỀU PHỐI LỘ TRÌNH · APP MODE</span>
          <span>·</span>
          <span>MODULE M4 · @<?= e($user['username']) ?></span>
        </div>
        <h1 class="app-shift-title">Chuyến đi cá nhân của tôi</h1>
        <p class="small text-white-50 mb-0">
          Dùng phím <kbd class="bg-dark border border-secondary">j</kbd> / <kbd class="bg-dark border border-secondary">k</kbd> để chọn chuyến đi, <kbd class="bg-dark border border-secondary">Enter</kbd> để mở, hoặc <kbd class="bg-dark border border-secondary">/</kbd> mở Bảng lệnh nhanh.
        </p>
      </div>
      <div class="d-flex flex-wrap gap-2">
        <button type="button" class="btn-cin-ghost py-2 px-3" onclick="window.BacDuApp && window.BacDuApp.openPalette()" data-key="/">
          <i class="bi bi-terminal"></i> Lệnh nhanh
        </button>
        <a href="templates.php" class="btn-cin-ghost py-2 px-3" data-key="m" data-palette-label="Chọn từ Lịch trình mẫu 12 tháng">
          <i class="bi bi-calendar2-range"></i> Chọn từ mẫu
        </a>
        <button type="button" class="btn-cin-amber py-2 px-3" data-bs-toggle="modal" data-bs-target="#createTripModal" data-key="n" data-palette-label="Tạo chuyến đi mới từ đầu">
          <i class="bi bi-plus-circle"></i> Tạo chuyến đi mới
        </button>
      </div>
    </div>
  </div>
</header>

<main class="container pb-5" id="mainContent">
  <!-- THANH LỌC TRẠNG THÁI CHUYẾN ĐI (APP VIEW TABS - Phím 1-4) -->
  <div class="app-view-tabs" role="navigation" aria-label="Bộ lọc trạng thái chuyến đi">
    <a href="trips.php?status=all" class="app-view-tab <?= $statusFilter === 'all' ? 'active' : '' ?>" data-key="1">
      Tất cả hồ sơ (<?= count($allTrips) ?>)
    </a>
    <a href="trips.php?status=upcoming" class="app-view-tab <?= $statusFilter === 'upcoming' ? 'active' : '' ?>" data-key="2">
      <span class="status-mark status-warn py-0 px-1">Sắp khởi hành</span>
    </a>
    <a href="trips.php?status=ongoing" class="app-view-tab <?= $statusFilter === 'ongoing' ? 'active' : '' ?>" data-key="3">
      <span class="status-mark status-ok py-0 px-1">Đang diễn ra</span>
    </a>
    <a href="trips.php?status=completed" class="app-view-tab <?= $statusFilter === 'completed' ? 'active' : '' ?>" data-key="4">
      <span class="status-mark status-neutral py-0 px-1">Đã hoàn thành</span>
    </a>
  </div>

  <?php if (empty($trips)): ?>
    <div class="app-work-surface p-5 text-center">
      <i class="bi bi-luggage fs-1 text-muted"></i>
      <h4 class="cin-display-h2 fs-3 mt-2">
        <?= $statusFilter === 'all' ? 'Chưa có hồ sơ chuyến đi nào' : 'Không có chuyến đi nào khớp bộ lọc trạng thái này' ?>
      </h4>
      <?php if ($statusFilter !== 'all'): ?>
        <p class="text-muted small mb-3">Bộ lọc trạng thái đang giới hạn kết quả hiển thị.</p>
        <a href="trips.php?status=all" class="btn-cin-ink">Xóa bộ lọc trạng thái</a>
      <?php else: ?>
        <p class="text-muted small mb-4">Bạn có thể chọn một lịch trình mẫu 12 tháng rồi nhấn "Dùng lịch trình này", hoặc tự tạo kế hoạch mới từ đầu.</p>
        <div class="d-flex justify-content-center gap-2">
          <a href="templates.php?month=2" class="btn-cin-outline">Xem mẫu Mộc Châu Tháng 2</a>
          <button type="button" class="btn-cin-amber" data-bs-toggle="modal" data-bs-target="#createTripModal">Tự tạo chuyến đi mới</button>
        </div>
      <?php endif; ?>
    </div>
  <?php else: ?>
    <div class="row g-4">
      <?php foreach ($trips as $tr): ?>
        <?php
          $st = $tr['timeline_status'];
          $bSum = calculate_trip_budget_summary($pdo, (int)$tr['id'], (float)$tr['total_budget']);
          $statusMarkClass = $st['code'] === 'ongoing' ? 'status-ok' : ($st['code'] === 'upcoming' ? 'status-warn' : 'status-neutral');
          $budgetCap = max(1, (float)$tr['total_budget']);
          $actPct = min(100, round(($bSum['total_actual'] / $budgetCap) * 100));
          $estPct = max(0, min(100 - $actPct, round((max(0, $bSum['total_estimated'] - $bSum['total_actual']) / $budgetCap) * 100)));
        ?>
        <div class="col-md-6 col-lg-4">
          <article class="field-slip" data-kb-row="1">
            <div class="field-slip-media" style="height: 190px;">
              <img src="<?= e($tr['cover_image_url']) ?>" alt="<?= e($tr['title']) ?>">
              <div class="field-slip-badges">
                <span class="slip-tag">
                  <?= e($st['label']) ?>
                </span>
                <?php if ((int)$tr['is_shared'] === 1): ?>
                  <span class="slip-tag slip-tag-jade" title="Đang bật liên kết chia sẻ chỉ đọc">
                    <i class="bi bi-link-45deg"></i> ĐANG CHIA SẺ
                  </span>
                <?php endif; ?>
              </div>
            </div>

            <div class="field-slip-body">
              <div class="loc-coord-strip">
                <span class="status-mark <?= $statusMarkClass ?>"><?= e($st['label']) ?></span>
                <span><?= (int)$tr['days_count'] ?> NGÀY · <?= (int)$tr['pax_count'] ?> NGƯỜI</span>
              </div>

              <?php if (!empty($tr['template_title'])): ?>
                <div class="small text-success fw-semibold font-monospace mb-1" style="font-size:0.72rem;">
                  <i class="bi bi-magic me-1"></i>GỐC MẪU: <?= e(mb_strimwidth($tr['template_title'], 0, 36, '...')) ?>
                </div>
              <?php else: ?>
                <div class="small text-muted font-monospace mb-1" style="font-size:0.72rem;">
                  <i class="bi bi-compass me-1"></i>TỰ THIẾT KẾ LỘ TRÌNH
                </div>
              <?php endif; ?>

              <h3 class="field-slip-title">
                <a href="trip_detail.php?id=<?= (int)$tr['id'] ?>"><?= e($tr['title']) ?></a>
              </h3>

              <div class="small text-muted mb-3 d-flex flex-column gap-1">
                <div><i class="bi bi-geo-alt-fill text-success me-1"></i>Điểm đến: <strong><?= e($tr['main_destination']) ?></strong> (từ <?= e($tr['departure_point']) ?>)</div>
                <div class="font-monospace" style="font-size:0.76rem;"><i class="bi bi-calendar3 text-primary me-1"></i><?= format_date_vn($tr['start_date']) ?> &rarr; <?= format_date_vn($tr['end_date']) ?></div>
                <div><i class="bi bi-list-check text-warning-emphasis me-1"></i><?= (int)$tr['activities_count'] ?> hoạt động · Checklist <?= (int)$tr['checklist_done'] ?>/<?= (int)$tr['checklist_total'] ?></div>
              </div>

              <div class="field-spec-box mb-3">
                <div class="d-flex justify-content-between mb-1">
                  <span class="text-muted">Ngân sách trần:</span>
                  <strong class="num-col"><?= format_vnd($tr['total_budget']) ?></strong>
                </div>
                <div class="d-flex justify-content-between mb-1">
                  <span class="text-muted">Tổng dự toán:</span>
                  <strong class="num-col text-primary"><?= format_vnd($bSum['total_estimated']) ?></strong>
                </div>
                <div class="d-flex justify-content-between">
                  <span class="text-muted">Thực tế đã chi:</span>
                  <strong class="num-col text-success"><?= format_vnd($bSum['total_actual']) ?></strong>
                </div>
                <div class="budget-bridge-meter" title="Thực tế: <?= $actPct ?>% | Dự toán thêm: <?= $estPct ?>%">
                  <div class="budget-bridge-fill-actual" style="width: <?= $actPct ?>%"></div>
                  <div class="budget-bridge-fill-est" style="width: <?= $estPct ?>%"></div>
                </div>
              </div>

              <div class="field-slip-footer">
                <form action="trips.php" method="post" onsubmit="return confirm('Bạn có chắc chắn muốn xóa chuyến đi này?');">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="delete_trip">
                  <input type="hidden" name="trip_id" value="<?= (int)$tr['id'] ?>">
                  <button type="submit" class="btn-cin-outline py-1 px-2 text-danger" title="Xóa chuyến đi">
                    <i class="bi bi-trash"></i>
                  </button>
                </form>

                <a href="trip_detail.php?id=<?= (int)$tr['id'] ?>" class="btn-cin-ink py-1 px-3">
                  Điều phối kế hoạch <i class="bi bi-arrow-right"></i>
                </a>
              </div>
            </div>
          </article>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</main>

<!-- MODAL TẠO CHUYẾN ĐI MỚI TỪ ĐẦU -->
<div class="modal fade" id="createTripModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <span class="cin-eyebrow" style="color: var(--cin-amber-bright); font-size: 0.65rem;">KHỞI TẠO HỒ SƠ CHUYẾN ĐI · UC07.1</span>
          <h5 class="modal-title mb-0">Tự tạo chuyến đi mới từ đầu</h5>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form action="trips.php" method="post" class="row g-3">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="create_trip">

          <div class="col-md-7">
            <label class="form-label">Tên chuyến đi</label>
            <input type="text" name="title" class="form-control" placeholder="VD: Khám phá Hà Giang mùa hoa tam giác mạch" required>
          </div>

          <div class="col-md-5">
            <label class="form-label">Tỉnh / Thành miền Bắc</label>
            <select name="province_id" class="form-select">
              <option value="">-- Chọn tỉnh/thành --</option>
              <?php foreach ($provinces as $prov): ?>
                <option value="<?= (int)$prov['id'] ?>"><?= e($prov['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="col-md-6">
            <label class="form-label">Điểm đến chính</label>
            <input type="text" name="main_destination" class="form-control" placeholder="VD: Đồng Văn - Mèo Vạc" required>
          </div>

          <div class="col-md-6">
            <label class="form-label">Điểm xuất phát</label>
            <input type="text" name="departure_point" class="form-control" value="<?= e($user['hometown'] ?: 'Hà Nội') ?>" required>
          </div>

          <div class="col-md-4">
            <label class="form-label">Ngày đi</label>
            <input type="date" name="start_date" class="form-control font-monospace" value="<?= date('Y-m-d', strtotime('+7 days')) ?>" required>
          </div>

          <div class="col-md-4">
            <label class="form-label">Ngày về</label>
            <input type="date" name="end_date" class="form-control font-monospace" value="<?= date('Y-m-d', strtotime('+9 days')) ?>" required>
          </div>

          <div class="col-md-4">
            <label class="form-label">Số người</label>
            <input type="number" name="pax_count" class="form-control font-monospace" min="1" max="50" value="2" required>
          </div>

          <div class="col-md-6">
            <label class="form-label">Tổng ngân sách dự kiến (VNĐ)</label>
            <input type="number" name="total_budget" class="form-control font-monospace" step="100000" value="5000000" required>
          </div>

          <div class="col-md-6">
            <label class="form-label">Ảnh bìa chuyến đi (URL tùy chọn)</label>
            <input type="url" name="cover_image_url" class="form-control" placeholder="https://images.unsplash.com/...">
          </div>

          <div class="col-12">
            <label class="form-label">Mô tả / Ghi chú chung</label>
            <textarea name="description" rows="2" class="form-control" placeholder="Mục tiêu chuyến đi, lưu ý thành viên..."></textarea>
          </div>

          <div class="col-12 pt-2">
            <button type="submit" class="btn-cin-amber w-100 justify-content-center py-2">
              <i class="bi bi-check2-circle"></i>Tạo chuyến đi và mở Bàn điều phối
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<?php if ($openNewModal): ?>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const modalEl = document.getElementById('createTripModal');
  if (modalEl && typeof bootstrap !== 'undefined') {
    new bootstrap.Modal(modalEl).show();
  }
});
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
