<?php
require_once __DIR__ . '/includes/helpers.php';

$pdo = get_db();
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $pdo->prepare('
    SELECT
        l.*,
        p.name AS province_name,
        p.slug AS province_slug,
        c.name AS category_name
    FROM locations l
    JOIN provinces p ON p.id = l.province_id
    JOIN categories c ON c.id = l.category_id
    WHERE l.id = ?
    LIMIT 1
');
$stmt->execute([$id]);
$location = $stmt->fetch();

if (!$location) {
    set_flash('warning', 'Không tìm thấy địa điểm bạn yêu cầu.');
    header('Location: locations.php');
    exit;
}

// Ảnh bổ sung của địa điểm
$stmtImgs = $pdo->prepare('SELECT * FROM location_images WHERE location_id = ? ORDER BY sort_order ASC, id ASC');
$stmtImgs->execute([$id]);
$images = $stmtImgs->fetchAll();

// Các lịch trình mẫu có sử dụng địa điểm này (qua hoạt động, lưu trú hoặc cùng tỉnh/vùng)
$stmtTemplates = $pdo->prepare("
    SELECT DISTINCT
        it.*,
        p.name AS province_name
    FROM itinerary_templates it
    JOIN provinces p ON p.id = it.province_id
    LEFT JOIN template_days td ON td.template_id = it.id
    LEFT JOIN template_activities ta ON ta.template_day_id = td.id
    LEFT JOIN template_accommodations tac ON tac.template_id = it.id
    WHERE it.status = 'published'
      AND (ta.location_id = ? OR tac.location_id = ? OR it.province_id = ?)
    ORDER BY CASE WHEN ta.location_id = ? OR tac.location_id = ? THEN 0 ELSE 1 END, it.views_count DESC
");
$stmtTemplates->execute([$id, $id, $location['province_id'], $id, $id]);
$relatedTemplates = $stmtTemplates->fetchAll();

$stmtTplMonths = $pdo->prepare('SELECT month_number FROM template_months WHERE template_id = ? ORDER BY month_number ASC');
foreach ($relatedTemplates as &$rtRow) {
    $stmtTplMonths->execute([$rtRow['id']]);
    $mNums = $stmtTplMonths->fetchAll(PDO::FETCH_COLUMN);
    $rtRow['months_label'] = !empty($mNums) ? 'Tháng ' . implode(', ', $mNums) : 'Quanh năm';
}
unset($rtRow);

// Các địa điểm cùng khu vực (Tham quan / Ăn uống / Lưu trú lân cận)
$stmtNearby = $pdo->prepare("
    SELECT id, name, location_type, thumbnail_url, ref_price, price_unit
    FROM locations
    WHERE province_id = ? AND id != ? AND status = 'active'
    ORDER BY location_type ASC, id ASC
    LIMIT 6
");
$stmtNearby->execute([$location['province_id'], $id]);
$nearbyLocations = $stmtNearby->fetchAll();

// Bài trải nghiệm đã duyệt của địa điểm này (Điểm đánh giá trung bình chỉ tính từ bài công khai)
$stmtReviews = $pdo->prepare("
    SELECT
        ep.*,
        u.full_name AS author_name,
        u.avatar_url AS author_avatar
    FROM experience_posts ep
    JOIN users u ON u.id = ep.user_id
    WHERE ep.location_id = ? AND ep.status = 'approved'
    ORDER BY ep.reviewed_at DESC, ep.id DESC
");
$stmtReviews->execute([$id]);
$approvedPosts = $stmtReviews->fetchAll();

$avgRating = 0.0;
if (!empty($approvedPosts)) {
    $sum = array_sum(array_map(fn($r) => (int)$r['rating'], $approvedPosts));
    $avgRating = round($sum / count($approvedPosts), 1);
}

// Kiểm tra yêu thích & danh sách ngày chuyến đi của User
$isFav = false;
$userTripDays = [];
if (is_logged_in()) {
    $uid = current_user()['id'];
    $chkFav = $pdo->prepare('SELECT 1 FROM favorite_locations WHERE user_id = ? AND location_id = ?');
    $chkFav->execute([$uid, $id]);
    $isFav = (bool)$chkFav->fetchColumn();

    $stmtTD = $pdo->prepare('
        SELECT td.id AS trip_day_id, td.day_number, td.trip_date, t.title AS trip_title
        FROM trip_days td
        JOIN trips t ON t.id = td.trip_id
        WHERE t.user_id = ?
        ORDER BY t.start_date DESC, td.day_number ASC
    ');
    $stmtTD->execute([$uid]);
    $userTripDays = $stmtTD->fetchAll();
}

$typeMeta = location_type_meta($location['location_type']);
$gmapViewUrl = google_maps_view_url($location['latitude'], $location['longitude'], $location['google_maps_url']);
$gmapDirUrl = google_maps_directions_url($location['latitude'], $location['longitude']);

$singleMapPoint = [[
    'location_id'     => (int)$location['id'],
    'title'           => $location['name'],
    'subtitle'        => $location['address'],
    'type'            => $location['location_type'],
    'lat'             => (float)$location['latitude'],
    'lng'             => (float)$location['longitude'],
    'google_maps_url' => $gmapViewUrl,
]];

$pageTitle = $location['name'] . ' — ' . $location['province_name'];
require_once __DIR__ . '/includes/header.php';
?>

<main id="mainContent" class="container py-4">
  <nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb small mb-0 font-monospace" style="font-size: 0.76rem;">
      <li class="breadcrumb-item"><a href="index.php" style="color: var(--cin-jade);">TRANG CHỦ</a></li>
      <li class="breadcrumb-item"><a href="locations.php" style="color: var(--cin-jade);">ĐỊA ĐIỂM & BẢN ĐỒ</a></li>
      <li class="breadcrumb-item"><a href="locations.php?province_id=<?= (int)$location['province_id'] ?>" style="color: var(--cin-jade);"><?= e(mb_strtoupper($location['province_name'])) ?></a></li>
      <li class="breadcrumb-item active" aria-current="page"><?= e($location['name']) ?></li>
    </ol>
  </nav>

  <!-- CINEMATIC DOSSIER HERO ĐỊA ĐIỂM -->
  <section class="cin-dossier-hero">
    <div class="cin-dossier-hero-main">
      <div class="row g-4 align-items-center">
        <div class="col-lg-5">
          <div class="cin-dossier-cover" style="height: 320px;">
            <img src="<?= e($location['thumbnail_url']) ?>" alt="<?= e($location['name']) ?>">
          </div>
          <?php if (!empty($images)): ?>
            <div class="d-flex gap-2 mt-2 overflow-auto">
              <?php foreach ($images as $img): ?>
                <img src="<?= e($img['image_url']) ?>" alt="<?= e($img['caption']) ?>"
                     class="object-fit-cover" style="border-radius: 3px; border: 1px solid rgba(244,239,228,0.22);" width="96" height="64" title="<?= e($img['caption']) ?>">
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>

        <div class="col-lg-7">
          <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
            <span class="slip-tag slip-tag-amber">
              <i class="bi <?= e($typeMeta['icon']) ?> me-1"></i><?= e($typeMeta['label']) ?>
            </span>
            <span class="slip-tag">
              <i class="bi bi-tag me-1"></i><?= e($location['category_name']) ?>
            </span>
            <span class="slip-tag">
              <i class="bi bi-geo-alt-fill text-warning me-1"></i><?= e($location['province_name']) ?> · <?= number_format((float)$location['latitude'], 4) ?>°N, <?= number_format((float)$location['longitude'], 4) ?>°E
            </span>
          </div>

          <h1 class="cin-display-h2 text-white mb-2"><?= e($location['name']) ?></h1>

          <div class="d-flex flex-wrap align-items-center gap-3 small mb-3 font-monospace" style="color: rgba(244,239,228,0.78); font-size: 0.78rem;">
            <?php if (count($approvedPosts) > 0): ?>
              <span style="color: var(--cin-amber-bright); font-weight: 700;">
                <i class="bi bi-star-fill"></i> <?= number_format($avgRating, 1) ?>/5.0
                <span style="color: rgba(244,239,228,0.65); font-weight: 400;">(<?= count($approvedPosts) ?> nhân chứng đã duyệt)</span>
              </span>
            <?php else: ?>
              <span><i class="bi bi-star me-1"></i>Chưa có bài đánh giá công khai</span>
            <?php endif; ?>
            <span>
              <i class="bi bi-clock-history me-1"></i>Cập nhật giá: <strong><?= format_date_vn($location['price_updated_at']) ?></strong>
            </span>
          </div>

          <p class="mb-4" style="color: rgba(244,239,228,0.84); font-size: 0.94rem; line-height: 1.65;">
            <?= e($location['description'] ?: $location['short_desc']) ?>
          </p>

          <!-- CÁC NÚT HÀNH ĐỘNG CHÍNH (M5 & M7) -->
          <div class="d-flex flex-wrap gap-2">
            <button type="button" class="btn-cin-amber" data-bs-toggle="modal" data-bs-target="#addToTripModal">
              <i class="bi bi-plus-circle-fill"></i>Đưa vào chuyến đi cá nhân
            </button>
            <a href="<?= e($gmapDirUrl) ?>" target="_blank" rel="noopener" class="btn-cin-ghost">
              <i class="bi bi-sign-turn-right-fill text-warning"></i>Chỉ đường Google Maps
            </a>
            <form action="favorites.php" method="post" class="d-inline m-0">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="toggle">
              <input type="hidden" name="location_id" value="<?= (int)$location['id'] ?>">
              <input type="hidden" name="redirect" value="location_detail.php?id=<?= (int)$location['id'] ?>">
              <button type="submit" class="btn-cin-ghost">
                <i class="bi <?= $isFav ? 'bi-heart-fill text-danger' : 'bi-heart' ?>"></i><?= $isFav ? 'Đã lưu sổ tay' : 'Lưu tọa độ' ?>
              </button>
            </form>
          </div>
        </div>
      </div>
    </div>

    <!-- DẢI THÔNG SỐ 4 CỘT -->
    <div class="cin-dossier-meta-strip">
      <div class="cin-dossier-meta-cell">
        <span class="meta-label">Địa chỉ khảo sát</span>
        <span class="meta-val" style="font-size: 0.84rem; font-family: var(--font-body);"><?= e($location['address']) ?></span>
      </div>
      <div class="cin-dossier-meta-cell">
        <span class="meta-label">Khung giờ mở cửa</span>
        <span class="meta-val"><?= e($location['opening_hours'] ?: 'Cả ngày') ?></span>
      </div>
      <div class="cin-dossier-meta-cell">
        <span class="meta-label">Liên hệ thực địa</span>
        <span class="meta-val"><?= e($location['contact_info'] ?: 'Đang cập nhật') ?></span>
      </div>
      <div class="cin-dossier-meta-cell">
        <span class="meta-label">Đơn giá tham khảo</span>
        <span class="meta-val" style="color: var(--cin-amber-bright);">
          <?= format_vnd($location['ref_price'], true) ?><?= (float)$location['ref_price'] > 0 ? '/' . e($location['price_unit']) : '' ?>
        </span>
      </div>
    </div>
  </section>

  <div class="row g-4">
    <!-- CỘT TRÁI: THÔNG TIN ĐẶC THÙ THEO NHÓM + BÀI TRẢI NGHIỆM CỘNG ĐỒNG -->
    <div class="col-lg-7">
      <!-- BẢNG THÔNG TIN ĐẶC THÙ (Mục 4.6 Đặc tả) -->
      <section class="cin-day-dossier">
        <div class="cin-day-header">
          <div class="d-flex align-items-center gap-2">
            <span class="cin-day-badge"><i class="bi <?= e($typeMeta['icon']) ?> me-1"></i>HỒ SƠ ĐẶC THÙ</span>
            <h2 class="h6 fw-bold mb-0" style="font-family: var(--font-display); font-size: 1.15rem;">
              Thông số chuyên biệt — Nhóm <?= e($typeMeta['label']) ?>
            </h2>
          </div>
          <span class="font-monospace small text-muted">MỤC 4.6 ĐẶC TẢ</span>
        </div>

        <div class="cin-day-body">
          <?php if ($location['location_type'] === 'attraction'): ?>
            <div class="row g-3">
              <div class="col-md-6">
                <div class="field-spec-box h-100 mt-0">
                  <div class="spec-line"><i class="bi bi-ticket-perforated text-success me-1"></i><strong>Giá vé & dịch vụ:</strong></div>
                  <div class="mt-1 price-mono"><?= e($location['ticket_price_info'] ?: format_vnd($location['ref_price'], true)) ?></div>
                </div>
              </div>
              <div class="col-md-6">
                <div class="field-spec-box h-100 mt-0">
                  <div class="spec-line"><i class="bi bi-hourglass-split text-primary me-1"></i><strong>Thời lượng tham quan gợi ý:</strong></div>
                  <div class="mt-1 fw-bold"><?= e($location['visit_duration'] ?: '1.5 - 2.5 giờ') ?></div>
                </div>
              </div>
              <div class="col-12">
                <div class="journal-tip-box mb-0">
                  <i class="bi bi-calendar2-heart-fill text-warning me-1"></i><strong>Ghi chú mùa vụ từ Ban biên tập:</strong>
                  <?= e($location['seasonal_note'] ?: 'Thích hợp tham quan quanh năm.') ?>
                </div>
              </div>
            </div>
          <?php elseif ($location['location_type'] === 'food'): ?>
            <div class="row g-3">
              <div class="col-md-6">
                <div class="field-spec-box h-100 mt-0">
                  <div class="spec-line"><i class="bi bi-shop text-warning me-1"></i><strong>Loại hình ẩm thực:</strong></div>
                  <div class="mt-1 fw-bold"><?= e($location['restaurant_type'] ?: 'Nhà hàng đặc sản địa phương') ?></div>
                </div>
              </div>
              <div class="col-md-6">
                <div class="field-spec-box h-100 mt-0">
                  <div class="spec-line"><i class="bi bi-cash-coin text-success me-1"></i><strong>Khoảng giá theo người:</strong></div>
                  <div class="mt-1 price-mono"><?= e($location['price_range_per_person'] ?: format_vnd($location['ref_price']) . '/người') ?></div>
                </div>
              </div>
              <div class="col-12">
                <div class="journal-tip-box mb-0">
                  <i class="bi bi-egg-fried text-warning me-1"></i><strong>Món đặc sản nổi bật nên thử:</strong>
                  <?= e($location['signature_dishes']) ?>
                </div>
              </div>
            </div>
          <?php else: ?>
            <div class="row g-3">
              <div class="col-md-6">
                <div class="field-spec-box h-100 mt-0">
                  <div class="spec-line"><i class="bi bi-house-heart text-primary me-1"></i><strong>Loại hình lưu trú:</strong></div>
                  <div class="mt-1 fw-bold"><?= e($location['stay_type'] ?: 'Homestay & Bungalow') ?></div>
                </div>
              </div>
              <div class="col-md-6">
                <div class="field-spec-box h-100 mt-0">
                  <div class="spec-line"><i class="bi bi-clock text-success me-1"></i><strong>Giờ nhận / trả phòng:</strong></div>
                  <div class="mt-1 font-monospace fw-bold"><?= e($location['checkin_checkout_hours'] ?: 'Nhận 14:00 - Trả 12:00') ?></div>
                </div>
              </div>
              <div class="col-12">
                <div class="field-spec-box mt-0">
                  <div class="spec-line"><i class="bi bi-tag-fill text-warning me-1"></i><strong>Chi tiết hạng phòng & giá tham khảo:</strong> <?= e($location['room_price_info']) ?></div>
                  <div class="spec-line mt-1"><i class="bi bi-wifi text-info me-1"></i><strong>Tiện ích cơ bản:</strong> <?= e($location['amenities']) ?></div>
                </div>
              </div>
            </div>
          <?php endif; ?>
        </div>
      </section>

      <!-- BÀI TRẢI NGHIỆM CỘNG ĐỒNG VỀ ĐỊA ĐIỂM NÀY (M3) -->
      <section class="cin-day-dossier mb-0">
        <div class="cin-day-header">
          <div>
            <span class="cin-day-badge me-2">NHÂN CHỨNG THỰC ĐỊA (<?= count($approvedPosts) ?>)</span>
            <span class="fw-bold" style="font-family: var(--font-display); font-size: 1.15rem;">Nhật ký trải nghiệm đã kiểm duyệt</span>
          </div>
          <a href="experience_form.php?location_id=<?= (int)$location['id'] ?>" class="btn-cin-amber py-1 px-3">
            <i class="bi bi-pencil-square"></i>Viết nhật ký
          </a>
        </div>

        <div class="cin-day-body">
          <?php if (empty($approvedPosts)): ?>
            <div class="cin-empty-state py-4">
              <p class="small text-muted mb-3">Chưa có bài trải nghiệm công khai cho tọa độ này. Bạn đã từng đặt chân đến đây?</p>
              <a href="experience_form.php?location_id=<?= (int)$location['id'] ?>" class="btn-cin-ink">
                <i class="bi bi-pencil-square"></i>Gửi bài chia sẻ đầu tiên
              </a>
            </div>
          <?php else: ?>
            <div class="d-flex flex-column gap-3">
              <?php foreach ($approvedPosts as $post): ?>
                <?php
                  $stmtPImg = $pdo->prepare('SELECT * FROM experience_images WHERE post_id = ? ORDER BY sort_order ASC');
                  $stmtPImg->execute([$post['id']]);
                  $postImages = $stmtPImg->fetchAll();
                ?>
                <article class="cin-activity-slip">
                  <div class="d-flex justify-content-between align-items-start mb-2">
                    <div class="d-flex align-items-center gap-2">
                      <img src="<?= e($post['author_avatar'] ?: 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=100') ?>"
                           alt="" class="rounded-circle object-fit-cover" width="36" height="36">
                      <div>
                        <div class="fw-bold small"><?= e($post['author_name']) ?></div>
                        <div class="font-monospace text-muted" style="font-size: 0.72rem;">
                          CHUYẾN ĐI T<?= (int)$post['visit_month'] ?>/<?= (int)$post['visit_year'] ?>
                          <?php if ((float)$post['ref_cost'] > 0): ?>
                            · CHI PHÍ: <strong style="color: var(--cin-jade);"><?= format_vnd($post['ref_cost']) ?>/<?= e($post['cost_unit']) ?></strong>
                          <?php endif; ?>
                        </div>
                      </div>
                    </div>
                    <div class="small fw-bold" style="color: var(--cin-amber);">
                      <?php for ($i = 1; $i <= 5; $i++): ?>
                        <i class="bi <?= $i <= (int)$post['rating'] ? 'bi-star-fill' : 'bi-star' ?>"></i>
                      <?php endfor; ?>
                    </div>
                  </div>

                  <h3 class="h6 fw-bold mb-2" style="font-family: var(--font-display); font-size: 1.12rem;"><?= e($post['title']) ?></h3>
                  <p class="small mb-3" style="color: var(--cin-ink-muted); line-height: 1.6;"><?= nl2br(e($post['content'])) ?></p>

                  <div class="row g-2 small mb-2">
                    <?php if (!empty($post['pros_liked'])): ?>
                      <div class="col-md-6">
                        <div class="field-spec-box h-100 mt-0">
                          <strong class="text-success"><i class="bi bi-hand-thumbs-up-fill me-1"></i>Điểm cộng:</strong> <?= e($post['pros_liked']) ?>
                        </div>
                      </div>
                    <?php endif; ?>
                    <?php if (!empty($post['cons_inconvenient'])): ?>
                      <div class="col-md-6">
                        <div class="field-spec-box h-100 mt-0">
                          <strong class="text-danger"><i class="bi bi-exclamation-circle-fill me-1"></i>Điểm lưu ý:</strong> <?= e($post['cons_inconvenient']) ?>
                        </div>
                      </div>
                    <?php endif; ?>
                    <?php if (!empty($post['tips_advice'])): ?>
                      <div class="col-12">
                        <div class="journal-tip-box mb-0">
                          <strong><i class="bi bi-lightbulb-fill text-warning me-1"></i>Lời khuyên thực địa:</strong> <?= e($post['tips_advice']) ?>
                        </div>
                      </div>
                    <?php endif; ?>
                  </div>

                  <?php if (!empty($postImages)): ?>
                    <div class="d-flex flex-wrap gap-2 mt-2">
                      <?php foreach ($postImages as $pi): ?>
                        <img src="<?= e($pi['image_url']) ?>" alt="<?= e($pi['caption']) ?>"
                             class="object-fit-cover border" style="border-radius: 3px;" width="120" height="82">
                      <?php endforeach; ?>
                    </div>
                  <?php endif; ?>
                </article>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </section>
    </div>

    <!-- CỘT PHẢI: BẢN ĐỒ LEAFLET + LỊCH TRÌNH MẪU SỬ DỤNG ĐỊA ĐIỂM -->
    <div class="col-lg-5">
      <div class="cin-map-frame mb-4">
        <div class="cin-map-header">
          <span><i class="bi bi-geo-alt-fill text-warning me-1"></i>TỌA ĐỘ TRÊN BẢN ĐỒ SỐ</span>
          <span class="font-monospace" style="color: var(--cin-amber-bright);"><?= number_format((float)$location['latitude'], 4) ?>°N · <?= number_format((float)$location['longitude'], 4) ?>°E</span>
        </div>
        <div id="singleLocationMap" class="leaflet-map-box" style="height: 300px; border-radius: 0; border: 0;"></div>
        <div class="p-3 d-flex gap-2" style="background: var(--cin-paper-card); border-top: 1px solid var(--cin-rule);">
          <a href="<?= e($gmapViewUrl) ?>" target="_blank" rel="noopener" class="btn-cin-outline flex-grow-1 justify-content-center">
            <i class="bi bi-box-arrow-up-right"></i>Mở Google Maps
          </a>
          <a href="<?= e($gmapDirUrl) ?>" target="_blank" rel="noopener" class="btn-cin-ink flex-grow-1 justify-content-center">
            <i class="bi bi-sign-turn-right-fill"></i>Dẫn đường tới đây
          </a>
        </div>
      </div>

      <!-- CÁC LỊCH TRÌNH MẪU CÓ SỬ DỤNG ĐỊA ĐIỂM NÀY -->
      <section class="cin-day-dossier mb-0">
        <div class="cin-day-header">
          <span class="fw-bold"><i class="bi bi-calendar2-week text-success me-1"></i>Kịch bản 12 tháng đi qua tọa độ này</span>
          <span class="font-monospace small text-muted"><?= count($relatedTemplates) ?> MẪU</span>
        </div>
        <div class="cin-day-body">
          <?php if (empty($relatedTemplates)): ?>
            <p class="small text-muted mb-0">Hiện chưa có kịch bản mẫu nào gắn trực tiếp địa điểm này.</p>
          <?php else: ?>
            <div class="d-flex flex-column gap-3">
              <?php foreach ($relatedTemplates as $rt): ?>
                <article class="cin-activity-slip p-2">
                  <div class="d-flex gap-3 align-items-center">
                    <img src="<?= e($rt['thumbnail_url']) ?>" alt="" class="object-fit-cover" style="border-radius: 3px;" width="86" height="68">
                    <div class="flex-grow-1">
                      <span class="slip-tag py-0 px-2" style="font-size: 0.65rem;"><?= e($rt['months_label']) ?> · <?= (int)$rt['duration_days'] ?>N<?= (int)$rt['duration_nights'] ?>Đ</span>
                      <h3 class="h6 fw-bold mt-1 mb-1" style="font-family: var(--font-display); font-size: 1.04rem;">
                        <a href="template_detail.php?id=<?= (int)$rt['id'] ?>" class="text-dark"><?= e($rt['title']) ?></a>
                      </h3>
                      <div class="price-mono" style="font-size: 0.78rem;">
                        <?= format_vnd($rt['ref_budget_per_person']) ?>/người
                      </div>
                    </div>
                  </div>
                </article>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </section>

      <!-- ĐỊA ĐIỂM LÂN CẬN CÙNG KHU VỰC (THAM QUAN / ĂN UỐNG / LƯU TRÚ) -->
      <?php if (!empty($nearbyLocations)): ?>
        <section class="cin-day-dossier mt-4 mb-0">
          <div class="cin-day-header">
            <span class="fw-bold"><i class="bi bi-compass-fill text-warning me-1"></i>Điểm tham quan, Ăn uống &amp; Lưu trú cùng khu vực <?= e($location['province_name']) ?></span>
            <a href="locations.php?province_id=<?= (int)$location['province_id'] ?>" class="btn-cin-outline py-1 px-2">Tất cả &rarr;</a>
          </div>
          <div class="cin-day-body">
            <div class="d-flex flex-column gap-2">
              <?php foreach ($nearbyLocations as $nb): ?>
                <?php $nbMeta = location_type_meta($nb['location_type']); ?>
                <article class="cin-activity-slip p-2 d-flex align-items-center justify-content-between gap-2">
                  <div class="d-flex align-items-center gap-2 overflow-hidden">
                    <img src="<?= e($nb['thumbnail_url']) ?>" alt="" class="object-fit-cover" style="border-radius: 3px;" width="58" height="48">
                    <div class="overflow-hidden">
                      <span class="slip-tag py-0 px-2" style="font-size: 0.62rem;">
                        <i class="bi <?= e($nbMeta['icon']) ?> me-1"></i><?= e($nbMeta['short']) ?>
                      </span>
                      <a href="location_detail.php?id=<?= (int)$nb['id'] ?>" class="d-block fw-bold small text-dark text-truncate mt-1">
                        <?= e($nb['name']) ?>
                      </a>
                    </div>
                  </div>
                  <div class="text-end flex-shrink-0">
                    <span class="price-mono" style="font-size: 0.75rem;"><?= format_vnd($nb['ref_price'], true) ?></span>
                  </div>
                </article>
              <?php endforeach; ?>
            </div>
          </div>
        </section>
      <?php endif; ?>
    </div>
  </div>
</main>

<!-- MODAL THÊM ĐỊA ĐIỂM VÀO CHUYẾN ĐI CÁ NHÂN -->
<div class="modal fade" id="addToTripModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow" style="border-radius: 4px; background: var(--cin-paper-card);">
      <div class="modal-header text-white" style="background: var(--cin-night); border-radius: 4px 4px 0 0;">
        <h5 class="modal-title fw-bold" style="font-family: var(--font-display);">
          <i class="bi bi-plus-circle-fill text-warning me-2"></i>Chèn tọa độ vào kịch bản chuyến đi
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-4">
        <?php if (!is_logged_in()): ?>
          <p class="text-muted small mb-3">Bạn cần đăng nhập để thêm <strong><?= e($location['name']) ?></strong> vào chuyến đi của mình.</p>
          <a href="auth.php?tab=login&redirect=<?= urlencode('location_detail.php?id=' . (int)$location['id']) ?>" class="btn-cin-ink w-100 justify-content-center">
            Đăng nhập ngay
          </a>
        <?php elseif (empty($userTripDays)): ?>
          <p class="text-muted small mb-3">Bạn chưa có chuyến đi nào. Hãy tạo một chuyến đi mới hoặc sao chép từ lịch trình mẫu trước nhé!</p>
          <div class="d-flex gap-2">
            <a href="templates.php" class="btn-cin-outline flex-grow-1 justify-content-center">Xem lịch trình mẫu</a>
            <a href="trips.php?action=new" class="btn-cin-ink flex-grow-1 justify-content-center">Tạo chuyến đi mới</a>
          </div>
        <?php else: ?>
          <form action="favorites.php" method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add_to_trip">
            <input type="hidden" name="location_id" value="<?= (int)$location['id'] ?>">

            <div class="mb-3">
              <label class="form-label small fw-bold">Địa điểm được chọn</label>
              <input type="text" class="form-control bg-light font-monospace small" value="<?= e($location['name']) ?> (<?= format_vnd($location['ref_price'], true) ?>)" disabled>
            </div>

            <div class="mb-3">
              <label class="form-label small fw-bold">Chọn Chuyến đi & Ngày thực hiện</label>
              <select name="trip_day_id" class="form-select" required>
                <?php foreach ($userTripDays as $td): ?>
                  <option value="<?= (int)$td['trip_day_id'] ?>">
                    <?= e($td['trip_title']) ?> — Ngày <?= (int)$td['day_number'] ?> (<?= format_date_vn($td['trip_date']) ?>)
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="row g-2 mb-3">
              <div class="col-6">
                <label class="form-label small fw-bold">Giờ bắt đầu</label>
                <input type="time" name="start_time" class="form-control font-monospace" value="09:00">
              </div>
              <div class="col-6">
                <label class="form-label small fw-bold">Giờ kết thúc</label>
                <input type="time" name="end_time" class="form-control font-monospace" value="11:00">
              </div>
            </div>

            <div class="mb-3">
              <label class="form-label small fw-bold">Ghi chú hoạt động</label>
              <input type="text" name="notes" class="form-control" placeholder="VD: Đặt bàn trước / Mang máy ảnh...">
            </div>

            <button type="submit" class="btn-cin-amber w-100 justify-content-center">
              <i class="bi bi-check2-circle"></i>Xác nhận chèn vào chuyến đi
            </button>
          </form>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const singlePoint = <?= json_encode($singleMapPoint, JSON_UNESCAPED_UNICODE) ?>;
  BacDuMap.init('singleLocationMap', singlePoint, [<?= (float)$location['latitude'] ?>, <?= (float)$location['longitude'] ?>], 14);
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
