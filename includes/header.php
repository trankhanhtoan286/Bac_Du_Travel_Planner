<?php
require_once __DIR__ . '/helpers.php';

$currentUser = current_user();
$currentPage = basename($_SERVER['PHP_SELF'] ?? 'index.php');
$pageTitle = isset($pageTitle) ? $pageTitle . ' | ' . APP_NAME : APP_NAME . ' — ' . APP_TAGLINE;
$flashes = get_flashes();

// Đếm số địa điểm yêu thích và số bài chờ duyệt (cho Admin) trên thanh điều hướng
$pdo = get_db();
$favCount = 0;
$pendingPostsCount = 0;
if ($currentUser) {
    $stmtFav = $pdo->prepare('SELECT COUNT(*) FROM favorite_locations WHERE user_id = ?');
    $stmtFav->execute([$currentUser['id']]);
    $favCount = (int)$stmtFav->fetchColumn();

    if ($currentUser['role'] === 'admin') {
        $pendingPostsCount = (int)$pdo->query("SELECT COUNT(*) FROM experience_posts WHERE status = 'pending'")->fetchColumn();
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($pageTitle) ?></title>
  <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64'%3E%3Crect width='64' height='64' rx='12' fill='%230b1914'/%3E%3Cpath d='M12 46L26 22l10 15 6-9 10 18H12z' fill='%23f59e0b'/%3E%3Ccircle cx='46' cy='18' r='5' fill='%23fef3c7'/%3E%3C/svg%3E">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:ital,wght@0,400;0,500;0,600;0,700;1,400&family=JetBrains+Mono:wght@400;500;600;700&family=Playfair+Display:ital,wght@0,600;0,700;0,800;1,600;1,700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
  <link rel="stylesheet" href="assets/css/style.css?v=13">
  <?php if ($currentPage === 'index.php'): ?>
  <link rel="stylesheet" href="assets/kit/reel.css?v=13">
  <?php endif; ?>
  <link rel="stylesheet" href="assets/css/cinematic-landing.css?v=13">
  <link rel="stylesheet" href="assets/css/cinematic-subpages.css?v=13">
</head>
<?php
  $appModePages = ['trips.php', 'trip_detail.php', 'admin.php', 'dashboard.php', 'profile.php', 'experience_form.php'];
  $bodyModeClass = $currentPage === 'index.php'
      ? 'cinematic-home'
      : (in_array($currentPage, $appModePages, true) ? 'cinematic-app' : 'cinematic-subpage');
?>
<body class="<?= $bodyModeClass ?>">
<?php if ($currentPage === 'index.php'): ?>
<a href="#monthly-recommendations" class="skip-film-link">Chuyển tới Lịch trình 12 tháng</a>
<?php else: ?>
<a href="#mainContent" class="skip-film-link">Chuyển tới Nội dung chính</a>
<?php endif; ?>

<!-- Thanh điều hướng ngang Cinematic Lacquer -->
<nav class="navbar navbar-expand-lg navbar-bacdu sticky-top">
  <div class="container-fluid px-3 px-xl-4">
    <a class="navbar-brand brand-logo text-nowrap" href="index.php">
      <div>
        <span class="d-block lh-1" style="font-family: var(--font-display, 'Playfair Display', serif); font-size: 1.45rem; font-weight: 700; letter-spacing: -0.01em;"><?= e(APP_NAME) ?></span>
        <small class="fw-semibold" style="font-family: var(--font-mono, monospace); font-size: 0.65rem; letter-spacing: 0.08em; color: #f59e0b;">Miền Bắc · 12 Tháng</small>
      </div>
    </a>

    <button class="navbar-toggler border-0 shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbar" aria-controls="mainNavbar" aria-expanded="false" aria-label="Menu">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="mainNavbar">
      <ul class="navbar-nav ms-auto mb-2 mb-lg-0 gap-1 flex-nowrap align-items-center me-lg-2 me-xl-3">
        <li class="nav-item">
          <a class="nav-link text-nowrap <?= $currentPage === 'index.php' ? 'active' : '' ?>" href="index.php">
            <i class="bi bi-house-door me-1"></i>Trang chủ
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link text-nowrap <?= in_array($currentPage, ['templates.php', 'template_detail.php'], true) ? 'active' : '' ?>" href="templates.php">
            <i class="bi bi-calendar2-range me-1"></i>Lịch trình 12 tháng
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link text-nowrap <?= in_array($currentPage, ['locations.php', 'location_detail.php'], true) ? 'active' : '' ?>" href="locations.php">
            <i class="bi bi-geo-alt me-1"></i>Địa điểm và Bản đồ
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link text-nowrap <?= in_array($currentPage, ['experiences.php', 'experience_form.php'], true) ? 'active' : '' ?>" href="experiences.php">
            <i class="bi bi-journal-richtext me-1"></i>Trải nghiệm cộng đồng
          </a>
        </li>
        <li class="nav-item">
          <?php if (!$currentUser && $currentPage === 'auth.php'): ?>
            <a class="nav-link text-nowrap" href="#login_identifier"
               onclick="event.preventDefault(); const el=document.getElementById('login_identifier')||document.querySelector('.auth-form-pane input'); if(el){el.focus();} if(window.showBacDuToast){window.showBacDuToast('warning','Vui lòng đăng nhập bên dưới để mở không gian <strong>Chuyến đi của tôi</strong>.');}">
              <i class="bi bi-luggage me-1"></i>Chuyến đi của tôi<i class="bi bi-lock-fill ms-1 opacity-50" style="font-size: 0.65rem;" title="Yêu cầu đăng nhập"></i>
            </a>
          <?php else: ?>
            <a class="nav-link text-nowrap <?= in_array($currentPage, ['trips.php', 'trip_detail.php'], true) ? 'active' : '' ?>" href="trips.php">
              <i class="bi bi-luggage me-1"></i>Chuyến đi của tôi<?php if (!$currentUser): ?><i class="bi bi-lock-fill ms-1 opacity-50" style="font-size: 0.65rem;" title="Yêu cầu đăng nhập"></i><?php endif; ?>
            </a>
          <?php endif; ?>
        </li>
        <?php if ($currentUser && $currentUser['role'] === 'admin'): ?>
        <li class="nav-item">
          <a class="nav-link text-nowrap <?= $currentPage === 'admin.php' ? 'active' : '' ?> fw-bold d-inline-flex align-items-center" style="color: #fbbf24;" href="admin.php">
            <i class="bi bi-shield-lock-fill me-1"></i><span>Quản trị</span>
            <?php if ($pendingPostsCount > 0): ?>
              <span class="slip-tag slip-tag-amber py-0 px-1 ms-1" style="font-size: 0.65rem;"><?= $pendingPostsCount ?></span>
            <?php endif; ?>
          </a>
        </li>
        <?php endif; ?>
      </ul>

      <?php if ($currentUser || $currentPage !== 'auth.php'): ?>
      <div class="d-flex align-items-center gap-2 flex-nowrap">
        <?php if ($currentUser): ?>
          <a href="favorites.php" class="btn-cin-ghost py-1 px-2 position-relative text-nowrap d-inline-flex align-items-center gap-1" title="Sổ tay tọa độ yêu thích">
            <i class="bi bi-heart-fill text-danger"></i>
            <span class="d-none d-xl-inline small">Đã lưu</span>
            <span id="navFavBadge" class="slip-tag slip-tag-amber py-0 px-1 <?= $favCount > 0 ? '' : 'd-none' ?>" style="font-size: 0.65rem;"><?= $favCount ?></span>
          </a>

          <div class="dropdown">
            <button class="cin-account-chip dropdown-toggle text-nowrap" type="button" data-bs-toggle="dropdown" aria-expanded="false">
              <img src="<?= e(get_avatar_url($currentUser['avatar_url'])) ?>"
                   alt="Avatar" class="cin-account-avatar" width="28" height="28">
              <span class="cin-account-name text-truncate"><?= e($currentUser['full_name']) ?></span>
              <?php if ($currentUser['role'] === 'admin'): ?>
                <span class="cin-account-role">ADMIN</span>
              <?php endif; ?>
            </button>
            <ul class="dropdown-menu dropdown-menu-end cin-account-menu">
              <li class="cin-account-menu-head">
                <div class="d-flex align-items-center justify-content-between mb-1">
                  <span class="cin-eyebrow" style="color: var(--cin-amber-bright); font-size: 0.65rem;">Hồ sơ định danh</span>
                  <span class="font-monospace" style="font-size: 0.65rem; color: rgba(244,239,228,0.6);">ID #<?= (int)$currentUser['id'] ?></span>
                </div>
                <div class="fw-bold text-white" style="font-family: var(--font-display); font-size: 1.1rem;"><?= e($currentUser['full_name']) ?></div>
                <div class="font-monospace" style="font-size: 0.72rem; color: rgba(244,239,228,0.68);">@<?= e($currentUser['username']) ?> · <?= e($currentUser['email']) ?></div>
              </li>
              <li><a class="dropdown-item cin-account-item <?= $currentPage === 'dashboard.php' ? 'active' : '' ?>" href="dashboard.php"><i class="bi bi-grid-1x2 text-warning"></i><span>Tổng quan cá nhân</span></a></li>
              <li><a class="dropdown-item cin-account-item <?= in_array($currentPage, ['trips.php', 'trip_detail.php'], true) ? 'active' : '' ?>" href="trips.php"><i class="bi bi-luggage text-info"></i><span>Chuyến đi của tôi</span></a></li>
              <li><a class="dropdown-item cin-account-item <?= $currentPage === 'favorites.php' ? 'active' : '' ?>" href="favorites.php"><i class="bi bi-heart-fill text-danger"></i><span>Sổ tay tọa độ (<span id="menuFavCount"><?= $favCount ?></span>)</span></a></li>
              <li><a class="dropdown-item cin-account-item <?= $currentPage === 'experience_form.php' ? 'active' : '' ?>" href="experience_form.php"><i class="bi bi-pencil-square text-warning"></i><span>Viết nhật ký thực địa</span></a></li>
              <li><a class="dropdown-item cin-account-item <?= $currentPage === 'profile.php' ? 'active' : '' ?>" href="profile.php"><i class="bi bi-person-gear text-light"></i><span>Hồ sơ và Đổi mật khẩu</span></a></li>
              <?php if ($currentUser['role'] === 'admin'): ?>
                <li><hr class="cin-account-divider"></li>
                <li><a class="dropdown-item cin-account-item fw-bold <?= $currentPage === 'admin.php' ? 'active' : '' ?>" style="color: #fbbf24;" href="admin.php"><i class="bi bi-speedometer2"></i><span>Quản trị và Ban biên tập</span></a></li>
              <?php endif; ?>
              <li><hr class="cin-account-divider"></li>
              <li>
                <form action="auth.php" method="post" class="m-0">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="logout">
                  <button type="submit" class="dropdown-item cin-account-item text-danger">
                    <i class="bi bi-box-arrow-right"></i><span>Đăng xuất khỏi hệ thống</span>
                  </button>
                </form>
              </li>
            </ul>
          </div>
        <?php else: ?>
          <a href="auth.php?tab=login" class="btn-cin-ghost py-1 px-3">Đăng nhập</a>
          <a href="auth.php?tab=register" class="btn-cin-amber py-1 px-3">Đăng ký</a>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>
</nav>

<div class="cin-toast-stack" id="cinToastStack" aria-live="polite">
<?php if (!empty($flashes)): ?>
  <?php foreach ($flashes as $fl): ?>
    <?php
      $fType = $fl['type'] ?? 'info';
      $fIcon = match ($fType) {
          'success' => 'bi-check-circle-fill text-success',
          'warning' => 'bi-exclamation-triangle-fill text-warning',
          'danger'  => 'bi-exclamation-octagon-fill text-danger',
          default   => 'bi-info-circle-fill text-info',
      };
    ?>
    <div class="alert alert-dismissible fade show cin-flash-banner cin-flash-<?= e($fType) ?> d-flex align-items-start gap-2" role="alert">
      <i class="bi <?= $fIcon ?> fs-6 mt-1 flex-shrink-0"></i>
      <div class="small lh-sm"><?= $fl['message'] ?></div>
      <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Đóng"></button>
    </div>
  <?php endforeach; ?>
<?php endif; ?>
</div>
