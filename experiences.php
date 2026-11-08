<?php
require_once __DIR__ . '/includes/helpers.php';

$pdo = get_db();
$monthsCatalog = get_months_catalog();

$provinceId = isset($_GET['province_id']) && $_GET['province_id'] !== '' ? (int)$_GET['province_id'] : 0;
$locationId = isset($_GET['location_id']) && $_GET['location_id'] !== '' ? (int)$_GET['location_id'] : 0;
$locationType = $_GET['type'] ?? '';
$visitMonth = isset($_GET['visit_month']) && $_GET['visit_month'] !== '' ? (int)$_GET['visit_month'] : 0;
$focusPostId = isset($_GET['post_id']) ? (int)$_GET['post_id'] : 0;

$provinces = $pdo->query('SELECT * FROM provinces WHERE is_active = 1 ORDER BY id ASC')->fetchAll();
$locations = $pdo->query("SELECT id, name, location_type FROM locations WHERE status = 'active' ORDER BY name ASC")->fetchAll();

$where = ["ep.status = 'approved'"];
$params = [];

// Nếu người dùng truyền post_id của chính họ hoặc là Admin, cho phép xem cả bài nháp/chờ duyệt/từ chối đó
if ($focusPostId > 0 && is_logged_in()) {
    $curUser = current_user();
    if ($curUser['role'] === 'admin') {
        $where = ['(ep.status = \'approved\' OR ep.id = ?)'];
        $params[] = $focusPostId;
    } else {
        $where = ['(ep.status = \'approved\' OR (ep.id = ? AND ep.user_id = ?))'];
        $params[] = $focusPostId;
        $params[] = $curUser['id'];
    }
}

if ($provinceId > 0) {
    $where[] = 'l.province_id = ?';
    $params[] = $provinceId;
}
if ($locationId > 0) {
    $where[] = 'ep.location_id = ?';
    $params[] = $locationId;
}
if (in_array($locationType, ['attraction', 'food', 'stay'], true)) {
    $where[] = 'l.location_type = ?';
    $params[] = $locationType;
}
if ($visitMonth >= 1 && $visitMonth <= 12) {
    $where[] = 'ep.visit_month = ?';
    $params[] = $visitMonth;
}

