<?php
/**
 * Bộ hàm tiện ích dùng chung, Phân quyền (Guest / User / Admin), Bảo mật CSRF
 * và Quy tắc nghiệp vụ Dự án Bắc Du (Travel Planner)
 */

require_once __DIR__ . '/../config/db.php';

/**
 * Escape HTML an toàn chống XSS
 */
function e(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Định dạng tiền tệ VNĐ
 */
function format_vnd($amount, bool $showFree = false): string
{
    $num = (float)$amount;
    if ($num <= 0 && $showFree) {
        return 'Miễn phí';
    }
    return number_format($num, 0, ',', '.') . ' đ';
}

/**
 * Định dạng ngày kiểu Việt Nam (dd/mm/yyyy)
 */
function format_date_vn(?string $dateStr): string
{
    if (!$dateStr) {
        return '—';
    }
    $ts = strtotime($dateStr);
    return $ts ? date('d/m/Y', $ts) : $dateStr;
}

/**
 * Tạo slug tiếng Việt chuẩn SEO
 */
function slugify(string $text): string
{
    $map = [
        '/(à|á|ạ|ả|ã|â|ầ|ấ|ậ|ẩ|ẫ|ă|ằ|ắ|ặ|ẳ|ẵ)/u' => 'a',
        '/(è|é|ẹ|ẻ|ẽ|ê|ề|ế|ệ|ể|ễ)/u' => 'e',
        '/(ì|í|ị|ỉ|ĩ)/u' => 'i',
        '/(ò|ó|ọ|ỏ|õ|ô|ồ|ố|ộ|ổ|ỗ|ơ|ờ|ớ|ợ|ở|ỡ)/u' => 'o',
        '/(ù|ú|ụ|ủ|ũ|ư|ừ|ứ|ự|ử|ữ)/u' => 'u',
        '/(ỳ|ý|ỵ|ỷ|ỹ)/u' => 'y',
        '/(đ)/u' => 'd',
        '/(À|Á|Ạ|Ả|Ã|Â|Ầ|Ấ|Ậ|Ẩ|Ẫ|Ă|Ằ|Ắ|Ặ|Ẳ|Ẵ)/u' => 'A',
        '/(È|É|Ẹ|Ẻ|Ẽ|Ê|Ề|Ế|Ệ|Ể|Ễ)/u' => 'E',
        '/(Ì|Í|Ị|Ỉ|Ĩ)/u' => 'I',
        '/(Ò|Ó|Ọ|Ỏ|Õ|Ô|Ồ|Ố|Ộ|Ổ|Ỗ|Ơ|Ờ|Ớ|Ợ|Ở|Ỡ)/u' => 'O',
        '/(Ù|Ú|Ụ|Ủ|Ũ|Ư|Ừ|Ứ|Ự|Ử|Ữ)/u' => 'U',
        '/(Ỳ|Ý|Ỵ|Ỷ|Ỹ)/u' => 'Y',
        '/(Đ)/u' => 'D',
    ];
    $text = preg_replace(array_keys($map), array_values($map), $text);
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    return trim($text, '-') ?: 'bac-du-' . time();
}

/**
 * CSRF Token Helpers
 */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(24));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): bool
{
    $token = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';
    return !empty($token) && hash_equals(csrf_token(), (string)$token);
}

/**
 * Hệ thống Flash Message
 */
function set_flash(string $type, string $message): void
{
    $_SESSION['flashes'][] = ['type' => $type, 'message' => $message];
}

function get_flashes(): array
{
    $flashes = $_SESSION['flashes'] ?? [];
    unset($_SESSION['flashes']);
    return $flashes;
}

/**
 * Quản lý Phiên đăng nhập & Phân quyền (M1)
 */
