<?php
require_once __DIR__ . '/includes/helpers.php';

$pdo = get_db();
$tab = $_GET['tab'] ?? 'login';
$redirect = $_GET['redirect'] ?? $_POST['redirect'] ?? 'index.php';

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
        header('Location: auth.php?tab=' . urlencode($tab));
        exit;
    }

    // UC01.2: Đăng nhập
    if ($action === 'login') {
        $identifier = trim($_POST['identifier'] ?? '');
        $password = (string)($_POST['password'] ?? '');

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
                set_flash('success', 'Đăng ký tài khoản thành công! Hãy bắt đầu tạo chuyến đi đầu tiên của bạn.');
                header('Location: dashboard.php');
                exit;
            }
        }
    }
    // UC01.4: Quên mật khẩu (Xác minh khớp Username + Email để đặt lại mật khẩu mới trên XAMPP)
    elseif ($action === 'forgot_password') {
        $username = strtolower(trim($_POST['username'] ?? ''));
        $email = strtolower(trim($_POST['email'] ?? ''));
        $newPw = (string)($_POST['new_password'] ?? '');
        $confirmPw = (string)($_POST['confirm_password'] ?? '');

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

            if (!$foundUser) {
                set_flash('danger', 'Không tìm thấy tài khoản khớp đồng thời cả Username và Email bạn cung cấp.');
                $tab = 'forgot';
            } elseif ($foundUser['status'] === 'locked') {
                set_flash('danger', 'Tài khoản này đang bị khóa, không thể đặt lại mật khẩu.');
                $tab = 'forgot';
            } else {
                $newHash = password_hash($newPw, PASSWORD_DEFAULT);
                $upd = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
                $upd->execute([$newHash, $foundUser['id']]);
                set_flash('success', 'Đã khôi phục mật khẩu mới thành công cho tài khoản <strong>@' . e($foundUser['username']) . '</strong>! Vui lòng đăng nhập.');
                header('Location: auth.php?tab=login');
                exit;
            }
        }
    }
}

$pageTitle = $tab === 'register' ? 'Đăng ký tài khoản' : ($tab === 'forgot' ? 'Quên mật khẩu' : 'Đăng nhập');
require_once __DIR__ . '/includes/header.php';
?>

