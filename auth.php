<?php
require_once __DIR__ . '/includes/helpers.php';

$pdo = get_db();
$tab = $_GET['tab'] ?? 'login';
$redirect = $_GET['redirect'] ?? $_POST['redirect'] ?? 'index.php';

// Nếu người dùng đã đăng nhập mà truy cập GET vào auth.php -> chuyển thẳng về trang đích hoặc Tổng quan cá nhân
if ($_SERVER['REQUEST_METHOD'] === 'GET' && is_logged_in()) {
    $safeTarget = ($redirect !== '' && $redirect !== 'index.php' && !str_starts_with($redirect, 'http') && !str_starts_with($redirect, '//'))
        ? $redirect
        : 'dashboard.php';
    header('Location: ' . $safeTarget);
    exit;
}

// Giữ lại giá trị nhập liệu khi xảy ra lỗi Validate (chống mất dữ liệu Form State Loss)
$oldIdentifier = '';
$oldFullName   = '';
$oldUsername   = '';
$oldEmail      = '';
$oldForgotUser = '';
$oldForgotEmail = '';
$oldForgotPhone = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'logout') {
        unset($_SESSION['user_id']);
        set_flash('info', 'Bạn đã đăng xuất khỏi hệ thống Bắc Du.');
        header('Location: index.php');
        exit;
    }

    if (!verify_csrf()) {
        set_flash('danger', 'Phiên làm việc không hợp lệ, vui lòng thử lại.');
        header('Location: auth.php?tab=' . urlencode($tab) . '&redirect=' . urlencode($redirect));
        exit;
    }

    // UC01.2: Đăng nhập
    if ($action === 'login') {
        $identifier = trim($_POST['identifier'] ?? '');
        $password = (string)($_POST['password'] ?? '');
        $oldIdentifier = $identifier;

        if ($identifier === '' || $password === '') {
            set_flash('danger', 'Vui lòng nhập đầy đủ Email/Username và Mật khẩu.');
        } else {
            $stmt = $pdo->prepare('SELECT * FROM users WHERE username = ? OR email = ? LIMIT 1');
            $stmt->execute([$identifier, $identifier]);
            $user = $stmt->fetch();

            if (!$user || !verify_user_password($password, $user['password_hash'])) {
                set_flash('danger', 'Thông tin đăng nhập không chính xác.');
            } elseif ($user['status'] === 'locked') {
                set_flash('danger', 'Tài khoản của bạn hiện đang bị tạm khóa bởi Quản trị viên.');
            } else {
                $_SESSION['user_id'] = (int)$user['id'];
                set_flash('success', 'Chào mừng trở lại, <strong>' . e($user['full_name']) . '</strong>!');
                $safeRedirect = (str_starts_with($redirect, 'http') || str_starts_with($redirect, '//')) ? 'index.php' : $redirect;
                header('Location: ' . $safeRedirect);
                exit;
            }
        }
        $tab = 'login';
    }
    // UC01.1: Đăng ký tài khoản
    elseif ($action === 'register') {
        $fullName = trim($_POST['full_name'] ?? '');
        $username = strtolower(trim($_POST['username'] ?? ''));
        $email = strtolower(trim($_POST['email'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $confirm = (string)($_POST['confirm_password'] ?? '');

        $oldFullName = $fullName;
        $oldUsername = $username;
        $oldEmail    = $email;

        if ($fullName === '' || $username === '' || $email === '' || $password === '') {
            set_flash('danger', 'Vui lòng điền đầy đủ các trường thông tin bắt buộc.');
            $tab = 'register';
        } elseif (!preg_match('/^[a-z0-9_]{3,30}$/', $username)) {
            set_flash('danger', 'Username chỉ gồm chữ thường, số hoặc dấu gạch dưới (3–30 ký tự).');
            $tab = 'register';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            set_flash('danger', 'Địa chỉ email không hợp lệ.');
            $tab = 'register';
        } elseif (strlen($password) < 6) {
            set_flash('danger', 'Mật khẩu cần có ít nhất 6 ký tự.');
            $tab = 'register';
        } elseif ($password !== $confirm) {
            set_flash('danger', 'Mật khẩu xác nhận không khớp.');
            $tab = 'register';
        } else {
            $chk = $pdo->prepare('SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1');
            $chk->execute([$username, $email]);
            if ($chk->fetch()) {
                set_flash('danger', 'Username hoặc Email này đã được sử dụng.');
                $tab = 'register';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $ins = $pdo->prepare('
                    INSERT INTO users (full_name, username, email, password_hash, role, status, hometown)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ');
                $ins->execute([$fullName, $username, $email, $hash, 'user', 'active', 'Hà Nội']);
                $_SESSION['user_id'] = (int)$pdo->lastInsertId();
                set_flash('success', 'Đăng ký tài khoản thành công! Chào mừng <strong>' . e($fullName) . '</strong> đến với Bắc Du.');
                $safeRedirect = ($redirect !== '' && $redirect !== 'index.php' && !str_starts_with($redirect, 'http') && !str_starts_with($redirect, '//'))
                    ? $redirect
                    : 'dashboard.php';
                header('Location: ' . $safeRedirect);
                exit;
            }
        }
    }
    // UC01.4: Quên mật khẩu (Xác minh khớp Username + Email + SĐT hồ sơ & khóa bảo vệ tài khoản Admin)
    elseif ($action === 'forgot_password') {
        $username = strtolower(trim($_POST['username'] ?? ''));
        $email = strtolower(trim($_POST['email'] ?? ''));
        $phone = preg_replace('/\s+/', '', trim($_POST['phone'] ?? ''));
        $newPw = (string)($_POST['new_password'] ?? '');
        $confirmPw = (string)($_POST['confirm_password'] ?? '');

        $oldForgotUser  = $username;
        $oldForgotEmail = $email;
        $oldForgotPhone = $phone;

        if ($username === '' || $email === '' || $newPw === '') {
            set_flash('danger', 'Vui lòng nhập đầy đủ Username, Email đã đăng ký và Mật khẩu mới.');
            $tab = 'forgot';
        } elseif (strlen($newPw) < 6) {
            set_flash('danger', 'Mật khẩu mới phải có ít nhất 6 ký tự.');
            $tab = 'forgot';
        } elseif ($newPw !== $confirmPw) {
            set_flash('danger', 'Mật khẩu xác nhận không khớp.');
            $tab = 'forgot';
        } else {
            $stmt = $pdo->prepare('SELECT * FROM users WHERE LOWER(username) = ? AND LOWER(email) = ? LIMIT 1');
            $stmt->execute([$username, $email]);
            $foundUser = $stmt->fetch();

            $savedPhone = preg_replace('/\s+/', '', trim((string)($foundUser['phone'] ?? '')));

            if (!$foundUser) {
                set_flash('danger', 'Không tìm thấy tài khoản khớp đồng thời cả Username và Email bạn cung cấp.');
                $tab = 'forgot';
            } elseif ($foundUser['role'] === 'admin') {
                set_flash('danger', 'Tài khoản Quản trị viên (Admin) được bảo vệ cấp hệ thống, không thể đặt lại mật khẩu qua biểu mẫu công khai.');
                $tab = 'forgot';
            } elseif ($foundUser['status'] === 'locked') {
                set_flash('danger', 'Tài khoản này đang bị khóa, không thể đặt lại mật khẩu.');
                $tab = 'forgot';
            } elseif ($savedPhone !== '' && $savedPhone !== $phone) {
                set_flash('danger', 'Số điện thoại xác minh không khớp với hồ sơ tài khoản đã đăng ký.');
                $tab = 'forgot';
            } else {
                $newHash = password_hash($newPw, PASSWORD_DEFAULT);
                $upd = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
                $upd->execute([$newHash, $foundUser['id']]);
                set_flash('success', 'Đã khôi phục mật khẩu mới thành công cho tài khoản <strong>@' . e($foundUser['username']) . '</strong>! Vui lòng đăng nhập.');
                header('Location: auth.php?tab=login&redirect=' . urlencode($redirect));
                exit;
            }
        }
    }
}

$pageTitle = $tab === 'register' ? 'Đăng ký tài khoản' : ($tab === 'forgot' ? 'Quên mật khẩu' : 'Đăng nhập');
require_once __DIR__ . '/includes/header.php';
?>

<main id="mainContent" class="container auth-stage-main">
  <div class="auth-editorial-shell">
    <div class="row g-0 align-items-stretch">
      <!-- CỘT TRÁI: TRẠM GÁC NIÊN LỊCH ĐIỆN ẢNH -->
      <div class="col-lg-6 d-none d-lg-flex">
        <div class="auth-poster-pane w-100">
          <div>
            <span class="cin-eyebrow mb-2" style="color: var(--cin-amber-bright);">
              CỘT MỐC KM0 · BẮC DU TRAVEL PLANNER
            </span>
            <h1 class="cin-display-h2 text-white mb-3" style="font-size: clamp(1.85rem, 2.4vw, 2.28rem); line-height: 1.25;">
              Lưu Trọn <span class="sw text-nowrap">Niên Lịch</span><br>và Kế Hoạch Du Lịch Miền Bắc
            </h1>
            <p class="small mb-0" style="color: rgba(244,239,228,0.82); line-height: 1.68;">
              Đăng nhập để sao chép kịch bản mẫu 12 tháng thành kế hoạch độc lập, đối soát ngân sách chống cộng trùng và ghi chép trải nghiệm thực địa cùng cộng đồng.
            </p>

            <div class="auth-poster-highlights">
              <span class="auth-poster-pill"><i class="bi bi-calendar2-check-fill"></i>Niên lịch 12 tháng</span>
              <span class="auth-poster-pill"><i class="bi bi-wallet2"></i>Đối soát chi phí</span>
              <span class="auth-poster-pill"><i class="bi bi-geo-alt-fill"></i>44 tọa độ thực địa</span>
            </div>
          </div>

          <div class="auth-demo-card">
            <div class="auth-demo-header">
              <span><i class="bi bi-lightning-charge-fill me-1"></i>ĐĂNG NHẬP NHANH DEMO</span>
              <span class="auth-demo-pw-badge">MK: 123456</span>
            </div>
            <div class="auth-demo-grid">
              <button type="button" class="auth-demo-btn auth-demo-btn--admin" onclick="fillDemoLogin('admin','123456')">
                <span class="auth-demo-id"><i class="bi bi-shield-fill-check me-1"></i>@admin</span>
                <span class="auth-demo-name">Quản trị viên</span>
              </button>
              <button type="button" class="auth-demo-btn" onclick="fillDemoLogin('kien','123456')">
                <span class="auth-demo-id">@kien</span>
                <span class="auth-demo-name">Ngô Xuân Kiên</span>
              </button>
              <button type="button" class="auth-demo-btn" onclick="fillDemoLogin('anh','123456')">
                <span class="auth-demo-id">@anh</span>
                <span class="auth-demo-name">Lê Thị Ngọc Ánh</span>
              </button>
              <button type="button" class="auth-demo-btn" onclick="fillDemoLogin('tram','123456')">
                <span class="auth-demo-id">@tram</span>
                <span class="auth-demo-name">Lò Thị Trâm</span>
              </button>
              <button type="button" class="auth-demo-btn" onclick="fillDemoLogin('van','123456')">
                <span class="auth-demo-id">@van</span>
                <span class="auth-demo-name">Nguyễn Thị Vân</span>
              </button>
              <button type="button" class="auth-demo-btn" onclick="fillDemoLogin('toan','123456')">
                <span class="auth-demo-id">@toan</span>
                <span class="auth-demo-name">Trần Khánh Toàn</span>
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- CỘT PHẢI: HỒ SƠ ĐỊNH DANH & XÁC THỰC (UC01) -->
      <div class="col-lg-6 d-flex">
        <section class="auth-form-pane w-100">
          <div>
            <?php if ($tab !== 'forgot'): ?>
              <div class="auth-segmented" role="tablist" aria-label="Chuyển đổi Đăng nhập và Đăng ký">
                <a class="auth-segmented-tab <?= $tab === 'login' ? 'active' : '' ?>"
                   href="auth.php?tab=login&redirect=<?= urlencode($redirect) ?>" data-key="1">
                  <i class="bi bi-box-arrow-in-right"></i>Đăng nhập
                </a>
                <a class="auth-segmented-tab <?= $tab === 'register' ? 'active' : '' ?>"
                   href="auth.php?tab=register&redirect=<?= urlencode($redirect) ?>" data-key="2">
                  <i class="bi bi-person-plus"></i>Đăng ký
                </a>
              </div>
            <?php else: ?>
              <div class="mb-4 pb-2" style="border-bottom: 1px solid rgba(20,34,27,0.1);">
                <a href="auth.php?tab=login&redirect=<?= urlencode($redirect) ?>" class="small fw-semibold text-decoration-none d-inline-flex align-items-center gap-1" style="color: var(--cin-jade);">
                  <i class="bi bi-arrow-left"></i>Quay lại màn hình Đăng nhập
                </a>
              </div>
            <?php endif; ?>

            <?php if ($tab === 'login'): ?>
              <span class="cin-eyebrow mb-1" style="color: var(--cin-jade);">XÁC THỰC LỮ KHÁCH</span>
              <h2 class="h3 fw-bold mb-1" style="font-family: var(--font-display); color: var(--cin-ink);">Chào mừng bạn quay lại!</h2>
              <p class="small text-muted mb-4">Nhập Username hoặc địa chỉ Email đã đăng ký để tiếp tục hành trình.</p>

              <form action="auth.php" method="post" id="loginForm">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="login">
                <input type="hidden" name="redirect" value="<?= e($redirect) ?>">

                <div class="mb-3">
                  <label for="login_identifier" class="form-label fw-semibold small mb-1">Email hoặc Username</label>
                  <div class="auth-field-box">
                    <span class="auth-field-icon"><i class="bi bi-person"></i></span>
                    <input type="text" name="identifier" id="login_identifier" class="form-control" value="<?= e($oldIdentifier) ?>" placeholder="Nhập username hoặc email của bạn" autocomplete="username" required>
                  </div>
                </div>

                <div class="mb-4">
                  <div class="d-flex justify-content-between align-items-center mb-1">
                    <label for="login_password" class="form-label fw-semibold small mb-0">Mật khẩu</label>
                    <a href="auth.php?tab=forgot&redirect=<?= urlencode($redirect) ?>" class="small fw-semibold text-decoration-none" style="color: var(--cin-jade);">Quên mật khẩu?</a>
                  </div>
                  <div class="auth-field-box">
                    <span class="auth-field-icon"><i class="bi bi-lock"></i></span>
                    <input type="password" name="password" id="login_password" class="form-control" placeholder="Nhập mật khẩu tài khoản" autocomplete="current-password" required>
                    <button type="button" class="auth-pw-toggle" onclick="toggleAuthPw(this, 'login_password')" title="Hiện / Ẩn mật khẩu" aria-label="Hiện hoặc ẩn mật khẩu">
                      <i class="bi bi-eye"></i>
                    </button>
                  </div>
                </div>

                <button type="submit" class="btn-cin-amber auth-submit-btn mb-3">
                  <i class="bi bi-box-arrow-in-right"></i>Đăng nhập vào Bắc Du
                </button>
              </form>

              <div class="d-lg-none mt-3 field-spec-box">
                <div class="d-flex justify-content-between align-items-center mb-2 small">
                  <strong><i class="bi bi-lightning-charge-fill text-warning me-1"></i>Tài khoản Demo nhanh</strong>
                  <span class="font-monospace text-muted" style="font-size: 0.72rem;">MK: 123456</span>
                </div>
                <div class="d-flex flex-wrap gap-1">
                  <button type="button" class="btn-cin-outline py-1 px-2" onclick="fillDemoLogin('admin','123456')">@admin</button>
                  <button type="button" class="btn-cin-outline py-1 px-2" onclick="fillDemoLogin('kien','123456')">@kien</button>
                  <button type="button" class="btn-cin-outline py-1 px-2" onclick="fillDemoLogin('anh','123456')">@anh</button>
                  <button type="button" class="btn-cin-outline py-1 px-2" onclick="fillDemoLogin('tram','123456')">@tram</button>
                  <button type="button" class="btn-cin-outline py-1 px-2" onclick="fillDemoLogin('van','123456')">@van</button>
                  <button type="button" class="btn-cin-outline py-1 px-2" onclick="fillDemoLogin('toan','123456')">@toan</button>
                </div>
              </div>

            <?php elseif ($tab === 'register'): ?>
              <span class="cin-eyebrow mb-1" style="color: var(--cin-jade);">ĐĂNG KÝ THÀNH VIÊN</span>
              <h2 class="h3 fw-bold mb-1" style="font-family: var(--font-display); color: var(--cin-ink);">Tạo tài khoản Bắc Du</h2>
              <p class="small text-muted mb-4">Khởi tạo hồ sơ miễn phí để lưu tọa độ yêu thích và lập kế hoạch riêng.</p>

              <form action="auth.php?tab=register" method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="register">
                <input type="hidden" name="redirect" value="<?= e($redirect) ?>">

                <div class="mb-3">
                  <label class="form-label fw-semibold small mb-1">Họ và tên</label>
                  <div class="auth-field-box">
                    <span class="auth-field-icon"><i class="bi bi-person-vcard"></i></span>
                    <input type="text" name="full_name" class="form-control" value="<?= e($oldFullName) ?>" placeholder="VD: Ngô Xuân Kiên" required>
                  </div>
                </div>

                <div class="row g-3 mb-3">
                  <div class="col-sm-6">
                    <label class="form-label fw-semibold small mb-1">Username</label>
                    <div class="auth-field-box">
                      <span class="auth-field-icon"><i class="bi bi-at"></i></span>
                      <input type="text" name="username" class="form-control" value="<?= e($oldUsername) ?>" placeholder="ngoxuankien" required>
                    </div>
                  </div>
                  <div class="col-sm-6">
                    <label class="form-label fw-semibold small mb-1">Email</label>
                    <div class="auth-field-box">
                      <span class="auth-field-icon"><i class="bi bi-envelope"></i></span>
                      <input type="email" name="email" class="form-control" value="<?= e($oldEmail) ?>" placeholder="ban@email.com" required>
                    </div>
                  </div>
                </div>

                <div class="row g-3 mb-4">
                  <div class="col-sm-6">
                    <label class="form-label fw-semibold small mb-1">Mật khẩu</label>
                    <div class="auth-field-box">
                      <span class="auth-field-icon"><i class="bi bi-lock"></i></span>
                      <input type="password" name="password" id="reg_password" class="form-control" minlength="6" placeholder="Ít nhất 6 ký tự" required>
                      <button type="button" class="auth-pw-toggle" onclick="toggleAuthPw(this, 'reg_password')" title="Hiện / Ẩn mật khẩu" aria-label="Hiện hoặc ẩn mật khẩu">
                        <i class="bi bi-eye"></i>
                      </button>
                    </div>
                  </div>
                  <div class="col-sm-6">
                    <label class="form-label fw-semibold small mb-1">Xác nhận mật khẩu</label>
                    <div class="auth-field-box">
                      <span class="auth-field-icon"><i class="bi bi-shield-check"></i></span>
                      <input type="password" name="confirm_password" id="reg_confirm_password" class="form-control" minlength="6" placeholder="Nhập lại mật khẩu" required>
                      <button type="button" class="auth-pw-toggle" onclick="toggleAuthPw(this, 'reg_confirm_password')" title="Hiện / Ẩn mật khẩu" aria-label="Hiện hoặc ẩn mật khẩu">
                        <i class="bi bi-eye"></i>
                      </button>
                    </div>
                  </div>
                </div>

                <button type="submit" class="btn-cin-amber auth-submit-btn">
                  <i class="bi bi-person-plus-fill"></i>Hoàn tất đăng ký
                </button>
              </form>

            <?php else: ?>
              <span class="cin-eyebrow mb-1" style="color: var(--cin-jade);">KHÔI PHỤC BẢO MẬT</span>
              <h2 class="h3 fw-bold mb-1" style="font-family: var(--font-display); color: var(--cin-ink);">Đặt lại mật khẩu</h2>
              <p class="small text-muted mb-4">Xác minh đồng thời Username, Email và Số điện thoại hồ sơ để đặt lại mật khẩu.</p>

              <form action="auth.php?tab=forgot" method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="forgot_password">
                <input type="hidden" name="redirect" value="<?= e($redirect) ?>">

                <div class="row g-3 mb-3">
                  <div class="col-sm-6">
                    <label class="form-label fw-semibold small mb-1">Username tài khoản</label>
                    <div class="auth-field-box">
                      <span class="auth-field-icon"><i class="bi bi-at"></i></span>
                      <input type="text" name="username" class="form-control" value="<?= e($oldForgotUser) ?>" placeholder="VD: kien" required>
                    </div>
                  </div>
                  <div class="col-sm-6">
                    <label class="form-label fw-semibold small mb-1">Số điện thoại hồ sơ</label>
                    <div class="auth-field-box">
                      <span class="auth-field-icon"><i class="bi bi-telephone"></i></span>
                      <input type="text" name="phone" class="form-control" value="<?= e($oldForgotPhone) ?>" placeholder="Nếu đã lưu trong hồ sơ">
                    </div>
                  </div>
                </div>

                <div class="mb-3">
                  <label class="form-label fw-semibold small mb-1">Email đã đăng ký</label>
                  <div class="auth-field-box">
                    <span class="auth-field-icon"><i class="bi bi-envelope"></i></span>
                    <input type="email" name="email" class="form-control" value="<?= e($oldForgotEmail) ?>" placeholder="ban@email.com" required>
                  </div>
                </div>

                <div class="row g-3 mb-4">
                  <div class="col-sm-6">
                    <label class="form-label fw-semibold small mb-1">Mật khẩu mới</label>
                    <div class="auth-field-box">
                      <span class="auth-field-icon"><i class="bi bi-key"></i></span>
                      <input type="password" name="new_password" id="forgot_new_pw" class="form-control" minlength="6" placeholder="Ít nhất 6 ký tự" required>
                      <button type="button" class="auth-pw-toggle" onclick="toggleAuthPw(this, 'forgot_new_pw')" title="Hiện / Ẩn mật khẩu" aria-label="Hiện hoặc ẩn mật khẩu">
                        <i class="bi bi-eye"></i>
                      </button>
                    </div>
                  </div>
                  <div class="col-sm-6">
                    <label class="form-label fw-semibold small mb-1">Xác nhận mật khẩu mới</label>
                    <div class="auth-field-box">
                      <span class="auth-field-icon"><i class="bi bi-check2-circle"></i></span>
                      <input type="password" name="confirm_password" id="forgot_confirm_pw" class="form-control" minlength="6" placeholder="Nhập lại mật khẩu" required>
                      <button type="button" class="auth-pw-toggle" onclick="toggleAuthPw(this, 'forgot_confirm_pw')" title="Hiện / Ẩn mật khẩu" aria-label="Hiện hoặc ẩn mật khẩu">
                        <i class="bi bi-eye"></i>
                      </button>
                    </div>
                  </div>
                </div>

                <button type="submit" class="btn-cin-amber auth-submit-btn">
                  <i class="bi bi-arrow-repeat"></i>Cập nhật mật khẩu mới
                </button>
              </form>
            <?php endif; ?>
          </div>

          <div class="pt-2 mt-3 text-center small" style="border-top: 1px dashed rgba(20,34,27,0.12); color: var(--cin-ink-muted); font-size: 0.78rem;">
            <?php if ($tab === 'login'): ?>
              Chưa có tài khoản lữ khách?
              <a href="auth.php?tab=register&redirect=<?= urlencode($redirect) ?>" class="fw-bold text-decoration-none" style="color: var(--cin-jade);">Đăng ký miễn phí ngay</a>
            <?php else: ?>
              Đã có tài khoản trên Bắc Du?
              <a href="auth.php?tab=login&redirect=<?= urlencode($redirect) ?>" class="fw-bold text-decoration-none" style="color: var(--cin-jade);">Đăng nhập tại đây</a>
            <?php endif; ?>
          </div>
        </section>
      </div>
    </div>
  </div>

  <?php if ($tab !== 'login'): ?>
    <form action="auth.php" method="post" id="quickDemoFallbackForm" class="d-none">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="login">
      <input type="hidden" name="redirect" value="<?= e($redirect) ?>">
      <input type="hidden" name="identifier" id="fallback_demo_id">
      <input type="hidden" name="password" id="fallback_demo_pw">
    </form>
  <?php endif; ?>
</main>

<script>
function fillDemoLogin(username, password) {
  const idInput = document.getElementById('login_identifier');
  const pwInput = document.getElementById('login_password');
  const form = document.getElementById('loginForm');
  if (idInput && pwInput && form) {
    idInput.value = username;
    pwInput.value = password;
    form.submit();
    return;
  }
  const fbForm = document.getElementById('quickDemoFallbackForm');
  if (fbForm) {
    document.getElementById('fallback_demo_id').value = username;
    document.getElementById('fallback_demo_pw').value = password;
    fbForm.submit();
  }
}

function toggleAuthPw(btn, inputId) {
  const input = document.getElementById(inputId);
  const icon = btn.querySelector('i');
  if (!input || !icon) return;
  if (input.type === 'password') {
    input.type = 'text';
    icon.className = 'bi bi-eye-slash';
  } else {
    input.type = 'password';
    icon.className = 'bi bi-eye';
  }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