function current_user(): ?array
{
    static $cachedUser = false;
    if ($cachedUser !== false) {
        return $cachedUser;
    }

    if (empty($_SESSION['user_id'])) {
        $cachedUser = null;
        return null;
    }

    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([(int)$_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user || $user['status'] === 'locked') {
        unset($_SESSION['user_id']);
        $cachedUser = null;
        return null;
    }

    $cachedUser = $user;
    return $user;
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

function is_admin(): bool
{
    $user = current_user();
    return $user !== null && $user['role'] === 'admin';
}

function require_login(?string $redirectAfter = null): array
{
    $user = current_user();
    if (!$user) {
        $target = $redirectAfter ?: ($_SERVER['REQUEST_URI'] ?? 'index.php');
        set_flash('warning', 'Vui lòng đăng nhập để sử dụng tính năng lập kế hoạch và chia sẻ trải nghiệm.');
        header('Location: auth.php?tab=login&redirect=' . urlencode($target));
        exit;
    }
    return $user;
}

function require_admin(): array
{
    $user = require_login();
    if ($user['role'] !== 'admin') {
        set_flash('danger', 'Bạn không có quyền truy cập khu vực Quản trị & Ban biên tập.');
        header('Location: index.php');
        exit;
    }
    return $user;
}

/**
 * Kiểm tra mật khẩu người dùng (hỗ trợ cả bcrypt chuẩn và mật khẩu demo 123456)
 */
function verify_user_password(string $plain, string $hash): bool
{
    if (password_verify($plain, $hash)) {
        return true;
    }
    // Hỗ trợ mật khẩu mặc định '123456' cho các tài khoản seed mẫu trong travel_planner.sql
    if ($plain === '123456' && $hash === '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi') {
        return true;
    }
    return false;
}

/**
 * Thông tin đặc trưng 12 tháng du lịch miền Bắc
 */
function get_months_catalog(): array
{
    return [
        1  => ['name' => 'Tháng 1',  'season' => 'Mùa Xuân', 'tag' => 'Hoa đào rừng & Săn mây Tà Xùa', 'highlight' => 'Mộc Châu, Tà Xùa, Sa Pa'],
        2  => ['name' => 'Tháng 2',  'season' => 'Mùa Xuân', 'tag' => 'Hoa mận trắng Nà Ka & Du xuân Tràng An', 'highlight' => 'Mộc Châu, Ninh Bình, Yên Tử'],
        3  => ['name' => 'Tháng 3',  'season' => 'Mùa Xuân', 'tag' => 'Hoa lê Hà Giang & Hoa ban Tây Bắc', 'highlight' => 'Hà Giang, Điện Biên, Ninh Bình'],
        4  => ['name' => 'Tháng 4',  'season' => 'Giao mùa', 'tag' => 'Đỗ quyên Fansipan & Thung lũng xanh', 'highlight' => 'Sa Pa, Pù Luông, Mai Châu'],
        5  => ['name' => 'Tháng 5',  'season' => 'Mùa Hạ',   'tag' => 'Lúa vàng Tam Cốc & Mùa hái mận chín', 'highlight' => 'Ninh Bình, Mộc Châu, Sa Pa'],
        6  => ['name' => 'Tháng 6',  'season' => 'Mùa Hạ',   'tag' => 'Du thuyền Vịnh Hạ Long & Sen Hang Múa', 'highlight' => 'Hạ Long, Cô Tô, Ninh Bình'],
        7  => ['name' => 'Tháng 7',  'season' => 'Mùa Hạ',   'tag' => 'Biển đảo Quảng Ninh & Cao nguyên mát', 'highlight' => 'Hạ Long, Cô Tô, Mộc Châu'],
        8  => ['name' => 'Tháng 8',  'season' => 'Chớm Thu', 'tag' => 'Thác Bản Giốc nước ngọc bích', 'highlight' => 'Cao Bằng, Hạ Long, Ba Bể'],
        9  => ['name' => 'Tháng 9',  'season' => 'Mùa Thu',  'tag' => 'Mùa vàng ruộng bậc thang Mường Hoa', 'highlight' => 'Sa Pa, Cao Bằng, Hà Nội'],
        10 => ['name' => 'Tháng 10', 'season' => 'Mùa Thu',  'tag' => 'Tam giác mạch Hà Giang & Thu Hà Nội', 'highlight' => 'Hà Giang, Pù Luông, Hà Nội'],
        11 => ['name' => 'Tháng 11', 'season' => 'Chớm Đông','tag' => 'Cải trắng Mộc Châu & Biển mây Tây Bắc', 'highlight' => 'Hà Giang, Tà Xùa, Mộc Châu'],
        12 => ['name' => 'Tháng 12', 'season' => 'Mùa Đông', 'tag' => 'Săn mây Tà Xùa & Dâu tây Bản Áng', 'highlight' => 'Tà Xùa, Sa Pa, Mộc Châu'],
    ];
}

/**
 * Metadata hiển thị cho 3 nhóm Địa điểm (Tham quan / Ăn uống / Lưu trú)
 */
function location_type_meta(string $type): array
{
    $map = [
        'attraction' => [
            'label' => 'Điểm tham quan',
            'short' => 'Tham quan',
            'badge' => 'badge-attraction',
            'icon'  => 'bi-compass',
            'color' => '#0f766e',
        ],
        'food' => [
            'label' => 'Ăn uống & Quán ngon',
            'short' => 'Ăn uống',
            'badge' => 'badge-food',
            'icon'  => 'bi-cup-hot',
            'color' => '#d97706',
        ],
        'stay' => [
            'label' => 'Nơi lưu trú',
            'short' => 'Lưu trú',
            'badge' => 'badge-stay',
            'icon'  => 'bi-house-heart',
            'color' => '#4f46e5',
        ],
    ];
    return $map[$type] ?? $map['attraction'];
}

/**
 * Metadata cho loại Hoạt động trong lịch trình
 */
function activity_type_meta(string $type): array
{
    $map = [
        'attraction'       => ['label' => 'Tham quan',       'icon' => 'bi-camera-fill',       'class' => 'act-attraction'],
        'food'             => ['label' => 'Ăn uống',         'icon' => 'bi-egg-fried',         'class' => 'act-food'],
        'checkin_checkout' => ['label' => 'Nhận/Trả phòng',  'icon' => 'bi-key-fill',          'class' => 'act-stay'],
        'transport'        => ['label' => 'Di chuyển',       'icon' => 'bi-bus-front-fill',    'class' => 'act-transport'],
        'other'            => ['label' => 'Hoạt động khác',  'icon' => 'bi-stars',             'class' => 'act-other'],
    ];
    return $map[$type] ?? $map['other'];
}

/**
 * Metadata cho 6 nhóm Khoản chi (M6)
 */
function expense_category_meta(string $category): array
{
    $map = [
        'food'      => ['label' => 'Ăn uống',        'icon' => 'bi-cup-straw',     'color' => '#f59e0b'],
        'transport' => ['label' => 'Di chuyển',      'icon' => 'bi-truck-front',   'color' => '#0ea5e9'],
        'stay'      => ['label' => 'Lưu trú',        'icon' => 'bi-house-door',    'color' => '#6366f1'],
        'ticket'    => ['label' => 'Vé tham quan',   'icon' => 'bi-ticket-perforated', 'color' => '#10b981'],
        'shopping'  => ['label' => 'Mua sắm & Quà',  'icon' => 'bi-bag-heart',     'color' => '#ec4899'],
        'other'     => ['label' => 'Chi phí khác',   'icon' => 'bi-wallet2',       'color' => '#64748b'],
    ];
    return $map[$category] ?? $map['other'];
}

/**
 * Metadata trạng thái Bài trải nghiệm cộng đồng (M3)
 */
function post_status_meta(string $status): array
{
    $map = [
        'draft'    => ['label' => 'Bản nháp',    'class' => 'bg-secondary-subtle text-secondary-emphasis border-secondary-subtle', 'icon' => 'bi-file-earmark-text'],
        'pending'  => ['label' => 'Chờ duyệt',   'class' => 'bg-warning-subtle text-warning-emphasis border-warning-subtle',       'icon' => 'bi-hourglass-split'],
        'approved' => ['label' => 'Đã công khai','class' => 'bg-success-subtle text-success-emphasis border-success-subtle',       'icon' => 'bi-check-circle-fill'],
        'rejected' => ['label' => 'Bị từ chối',  'class' => 'bg-danger-subtle text-danger-emphasis border-danger-subtle',          'icon' => 'bi-x-circle-fill'],
        'hidden'   => ['label' => 'Đã ẩn',       'class' => 'bg-dark-subtle text-dark-emphasis border-dark-subtle',                'icon' => 'bi-eye-slash-fill'],
    ];
    return $map[$status] ?? $map['pending'];
}

/**
 * Tính trạng thái chuyến đi theo ngày hiện tại: Sắp diễn ra / Đang diễn ra / Đã kết thúc (M4)
 */
function trip_timeline_status(string $startDate, string $endDate): array
{
    $today = date('Y-m-d');
    if ($today < $startDate) {
        $daysLeft = (int)round((strtotime($startDate) - strtotime($today)) / 86400);
        return [
            'code'  => 'upcoming',
            'label' => 'Sắp diễn ra' . ($daysLeft > 0 ? " (còn {$daysLeft} ngày)" : ''),
            'badge' => 'bg-info-subtle text-info-emphasis border-info-subtle',
            'icon'  => 'bi-calendar-event',
        ];
    }
    if ($today >= $startDate && $today <= $endDate) {
        return [
            'code'  => 'ongoing',
            'label' => 'Đang diễn ra',
            'badge' => 'bg-success-subtle text-success-emphasis border-success-subtle',
            'icon'  => 'bi-broadcast',
        ];
    }
    return [
        'code'  => 'completed',
        'label' => 'Đã kết thúc',
        'badge' => 'bg-secondary-subtle text-secondary-emphasis border-secondary-subtle',
        'icon'  => 'bi-check2-all',
    ];
}

/**
 * Tạo liên kết Google Maps xem vị trí & chỉ đường từ tọa độ
 */
function google_maps_view_url($lat, $lng, ?string $customUrl = null): string
{
    if (!empty($customUrl)) {
        return $customUrl;
    }
    return sprintf('https://www.google.com/maps/search/?api=1&query=%s,%s', urlencode((string)$lat), urlencode((string)$lng));
}

function google_maps_directions_url($lat, $lng): string
{
    return sprintf('https://www.google.com/maps/dir/?api=1&destination=%s,%s', urlencode((string)$lat), urlencode((string)$lng));
}

/**
 * Tính tổng hợp Ngân sách của Chuyến đi theo Quy tắc chống tính trùng (Mục 4.9 Đặc tả):
 * - Bảng `expenses` là nguồn duy nhất tính tổng dự kiến và thực tế của chuyến đi.
 * - Chi phí từ Hoạt động (`activities`), Lưu trú (`accommodations`), Phương tiện (`transports`)
 *   khi tích chọn "Đồng bộ vào bảng Khoản chi" sẽ tạo/cập nhật bản ghi liên kết trong `expenses`
 *   thông qua `linked_activity_id`, `linked_accommodation_id`, `linked_transport_id` thay vì cộng dồn hai lần.
 */
function calculate_trip_budget_summary(PDO $pdo, int $tripId, float $totalBudget): array
{
    $stmt = $pdo->prepare('
        SELECT
            expense_category,
            COUNT(*) AS items_count,
            COALESCE(SUM(estimated_amount), 0) AS sum_estimated,
            COALESCE(SUM(actual_amount), 0) AS sum_actual
        FROM expenses
        WHERE trip_id = ?
        GROUP BY expense_category
    ');
    $stmt->execute([$tripId]);
    $rows = $stmt->fetchAll();

    $byCategory = [];
    foreach (['food', 'transport', 'stay', 'ticket', 'shopping', 'other'] as $cat) {
        $meta = expense_category_meta($cat);
        $byCategory[$cat] = [
            'category'      => $cat,
            'label'         => $meta['label'],
            'icon'          => $meta['icon'],
            'color'         => $meta['color'],
            'items_count'   => 0,
            'sum_estimated' => 0.0,
            'sum_actual'    => 0.0,
        ];
    }

    $totalEstimated = 0.0;
    $totalActual = 0.0;
    foreach ($rows as $r) {
        $cat = $r['expense_category'];
        if (!isset($byCategory[$cat])) {
            $cat = 'other';
        }
        $est = (float)$r['sum_estimated'];
        $act = (float)$r['sum_actual'];
        $byCategory[$cat]['items_count'] += (int)$r['items_count'];
        $byCategory[$cat]['sum_estimated'] += $est;
        $byCategory[$cat]['sum_actual'] += $act;
        $totalEstimated += $est;
        $totalActual += $act;
    }

    $remainingVsEstimated = $totalBudget - $totalEstimated;
    $remainingVsActual = $totalBudget - $totalActual;

    return [
        'total_budget'           => $totalBudget,
        'total_estimated'        => $totalEstimated,
        'total_actual'           => $totalActual,
        'remaining_vs_estimated' => $remainingVsEstimated,
        'remaining_vs_actual'    => $remainingVsActual,
        'percent_estimated'      => $totalBudget > 0 ? round(($totalEstimated / $totalBudget) * 100, 1) : 0,
        'percent_actual'         => $totalBudget > 0 ? round(($totalActual / $totalBudget) * 100, 1) : 0,
        'by_category'            => $byCategory,
    ];
}

/**
 * Sao chép Lịch trình mẫu thành Chuyến đi cá nhân độc lập (Mục 4.3 Đặc tả)
 */
function clone_template_to_trip(
    PDO $pdo,
    int $userId,
    int $templateId,
    string $tripTitle,
    string $startDate,
    int $paxCount,
    string $departurePoint,
    float $plannedBudget
): int {
    $stmt = $pdo->prepare('SELECT * FROM itinerary_templates WHERE id = ? LIMIT 1');
    $stmt->execute([$templateId]);
    $template = $stmt->fetch();
    if (!$template) {
        throw new RuntimeException('Không tìm thấy lịch trình mẫu.');
    }

    $durationDays = max(1, (int)$template['duration_days']);
    $startTs = strtotime($startDate);
    if (!$startTs) {
        $startTs = time();
        $startDate = date('Y-m-d', $startTs);
    }
    $endDate = date('Y-m-d', strtotime('+' . ($durationDays - 1) . ' days', $startTs));
    $paxCount = max(1, $paxCount);
    $basePax = max(1, (int)$template['base_pax_count']);

    if ($plannedBudget <= 0) {
        $plannedBudget = (float)$template['ref_budget_per_person'] * $paxCount;
    }

    $pdo->beginTransaction();
    try {
        // 1. Tạo bản ghi chuyến đi độc lập trong bảng trips
        $insTrip = $pdo->prepare('
            INSERT INTO trips (
                user_id, source_template_id, province_id, title, main_destination,
                start_date, end_date, pax_count, departure_point, total_budget,
                description, cover_image_url, share_token, is_shared
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)
        ');
        $shareToken = 'bacdu-' . substr(bin2hex(random_bytes(6)), 0, 10);
        $insTrip->execute([
            $userId,
            $templateId,
            $template['province_id'],
            $tripTitle,
            $template['main_destination'],
            $startDate,
            $endDate,
            $paxCount,
            $departurePoint,
            $plannedBudget,
            'Khởi tạo từ lịch trình mẫu "' . $template['title'] . '". Bạn có thể tùy chỉnh toàn bộ hoạt động, quán ăn, lưu trú và ngân sách.',
            $template['thumbnail_url'],
            $shareToken,
        ]);
        $tripId = (int)$pdo->lastInsertId();

        // 2. Lấy các ngày mẫu và chuyển thành ngày thực tế theo ngày khởi hành
        $stmtDays = $pdo->prepare('SELECT * FROM template_days WHERE template_id = ? ORDER BY day_number ASC');
        $stmtDays->execute([$templateId]);
        $tDays = $stmtDays->fetchAll();

        $activityIdMap = []; // template_activity_id => new activity_id
        $insDay = $pdo->prepare('INSERT INTO trip_days (trip_id, day_number, trip_date, title, notes) VALUES (?, ?, ?, ?, ?)');
        $insAct = $pdo->prepare('
            INSERT INTO activities (
                trip_day_id, location_id, title, activity_type,
                start_time, end_time, description, estimated_cost, notes, sort_order
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $stmtTActs = $pdo->prepare('SELECT * FROM template_activities WHERE template_day_id = ? ORDER BY sort_order ASC, id ASC');

        if (empty($tDays)) {
            for ($d = 1; $d <= $durationDays; $d++) {
                $dayDate = date('Y-m-d', strtotime('+' . ($d - 1) . ' days', $startTs));
                $insDay->execute([$tripId, $d, $dayDate, "Ngày {$d}: Khám phá " . $template['main_destination'], '']);
            }
        } else {
            foreach ($tDays as $tDay) {
                $dayNum = (int)$tDay['day_number'];
                $dayDate = date('Y-m-d', strtotime('+' . ($dayNum - 1) . ' days', $startTs));
                $insDay->execute([
                    $tripId,
                    $dayNum,
                    $dayDate,
                    $tDay['title'],
                    $tDay['transport_note'] ?: $tDay['summary'],
                ]);
                $newDayId = (int)$pdo->lastInsertId();

                // Sao chép các hoạt động của ngày đó
                $stmtTActs->execute([$tDay['id']]);
                $tActs = $stmtTActs->fetchAll();
                foreach ($tActs as $tAct) {
                    // Chi phí hoạt động trong mẫu thường theo người -> nhân số người dự kiến
                    $estCost = (float)$tAct['estimated_cost'] * $paxCount;
                    $noteParts = array_filter([$tAct['transport_note'], $tAct['important_note']]);
                    $insAct->execute([
                        $newDayId,
                        $tAct['location_id'] ?: null,
                        $tAct['title'],
                        $tAct['activity_type'],
                        $tAct['start_time'],
                        $tAct['end_time'],
                        $tAct['description'],
                        $estCost,
                        implode(' | ', $noteParts),
                        (int)$tAct['sort_order'],
                    ]);
                    $activityIdMap[(int)$tAct['id']] = (int)$pdo->lastInsertId();
                }
            }
        }

        // 3. Sao chép gợi ý lưu trú từ template_accommodations
        $stmtTAcc = $pdo->prepare('
            SELECT ta.*, l.address AS loc_address, l.contact_info AS loc_contact
            FROM template_accommodations ta
            LEFT JOIN locations l ON l.id = ta.location_id
            WHERE ta.template_id = ?
            ORDER BY ta.night_number ASC
        ');
        $stmtTAcc->execute([$templateId]);
        $tAccs = $stmtTAcc->fetchAll();

        $accommodationIdMap = [];
        $insAcc = $pdo->prepare('
            INSERT INTO accommodations (
                trip_id, location_id, stay_name, address,
                checkin_date, checkin_time, checkout_date, checkout_time,
                room_count, estimated_cost, actual_cost, contact_info, booking_status, notes
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?)
        ');
        foreach ($tAccs as $tAcc) {
            $nightNum = max(1, (int)$tAcc['night_number']);
            $checkinDate = date('Y-m-d', strtotime('+' . ($nightNum - 1) . ' days', $startTs));
            $checkoutDate = date('Y-m-d', strtotime('+' . $nightNum . ' days', $startTs));
            $insAcc->execute([
                $tripId,
                $tAcc['location_id'] ?: null,
                $tAcc['stay_name'],
                $tAcc['loc_address'] ?: $template['main_destination'],
                $checkinDate,
                '14:00',
                $checkoutDate,
                '12:00',
                max(1, (int)ceil($paxCount / 2)),
                (float)$tAcc['base_cost'],
                $tAcc['loc_contact'] ?: '',
                'planned',
                'Gợi ý lưu trú từ lịch trình mẫu (chưa phải xác nhận đặt phòng thật). ' . ($tAcc['notes'] ?? ''),
            ]);
            $accommodationIdMap[(int)$tAcc['id']] = (int)$pdo->lastInsertId();
        }

        // 4. Sao chép các khoản dự toán từ template_expenses sang bảng expenses (nguồn chuẩn tính ngân sách)
        $stmtTExp = $pdo->prepare('SELECT * FROM template_expenses WHERE template_id = ? ORDER BY id ASC');
        $stmtTExp->execute([$templateId]);
        $tExps = $stmtTExp->fetchAll();

        $insExp = $pdo->prepare('
            INSERT INTO expenses (
                trip_id, expense_category, title, estimated_amount, actual_amount,
                linked_activity_id, linked_accommodation_id, expense_date, notes
            ) VALUES (?, ?, ?, ?, 0, ?, ?, ?, ?)
        ');

        if (!empty($tExps)) {
            foreach ($tExps as $tExp) {
                $linkedAct = !empty($tExp['linked_activity_id']) && isset($activityIdMap[(int)$tExp['linked_activity_id']])
                    ? $activityIdMap[(int)$tExp['linked_activity_id']]
                    : null;
                $linkedAcc = !empty($tExp['linked_accommodation_id']) && isset($accommodationIdMap[(int)$tExp['linked_accommodation_id']])
                    ? $accommodationIdMap[(int)$tExp['linked_accommodation_id']]
                    : null;
                // Quy đổi dự toán theo tỷ lệ số người so với cơ sở tính của mẫu
                $scaledEst = round(((float)$tExp['estimated_amount'] / $basePax) * $paxCount, -3);
                $insExp->execute([
                    $tripId,
                    $tExp['expense_category'],
                    $tExp['title'],
                    $scaledEst,
                    $linkedAct,
                    $linkedAcc,
                    $startDate,
                    'Dự toán sao chép từ mẫu (' . ($tExp['notes'] ?: 'Cần điều chỉnh theo thực tế chuyến đi') . ')',
                ]);
            }
        } else {
            // Nếu mẫu chưa khai báo chi tiết từng khoản dự toán, tạo khoản dự toán khởi tạo theo nhóm
            $insExp->execute([
                $tripId,
                'other',
                'Dự toán cơ sở từ lịch trình mẫu (' . $paxCount . ' người)',
                $plannedBudget,
                null,
                null,
                $startDate,
                'Dự toán ban đầu được tính từ mức tham khảo của mẫu.',
            ]);
        }

        // 5. Khởi tạo bộ Checklist chuẩn bị mặc định cho chuyến đi miền Bắc
        $defaultChecklists = [
            ['Căn cước công dân (CCCD) & Giấy phép lái xe', 'Giấy tờ & Tiền mặt', 1],
            ['Áo khoác ấm / Trang phục phù hợp theo mùa miền Bắc', 'Trang phục', 2],
            ['Giày thể thao đế bám tốt để đi bộ tham quan', 'Trang phục', 3],
            ['Sạc dự phòng, điện thoại & dây cáp sạc', 'Thiết bị điện tử', 4],
            ['Thuốc say tàu xe, băng cá nhân & kem chống côn trùng', 'Y tế cá nhân', 5],
        ];
        $insCheck = $pdo->prepare('INSERT INTO checklists (trip_id, item_name, category, is_completed, sort_order) VALUES (?, ?, ?, 0, ?)');
        foreach ($defaultChecklists as $item) {
            $insCheck->execute([$tripId, $item[0], $item[1], $item[2]]);
        }

        // 6. Tăng bộ đếm copies_count của lịch trình mẫu phục vụ thống kê Product Insights (Thành viên 5)
        $updCopy = $pdo->prepare('UPDATE itinerary_templates SET copies_count = copies_count + 1 WHERE id = ?');
        $updCopy->execute([$templateId]);

        $pdo->commit();
        return $tripId;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}
