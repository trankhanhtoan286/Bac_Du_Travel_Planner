<?php
require_once 'config/db.php';
require_once 'includes/header.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'] ?? '';
    $content = $_POST['content'] ?? '';
    // Luu danh gia
}
?>
<form method="POST">
  <input type="text" name="title" placeholder="Tiêu đề bài viết" required>
  <textarea name="content" placeholder="Chia sẻ trải nghiệm"></textarea>
  <button type="submit">Gửi bài</button>
</form>
<?php require_once 'includes/footer.php'; ?>