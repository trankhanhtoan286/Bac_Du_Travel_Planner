<?php
require_once __DIR__ . '/includes/helpers.php';

$user = require_login('profile.php');
$pdo = get_db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        set_flash('danger', 'Phiên làm việc không hợp lệ.');
        header('Location: profile.php');
        exit;
    }

    $action = $_POST['action'] ?? '';
    if ($action === 'update_profile') {
        $fullName = trim($_POST['full_name'] ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));
        $phone = trim($_POST['phone'] ?? '');
        $hometown = trim($_POST['hometown'] ?? 'Hà Nội');
        $avatarUrl = trim($_POST['avatar_url'] ?? '');
        $bio = trim($_POST['bio'] ?? '');

        if ($fullName === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            set_flash('danger', 'Vui lòng nhập họ tên và địa chỉ email hợp lệ.');
        } else {
            $chk = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1');
            $chk->execute([$email, $user['id']]);
            if ($chk->fetch()) {
                set_flash('danger', 'Địa chỉ email này đã thuộc về một tài khoản khác.');
            } else {
                $upd = $pdo->prepare('
                    UPDATE users
                    SET full_name = ?, email = ?, phone = ?, hometown = ?, avatar_url = ?, bio = ?
                    WHERE id = ?
                ');
                $upd->execute([$fullName, $email, $phone, $hometown, $avatarUrl ?: null, $bio, $user['id']]);
                set_flash('success', 'Đã cập nhật thông tin hồ sơ cá nhân.');
            }
        }
        header('Location: profile.php');
        exit;
    } elseif ($action === 'change_password') {
        $currentPw = (string)($_POST['current_password'] ?? '');
        $newPw = (string)($_POST['new_password'] ?? '');
        $confirmPw = (string)($_POST['confirm_password'] ?? '');

        if (!verify_user_password($currentPw, $user['password_hash'])) {
            set_flash('danger', 'Mật khẩu hiện tại không chính xác.');
        } elseif (strlen($newPw) < 6) {
            set_flash('danger', 'Mật khẩu mới phải có ít nhất 6 ký tự.');
        } elseif ($newPw !== $confirmPw) {
            set_flash('danger', 'Mật khẩu xác nhận không khớp.');
        } else {
            $newHash = password_hash($newPw, PASSWORD_DEFAULT);
            $upd = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
            $upd->execute([$newHash, $user['id']]);
            set_flash('success', 'Đổi mật khẩu thành công!');
        }
        header('Location: profile.php');
        exit;
    }
}

$pageTitle = 'Hồ sơ cá nhân & Bảo mật';
require_once __DIR__ . '/includes/header.php';
?>

<!-- APP MODE SHIFT BAR -->
<header class="app-shift-bar mb-4">
  <div class="container">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
      <div>
        <div class="app-shift-eyebrow">
          <span><i class="bi bi-person-badge-fill text-warning me-1"></i>HỒ SƠ LỮ KHÁCH &amp; BẢO MẬT TÀI KHOẢN · MODULE M1</span>
          <span>·</span>
          <span>ĐỊNH DANH: <strong class="text-white">@<?= e($user['username']) ?></strong></span>
        </div>
        <h1 class="app-shift-title">Cập Nhật Thông Tin Cá Nhân &amp; Đổi Mật Khẩu</h1>
      </div>
      <div class="d-flex flex-wrap gap-2">
        <a href="dashboard.php" class="btn-cin-ghost py-1 px-3" data-key="d">
          <i class="bi bi-grid-1x2"></i> Về Dashboard <span class="app-kbd-badge">d</span>
        </a>
        <button type="button" class="btn-cin-ghost py-1 px-3" onclick="window.BacDuApp && window.BacDuApp.openPalette()" data-open-palette title="Mở Bảng Lệnh Nhanh (/)">
          <i class="bi bi-terminal"></i> Lệnh <span class="app-kbd-badge">/</span>
        </button>
      </div>
    </div>
  </div>
</header>

