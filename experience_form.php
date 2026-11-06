<?php
require_once __DIR__ . '/includes/helpers.php';

$user = require_login('experience_form.php');
$pdo = get_db();

$postId = isset($_GET['id']) ? (int)$_GET['id'] : (int)($_POST['post_id'] ?? 0);
$preselectLocationId = isset($_GET['location_id']) ? (int)$_GET['location_id'] : 0;

$post = null;
$existingImages = [];
if ($postId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM experience_posts WHERE id = ? AND (user_id = ? OR ? = 1) LIMIT 1');
    $stmt->execute([$postId, $user['id'], $user['role'] === 'admin' ? 1 : 0]);
    $post = $stmt->fetch();
    if (!$post) {
        set_flash('danger', 'Không tìm thấy bài viết hoặc bạn không có quyền chỉnh sửa.');
        header('Location: dashboard.php');
        exit;
    }
    $stmtImgs = $pdo->prepare('SELECT * FROM experience_images WHERE post_id = ? ORDER BY sort_order ASC');
    $stmtImgs->execute([$postId]);
    $existingImages = $stmtImgs->fetchAll();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        set_flash('danger', 'Phiên làm việc không hợp lệ.');
        header('Location: experience_form.php');
        exit;
    }

    $action = $_POST['action'] ?? 'submit_review';
    if ($action === 'delete_post' && $post) {
        $pdo->prepare('DELETE FROM experience_posts WHERE id = ?')->execute([$post['id']]);
        set_flash('info', 'Đã xóa bài trải nghiệm của bạn.');
        header('Location: dashboard.php');
        exit;
    }

    $locationId = (int)($_POST['location_id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $visitMonth = max(1, min(12, (int)($_POST['visit_month'] ?? 2)));
    $visitYear = max(2020, min(2030, (int)($_POST['visit_year'] ?? date('Y'))));
    $rating = max(1, min(5, (int)($_POST['rating'] ?? 5)));
    $refCost = !empty($_POST['ref_cost']) ? (float)$_POST['ref_cost'] : null;
    $costUnit = trim($_POST['cost_unit'] ?? 'người');
    $content = trim($_POST['content'] ?? '');
    $prosLiked = trim($_POST['pros_liked'] ?? '');
    $consInconvenient = trim($_POST['cons_inconvenient'] ?? '');
    $tipsAdvice = trim($_POST['tips_advice'] ?? '');
    $imageUrlsRaw = trim($_POST['image_urls'] ?? '');

    // Quy tắc Mục 4.8:
    // - Người viết có thể Lưu nháp ('draft') hoặc Gửi duyệt ('pending').
    // - Bài đã duyệt ('approved') nếu được chỉnh sửa sẽ chuyển về 'pending' để Admin duyệt lại.
    $targetStatus = ($action === 'save_draft') ? 'draft' : 'pending';

    if ($locationId <= 0 || $title === '' || $content === '') {
        set_flash('danger', 'Vui lòng chọn Địa điểm, nhập Tiêu đề và Nội dung trải nghiệm.');
    } else {
        if ($post) {
            $upd = $pdo->prepare('
                UPDATE experience_posts
                SET location_id = ?, title = ?, visit_month = ?, visit_year = ?,
                    content = ?, rating = ?, ref_cost = ?, cost_unit = ?,
                    pros_liked = ?, cons_inconvenient = ?, tips_advice = ?,
                    status = ?, updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ');
            $upd->execute([
                $locationId, $title, $visitMonth, $visitYear,
                $content, $rating, $refCost, $costUnit,
                $prosLiked, $consInconvenient, $tipsAdvice,
                $targetStatus, $post['id']
            ]);
            $savedId = (int)$post['id'];
        } else {
            $ins = $pdo->prepare('
                INSERT INTO experience_posts (
                    user_id, location_id, title, visit_month, visit_year,
                    content, rating, ref_cost, cost_unit,
                    pros_liked, cons_inconvenient, tips_advice, status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ');
            $ins->execute([
                $user['id'], $locationId, $title, $visitMonth, $visitYear,
                $content, $rating, $refCost, $costUnit,
                $prosLiked, $consInconvenient, $tipsAdvice, $targetStatus
            ]);
            $savedId = (int)$pdo->lastInsertId();
        }

        // Lưu danh sách URL ảnh thực tế (mỗi dòng 1 URL)
        $pdo->prepare('DELETE FROM experience_images WHERE post_id = ?')->execute([$savedId]);
        if ($imageUrlsRaw !== '') {
            $urls = array_filter(array_map('trim', explode("\n", $imageUrlsRaw)));
            $insImg = $pdo->prepare('INSERT INTO experience_images (post_id, image_url, caption, sort_order) VALUES (?, ?, ?, ?)');
            $order = 1;
            foreach ($urls as $u) {
                if (filter_var($u, FILTER_VALIDATE_URL)) {
                    $insImg->execute([$savedId, $u, $title, $order++]);
                }
            }
        }

        if ($targetStatus === 'draft') {
            set_flash('info', 'Đã lưu bài viết ở trạng thái <strong>Bản nháp</strong>. Bạn có thể chỉnh sửa và gửi duyệt bất cứ lúc nào.');
        } else {
            set_flash('success', 'Đã gửi bài trải nghiệm tới Ban biên tập! Bài viết đang ở trạng thái <strong>Chờ duyệt</strong> trước khi hiển thị công khai.');
        }
        header('Location: dashboard.php');
        exit;
    }
}

$locations = $pdo->query("
    SELECT l.id, l.name, l.location_type, p.name AS province_name
    FROM locations l
    JOIN provinces p ON p.id = l.province_id
    WHERE l.status = 'active'
    ORDER BY p.id ASC, l.name ASC
")->fetchAll();

$defaultImageLines = implode("\n", array_map(fn($i) => $i['image_url'], $existingImages));
$pageTitle = $post ? 'Chỉnh sửa bài trải nghiệm' : 'Viết bài chia sẻ trải nghiệm';
require_once __DIR__ . '/includes/header.php';
?>

<!-- APP MODE SHIFT BAR -->
<header class="app-shift-bar mb-4">
  <div class="container">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
      <div>
        <div class="app-shift-eyebrow">
          <span><i class="bi bi-pencil-square text-warning me-1"></i>SOẠN THẢO NHẬT KÝ THỰC ĐỊA · MODULE M3 (UC09)</span>
          <span>·</span>
          <span>TÁC GIẢ: <strong class="text-white"><?= e($user['full_name']) ?></strong></span>
        </div>
        <h1 class="app-shift-title"><?= $post ? 'Chỉnh Sửa Bài Trải Nghiệm Cộng Đồng' : 'Viết Bài Chia Sẻ Trải Nghiệm Thực Địa' ?></h1>
      </div>
      <div class="d-flex flex-wrap gap-2">
        <a href="experiences.php" class="btn-cin-ghost py-1 px-3">
          <i class="bi bi-journal-richtext"></i> Cộng đồng
        </a>
        <a href="dashboard.php" class="btn-cin-ghost py-1 px-3" data-key="d">
          <i class="bi bi-arrow-left"></i> Về Dashboard <span class="app-kbd-badge">d</span>
        </a>
      </div>
    </div>
  </div>
</header>

<main id="mainContent" class="container py-4">
  <div class="row justify-content-center">
    <div class="col-lg-9">
      <?php if ($post && $post['status'] === 'rejected'): ?>
        <!-- ONE LIT REGION: Rejected post needing revision -->
        <section class="app-signature-set is-lit mb-4">
          <div class="app-lit-callout">
            <div class="d-flex align-items-center gap-3">
              <span class="lit-pulse" aria-hidden="true"></span>
              <div>
                <div class="fw-bold" style="color: var(--cin-ink); font-size: 0.9rem;">
                  YÊU CẦU CHỈNH SỬA TỪ BAN BIÊN TẬP (UC09.12 — UC09.13)
                </div>
                <div class="small" style="color: var(--cin-ink-muted);">
                  <?= e($post['rejection_reason']) ?>
                </div>
              </div>
            </div>
            <span class="status-mark status-err">Bị từ chối</span>
          </div>
        </section>
      <?php elseif ($post && $post['status'] === 'approved'): ?>
        <div class="cin-season-callout mb-4">
          <div>
            <span class="cin-eyebrow" style="color: var(--cin-amber-bright);">QUY TẮC KIỂM DUYỆT LẠI · MỤC 4.8</span>
            <div class="small mt-1" style="color: rgba(244,239,228,0.85);">
              Bài viết này hiện đang được công khai. Nếu bạn chỉnh sửa nội dung và nhấn <strong>Gửi duyệt công khai</strong>, bài viết sẽ chuyển về trạng thái <strong>Chờ duyệt lại</strong> bởi Admin.
            </div>
          </div>
          <span class="status-mark status-done">
            <span class="status-shape shape-circle"></span>Đang công khai
          </span>
        </div>
      <?php endif; ?>

      <section class="cin-day-dossier mb-0">
        <div class="cin-day-header">
          <div>
            <span class="cin-day-badge me-2"><?= $post ? 'HỒ SƠ #' . (int)$post['id'] : 'NHẬT KÝ MỚI' ?></span>
            <span class="fw-bold" style="font-family: var(--font-display); font-size: 1.2rem;">
              <?= $post ? 'Chỉnh sửa báo cáo trải nghiệm thực địa' : 'Ghi chép trải nghiệm thực địa mới' ?>
            </span>
          </div>
          <span class="font-monospace small text-muted">KIỂM DUYỆT TRƯỚC KHI XUẤT BẢN</span>
        </div>

        <div class="cin-day-body p-4">
          <form action="experience_form.php<?= $post ? '?id=' . (int)$post['id'] : '' ?>" method="post" class="row g-3">
            <?= csrf_field() ?>
            <input type="hidden" name="post_id" value="<?= (int)($post['id'] ?? 0) ?>">

            <div class="col-md-8">
              <label class="form-label small fw-bold">1. Tọa độ bạn đã trải nghiệm *</label>
              <select name="location_id" class="form-select" required>
                <option value="">-- Chọn điểm tham quan / quán ăn / nơi lưu trú --</option>
                <?php foreach ($locations as $loc): ?>
                  <?php
                    $tMeta = location_type_meta($loc['location_type']);
                    $selectedId = (int)($post['location_id'] ?? $preselectLocationId);
                  ?>
                  <option value="<?= (int)$loc['id'] ?>" <?= $selectedId === (int)$loc['id'] ? 'selected' : '' ?>>
                    [<?= e($loc['province_name']) ?> · <?= e($tMeta['short']) ?>] <?= e($loc['name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-md-4">
              <label class="form-label small fw-bold">2. Đánh giá tổng thể (1–5 sao) *</label>
              <select name="rating" class="form-select fw-bold" style="color: var(--cin-amber);">
                <?php for ($r = 5; $r >= 1; $r--): ?>
                  <option value="<?= $r ?>" <?= (int)($post['rating'] ?? 5) === $r ? 'selected' : '' ?>>
                    <?= $r ?> Sao <?= $r === 5 ? '— Rất tuyệt vời' : ($r === 4 ? '— Đáng trải nghiệm' : '') ?>
                  </option>
                <?php endfor; ?>
              </select>
            </div>

            <div class="col-12">
              <label class="form-label small fw-bold">3. Tiêu đề bài chia sẻ *</label>
              <input type="text" name="title" class="form-control"
                     value="<?= e($post['title'] ?? '') ?>"
                     placeholder="VD: Kinh nghiệm săn hoa mận Nà Ka đầu tháng 2: Giờ chụp đẹp và góc vắng người" required>
            </div>

            <div class="col-md-3">
              <label class="form-label small fw-bold">Tháng đã đi</label>
              <select name="visit_month" class="form-select font-monospace">
                <?php for ($m = 1; $m <= 12; $m++): ?>
                  <option value="<?= $m ?>" <?= (int)($post['visit_month'] ?? 2) === $m ? 'selected' : '' ?>>Tháng <?= $m ?></option>
                <?php endfor; ?>
              </select>
            </div>

            <div class="col-md-3">
              <label class="form-label small fw-bold">Năm trải nghiệm</label>
              <input type="number" name="visit_year" class="form-control font-monospace" value="<?= (int)($post['visit_year'] ?? date('Y')) ?>" min="2020" max="2030">
            </div>

            <div class="col-md-3">
              <label class="form-label small fw-bold">Chi phí thực tế (VND)</label>
              <input type="number" name="ref_cost" class="form-control font-monospace" step="10000" value="<?= e((string)($post['ref_cost'] ?? '')) ?>" placeholder="VD: 150000">
            </div>

            <div class="col-md-3">
              <label class="form-label small fw-bold">Đơn vị tính</label>
              <input type="text" name="cost_unit" class="form-control" value="<?= e($post['cost_unit'] ?? 'người') ?>" placeholder="người / phòng/đêm">
            </div>

            <div class="col-12">
              <label class="form-label small fw-bold">4. Nội dung trải nghiệm chi tiết *</label>
              <textarea name="content" rows="5" class="form-control"
                        placeholder="Chia sẻ chi tiết về khung cảnh thực tế, đường đi, chất lượng món ăn/phòng nghỉ..." required><?= e($post['content'] ?? '') ?></textarea>
            </div>

            <div class="col-md-4">
              <label class="form-label small fw-bold text-success"><i class="bi bi-hand-thumbs-up-fill me-1"></i>Điều yêu thích</label>
              <textarea name="pros_liked" rows="3" class="form-control" placeholder="Cảnh đẹp, đồ ăn tươi, chủ nhà nhiệt tình..."><?= e($post['pros_liked'] ?? '') ?></textarea>
            </div>

            <div class="col-md-4">
              <label class="form-label small fw-bold text-danger"><i class="bi bi-exclamation-circle-fill me-1"></i>Điểm bất tiện</label>
              <textarea name="cons_inconvenient" rows="3" class="form-control" placeholder="Đường dốc, cuối tuần đông khách..."><?= e($post['cons_inconvenient'] ?? '') ?></textarea>
            </div>

            <div class="col-md-4">
              <label class="form-label small fw-bold" style="color: var(--cin-amber);"><i class="bi bi-lightbulb-fill me-1"></i>Lời khuyên cho người đi sau</label>
              <textarea name="tips_advice" rows="3" class="form-control" placeholder="Nên đi giờ nào, mang trang phục gì..."><?= e($post['tips_advice'] ?? '') ?></textarea>
            </div>

            <div class="col-12">
              <label class="form-label small fw-bold">5. Ảnh thực tế (Mỗi dòng 1 URL hình ảnh)</label>
              <textarea name="image_urls" rows="2" class="form-control font-monospace small"
                        placeholder="https://images.unsplash.com/photo-1490750967868-88aa4486c946?w=800"><?= e($defaultImageLines) ?></textarea>
            </div>

            <div class="col-12 pt-3 border-top d-flex flex-wrap justify-content-between align-items-center gap-2">
              <div class="d-flex flex-wrap gap-2">
                <button type="submit" name="action" value="submit_review" class="btn-cin-amber">
                  <i class="bi bi-send-check-fill"></i><?= ($post && $post['status'] === 'rejected') ? 'Gửi duyệt lại (UC09.13)' : 'Gửi duyệt công khai (UC09.8)' ?>
                </button>
                <button type="submit" name="action" value="save_draft" class="btn-cin-outline">
                  <i class="bi bi-file-earmark-text"></i>Lưu bản nháp (UC09.7)
                </button>
              </div>
            </div>
          </form>

          <?php if ($post): ?>
            <div class="mt-3 pt-3 border-top text-end">
              <form action="experience_form.php?id=<?= (int)$post['id'] ?>" method="post" onsubmit="return confirm('Bạn chắc chắn muốn xóa bài viết này?');" class="d-inline m-0">
                <?= csrf_field() ?>
                <input type="hidden" name="post_id" value="<?= (int)$post['id'] ?>">
                <input type="hidden" name="action" value="delete_post">
                <button type="submit" class="btn-cin-outline text-danger">
                  <i class="bi bi-trash"></i>Xóa bài viết (UC09.10)
                </button>
              </form>
            </div>
          <?php endif; ?>
        </div>
      </section>
    </div>
  </div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
