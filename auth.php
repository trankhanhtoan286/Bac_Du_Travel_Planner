<?php
require_once 'config/db.php';
require_once 'includes/helpers.php';

$action = $_GET['action'] ?? 'login';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Xu ly dang nhap co ban
    $email = $_POST['email'] ?? '';
    $pass = $_POST['password'] ?? '';
    if ($email && $pass) {
        // Login logic
    }
}
require_once 'includes/header.php';
?>
<div class="auth-container">
  <h2>Đăng nhập / Đăng ký</h2>
  <form method="POST">
    <input type="email" name="email" placeholder="Email" required>
    <input type="password" name="password" placeholder="Mật khẩu" required>
    <button type="submit">Xác nhận</button>
  </form>
</div>
<?php require_once 'includes/footer.php'; ?>