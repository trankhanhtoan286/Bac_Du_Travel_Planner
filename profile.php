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
        $removeAvatar = !empty($_POST['remove_avatar']) && $_POST['remove_avatar'] == '1';

        // 1. Người dùng chọn gỡ ảnh đại diện (quay về avatar mặc định)
        if ($removeAvatar) {
            $avatarUrl = null;
        }

        // 2. Người dùng tải ảnh đại diện lên từ máy tính (File Upload)
        if (!empty($_FILES['avatar_file']['name']) && $_FILES['avatar_file']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['avatar_file'];
            $maxSize = 5 * 1024 * 1024; // 5MB
            $allowedExts = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

            if ($file['size'] > $maxSize) {
                set_flash('danger', 'Kích thước file ảnh vượt quá giới hạn 5MB.');
                header('Location: profile.php');
                exit;
            } elseif (!in_array($ext, $allowedExts, true)) {
                set_flash('danger', 'Định dạng ảnh không hỗ trợ. Vui lòng tải file JPG, PNG, WEBP hoặc GIF.');
                header('Location: profile.php');
                exit;
            } else {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime = finfo_file($finfo, $file['tmp_name']);
                finfo_close($finfo);

                $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
                if (!in_array($mime, $allowedMimes, true)) {
                    set_flash('danger', 'Tệp tải lên không phải là ảnh hợp lệ.');
                    header('Location: profile.php');
                    exit;
                }

                $uploadDir = __DIR__ . '/uploads/avatars';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }

                $fileName = 'avatar_u' . (int)$user['id'] . '_' . time() . '_' . substr(bin2hex(random_bytes(4)), 0, 6) . '.' . $ext;
                $destPath = $uploadDir . '/' . $fileName;

                if (move_uploaded_file($file['tmp_name'], $destPath)) {
                    $avatarUrl = 'uploads/avatars/' . $fileName;
                } else {
                    set_flash('danger', 'Không thể lưu file ảnh vào thư mục máy chủ.');
                    header('Location: profile.php');
                    exit;
                }
            }
        } elseif (!$removeAvatar && $avatarUrl === '' && !empty($user['avatar_url'])) {
            // Giữ lại avatar hiện tại nếu không chọn ảnh mới và không gỡ ảnh
            $avatarUrl = $user['avatar_url'];
        }

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
                set_flash('success', 'Đã cập nhật thông tin hồ sơ và ảnh đại diện thành công.');
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

$pageTitle = 'Hồ sơ cá nhân và Bảo mật';
require_once __DIR__ . '/includes/header.php';
?>