<main id="mainContent" class="container py-4">
  <div class="row g-4">
    <div class="col-lg-4">
      <article class="field-slip h-100">
        <div class="field-slip-body text-center p-4">
          <img src="<?= e($user['avatar_url'] ?: 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=300') ?>"
               alt="<?= e($user['full_name']) ?>" class="rounded-circle object-fit-cover border shadow-sm mb-3" width="110" height="110">
          <h2 class="h4 fw-bold mb-1" style="font-family: var(--font-display);"><?= e($user['full_name']) ?></h2>
          <div class="font-monospace text-muted small mb-2">@<?= e($user['username']) ?> · <?= e($user['email']) ?></div>
          <div class="mb-3">
            <span class="status-mark <?= $user['role'] === 'admin' ? 'status-err' : 'status-done' ?>">
              <span class="status-shape <?= $user['role'] === 'admin' ? 'shape-diamond' : 'shape-circle' ?>"></span>
              <?= $user['role'] === 'admin' ? 'Quản trị viên & Ban biên tập' : 'Thành viên Bắc Du' ?>
            </span>
          </div>
          <p class="small mb-3" style="color: var(--cin-ink-muted);"><?= e($user['bio'] ?: 'Chưa có giới thiệu bản thân.') ?></p>
          <div class="field-spec-box text-start mt-auto">
            <div class="spec-line"><i class="bi bi-geo-alt me-2 text-success"></i>Xuất phát mặc định: <strong><?= e($user['hometown'] ?: 'Hà Nội') ?></strong></div>
            <div class="spec-line"><i class="bi bi-telephone me-2 text-success"></i>Điện thoại: <strong class="font-monospace"><?= e($user['phone'] ?: 'Chưa cập nhật') ?></strong></div>
            <div class="spec-line"><i class="bi bi-calendar3 me-2 text-success"></i>Ngày gia nhập: <strong class="font-monospace"><?= format_date_vn($user['created_at']) ?></strong></div>
          </div>
        </div>
      </article>
    </div>

    <div class="col-lg-8">
      <section class="cin-day-dossier">
        <div class="cin-day-header">
          <span class="fw-bold"><i class="bi bi-person-lines-fill text-success me-1"></i>Cập nhật thông tin hồ sơ (UC06.2)</span>
          <span class="font-monospace small text-muted">ID #<?= (int)$user['id'] ?></span>
        </div>
        <div class="cin-day-body">
          <form action="profile.php" method="post" class="row g-3">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="update_profile">

            <div class="col-md-6">
              <label class="form-label small fw-bold">Họ và tên</label>
              <input type="text" name="full_name" class="form-control" value="<?= e($user['full_name']) ?>" required>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-bold">Username (Cố định)</label>
              <input type="text" class="form-control bg-light font-monospace" value="@<?= e($user['username']) ?>" disabled>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-bold">Địa chỉ Email</label>
              <input type="email" name="email" class="form-control" value="<?= e($user['email']) ?>" required>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-bold">Số điện thoại</label>
              <input type="text" name="phone" class="form-control font-monospace" value="<?= e($user['phone']) ?>" placeholder="09xxxxxxxx">
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-bold">Điểm xuất phát thường xuyên</label>
              <input type="text" name="hometown" class="form-control" value="<?= e($user['hometown'] ?: 'Hà Nội') ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-bold">URL Ảnh đại diện</label>
              <input type="url" name="avatar_url" class="form-control" value="<?= e($user['avatar_url']) ?>" placeholder="https://...">
            </div>
            <div class="col-12">
              <label class="form-label small fw-bold">Giới thiệu ngắn / Sở thích du lịch</label>
              <textarea name="bio" rows="3" class="form-control"><?= e($user['bio']) ?></textarea>
            </div>
            <div class="col-12">
              <button type="submit" class="btn-cin-amber">
                <i class="bi bi-check2-circle"></i>Lưu thay đổi hồ sơ
              </button>
            </div>
          </form>
        </div>
      </section>

      <section class="cin-day-dossier mb-0">
        <div class="cin-day-header">
          <span class="fw-bold"><i class="bi bi-shield-lock text-warning me-1"></i>Đổi mật khẩu bảo mật (UC06.3)</span>
        </div>
        <div class="cin-day-body">
          <form action="profile.php" method="post" class="row g-3">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="change_password">
            <div class="col-md-4">
              <label class="form-label small fw-bold">Mật khẩu hiện tại</label>
              <input type="password" name="current_password" class="form-control" required>
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-bold">Mật khẩu mới</label>
              <input type="password" name="new_password" class="form-control" minlength="6" required>
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-bold">Xác nhận mật khẩu mới</label>
              <input type="password" name="confirm_password" class="form-control" minlength="6" required>
            </div>
            <div class="col-12">
              <button type="submit" class="btn-cin-ink">
                <i class="bi bi-key"></i>Cập nhật mật khẩu
              </button>
            </div>
          </form>
        </div>
      </section>
    </div>
  </div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