$whereSql = implode(' AND ', $where);
$stmt = $pdo->prepare("
    SELECT
        ep.*,
        u.full_name AS author_name,
        u.avatar_url AS author_avatar,
        l.name AS location_name,
        l.location_type,
        l.latitude,
        l.longitude,
        l.google_maps_url,
        p.name AS province_name
    FROM experience_posts ep
    JOIN users u ON u.id = ep.user_id
    JOIN locations l ON l.id = ep.location_id
    JOIN provinces p ON p.id = l.province_id
    WHERE {$whereSql}
    ORDER BY CASE WHEN ep.id = ? THEN 0 ELSE 1 END, ep.reviewed_at DESC, ep.id DESC
");
$params[] = $focusPostId;
$stmt->execute($params);
$posts = $stmt->fetchAll();

// Lấy danh sách ngày chuyến đi của User để hỗ trợ nút "Thêm địa điểm vào chuyến đi" ngay từ bài viết
$userTripDays = [];
if (is_logged_in()) {
    $stmtTD = $pdo->prepare('
        SELECT td.id AS trip_day_id, td.day_number, td.trip_date, t.title AS trip_title
        FROM trip_days td
        JOIN trips t ON t.id = td.trip_id
        WHERE t.user_id = ?
        ORDER BY t.start_date DESC, td.day_number ASC
    ');
    $stmtTD->execute([current_user()['id']]);
    $userTripDays = $stmtTD->fetchAll();
}

$pageTitle = 'Trải nghiệm Cộng đồng & Review Thực tế';
require_once __DIR__ . '/includes/header.php';
?>

<!-- SUBPAGE CINEMATIC HERO -->
<header class="cin-subhero">
  <img src="https://images.unsplash.com/photo-1528127269322-539801943592?w=1800&auto=format&fit=crop&q=82"
       alt="Trải nghiệm Cộng đồng Miền Bắc" class="cin-subhero-bg">
  <div class="cin-subhero-overlay"></div>
  <div class="container cin-subhero-inner">
    <div class="d-flex flex-wrap justify-content-between align-items-end gap-3">
      <div>
        <span class="cin-eyebrow mb-2" style="color: var(--cin-amber-bright);">
          NHÂN CHỨNG MÙA VỤ · MODULE M3 (<?= count($posts) ?> NHẬT KÝ)
        </span>
        <h1 class="cin-subhero-title">
          Nhật Ký <span class="sw">Thực Địa</span> &amp; Góc Nhìn Cộng Đồng
        </h1>
        <p class="cin-subhero-sub">
          Mọi bài viết đều được đối chiếu tọa độ và kiểm duyệt bởi Ban biên tập trước khi xuất bản, kèm chi phí thực tế và lời khuyên cho người đi sau.
        </p>
      </div>
      <div class="d-flex flex-wrap gap-2">
        <?php if (is_logged_in()): ?>
          <a href="dashboard.php" class="btn-cin-ghost">
            <i class="bi bi-journal-check"></i>Bài viết của tôi
          </a>
        <?php endif; ?>
        <a href="experience_form.php" class="btn-cin-amber">
          <i class="bi bi-pencil-square"></i>Viết nhật ký trải nghiệm
        </a>
      </div>
    </div>
  </div>
</header>

<main id="mainContent" class="container pb-5">
  <!-- BÀN LỌC NHẬT KÝ CỘNG ĐỒNG -->
  <section class="cin-filter-console" aria-label="Bộ lọc bài trải nghiệm">
    <form action="experiences.php" method="get" class="row g-3 align-items-end">
      <div class="col-md-3">
        <label class="cin-filter-label" for="fExpProv">Vùng / Tỉnh thành</label>
        <select id="fExpProv" name="province_id" class="form-select">
          <option value="">Tất cả tỉnh/thành</option>
          <?php foreach ($provinces as $prov): ?>
            <option value="<?= (int)$prov['id'] ?>" <?= $provinceId === (int)$prov['id'] ? 'selected' : '' ?>>
              <?= e($prov['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-md-3">
        <label class="cin-filter-label" for="fExpLoc">Tọa độ cụ thể</label>
        <select id="fExpLoc" name="location_id" class="form-select">
          <option value="">Tất cả địa điểm</option>
          <?php foreach ($locations as $loc): ?>
            <option value="<?= (int)$loc['id'] ?>" <?= $locationId === (int)$loc['id'] ? 'selected' : '' ?>>
              <?= e($loc['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-md-2">
        <label class="cin-filter-label" for="fExpType">Nhóm tọa độ</label>
        <select id="fExpType" name="type" class="form-select">
          <option value="">Tất cả 3 nhóm</option>
          <option value="attraction" <?= $locationType === 'attraction' ? 'selected' : '' ?>>Tham quan</option>
          <option value="food" <?= $locationType === 'food' ? 'selected' : '' ?>>Ăn uống</option>
          <option value="stay" <?= $locationType === 'stay' ? 'selected' : '' ?>>Lưu trú</option>
        </select>
      </div>

      <div class="col-md-2">
        <label class="cin-filter-label" for="fExpMonth">Tháng đã đi</label>
        <select id="fExpMonth" name="visit_month" class="form-select">
          <option value="">Mọi tháng</option>
          <?php foreach ($monthsCatalog as $mNum => $mData): ?>
            <option value="<?= $mNum ?>" <?= $visitMonth === $mNum ? 'selected' : '' ?>>
              <?= e($mData['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-md-2 d-flex gap-2">
        <button type="submit" class="btn-cin-ink flex-grow-1 justify-content-center">
          <i class="bi bi-funnel-fill"></i>Lọc bài
        </button>
        <a href="experiences.php" class="btn-cin-outline px-3" title="Xóa lọc">
          <i class="bi bi-arrow-counterclockwise"></i>
        </a>
      </div>
    </form>
  </section>

  <?php if (empty($posts)): ?>
    <div class="cin-empty-state">
      <i class="bi bi-journal-x fs-1" style="color: var(--cin-amber);"></i>
      <h3 class="h4 fw-bold mt-3" style="font-family: var(--font-display);">Chưa có bài trải nghiệm khớp với bộ lọc</h3>
      <p class="small text-muted mb-3">Hãy thử chọn tất cả các tháng hoặc trở thành người đầu tiên chia sẻ trải nghiệm cho địa điểm này.</p>
      <a href="experience_form.php" class="btn-cin-amber">Viết bài chia sẻ ngay</a>
    </div>
  <?php else: ?>
    <div class="d-flex flex-column gap-4">
      <?php foreach ($posts as $post): ?>
        <?php
          $locMeta = location_type_meta($post['location_type']);
          $stMeta = post_status_meta($post['status']);
          $stmtImgs = $pdo->prepare('SELECT * FROM experience_images WHERE post_id = ? ORDER BY sort_order ASC');
          $stmtImgs->execute([$post['id']]);
          $postImgs = $stmtImgs->fetchAll();

          $statusTone = 'status-done';
          $statusShape = 'shape-circle';
          if ($post['status'] === 'pending') { $statusTone = 'status-warn'; $statusShape = 'shape-triangle'; }
          elseif ($post['status'] === 'rejected') { $statusTone = 'status-err'; $statusShape = 'shape-diamond'; }
          elseif ($post['status'] === 'draft' || $post['status'] === 'hidden') { $statusTone = 'status-muted'; $statusShape = 'shape-square'; }
        ?>
        <article class="cin-day-dossier mb-0" style="<?= $focusPostId === (int)$post['id'] ? 'border-color: var(--cin-amber); box-shadow: 0 0 0 2px rgba(217,119,6,0.22);' : '' ?>">
          <!-- THANH HEADER NHẬT KÝ -->
          <div class="cin-day-header">
            <div class="d-flex align-items-center gap-3">
              <img src="<?= e($post['author_avatar'] ?: 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=100') ?>"
                   alt="" class="rounded-circle object-fit-cover border" width="42" height="42">
              <div>
                <div class="fw-bold"><?= e($post['author_name']) ?></div>
                <div class="font-monospace text-muted" style="font-size: 0.74rem;">
                  TRẢI NGHIỆM: <strong>THÁNG <?= (int)$post['visit_month'] ?>/<?= (int)$post['visit_year'] ?></strong>
                  <?php if ((float)$post['ref_cost'] > 0): ?>
                    · CHI PHÍ THỰC TẾ: <strong class="price-mono" style="font-size: 0.78rem;"><?= format_vnd($post['ref_cost']) ?>/<?= e($post['cost_unit']) ?></strong>
                  <?php endif; ?>
                </div>
              </div>
            </div>

            <div class="d-flex flex-wrap align-items-center gap-2">
              <?php if ($post['status'] !== 'approved'): ?>
                <span class="status-mark <?= $statusTone ?>">
                  <span class="status-shape <?= $statusShape ?>"></span><?= e($stMeta['label']) ?>
                </span>
              <?php endif; ?>
              <a href="location_detail.php?id=<?= (int)$post['location_id'] ?>" class="slip-tag">
                <i class="bi <?= e($locMeta['icon']) ?> me-1"></i><?= e($post['location_name']) ?> · <?= e($post['province_name']) ?>
              </a>
              <span class="slip-tag slip-tag-amber">
                <i class="bi bi-star-fill me-1"></i><?= (int)$post['rating'] ?>.0 / 5
              </span>
            </div>
          </div>

          <div class="cin-day-body">
            <?php if ($post['status'] === 'rejected' && !empty($post['rejection_reason'])): ?>
              <div class="p-3 mb-3 small" style="background: rgba(185,28,28,0.08); border-left: 3px solid #b91c1c; color: #991b1b;">
                <strong><i class="bi bi-exclamation-octagon-fill me-1"></i>Lý do từ chối từ Ban biên tập:</strong> <?= e($post['rejection_reason']) ?>
              </div>
            <?php endif; ?>

            <h2 class="h4 fw-bold mb-2" style="font-family: var(--font-display);"><?= e($post['title']) ?></h2>
            <p class="mb-3" style="color: var(--cin-ink-muted); font-size: 0.94rem; line-height: 1.68;"><?= nl2br(e($post['content'])) ?></p>

            <div class="row g-3 small mb-3">
              <?php if (!empty($post['pros_liked'])): ?>
                <div class="col-md-4">
                  <div class="field-spec-box h-100 mt-0">
                    <div class="fw-bold text-success mb-1"><i class="bi bi-hand-thumbs-up-fill me-1"></i>Điều yêu thích:</div>
                    <div><?= e($post['pros_liked']) ?></div>
                  </div>
                </div>
              <?php endif; ?>
              <?php if (!empty($post['cons_inconvenient'])): ?>
                <div class="col-md-4">
                  <div class="field-spec-box h-100 mt-0">
                    <div class="fw-bold text-danger mb-1"><i class="bi bi-exclamation-circle-fill me-1"></i>Điểm bất tiện:</div>
                    <div><?= e($post['cons_inconvenient']) ?></div>
                  </div>
                </div>
              <?php endif; ?>
              <?php if (!empty($post['tips_advice'])): ?>
                <div class="col-md-4">
                  <div class="journal-tip-box h-100 mb-0">
                    <div class="fw-bold mb-1"><i class="bi bi-lightbulb-fill text-warning me-1"></i>Lời khuyên thực tế:</div>
                    <div><?= e($post['tips_advice']) ?></div>
                  </div>
                </div>
              <?php endif; ?>
            </div>

            <?php if (!empty($postImgs)): ?>
              <div class="row g-2 mb-3">
                <?php foreach ($postImgs as $img): ?>
                  <div class="col-6 col-md-3">
                    <img src="<?= e($img['image_url']) ?>" alt="<?= e($img['caption']) ?>"
                         class="w-100 object-fit-cover border" style="height: 155px; border-radius: 3px;">
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>

            <div class="pt-3 border-top d-flex flex-wrap justify-content-between align-items-center gap-2">
              <div class="d-flex flex-wrap gap-2">
                <a href="location_detail.php?id=<?= (int)$post['location_id'] ?>" class="btn-cin-outline">
                  <i class="bi bi-geo-alt-fill text-success"></i>Hồ sơ tọa độ
                </a>
                <a href="<?= e(google_maps_view_url($post['latitude'], $post['longitude'], $post['google_maps_url'])) ?>"
                   target="_blank" rel="noopener" class="btn-cin-outline">
                  <i class="bi bi-map text-danger"></i>Google Maps
                </a>
                <?php if (is_logged_in() && (int)current_user()['id'] === (int)$post['user_id']): ?>
                  <a href="experience_form.php?id=<?= (int)$post['id'] ?>" class="btn-cin-ink">
                    <i class="bi bi-pencil"></i>Sửa bài viết của tôi
                  </a>
                <?php endif; ?>
              </div>

              <?php if (!empty($userTripDays)): ?>
                <form action="favorites.php" method="post" class="d-flex align-items-center gap-2 m-0">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="add_to_trip">
                  <input type="hidden" name="location_id" value="<?= (int)$post['location_id'] ?>">
                  <select name="trip_day_id" class="form-select form-select-sm" style="max-width: 230px; border-radius: 3px;" required>
                    <?php foreach ($userTripDays as $td): ?>
                      <option value="<?= (int)$td['trip_day_id'] ?>">
                        <?= e(mb_strimwidth($td['trip_title'], 0, 18, '...')) ?> - Ngày <?= (int)$td['day_number'] ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                  <button type="submit" class="btn-cin-amber py-1 px-3">
                    <i class="bi bi-plus-circle"></i>Chèn vào chuyến đi
                  </button>
                </form>
              <?php endif; ?>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