<!-- APP MODE SHIFT BAR -->
<header class="app-shift-bar mb-4">
  <div class="container">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
      <div>
        <div class="app-shift-eyebrow">
          <span><i class="bi bi-person-badge-fill text-warning me-1"></i>HỒ SƠ LỮ KHÁCH VÀ BẢO MẬT TÀI KHOẢN</span>
          <span>·</span>
          <span>ĐỊNH DANH: <strong class="text-white">@<?= e($user['username']) ?></strong></span>
        </div>
        <h1 class="app-shift-title">Cập Nhật Thông Tin Cá Nhân và Đổi Mật Khẩu</h1>
      </div>
      <div class="d-flex flex-wrap gap-2">
        <a href="dashboard.php" class="btn-cin-ghost py-1 px-3" data-key="d">
          <i class="bi bi-grid-1x2"></i> Về Tổng quan <span class="app-kbd-badge">d</span>
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
          <!-- Khung ảnh đại diện với camera badge kiểu Facebook -->
          <div class="position-relative d-inline-block mb-3">
            <img id="avatarPreview"
                 src="<?= e(get_avatar_url($user['avatar_url'])) ?>"
                 alt="<?= e($user['full_name']) ?>"
                 class="rounded-circle object-fit-cover shadow-sm"
                 width="120" height="120"
                 style="border: 3px solid var(--cin-brass); background: #14241c;">
            <button type="button" class="btn-avatar-camera"
                    title="Chọn ảnh đại diện từ máy tính"
                    onclick="document.getElementById('avatarFileInput').click();">
              <i class="bi bi-camera-fill"></i>
            </button>
            <input type="file" id="avatarFileInput" name="avatar_file" form="profileForm"
                   accept="image/png, image/jpeg, image/jpg, image/webp"
                   class="d-none" onchange="previewAvatar(this);">
          </div>

          <div class="d-flex justify-content-center gap-2 mb-3">
            <button type="button" class="btn-cin-outline py-1 px-2 small"
                    onclick="document.getElementById('avatarFileInput').click();">
              <i class="bi bi-upload me-1"></i>Tải ảnh từ máy
            </button>
            <button type="button" class="btn-cin-outline py-1 px-2 small text-danger <?= empty($user['avatar_url']) ? 'd-none' : '' ?>"
                    id="btnRemoveAvatar" onclick="removeAvatar();">
              <i class="bi bi-trash me-1"></i>Gỡ ảnh
            </button>
          </div>

          <div id="newAvatarNotice" class="alert alert-warning py-1 px-2 small d-none mb-3 text-start">
            <i class="bi bi-info-circle-fill me-1 text-warning"></i>Đã chọn ảnh mới. Nhấn <strong>Lưu thay đổi hồ sơ</strong> để lưu ảnh này.
          </div>

          <h2 class="h4 fw-bold mb-1" style="font-family: var(--font-display);"><?= e($user['full_name']) ?></h2>
          <div class="font-monospace text-muted small mb-2">@<?= e($user['username']) ?> · <?= e($user['email']) ?></div>
          <div class="mb-3">
            <span class="status-mark <?= $user['role'] === 'admin' ? 'status-err' : 'status-done' ?>">
              <span class="status-shape <?= $user['role'] === 'admin' ? 'shape-diamond' : 'shape-circle' ?>"></span>
              <?= $user['role'] === 'admin' ? 'Quản trị viên và Ban biên tập' : 'Thành viên Bắc Du' ?>
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
          <span class="fw-bold"><i class="bi bi-person-lines-fill text-success me-1"></i>Cập nhật thông tin hồ sơ</span>
          <span class="font-monospace small text-muted">ID #<?= (int)$user['id'] ?></span>
        </div>
        <div class="cin-day-body">
          <form action="profile.php" method="post" enctype="multipart/form-data" class="row g-3" id="profileForm">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="update_profile">
            <input type="hidden" name="remove_avatar" id="removeAvatarInput" value="0">

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
              <label class="form-label small fw-bold">URL Ảnh đại diện (Tùy chọn)</label>
              <input type="url" name="avatar_url" id="avatarUrlInput" class="form-control" value="<?= e($user['avatar_url']) ?>" placeholder="https://... hoặc tải ảnh từ máy bên trái">
              <small class="text-muted" style="font-size: 0.72rem;">Ưu tiên chọn ảnh từ máy tính ở khung bên trái hoặc dán URL trực tiếp.</small>
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
          <span class="fw-bold"><i class="bi bi-shield-lock text-warning me-1"></i>Đổi mật khẩu bảo mật</span>
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

<script>
function previewAvatar(input) {
  if (input.files && input.files[0]) {
    const file = input.files[0];
    if (file.size > 5 * 1024 * 1024) {
      alert('Kích thước file ảnh vượt quá 5MB. Vui lòng chọn ảnh nhỏ hơn.');
      input.value = '';
      return;
    }
    const reader = new FileReader();
    reader.onload = function(e) {
      const img = document.getElementById('avatarPreview');
      if (img) img.src = e.target.result;
      const notice = document.getElementById('newAvatarNotice');
      if (notice) notice.classList.remove('d-none');
      const btnRemove = document.getElementById('btnRemoveAvatar');
      if (btnRemove) btnRemove.classList.remove('d-none');
      const removeInput = document.getElementById('removeAvatarInput');
      if (removeInput) removeInput.value = '0';
    };
    reader.readAsDataURL(file);
  }
}

function removeAvatar() {
  if (confirm('Bạn có chắc muốn gỡ ảnh đại diện hiện tại và quay về ảnh mặc định?')) {
    const removeInput = document.getElementById('removeAvatarInput');
    if (removeInput) removeInput.value = '1';
    const fileInput = document.getElementById('avatarFileInput');
    if (fileInput) fileInput.value = '';
    const urlInput = document.getElementById('avatarUrlInput');
    if (urlInput) urlInput.value = '';
    const img = document.getElementById('avatarPreview');
    if (img) img.src = 'assets/img/default-avatar.svg';
    document.getElementById('profileForm').submit();
  }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
