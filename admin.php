<?php
require_once __DIR__ . '/includes/helpers.php';

$admin = require_admin();
$pdo = get_db();
$tab = $_GET['tab'] ?? 'moderation';
$monthsCatalog = get_months_catalog();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        set_flash('danger', 'Phiên làm việc không hợp lệ.');
        header('Location: admin.php');
        exit;
    }

    $action = $_POST['action'] ?? '';
    $redirectTab = $_POST['redirect_tab'] ?? $tab;

    // 1. KIỂM DUYỆT BÀI TRẢI NGHIỆM (Duyệt / Từ chối kèm lý do / Ẩn bài)
    if ($action === 'moderate_post') {
        $postId = (int)($_POST['post_id'] ?? 0);
        $newStatus = $_POST['new_status'] ?? 'approved';
        $reason = trim($_POST['rejection_reason'] ?? '');

        if (in_array($newStatus, ['approved', 'rejected', 'hidden', 'pending'], true) && $postId > 0) {
            if ($newStatus === 'rejected' && $reason === '') {
                $reason = 'Bài viết cần bổ sung thêm thông tin chi tiết trải nghiệm thực tế và hình ảnh rõ nét hơn.';
            }
            $upd = $pdo->prepare('
                UPDATE experience_posts
                SET status = ?, rejection_reason = ?, reviewed_by = ?, reviewed_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ');
            $upd->execute([$newStatus, $newStatus === 'rejected' ? $reason : null, $admin['id'], $postId]);
            $stMeta = post_status_meta($newStatus);
            set_flash('success', 'Đã cập nhật trạng thái bài viết #' . $postId . ' thành: <strong>' . e($stMeta['label']) . '</strong>.');
        }
        $redirectTab = 'moderation';
    }

    // 2. QUẢN LÝ LỊCH TRÌNH MẪU (Tạo mới / Cập nhật / Đổi trạng thái Nháp - Xuất bản - Lưu trữ)
    elseif ($action === 'save_template') {
        $tplId = (int)($_POST['template_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $provinceId = (int)($_POST['province_id'] ?? 1);
        $mainDest = trim($_POST['main_destination'] ?? '');
        $durationDays = max(1, (int)($_POST['duration_days'] ?? 3));
        $durationNights = max(0, (int)($_POST['duration_nights'] ?? ($durationDays - 1)));
        $assumedDep = trim($_POST['assumed_departure'] ?? 'Hà Nội');
        $transport = trim($_POST['suggested_transport'] ?? 'Xe limousine + Xe máy');
        $refBudget = max(0, (float)($_POST['ref_budget_per_person'] ?? 2500000));
        $basePax = max(1, (int)($_POST['base_pax_count'] ?? 2));
        $seasonalReason = trim($_POST['seasonal_reason'] ?? '');
        $summary = trim($_POST['summary'] ?? '');
        $notes = trim($_POST['essential_notes'] ?? '');
        $thumbUrl = trim($_POST['thumbnail_url'] ?? 'https://images.unsplash.com/photo-1490750967868-88aa4486c946?w=900&auto=format&fit=crop&q=80');
        $status = in_array($_POST['status'] ?? '', ['draft', 'published', 'archived'], true) ? $_POST['status'] : 'published';
        $selectedMonths = isset($_POST['months']) && is_array($_POST['months']) ? array_map('intval', $_POST['months']) : [2];

        if ($title !== '' && $mainDest !== '') {
            if ($tplId > 0) {
                $upd = $pdo->prepare('
                    UPDATE itinerary_templates
                    SET title = ?, province_id = ?, main_destination = ?, thumbnail_url = ?,
                        duration_days = ?, duration_nights = ?, assumed_departure = ?, suggested_transport = ?,
                        ref_budget_per_person = ?, base_pax_count = ?, seasonal_reason = ?,
                        summary = ?, essential_notes = ?, status = ?, updated_date = ?
                    WHERE id = ?
                ');
                $upd->execute([
                    $title, $provinceId, $mainDest, $thumbUrl,
                    $durationDays, $durationNights, $assumedDep, $transport,
                    $refBudget, $basePax, $seasonalReason,
                    $summary, $notes, $status, date('Y-m-d'), $tplId
                ]);
                $savedTplId = $tplId;
            } else {
                $slug = slugify($title) . '-' . substr(uniqid(), -4);
                $ins = $pdo->prepare('
                    INSERT INTO itinerary_templates (
                        title, slug, province_id, main_destination, thumbnail_url,
                        duration_days, duration_nights, assumed_departure, suggested_transport,
                        ref_budget_per_person, base_pax_count, seasonal_reason,
                        summary, essential_notes, status, author_id, updated_date
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ');
                $ins->execute([
                    $title, $slug, $provinceId, $mainDest, $thumbUrl,
                    $durationDays, $durationNights, $assumedDep, $transport,
                    $refBudget, $basePax, $seasonalReason,
                    $summary, $notes, $status, $admin['id'], date('Y-m-d')
                ]);
                $savedTplId = (int)$pdo->lastInsertId();

                // Tự động tạo khung các ngày mẫu ban đầu cho mẫu mới
                $insDay = $pdo->prepare('INSERT INTO template_days (template_id, day_number, title, summary) VALUES (?, ?, ?, ?)');
                for ($d = 1; $d <= $durationDays; $d++) {
                    $insDay->execute([$savedTplId, $d, "Ngày {$d}: Khám phá {$mainDest}", "Lịch trình tham quan và ẩm thực ngày {$d}"]);
                }
            }

            // Cập nhật các tháng phù hợp (template_months)
            $pdo->prepare('DELETE FROM template_months WHERE template_id = ?')->execute([$savedTplId]);
            $insM = $pdo->prepare('INSERT INTO template_months (template_id, month_number, month_reason) VALUES (?, ?, ?)');
            foreach ($selectedMonths as $mNum) {
                if ($mNum >= 1 && $mNum <= 12) {
                    $insM->execute([$savedTplId, $mNum, $seasonalReason]);
                }
            }

            set_flash('success', 'Đã lưu lịch trình mẫu <strong>' . e($title) . '</strong> và gán vào các tháng đã chọn.');
        }
        $redirectTab = 'templates';
    }

    elseif ($action === 'toggle_template_status') {
        $tplId = (int)($_POST['template_id'] ?? 0);
        $newStatus = $_POST['new_status'] ?? 'published';
        if (in_array($newStatus, ['draft', 'published', 'archived'], true)) {
            $pdo->prepare('UPDATE itinerary_templates SET status = ?, updated_date = ? WHERE id = ?')
                ->execute([$newStatus, date('Y-m-d'), $tplId]);
            set_flash('info', 'Đã cập nhật trạng thái xuất bản của lịch trình mẫu.');
        }
        $redirectTab = 'templates';
    }

    // 3. QUẢN LÝ ĐỊA ĐIỂM (Thêm mới / Cập nhật giá tham khảo / Lưu trữ thay vì xóa cứng)
    elseif ($action === 'save_location') {
        $locId = (int)($_POST['location_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $provinceId = (int)($_POST['province_id'] ?? 1);
        $categoryId = (int)($_POST['category_id'] ?? 1);
        $locType = in_array($_POST['location_type'] ?? '', ['attraction', 'food', 'stay'], true) ? $_POST['location_type'] : 'attraction';
        $thumbUrl = trim($_POST['thumbnail_url'] ?? 'https://images.unsplash.com/photo-1528127269322-539801943592?w=800&auto=format&fit=crop&q=80');
        $shortDesc = trim($_POST['short_desc'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $lat = (float)($_POST['latitude'] ?? 20.8448);
        $lng = (float)($_POST['longitude'] ?? 104.6391);
        $openHours = trim($_POST['opening_hours'] ?? '07:00 - 18:00');
        $contact = trim($_POST['contact_info'] ?? '');
        $refPrice = max(0, (float)($_POST['ref_price'] ?? 0));
        $priceUnit = trim($_POST['price_unit'] ?? 'người');
        // Thông tin đặc thù
        $seasonalNote = trim($_POST['seasonal_note'] ?? '');
        $visitDuration = trim($_POST['visit_duration'] ?? '');
        $sigDishes = trim($_POST['signature_dishes'] ?? '');
        $stayType = trim($_POST['stay_type'] ?? '');
        $amenities = trim($_POST['amenities'] ?? '');

        if ($name !== '') {
            if ($locId > 0) {
                $upd = $pdo->prepare('
                    UPDATE locations
                    SET name = ?, province_id = ?, category_id = ?, location_type = ?,
                        thumbnail_url = ?, short_desc = ?, address = ?, latitude = ?, longitude = ?,
                        opening_hours = ?, contact_info = ?, ref_price = ?, price_unit = ?, price_updated_at = ?,
                        seasonal_note = ?, visit_duration = ?, signature_dishes = ?, stay_type = ?, amenities = ?
                    WHERE id = ?
                ');
                $upd->execute([
                    $name, $provinceId, $categoryId, $locType,
                    $thumbUrl, $shortDesc, $address, $lat, $lng,
                    $openHours, $contact, $refPrice, $priceUnit, date('Y-m-d'),
                    $seasonalNote, $visitDuration, $sigDishes, $stayType, $amenities,
                    $locId
                ]);
                set_flash('success', 'Đã cập nhật thông tin và ngày cập nhật giá cho địa điểm <strong>' . e($name) . '</strong>.');
            } else {
                $slug = slugify($name) . '-' . substr(uniqid(), -4);
                $ins = $pdo->prepare('
                    INSERT INTO locations (
                        province_id, category_id, name, slug, location_type,
                        thumbnail_url, short_desc, description, address, latitude, longitude,
                        opening_hours, contact_info, ref_price, price_unit, price_updated_at,
                        seasonal_note, visit_duration, signature_dishes, stay_type, amenities,
                        status, created_by
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ');
                $ins->execute([
                    $provinceId, $categoryId, $name, $slug, $locType,
                    $thumbUrl, $shortDesc, $shortDesc, $address, $lat, $lng,
                    $openHours, $contact, $refPrice, $priceUnit, date('Y-m-d'),
                    $seasonalNote, $visitDuration, $sigDishes, $stayType, $amenities,
                    'active', $admin['id']
                ]);
                set_flash('success', 'Đã thêm địa điểm mới <strong>' . e($name) . '</strong> vào hệ thống.');
            }
        }
        $redirectTab = 'locations';
    }

    elseif ($action === 'toggle_location_status') {
        $locId = (int)($_POST['location_id'] ?? 0);
        $newStatus = ($_POST['new_status'] ?? 'active') === 'archived' ? 'archived' : 'active';
        $pdo->prepare('UPDATE locations SET status = ? WHERE id = ?')->execute([$newStatus, $locId]);
        set_flash('info', $newStatus === 'archived'
            ? 'Đã chuyển địa điểm sang trạng thái Lưu trữ (Ngừng hiển thị công khai nhưng bảo toàn dữ liệu cho các lịch trình đang tham chiếu).'
            : 'Đã kích hoạt hiển thị lại địa điểm.');
        $redirectTab = 'locations';
    }

    // 4. QUẢN LÝ NGƯỜI DÙNG (Khóa / Mở khóa tài khoản & Phân quyền)
    elseif ($action === 'toggle_user_status') {
        $targetUserId = (int)($_POST['user_id'] ?? 0);
        if ($targetUserId === (int)$admin['id']) {
            set_flash('warning', 'Không thể tự khóa tài khoản Quản trị viên đang đăng nhập.');
        } else {
            $newStatus = ($_POST['new_status'] ?? 'active') === 'locked' ? 'locked' : 'active';
            $pdo->prepare('UPDATE users SET status = ? WHERE id = ?')->execute([$newStatus, $targetUserId]);
            set_flash('success', $newStatus === 'locked' ? 'Đã khóa tài khoản người dùng.' : 'Đã mở khóa tài khoản người dùng.');
        }
        $redirectTab = 'users';
    }

    elseif ($action === 'change_user_role') {
        $targetUserId = (int)($_POST['user_id'] ?? 0);
        $newRole = ($_POST['new_role'] ?? 'user') === 'admin' ? 'admin' : 'user';
        if ($targetUserId !== (int)$admin['id']) {
            $pdo->prepare('UPDATE users SET role = ? WHERE id = ?')->execute([$newRole, $targetUserId]);
            set_flash('success', 'Đã cập nhật quyền hạn cho người dùng.');
        }
        $redirectTab = 'users';
    }

    // 5. UC10.2: QUẢN LÝ TỈNH / THÀNH
    elseif ($action === 'save_province') {
        $provId = (int)($_POST['province_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $subregion = trim($_POST['subregion'] ?? 'Tây Bắc');
        $bestMonths = trim($_POST['best_months'] ?? '1,2,3,10,11,12');
        $desc = trim($_POST['description'] ?? '');
        $imgUrl = trim($_POST['image_url'] ?? 'https://images.unsplash.com/photo-1528127269322-539801943592?w=900');

        if ($name !== '') {
            if ($provId > 0) {
                $pdo->prepare('UPDATE provinces SET name = ?, subregion = ?, best_months = ?, description = ?, image_url = ? WHERE id = ?')
                    ->execute([$name, $subregion, $bestMonths, $desc, $imgUrl, $provId]);
                set_flash('success', 'Đã cập nhật thông tin Tỉnh/Thành <strong>' . e($name) . '</strong> (UC10.2).');
            } else {
                $slug = slugify($name) . '-' . substr(uniqid(), -3);
                $pdo->prepare('INSERT INTO provinces (name, slug, subregion, best_months, description, image_url, is_active) VALUES (?, ?, ?, ?, ?, ?, 1)')
                    ->execute([$name, $slug, $subregion, $bestMonths, $desc, $imgUrl]);
                set_flash('success', 'Đã thêm Tỉnh/Thành mới <strong>' . e($name) . '</strong> (UC10.2).');
            }
        }
        $redirectTab = 'catalogs';
    }

    elseif ($action === 'toggle_province') {
        $provId = (int)($_POST['province_id'] ?? 0);
        $pdo->prepare('UPDATE provinces SET is_active = CASE WHEN is_active = 1 THEN 0 ELSE 1 END WHERE id = ?')->execute([$provId]);
        set_flash('info', 'Đã cập nhật trạng thái hiển thị Tỉnh/Thành (UC10.2).');
        $redirectTab = 'catalogs';
    }

    // 6. UC10.3: QUẢN LÝ DANH MỤC ĐỊA ĐIỂM
    elseif ($action === 'save_category') {
        $catId = (int)($_POST['category_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $locType = in_array($_POST['location_type'] ?? '', ['attraction', 'food', 'stay'], true) ? $_POST['location_type'] : 'attraction';
        $icon = trim($_POST['icon'] ?? 'bi-geo-alt');
        $desc = trim($_POST['description'] ?? '');

        if ($name !== '') {
            if ($catId > 0) {
                $pdo->prepare('UPDATE categories SET name = ?, location_type = ?, icon = ?, description = ? WHERE id = ?')
                    ->execute([$name, $locType, $icon, $desc, $catId]);
                set_flash('success', 'Đã cập nhật Danh mục <strong>' . e($name) . '</strong> (UC10.3).');
            } else {
                $slug = slugify($name) . '-' . substr(uniqid(), -3);
                $pdo->prepare('INSERT INTO categories (name, slug, location_type, icon, description, is_active) VALUES (?, ?, ?, ?, ?, 1)')
                    ->execute([$name, $slug, $locType, $icon, $desc]);
                set_flash('success', 'Đã thêm Danh mục mới <strong>' . e($name) . '</strong> (UC10.3).');
            }
        }
        $redirectTab = 'catalogs';
    }

    elseif ($action === 'toggle_category') {
        $catId = (int)($_POST['category_id'] ?? 0);
        $pdo->prepare('UPDATE categories SET is_active = CASE WHEN is_active = 1 THEN 0 ELSE 1 END WHERE id = ?')->execute([$catId]);
        set_flash('info', 'Đã cập nhật trạng thái Danh mục (UC10.3).');
        $redirectTab = 'catalogs';
    }

    // 7. UC10.7: QUẢN LÝ NGÀY & HOẠT ĐỘNG TRONG LỊCH TRÌNH MẪU
    elseif ($action === 'save_template_day') {
        $tplId = (int)($_POST['template_id'] ?? 1);
        $dayId = (int)($_POST['template_day_id'] ?? 0);
        $dayNum = max(1, (int)($_POST['day_number'] ?? 1));
        $title = trim($_POST['title'] ?? '');
        $summary = trim($_POST['summary'] ?? '');
        $transNote = trim($_POST['transport_note'] ?? '');

        if ($title !== '') {
            if ($dayId > 0) {
                $pdo->prepare('UPDATE template_days SET day_number = ?, title = ?, summary = ?, transport_note = ? WHERE id = ? AND template_id = ?')
                    ->execute([$dayNum, $title, $summary, $transNote, $dayId, $tplId]);
                set_flash('success', 'Đã cập nhật ngày trong lịch trình mẫu (UC10.7).');
            } else {
                $pdo->prepare('INSERT INTO template_days (template_id, day_number, title, summary, transport_note) VALUES (?, ?, ?, ?, ?)')
                    ->execute([$tplId, $dayNum, $title, $summary, $transNote]);
                set_flash('success', 'Đã thêm ngày mới vào lịch trình mẫu (UC10.7).');
            }
        }
        header('Location: admin.php?tab=template_days&template_id=' . $tplId);
        exit;
    }

    elseif ($action === 'delete_template_day') {
        $tplId = (int)($_POST['template_id'] ?? 1);
        $dayId = (int)($_POST['template_day_id'] ?? 0);
        $pdo->prepare('DELETE FROM template_days WHERE id = ? AND template_id = ?')->execute([$dayId, $tplId]);
        set_flash('info', 'Đã xóa ngày khỏi lịch trình mẫu.');
        header('Location: admin.php?tab=template_days&template_id=' . $tplId);
        exit;
    }

    elseif ($action === 'save_template_activity') {
        $tplId = (int)($_POST['template_id'] ?? 1);
        $actId = (int)($_POST['template_activity_id'] ?? 0);
        $tDayId = (int)($_POST['template_day_id'] ?? 0);
        $locId = !empty($_POST['location_id']) ? (int)$_POST['location_id'] : null;
        $title = trim($_POST['title'] ?? '');
        $actType = $_POST['activity_type'] ?? 'attraction';
        $startTime = trim($_POST['start_time'] ?? '08:00');
        $endTime = trim($_POST['end_time'] ?? '10:00');
        $desc = trim($_POST['description'] ?? '');
        $transNote = trim($_POST['transport_note'] ?? '');
        $estCost = max(0, (float)($_POST['estimated_cost'] ?? 0));
        $impNote = trim($_POST['important_note'] ?? '');
        $sortOrder = max(1, (int)($_POST['sort_order'] ?? 1));

        if ($tDayId > 0 && $title !== '') {
            if ($actId > 0) {
                $pdo->prepare('
                    UPDATE template_activities
                    SET template_day_id = ?, location_id = ?, title = ?, activity_type = ?,
                        start_time = ?, end_time = ?, description = ?, transport_note = ?,
                        estimated_cost = ?, important_note = ?, sort_order = ?
                    WHERE id = ?
                ')->execute([$tDayId, $locId, $title, $actType, $startTime, $endTime, $desc, $transNote, $estCost, $impNote, $sortOrder, $actId]);
                set_flash('success', 'Đã cập nhật hoạt động trong lịch trình mẫu (UC10.7).');
            } else {
                $pdo->prepare('
                    INSERT INTO template_activities (
                        template_day_id, location_id, title, activity_type,
                        start_time, end_time, description, transport_note,
                        estimated_cost, important_note, sort_order
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ')->execute([$tDayId, $locId, $title, $actType, $startTime, $endTime, $desc, $transNote, $estCost, $impNote, $sortOrder]);
                set_flash('success', 'Đã thêm hoạt động mới vào ngày mẫu (UC10.7).');
            }
        }
        header('Location: admin.php?tab=template_days&template_id=' . $tplId);
        exit;
    }

    elseif ($action === 'delete_template_activity') {
        $tplId = (int)($_POST['template_id'] ?? 1);
        $actId = (int)($_POST['template_activity_id'] ?? 0);
        $pdo->prepare('DELETE FROM template_activities WHERE id = ?')->execute([$actId]);
        set_flash('info', 'Đã xóa hoạt động khỏi lịch trình mẫu.');
        header('Location: admin.php?tab=template_days&template_id=' . $tplId);
        exit;
    }

    header('Location: admin.php?tab=' . urlencode($redirectTab));
    exit;
}

// THỐNG KÊ TỔNG QUAN HỆ THỐNG (Admin Dashboard - Mục 4.13)
$stats = [
    'users'          => (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn(),
    'trips'          => (int)$pdo->query('SELECT COUNT(*) FROM trips')->fetchColumn(),
    'locations'      => (int)$pdo->query("SELECT COUNT(*) FROM locations WHERE status = 'active'")->fetchColumn(),
    'templates'      => (int)$pdo->query("SELECT COUNT(*) FROM itinerary_templates WHERE status = 'published'")->fetchColumn(),
    'pending_posts'  => (int)$pdo->query("SELECT COUNT(*) FROM experience_posts WHERE status = 'pending'")->fetchColumn(),
    'approved_posts' => (int)$pdo->query("SELECT COUNT(*) FROM experience_posts WHERE status = 'approved'")->fetchColumn(),
];

// Dữ liệu cho các Tab quản trị
$allPosts = $pdo->query('
    SELECT
        ep.*,
        u.full_name AS author_name,
        u.username AS author_username,
        l.name AS location_name,
        p.name AS province_name
    FROM experience_posts ep
    JOIN users u ON u.id = ep.user_id
    JOIN locations l ON l.id = ep.location_id
    JOIN provinces p ON p.id = l.province_id
    ORDER BY CASE WHEN ep.status = \'pending\' THEN 0 ELSE 1 END, ep.created_at DESC
')->fetchAll();

$allTemplates = $pdo->query('
    SELECT
        it.*,
        p.name AS province_name,
        (SELECT COUNT(*) FROM trips t WHERE t.source_template_id = it.id) AS actual_trips_created
    FROM itinerary_templates it
    JOIN provinces p ON p.id = it.province_id
    ORDER BY it.copies_count DESC, it.views_count DESC
')->fetchAll();

$monthsByTemplate = [];
foreach ($pdo->query('SELECT template_id, month_number FROM template_months ORDER BY month_number ASC')->fetchAll() as $rm) {
    $monthsByTemplate[(int)$rm['template_id']][] = (int)$rm['month_number'];
}

$allLocations = $pdo->query('
    SELECT
        l.*,
        p.name AS province_name,
        c.name AS category_name
    FROM locations l
    JOIN provinces p ON p.id = l.province_id
    JOIN categories c ON c.id = l.category_id
    ORDER BY l.id DESC
')->fetchAll();

$allUsers = $pdo->query('
    SELECT
        u.*,
        (SELECT COUNT(*) FROM trips t WHERE t.user_id = u.id) AS trips_count,
        (SELECT COUNT(*) FROM experience_posts ep WHERE ep.user_id = u.id) AS posts_count
    FROM users u
    ORDER BY u.id ASC
')->fetchAll();

$provinces = $pdo->query('SELECT * FROM provinces ORDER BY id ASC')->fetchAll();
$categories = $pdo->query('SELECT * FROM categories ORDER BY location_type ASC, id ASC')->fetchAll();

$pageTitle = 'Admin Dashboard & Ban biên tập nội dung';
require_once __DIR__ . '/includes/header.php';
?>

<?php
// Tính ma trận phủ sóng 12 tháng cho Signature Set B (App Mode)
$monthCoverage = [];
for ($m = 1; $m <= 12; $m++) {
    $monthCoverage[$m] = ['templates' => 0, 'copies' => 0, 'views' => 0];
}
foreach ($allTemplates as $tplItem) {
    $tId = (int)$tplItem['id'];
    $mList = $monthsByTemplate[$tId] ?? [];
    foreach ($mList as $mNum) {
        if (isset($monthCoverage[$mNum])) {
            $monthCoverage[$mNum]['templates']++;
            $monthCoverage[$mNum]['copies'] += (int)$tplItem['copies_count'];
            $monthCoverage[$mNum]['views'] += (int)$tplItem['views_count'];
        }
    }
}
?>

<!-- APP MODE SHIFT HEADER FOR ADMIN & EDITORIAL CREW -->
<header class="app-shift-bar mb-4">
  <div class="container">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
      <div>
        <div class="app-shift-eyebrow">
          <span><i class="bi bi-shield-lock-fill text-warning me-1"></i>BAN BIÊN TẬP & QUẢN TRỊ HỆ THỐNG · APP MODE</span>
          <span>·</span>
          <span>MODULE M8 · CA TRỰC: @<?= e($admin['username']) ?></span>
        </div>
        <h1 class="app-shift-title">Bàn Điều Phối Nội Dung & Kiểm Duyệt Bắc Du</h1>
        <p class="small text-white-50 mb-0">
          Phím tắt ca trực: <kbd class="bg-dark border border-secondary">1</kbd>–<kbd class="bg-dark border border-secondary">6</kbd> chuyển phân khu · <kbd class="bg-dark border border-secondary">j</kbd>/<kbd class="bg-dark border border-secondary">k</kbd> duyệt dòng · <kbd class="bg-dark border border-secondary">/</kbd> mở Bảng lệnh nhanh.
        </p>
      </div>
      <div class="d-flex flex-wrap gap-2">
        <button type="button" class="btn-cin-ghost py-1 px-3" onclick="window.BacDuApp && window.BacDuApp.openPalette()" data-key="/">
          <i class="bi bi-terminal"></i> Lệnh nhanh
        </button>
        <a href="templates.php?month=2" class="btn-cin-ghost py-1 px-3" data-key="v" data-palette-label="Xem giao diện Khách (Lịch trình 12 tháng)">
          <i class="bi bi-eye"></i> Xem trang Khách
        </a>
        <button type="button" class="btn-cin-amber py-1 px-3" data-bs-toggle="modal" data-bs-target="#templateEditorModal" onclick="resetTemplateModal()" data-key="n" data-palette-label="Biên soạn lịch trình mẫu mới">
          <i class="bi bi-plus-circle"></i> + Biên soạn mẫu mới
        </button>
      </div>
    </div>
  </div>
</header>

<main class="container pb-5" id="mainContent">
  <!-- ========================================================================
       SIGNATURE SET B (APP MODE): 12-MONTH EDITORIAL COVERAGE MATRIX & LIT TRIAGE
       Replaces generic 5-card KPI row with a bespoke 12-month coverage instrument
       ======================================================================== -->
  <section class="app-signature-set <?= $stats['pending_posts'] > 0 ? 'is-lit' : '' ?>" id="editorialMatrixSet" aria-label="Ma trận biên tập 12 tháng và hàng đợi kiểm duyệt">
    <!-- 1. THE ONE LIT REGION: Only lit when pending posts require human moderation -->
    <?php if ($stats['pending_posts'] > 0): ?>
      <div class="app-lit-callout mb-3">
        <div>
          <span class="status-mark status-warn me-2">CẦN DUYỆT NGAY (<?= $stats['pending_posts'] ?>)</span>
          <span class="small fw-semibold">
            Đang có <strong><?= $stats['pending_posts'] ?> bài trải nghiệm cộng đồng</strong> ở trạng thái chờ kiểm duyệt. Hãy duyệt công khai hoặc gửi lý do từ chối.
          </span>
        </div>
        <a href="admin.php?tab=moderation" class="btn-cin-amber py-1 px-3">Mở hàng đợi kiểm duyệt</a>
      </div>
    <?php else: ?>
      <div class="app-calm-callout mb-3">
        <span><span class="status-mark status-ok me-2">HÀNG ĐỢI ĐÃ SẠCH</span> Toàn bộ <?= $stats['approved_posts'] ?> bài trải nghiệm hợp lệ đã được duyệt công khai.</span>
        <span class="font-monospace small">0 BÀI TỒN ĐỌNG</span>
      </div>
    <?php endif; ?>

    <div class="row g-3 align-items-center">
      <!-- 2. 12-MONTH SEASONAL COVERAGE & COPY MATRIX -->
      <div class="col-lg-8">
        <div class="d-flex justify-content-between align-items-center mb-1">
          <span class="cin-eyebrow text-dark">Ma trận độ phủ Lịch trình mẫu & Lượt sao chép theo 12 tháng</span>
          <span class="small font-monospace text-muted">Nhấp từng tháng để kiểm tra trang Khách</span>
        </div>
        <div class="editorial-matrix-grid">
          <?php for ($m = 1; $m <= 12; $m++): ?>
            <?php $mc = $monthCoverage[$m]; ?>
            <a href="templates.php?month=<?= $m ?>" class="matrix-month-cell" title="Tháng <?= $m ?>: <?= $mc['templates'] ?> mẫu · <?= $mc['copies'] ?> lượt dùng">
              <span class="mm-label">T<?= str_pad((string)$m, 2, '0', STR_PAD_LEFT) ?></span>
              <span class="mm-val"><?= $mc['templates'] ?> mẫu</span>
              <span class="mm-sub"><?= $mc['copies'] ?> dùng</span>
            </a>
          <?php endfor; ?>
        </div>
      </div>

      <!-- 3. SYSTEM INVENTORY LEDGER (TABULAR NUMBERS) -->
      <div class="col-lg-4 border-start-lg ps-lg-3">
        <span class="cin-eyebrow text-dark d-block mb-1">Chỉ số vận hành toàn hệ thống</span>
        <table class="app-table">
          <tbody>
            <tr>
              <td class="py-1 text-muted">Lịch trình mẫu xuất bản</td>
              <td class="py-1 num-col fw-bold text-success"><?= $stats['templates'] ?></td>
            </tr>
            <tr>
              <td class="py-1 text-muted">Tọa độ địa điểm hoạt động</td>
              <td class="py-1 num-col fw-bold text-primary"><?= $stats['locations'] ?></td>
            </tr>
            <tr>
              <td class="py-1 text-muted">Chuyến đi cá nhân đã tạo</td>
              <td class="py-1 num-col fw-bold"><?= $stats['trips'] ?></td>
            </tr>
            <tr>
              <td class="py-1 text-muted">Tài khoản thành viên & Admin</td>
              <td class="py-1 num-col fw-bold"><?= $stats['users'] ?></td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </section>

  <!-- THANH ĐIỀU HƯỚNG 6 PHÂN KHU QUẢN TRỊ (Phím 1-6) -->
  <nav class="app-view-tabs" role="tablist" aria-label="Các phân khu quản trị">
    <a class="app-view-tab <?= $tab === 'moderation' ? 'active' : '' ?>"
       href="admin.php?tab=moderation" data-key="1">
      <i class="bi bi-check2-circle"></i> 1. Kiểm duyệt Bài viết
      <?php if ($stats['pending_posts'] > 0): ?>
        <span class="status-mark status-warn py-0 px-1 ms-1"><?= $stats['pending_posts'] ?></span>
      <?php endif; ?>
    </a>
    <a class="app-view-tab <?= $tab === 'templates' ? 'active' : '' ?>"
       href="admin.php?tab=templates" data-key="2">
      <i class="bi bi-calendar2-range"></i> 2. Lịch trình mẫu & Insights (<?= count($allTemplates) ?>)
    </a>
    <a class="app-view-tab <?= $tab === 'locations' ? 'active' : '' ?>"
       href="admin.php?tab=locations" data-key="3">
      <i class="bi bi-geo-alt"></i> 3. Quản lý Địa điểm (<?= count($allLocations) ?>)
    </a>
    <a class="app-view-tab <?= $tab === 'users' ? 'active' : '' ?>"
       href="admin.php?tab=users" data-key="4">
      <i class="bi bi-people"></i> 4. Người dùng (<?= count($allUsers) ?>)
    </a>
    <a class="app-view-tab <?= $tab === 'template_days' ? 'active' : '' ?>"
       href="admin.php?tab=template_days" data-key="5">
      <i class="bi bi-calendar2-week"></i> 5. Ngày & HĐ Mẫu (UC10.7)
    </a>
    <a class="app-view-tab <?= $tab === 'catalogs' ? 'active' : '' ?>"
       href="admin.php?tab=catalogs" data-key="6">
      <i class="bi bi-tags"></i> 6. Tỉnh/Thành & Danh mục
    </a>
  </nav>

  <!-- ========================================================================
       TAB 1: KIỂM DUYỆT BÀI TRẢI NGHIỆM CỘNG ĐỒNG (M3 & M8)
       ======================================================================== -->
  <?php if ($tab === 'moderation'): ?>
    <div class="app-work-surface">
      <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
        <div>
          <span class="cin-eyebrow text-dark">Hàng đợi biên tập (UC10.9 -> UC10.12)</span>
          <h4 class="field-slip-title mb-0">Kiểm duyệt Bài trải nghiệm Cộng đồng</h4>
          <small class="text-muted">Duyệt công khai, Từ chối kèm lý do để tác giả chỉnh sửa gửi lại, hoặc Ẩn bài không phù hợp.</small>
        </div>
        <span class="font-monospace small text-muted"><?= count($allPosts) ?> BẢN GHI</span>
      </div>

      <div class="d-flex flex-column gap-3">
        <?php foreach ($allPosts as $p): ?>
          <?php
            $pMarkClass = $p['status'] === 'approved'
                ? 'status-ok'
                : ($p['status'] === 'pending' ? 'status-warn' : ($p['status'] === 'rejected' ? 'status-fail' : 'status-neutral'));
            $stMeta = post_status_meta($p['status']);
          ?>
          <div class="cin-activity-slip <?= $p['status'] === 'pending' ? 'border-warning' : '' ?>" style="<?= $p['status'] === 'pending' ? 'border-left: 4px solid var(--cin-amber); background: #fffdf7;' : '' ?>">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">
              <div>
                <span class="status-mark <?= $pMarkClass ?> me-1"><?= e($stMeta['label']) ?></span>
                <span class="slip-tag slip-tag-jade me-1">
                  <i class="bi bi-geo-alt-fill me-1"></i><?= e($p['location_name']) ?> (<?= e($p['province_name']) ?>)
                </span>
                <span class="font-monospace fw-bold text-warning-emphasis small">★ <?= (int)$p['rating'] ?>/5</span>
                <h5 class="fw-bold font-sans-title mt-2 mb-1 fs-6"><?= e($p['title']) ?></h5>
                <div class="small text-muted font-monospace" style="font-size:0.75rem;">
                  Tác giả: <strong><?= e($p['author_name']) ?></strong> (@<?= e($p['author_username']) ?>) ·
                  Đi T<?= (int)$p['visit_month'] ?>/<?= (int)$p['visit_year'] ?> ·
                  Chi phí: <strong><?= format_vnd($p['ref_cost'], true) ?></strong>
                </div>
              </div>
              <a href="experiences.php?post_id=<?= (int)$p['id'] ?>" target="_blank" class="btn-cin-outline py-1 px-2" style="font-size:0.76rem;">
                <i class="bi bi-box-arrow-up-right"></i> Xem bài
              </a>
            </div>

            <p class="small text-secondary mb-2"><?= nl2br(e($p['content'])) ?></p>

            <?php if (!empty($p['rejection_reason'])): ?>
              <div class="small text-danger mb-2 font-monospace">
                <strong>Lý do từ chối đã gửi:</strong> <?= e($p['rejection_reason']) ?>
              </div>
            <?php endif; ?>

            <form action="admin.php?tab=moderation" method="post" class="row g-2 align-items-center pt-2 border-top">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="moderate_post">
              <input type="hidden" name="post_id" value="<?= (int)$p['id'] ?>">

              <div class="col-md-6">
                <input type="text" name="rejection_reason" class="form-control form-control-sm"
                       value="<?= e($p['rejection_reason'] ?? '') ?>"
                       placeholder="Nhập lý do từ chối cụ thể (bắt buộc nếu chọn Từ chối)...">
              </div>
              <div class="col-md-6 d-flex flex-wrap justify-content-md-end gap-2">
                <button type="submit" name="new_status" value="approved" class="btn-cin-ink py-1 px-3" style="font-size:0.78rem;">
                  <i class="bi bi-check-circle"></i> Duyệt công khai
                </button>
                <button type="submit" name="new_status" value="rejected" class="btn btn-sm btn-danger rounded-1 px-3" style="font-size:0.78rem;">
                  <i class="bi bi-x-circle me-1"></i>Từ chối kèm lý do
                </button>
                <button type="submit" name="new_status" value="hidden" class="btn-cin-outline py-1 px-3" style="font-size:0.78rem;">
                  <i class="bi bi-eye-slash"></i> Ẩn bài
                </button>
              </div>
            </form>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>

  <!-- ========================================================================
       TAB 2: QUẢN LÝ LỊCH TRÌNH MẪU 12 THÁNG & PRODUCT INSIGHTS (M2 & M8)
       ======================================================================== -->
  <?php if ($tab === 'templates'): ?>
    <div class="app-work-surface mb-4">
      <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <div>
          <span class="cin-eyebrow text-dark">Hiệu suất chuyển đổi mẫu (UC10.5 -> UC10.8)</span>
          <h4 class="field-slip-title mb-0">
            Bảng Quản trị Lịch trình Mẫu & Phân tích Chuyển đổi
          </h4>
        </div>
        <button type="button" class="btn-cin-amber py-1 px-3" data-bs-toggle="modal" data-bs-target="#templateEditorModal" onclick="resetTemplateModal()">
          <i class="bi bi-plus-circle"></i> + Biên soạn lịch trình mẫu mới
        </button>
      </div>

      <div class="table-responsive">
        <table class="app-table">
          <thead>
            <tr>
              <th>Lịch trình mẫu & Điểm đến</th>
              <th>Tháng phù hợp</th>
              <th class="num-col">Dự toán / người</th>
              <th class="num-col">Lượt xem</th>
              <th class="num-col">Lượt sao chép</th>
              <th class="num-col">Tỷ lệ dùng mẫu</th>
              <th>Trạng thái</th>
              <th class="text-end">Thao tác</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($allTemplates as $tpl): ?>
              <?php
                $tMonths = $monthsByTemplate[(int)$tpl['id']] ?? [];
                $views = max(1, (int)$tpl['views_count']);
                $copies = (int)$tpl['copies_count'];
                $convRate = number_format(($copies / $views) * 100, 1);
                $tplMark = $tpl['status'] === 'published' ? 'status-ok' : ($tpl['status'] === 'draft' ? 'status-warn' : 'status-neutral');
                $tplLabel = $tpl['status'] === 'published' ? 'Đã xuất bản' : ($tpl['status'] === 'draft' ? 'Bản nháp' : 'Lưu trữ');
              ?>
              <tr class="<?= $tpl['status'] === 'draft' ? 'row-needs-action' : '' ?>">
                <td>
                  <a href="template_detail.php?id=<?= (int)$tpl['id'] ?>" class="fw-bold text-dark d-block"><?= e($tpl['title']) ?></a>
                  <span class="text-muted" style="font-size:0.75rem;"><?= e($tpl['main_destination']) ?> · <?= (int)$tpl['duration_days'] ?>N<?= (int)$tpl['duration_nights'] ?>Đ · Cập nhật: <?= format_date_vn($tpl['updated_date']) ?></span>
                </td>
                <td>
                  <div class="d-flex flex-wrap gap-1" style="max-width: 190px;">
                    <?php foreach ($tMonths as $mNum): ?>
                      <span class="slip-tag py-0 px-1" style="font-size:0.65rem;">T<?= $mNum ?></span>
                    <?php endforeach; ?>
                  </div>
                </td>
                <td class="num-col fw-bold text-success"><?= format_vnd($tpl['ref_budget_per_person']) ?></td>
                <td class="num-col"><?= (int)$tpl['views_count'] ?></td>
                <td class="num-col fw-bold text-primary"><?= $copies ?></td>
                <td class="num-col fw-semibold"><?= $convRate ?>%</td>
                <td>
                  <span class="status-mark <?= $tplMark ?>"><?= $tplLabel ?></span>
                </td>
                <td class="text-end text-nowrap">
                  <a href="admin.php?tab=template_days&template_id=<?= (int)$tpl['id'] ?>" class="btn-cin-outline py-0 px-2" style="font-size:0.75rem;" title="Quản lý ngày và hoạt động trong lịch trình mẫu (UC10.7)">
                    <i class="bi bi-calendar2-week"></i> Ngày & HĐ
                  </a>
                  <button type="button" class="btn-cin-outline py-0 px-2" style="font-size:0.75rem;"
                          onclick='editTemplateModal(<?= json_encode($tpl, JSON_UNESCAPED_UNICODE) ?>, <?= json_encode($tMonths) ?>)'>
                    <i class="bi bi-pencil"></i> Sửa
                  </button>
                  <form action="admin.php?tab=templates" method="post" class="d-inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="toggle_template_status">
                    <input type="hidden" name="template_id" value="<?= (int)$tpl['id'] ?>">
                    <input type="hidden" name="new_status" value="<?= $tpl['status'] === 'published' ? 'archived' : 'published' ?>">
                    <button type="submit" class="btn-cin-outline py-0 px-2" style="font-size:0.75rem;">
                      <?= $tpl['status'] === 'published' ? 'Ẩn' : 'Xuất bản' ?>
                    </button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endif; ?>

  <!-- ========================================================================
       TAB 3: QUẢN LÝ ĐỊA ĐIỂM 3 NHÓM (M5 & M8)
       ======================================================================== -->
  <?php if ($tab === 'locations'): ?>
    <div class="app-work-surface">
      <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <div>
          <span class="cin-eyebrow text-dark">Lưu trữ tọa độ 3 nhóm (UC10.4)</span>
          <h4 class="field-slip-title mb-0">Quản lý Địa điểm Tham quan, Ăn uống & Lưu trú</h4>
          <small class="text-muted">Ưu tiên chuyển trạng thái Lưu trữ thay vì xóa cứng để bảo toàn lịch sử các chuyến đi.</small>
        </div>
        <button type="button" class="btn-cin-amber py-1 px-3" data-bs-toggle="modal" data-bs-target="#locationEditorModal" onclick="resetLocationModal()">
          <i class="bi bi-plus-circle"></i> + Thêm địa điểm mới
        </button>
      </div>

      <div class="table-responsive">
        <table class="app-table">
          <thead>
            <tr>
              <th>Tên địa điểm & Địa chỉ</th>
              <th>Tỉnh / Thành</th>
              <th>Nhóm & Danh mục</th>
              <th class="num-col">Giá tham khảo</th>
              <th class="num-col">Ngày cập nhật</th>
              <th>Trạng thái</th>
              <th class="text-end">Thao tác</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($allLocations as $loc): ?>
              <?php $tMeta = location_type_meta($loc['location_type']); ?>
              <tr>
                <td>
                  <a href="location_detail.php?id=<?= (int)$loc['id'] ?>" class="fw-bold text-dark d-block"><?= e($loc['name']) ?></a>
                  <span class="text-muted" style="font-size:0.75rem;"><?= e($loc['address']) ?></span>
                </td>
                <td><?= e($loc['province_name']) ?></td>
                <td>
                  <span class="badge <?= e($tMeta['badge']) ?> rounded-1 font-monospace"><?= e($tMeta['short']) ?></span>
                  <div class="text-muted" style="font-size:0.72rem;"><?= e($loc['category_name']) ?></div>
                </td>
                <td class="num-col fw-bold"><?= format_vnd($loc['ref_price'], true) ?><?= (float)$loc['ref_price'] > 0 ? '/' . e($loc['price_unit']) : '' ?></td>
                <td class="num-col text-muted"><?= format_date_vn($loc['price_updated_at']) ?></td>
                <td>
                  <span class="status-mark <?= $loc['status'] === 'active' ? 'status-ok' : 'status-neutral' ?>">
                    <?= $loc['status'] === 'active' ? 'Đang hiển thị' : 'Đã lưu trữ' ?>
                  </span>
                </td>
                <td class="text-end text-nowrap">
                  <button type="button" class="btn-cin-outline py-0 px-2" style="font-size:0.75rem;"
                          onclick='editLocationModal(<?= json_encode($loc, JSON_UNESCAPED_UNICODE) ?>)'>
                    <i class="bi bi-pencil"></i> Sửa
                  </button>
                  <form action="admin.php?tab=locations" method="post" class="d-inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="toggle_location_status">
                    <input type="hidden" name="location_id" value="<?= (int)$loc['id'] ?>">
                    <input type="hidden" name="new_status" value="<?= $loc['status'] === 'active' ? 'archived' : 'active' ?>">
                    <button type="submit" class="btn-cin-outline py-0 px-2" style="font-size:0.75rem;">
                      <?= $loc['status'] === 'active' ? 'Lưu trữ' : 'Kích hoạt' ?>
                    </button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endif; ?>

  <!-- ========================================================================
       TAB 4: QUẢN LÝ NGƯỜI DÙNG & PHÂN QUYỀN (M1 & M8)
       ======================================================================== -->
  <?php if ($tab === 'users'): ?>
    <div class="app-work-surface">
      <div class="mb-3">
        <span class="cin-eyebrow text-dark">Phân quyền & Trạng thái (UC10.1)</span>
        <h4 class="field-slip-title mb-0">Quản lý Tài khoản Người dùng & Phân quyền</h4>
      </div>
      <div class="table-responsive">
        <table class="app-table">
          <thead>
            <tr>
              <th class="num-col">ID</th>
              <th>Họ và tên / Username</th>
              <th>Email & Điện thoại</th>
              <th>Vai trò</th>
              <th class="num-col">Chuyến đi</th>
              <th class="num-col">Bài viết</th>
              <th>Trạng thái</th>
              <th class="text-end">Thao tác quản trị</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($allUsers as $u): ?>
              <tr class="<?= $u['status'] !== 'active' ? 'row-needs-action' : '' ?>">
                <td class="num-col">#<?= (int)$u['id'] ?></td>
                <td>
                  <div class="fw-bold text-dark"><?= e($u['full_name']) ?></div>
                  <div class="text-muted font-monospace" style="font-size:0.75rem;">@<?= e($u['username']) ?> · <?= e($u['hometown']) ?></div>
                </td>
                <td>
                  <div><?= e($u['email']) ?></div>
                  <div class="text-muted font-monospace" style="font-size:0.75rem;"><?= e($u['phone'] ?: '—') ?></div>
                </td>
                <td>
                  <span class="slip-tag <?= $u['role'] === 'admin' ? 'bg-warning text-dark border-warning' : 'slip-tag-jade' ?>">
                    <?= strtoupper($u['role']) ?>
                  </span>
                </td>
                <td class="num-col fw-semibold"><?= (int)$u['trips_count'] ?></td>
                <td class="num-col fw-semibold"><?= (int)$u['posts_count'] ?></td>
                <td>
                  <span class="status-mark <?= $u['status'] === 'active' ? 'status-ok' : 'status-fail' ?>">
                    <?= $u['status'] === 'active' ? 'Hoạt động' : 'Đang khóa' ?>
                  </span>
                </td>
                <td class="text-end text-nowrap">
                  <?php if ((int)$u['id'] !== (int)$admin['id']): ?>
                    <form action="admin.php?tab=users" method="post" class="d-inline">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="toggle_user_status">
                      <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                      <input type="hidden" name="new_status" value="<?= $u['status'] === 'active' ? 'locked' : 'active' ?>">
                      <button type="submit" class="btn-cin-outline py-0 px-2" style="font-size:0.75rem;">
                        <?= $u['status'] === 'active' ? 'Khóa TK' : 'Mở khóa' ?>
                      </button>
                    </form>
                    <form action="admin.php?tab=users" method="post" class="d-inline">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="change_user_role">
                      <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                      <input type="hidden" name="new_role" value="<?= $u['role'] === 'admin' ? 'user' : 'admin' ?>">
                      <button type="submit" class="btn-cin-outline py-0 px-2" style="font-size:0.75rem;">
                        Đổi sang <?= $u['role'] === 'admin' ? 'User' : 'Admin' ?>
                      </button>
                    </form>
                  <?php else: ?>
                    <span class="text-muted fst-italic small">Tài khoản ca trực</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endif; ?>

  <!-- ========================================================================
       TAB 5: QUẢN LÝ NGÀY VÀ HOẠT ĐỘNG TRONG LỊCH TRÌNH MẪU (UC10.7)
       ======================================================================== -->
  <?php if ($tab === 'template_days'): ?>
    <?php
      $selectedTplId = max(1, (int)($_GET['template_id'] ?? ($allTemplates[0]['id'] ?? 1)));
      $stmtSelTpl = $pdo->prepare('SELECT * FROM itinerary_templates WHERE id = ?');
      $stmtSelTpl->execute([$selectedTplId]);
      $selectedTpl = $stmtSelTpl->fetch() ?: ($allTemplates[0] ?? null);
      if ($selectedTpl) {
          $selectedTplId = (int)$selectedTpl['id'];
      }
      $stmtTDays = $pdo->prepare('SELECT * FROM template_days WHERE template_id = ? ORDER BY day_number ASC');
      $stmtTDays->execute([$selectedTplId]);
      $tDaysList = $stmtTDays->fetchAll();

      $stmtTActs = $pdo->prepare('
          SELECT ta.*, td.day_number, l.name AS loc_name, l.location_type
          FROM template_activities ta
          JOIN template_days td ON td.id = ta.template_day_id
          LEFT JOIN locations l ON l.id = ta.location_id
          WHERE td.template_id = ?
          ORDER BY td.day_number ASC, ta.sort_order ASC, ta.start_time ASC
      ');
      $stmtTActs->execute([$selectedTplId]);
      $tActsByDayId = [];
      foreach ($stmtTActs->fetchAll() as $taRow) {
          $tActsByDayId[(int)$taRow['template_day_id']][] = $taRow;
      }
    ?>
    <section class="cin-day-dossier mb-4">
      <div class="cin-day-header">
        <div>
          <span class="cin-day-badge me-2">UC10.7 · KỊCH BẢN MẪU</span>
          <span class="fw-bold" style="font-family: var(--font-display); font-size: 1.2rem;">Quản lý Ngày &amp; Hoạt động trong Lịch trình mẫu</span>
        </div>
        <form action="admin.php" method="get" class="d-flex align-items-center gap-2 m-0">
          <input type="hidden" name="tab" value="template_days">
          <label class="small fw-bold text-nowrap font-monospace">CHỌN MẪU:</label>
          <select name="template_id" class="form-select form-select-sm" onchange="this.form.submit()">
            <?php foreach ($allTemplates as $tplOpt): ?>
              <option value="<?= (int)$tplOpt['id'] ?>" <?= (int)$tplOpt['id'] === $selectedTplId ? 'selected' : '' ?>>
                #<?= (int)$tplOpt['id'] ?> — <?= e($tplOpt['title']) ?> (<?= (int)$tplOpt['duration_days'] ?>N<?= (int)$tplOpt['duration_nights'] ?>Đ)
              </option>
            <?php endforeach; ?>
          </select>
        </form>
      </div>

      <div class="cin-day-body p-4">
        <!-- Form thêm / cập nhật Ngày trong lịch trình mẫu -->
        <div class="field-spec-box mt-0 mb-4 p-3">
          <h6 class="fw-bold mb-2"><i class="bi bi-plus-circle text-success me-1"></i>Thêm hoặc cập nhật Ngày trong lịch trình mẫu</h6>
          <form action="admin.php?tab=template_days&template_id=<?= $selectedTplId ?>" method="post" class="row g-2 align-items-end">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save_template_day">
            <input type="hidden" name="template_id" value="<?= $selectedTplId ?>">
            <input type="hidden" name="template_day_id" id="tday_id_input" value="0">
            <div class="col-md-2">
              <label class="form-label small fw-bold mb-1">Ngày thứ *</label>
              <input type="number" name="day_number" id="tday_num_input" class="form-control form-control-sm font-monospace" min="1" max="15" value="<?= count($tDaysList) + 1 ?>" required>
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-bold mb-1">Tiêu đề ngày *</label>
              <input type="text" name="day_title" id="tday_title_input" class="form-control form-control-sm" placeholder="VD: Ngày 1: Hà Nội – Đèo Đá Trắng – Bản Lác" required>
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-bold mb-1">Tóm tắt lịch trình trong ngày</label>
              <input type="text" name="summary" id="tday_sum_input" class="form-control form-control-sm" placeholder="Mô tả ngắn trọng tâm trong ngày...">
            </div>
            <div class="col-md-2">
              <button type="submit" class="btn-cin-ink w-100 justify-content-center py-1"><i class="bi bi-save"></i>Lưu Ngày</button>
            </div>
          </form>
        </div>

        <!-- Form thêm / sửa Hoạt động trong Ngày mẫu -->
        <div class="field-spec-box mt-0 mb-4 p-3" id="tplActivityFormBox">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <h6 class="fw-bold mb-0" id="tactFormTitle"><i class="bi bi-clock-history text-warning me-1"></i>Thêm Hoạt động mới vào Ngày mẫu</h6>
            <button type="button" class="btn-cin-outline py-0 px-2" onclick="resetTplActForm()">Làm mới form</button>
          </div>
          <form action="admin.php?tab=template_days&template_id=<?= $selectedTplId ?>" method="post" class="row g-2">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save_template_activity">
            <input type="hidden" name="template_id" value="<?= $selectedTplId ?>">
            <input type="hidden" name="template_activity_id" id="tact_id_input" value="0">

            <div class="col-md-3">
              <label class="form-label small fw-bold mb-1">Thuộc Ngày *</label>
              <select name="template_day_id" id="tact_day_input" class="form-select form-select-sm" required>
                <?php foreach ($tDaysList as $td): ?>
                  <option value="<?= (int)$td['id'] ?>">Ngày <?= (int)$td['day_number'] ?>: <?= e($td['day_title']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-bold mb-1">Tên hoạt động *</label>
              <input type="text" name="title" id="tact_title_input" class="form-control form-control-sm" placeholder="VD: Săn mây đỉnh Tà Xùa" required>
            </div>
            <div class="col-md-3">
              <label class="form-label small fw-bold mb-1">Liên kết Địa điểm</label>
              <select name="location_id" id="tact_loc_input" class="form-select form-select-sm">
                <option value="0">-- Tự nhập / Không gắn --</option>
                <?php foreach ($allLocations as $locOpt): ?>
                  <option value="<?= (int)$locOpt['id'] ?>">[<?= e($locOpt['province_name']) ?>] <?= e($locOpt['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-2">
              <label class="form-label small fw-bold mb-1">Loại HĐ</label>
              <select name="activity_type" id="tact_type_input" class="form-select form-select-sm">
                <option value="sightseeing">Tham quan</option>
                <option value="food">Ăn uống</option>
                <option value="stay">Lưu trú</option>
                <option value="transport">Di chuyển</option>
                <option value="other">Khác</option>
              </select>
            </div>
            <div class="col-md-2">
              <label class="form-label small fw-bold mb-1">Bắt đầu</label>
              <input type="time" name="start_time" id="tact_start_input" class="form-control form-control-sm font-monospace" value="08:00">
            </div>
            <div class="col-md-2">
              <label class="form-label small fw-bold mb-1">Kết thúc</label>
              <input type="time" name="end_time" id="tact_end_input" class="form-control form-control-sm font-monospace" value="10:00">
            </div>
            <div class="col-md-2">
              <label class="form-label small fw-bold mb-1">Chi phí/người (đ)</label>
              <input type="number" name="estimated_cost_per_person" id="tact_cost_input" class="form-control form-control-sm font-monospace" step="10000" value="0">
            </div>
            <div class="col-md-2">
              <label class="form-label small fw-bold mb-1">Thứ tự sắp xếp</label>
              <input type="number" name="sort_order" id="tact_sort_input" class="form-control form-control-sm font-monospace" value="1">
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-bold mb-1">Ghi chú di chuyển</label>
              <input type="text" name="transport_note" id="tact_trans_input" class="form-control form-control-sm" placeholder="VD: Đi xe máy 15km đường đèo">
            </div>
            <div class="col-md-5">
              <label class="form-label small fw-bold mb-1">Mô tả chi tiết</label>
              <input type="text" name="description" id="tact_desc_input" class="form-control form-control-sm" placeholder="Gợi ý trải nghiệm...">
            </div>
            <div class="col-md-5">
              <label class="form-label small fw-bold mb-1">Lưu ý quan trọng</label>
              <input type="text" name="important_note" id="tact_note_input" class="form-control form-control-sm" placeholder="VD: Mang áo ấm, đặt bàn trước">
            </div>
            <div class="col-md-2 d-flex align-items-end">
              <button type="submit" class="btn-cin-amber w-100 justify-content-center py-1"><i class="bi bi-check2-circle"></i>Lưu HĐ</button>
            </div>
          </form>
        </div>

        <!-- Danh sách các Ngày & Hoạt động hiện có -->
        <?php if (empty($tDaysList)): ?>
          <div class="cin-empty-state py-4 text-muted">Lịch trình mẫu này chưa có ngày nào. Hãy thêm Ngày ở biểu mẫu phía trên.</div>
        <?php else: ?>
          <div class="d-flex flex-column gap-3">
            <?php foreach ($tDaysList as $td): ?>
              <?php $dayActs = $tActsByDayId[(int)$td['id']] ?? []; ?>
              <article class="cin-activity-slip p-3">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2 pb-2 border-bottom">
                  <div>
                    <span class="cin-day-badge me-2">NGÀY 0<?= (int)$td['day_number'] ?></span>
                    <strong style="font-family: var(--font-display); font-size: 1.15rem;"><?= e($td['day_title']) ?></strong>
                    <?php if (!empty($td['summary'])): ?>
                      <div class="small text-muted mt-1"><?= e($td['summary']) ?></div>
                    <?php endif; ?>
                  </div>
                  <div class="d-flex gap-1">
                    <button type="button" class="btn-cin-outline py-1 px-2"
                            onclick='editTplDay(<?= json_encode($td, JSON_UNESCAPED_UNICODE) ?>)'>
                      <i class="bi bi-pencil"></i> Sửa ngày
                    </button>
                    <form action="admin.php?tab=template_days&template_id=<?= $selectedTplId ?>" method="post" class="d-inline m-0" onsubmit="return confirm('Xóa ngày này và các hoạt động bên trong?');">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="delete_template_day">
                      <input type="hidden" name="template_id" value="<?= $selectedTplId ?>">
                      <input type="hidden" name="template_day_id" value="<?= (int)$td['id'] ?>">
                      <button type="submit" class="btn-cin-outline py-1 px-2 text-danger"><i class="bi bi-trash"></i></button>
                    </form>
                  </div>
                </div>

                <?php if (empty($dayActs)): ?>
                  <div class="small text-muted fst-italic">Chưa có hoạt động trong ngày này.</div>
                <?php else: ?>
                  <div class="table-responsive">
                    <table class="app-table">
                      <thead>
                        <tr>
                          <th>Thứ tự / Giờ</th>
                          <th>Hoạt động &amp; Địa điểm</th>
                          <th>Loại</th>
                          <th class="num-col">Chi phí/người</th>
                          <th class="text-end">Thao tác</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php foreach ($dayActs as $ta): ?>
                          <tr data-kb-row="1">
                            <td class="text-nowrap font-monospace">
                              <span class="slip-tag py-0 px-1">#<?= (int)$ta['sort_order'] ?></span>
                              <?= e($ta['start_time']) ?> - <?= e($ta['end_time']) ?>
                            </td>
                            <td>
                              <div class="fw-bold"><?= e($ta['title']) ?></div>
                              <?php if (!empty($ta['loc_name'])): ?>
                                <span class="slip-tag py-0 px-2" style="font-size: 0.65rem;"><i class="bi bi-geo-alt-fill me-1"></i><?= e($ta['loc_name']) ?></span>
                              <?php endif; ?>
                              <?php if (!empty($ta['description'])): ?>
                                <span class="text-muted ms-1 small"><?= e($ta['description']) ?></span>
                              <?php endif; ?>
                            </td>
                            <td><span class="status-mark status-info"><?= e($ta['activity_type']) ?></span></td>
                            <td class="num-col fw-bold" style="color: var(--cin-jade);"><?= format_vnd($ta['estimated_cost_per_person'], true) ?></td>
                            <td class="text-end text-nowrap">
                              <button type="button" class="btn-cin-outline py-0 px-2"
                                      onclick='editTplAct(<?= json_encode($ta, JSON_UNESCAPED_UNICODE) ?>)'>
                                <i class="bi bi-pencil"></i> Sửa
                              </button>
                              <form action="admin.php?tab=template_days&template_id=<?= $selectedTplId ?>" method="post" class="d-inline m-0" onsubmit="return confirm('Xóa hoạt động mẫu này?');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete_template_activity">
                                <input type="hidden" name="template_id" value="<?= $selectedTplId ?>">
                                <input type="hidden" name="template_activity_id" value="<?= (int)$ta['id'] ?>">
                                <button type="submit" class="btn-cin-outline py-0 px-2 text-danger"><i class="bi bi-trash"></i></button>
                              </form>
                            </td>
                          </tr>
                        <?php endforeach; ?>
                      </tbody>
                    </table>
                  </div>
                <?php endif; ?>
              </article>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </section>
  <?php endif; ?>

  <!-- ========================================================================
       TAB 6: QUẢN LÝ TỈNH/THÀNH (UC10.2) & DANH MỤC (UC10.3)
       ======================================================================== -->
  <?php if ($tab === 'catalogs'): ?>
    <div class="row g-4">
      <!-- CỘT TRÁI: QUẢN LÝ TỈNH/THÀNH (UC10.2) -->
      <div class="col-lg-6">
        <section class="cin-day-dossier h-100 mb-0">
          <div class="cin-day-header">
            <span class="fw-bold"><i class="bi bi-map text-success me-1"></i>Quản lý Tỉnh / Thành miền Bắc (UC10.2)</span>
            <span class="font-monospace small text-muted"><?= count($provinces) ?> TỈNH</span>
          </div>
          <div class="cin-day-body p-4">
            <form action="admin.php?tab=catalogs" method="post" class="row g-2 field-spec-box mt-0 mb-3 p-3">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="save_province">
              <input type="hidden" name="province_id" id="prov_id_input" value="0">
              <div class="col-md-5">
                <label class="form-label small fw-bold mb-1">Tên tỉnh/thành *</label>
                <input type="text" name="name" id="prov_name_input" class="form-control form-control-sm" placeholder="VD: Bắc Kạn" required>
              </div>
              <div class="col-md-4">
                <label class="form-label small fw-bold mb-1">Tiểu vùng</label>
                <input type="text" name="region_sub" id="prov_reg_input" class="form-control form-control-sm" value="Đông Bắc">
              </div>
              <div class="col-md-3">
                <label class="form-label small fw-bold mb-1">Trạng thái</label>
                <select name="status" id="prov_status_input" class="form-select form-select-sm">
                  <option value="active">Hoạt động</option>
                  <option value="archived">Ẩn</option>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label small fw-bold mb-1">Vĩ độ (Lat)</label>
                <input type="number" step="0.0001" name="center_lat" id="prov_lat_input" class="form-control form-control-sm font-monospace" value="22.1470">
              </div>
              <div class="col-md-6">
                <label class="form-label small fw-bold mb-1">Kinh độ (Lng)</label>
                <input type="number" step="0.0001" name="center_lng" id="prov_lng_input" class="form-control form-control-sm font-monospace" value="105.8348">
              </div>
              <div class="col-md-9">
                <input type="text" name="featured_summary" id="prov_sum_input" class="form-control form-control-sm" placeholder="Đặc trưng nổi bật (VD: Hồ Ba Bể, rừng nguyên sinh)...">
              </div>
              <div class="col-md-3">
                <button type="submit" class="btn-cin-amber w-100 justify-content-center py-1">Lưu Tỉnh</button>
              </div>
            </form>

            <div class="table-responsive">
              <table class="app-table">
                <thead>
                  <tr>
                    <th>Tỉnh / Thành</th>
                    <th>Tiểu vùng</th>
                    <th>Trạng thái</th>
                    <th class="text-end">Thao tác</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($provinces as $pr): ?>
                    <tr data-kb-row="1">
                      <td class="fw-bold"><?= e($pr['name']) ?></td>
                      <td><?= e($pr['region_sub']) ?></td>
                      <td>
                        <span class="status-mark <?= ($pr['status'] ?? 'active') === 'active' ? 'status-done' : 'status-muted' ?>">
                          <span class="status-shape <?= ($pr['status'] ?? 'active') === 'active' ? 'shape-circle' : 'shape-square' ?>"></span>
                          <?= ($pr['status'] ?? 'active') === 'active' ? 'Hoạt động' : 'Đã ẩn' ?>
                        </span>
                      </td>
                      <td class="text-end">
                        <button type="button" class="btn-cin-outline py-0 px-2"
                                onclick='editProvince(<?= json_encode($pr, JSON_UNESCAPED_UNICODE) ?>)'>Sửa</button>
                        <form action="admin.php?tab=catalogs" method="post" class="d-inline m-0">
                          <?= csrf_field() ?>
                          <input type="hidden" name="action" value="toggle_province">
                          <input type="hidden" name="province_id" value="<?= (int)$pr['id'] ?>">
                          <input type="hidden" name="new_status" value="<?= ($pr['status'] ?? 'active') === 'active' ? 'archived' : 'active' ?>">
                          <button type="submit" class="btn-cin-outline py-0 px-2">
                            <?= ($pr['status'] ?? 'active') === 'active' ? 'Ẩn' : 'Bật' ?>
                          </button>
                        </form>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </section>
      </div>

      <!-- CỘT PHẢI: QUẢN LÝ DANH MỤC (UC10.3) -->
      <div class="col-lg-6">
        <section class="cin-day-dossier h-100 mb-0">
          <div class="cin-day-header">
            <span class="fw-bold"><i class="bi bi-tags text-primary me-1"></i>Quản lý Danh mục Địa điểm (UC10.3)</span>
            <span class="font-monospace small text-muted"><?= count($categories) ?> DANH MỤC</span>
          </div>
          <div class="cin-day-body p-4">
            <form action="admin.php?tab=catalogs" method="post" class="row g-2 field-spec-box mt-0 mb-3 p-3">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="save_category">
              <input type="hidden" name="category_id" id="cat_id_input" value="0">
              <div class="col-md-5">
                <label class="form-label small fw-bold mb-1">Tên danh mục *</label>
                <input type="text" name="name" id="cat_name_input" class="form-control form-control-sm" placeholder="VD: Thác nước & Suối" required>
              </div>
              <div class="col-md-4">
                <label class="form-label small fw-bold mb-1">Nhóm chính</label>
                <select name="location_type" id="cat_type_input" class="form-select form-select-sm">
                  <option value="sightseeing">Điểm tham quan</option>
                  <option value="food">Ăn uống</option>
                  <option value="stay">Lưu trú</option>
                </select>
              </div>
              <div class="col-md-3">
                <label class="form-label small fw-bold mb-1">Icon Bootstrap</label>
                <input type="text" name="icon_class" id="cat_icon_input" class="form-control form-control-sm font-monospace" value="bi-geo-alt">
              </div>
              <div class="col-md-9">
                <select name="status" id="cat_status_input" class="form-select form-select-sm">
                  <option value="active">Trạng thái: Đang hoạt động</option>
                  <option value="archived">Trạng thái: Ngừng sử dụng</option>
                </select>
              </div>
              <div class="col-md-3">
                <button type="submit" class="btn-cin-amber w-100 justify-content-center py-1">Lưu Danh mục</button>
              </div>
            </form>

            <div class="table-responsive">
              <table class="app-table">
                <thead>
                  <tr>
                    <th>Danh mục</th>
                    <th>Nhóm địa điểm</th>
                    <th>Trạng thái</th>
                    <th class="text-end">Thao tác</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($categories as $cat): ?>
                    <?php $cMeta = location_type_meta($cat['location_type']); ?>
                    <tr data-kb-row="1">
                      <td class="fw-bold"><i class="bi <?= e($cat['icon_class']) ?> me-1"></i><?= e($cat['name']) ?></td>
                      <td><span class="slip-tag py-0 px-2" style="font-size: 0.65rem;"><?= e($cMeta['short']) ?></span></td>
                      <td>
                        <span class="status-mark <?= ($cat['status'] ?? 'active') === 'active' ? 'status-done' : 'status-muted' ?>">
                          <span class="status-shape <?= ($cat['status'] ?? 'active') === 'active' ? 'shape-circle' : 'shape-square' ?>"></span>
                          <?= ($cat['status'] ?? 'active') === 'active' ? 'Hoạt động' : 'Đã ẩn' ?>
                        </span>
                      </td>
                      <td class="text-end">
                        <button type="button" class="btn-cin-outline py-0 px-2"
                                onclick='editCategory(<?= json_encode($cat, JSON_UNESCAPED_UNICODE) ?>)'>Sửa</button>
                        <form action="admin.php?tab=catalogs" method="post" class="d-inline m-0">
                          <?= csrf_field() ?>
                          <input type="hidden" name="action" value="toggle_category">
                          <input type="hidden" name="category_id" value="<?= (int)$cat['id'] ?>">
                          <input type="hidden" name="new_status" value="<?= ($cat['status'] ?? 'active') === 'active' ? 'archived' : 'active' ?>">
                          <button type="submit" class="btn-cin-outline py-0 px-2">
                            <?= ($cat['status'] ?? 'active') === 'active' ? 'Ẩn' : 'Bật' ?>
                          </button>
                        </form>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </section>
      </div>
    </div>
  <?php endif; ?>
</main>

<!-- MODAL BIÊN SOẠN LỊCH TRÌNH MẪU -->
<div class="modal fade" id="templateEditorModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <span class="cin-eyebrow" style="color: var(--cin-amber-bright); font-size: 0.64rem;">BAN BIÊN TẬP · UC10.6</span>
          <h5 class="modal-title mb-0" id="tplModalTitle">Biên soạn Lịch trình mẫu theo tháng</h5>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form action="admin.php?tab=templates" method="post" class="row g-3">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="save_template">
          <input type="hidden" name="template_id" id="tpl_id_input" value="0">

          <div class="col-md-8">
            <label class="form-label">Tên lịch trình mẫu *</label>
            <input type="text" name="title" id="tpl_title_input" class="form-control" required>
          </div>
          <div class="col-md-4">
            <label class="form-label">Trạng thái</label>
            <select name="status" id="tpl_status_input" class="form-select">
              <option value="published">Xuất bản công khai</option>
              <option value="draft">Lưu bản nháp</option>
              <option value="archived">Ngừng xuất bản (Lưu trữ)</option>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Tỉnh / Thành</label>
            <select name="province_id" id="tpl_prov_input" class="form-select">
              <?php foreach ($provinces as $prov): ?>
                <option value="<?= (int)$prov['id'] ?>"><?= e($prov['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Điểm đến chính *</label>
            <input type="text" name="main_destination" id="tpl_dest_input" class="form-control" required>
          </div>
          <div class="col-md-3">
            <label class="form-label">Số ngày</label>
            <input type="number" name="duration_days" id="tpl_days_input" class="form-control font-monospace" min="1" value="3">
          </div>
          <div class="col-md-3">
            <label class="form-label">Số đêm</label>
            <input type="number" name="duration_nights" id="tpl_nights_input" class="form-control font-monospace" min="0" value="2">
          </div>
          <div class="col-md-6">
            <label class="form-label">Ngân sách tham khảo / 1 người (VNĐ)</label>
            <input type="number" name="ref_budget_per_person" id="tpl_budget_input" class="form-control font-monospace" step="50000" value="2450000">
          </div>
          <div class="col-md-6">
            <label class="form-label">Phương tiện gợi ý</label>
            <input type="text" name="suggested_transport" id="tpl_trans_input" class="form-control" value="Xe limousine + Thuê xe máy">
          </div>
          <div class="col-md-6">
            <label class="form-label">Ảnh đại diện (URL)</label>
            <input type="url" name="thumbnail_url" id="tpl_thumb_input" class="form-control" value="https://images.unsplash.com/photo-1490750967868-88aa4486c946?w=900">
          </div>

          <div class="col-12">
            <label class="form-label">Gán lịch trình mẫu cho các tháng phù hợp (Chọn 1 hoặc nhiều tháng):</label>
            <div class="d-flex flex-wrap gap-2 field-spec-box mt-0">
              <?php for ($m = 1; $m <= 12; $m++): ?>
                <div class="form-check form-check-inline m-0">
                  <input class="form-check-input tpl-month-chk" type="checkbox" name="months[]" value="<?= $m ?>" id="tpl_m_<?= $m ?>">
                  <label class="form-check-label small" for="tpl_m_<?= $m ?>">Tháng <?= $m ?></label>
                </div>
              <?php endfor; ?>
            </div>
          </div>

          <div class="col-12">
            <label class="form-label">Lý do phù hợp theo mùa *</label>
            <textarea name="seasonal_reason" id="tpl_reason_input" rows="2" class="form-control" required></textarea>
          </div>
          <div class="col-12">
            <label class="form-label">Tóm tắt lịch trình</label>
            <textarea name="summary" id="tpl_summary_input" rows="2" class="form-control"></textarea>
          </div>
          <div class="col-12">
            <label class="form-label">Lưu ý cần thiết</label>
            <textarea name="essential_notes" id="tpl_notes_input" rows="2" class="form-control"></textarea>
          </div>
          <div class="col-12">
            <button type="submit" class="btn-cin-amber w-100 justify-content-center py-2">
              <i class="bi bi-check2-circle"></i>Lưu lịch trình mẫu
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- MODAL THÊM / SỬA ĐỊA ĐIỂM -->
<div class="modal fade" id="locationEditorModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <span class="cin-eyebrow" style="color: var(--cin-amber-bright); font-size: 0.64rem;">HỒ SƠ TỌA ĐỘ · UC10.4</span>
          <h5 class="modal-title mb-0" id="locModalTitle">Thêm / Cập nhật Địa điểm</h5>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form action="admin.php?tab=locations" method="post" class="row g-3">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="save_location">
          <input type="hidden" name="location_id" id="loc_id_input" value="0">

          <div class="col-md-6">
            <label class="form-label">Tên địa điểm *</label>
            <input type="text" name="name" id="loc_name_input" class="form-control" required>
          </div>
          <div class="col-md-3">
            <label class="form-label">Nhóm địa điểm</label>
            <select name="location_type" id="loc_type_input" class="form-select">
              <option value="attraction">Tham quan</option>
              <option value="food">Ăn uống</option>
              <option value="stay">Lưu trú</option>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label">Danh mục</label>
            <select name="category_id" id="loc_cat_input" class="form-select">
              <?php foreach ($categories as $cat): ?>
                <option value="<?= (int)$cat['id'] ?>"><?= e($cat['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="col-md-5">
            <label class="form-label">Tỉnh / Thành</label>
            <select name="province_id" id="loc_prov_input" class="form-select">
              <?php foreach ($provinces as $prov): ?>
                <option value="<?= (int)$prov['id'] ?>"><?= e($prov['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-7">
            <label class="form-label">Địa chỉ</label>
            <input type="text" name="address" id="loc_addr_input" class="form-control" required>
          </div>

          <div class="col-md-3">
            <label class="form-label">Vĩ độ (Latitude)</label>
            <input type="text" name="latitude" id="loc_lat_input" class="form-control font-monospace" value="20.8448">
          </div>
          <div class="col-md-3">
            <label class="form-label">Kinh độ (Longitude)</label>
            <input type="text" name="longitude" id="loc_lng_input" class="form-control font-monospace" value="104.6391">
          </div>
          <div class="col-md-3">
            <label class="form-label">Giá tham khảo (VNĐ)</label>
            <input type="number" name="ref_price" id="loc_price_input" class="form-control font-monospace" value="50000">
          </div>
          <div class="col-md-3">
            <label class="form-label">Đơn vị tính</label>
            <input type="text" name="price_unit" id="loc_unit_input" class="form-control" value="người">
          </div>

          <div class="col-md-6">
            <label class="form-label">Giờ hoạt động</label>
            <input type="text" name="opening_hours" id="loc_hours_input" class="form-control font-monospace" value="07:00 - 18:00">
          </div>
          <div class="col-md-6">
            <label class="form-label">Ảnh đại diện (URL)</label>
            <input type="url" name="thumbnail_url" id="loc_thumb_input" class="form-control" value="https://images.unsplash.com/photo-1528127269322-539801943592?w=800">
          </div>

          <div class="col-12">
            <label class="form-label">Mô tả ngắn</label>
            <input type="text" name="short_desc" id="loc_desc_input" class="form-control" required>
          </div>

          <div class="col-md-6">
            <label class="form-label">Lưu ý theo mùa (Tham quan) / Món nổi bật (Ăn uống)</label>
            <input type="text" name="seasonal_note" id="loc_seasonal_input" class="form-control">
          </div>
          <div class="col-md-6">
            <label class="form-label">Thời lượng tham quan / Tiện ích lưu trú</label>
            <input type="text" name="visit_duration" id="loc_dur_input" class="form-control">
          </div>

          <div class="col-12">
            <button type="submit" class="btn-cin-amber w-100 justify-content-center py-2">
              <i class="bi bi-check2-circle"></i>Lưu thông tin địa điểm
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
function resetTemplateModal() {
  document.getElementById('tplModalTitle').textContent = 'Biên soạn Lịch trình mẫu mới';
  document.getElementById('tpl_id_input').value = 0;
  document.getElementById('tpl_title_input').value = '';
  document.getElementById('tpl_dest_input').value = '';
  document.getElementById('tpl_reason_input').value = '';
  document.getElementById('tpl_summary_input').value = '';
  document.getElementById('tpl_notes_input').value = '';
  document.querySelectorAll('.tpl-month-chk').forEach(c => c.checked = false);
}

function editTemplateModal(tpl, months) {
  document.getElementById('tplModalTitle').textContent = 'Chỉnh sửa Lịch trình mẫu #' + tpl.id;
  document.getElementById('tpl_id_input').value = tpl.id;
  document.getElementById('tpl_title_input').value = tpl.title;
  document.getElementById('tpl_status_input').value = tpl.status;
  document.getElementById('tpl_prov_input').value = tpl.province_id;
  document.getElementById('tpl_dest_input').value = tpl.main_destination;
  document.getElementById('tpl_days_input').value = tpl.duration_days;
  document.getElementById('tpl_nights_input').value = tpl.duration_nights;
  document.getElementById('tpl_budget_input').value = tpl.ref_budget_per_person;
  document.getElementById('tpl_trans_input').value = tpl.suggested_transport;
  document.getElementById('tpl_thumb_input').value = tpl.thumbnail_url;
  document.getElementById('tpl_reason_input').value = tpl.seasonal_reason || '';
  document.getElementById('tpl_summary_input').value = tpl.summary || '';
  document.getElementById('tpl_notes_input').value = tpl.essential_notes || '';
  document.querySelectorAll('.tpl-month-chk').forEach(c => {
    c.checked = months.includes(parseInt(c.value, 10));
  });
  new bootstrap.Modal(document.getElementById('templateEditorModal')).show();
}

function resetLocationModal() {
  document.getElementById('locModalTitle').textContent = 'Thêm Địa điểm mới';
  document.getElementById('loc_id_input').value = 0;
  document.getElementById('loc_name_input').value = '';
  document.getElementById('loc_addr_input').value = '';
  document.getElementById('loc_desc_input').value = '';
}

function editLocationModal(loc) {
  document.getElementById('locModalTitle').textContent = 'Cập nhật Địa điểm #' + loc.id;
  document.getElementById('loc_id_input').value = loc.id;
  document.getElementById('loc_name_input').value = loc.name;
  document.getElementById('loc_type_input').value = loc.location_type;
  document.getElementById('loc_cat_input').value = loc.category_id;
  document.getElementById('loc_prov_input').value = loc.province_id;
  document.getElementById('loc_addr_input').value = loc.address || '';
  document.getElementById('loc_lat_input').value = loc.latitude;
  document.getElementById('loc_lng_input').value = loc.longitude;
  document.getElementById('loc_price_input').value = loc.ref_price;
  document.getElementById('loc_unit_input').value = loc.price_unit;
  document.getElementById('loc_hours_input').value = loc.opening_hours || '';
  document.getElementById('loc_thumb_input').value = loc.thumbnail_url || '';
  document.getElementById('loc_desc_input').value = loc.short_desc || '';
  document.getElementById('loc_seasonal_input').value = loc.seasonal_note || loc.signature_dishes || '';
  document.getElementById('loc_dur_input').value = loc.visit_duration || loc.amenities || '';
  new bootstrap.Modal(document.getElementById('locationEditorModal')).show();
}

function editTplDay(td) {
  document.getElementById('tday_id_input').value = td.id;
  document.getElementById('tday_num_input').value = td.day_number;
  document.getElementById('tday_title_input').value = td.day_title || '';
  document.getElementById('tday_sum_input').value = td.summary || '';
  document.getElementById('tday_title_input').focus();
}

function resetTplActForm() {
  document.getElementById('tactFormTitle').innerHTML = '<i class="bi bi-clock-history text-primary me-1"></i>Thêm Hoạt động mới vào Ngày mẫu';
  document.getElementById('tact_id_input').value = 0;
  document.getElementById('tact_title_input').value = '';
  document.getElementById('tact_loc_input').value = 0;
  document.getElementById('tact_cost_input').value = 0;
  document.getElementById('tact_trans_input').value = '';
  document.getElementById('tact_desc_input').value = '';
  document.getElementById('tact_note_input').value = '';
}

function editTplAct(ta) {
  document.getElementById('tactFormTitle').innerHTML = '<i class="bi bi-pencil-square text-primary me-1"></i>Chỉnh sửa Hoạt động mẫu #' + ta.id;
  document.getElementById('tact_id_input').value = ta.id;
  document.getElementById('tact_day_input').value = ta.template_day_id;
  document.getElementById('tact_title_input').value = ta.title || '';
  document.getElementById('tact_loc_input').value = ta.location_id || 0;
  document.getElementById('tact_type_input').value = ta.activity_type || 'sightseeing';
  document.getElementById('tact_start_input').value = (ta.start_time || '08:00').substring(0, 5);
  document.getElementById('tact_end_input').value = (ta.end_time || '10:00').substring(0, 5);
  document.getElementById('tact_cost_input').value = ta.estimated_cost_per_person || 0;
  document.getElementById('tact_sort_input').value = ta.sort_order || 1;
  document.getElementById('tact_trans_input').value = ta.transport_note || '';
  document.getElementById('tact_desc_input').value = ta.description || '';
  document.getElementById('tact_note_input').value = ta.important_note || '';
  document.getElementById('tplActivityFormBox').scrollIntoView({ behavior: 'smooth', block: 'center' });
}

function editProvince(pr) {
  document.getElementById('prov_id_input').value = pr.id;
  document.getElementById('prov_name_input').value = pr.name || '';
  document.getElementById('prov_reg_input').value = pr.region_sub || 'Đông Bắc';
  document.getElementById('prov_status_input').value = pr.status || 'active';
  document.getElementById('prov_lat_input').value = pr.center_lat || 21.0285;
  document.getElementById('prov_lng_input').value = pr.center_lng || 105.8542;
  document.getElementById('prov_sum_input').value = pr.featured_summary || '';
  document.getElementById('prov_name_input').focus();
}

function editCategory(cat) {
  document.getElementById('cat_id_input').value = cat.id;
  document.getElementById('cat_name_input').value = cat.name || '';
  document.getElementById('cat_type_input').value = cat.location_type || 'sightseeing';
  document.getElementById('cat_icon_input').value = cat.icon_class || 'bi-geo-alt';
  document.getElementById('cat_status_input').value = cat.status || 'active';
  document.getElementById('cat_name_input').focus();
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
