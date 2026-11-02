<?php
require_once __DIR__ . '/includes/helpers.php';

$pdo = get_db();
$monthsCatalog = get_months_catalog();
// Mặc định gợi ý Tháng 2 (mùa hoa mận Mộc Châu theo kịch bản Demo mục 5) hoặc tháng người dùng chọn trên trang chủ
$selectedMonth = isset($_GET['month']) ? max(1, min(12, (int)$_GET['month'])) : 2;
$monthInfo = $monthsCatalog[$selectedMonth];

// Lấy danh sách tỉnh/thành miền Bắc cho bộ lọc nhanh
$provinces = $pdo->query('SELECT * FROM provinces WHERE is_active = 1 ORDER BY id ASC')->fetchAll();

// Lấy các lịch trình mẫu đã xuất bản của tháng đang chọn
$stmtTemplates = $pdo->prepare('
    SELECT
        it.*,
        p.name AS province_name,
        tm.month_reason,
        u.full_name AS author_name
    FROM itinerary_templates it
    JOIN template_months tm ON tm.template_id = it.id
    JOIN provinces p ON p.id = it.province_id
    LEFT JOIN users u ON u.id = it.author_id
    WHERE it.status = ? AND tm.month_number = ?
    ORDER BY it.views_count DESC, it.id ASC
');
$stmtTemplates->execute(['published', $selectedMonth]);
$monthTemplates = $stmtTemplates->fetchAll();

// Lấy 6 địa điểm tiêu biểu (đủ 3 nhóm Tham quan, Ăn uống, Lưu trú) kèm điểm đánh giá trung bình từ bài đã duyệt
$featuredLocations = $pdo->query("
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
    WHERE l.status = 'active'
    GROUP BY l.id
    ORDER BY l.id ASC
    LIMIT 6
")->fetchAll();

// Lấy 3 bài trải nghiệm cộng đồng mới nhất đã được duyệt
$latestExperiences = $pdo->query("
    SELECT
        ep.*,
        u.full_name AS author_name,
        u.avatar_url AS author_avatar,
        l.name AS location_name,
        l.location_type,
        p.name AS province_name,
        (SELECT image_url FROM experience_images WHERE post_id = ep.id ORDER BY sort_order ASC LIMIT 1) AS first_image
    FROM experience_posts ep
    JOIN users u ON u.id = ep.user_id
    JOIN locations l ON l.id = ep.location_id
    JOIN provinces p ON p.id = l.province_id
    WHERE ep.status = 'approved'
    ORDER BY ep.reviewed_at DESC, ep.id DESC
    LIMIT 3
")->fetchAll();

// Danh sách ID địa điểm yêu thích của user hiện tại
$userFavIds = [];
if (is_logged_in()) {
    $stmtF = $pdo->prepare('SELECT location_id FROM favorite_locations WHERE user_id = ?');
    $stmtF->execute([current_user()['id']]);
    $userFavIds = array_map('intval', $stmtF->fetchAll(PDO::FETCH_COLUMN));
}

$compassAngle = ($selectedMonth - 1) * 30;

$pageTitle = 'Khám phá Lịch trình 12 Tháng & Lập kế hoạch Du lịch Miền Bắc';
require_once __DIR__ . '/includes/header.php';
?>

<!-- Fixed Decorative Latitude & Reel Progress Instrument (aria-hidden) -->
<aside class="cin-instrument-rail" aria-hidden="true">
  <span id="hudSceneCode">SC.01</span>
  <div class="rail-track"><div class="rail-fill" id="hudRailFill"></div></div>
  <span id="hudLatCode">20°50'N</span>
</aside>

<main class="cin-main" id="main-film">

  <!-- ========================================================================
       SCENE 1: ARRIVAL — COLD OPEN & QUICK ITINERARY DISPATCH CONSOLE
       ======================================================================== -->
  <section class="scene-arrival" data-scene="arrival" data-still="0.45">
    <div class="frame">
      <!-- Layered Atmospheric Backdrop (Graded by kit/grade.js) -->
      <div class="arrival-backdrop" aria-hidden="true">
        <img src="https://images.unsplash.com/photo-1528127269322-539801943592?w=1600&auto=format&fit=crop&q=80"
             alt="" class="arrival-plate-img" data-grade>
        <video class="arrival-mist-video" src="assets/kit/grade-assets/fog.mp4"
               poster="assets/kit/grade-assets/fog.jpg" muted playsinline loop autoplay preload="metadata" data-grade></video>
        <div class="arrival-sun-shaft"></div>

        <!-- Lead Actor State 1 & 2: The Northern Almanac Compass Rose -->
        <div class="almanac-compass-stage" id="heroCompassStage">
          <svg class="almanac-compass-svg" viewBox="0 0 240 240" fill="none" xmlns="http://www.w3.org/2000/svg">
            <circle cx="120" cy="120" r="110" stroke="rgba(245, 158, 11, 0.38)" stroke-width="1" stroke-dasharray="3 5"/>
            <circle cx="120" cy="120" r="88" stroke="rgba(246, 238, 224, 0.28)" stroke-width="1"/>
            <circle cx="120" cy="120" r="54" stroke="rgba(245, 158, 11, 0.45)" stroke-width="1"/>
            <?php for ($deg = 0; $deg < 360; $deg += 30): ?>
              <line x1="120" y1="10" x2="120" y2="24" stroke="rgba(246, 238, 224, 0.55)" stroke-width="1.5"
                    transform="rotate(<?= $deg ?> 120 120)"/>
            <?php endfor; ?>
            <g id="heroCompassNeedle" transform="rotate(<?= $compassAngle ?> 120 120)">
              <polygon points="120,18 126,120 120,106 114,120" fill="#f59e0b"/>
              <polygon points="120,222 125,120 120,134 115,120" fill="rgba(246, 238, 224, 0.45)"/>
              <circle cx="120" cy="120" r="5" fill="#f4efe4" stroke="#d97706" stroke-width="2"/>
            </g>
          </svg>
        </div>

        <!-- Wooden Louvered Shutters that part as scroll progresses -->
        <div class="shutter-wing shutter-left"></div>
        <div class="shutter-wing shutter-right"></div>
      </div>

      <!-- Foreground Content: Editorial Dialogue + Quick Dispatch Console -->
      <div class="arrival-grid">
        <div class="arrival-lead-copy">
          <div class="arrival-meta-bar">
            <span><i class="bi bi-compass me-1"></i>NỀN TẢNG DU LỊCH MIỀN BẮC THEO 12 THÁNG</span>
            <span>·</span>
            <span><?= e($monthInfo['name']) ?> (<?= e($monthInfo['season']) ?>)</span>
          </div>

          <h1 class="cin-display-h1">
            Tháng này đi đâu ở miền Bắc?<br>
            <span class="sw" id="heroBlurTitle">Chọn mùa đẹp nhất, lên lịch trình ngay.</span>
          </h1>

          <p class="arrival-subcopy">
            Kết hợp lịch trình mẫu chuẩn chỉnh từ Ban biên tập và góc nhìn thực tế từ Cộng đồng. Sao chép thành chuyến đi riêng chỉ với 1 cú nhấp chuột, tự do điều chỉnh điểm đến, ngân sách và bản đồ.
          </p>

          <div class="arrival-cta-row">
            <a href="templates.php?month=<?= $selectedMonth ?>" class="btn-cin-amber">
              <i class="bi bi-compass"></i>Khám phá lịch trình Tháng <?= $selectedMonth ?>
            </a>
            <a href="trips.php?action=new" class="btn-cin-ghost">
              <i class="bi bi-plus-circle"></i>Tự tạo chuyến đi mới
            </a>
          </div>
        </div>

        <!-- Dispatch Console (Quick Search Form) -->
        <div class="dispatch-console">
          <div class="dispatch-header">
            <h2 class="dispatch-title">
              <i class="bi bi-sliders2-vertical text-warning me-2"></i>Bộ tìm kiếm lịch trình nhanh
            </h2>
            <span class="dispatch-stamp">12 Tháng · Km0</span>
          </div>

          <form action="templates.php" method="get" class="row g-2">
            <div class="col-12">
              <label for="quickMonthSelect" class="form-label">1. Bạn dự định đi vào tháng mấy?</label>
              <select id="quickMonthSelect" name="month" class="form-select">
                <?php foreach ($monthsCatalog as $mNum => $mData): ?>
                  <option value="<?= $mNum ?>" <?= $mNum === $selectedMonth ? 'selected' : '' ?>>
                    <?= e($mData['name']) ?> — <?= e($mData['tag']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-7">
              <label for="quickProvSelect" class="form-label">2. Điểm đến miền Bắc</label>
              <select id="quickProvSelect" name="province_id" class="form-select">
                <option value="">Tất cả điểm đến</option>
                <?php foreach ($provinces as $prov): ?>
                  <option value="<?= (int)$prov['id'] ?>"><?= e($prov['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-5">
              <label for="quickDurSelect" class="form-label">3. Thời lượng</label>
              <select id="quickDurSelect" name="duration" class="form-select">
                <option value="">Mọi thời lượng</option>
                <option value="2">2 Ngày 1 Đêm</option>
                <option value="3">3 Ngày 2 Đêm</option>
              </select>
            </div>
            <div class="col-12 pt-1">
              <button type="submit" class="btn-cin-ink w-100 py-2">
                <i class="bi bi-search"></i>Xem lịch trình gợi ý phù hợp
              </button>
            </div>
          </form>

          <div class="dispatch-hot-strip">
            <span><i class="bi bi-fire text-danger me-1"></i>Đang hot: <strong>Mộc Châu 3N2Đ (Tháng 2)</strong></span>
            <a href="template_detail.php?id=1" class="fw-bold" style="color: var(--cin-jade);">Xem ngay &rarr;</a>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- ========================================================================
       SCENE 2: ALMANAC — BÁNH XE 12 THÁNG & LỊCH TRÌNH MẪU CHUẨN MÙA (FLOW)
       ======================================================================== -->
  <section class="scene-almanac" data-scene="almanac" data-flow>
    <div class="frame">
      <div class="almanac-container">
        <div class="almanac-top-bar">
          <div>
            <span class="cin-eyebrow" style="color: var(--cin-jade);">Bốn mùa miền Bắc · Sổ Địa Dư</span>
            <h2 class="cin-display-h2 mt-1">Chọn tháng khởi hành — <span class="sw">Xem gợi ý chuẩn mùa</span></h2>
            <p class="mb-0" style="color: var(--cin-ink-muted);">
              Mỗi tháng đều có những vùng đất bước vào độ đẹp nhất do Ban biên tập chọn lọc.
            </p>
          </div>
          <a href="templates.php" class="btn-cin-outline">
            Xem tất cả lịch trình mẫu <i class="bi bi-arrow-right"></i>
          </a>
        </div>

        <!-- THE AUDIENCE'S PART: Interactive 12-Month Compass Console + 12 Month Cells -->
        <div class="almanac-interactive-deck">
          <!-- Left: Live Compass Telemetry Card (Lead State 3) -->
          <div class="compass-live-card" aria-live="polite">
            <div>
              <div class="d-flex justify-content-between align-items-center small font-monospace" style="color: var(--cin-amber-bright);">
                <span>LA BÀN MÙA VỤ</span>
                <span id="liveCompassSeason"><?= e($monthInfo['season']) ?></span>
              </div>
              <div class="compass-dial-wrap" aria-hidden="true">
                <svg class="compass-dial-svg" viewBox="0 0 200 200">
                  <circle cx="100" cy="100" r="90" fill="none" stroke="rgba(246, 238, 224, 0.2)" stroke-width="1.5"/>
                  <circle cx="100" cy="100" r="68" fill="none" stroke="rgba(245, 158, 11, 0.35)" stroke-width="1" stroke-dasharray="2 4"/>
                  <?php for ($m = 1; $m <= 12; $m++): $ang = ($m - 1) * 30 - 90; $rad = deg2rad($ang); ?>
                    <text x="<?= round(100 + 80 * cos($rad), 1) ?>" y="<?= round(104 + 80 * sin($rad), 1) ?>"
                          text-anchor="middle" fill="<?= $m === $selectedMonth ? '#f59e0b' : 'rgba(246,238,224,0.6)' ?>"
                          font-family="Be Vietnam Pro, sans-serif" font-size="9" font-weight="600">T<?= $m ?></text>
                  <?php endfor; ?>
                  <g class="compass-needle-group" id="liveCompassNeedle" style="transform: rotate(<?= $compassAngle ?>deg);">
                    <polygon points="100,26 105,100 100,90 95,100" fill="#f59e0b"/>
                    <polygon points="100,174 104,100 100,110 96,100" fill="rgba(246,238,224,0.35)"/>
                    <circle cx="100" cy="100" r="5" fill="#f4efe4" stroke="#d97706" stroke-width="2"/>
                  </g>
                </svg>
              </div>
            </div>

            <div>
              <div class="font-monospace small text-uppercase" style="color: rgba(246,238,224,0.65); font-size: 0.7rem;">
                TỌA ĐỘ MÙA ĐANG CHỌN
              </div>
              <h3 class="h4 fw-bold mb-1" style="font-family: var(--font-display); color: var(--cin-cream);" id="liveCompassMonthName">
                <?= e($monthInfo['name']) ?> — <?= e($monthInfo['tag']) ?>
              </h3>
              <p class="small mb-3" style="color: rgba(246,238,224,0.78);" id="liveCompassHighlight">
                Tâm điểm: <?= e($monthInfo['highlight']) ?>
              </p>
              <a href="templates.php?month=<?= $selectedMonth ?>" id="liveCompassAction" class="btn-cin-amber w-100 py-2">
                <i class="bi bi-compass"></i>Mở mẫu <?= e($monthInfo['name']) ?>
              </a>
            </div>
          </div>

          <!-- Right: 12-Month Almanac Selector Grid -->
          <div class="almanac-months-grid" role="navigation" aria-label="Chọn 12 tháng du lịch miền Bắc">
            <?php foreach ($monthsCatalog as $mNum => $mData): ?>
              <a href="index.php?month=<?= $mNum ?>#monthly-recommendations"
                 class="almanac-month-cell month-pill <?= $mNum === $selectedMonth ? 'active' : '' ?>"
                 data-month="<?= $mNum ?>"
                 data-name="<?= e($mData['name']) ?>"
                 data-season="<?= e($mData['season']) ?>"
                 data-tag="<?= e($mData['tag']) ?>"
                 data-highlight="<?= e($mData['highlight']) ?>">
                <div class="m-top">
                  <span class="m-num">M.<?= str_pad((string)$mNum, 2, '0', STR_PAD_LEFT) ?></span>
                  <span class="m-season"><?= e($mData['season']) ?></span>
                </div>
                <div class="m-name"><?= e($mData['name']) ?></div>
                <div class="m-highlight" title="<?= e($mData['tag']) ?>">
                  <?= e($mData['highlight']) ?>
                </div>
              </a>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- KẾT QUẢ LỊCH TRÌNH MẪU CỦA THÁNG ĐANG CHỌN -->
        <div id="monthly-recommendations" class="almanac-dossier-box">
          <div class="dossier-banner">
            <div>
              <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                <span class="slip-tag slip-tag-jade"><?= e($monthInfo['name']) ?> · <?= e($monthInfo['season']) ?></span>
                <h3 class="h4 fw-bold mb-0" style="font-family: var(--font-display);" id="activeMonthTagHeading">
                  <?= e($monthInfo['tag']) ?>
                </h3>
              </div>
              <p class="small mb-0" style="color: var(--cin-ink-muted);">
                Hiển thị <strong id="tplCountOdometer"><?= count($monthTemplates) ?></strong> lịch trình mẫu phù hợp nhất trong <?= strtolower($monthInfo['name']) ?>.
              </p>
            </div>
            <a href="templates.php?month=<?= $selectedMonth ?>" class="btn-cin-ink">
              Mở bộ lọc chi tiết <?= e($monthInfo['name']) ?> <i class="bi bi-funnel"></i>
            </a>
          </div>

          <div class="row g-4">
            <?php foreach ($monthTemplates as $tpl): ?>
              <div class="col-md-6 col-lg-4">
                <article class="field-slip">
                  <div class="field-slip-media">
                    <img src="<?= e($tpl['thumbnail_url']) ?>" alt="<?= e($tpl['title']) ?>" data-grade>
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
                    <h4 class="field-slip-title">
                      <a href="template_detail.php?id=<?= (int)$tpl['id'] ?>&month=<?= $selectedMonth ?>">
                        <?= e($tpl['title']) ?>
                      </a>
                    </h4>
                    <div class="field-reason-box">
                      <i class="bi bi-sparkles me-1"></i><strong>Vì sao hợp <?= e($monthInfo['name']) ?>:</strong>
                      <?= e($tpl['month_reason'] ?: $tpl['seasonal_reason']) ?>
                    </div>
                    <div class="field-slip-footer">
                      <div>
                        <div class="small" style="color: var(--cin-ink-muted); font-size: 0.74rem;">Ngân sách tham khảo</div>
                        <div class="price-mono"><?= format_vnd($tpl['ref_budget_per_person']) ?><small class="fw-normal text-muted">/người</small></div>
                      </div>
                      <a href="template_detail.php?id=<?= (int)$tpl['id'] ?>&month=<?= $selectedMonth ?>" class="btn-cin-ink">
                        Xem chi tiết <i class="bi bi-arrow-right"></i>
                      </a>
                    </div>
                  </div>
                </article>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- ========================================================================
       SCENE 3: CHRONICLE — LUỒNG HOẠT ĐỘNG 4 BƯỚC (LATERAL DOLLY TRACK)
       ======================================================================== -->
  <section class="scene-chronicle" data-scene="chronicle" data-still="0.5">
    <div class="frame">
      <div class="chronicle-header">
        <div>
          <span class="cin-eyebrow" style="color: var(--cin-amber-bright);">Cơ chế Lập kế hoạch · 4 Trạm</span>
          <h2 class="cin-display-h2 text-white mb-0">
            <span id="chronicleManifesto">Từ lịch trình mẫu đến chuyến đi độc lập chỉ qua 4 bước</span>
          </h2>
        </div>
        <span class="font-monospace small" style="color: rgba(246,238,224,0.7);">
          CUỘN ĐỂ DI CHUYỂN QUA CÁC TRẠM &rarr;
        </span>
      </div>

      <div class="chronicle-rail-viewport">
        <div class="chronicle-track" id="chronicleTrack">
          <article class="chronicle-step-card">
            <div>
              <div class="step-index-row">
                <span class="step-num">01</span>
                <span class="step-icon-badge"><i class="bi bi-calendar2-check-fill"></i></span>
              </div>
              <h3 class="h5 fw-bold mb-2" style="font-family: var(--font-display);">1. Chọn tháng & Xem mẫu</h3>
              <p class="small mb-0" style="color: var(--cin-ink-muted);">
                Tham khảo lịch trình mẫu theo 12 tháng kèm điểm tham quan, quán ăn, lưu trú và dự toán.
              </p>
            </div>
            <div class="mt-3 pt-2 border-top small font-monospace text-uppercase" style="color: var(--cin-jade);">
              Trạm 01 · Định vị mùa đẹp nhất
            </div>
          </article>

          <article class="chronicle-step-card">
            <div>
              <div class="step-index-row">
                <span class="step-num">02</span>
                <span class="step-icon-badge"><i class="bi bi-copy"></i></span>
              </div>
              <h3 class="h5 fw-bold mb-2" style="font-family: var(--font-display);">2. Sao chép thành chuyến đi riêng</h3>
              <p class="small mb-0" style="color: var(--cin-ink-muted);">
                Nhấn "Dùng lịch trình này", hệ thống tự động quy đổi ngày thực tế và tạo kế hoạch độc lập.
              </p>
            </div>
            <div class="mt-3 pt-2 border-top small font-monospace text-uppercase" style="color: var(--cin-jade);">
              Trạm 02 · Nhân bản 1 cú nhấp
            </div>
          </article>

          <article class="chronicle-step-card">
            <div>
              <div class="step-index-row">
                <span class="step-num">03</span>
                <span class="step-icon-badge"><i class="bi bi-sliders"></i></span>
              </div>
              <h3 class="h5 fw-bold mb-2" style="font-family: var(--font-display);">3. Tùy chỉnh & Quản lý ngân sách</h3>
              <p class="small mb-0" style="color: var(--cin-ink-muted);">
                Đổi quán ăn, nơi ở, theo dõi chi phí Dự kiến vs Thực tế (không tính trùng) và Checklist.
              </p>
            </div>
            <div class="mt-3 pt-2 border-top small font-monospace text-uppercase" style="color: var(--cin-jade);">
              Trạm 03 · Cân đối ngân sách
            </div>
          </article>

          <article class="chronicle-step-card">
            <div>
              <div class="step-index-row">
                <span class="step-num">04</span>
                <span class="step-icon-badge"><i class="bi bi-share-fill"></i></span>
              </div>
              <h3 class="h5 fw-bold mb-2" style="font-family: var(--font-display);">4. Xem Bản đồ & Chia sẻ</h3>
              <p class="small mb-0" style="color: var(--cin-ink-muted);">
                Xem điểm dừng theo ngày trên bản đồ Leaflet, mở Google Maps chỉ đường và chia sẻ link chỉ đọc.
              </p>
            </div>
            <div class="mt-3 pt-2 border-top small font-monospace text-uppercase" style="color: var(--cin-jade);">
              Trạm 04 · Khởi hành & Chia sẻ
            </div>
          </article>
        </div>
      </div>

      <!-- Lead Actor State 4: Northern Route Elevation Progress Line -->
      <div class="chronicle-route-bar" aria-hidden="true">
        <span>KM 00 · HÀ NỘI</span>
        <svg class="route-line-svg" viewBox="0 0 600 18" preserveAspectRatio="none">
          <line x1="0" y1="9" x2="600" y2="9" stroke="rgba(246, 238, 224, 0.2)" stroke-width="2" stroke-dasharray="4 4"/>
          <line id="chronicleRouteProgress" x1="0" y1="9" x2="300" y2="9" stroke="#f59e0b" stroke-width="3"/>
        </svg>
        <span>ĐỈNH HÀNH TRÌNH · MIỀN BẮC</span>
      </div>
    </div>
  </section>

  <!-- ========================================================================
       SCENE 4: LOCATIONS — ĐỊA ĐIỂM THAM QUAN, ĂN UỐNG & LƯU TRÚ (FLOW)
       ======================================================================== -->
  <section class="scene-locations" data-scene="locations" data-flow>
    <div class="frame">
      <div class="locations-container">
        <div class="almanac-top-bar">
          <div>
            <span class="cin-eyebrow" style="color: var(--cin-jade);">Dữ liệu chuẩn hóa 3 nhóm</span>
            <h2 class="cin-display-h2 mt-1">Điểm tham quan, Quán ngon & <span class="sw">Lưu trú chọn lọc</span></h2>
            <p class="mb-0" style="color: var(--cin-ink-muted);">
              Mỗi địa điểm đều có giá tham khảo, lưu ý theo mùa, tọa độ bản đồ và đánh giá thực tế.
            </p>
          </div>
          <div class="d-flex flex-wrap gap-2" role="toolbar" aria-label="Bộ lọc nhóm địa điểm">
            <button type="button" class="btn-cin-outline active" data-loc-filter="all">
              Tất cả (<?= count($featuredLocations) ?>)
            </button>
            <a href="locations.php?type=attraction" class="btn-cin-outline" data-loc-filter="attraction">
              <i class="bi bi-compass"></i>Tham quan
            </a>
            <a href="locations.php?type=food" class="btn-cin-outline" data-loc-filter="food">
              <i class="bi bi-cup-hot"></i>Ăn uống
            </a>
            <a href="locations.php?type=stay" class="btn-cin-outline" data-loc-filter="stay">
              <i class="bi bi-house-heart"></i>Lưu trú
            </a>
            <a href="locations.php" class="btn-cin-ink">
              Tất cả &rarr;
            </a>
          </div>
        </div>

        <div class="row g-4" id="featuredLocationsGrid">
          <?php foreach ($featuredLocations as $loc): ?>
            <?php
              $typeMeta = location_type_meta($loc['location_type']);
              $isFav = in_array((int)$loc['id'], $userFavIds, true);
            ?>
            <div class="col-md-6 col-lg-4 loc-card-col" data-loc-type="<?= e($loc['location_type']) ?>">
              <article class="field-slip">
                <div class="field-slip-media" style="height: 205px;">
                  <img src="<?= e($loc['thumbnail_url']) ?>" alt="<?= e($loc['name']) ?>" data-grade>
                  <div class="field-slip-badges">
                    <span class="slip-tag">
                      <i class="bi <?= e($typeMeta['icon']) ?> me-1"></i><?= e($typeMeta['label']) ?>
                    </span>
                  </div>
                  <div class="card-fav-overlay">
                    <form action="favorites.php" method="post" class="m-0">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="toggle">
                      <input type="hidden" name="location_id" value="<?= (int)$loc['id'] ?>">
                      <input type="hidden" name="redirect" value="<?= e($_SERVER['REQUEST_URI'] ?? 'index.php') ?>">
                      <button type="submit" class="btn btn-light btn-sm rounded-circle shadow-sm" title="<?= $isFav ? 'Bỏ yêu thích' : 'Lưu yêu thích' ?>" aria-label="<?= $isFav ? 'Bỏ yêu thích' : 'Lưu yêu thích' ?> <?= e($loc['name']) ?>">
                        <i class="bi <?= $isFav ? 'bi-heart-fill text-danger' : 'bi-heart text-secondary' ?>"></i>
                      </button>
                    </form>
                  </div>
                </div>
                <div class="field-slip-body">
                  <!-- Lead Actor State 5: GPS Coordinate Verification Strip -->
                  <div class="loc-coord-strip">
                    <span><i class="bi bi-geo-alt-fill text-success me-1"></i><?= e($loc['province_name']) ?></span>
                    <span><?= number_format((float)$loc['latitude'], 2) ?>°N · <?= number_format((float)$loc['longitude'], 2) ?>°E</span>
                  </div>

                  <div class="d-flex justify-content-between align-items-baseline mb-1">
                    <h3 class="field-slip-title mb-0">
                      <a href="location_detail.php?id=<?= (int)$loc['id'] ?>"><?= e($loc['name']) ?></a>
                    </h3>
                    <?php if ($loc['review_count'] > 0): ?>
                      <span class="small fw-bold" style="color: var(--cin-amber); white-space: nowrap;">
                        <i class="bi bi-star-fill"></i> <?= number_format((float)$loc['avg_rating'], 1) ?>
                        <span class="text-muted fw-normal">(<?= (int)$loc['review_count'] ?>)</span>
                      </span>
                    <?php endif; ?>
                  </div>

                  <p class="small mb-3" style="color: var(--cin-ink-muted);"><?= e($loc['short_desc']) ?></p>

                  <div class="field-slip-footer">
                    <div>
                      <span class="small d-block" style="color: var(--cin-ink-muted); font-size: 0.72rem;">Giá tham khảo</span>
                      <span class="price-mono">
                        <?= format_vnd($loc['ref_price'], true) ?>
                        <?php if ((float)$loc['ref_price'] > 0): ?>
                          <small class="text-muted fw-normal">/<?= e($loc['price_unit']) ?></small>
                        <?php endif; ?>
                      </span>
                    </div>
                    <div class="d-flex gap-2">
                      <a href="<?= e(google_maps_view_url($loc['latitude'], $loc['longitude'], $loc['google_maps_url'])) ?>"
                         target="_blank" rel="noopener" class="btn-cin-outline px-2" title="Mở Google Maps" aria-label="Mở Google Maps <?= e($loc['name']) ?>">
                        <i class="bi bi-map text-danger"></i>
                      </a>
                      <a href="location_detail.php?id=<?= (int)$loc['id'] ?>" class="btn-cin-ink">
                        Chi tiết
                      </a>
                    </div>
                  </div>
                </div>
              </article>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </section>

  <!-- ========================================================================
       SCENE 5: WITNESSES — GÓC TRẢI NGHIỆM CỘNG ĐỒNG MỚI NHẤT (FLOW)
       ======================================================================== -->
  <section class="scene-witnesses" data-scene="witnesses" data-flow>
    <div class="frame">
      <div class="witnesses-container">
        <div class="almanac-top-bar" style="border-bottom-color: var(--cin-rule-light);">
          <div>
            <span class="cin-eyebrow" style="color: var(--cin-amber-bright);">Review thực tế đã kiểm duyệt</span>
            <h2 class="cin-display-h2 text-white mt-1">Trải nghiệm <span class="sw">Cộng đồng</span></h2>
            <p class="mb-0" style="color: var(--cin-cream-muted);">
              Đọc kinh nghiệm thực tế về điểm tham quan, quán ăn, homestay từ những người vừa trở về.
            </p>
          </div>
          <div class="d-flex flex-wrap gap-2">
            <a href="experience_form.php" class="btn-cin-amber">
              <i class="bi bi-pencil-square"></i>Chia sẻ trải nghiệm
            </a>
            <a href="experiences.php" class="btn-cin-ghost">
              Xem tất cả bài viết &rarr;
            </a>
          </div>
        </div>

        <div class="row g-4">
          <?php foreach ($latestExperiences as $idx => $post): ?>
            <?php $locMeta = location_type_meta($post['location_type']); ?>
            <div class="col-lg-4">
              <article class="journal-slip">
                <?php if (!empty($post['first_image'])): ?>
                  <div class="field-slip-media" style="height: 195px;">
                    <img src="<?= e($post['first_image']) ?>" alt="<?= e($post['title']) ?>" data-grade>
                    <div class="field-slip-badges">
                      <span class="slip-tag">
                        Trải nghiệm T<?= (int)$post['visit_month'] ?>/<?= (int)$post['visit_year'] ?>
                      </span>
                    </div>
                  </div>
                <?php endif; ?>
                <div class="field-slip-body">
                  <div class="d-flex justify-content-between align-items-center mb-2">
                    <a href="location_detail.php?id=<?= (int)$post['location_id'] ?>" class="badge <?= e($locMeta['badge']) ?> rounded-1 px-2 py-1">
                      <i class="bi bi-geo-alt-fill me-1"></i><?= e($post['location_name']) ?>
                    </a>
                    <span class="small fw-bold" style="color: var(--cin-amber);">
                      <?php for ($i = 1; $i <= 5; $i++): ?>
                        <i class="bi <?= $i <= (int)$post['rating'] ? 'bi-star-fill' : 'bi-star' ?>"></i>
                      <?php endfor; ?>
                    </span>
                  </div>

                  <h3 class="field-slip-title">
                    <a href="experiences.php?post_id=<?= (int)$post['id'] ?>"><?= e($post['title']) ?></a>
                  </h3>

                  <p class="small mb-3" style="color: var(--cin-ink-muted);">
                    <?= e(mb_strimwidth($post['content'], 0, 145, '...')) ?>
                  </p>

                  <?php if (!empty($post['tips_advice'])): ?>
                    <div class="journal-tip-box">
                      <i class="bi bi-lightbulb-fill text-warning me-1"></i><strong>Lời khuyên:</strong>
                      <span><?= e(mb_strimwidth($post['tips_advice'], 0, 95, '...')) ?></span>
                    </div>
                  <?php endif; ?>

                  <div class="field-slip-footer small">
                    <div class="d-flex align-items-center gap-2">
                      <img src="<?= e($post['author_avatar'] ?: 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=100') ?>"
                           alt="<?= e($post['author_name']) ?>" class="rounded-circle object-fit-cover" width="26" height="26" data-grade="off">
                      <span class="fw-semibold"><?= e($post['author_name']) ?></span>
                    </div>
                    <a href="experiences.php?post_id=<?= (int)$post['id'] ?>" class="fw-bold" style="color: var(--cin-jade);">Đọc tiếp &rarr;</a>
                  </div>
                </div>
              </article>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </section>

  <!-- ========================================================================
       SCENE 6: DEPARTURE — FINAL IMAGE / CỘT MỐC KM0 KHỞI HÀNH (PINNED HOLD)
       ======================================================================== -->
  <section class="scene-departure" data-scene="departure" data-hold data-still="0.7">
    <div class="frame">
      <div class="km0-monument-box">
        <!-- Lead Actor State 6: Km0 Compass Locked at 0° North -->
        <div class="km0-compass-mini" aria-hidden="true">
          <svg viewBox="0 0 100 100" width="100%" height="100%">
            <circle cx="50" cy="50" r="44" fill="none" stroke="rgba(245, 158, 11, 0.55)" stroke-width="1.5"/>
            <circle cx="50" cy="50" r="32" fill="none" stroke="rgba(246, 238, 224, 0.25)" stroke-width="1" stroke-dasharray="2 3"/>
            <g id="km0FinalNeedle">
              <polygon points="50,8 55,50 50,42 45,50" fill="#f59e0b"/>
              <polygon points="50,92 54,50 50,58 46,50" fill="rgba(246, 238, 224, 0.4)"/>
              <circle cx="50" cy="50" r="4" fill="#f4efe4" stroke="#d97706" stroke-width="2"/>
            </g>
          </svg>
        </div>

        <span class="cin-eyebrow justify-content-center mb-2" style="color: var(--cin-amber-bright);">
          CỘT MỐC KM0 · BẮC DU TRAVEL PLANNER
        </span>
        <h2 class="cin-display-h2 text-white mb-3" id="finalSlitTitle">
          Mùa đẹp nhất đang gọi tên bạn.
        </h2>
        <p class="mb-4 mx-auto" style="color: var(--cin-cream-muted); max-width: 54ch; font-size: 0.96rem;">
          Chọn ngay lịch trình mẫu <?= strtolower(e($monthInfo['name'])) ?> (<?= e($monthInfo['tag']) ?>) hoặc tự khởi tạo chuyến đi miền Bắc với dự toán ngân sách và bản đồ điểm dừng riêng.
        </p>
        <div class="d-flex flex-wrap justify-content-center gap-3">
          <a href="templates.php?month=<?= $selectedMonth ?>" class="btn-cin-amber">
            <i class="bi bi-compass"></i>Khám phá lịch trình <?= e($monthInfo['name']) ?>
          </a>
          <a href="trips.php?action=new" class="btn-cin-ghost">
            <i class="bi bi-luggage"></i>Tự tạo chuyến đi mới
          </a>
        </div>
      </div>
    </div>
  </section>

</main>

<!-- Cinematic UI Kit Scripts (GSAP + Lenis + Reel + Grade + TypeFx) -->
<script src="https://cdn.jsdelivr.net/npm/gsap@3.15.0/dist/gsap.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/lenis@1.3.26/dist/lenis.min.js"></script>
<script type="module">
  import { createReel } from './assets/kit/reel.js';
  import { createGrade, applyLook } from './assets/kit/grade.js';
  import { typeFx } from './assets/kit/type-fx.js';

  const reduced = matchMedia('(prefers-reduced-motion: reduce)').matches;
  const lenis = reduced || typeof Lenis === 'undefined' ? null : new Lenis({ autoRaf: false, lerp: 0.1 });
  const reel = createReel({ lenis });

  // 1. Cast the Page's Font Face into Type-FX
  typeFx.defaults({ font: 'inherit', weight: 'inherit' });

  const heroBlur = typeFx(document.querySelector('#heroBlurTitle'), 'blur-resolve', { by: 'word' });
  const manifestoFill = typeFx(document.querySelector('#chronicleManifesto'), 'read-fill', { by: 'word' });
  const finalSlit = typeFx(document.querySelector('#finalSlitTitle'), 'slit-scan');

  // 2. Apply the Researched Colour Grade ("The Vertical Ray of the Sun" — Mark Lee Ping-bin)
  const gradeSpec = {
    ground: '#0b1914',
    ink: '#f4efe4',
    accent: '#d97706',
    palette: [
      '#0b1914', '#10241c', '#12241c', '#164e3a', '#226b50',
      '#f4efe4', '#eae1cf', '#efe8d8', '#fbf8f1', '#14221b',
      '#4a5d52', '#d97706', '#f59e0b', '#5c3404', '#8b7e6a'
    ]
  };
  const grade = createGrade({ clock: reel, spec: gradeSpec, tier: 'lite' });
  applyLook(grade.look, {
    contrast: 1.12,
    saturation: 1.16,
    temperature: 0.16,
    tint: -0.06,
    lift: 0.02,
    split: 0.35,
    shadows: '#0e291f',
    highlights: '#f7dfb9',
    grain: 0.025,
    vignette: 0.18,
    halation: 0.14,
    halationColor: '#e08b3e',
    bloom: 0.12
  });

  // 3. Opening Titles (Intro)
  reel.intro((tl) => {
    tl.from('.arrival-meta-bar', { opacity: 0, y: 12, duration: 0.6, ease: 'power3.out' }, 0)
      .from('.dispatch-console', { opacity: 0, y: 18, duration: 0.75, ease: 'power3.out' }, 0.15);
  });

  // 4. Scene 1: Arrival — Shutter Aperture Opens & Compass Rose Calibrates
  const baseAngle = <?= (int)$compassAngle ?>;
  const heroState = { rot: baseAngle - 35, scale: 0.92 };
  const needleEl = document.getElementById('heroCompassNeedle');
  const stageEl = document.getElementById('heroCompassStage');

  reel.scene('arrival', ({ main, q }) => {
    main.fromTo(heroBlur, { p: 0.35 }, { p: 1, duration: 0.35 }, 0)
        .fromTo(q('.shutter-left'), { xPercent: 0 }, { xPercent: -92, ease: 'power1.inOut' }, 0)
        .fromTo(q('.shutter-right'), { xPercent: 0 }, { xPercent: 92, ease: 'power1.inOut' }, 0)
        .fromTo(q('.arrival-plate-img'), { scale: 1.0 }, { scale: 1.12 }, 0)
        .fromTo(q('.arrival-sun-shaft'), { xPercent: -18, opacity: 0.65 }, { xPercent: 18, opacity: 1 }, 0)
        .fromTo(heroState, { rot: baseAngle - 35, scale: 0.9 }, { rot: baseAngle + 25, scale: 1.16 }, 0);
  });

  // 5. Scene 3: Chronicle — 4-Step Lateral Dolly Track
  const routeProg = { x2: 120 };
  const routeLine = document.getElementById('chronicleRouteProgress');

  reel.scene('chronicle', ({ main, frame }) => {
    const track = document.getElementById('chronicleTrack');
    main.fromTo(manifestoFill, { p: 0.25 }, { p: 1, duration: 0.45 }, 0)
        .fromTo(
          track,
          { x: () => Math.min(80, frame.clientWidth * 0.06) },
          { x: () => -Math.max(180, track.scrollWidth - frame.clientWidth + 48), ease: 'none' },
          0
        )
        .fromTo(routeProg, { x2: 90 }, { x2: 600 }, 0);
  });

  // 6. Scene 5 & 6: Witnesses Tip Highlighter & Departure Km0 Compass Lock
  const km0State = { rot: -48 };
  const km0Needle = document.getElementById('km0FinalNeedle');

  reel.scene('departure', ({ main, q }) => {
    main.fromTo(finalSlit, { p: 0 }, { p: 1, duration: 0.45 }, 0)
        .fromTo(km0State, { rot: -48 }, { rot: 0, duration: 0.65, ease: 'power2.out' }, 0)
        .fromTo(q('.km0-monument-box'), { scale: 0.96 }, { scale: 1.0, duration: 0.6 }, 0);
  });

  // Frame-by-frame deterministic application for SVG pose objects & HUD instruments
  const hudScene = document.getElementById('hudSceneCode');
  const hudLat = document.getElementById('hudLatCode');

  reel.onFrame((f) => {
    if (needleEl) needleEl.setAttribute('transform', `rotate(${heroState.rot.toFixed(1)} 120 120)`);
    if (stageEl) stageEl.style.transform = `translate(-50%, -50%) scale(${heroState.scale.toFixed(3)})`;
    if (routeLine) routeLine.setAttribute('x2', routeProg.x2.toFixed(1));
    if (km0Needle) km0Needle.setAttribute('transform', `rotate(${km0State.rot.toFixed(1)} 50 50)`);

    document.documentElement.style.setProperty('--page-prog', f.progress.toFixed(3));
    if (hudScene && f.scene) {
      hudScene.textContent = `SC.0${f.scene.index + 1}`;
    }
    if (hudLat) {
      const deg = 20 + f.progress * 3;
      const min = Math.floor((deg % 1) * 60);
      hudLat.textContent = `${Math.floor(deg)}°${String(min).padStart(2, '0')}'N`;
    }
  });

  reel.start();

  // Ensure keyboard focus inside any unpinned/mobile or flow section scrolls cleanly past sticky navbar
  addEventListener('focusin', (e) => {
    const el = e.target;
    if (!el || !(el instanceof HTMLElement) || el.classList.contains('skip-film-link')) return;
    if (el.closest('.navbar-bacdu')) return;
    const r = el.getBoundingClientRect();
    if (r.top < 78 || r.bottom > innerHeight - 16) {
      const targetY = Math.max(0, window.scrollY + r.top - Math.round(innerHeight * 0.35));
      if (lenis) lenis.scrollTo(targetY, { immediate: true, force: true });
      else window.scrollTo(0, targetY);
    }
  });

  // 7. Audience's Part: Interactive 12-Month Almanac Dial Preview (Hover/Focus)
  const monthCells = document.querySelectorAll('.almanac-month-cell');
  const liveNeedle = document.getElementById('liveCompassNeedle');
  const liveSeason = document.getElementById('liveCompassSeason');
  const liveName = document.getElementById('liveCompassMonthName');
  const liveHighlight = document.getElementById('liveCompassHighlight');
  const liveAction = document.getElementById('liveCompassAction');

  function previewMonth(cell) {
    const m = parseInt(cell.dataset.month || '2', 10);
    const angle = (m - 1) * 30;
    if (liveNeedle) liveNeedle.style.transform = `rotate(${angle}deg)`;
    if (liveSeason) liveSeason.textContent = cell.dataset.season || '';
    if (liveName) liveName.textContent = `${cell.dataset.name} — ${cell.dataset.tag}`;
    if (liveHighlight) liveHighlight.textContent = `Tâm điểm: ${cell.dataset.highlight}`;
    if (liveAction) {
      liveAction.href = `templates.php?month=${m}`;
      liveAction.innerHTML = `<i class="bi bi-compass"></i>Mở mẫu ${cell.dataset.name}`;
    }
  }

  monthCells.forEach((cell) => {
    cell.addEventListener('mouseenter', () => previewMonth(cell));
    cell.addEventListener('focus', () => previewMonth(cell));
  });

  // 8. Interactive Category Filter Lens on Featured Locations (Scene 4)
  const filterBtns = document.querySelectorAll('[data-loc-filter]');
  const locCols = document.querySelectorAll('.loc-card-col');
  filterBtns.forEach((btn) => {
    if (btn.tagName === 'BUTTON') {
      btn.addEventListener('click', () => {
        filterBtns.forEach((b) => b.classList.remove('active'));
        btn.classList.add('active');
        locCols.forEach((col) => { col.hidden = false; });
        reel.measure();
      });
    }
  });
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