<main id="mainContent" class="container py-5">
  <div class="row justify-content-center align-items-stretch g-4">
    <!-- CỘT TRÁI: TRẠM GÁC NIÊN LỊCH ĐIỆN ẢNH -->
    <div class="col-lg-5 d-none d-lg-flex">
      <div class="p-4 p-xl-5 text-white d-flex flex-column justify-content-between w-100"
           style="border-radius: 4px; border: 1px solid rgba(217,119,6,0.35); background: linear-gradient(150deg, rgba(11,25,20,0.92), rgba(16,36,28,0.84)), url('https://images.unsplash.com/photo-1559592413-7cec4d0cae2b?w=1000&auto=format&fit=crop&q=80') center/cover; box-shadow: 0 24px 60px rgba(11,25,20,0.22);">
        <div>
          <span class="cin-eyebrow mb-2" style="color: var(--cin-amber-bright);">
            CỘT MỐC KM0 · BẮC DU TRAVEL PLANNER
          </span>
          <h1 class="cin-display-h2 text-white mb-3">
            Lưu Trọn <span class="sw">Niên Lịch</span> &amp; Kế Hoạch Du Lịch Miền Bắc
          </h1>
          <p class="small mb-0" style="color: rgba(244,239,228,0.78); line-height: 1.65;">
            Đăng nhập để sao chép kịch bản mẫu 12 tháng thành kế hoạch độc lập, đối soát ngân sách chống cộng trùng và ghi chép trải nghiệm thực địa cùng cộng đồng.
          </p>
        </div>

        <div class="p-3" style="border-radius: 4px; background: rgba(11,25,20,0.82); border: 1px solid rgba(217,119,6,0.35);">
          <div class="font-monospace small fw-bold mb-2" style="color: var(--cin-amber-bright); font-size: 0.74rem;">
            <i class="bi bi-lightning-charge-fill me-1"></i>CHỌN NHANH TÀI KHOẢN DEMO (MẬT KHẨU: 123456)
          </div>
          <div class="d-flex flex-wrap gap-2">
            <button type="button" class="btn-cin-amber py-1 px-2" style="font-size: 0.72rem;" onclick="fillDemoLogin('admin','123456')">
              <i class="bi bi-shield-check"></i>admin|123456
            </button>
            <button type="button" class="btn-cin-ghost py-1 px-2" style="font-size: 0.72rem;" onclick="fillDemoLogin('kien','123456')">
              kien (Ngô Xuân Kiên)
            </button>
            <button type="button" class="btn-cin-ghost py-1 px-2" style="font-size: 0.72rem;" onclick="fillDemoLogin('anh','123456')">
              anh (Lê Thị Ngọc Ánh)
            </button>
            <button type="button" class="btn-cin-ghost py-1 px-2" style="font-size: 0.72rem;" onclick="fillDemoLogin('tram','123456')">
              tram (Lò Thị Trâm)
            </button>
            <button type="button" class="btn-cin-ghost py-1 px-2" style="font-size: 0.72rem;" onclick="fillDemoLogin('van','123456')">
              van (Nguyễn Thị Vân)
            </button>
            <button type="button" class="btn-cin-ghost py-1 px-2" style="font-size: 0.72rem;" onclick="fillDemoLogin('toan','123456')">
              toan (Trần Khánh Toàn)
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- CỘT PHẢI: HỒ SƠ ĐỊNH DANH & XÁC THỰC (UC01) -->
    <div class="col-md-8 col-lg-5">
      <section class="cin-day-dossier mb-0 h-100 d-flex flex-column">
        <div class="cin-day-header">
          <div class="app-view-tabs w-100 justify-content-between" role="tablist">
            <a class="app-view-tab flex-grow-1 justify-content-center <?= $tab === 'login' ? 'active' : '' ?>"
               href="auth.php?tab=login&redirect=<?= urlencode($redirect) ?>" data-key="1">
              Đăng nhập
            </a>
            <a class="app-view-tab flex-grow-1 justify-content-center <?= $tab === 'register' ? 'active' : '' ?>"
               href="auth.php?tab=register&redirect=<?= urlencode($redirect) ?>" data-key="2">
              Đăng ký
            </a>
            <a class="app-view-tab flex-grow-1 justify-content-center <?= $tab === 'forgot' ? 'active' : '' ?>"
               href="auth.php?tab=forgot&redirect=<?= urlencode($redirect) ?>" data-key="3">
              Quên MK
            </a>
          </div>
        </div>

        <div class="cin-day-body p-4 flex-grow-1 d-flex flex-column justify-content-center">
          <?php if ($tab === 'login'): ?>
            <span class="cin-eyebrow mb-1" style="color: var(--cin-jade);">XÁC THỰC LỮ KHÁCH · UC01.2</span>
            <h2 class="h3 fw-bold mb-1" style="font-family: var(--font-display);">Chào mừng bạn quay lại!</h2>
            <p class="small text-muted mb-4">Đăng nhập bằng Username hoặc Email đã đăng ký.</p>

            <form action="auth.php" method="post" id="loginForm">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="login">
              <input type="hidden" name="redirect" value="<?= e($redirect) ?>">

              <div class="mb-3">
                <label class="form-label fw-semibold small">Email hoặc Username</label>
                <div class="input-group">
                  <span class="input-group-text bg-light"><i class="bi bi-person"></i></span>
                  <input type="text" name="identifier" id="login_identifier" class="form-control font-monospace" placeholder="VD: kien, anh, tram, van, toan hoặc admin" required>
                </div>
              </div>

              <div class="mb-3">
                <div class="d-flex justify-content-between align-items-center">
                  <label class="form-label fw-semibold small">Mật khẩu</label>
                  <a href="auth.php?tab=forgot" class="small fw-semibold" style="color: var(--cin-jade);">Quên mật khẩu?</a>
                </div>
                <div class="input-group">
                  <span class="input-group-text bg-light"><i class="bi bi-lock"></i></span>
                  <input type="password" name="password" id="login_password" class="form-control font-monospace" placeholder="Nhập mật khẩu (mặc định: 123456)" required>
                </div>
              </div>

              <button type="submit" class="btn-cin-amber w-100 justify-content-center py-2 mt-2 mb-3">
                <i class="bi bi-box-arrow-in-right"></i>Đăng nhập vào Bắc Du
              </button>
            </form>

            <div class="d-lg-none mt-2 field-spec-box">
              <div class="fw-bold mb-2 small">Chọn nhanh tài khoản (MK: 123456):</div>
              <div class="d-flex flex-wrap gap-1">
                <button type="button" class="btn-cin-amber py-1 px-2" onclick="fillDemoLogin('admin','123456')">admin</button>
                <button type="button" class="btn-cin-outline py-1 px-2" onclick="fillDemoLogin('kien','123456')">kien</button>
                <button type="button" class="btn-cin-outline py-1 px-2" onclick="fillDemoLogin('anh','123456')">anh</button>
                <button type="button" class="btn-cin-outline py-1 px-2" onclick="fillDemoLogin('tram','123456')">tram</button>
                <button type="button" class="btn-cin-outline py-1 px-2" onclick="fillDemoLogin('van','123456')">van</button>
                <button type="button" class="btn-cin-outline py-1 px-2" onclick="fillDemoLogin('toan','123456')">toan</button>
              </div>
            </div>

          <?php elseif ($tab === 'register'): ?>
            <span class="cin-eyebrow mb-1" style="color: var(--cin-jade);">ĐĂNG KÝ THÀNH VIÊN · UC01.1</span>
            <h2 class="h3 fw-bold mb-1" style="font-family: var(--font-display);">Tạo tài khoản Bắc Du</h2>
            <p class="small text-muted mb-4">Đăng ký tài khoản miễn phí để bắt đầu lập kế hoạch.</p>

            <form action="auth.php?tab=register" method="post">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="register">
              <input type="hidden" name="redirect" value="<?= e($redirect) ?>">

              <div class="mb-3">
                <label class="form-label fw-semibold small">Họ và tên</label>
                <input type="text" name="full_name" class="form-control" placeholder="VD: Ngô Xuân Kiên" required>
              </div>

              <div class="row g-3 mb-3">
                <div class="col-sm-6">
                  <label class="form-label fw-semibold small">Username</label>
                  <input type="text" name="username" class="form-control font-monospace" placeholder="ngoxuankien" required>
                </div>
                <div class="col-sm-6">
                  <label class="form-label fw-semibold small">Email</label>
                  <input type="email" name="email" class="form-control" placeholder="ban@email.com" required>
                </div>
              </div>

              <div class="row g-3 mb-4">
                <div class="col-sm-6">
                  <label class="form-label fw-semibold small">Mật khẩu</label>
                  <input type="password" name="password" class="form-control" placeholder="Ít nhất 6 ký tự" required>
                </div>
                <div class="col-sm-6">
                  <label class="form-label fw-semibold small">Xác nhận mật khẩu</label>
                  <input type="password" name="confirm_password" class="form-control" placeholder="Nhập lại mật khẩu" required>
                </div>
              </div>

              <button type="submit" class="btn-cin-amber w-100 justify-content-center py-2">
                <i class="bi bi-person-plus-fill"></i>Hoàn tất đăng ký
              </button>
            </form>

          <?php else: ?>
            <span class="cin-eyebrow mb-1" style="color: var(--cin-jade);">KHÔI PHỤC BẢO MẬT · UC01.4</span>
            <h2 class="h3 fw-bold mb-1" style="font-family: var(--font-display);">Khôi phục mật khẩu</h2>
            <p class="small text-muted mb-4">Xác minh Username và Email đã đăng ký để đặt lại mật khẩu mới.</p>

            <form action="auth.php?tab=forgot" method="post">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="forgot_password">

              <div class="mb-3">
                <label class="form-label fw-semibold small">Username tài khoản</label>
                <input type="text" name="username" class="form-control font-monospace" placeholder="VD: kien" required>
              </div>

              <div class="mb-3">
                <label class="form-label fw-semibold small">Email đã đăng ký</label>
                <input type="email" name="email" class="form-control" placeholder="VD: kien@bacdu.vn" required>
              </div>

              <div class="row g-3 mb-4">
                <div class="col-sm-6">
                  <label class="form-label fw-semibold small">Mật khẩu mới</label>
                  <input type="password" name="new_password" class="form-control" minlength="6" placeholder="Ít nhất 6 ký tự" required>
                </div>
                <div class="col-sm-6">
                  <label class="form-label fw-semibold small">Xác nhận mật khẩu mới</label>
                  <input type="password" name="confirm_password" class="form-control" minlength="6" placeholder="Nhập lại mật khẩu" required>
                </div>
              </div>

              <button type="submit" class="btn-cin-ink w-100 justify-content-center py-2">
                <i class="bi bi-arrow-repeat"></i>Đặt lại mật khẩu mới
              </button>
            </form>
          <?php endif; ?>
        </div>
      </section>
    </div>
  </div>
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
  } else {
    window.location.href = 'auth.php?tab=login';
  }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
