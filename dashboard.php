<?php
require_once __DIR__ . '/includes/helpers.php';

$user = require_login('dashboard.php');
$pdo = get_db();

// Lấy danh sách chuyến đi của người dùng
$stmtTrips = $pdo->prepare('
    SELECT
        t.*,
        it.title AS source_template_title,
        (SELECT COUNT(*) FROM trip_days WHERE trip_id = t.id) AS days_count,
        (SELECT COUNT(*) FROM checklists WHERE trip_id = t.id) AS checklist_total,
        (SELECT COUNT(*) FROM checklists WHERE trip_id = t.id AND is_completed = 1) AS checklist_done
    FROM trips t
    LEFT JOIN itinerary_templates it ON it.id = t.source_template_id
    WHERE t.user_id = ?
    ORDER BY t.start_date DESC, t.id DESC
');
$stmtTrips->execute([$user['id']]);
$myTrips = $stmtTrips->fetchAll();

$upcomingCount = 0;
$ongoingCount = 0;
$completedCount = 0;
foreach ($myTrips as $tr) {
    $st = trip_timeline_status($tr['start_date'], $tr['end_date']);
    if ($st['code'] === 'upcoming') $upcomingCount++;
    elseif ($st['code'] === 'ongoing') $ongoingCount++;
    else $completedCount++;
}

// Lấy danh sách địa điểm yêu thích
$stmtFavs = $pdo->prepare('
    SELECT l.*, p.name AS province_name
    FROM favorite_locations fl
    JOIN locations l ON l.id = fl.location_id
    JOIN provinces p ON p.id = l.province_id
    WHERE fl.user_id = ?
    ORDER BY fl.created_at DESC
    LIMIT 6
');
$stmtFavs->execute([$user['id']]);
$myFavs = $stmtFavs->fetchAll();

// Lấy danh sách bài trải nghiệm của người dùng
$stmtPosts = $pdo->prepare('
    SELECT ep.*, l.name AS location_name, p.name AS province_name
    FROM experience_posts ep
    JOIN locations l ON l.id = ep.location_id
    JOIN provinces p ON p.id = l.province_id
    WHERE ep.user_id = ?
    ORDER BY ep.updated_at DESC, ep.id DESC
');
$stmtPosts->execute([$user['id']]);
$myPosts = $stmtPosts->fetchAll();

$rejectedPostsCount = 0;
$pendingPostsCountUser = 0;
foreach ($myPosts as $mp) {
    if ($mp['status'] === 'rejected') $rejectedPostsCount++;
    elseif ($mp['status'] === 'pending') $pendingPostsCountUser++;
}

$pageTitle = 'Bảng điều khiển cá nhân (User Dashboard)';
require_once __DIR__ . '/includes/header.php';
?>

<!-- APP MODE SHIFT BAR -->
<header class="app-shift-bar mb-4">
  <div class="container">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
      <div>
        <div class="app-shift-eyebrow">
          <span><i class="bi bi-grid-1x2-fill text-warning me-1"></i>BÀN ĐIỀU HÀNH CÁ NHÂN · USER DASHBOARD</span>
          <span>·</span>
          <span>MODULE M8 · LỮ KHÁCH: <strong class="text-white"><?= e($user['full_name']) ?></strong> (@<?= e($user['username']) ?>)</span>
        </div>
        <h1 class="app-shift-title">Tổng Quan Hành Trình &amp; Nhật Ký Thực Địa</h1>
        <p class="small text-white-50 mb-0">
          Nhấn <kbd class="app-kbd">j</kbd> / <kbd class="app-kbd">k</kbd> để duyệt qua danh sách chuyến đi, <kbd class="app-kbd">Enter</kbd> để mở bàn điều phối, hoặc <kbd class="app-kbd">/</kbd> để mở Bảng lệnh nhanh.
        </p>
      </div>
      <div class="d-flex flex-wrap gap-2">
        <button type="button" class="btn-cin-ghost py-1 px-3" onclick="window.BacDuApp && window.BacDuApp.openPalette()" data-open-palette title="Mở Bảng Lệnh Nhanh (/)">
          <i class="bi bi-terminal"></i> Lệnh <span class="app-kbd-badge">/</span>
        </button>
        <a href="templates.php" class="btn-cin-ghost py-1 px-3" data-key="m" data-palette-label="Khám phá Lịch trình mẫu 12 tháng">
          <i class="bi bi-calendar2-range"></i> Mẫu 12 tháng <span class="app-kbd-badge">m</span>
        </a>
        <a href="experience_form.php" class="btn-cin-ghost py-1 px-3" data-key="w" data-palette-label="Viết bài trải nghiệm cộng đồng">
          <i class="bi bi-pencil-square"></i> Viết bài <span class="app-kbd-badge">w</span>
        </a>
        <a href="trips.php?action=new" class="btn-cin-amber py-1 px-3" data-key="n" data-palette-label="Tạo chuyến đi mới">
          <i class="bi bi-plus-lg"></i> Tạo chuyến đi <span class="app-kbd-badge">n</span>
        </a>
      </div>
    </div>
  </div>
</header>

<main id="mainContent" class="container pb-5">
  <!-- SIGNATURE SET: PERSONAL EXPEDITION & DISPATCH MATRIX (Replaces 4 Generic KPI Cards) -->
  <section class="app-signature-set <?= $rejectedPostsCount > 0 ? 'is-lit' : '' ?> mb-4" id="personalDispatchSet" aria-label="Bàn điều phối hành trình cá nhân">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 pb-2 mb-3 border-bottom">
      <span class="cin-eyebrow mb-0" style="color: var(--cin-jade);">
        <i class="bi bi-compass-fill text-warning me-1"></i>HỒ SƠ HÀNH TRÌNH &amp; NHẬT KÝ CỦA <?= e(mb_strtoupper($user['full_name'])) ?>
      </span>
      <span class="font-monospace small" style="color: var(--cin-ink-muted);">
        ĐIỂM XUẤT PHÁT MẶC ĐỊNH: <strong style="color: var(--cin-ink);"><?= e($user['hometown'] ?: 'Hà Nội') ?></strong>
      </span>
    </div>

    <!-- ONE LIT REGION: Lit only when a post is rejected and needs revision -->
    <?php if ($rejectedPostsCount > 0): ?>
      <div class="app-lit-callout mb-3">
        <div class="d-flex align-items-center gap-3">
          <span class="lit-pulse" aria-hidden="true"></span>
          <div>
            <div class="fw-bold" style="color: var(--cin-ink); font-size: 0.88rem;">
              CẦN BỔ SUNG: BẠN CÓ <?= $rejectedPostsCount ?> BÀI TRẢI NGHIỆM BỊ TỪ CHỐI DUYỆT
            </div>
            <div class="small" style="color: var(--cin-ink-muted);">
              Xem góp ý của Ban biên tập ở cột bên phải, chỉnh sửa nội dung và gửi duyệt lại.
            </div>
          </div>
        </div>
        <span class="status-mark status-warn">Cần chỉnh sửa</span>
      </div>
    <?php else: ?>
      <div class="app-calm-callout mb-3">
        <div class="d-flex flex-wrap align-items-center gap-2 small" style="color: var(--cin-ink);">
          <span class="status-mark status-ok">Sẵn sàng khởi hành</span>
          <span>
            Bạn đang có <strong><?= count($myTrips) ?></strong> chuyến đi (<strong><?= $upcomingCount ?></strong> sắp khởi hành, <strong><?= $ongoingCount ?></strong> đang đi, <strong><?= $completedCount ?></strong> đã hoàn thành) và <strong><?= count($myPosts) ?></strong> bài trải nghiệm.
          </span>
        </div>
        <a href="profile.php" class="btn-cin-outline py-1 px-2">
          <i class="bi bi-person-gear"></i> Hồ sơ &amp; Mật khẩu
        </a>
      </div>
    <?php endif; ?>

    <div class="row g-3 align-items-stretch">
      <div class="col-lg-8">
        <div class="row g-2 font-monospace small h-100">
          <div class="col-6 col-md-3">
            <div class="p-3 h-100" style="background: var(--cin-paper); border: 1px solid rgba(20, 34, 27, 0.16); border-radius: 3px;">
              <div style="color: var(--cin-ink-muted); font-size: 0.68rem; font-weight: 700;">TỔNG CHUYẾN ĐI</div>
              <div class="fs-4 fw-bold mt-1" style="color: var(--cin-ink);"><?= count($myTrips) ?></div>
              <div style="color: var(--cin-jade); font-size: 0.72rem; font-weight: 600;"><?= $upcomingCount ?> sắp tới · <?= $ongoingCount ?> đang đi</div>
            </div>
          </div>
          <div class="col-6 col-md-3">
            <div class="p-3 h-100" style="background: var(--cin-paper); border: 1px solid rgba(20, 34, 27, 0.16); border-radius: 3px;">
              <div style="color: var(--cin-ink-muted); font-size: 0.68rem; font-weight: 700;">ĐÃ HOÀN THÀNH</div>
              <div class="fs-4 fw-bold mt-1" style="color: var(--cin-ink);"><?= $completedCount ?></div>
              <div style="color: var(--cin-ink-muted); font-size: 0.72rem;">Hồ sơ lưu trữ</div>
            </div>
          </div>
          <div class="col-6 col-md-3">
            <div class="p-3 h-100" style="background: var(--cin-paper); border: 1px solid rgba(20, 34, 27, 0.16); border-radius: 3px;">
              <div style="color: var(--cin-ink-muted); font-size: 0.68rem; font-weight: 700;">TỌA ĐỘ YÊU THÍCH</div>
              <div class="fs-4 fw-bold mt-1" style="color: var(--cin-amber);"><?= count($myFavs) ?></div>
              <a href="favorites.php" style="color: var(--cin-jade); font-size: 0.72rem; font-weight: 700;">Mở sổ tay &rarr;</a>
            </div>
          </div>
          <div class="col-6 col-md-3">
            <div class="p-3 h-100" style="background: var(--cin-paper); border: 1px solid rgba(20, 34, 27, 0.16); border-radius: 3px;">
              <div style="color: var(--cin-ink-muted); font-size: 0.68rem; font-weight: 700;">NHẬT KÝ CỘNG ĐỒNG</div>
              <div class="fs-4 fw-bold mt-1" style="color: var(--cin-ink);"><?= count($myPosts) ?></div>
              <a href="experience_form.php" style="color: var(--cin-jade); font-size: 0.72rem; font-weight: 700;">+ Viết bài mới &rarr;</a>
            </div>
          </div>
        </div>
      </div>
      <div class="col-lg-4">
        <div class="p-3 h-100 d-flex flex-column justify-content-center" style="background: var(--cin-paper-warm); border: 1px solid rgba(20, 34, 27, 0.18); border-left: 3px solid var(--cin-amber); border-radius: 3px;">
          <div class="small font-monospace text-uppercase fw-bold mb-1" style="color: var(--cin-jade-deep); font-size: 0.72rem;">
            <i class="bi bi-keyboard me-1 text-warning"></i>Điều hướng bàn phím (App Mode)
          </div>
          <div class="small" style="color: var(--cin-ink-muted); font-size: 0.8rem;">
            Nhấn <kbd class="bg-dark text-white px-1 rounded-1">j</kbd> / <kbd class="bg-dark text-white px-1 rounded-1">k</kbd> để duyệt qua danh sách chuyến đi, nhấn <kbd class="bg-dark text-white px-1 rounded-1">Enter</kbd> để mở bàn điều phối.
          </div>
        </div>
      </div>
    </div>
  </section>

  <div class="row g-4">
    <!-- CỘT TRÁI: CHUYẾN ĐI CỦA TÔI + TỌA ĐỘ ĐÃ LƯU -->
    <div class="col-lg-7">
      <section class="cin-day-dossier">
        <div class="cin-day-header">
          <span class="fw-bold"><i class="bi bi-compass text-success me-1"></i>Kế hoạch chuyến đi của bạn (<?= count($myTrips) ?>)</span>
          <a href="trips.php" class="btn-cin-outline py-1 px-2">Quản lý tất cả &rarr;</a>
        </div>
        <div class="cin-day-body">
          <?php if (empty($myTrips)): ?>
            <div class="cin-empty-state py-4">
              <i class="bi bi-luggage fs-1" style="color: var(--cin-amber);"></i>
              <p class="mt-2 mb-3 small text-muted">Bạn chưa có chuyến đi nào. Hãy sao chép từ lịch trình mẫu 12 tháng hoặc tự tạo mới!</p>
              <a href="templates.php" class="btn-cin-amber">Khám phá lịch trình mẫu</a>
            </div>
          <?php else: ?>
            <div class="d-flex flex-column gap-3">
              <?php foreach ($myTrips as $tr): ?>
                <?php
                  $st = trip_timeline_status($tr['start_date'], $tr['end_date']);
                  $budgetSum = calculate_trip_budget_summary($pdo, (int)$tr['id'], (float)$tr['total_budget']);
                  $statusClass = 'status-info';
                  $shapeClass = 'shape-triangle';
                  if ($st['code'] === 'ongoing') { $statusClass = 'status-done'; $shapeClass = 'shape-circle'; }
                  elseif ($st['code'] === 'completed') { $statusClass = 'status-muted'; $shapeClass = 'shape-square'; }
                ?>
                <article class="cin-activity-slip" data-kb-row="1">
                  <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">
                    <div>
                      <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                        <span class="status-mark <?= $statusClass ?>">
                          <span class="status-shape <?= $shapeClass ?>"></span><?= e($st['label']) ?>
                        </span>
                        <?php if (!empty($tr['source_template_title'])): ?>
                          <span class="slip-tag py-0 px-2" style="font-size: 0.65rem;">Từ mẫu: <?= e(mb_strimwidth($tr['source_template_title'], 0, 28, '...')) ?></span>
                        <?php endif; ?>
                      </div>
                      <h3 class="h6 fw-bold mb-1" style="font-family: var(--font-display); font-size: 1.15rem;">
                        <a href="trip_detail.php?id=<?= (int)$tr['id'] ?>" class="text-dark" data-kb-primary><?= e($tr['title']) ?></a>
                      </h3>
                      <div class="small font-monospace text-muted" style="font-size: 0.74rem;">
                        <i class="bi bi-geo-alt-fill text-success me-1"></i><?= e($tr['main_destination']) ?> ·
                        <?= format_date_vn($tr['start_date']) ?> &rarr; <?= format_date_vn($tr['end_date']) ?> ·
                        <?= (int)$tr['pax_count'] ?> người
                      </div>
                    </div>
                    <a href="trip_detail.php?id=<?= (int)$tr['id'] ?>" class="btn-cin-ink py-1 px-3">
                      Điều phối &rarr;
                    </a>
                  </div>
                  <div class="row g-2 pt-2 border-top mt-2 font-monospace" style="font-size: 0.74rem;">
                    <div class="col-sm-4">
                      <span class="text-muted">TRẦN:</span> <strong><?= format_vnd($tr['total_budget']) ?></strong>
                    </div>
                    <div class="col-sm-4">
                      <span class="text-muted">DỰ TOÁN:</span> <strong style="color: var(--cin-jade);"><?= format_vnd($budgetSum['total_estimated']) ?></strong>
                    </div>
                    <div class="col-sm-4">
                      <span class="text-muted">CHECKLIST:</span> <strong><?= (int)$tr['checklist_done'] ?>/<?= (int)$tr['checklist_total'] ?> mục</strong>
                    </div>
                  </div>
                </article>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </section>

      <!-- ĐỊA ĐIỂM YÊU THÍCH -->
      <section class="cin-day-dossier mb-0">
        <div class="cin-day-header">
          <span class="fw-bold"><i class="bi bi-heart-fill text-danger me-1"></i>Sổ tay tọa độ đã lưu yêu thích</span>
          <a href="favorites.php" class="btn-cin-outline py-1 px-2">Xem tất cả &rarr;</a>
        </div>
        <div class="cin-day-body">
          <?php if (empty($myFavs)): ?>
            <p class="small text-muted mb-0">Chưa có địa điểm yêu thích nào.</p>
          <?php else: ?>
            <div class="row g-2">
              <?php foreach ($myFavs as $fav): ?>
                <?php $tMeta = location_type_meta($fav['location_type']); ?>
                <div class="col-md-6">
                  <div class="cin-activity-slip p-2 d-flex align-items-center gap-2">
                    <img src="<?= e($fav['thumbnail_url']) ?>" alt="" class="object-fit-cover" style="border-radius: 3px;" width="54" height="54">
                    <div class="overflow-hidden flex-grow-1">
                      <span class="slip-tag py-0 px-2" style="font-size:0.62rem;"><?= e($tMeta['short']) ?></span>
                      <a href="location_detail.php?id=<?= (int)$fav['id'] ?>" class="d-block fw-bold small text-dark text-truncate mt-1"><?= e($fav['name']) ?></a>
                      <div class="font-monospace text-muted" style="font-size:0.7rem;"><?= e($fav['province_name']) ?></div>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </section>
    </div>

    <!-- CỘT PHẢI: QUẢN LÝ BÀI TRẢI NGHIỆM & TRẠNG THÁI DUYỆT -->
    <div class="col-lg-5">
      <section class="cin-day-dossier mb-0 h-100">
        <div class="cin-day-header">
          <span class="fw-bold"><i class="bi bi-journal-check text-warning me-1"></i>Nhật ký trải nghiệm &amp; Trạng thái duyệt</span>
          <a href="experience_form.php" class="btn-cin-amber py-1 px-2">+ Viết bài</a>
        </div>
        <div class="cin-day-body">
          <?php if (empty($myPosts)): ?>
            <div class="cin-empty-state py-4">
              <p class="small text-muted mb-2">Bạn chưa đăng bài chia sẻ trải nghiệm nào.</p>
              <a href="experience_form.php" class="btn-cin-ink">Chia sẻ bài đầu tiên</a>
            </div>
          <?php else: ?>
            <div class="d-flex flex-column gap-3">
              <?php foreach ($myPosts as $mp): ?>
                <?php
                  $stMeta = post_status_meta($mp['status']);
                  $pTone = 'status-done';
                  $pShape = 'shape-circle';
                  if ($mp['status'] === 'pending') { $pTone = 'status-warn'; $pShape = 'shape-triangle'; }
                  elseif ($mp['status'] === 'rejected') { $pTone = 'status-err'; $pShape = 'shape-diamond'; }
                  elseif ($mp['status'] === 'draft' || $mp['status'] === 'hidden') { $pTone = 'status-muted'; $pShape = 'shape-square'; }
                ?>
                <article class="cin-activity-slip" data-kb-row="1">
                  <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="status-mark <?= $pTone ?>">
                      <span class="status-shape <?= $pShape ?>"></span><?= e($stMeta['label']) ?>
                    </span>
                    <span class="small fw-bold" style="color: var(--cin-amber);">
                      <i class="bi bi-star-fill"></i> <?= (int)$mp['rating'] ?>/5
                    </span>
                  </div>
                  <h3 class="h6 fw-bold mt-1 mb-1" style="font-family: var(--font-display); font-size: 1.08rem;">
                    <a href="experiences.php?post_id=<?= (int)$mp['id'] ?>" class="text-dark" data-kb-primary><?= e($mp['title']) ?></a>
                  </h3>
                  <div class="font-monospace text-muted mb-2" style="font-size: 0.72rem;">
                    <i class="bi bi-geo-alt me-1"></i><?= e($mp['location_name']) ?> (<?= e($mp['province_name']) ?>) · T<?= (int)$mp['visit_month'] ?>/<?= (int)$mp['visit_year'] ?>
                  </div>

                  <?php if ($mp['status'] === 'rejected' && !empty($mp['rejection_reason'])): ?>
                    <div class="p-2 mb-2 small" style="background: rgba(185,28,28,0.08); border-left: 3px solid #b91c1c; color: #991b1b;">
                      <strong><i class="bi bi-exclamation-triangle-fill me-1"></i>Góp ý từ Ban biên tập:</strong><br>
                      <?= e($mp['rejection_reason']) ?>
                    </div>
                  <?php endif; ?>

                  <div class="d-flex justify-content-end gap-2 mt-2 pt-2 border-top">
                    <a href="experience_form.php?id=<?= (int)$mp['id'] ?>" class="btn-cin-ink py-1 px-2">
                      <i class="bi bi-pencil"></i><?= $mp['status'] === 'rejected' ? 'Sửa & Gửi duyệt lại' : 'Chỉnh sửa' ?>
                    </a>
                    <a href="experiences.php?post_id=<?= (int)$mp['id'] ?>" class="btn-cin-outline py-1 px-2">
                      Xem
                    </a>
                  </div>
                </article>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </section>
    </div>
  </div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
