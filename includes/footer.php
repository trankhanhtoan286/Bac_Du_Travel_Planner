<footer class="footer-bacdu">
  <div class="container">
    <div class="row g-4 pb-4 border-bottom border-secondary border-opacity-25">
      <div class="col-lg-4">
        <div class="mb-3">
          <h5 class="mb-1 text-white fw-bold font-sans-title"><?= e(APP_NAME) ?> — Travel Planner</h5>
          <small class="text-white-50">Nền tảng lịch trình 12 tháng & kế hoạch du lịch miền Bắc</small>
        </div>
        <p class="small text-white-50 mb-3">
          Giúp bạn trả lời trọn vẹn: Tháng này nên đi đâu ở miền Bắc? Đi mấy ngày, tham quan đâu, ăn ở đâu, nghỉ ở đâu và biến gợi ý của Ban biên tập thành kế hoạch cá nhân độc lập.
        </p>
        <div class="d-flex flex-wrap gap-2 small">
          <span class="slip-tag slip-tag-jade">
            <i class="bi bi-database-check me-1"></i>DB: <?= strtoupper(defined('ACTIVE_DB_DRIVER') ? ACTIVE_DB_DRIVER : 'MYSQL') ?>
          </span>
          <span class="slip-tag slip-tag-amber">
            <i class="bi bi-map me-1"></i>OpenStreetMap + Leaflet
          </span>
        </div>
      </div>

      <div class="col-6 col-lg-2 offset-lg-1">
        <h6 class="text-white fw-bold font-sans-title mb-3">Khám phá theo mùa</h6>
        <ul class="list-unstyled small d-flex flex-column gap-2">
          <li><a href="templates.php?month=2">Tháng 2: Hoa mận Mộc Châu</a></li>
          <li><a href="templates.php?month=3">Tháng 3: Hoa lê Hà Giang</a></li>
          <li><a href="templates.php?month=5">Tháng 5: Lúa vàng Ninh Bình</a></li>
          <li><a href="templates.php?month=7">Tháng 7: Vịnh Hạ Long</a></li>
          <li><a href="templates.php?month=9">Tháng 9: Mùa vàng Sa Pa</a></li>
          <li><a href="templates.php?month=12">Tháng 12: Săn mây Tà Xùa</a></li>
        </ul>
      </div>

      <div class="col-6 col-lg-2">
        <h6 class="text-white fw-bold font-sans-title mb-3">Công cụ lập kế hoạch</h6>
        <ul class="list-unstyled small d-flex flex-column gap-2">
          <li><a href="templates.php">Lịch trình mẫu 12 tháng</a></li>
          <li><a href="locations.php">Điểm tham quan, Ăn uống, Lưu trú</a></li>
          <li><a href="trips.php">Tạo & Quản lý chuyến đi</a></li>
          <li><a href="experiences.php">Trải nghiệm cộng đồng</a></li>
          <li><a href="favorites.php">Địa điểm đã lưu</a></li>
        </ul>
      </div>

      <div class="col-lg-3">
        <h6 class="text-white fw-bold font-sans-title mb-3">Tài khoản Demo nhanh</h6>
        <div class="p-3 rounded-3 bg-white bg-opacity-10 border border-secondary border-opacity-25 small" style="font-size: 0.78rem; line-height: 1.65;">
          <div class="mb-2 pb-1 border-bottom border-secondary border-opacity-25">
            <span class="badge bg-warning text-dark me-1">Admin</span>
            <code class="text-white">admin|123456</code>
          </div>
          <div class="text-white-50 font-monospace">
            <div><code class="text-white">kien|123456</code> (Ngô Xuân Kiên)</div>
            <div><code class="text-white">anh|123456</code> (Lê Thị Ngọc Ánh)</div>
            <div><code class="text-white">tram|123456</code> (Lò Thị Trâm)</div>
            <div><code class="text-white">van|123456</code> (Nguyễn Thị Vân)</div>
            <div><code class="text-white">toan|123456</code> (Trần Khánh Toàn)</div>
          </div>
        </div>
      </div>
    </div>

    <div class="d-flex flex-column flex-md-row justify-content-between align-items-center pt-3 small text-white-50">
      <div>&copy; <?= date('Y') ?> <strong><?= e(APP_NAME) ?> (Travel Planner)</strong> — Đồ án Hệ thống Tham khảo Lịch trình & Lập kế hoạch Du lịch Miền Bắc.</div>
      <div class="mt-2 mt-md-0">Giá cả, giờ mở cửa và mùa cảnh quan là thông tin tham khảo có ngày cập nhật bởi Ban biên tập.</div>
    </div>
  </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="assets/js/app.js"></script>
<script src="assets/js/cinematic-app.js"></script>
</body>
</html>
