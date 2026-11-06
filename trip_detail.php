<?php
require_once __DIR__ . '/includes/helpers.php';

$user = require_login();
$pdo = get_db();
$tripId = isset($_GET['id']) ? (int)$_GET['id'] : (int)($_POST['trip_id'] ?? 0);
$activeTab = $_GET['tab'] ?? 'itinerary';

$stmtTrip = $pdo->prepare('
    SELECT
        t.*,
        it.title AS source_template_title
    FROM trips t
    LEFT JOIN itinerary_templates it ON it.id = t.source_template_id
    WHERE t.id = ? AND (t.user_id = ? OR ? = 1)
    LIMIT 1
');
$stmtTrip->execute([$tripId, $user['id'], $user['role'] === 'admin' ? 1 : 0]);
$trip = $stmtTrip->fetch();

if (!$trip) {
    set_flash('warning', 'Không tìm thấy chuyến đi hoặc bạn không có quyền truy cập.');
    header('Location: trips.php');
    exit;
}

// XỬ LÝ CÁC HÀNH ĐỘNG POST TRONG TRIP DASHBOARD
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        set_flash('danger', 'Phiên làm việc không hợp lệ.');
        header('Location: trip_detail.php?id=' . $tripId);
        exit;
    }

    $action = $_POST['action'] ?? '';
    $redirectTab = $_POST['redirect_tab'] ?? 'itinerary';

    // 1. Cập nhật thông tin chuyến đi & Kiểm tra ảnh hưởng ngày/hoạt động (Mục 4.4 & 4.5)
    if ($action === 'update_trip_info') {
        $title = trim($_POST['title'] ?? $trip['title']);
        $mainDest = trim($_POST['main_destination'] ?? $trip['main_destination']);
        $startDate = trim($_POST['start_date'] ?? $trip['start_date']);
        $endDate = trim($_POST['end_date'] ?? $trip['end_date']);
        $paxCount = max(1, (int)($_POST['pax_count'] ?? $trip['pax_count']));
        $depPoint = trim($_POST['departure_point'] ?? $trip['departure_point']);
        $totalBudget = max(0, (float)($_POST['total_budget'] ?? $trip['total_budget']));
        $description = trim($_POST['description'] ?? '');

        if (strtotime($endDate) < strtotime($startDate)) {
            $endDate = $startDate;
        }

        $upd = $pdo->prepare('
            UPDATE trips
            SET title = ?, main_destination = ?, start_date = ?, end_date = ?,
                pax_count = ?, departure_point = ?, total_budget = ?, description = ?
            WHERE id = ?
        ');
        $upd->execute([$title, $mainDest, $startDate, $endDate, $paxCount, $depPoint, $totalBudget, $description, $tripId]);

        // Cập nhật lại ngày thực tế của các trip_days hiện có và kiểm tra ngày vượt quá khoảng mới
        $newTotalDays = max(1, (int)round((strtotime($endDate) - strtotime($startDate)) / 86400) + 1);
        $stmtDays = $pdo->prepare('SELECT * FROM trip_days WHERE trip_id = ? ORDER BY day_number ASC');
        $stmtDays->execute([$tripId]);
        $existingDays = $stmtDays->fetchAll();

        $affectedDays = [];
        $updDayDate = $pdo->prepare('UPDATE trip_days SET trip_date = ? WHERE id = ?');
        foreach ($existingDays as $ed) {
            $dNum = (int)$ed['day_number'];
            $newDate = date('Y-m-d', strtotime('+' . ($dNum - 1) . ' days', strtotime($startDate)));
            $updDayDate->execute([$newDate, $ed['id']]);
            if ($dNum > $newTotalDays) {
                $affectedDays[] = 'Ngày ' . $dNum . ' (' . format_date_vn($newDate) . ')';
            }
        }

        // Nếu khoảng ngày mới dài hơn số ngày hiện có -> tự động bổ sung các ngày còn thiếu
        $maxExistingDayNum = empty($existingDays) ? 0 : max(array_map(fn($d) => (int)$d['day_number'], $existingDays));
        if ($newTotalDays > $maxExistingDayNum && $newTotalDays <= 20) {
            $insDay = $pdo->prepare('INSERT INTO trip_days (trip_id, day_number, trip_date, title, notes) VALUES (?, ?, ?, ?, ?)');
            for ($d = $maxExistingDayNum + 1; $d <= $newTotalDays; $d++) {
                $dDate = date('Y-m-d', strtotime('+' . ($d - 1) . ' days', strtotime($startDate)));
                $insDay->execute([$tripId, $d, $dDate, "Ngày {$d}: Khám phá {$mainDest}", '']);
            }
        }

        if (!empty($affectedDays)) {
            set_flash(
                'warning',
                'Đã cập nhật thông tin chuyến đi! <strong>Lưu ý (Quy tắc bảo toàn dữ liệu Mục 4.5):</strong> Khoảng ngày đi/về mới ngắn hơn lịch trình hiện tại. Các ngày đang nằm ngoài khoảng mới gồm: <strong>' . implode(', ', $affectedDays) . '</strong>. Hệ thống giữ nguyên các ngày và hoạt động này để bạn chủ động chuyển hoạt động trước khi xóa ngày.'
            );
        } else {
            set_flash('success', 'Đã cập nhật thông tin chuyến đi và đồng bộ mốc ngày lịch trình.');
        }
    }

    // 2. Thêm ngày mới vào lịch trình
    elseif ($action === 'add_trip_day') {
        $stmtMax = $pdo->prepare('SELECT COALESCE(MAX(day_number), 0) FROM trip_days WHERE trip_id = ?');
        $stmtMax->execute([$tripId]);
        $nextDayNum = (int)$stmtMax->fetchColumn() + 1;
        $nextDate = date('Y-m-d', strtotime('+' . ($nextDayNum - 1) . ' days', strtotime($trip['start_date'])));
        $dayTitle = trim($_POST['day_title'] ?? '') ?: "Ngày {$nextDayNum}: Hành trình khám phá";
        $dayNotes = trim($_POST['day_notes'] ?? '');

        $ins = $pdo->prepare('INSERT INTO trip_days (trip_id, day_number, trip_date, title, notes) VALUES (?, ?, ?, ?, ?)');
        $ins->execute([$tripId, $nextDayNum, $nextDate, $dayTitle, $dayNotes]);
        set_flash('success', "Đã thêm Ngày {$nextDayNum} vào lịch trình chuyến đi.");
    }

    // 3. Thêm hoặc Chỉnh sửa hoạt động vào ngày (UC07.8 & UC07.9 - kèm đồng bộ Khoản chi chống tính trùng)
    elseif ($action === 'add_activity' || $action === 'save_activity') {
        $actId = (int)($_POST['activity_id'] ?? 0);
        $tripDayId = (int)($_POST['trip_day_id'] ?? 0);
        $locationId = !empty($_POST['location_id']) ? (int)$_POST['location_id'] : null;
        $title = trim($_POST['title'] ?? '');
        $actType = $_POST['activity_type'] ?? 'attraction';
        $startTime = trim($_POST['start_time'] ?? '08:30');
        $endTime = trim($_POST['end_time'] ?? '10:30');
        $desc = trim($_POST['description'] ?? '');
        $estCost = max(0, (float)($_POST['estimated_cost'] ?? 0));
        $notes = trim($_POST['notes'] ?? '');
        $syncExpense = isset($_POST['sync_to_expense']) && (int)$_POST['sync_to_expense'] === 1;

        if ($locationId && $title === '') {
            $stmtL = $pdo->prepare('SELECT name, short_desc FROM locations WHERE id = ?');
            $stmtL->execute([$locationId]);
            $locInfo = $stmtL->fetch();
            if ($locInfo) {
                $title = $locInfo['name'];
                if ($desc === '') $desc = $locInfo['short_desc'];
            }
        }

        if ($tripDayId > 0 && $title !== '') {
            $expCat = 'other';
            if ($actType === 'food') $expCat = 'food';
            elseif ($actType === 'attraction') $expCat = 'ticket';
            elseif ($actType === 'transport') $expCat = 'transport';
            elseif ($actType === 'checkin_checkout') $expCat = 'stay';

            if ($actId > 0) {
                // UC07.9: Chỉnh sửa hoạt động hiện có
                $updAct = $pdo->prepare('
                    UPDATE activities
                    SET trip_day_id = ?, location_id = ?, title = ?, activity_type = ?,
                        start_time = ?, end_time = ?, description = ?, estimated_cost = ?, notes = ?
                    WHERE id = ?
                ');
                $updAct->execute([$tripDayId, $locationId, $title, $actType, $startTime, $endTime, $desc, $estCost, $notes, $actId]);

                if ($syncExpense) {
                    $chkExp = $pdo->prepare('SELECT id FROM expenses WHERE linked_activity_id = ? AND trip_id = ? LIMIT 1');
                    $chkExp->execute([$actId, $tripId]);
                    $linkedExpId = (int)$chkExp->fetchColumn();
                    if ($linkedExpId > 0) {
                        $pdo->prepare('UPDATE expenses SET expense_category = ?, title = ?, estimated_amount = ? WHERE id = ?')
                            ->execute([$expCat, $title, $estCost, $linkedExpId]);
                    } elseif ($estCost > 0) {
                        $pdo->prepare('
                            INSERT INTO expenses (trip_id, expense_category, title, estimated_amount, actual_amount, linked_activity_id, expense_date, notes)
                            VALUES (?, ?, ?, ?, 0, ?, ?, ?)
                        ')->execute([$tripId, $expCat, $title, $estCost, $actId, $trip['start_date'], 'Liên kết tự động từ Hoạt động lịch trình']);
                    }
                }
                set_flash('success', 'Đã cập nhật hoạt động <strong>' . e($title) . '</strong> (UC07.9).');
            } else {
                // UC07.8: Thêm hoạt động mới
                $stmtMaxSort = $pdo->prepare('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM activities WHERE trip_day_id = ?');
                $stmtMaxSort->execute([$tripDayId]);
                $nextSort = (int)$stmtMaxSort->fetchColumn();

                $insAct = $pdo->prepare('
                    INSERT INTO activities (trip_day_id, location_id, title, activity_type, start_time, end_time, description, estimated_cost, notes, sort_order)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ');
                $insAct->execute([$tripDayId, $locationId, $title, $actType, $startTime, $endTime, $desc, $estCost, $notes, $nextSort]);
                $newActId = (int)$pdo->lastInsertId();

                if ($syncExpense && $estCost > 0) {
                    $insExp = $pdo->prepare('
                        INSERT INTO expenses (trip_id, expense_category, title, estimated_amount, actual_amount, linked_activity_id, expense_date, notes)
                        VALUES (?, ?, ?, ?, 0, ?, ?, ?)
                    ');
                    $insExp->execute([$tripId, $expCat, $title, $estCost, $newActId, $trip['start_date'], 'Liên kết tự động từ Hoạt động lịch trình']);
                }
                set_flash('success', 'Đã thêm hoạt động <strong>' . e($title) . '</strong> vào lịch trình.');
            }
        }
    }

    // 4. Sắp xếp thứ tự hoạt động (UC07.11)
    elseif ($action === 'reorder_activity') {
        $actId = (int)($_POST['activity_id'] ?? 0);
        $direction = ($_POST['direction'] ?? 'up') === 'down' ? 'down' : 'up';

        $stmtCur = $pdo->prepare('SELECT id, trip_day_id, sort_order FROM activities WHERE id = ? LIMIT 1');
        $stmtCur->execute([$actId]);
        $curAct = $stmtCur->fetch();
        if ($curAct) {
            $stmtList = $pdo->prepare('SELECT id FROM activities WHERE trip_day_id = ? ORDER BY sort_order ASC, start_time ASC, id ASC');
            $stmtList->execute([$curAct['trip_day_id']]);
            $orderedIds = array_map('intval', $stmtList->fetchAll(PDO::FETCH_COLUMN));

            $idx = array_search($actId, $orderedIds, true);
            if ($idx !== false) {
                $swapIdx = ($direction === 'up') ? $idx - 1 : $idx + 1;
                if (isset($orderedIds[$swapIdx])) {
                    $tmp = $orderedIds[$idx];
                    $orderedIds[$idx] = $orderedIds[$swapIdx];
                    $orderedIds[$swapIdx] = $tmp;
                }
                $updSort = $pdo->prepare('UPDATE activities SET sort_order = ? WHERE id = ?');
                foreach ($orderedIds as $pos => $idVal) {
                    $updSort->execute([$pos + 1, $idVal]);
                }
                set_flash('success', 'Đã cập nhật thứ tự sắp xếp hoạt động trong ngày (UC07.11).');
            }
        }
    }

    // 4b. Xóa hoạt động (UC07.10)
    elseif ($action === 'delete_activity') {
        $actId = (int)($_POST['activity_id'] ?? 0);
        $pdo->prepare('DELETE FROM activities WHERE id = ?')->execute([$actId]);
        set_flash('info', 'Đã xóa hoạt động khỏi lịch trình.');
    }

    // 5. Thêm hoặc Cập nhật Khoản chi (M6 - Bảng chuẩn tính tổng ngân sách)
    elseif ($action === 'save_expense') {
        $expId = (int)($_POST['expense_id'] ?? 0);
        $cat = $_POST['expense_category'] ?? 'other';
        $title = trim($_POST['title'] ?? '');
        $estAmount = max(0, (float)($_POST['estimated_amount'] ?? 0));
        $actAmount = max(0, (float)($_POST['actual_amount'] ?? 0));
        $expDate = trim($_POST['expense_date'] ?? $trip['start_date']);
        $notes = trim($_POST['notes'] ?? '');

        if ($title !== '') {
            if ($expId > 0) {
                $upd = $pdo->prepare('
                    UPDATE expenses
                    SET expense_category = ?, title = ?, estimated_amount = ?, actual_amount = ?, expense_date = ?, notes = ?
                    WHERE id = ? AND trip_id = ?
                ');
                $upd->execute([$cat, $title, $estAmount, $actAmount, $expDate, $notes, $expId, $tripId]);
                set_flash('success', 'Đã cập nhật khoản chi <strong>' . e($title) . '</strong>.');
            } else {
                $ins = $pdo->prepare('
                    INSERT INTO expenses (trip_id, expense_category, title, estimated_amount, actual_amount, expense_date, notes)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ');
                $ins->execute([$tripId, $cat, $title, $estAmount, $actAmount, $expDate, $notes]);
                set_flash('success', 'Đã thêm khoản chi mới vào bảng ngân sách.');
            }
        }
        $redirectTab = 'budget';
    }

    // 6. Xóa khoản chi
    elseif ($action === 'delete_expense') {
        $expId = (int)($_POST['expense_id'] ?? 0);
        $pdo->prepare('DELETE FROM expenses WHERE id = ? AND trip_id = ?')->execute([$expId, $tripId]);
        set_flash('info', 'Đã xóa khoản chi.');
        $redirectTab = 'budget';
    }

    // 7. Thêm thông tin Lưu trú (M6)
    elseif ($action === 'add_accommodation') {
        $locationId = !empty($_POST['location_id']) ? (int)$_POST['location_id'] : null;
        $stayName = trim($_POST['stay_name'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $checkinDate = trim($_POST['checkin_date'] ?? $trip['start_date']);
        $checkoutDate = trim($_POST['checkout_date'] ?? $trip['end_date']);
        $roomCount = max(1, (int)($_POST['room_count'] ?? 1));
        $estCost = max(0, (float)($_POST['estimated_cost'] ?? 0));
        $actCost = max(0, (float)($_POST['actual_cost'] ?? 0));
        $contact = trim($_POST['contact_info'] ?? '');
        $bookingCode = trim($_POST['booking_code'] ?? '');
        $bookingStatus = ($_POST['booking_status'] ?? 'planned') === 'booked' ? 'booked' : 'planned';
        $notes = trim($_POST['notes'] ?? '');
        $syncExpense = isset($_POST['sync_to_expense']) && (int)$_POST['sync_to_expense'] === 1;

        if ($locationId && $stayName === '') {
            $stmtL = $pdo->prepare('SELECT name, address, contact_info FROM locations WHERE id = ?');
            $stmtL->execute([$locationId]);
            $loc = $stmtL->fetch();
            if ($loc) {
                $stayName = $loc['name'];
                if ($address === '') $address = $loc['address'];
                if ($contact === '') $contact = $loc['contact_info'];
            }
        }

        if ($stayName !== '') {
            $insAcc = $pdo->prepare('
                INSERT INTO accommodations (
                    trip_id, location_id, stay_name, address,
                    checkin_date, checkin_time, checkout_date, checkout_time,
                    room_count, estimated_cost, actual_cost, contact_info, booking_code, booking_status, notes
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ');
            $insAcc->execute([
                $tripId, $locationId, $stayName, $address,
                $checkinDate, '14:00', $checkoutDate, '12:00',
                $roomCount, $estCost, $actCost, $contact, $bookingCode, $bookingStatus, $notes
            ]);
            $newAccId = (int)$pdo->lastInsertId();

            if ($syncExpense && ($estCost > 0 || $actCost > 0)) {
                $insExp = $pdo->prepare('
                    INSERT INTO expenses (trip_id, expense_category, title, estimated_amount, actual_amount, linked_accommodation_id, expense_date, notes)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ');
                $insExp->execute([
                    $tripId, 'stay', 'Lưu trú: ' . $stayName . " ({$roomCount} phòng)",
                    $estCost, $actCost, $newAccId, $checkinDate, 'Liên kết tự động từ mục Lưu trú'
                ]);
            }
            set_flash('success', 'Đã lưu thông tin lưu trú <strong>' . e($stayName) . '</strong>.');
        }
        $redirectTab = 'stay_transport';
    }

    elseif ($action === 'delete_accommodation') {
        $accId = (int)($_POST['accommodation_id'] ?? 0);
        $pdo->prepare('DELETE FROM accommodations WHERE id = ? AND trip_id = ?')->execute([$accId, $tripId]);
        set_flash('info', 'Đã xóa mục lưu trú.');
        $redirectTab = 'stay_transport';
    }

    // 8. Thêm thông tin Phương tiện (M6)
    elseif ($action === 'add_transport') {
        $transType = trim($_POST['transport_type'] ?? 'Xe limousine');
        $depPoint = trim($_POST['departure_point'] ?? '');
        $arrPoint = trim($_POST['arrival_point'] ?? '');
        $depTime = trim($_POST['departure_datetime'] ?? '');
        $arrTime = trim($_POST['arrival_datetime'] ?? '');
        $estCost = max(0, (float)($_POST['estimated_cost'] ?? 0));
        $actCost = max(0, (float)($_POST['actual_cost'] ?? 0));
        $bookingCode = trim($_POST['booking_code'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        $syncExpense = isset($_POST['sync_to_expense']) && (int)$_POST['sync_to_expense'] === 1;

        if ($depPoint !== '' && $arrPoint !== '') {
            $insTr = $pdo->prepare('
                INSERT INTO transports (
                    trip_id, transport_type, departure_point, arrival_point,
                    departure_datetime, arrival_datetime, estimated_cost, actual_cost, booking_code, notes
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ');
            $insTr->execute([
                $tripId, $transType, $depPoint, $arrPoint,
                $depTime ?: null, $arrTime ?: null, $estCost, $actCost, $bookingCode, $notes
            ]);
            $newTrId = (int)$pdo->lastInsertId();

            if ($syncExpense && ($estCost > 0 || $actCost > 0)) {
                $insExp = $pdo->prepare('
                    INSERT INTO expenses (trip_id, expense_category, title, estimated_amount, actual_amount, linked_transport_id, expense_date, notes)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ');
                $insExp->execute([
                    $tripId, 'transport', $transType . ': ' . $depPoint . ' → ' . $arrPoint,
                    $estCost, $actCost, $newTrId, $trip['start_date'], 'Liên kết tự động từ mục Phương tiện'
                ]);
            }
            set_flash('success', 'Đã thêm thông tin phương tiện di chuyển.');
        }
        $redirectTab = 'stay_transport';
    }

    elseif ($action === 'delete_transport') {
        $trId = (int)($_POST['transport_id'] ?? 0);
        $pdo->prepare('DELETE FROM transports WHERE id = ? AND trip_id = ?')->execute([$trId, $tripId]);
        set_flash('info', 'Đã xóa mục phương tiện.');
        $redirectTab = 'stay_transport';
    }

    // 9. Quản lý Checklist chuẩn bị (M7 - UC08.9 -> UC08.14)
    elseif ($action === 'add_checklist' || $action === 'save_checklist') {
        $itemId = (int)($_POST['item_id'] ?? 0);
        $itemName = trim($_POST['item_name'] ?? '');
        $category = trim($_POST['category'] ?? 'Đồ dùng cá nhân');
        if ($itemName !== '') {
            if ($itemId > 0) {
                // UC08.11: Chỉnh sửa mục checklist
                $upd = $pdo->prepare('UPDATE checklists SET item_name = ?, category = ? WHERE id = ? AND trip_id = ?');
                $upd->execute([$itemName, $category, $itemId, $tripId]);
                set_flash('success', 'Đã cập nhật mục Checklist (UC08.11).');
            } else {
                // UC08.10: Thêm mục checklist mới
                $ins = $pdo->prepare('INSERT INTO checklists (trip_id, item_name, category, is_completed, sort_order) VALUES (?, ?, ?, 0, 10)');
                $ins->execute([$tripId, $itemName, $category]);
                set_flash('success', 'Đã thêm mục chuẩn bị vào Checklist.');
            }
        }
        $redirectTab = 'checklist';
    }

    elseif ($action === 'toggle_checklist') {
        $itemId = (int)($_POST['item_id'] ?? 0);
        $pdo->prepare('UPDATE checklists SET is_completed = CASE WHEN is_completed = 1 THEN 0 ELSE 1 END WHERE id = ? AND trip_id = ?')
            ->execute([$itemId, $tripId]);
        $redirectTab = 'checklist';
    }

    elseif ($action === 'delete_checklist') {
        $itemId = (int)($_POST['item_id'] ?? 0);
        $pdo->prepare('DELETE FROM checklists WHERE id = ? AND trip_id = ?')->execute([$itemId, $tripId]);
        $redirectTab = 'checklist';
    }

    // 10. Bật / Tắt liên kết chia sẻ chỉ đọc (M7 - Mục 4.12)
    elseif ($action === 'toggle_share') {
        $newState = (int)$trip['is_shared'] === 1 ? 0 : 1;
        $token = $trip['share_token'] ?: ('bacdu-' . substr(bin2hex(random_bytes(6)), 0, 10));
        $pdo->prepare('UPDATE trips SET is_shared = ?, share_token = ? WHERE id = ?')
            ->execute([$newState, $token, $tripId]);
        set_flash(
            $newState ? 'success' : 'info',
            $newState
                ? 'Đã BẬT liên kết chia sẻ chuyến đi chỉ đọc! Người có liên kết có thể xem lịch trình nhưng không thấy mã đặt chỗ và ghi chú riêng tư.'
                : 'Đã TẮT liên kết chia sẻ chuyến đi. Chuyến đi hiện ở chế độ riêng tư.'
        );
        $redirectTab = 'share';
    }

    header('Location: trip_detail.php?id=' . $tripId . '&tab=' . urlencode($redirectTab));
    exit;
}

// TẢI DỮ LIỆU HIỂN THỊ TRIP DASHBOARD
$timelineStatus = trip_timeline_status($trip['start_date'], $trip['end_date']);
$budgetSummary = calculate_trip_budget_summary($pdo, $tripId, (float)$trip['total_budget']);

// Danh sách tất cả địa điểm trong hệ thống để chọn nhanh khi thêm Hoạt động / Lưu trú
$allLocations = $pdo->query("
    SELECT l.id, l.name, l.location_type, l.ref_price, l.price_unit, p.name AS province_name
    FROM locations l
    JOIN provinces p ON p.id = l.province_id
    WHERE l.status = 'active'
    ORDER BY l.province_id ASC, l.location_type ASC, l.name ASC
")->fetchAll();

// Các ngày và hoạt động của chuyến đi
$stmtDays = $pdo->prepare('SELECT * FROM trip_days WHERE trip_id = ? ORDER BY day_number ASC');
$stmtDays->execute([$tripId]);
$tripDays = $stmtDays->fetchAll();

$stmtActs = $pdo->prepare('
    SELECT
        a.*,
        l.name AS location_name,
        l.location_type,
        l.latitude,
        l.longitude,
        l.google_maps_url,
        (SELECT id FROM expenses e WHERE e.linked_activity_id = a.id LIMIT 1) AS linked_expense_id
    FROM activities a
    LEFT JOIN locations l ON l.id = a.location_id
    WHERE a.trip_day_id = ?
    ORDER BY a.sort_order ASC, a.start_time ASC, a.id ASC
');

$daysData = [];
$mapPoints = [];
$totalActivitiesCount = 0;

foreach ($tripDays as $td) {
    $stmtActs->execute([$td['id']]);
    $acts = $stmtActs->fetchAll();
    $td['activities'] = $acts;
    $daysData[] = $td;
    $totalActivitiesCount += count($acts);

    foreach ($acts as $act) {
        if (!empty($act['latitude']) && !empty($act['longitude'])) {
            $mapPoints[] = [
                'day_number'      => (int)$td['day_number'],
                'location_id'     => (int)$act['location_id'],
                'title'           => $act['title'],
                'subtitle'        => 'Ngày ' . (int)$td['day_number'] . ' (' . $act['start_time'] . ') · ' . ($act['location_name'] ?? ''),
                'time'            => $act['start_time'],
                'type'            => $act['location_type'] ?: $act['activity_type'],
                'lat'             => (float)$act['latitude'],
                'lng'             => (float)$act['longitude'],
                'google_maps_url' => google_maps_view_url($act['latitude'], $act['longitude'], $act['google_maps_url']),
            ];
        }
    }
}

// Danh sách khoản chi (Expenses)
$stmtExps = $pdo->prepare('
    SELECT
        e.*,
        a.title AS linked_activity_title,
        ac.stay_name AS linked_acc_name,
        tr.transport_type AS linked_trans_name
    FROM expenses e
    LEFT JOIN activities a ON a.id = e.linked_activity_id
    LEFT JOIN accommodations ac ON ac.id = e.linked_accommodation_id
    LEFT JOIN transports tr ON tr.id = e.linked_transport_id
    WHERE e.trip_id = ?
    ORDER BY e.id ASC
');
$stmtExps->execute([$tripId]);
$expensesList = $stmtExps->fetchAll();

// Danh sách Lưu trú & Phương tiện
$stmtAccs = $pdo->prepare('SELECT * FROM accommodations WHERE trip_id = ? ORDER BY checkin_date ASC, id ASC');
$stmtAccs->execute([$tripId]);
$accommodationsList = $stmtAccs->fetchAll();

$stmtTrans = $pdo->prepare('SELECT * FROM transports WHERE trip_id = ? ORDER BY id ASC');
$stmtTrans->execute([$tripId]);
$transportsList = $stmtTrans->fetchAll();

// Danh sách Checklist
$stmtChecks = $pdo->prepare('SELECT * FROM checklists WHERE trip_id = ? ORDER BY is_completed ASC, sort_order ASC, id ASC');
$stmtChecks->execute([$tripId]);
$checklists = $stmtChecks->fetchAll();
$checklistTotal = count($checklists);
$checklistDone = count(array_filter($checklists, fn($c) => (int)$c['is_completed'] === 1));
$checklistPercent = $checklistTotal > 0 ? round(($checklistDone / $checklistTotal) * 100) : 0;

// URL chia sẻ chỉ đọc
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$baseDir = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\');
$shareUrl = $scheme . '://' . $host . ($baseDir ? $baseDir : '') . '/share.php?token=' . urlencode((string)$trip['share_token']);

$pageTitle = 'Trip Dashboard: ' . $trip['title'];
require_once __DIR__ . '/includes/header.php';
?>

<?php
$statusMarkClass = $timelineStatus['code'] === 'ongoing' ? 'status-ok' : ($timelineStatus['code'] === 'upcoming' ? 'status-warn' : 'status-neutral');
$budgetCap = max(1, (float)$budgetSummary['total_budget']);
$actPct = min(100, round(($budgetSummary['total_actual'] / $budgetCap) * 100));
$estPct = max(0, min(100 - $actPct, round((max(0, $budgetSummary['total_estimated'] - $budgetSummary['total_actual']) / $budgetCap) * 100)));
$isBudgetOver = $budgetSummary['remaining_vs_actual'] < 0 || $budgetSummary['remaining_vs_estimated'] < 0;
$hasUnfinishedChecklist = $checklistDone < $checklistTotal;
?>

<!-- APP MODE SHIFT HEADER -->
<header class="app-shift-bar mb-4">
  <div class="container">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
      <div>
        <div class="app-shift-eyebrow mb-1">
          <span><i class="bi bi-compass text-warning me-1"></i>BÀN ĐIỀU PHỐI LỘ TRÌNH · APP MODE</span>
          <span>·</span>
          <span>HỒ SƠ #<?= str_pad((string)$trip['id'], 3, '0', STR_PAD_LEFT) ?></span>
          <?php if (!empty($trip['source_template_id'])): ?>
            <span>·</span>
            <a href="template_detail.php?id=<?= (int)$trip['source_template_id'] ?>" class="text-warning">
              <i class="bi bi-magic me-1"></i>GỐC MẪU: <?= e($trip['source_template_title']) ?>
            </a>
          <?php endif; ?>
        </div>

        <h1 class="app-shift-title"><?= e($trip['title']) ?></h1>

        <div class="d-flex flex-wrap align-items-center gap-3 small text-white-50 mt-1">
          <span class="status-mark <?= $statusMarkClass ?>"><?= e($timelineStatus['label']) ?></span>
          <span><i class="bi bi-geo-alt-fill text-warning me-1"></i><strong class="text-white"><?= e($trip['main_destination']) ?></strong> (từ <?= e($trip['departure_point']) ?>)</span>
          <span class="font-monospace"><i class="bi bi-calendar3 text-info me-1"></i><strong class="text-white"><?= format_date_vn($trip['start_date']) ?> &rarr; <?= format_date_vn($trip['end_date']) ?></strong> (<?= count($daysData) ?> ngày)</span>
          <span class="font-monospace"><i class="bi bi-people-fill text-warning me-1"></i><strong class="text-white"><?= (int)$trip['pax_count'] ?> người</strong></span>
          <span class="status-mark <?= (int)$trip['is_shared'] === 1 ? 'status-ok' : 'status-neutral' ?>">
            <?= (int)$trip['is_shared'] === 1 ? 'Đang bật chia sẻ chỉ đọc' : 'Chế độ riêng tư' ?>
          </span>
        </div>
      </div>

      <div class="d-flex flex-wrap gap-2">
        <button type="button" class="btn-cin-ghost py-1 px-3" onclick="window.BacDuApp && window.BacDuApp.openPalette()" data-key="/">
          <i class="bi bi-terminal"></i> Lệnh nhanh
        </button>
        <button type="button" class="btn-cin-ghost py-1 px-3" data-bs-toggle="modal" data-bs-target="#editTripModal" data-key="i" data-palette-label="Sửa thông tin & Ngày đi/về">
          <i class="bi bi-sliders"></i> Sửa chuyến đi
        </button>
        <?php if ((int)$trip['is_shared'] === 1): ?>
          <a href="share.php?token=<?= urlencode((string)$trip['share_token']) ?>" target="_blank" class="btn-cin-amber py-1 px-3">
            <i class="bi bi-box-arrow-up-right"></i> Xem bản Chia sẻ
          </a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</header>

<main class="container pb-5" id="mainContent">
  <!-- ========================================================================
       SIGNATURE SET A (APP MODE): ROUTE STRIP & ANTI-DOUBLE-COUNT LEDGER BRIDGE
       Replaces generic 4-card KPI row with a bespoke expedition instrument
       ======================================================================== -->
  <section class="app-signature-set <?= ($isBudgetOver || $hasUnfinishedChecklist) ? 'is-lit' : '' ?>" id="expeditionLedgerSet" aria-label="Bảng tổng hợp lộ trình và đối soát ngân sách">
    <!-- 1. THE ONE LIT REGION: Only illuminates when human action is required -->
    <?php if ($isBudgetOver): ?>
      <div class="app-lit-callout mb-3">
        <div>
          <span class="status-mark status-warn me-2">CẦN ĐIỀU CHỈNH NGÂN SÁCH</span>
          <span class="small fw-semibold">
            Dự toán hoặc chi phí thực tế đang vượt trần ngân sách thiết lập (Chênh lệch: <strong class="font-monospace text-danger"><?= format_vnd($budgetSummary['remaining_vs_estimated']) ?></strong>).
          </span>
        </div>
        <a href="trip_detail.php?id=<?= $tripId ?>&tab=budget" class="btn-cin-amber py-1 px-3">Rà soát khoản chi ngay</a>
      </div>
    <?php elseif ($hasUnfinishedChecklist): ?>
      <div class="app-lit-callout mb-3">
        <div>
          <span class="status-mark status-warn me-2">CẦN HOÀN TẤT CHUẨN BỊ</span>
          <span class="small">
            Còn <strong><?= $checklistTotal - $checklistDone ?>/<?= $checklistTotal ?> mục</strong> trong Checklist hành lý chưa đánh dấu hoàn thành trước giờ khởi hành.
          </span>
        </div>
        <a href="trip_detail.php?id=<?= $tripId ?>&tab=checklist" class="btn-cin-ink py-1 px-3">Mở Checklist (<?= $checklistPercent ?>%)</a>
      </div>
    <?php else: ?>
      <div class="app-calm-callout mb-3">
        <span><span class="status-mark status-ok me-2">SẴN SÀNG KHỞI HÀNH</span> Ngân sách nằm trong hạn mức an toàn và toàn bộ <?= $checklistTotal ?> mục Checklist đã hoàn tất.</span>
        <span class="font-monospace small">CHỐNG TÍNH TRÙNG: ĐANG BẬT</span>
      </div>
    <?php endif; ?>

    <div class="row g-3 align-items-center">
      <!-- 2. DAY-BY-DAY EXPEDITION ROUTE STRIP -->
      <div class="col-lg-7">
        <div class="d-flex justify-content-between align-items-center mb-1">
          <span class="cin-eyebrow text-dark">Trục ngày hành trình (<?= count($daysData) ?> ngày · <?= $totalActivitiesCount ?> điểm dừng)</span>
          <span class="small font-monospace text-muted">Nhấp để lọc bản đồ & cuộn tới ngày</span>
        </div>
        <div class="route-strip-nodes">
          <?php foreach ($daysData as $dNode): ?>
            <a href="trip_detail.php?id=<?= $tripId ?>&tab=itinerary#day-block-<?= (int)$dNode['day_number'] ?>"
               class="route-day-node"
               onclick="if (typeof BacDuMap !== 'undefined') BacDuMap.renderMarkers('<?= (int)$dNode['day_number'] ?>', 'all');">
              <div class="d-flex justify-content-between align-items-center">
                <span class="r-num">NGÀY <?= str_pad((string)$dNode['day_number'], 2, '0', STR_PAD_LEFT) ?></span>
                <span class="r-date"><?= format_date_vn($dNode['trip_date']) ?></span>
              </div>
              <div class="r-title"><?= e($dNode['title']) ?></div>
              <div class="small font-monospace text-muted mt-1" style="font-size:0.68rem;">
                <?= count($dNode['activities']) ?> hoạt động
              </div>
            </a>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- 3. ANTI-DOUBLE-COUNT BUDGET RECONCILIATION BRIDGE -->
      <div class="col-lg-5 border-start-lg ps-lg-3">
        <div class="d-flex justify-content-between align-items-baseline mb-1">
          <span class="cin-eyebrow text-dark">Đối soát ngân sách (Chống cộng trùng)</span>
          <span class="font-monospace small text-muted"><?= count($accommodationsList) ?> lưu trú · <?= count($transportsList) ?> xe</span>
        </div>
        <div class="row g-2 text-end font-monospace">
          <div class="col-4">
            <div class="text-muted" style="font-size:0.68rem;">NGÂN SÁCH TRẦN</div>
            <div class="fw-bold text-dark num-col"><?= format_vnd($budgetSummary['total_budget']) ?></div>
          </div>
          <div class="col-4">
            <div class="text-muted" style="font-size:0.68rem;">DỰ TOÁN LIÊN KẾT</div>
            <div class="fw-bold text-primary num-col"><?= format_vnd($budgetSummary['total_estimated']) ?></div>
          </div>
          <div class="col-4">
            <div class="text-muted" style="font-size:0.68rem;">THỰC CHI / CÒN LẠI</div>
            <div class="fw-bold <?= $budgetSummary['remaining_vs_actual'] >= 0 ? 'text-success' : 'text-danger' ?> num-col">
              <?= format_vnd($budgetSummary['total_actual']) ?>
            </div>
          </div>
        </div>
        <div class="budget-bridge-meter" title="Thực chi: <?= format_vnd($budgetSummary['total_actual']) ?> | Dự toán: <?= format_vnd($budgetSummary['total_estimated']) ?>">
          <div class="budget-bridge-fill-actual" style="width: <?= $actPct ?>%"></div>
          <div class="budget-bridge-fill-est" style="width: <?= $estPct ?>%"></div>
        </div>
        <div class="d-flex justify-content-between small font-monospace text-muted" style="font-size:0.7rem;">
          <span>Còn lại so với thực chi: <strong class="<?= $budgetSummary['remaining_vs_actual'] >= 0 ? 'text-success' : 'text-danger' ?>"><?= format_vnd($budgetSummary['remaining_vs_actual']) ?></strong></span>
          <span>Checklist: <strong><?= $checklistDone ?>/<?= $checklistTotal ?></strong> (<?= $checklistPercent ?>%)</span>
        </div>
      </div>
    </div>
  </section>

  <!-- THANH ĐIỀU HƯỚNG CÁC PHÂN KHU TRIP DASHBOARD (Phím 1-5) -->
  <nav class="app-view-tabs" role="tablist" aria-label="Các phân khu quản lý chuyến đi">
    <a class="app-view-tab <?= $activeTab === 'itinerary' ? 'active' : '' ?>"
       href="trip_detail.php?id=<?= $tripId ?>&tab=itinerary" data-key="1">
      <i class="bi bi-calendar2-week"></i> 1. Lịch trình & Bản đồ (<?= count($daysData) ?> ngày)
    </a>
    <a class="app-view-tab <?= $activeTab === 'budget' ? 'active' : '' ?>"
       href="trip_detail.php?id=<?= $tripId ?>&tab=budget" data-key="2">
      <i class="bi bi-wallet2"></i> 2. Ngân sách & Khoản chi (<?= count($expensesList) ?>)
    </a>
    <a class="app-view-tab <?= $activeTab === 'stay_transport' ? 'active' : '' ?>"
       href="trip_detail.php?id=<?= $tripId ?>&tab=stay_transport" data-key="3">
      <i class="bi bi-house-heart"></i> 3. Lưu trú & Phương tiện
    </a>
    <a class="app-view-tab <?= $activeTab === 'checklist' ? 'active' : '' ?>"
       href="trip_detail.php?id=<?= $tripId ?>&tab=checklist" data-key="4">
      <i class="bi bi-check2-square"></i> 4. Checklist (<?= $checklistDone ?>/<?= $checklistTotal ?>)
    </a>
    <a class="app-view-tab <?= $activeTab === 'share' ? 'active' : '' ?>"
       href="trip_detail.php?id=<?= $tripId ?>&tab=share" data-key="5">
      <i class="bi bi-share"></i> 5. Chia sẻ chỉ đọc
    </a>
  </nav>

  <!-- ========================================================================
       TAB 1: LỊCH TRÌNH CÁ NHÂN THEO NGÀY & BẢN ĐỒ LEAFLET (M4 & M5)
       ======================================================================== -->
  <?php if ($activeTab === 'itinerary'): ?>
    <div class="row g-4">
      <div class="col-lg-7">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
          <div>
            <span class="cin-eyebrow text-dark">Điều phối hoạt động (Phím j/k chọn · Enter sửa)</span>
            <h4 class="cin-display-h2 fs-3 mb-0">Timeline lịch trình theo ngày</h4>
          </div>
          <div class="d-flex gap-2">
            <button type="button" class="btn-cin-outline py-1 px-3" data-bs-toggle="modal" data-bs-target="#addDayModal" data-key="d" data-palette-label="Thêm ngày mới vào chuyến đi">
              <i class="bi bi-calendar-plus"></i> + Thêm ngày
            </button>
            <button type="button" class="btn-cin-amber py-1 px-3" data-bs-toggle="modal" data-bs-target="#addActivityModal" data-key="a" data-palette-label="Thêm hoạt động mới vào ngày">
              <i class="bi bi-plus-circle"></i> + Thêm hoạt động
            </button>
          </div>
        </div>

        <?php foreach ($daysData as $day): ?>
          <div class="cin-day-dossier" id="day-block-<?= (int)$day['day_number'] ?>">
            <div class="cin-day-header">
              <div>
                <span class="cin-day-stamp me-1">NGÀY <?= str_pad((string)$day['day_number'], 2, '0', STR_PAD_LEFT) ?></span>
                <span class="slip-tag">
                  <i class="bi bi-calendar3 me-1"></i><?= format_date_vn($day['trip_date']) ?>
                </span>
                <h5 class="field-slip-title mt-2 mb-0"><?= e($day['title']) ?></h5>
                <?php if (!empty($day['notes'])): ?>
                  <div class="small text-muted mt-1"><i class="bi bi-info-circle me-1"></i><?= e($day['notes']) ?></div>
                <?php endif; ?>
              </div>
              <button type="button" class="btn-cin-outline py-1 px-2"
                      onclick="openAddActivityForDay(<?= (int)$day['id'] ?>)">
                <i class="bi bi-plus-lg"></i> Thêm HĐ
              </button>
            </div>

            <?php if (empty($day['activities'])): ?>
              <div class="text-center py-3 bg-light rounded-2 small text-muted">
                Chưa có hoạt động nào trong ngày này. Nhấn "Thêm HĐ" để bổ sung.
              </div>
            <?php else: ?>
              <div class="timeline-container">
                <?php foreach ($day['activities'] as $act): ?>
                  <?php $actMeta = activity_type_meta($act['activity_type']); ?>
                  <div class="timeline-item <?= e($actMeta['class']) ?>">
                    <div class="timeline-dot"><i class="bi <?= e($actMeta['icon']) ?>"></i></div>
                    <div class="cin-activity-slip">
                      <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-1">
                        <div>
                          <span class="slip-tag me-1"><?= e($act['start_time']) ?> - <?= e($act['end_time']) ?></span>
                          <span class="badge bg-light text-dark border rounded-1 font-monospace"><?= e($actMeta['label']) ?></span>
                          <?php if (!empty($act['linked_expense_id'])): ?>
                            <span class="status-mark status-ok ms-1" title="Đã liên kết với bảng Khoản chi để không tính trùng">
                              Đã liên kết Khoản chi
                            </span>
                          <?php endif; ?>
                        </div>
                        <div class="d-flex align-items-center gap-1">
                          <span class="price-mono small me-1"><?= format_vnd($act['estimated_cost'], true) ?></span>
                          <!-- UC07.11: Sắp xếp thứ tự hoạt động Lên / Xuống -->
                          <form action="trip_detail.php?id=<?= $tripId ?>" method="post" class="m-0 d-inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="reorder_activity">
                            <input type="hidden" name="activity_id" value="<?= (int)$act['id'] ?>">
                            <input type="hidden" name="direction" value="up">
                            <button type="submit" class="btn btn-sm btn-light border py-0 px-1" title="Chuyển lên (UC07.11)"><i class="bi bi-arrow-up"></i></button>
                          </form>
                          <form action="trip_detail.php?id=<?= $tripId ?>" method="post" class="m-0 d-inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="reorder_activity">
                            <input type="hidden" name="activity_id" value="<?= (int)$act['id'] ?>">
                            <input type="hidden" name="direction" value="down">
                            <button type="submit" class="btn btn-sm btn-light border py-0 px-1" title="Chuyển xuống (UC07.11)"><i class="bi bi-arrow-down"></i></button>
                          </form>
                          <!-- UC07.9: Chỉnh sửa hoạt động -->
                          <button type="button" class="btn-cin-outline py-0 px-2"
                                  title="Chỉnh sửa hoạt động (UC07.9)"
                                  onclick='editActivityModal(<?= json_encode($act, JSON_UNESCAPED_UNICODE) ?>)'>
                            <i class="bi bi-pencil"></i>
                          </button>
                          <!-- UC07.10: Xóa hoạt động -->
                          <form action="trip_detail.php?id=<?= $tripId ?>" method="post" class="m-0 d-inline" onsubmit="return confirm('Xóa hoạt động này?');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete_activity">
                            <input type="hidden" name="activity_id" value="<?= (int)$act['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger rounded-1 py-0 px-2" title="Xóa hoạt động"><i class="bi bi-trash"></i></button>
                          </form>
                        </div>
                      </div>

                      <h6 class="fw-bold font-sans-title mt-1 mb-1"><?= e($act['title']) ?></h6>
                      <?php if (!empty($act['description'])): ?>
                        <p class="small text-secondary mb-2"><?= e($act['description']) ?></p>
                      <?php endif; ?>
                      <?php if (!empty($act['notes'])): ?>
                        <div class="small text-muted mb-2"><i class="bi bi-sticky me-1 text-warning"></i><?= e($act['notes']) ?></div>
                      <?php endif; ?>

                      <?php if (!empty($act['location_id'])): ?>
                        <div class="pt-2 border-top d-flex flex-wrap justify-content-between align-items-center gap-2 small">
                          <a href="location_detail.php?id=<?= (int)$act['location_id'] ?>" class="fw-semibold text-success">
                            <i class="bi bi-geo-alt-fill me-1"></i><?= e($act['location_name']) ?>
                          </a>
                          <div class="d-flex gap-1">
                            <a href="<?= e(google_maps_view_url($act['latitude'], $act['longitude'], $act['google_maps_url'])) ?>"
                               target="_blank" rel="noopener" class="btn-cin-outline py-0 px-2" style="font-size:0.75rem;">
                              <i class="bi bi-geo-alt-fill text-danger"></i> Google Maps
                            </a>
                            <a href="<?= e(google_maps_directions_url($act['latitude'], $act['longitude'])) ?>"
                               target="_blank" rel="noopener" class="btn-cin-outline py-0 px-2" style="font-size:0.75rem;">
                              <i class="bi bi-sign-turn-right-fill text-primary"></i> Chỉ đường
                            </a>
                          </div>
                        </div>
                      <?php endif; ?>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>

      <!-- CỘT PHẢI TAB 1: BẢN ĐỒ LEAFLET ĐIỂM DỪNG THEO NGÀY -->
      <div class="col-lg-5">
        <div class="cin-map-frame sticky-top" style="top: 86px; z-index: 10;">
          <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <div>
              <span class="cin-eyebrow text-dark">Tọa độ thực địa</span>
              <h5 class="field-slip-title mb-0">Bản đồ chuyến đi</h5>
            </div>
            <div class="d-flex flex-wrap gap-1">
              <button type="button" class="btn-cin-ink py-1 px-2" style="font-size:0.75rem;" onclick="BacDuMap.renderMarkers('all', 'all')">Tất cả</button>
              <?php foreach ($daysData as $dBtn): ?>
                <button type="button" class="btn-cin-outline py-1 px-2" style="font-size:0.75rem;" onclick="BacDuMap.renderMarkers('<?= (int)$dBtn['day_number'] ?>', 'all')">
                  N<?= (int)$dBtn['day_number'] ?>
                </button>
              <?php endforeach; ?>
            </div>
          </div>

          <div id="tripItineraryMap" class="leaflet-map-box mb-3" style="height: 380px;"></div>

          <div class="field-spec-box mb-0">
            <div class="fw-bold text-dark mb-1"><i class="bi bi-compass text-success me-1"></i>Hướng dẫn bản đồ:</div>
            Nhấp vào bất kỳ điểm dừng nào trên bản đồ để mở <strong>Google Maps</strong> hoặc <strong>Chỉ đường</strong> trực tiếp.
          </div>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <!-- ========================================================================
       TAB 2: NGÂN SÁCH & KHOẢN CHI CHỐNG TÍNH TRÙNG (M6)
       ======================================================================== -->
  <?php if ($activeTab === 'budget'): ?>
    <div class="cin-season-callout mb-4">
      <div class="d-flex align-items-center gap-2">
        <i class="bi bi-shield-check fs-4 text-warning"></i>
        <div class="small">
          <strong class="text-warning">Quy tắc chống tính trùng ngân sách (Mục 4.9 Đặc tả):</strong>
          Bảng <strong>Khoản chi</strong> dưới đây là nguồn duy nhất dùng để tính Tổng dự toán và Tổng thực tế đã chi của chuyến đi. Chi phí của Hoạt động, Lưu trú hoặc Phương tiện được liên kết trực tiếp vào khoản chi tương ứng, tuyệt đối không cộng chồng giá tham khảo hai lần.
        </div>
      </div>
    </div>

    <div class="row g-4">
      <div class="col-lg-8">
        <div class="app-work-surface mb-4">
          <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
            <div>
              <span class="cin-eyebrow text-dark">Sổ cái đối soát (Tabular Numerals)</span>
              <h5 class="field-slip-title mb-0">Danh sách Khoản chi (Dự kiến vs Thực tế)</h5>
            </div>
            <button type="button" class="btn-cin-amber py-1 px-3" data-bs-toggle="modal" data-bs-target="#addExpenseModal" data-key="e" data-palette-label="Thêm khoản chi mới vào ngân sách">
              <i class="bi bi-plus-circle"></i> + Thêm khoản chi
            </button>
          </div>

          <div class="table-responsive">
            <table class="app-table">
              <thead>
                <tr>
                  <th>Nhóm & Nội dung khoản chi</th>
                  <th class="num-col">Dự kiến (VNĐ)</th>
                  <th class="num-col">Thực chi (VNĐ)</th>
                  <th class="num-col">Chênh lệch</th>
                  <th class="text-end">Thao tác</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($expensesList as $exp): ?>
                  <?php
                    $cMeta = expense_category_meta($exp['expense_category']);
                    $diff = (float)$exp['estimated_amount'] - (float)$exp['actual_amount'];
                  ?>
                  <tr class="<?= $diff < 0 ? 'row-needs-action' : '' ?>">
                    <td>
                      <span class="badge rounded-1 mb-1 font-monospace" style="background: <?= e($cMeta['color']) ?>;">
                        <i class="bi <?= e($cMeta['icon']) ?> me-1"></i><?= e($cMeta['label']) ?>
                      </span>
                      <div class="fw-bold text-dark"><?= e($exp['title']) ?></div>
                      <div class="text-muted" style="font-size: 0.74rem;">
                        <?php if (!empty($exp['linked_activity_title'])): ?>
                          <span class="text-success"><i class="bi bi-link-45deg"></i>HĐ: <?= e($exp['linked_activity_title']) ?></span> ·
                        <?php endif; ?>
                        <?php if (!empty($exp['linked_acc_name'])): ?>
                          <span class="text-primary"><i class="bi bi-link-45deg"></i>Lưu trú: <?= e($exp['linked_acc_name']) ?></span> ·
                        <?php endif; ?>
                        <?php if (!empty($exp['linked_trans_name'])): ?>
                          <span class="text-info"><i class="bi bi-link-45deg"></i>Xe: <?= e($exp['linked_trans_name']) ?></span> ·
                        <?php endif; ?>
                        <?= e($exp['notes']) ?>
                      </div>
                    </td>
                    <td class="num-col fw-semibold"><?= format_vnd($exp['estimated_amount']) ?></td>
                    <td class="num-col fw-bold text-success"><?= format_vnd($exp['actual_amount']) ?></td>
                    <td class="num-col <?= $diff >= 0 ? 'text-muted' : 'text-danger fw-bold' ?>">
                      <?= $diff >= 0 ? '+' . format_vnd($diff) : format_vnd($diff) ?>
                    </td>
                    <td class="text-end text-nowrap">
                      <button type="button" class="btn-cin-outline py-0 px-2"
                              onclick='editExpense(<?= json_encode($exp, JSON_UNESCAPED_UNICODE) ?>)'>
                        <i class="bi bi-pencil"></i>
                      </button>
                      <form action="trip_detail.php?id=<?= $tripId ?>" method="post" class="d-inline" onsubmit="return confirm('Xóa khoản chi này?');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete_expense">
                        <input type="hidden" name="expense_id" value="<?= (int)$exp['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger rounded-1 py-0 px-2"><i class="bi bi-trash"></i></button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
              <tfoot class="fw-bold border-top">
                <tr>
                  <td class="pt-2">TỔNG CỘNG TOÀN CHUYẾN ĐI</td>
                  <td class="num-col pt-2 text-primary fs-6"><?= format_vnd($budgetSummary['total_estimated']) ?></td>
                  <td class="num-col pt-2 text-success fs-6"><?= format_vnd($budgetSummary['total_actual']) ?></td>
                  <td class="num-col pt-2"><?= format_vnd($budgetSummary['total_estimated'] - $budgetSummary['total_actual']) ?></td>
                  <td></td>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>
      </div>

      <div class="col-lg-4">
        <div class="app-work-surface mb-4">
          <span class="cin-eyebrow text-dark">Cơ cấu 6 nhóm chi phí</span>
          <h5 class="field-slip-title mb-2">Phân bổ dự toán & thực chi</h5>
          <canvas id="budgetChart" height="220" class="mb-3"></canvas>
          <div class="d-flex flex-column gap-2 small">
            <?php foreach ($budgetSummary['by_category'] as $catRow): ?>
              <div class="d-flex justify-content-between align-items-center p-2 rounded-1 border bg-light">
                <span>
                  <i class="bi <?= e($catRow['icon']) ?> me-1" style="color:<?= e($catRow['color']) ?>"></i>
                  <strong><?= e($catRow['label']) ?></strong> (<?= (int)$catRow['items_count'] ?>)
                </span>
                <div class="num-col">
                  <div class="fw-bold"><?= format_vnd($catRow['sum_estimated']) ?></div>
                  <div class="text-success" style="font-size:0.72rem;">Thực: <?= format_vnd($catRow['sum_actual']) ?></div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <!-- ========================================================================
       TAB 3: THÔNG TIN LƯU TRÚ & PHƯƠNG TIỆN CỦA CHUYẾN ĐI (M6 - Mục 4.10)
       ======================================================================== -->
  <?php if ($activeTab === 'stay_transport'): ?>
    <div class="row g-4">
      <!-- LƯU TRÚ -->
      <div class="col-lg-6">
        <section class="cin-day-dossier h-100 mb-0">
          <div class="cin-day-header">
            <div>
              <span class="cin-day-badge me-2">LƯU TRÚ (<?= count($accommodationsList) ?>)</span>
              <span class="fw-bold" style="font-family: var(--font-display); font-size: 1.15rem;">Kế hoạch Homestay &amp; Khách sạn</span>
            </div>
            <button type="button" class="btn-cin-amber py-1 px-2" data-bs-toggle="modal" data-bs-target="#addAccommodationModal">
              <i class="bi bi-plus-lg"></i>Thêm nơi lưu trú
            </button>
          </div>

          <div class="cin-day-body">
            <?php if (empty($accommodationsList)): ?>
              <div class="cin-empty-state py-4 small text-muted">Chưa có thông tin lưu trú nào.</div>
            <?php else: ?>
              <div class="d-flex flex-column gap-3">
                <?php foreach ($accommodationsList as $acc): ?>
                  <article class="cin-activity-slip" data-kb-row="1">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                      <span class="status-mark <?= $acc['booking_status'] === 'booked' ? 'status-done' : 'status-warn' ?>">
                        <span class="status-shape <?= $acc['booking_status'] === 'booked' ? 'shape-circle' : 'shape-triangle' ?>"></span>
                        <?= $acc['booking_status'] === 'booked' ? 'Đã đặt phòng' : 'Dự kiến' ?>
                      </span>
                      <form action="trip_detail.php?id=<?= $tripId ?>" method="post" class="m-0" onsubmit="return confirm('Xóa thông tin lưu trú này?');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete_accommodation">
                        <input type="hidden" name="accommodation_id" value="<?= (int)$acc['id'] ?>">
                        <button type="submit" class="btn-cin-outline py-0 px-2 text-danger" title="Xóa"><i class="bi bi-trash"></i></button>
                      </form>
                    </div>
                    <h3 class="h6 fw-bold mb-1" style="font-family: var(--font-display); font-size: 1.12rem;"><?= e($acc['stay_name']) ?></h3>
                    <div class="small text-muted mb-2"><i class="bi bi-geo-alt me-1"></i><?= e($acc['address']) ?></div>
                    <div class="field-spec-box mb-2">
                      <div class="row g-2 small">
                        <div class="col-6"><strong>Check-in:</strong> <?= format_date_vn($acc['checkin_date']) ?> (<?= e($acc['checkin_time']) ?>)</div>
                        <div class="col-6"><strong>Check-out:</strong> <?= format_date_vn($acc['checkout_date']) ?> (<?= e($acc['checkout_time']) ?>)</div>
                        <div class="col-6"><strong>Số phòng:</strong> <?= (int)$acc['room_count'] ?> phòng</div>
                        <div class="col-6"><strong>Chi phí:</strong> <span class="price-mono"><?= format_vnd($acc['estimated_cost']) ?></span></div>
                        <?php if (!empty($acc['booking_code']) || !empty($acc['contact_info'])): ?>
                          <div class="col-12 border-top pt-1 text-secondary">
                            <i class="bi bi-shield-lock me-1"></i>Mã đặt chỗ (riêng tư): <code><?= e($acc['booking_code'] ?: '—') ?></code> · SĐT: <?= e($acc['contact_info'] ?: '—') ?>
                          </div>
                        <?php endif; ?>
                      </div>
                    </div>
                    <?php if (!empty($acc['notes'])): ?>
                      <div class="small text-muted"><i class="bi bi-sticky me-1"></i><?= e($acc['notes']) ?></div>
                    <?php endif; ?>
                  </article>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        </section>
      </div>

      <!-- PHƯƠNG TIỆN -->
      <div class="col-lg-6">
        <section class="cin-day-dossier h-100 mb-0">
          <div class="cin-day-header">
            <div>
              <span class="cin-day-badge me-2">DI CHUYỂN (<?= count($transportsList) ?>)</span>
              <span class="fw-bold" style="font-family: var(--font-display); font-size: 1.15rem;">Thông tin Phương tiện &amp; Vé xe</span>
            </div>
            <button type="button" class="btn-cin-amber py-1 px-2" data-bs-toggle="modal" data-bs-target="#addTransportModal">
              <i class="bi bi-plus-lg"></i>Thêm phương tiện
            </button>
          </div>

          <div class="cin-day-body">
            <?php if (empty($transportsList)): ?>
              <div class="cin-empty-state py-4 small text-muted">Chưa có thông tin phương tiện nào.</div>
            <?php else: ?>
              <div class="d-flex flex-column gap-3">
                <?php foreach ($transportsList as $tr): ?>
                  <article class="cin-activity-slip" data-kb-row="1">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                      <span class="status-mark status-info">
                        <i class="bi bi-bus-front"></i><?= e($tr['transport_type']) ?>
                      </span>
                      <form action="trip_detail.php?id=<?= $tripId ?>" method="post" class="m-0" onsubmit="return confirm('Xóa phương tiện này?');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete_transport">
                        <input type="hidden" name="transport_id" value="<?= (int)$tr['id'] ?>">
                        <button type="submit" class="btn-cin-outline py-0 px-2 text-danger" title="Xóa"><i class="bi bi-trash"></i></button>
                      </form>
                    </div>
                    <h3 class="h6 fw-bold mb-2" style="font-family: var(--font-display); font-size: 1.12rem;">
                      <?= e($tr['departure_point']) ?> <i class="bi bi-arrow-right mx-1 text-success"></i> <?= e($tr['arrival_point']) ?>
                    </h3>
                    <div class="field-spec-box mb-2">
                      <div class="row g-2 small">
                        <div class="col-6"><strong>Khởi hành:</strong> <?= e($tr['departure_datetime'] ?: 'Linh hoạt') ?></div>
                        <div class="col-6"><strong>Đến nơi:</strong> <?= e($tr['arrival_datetime'] ?: 'Linh hoạt') ?></div>
                        <div class="col-6"><strong>Dự kiến:</strong> <span class="price-mono"><?= format_vnd($tr['estimated_cost']) ?></span></div>
                        <div class="col-6"><strong>Thực tế:</strong> <span class="price-mono" style="color: var(--cin-jade);"><?= format_vnd($tr['actual_cost']) ?></span></div>
                        <?php if (!empty($tr['booking_code'])): ?>
                          <div class="col-12 border-top pt-1 text-secondary">
                            <i class="bi bi-shield-lock me-1"></i>Mã vé/đặt chỗ (riêng tư): <code><?= e($tr['booking_code']) ?></code>
                          </div>
                        <?php endif; ?>
                      </div>
                    </div>
                    <?php if (!empty($tr['notes'])): ?>
                      <div class="small text-muted"><i class="bi bi-sticky me-1"></i><?= e($tr['notes']) ?></div>
                    <?php endif; ?>
                  </article>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        </section>
      </div>
    </div>
  <?php endif; ?>

  <!-- ========================================================================
       TAB 4: CHECKLIST CHUẨN BỊ CHUYẾN ĐI (M7 - Mục 4.11)
       ======================================================================== -->
  <?php if ($activeTab === 'checklist'): ?>
    <div class="row g-4">
      <div class="col-lg-5">
        <section class="cin-day-dossier mb-0">
          <div class="cin-day-header">
            <span class="fw-bold" id="checklistFormTitle">
              <i class="bi bi-plus-square-fill text-success me-1"></i>Thêm / Cập nhật mục Checklist
            </span>
          </div>
          <div class="cin-day-body">
            <p class="small text-muted mb-3">Đánh dấu từng mục để không bỏ quên giấy tờ hay trang phục theo mùa miền Bắc.</p>

            <form action="trip_detail.php?id=<?= $tripId ?>" method="post">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="save_checklist">
              <input type="hidden" name="item_id" id="ck_item_id" value="0">

              <div class="mb-3">
                <label class="form-label small fw-bold">Tên đồ dùng / Công việc</label>
                <input type="text" name="item_name" id="ck_item_name" class="form-control" placeholder="VD: Áo giữ nhiệt, Thuốc say xe, Đặt cọc xe máy..." required>
              </div>

              <div class="mb-3">
                <label class="form-label small fw-bold">Nhóm chuẩn bị</label>
                <select name="category" id="ck_category" class="form-select">
                  <option value="Giấy tờ & Tiền mặt">Giấy tờ &amp; Tiền mặt</option>
                  <option value="Trang phục">Trang phục theo mùa</option>
                  <option value="Thiết bị điện tử">Thiết bị điện tử &amp; Máy ảnh</option>
                  <option value="Y tế cá nhân">Y tế &amp; Đồ dùng cá nhân</option>
                  <option value="Công việc trước chuyến đi">Công việc trước chuyến đi</option>
                </select>
              </div>

              <div class="d-flex gap-2">
                <button type="submit" class="btn-cin-amber flex-grow-1 justify-content-center" id="ck_submit_btn">
                  <i class="bi bi-plus-circle"></i>Lưu vào Checklist
                </button>
                <button type="button" class="btn-cin-outline d-none" id="ck_cancel_btn" onclick="resetChecklistForm()">
                  Hủy sửa
                </button>
              </div>
            </form>
          </div>
        </section>
      </div>

      <div class="col-lg-7">
        <section class="cin-day-dossier mb-0">
          <div class="cin-day-header">
            <span class="fw-bold"><i class="bi bi-list-check text-success me-1"></i>Danh sách chuẩn bị (<?= $checklistDone ?>/<?= $checklistTotal ?>)</span>
            <span class="status-mark status-done">
              <span class="status-shape shape-circle"></span><?= $checklistPercent ?>% hoàn tất
            </span>
          </div>
          <div class="cin-day-body">
            <div class="budget-bridge-meter mb-4" style="height: 8px;">
              <div class="budget-bridge-fill-actual" style="width: <?= $checklistPercent ?>%;"></div>
            </div>

            <div class="d-flex flex-column gap-2">
              <?php foreach ($checklists as $ck): ?>
                <article class="cin-activity-slip py-2 px-3 d-flex justify-content-between align-items-center" data-kb-row="1" style="<?= (int)$ck['is_completed'] === 1 ? 'opacity: 0.72;' : '' ?>">
                  <form action="trip_detail.php?id=<?= $tripId ?>" method="post" class="d-flex align-items-center gap-3 m-0 flex-grow-1">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="toggle_checklist">
                    <input type="hidden" name="item_id" value="<?= (int)$ck['id'] ?>">
                    <button type="submit" class="<?= (int)$ck['is_completed'] === 1 ? 'btn-cin-ink' : 'btn-cin-outline' ?> py-1 px-2" title="Đánh dấu hoàn thành">
                      <i class="bi <?= (int)$ck['is_completed'] === 1 ? 'bi-check-lg' : 'bi-square' ?>"></i>
                    </button>
                    <div>
                      <div class="fw-semibold <?= (int)$ck['is_completed'] === 1 ? 'text-decoration-line-through text-muted' : 'text-dark' ?>">
                        <?= e($ck['item_name']) ?>
                      </div>
                      <span class="slip-tag py-0 px-2" style="font-size:0.64rem;"><?= e($ck['category']) ?></span>
                    </div>
                  </form>

                  <div class="d-flex align-items-center gap-1">
                    <button type="button" class="btn-cin-outline py-1 px-2"
                            title="Sửa mục checklist (UC08.11)"
                            onclick='editChecklistItem(<?= json_encode($ck, JSON_UNESCAPED_UNICODE) ?>)'>
                      <i class="bi bi-pencil"></i>
                    </button>
                    <form action="trip_detail.php?id=<?= $tripId ?>" method="post" class="m-0">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="delete_checklist">
                      <input type="hidden" name="item_id" value="<?= (int)$ck['id'] ?>">
                      <button type="submit" class="btn-cin-outline py-1 px-2 text-danger" title="Xóa mục checklist"><i class="bi bi-trash"></i></button>
                    </form>
                  </div>
                </article>
              <?php endforeach; ?>
            </div>
          </div>
        </section>
      </div>
    </div>
  <?php endif; ?>

  <!-- ========================================================================
       TAB 5: CHIA SẺ CHUYẾN ĐI CHỈ ĐỌC (M7 - Mục 4.12)
       ======================================================================== -->
  <?php if ($activeTab === 'share'): ?>
    <div class="row justify-content-center">
      <div class="col-lg-8">
        <section class="cin-day-dossier mb-0">
          <div class="cin-day-header">
            <div class="d-flex align-items-center gap-2">
              <span class="cin-day-badge">CHIA SẺ · UC05</span>
              <span class="fw-bold" style="font-family: var(--font-display); font-size: 1.2rem;">Cấu hình Liên kết Chia sẻ Chỉ đọc</span>
            </div>
            <span class="status-mark <?= (int)$trip['is_shared'] === 1 ? 'status-done' : 'status-muted' ?>">
              <span class="status-shape <?= (int)$trip['is_shared'] === 1 ? 'shape-circle' : 'shape-square' ?>"></span>
              <?= (int)$trip['is_shared'] === 1 ? 'Đang bật công khai' : 'Chế độ Riêng tư' ?>
            </span>
          </div>

          <div class="cin-day-body p-4">
            <div class="field-spec-box mb-4 mt-0">
              <div class="fw-bold text-dark mb-1"><i class="bi bi-shield-check text-success me-1"></i>Quy tắc bảo mật khi chia sẻ chuyến đi (Mục 4.12 Đặc tả):</div>
              <ul class="mb-0 ps-3 small">
                <li>Người có liên kết chỉ có quyền <strong>xem kế hoạch lịch trình, bản đồ và tổng dự toán</strong> (không thể chỉnh sửa).</li>
                <li>Hệ thống tự động <strong>ẩn toàn bộ mã đặt chỗ (booking code), thông tin liên hệ cá nhân và ghi chú riêng tư</strong>.</li>
                <li>Bạn có thể tắt liên kết chia sẻ bất kỳ lúc nào.</li>
              </ul>
            </div>

            <?php if ((int)$trip['is_shared'] === 1): ?>
              <div class="mb-4">
                <label class="form-label small fw-bold">Liên kết chia sẻ chỉ đọc của chuyến đi:</label>
                <div class="d-flex flex-wrap gap-2">
                  <input type="text" id="shareLinkInput" class="form-control bg-light font-monospace flex-grow-1" style="max-width: 460px;" value="<?= e($shareUrl) ?>" readonly>
                  <button type="button" class="btn-cin-amber" onclick="copyToClipboard('shareLinkInput', this)">
                    <i class="bi bi-clipboard"></i>Sao chép liên kết
                  </button>
                  <a href="share.php?token=<?= urlencode((string)$trip['share_token']) ?>" target="_blank" class="btn-cin-ink">
                    <i class="bi bi-box-arrow-up-right"></i>Mở xem thử
                  </a>
                </div>
              </div>
            <?php endif; ?>

            <form action="trip_detail.php?id=<?= $tripId ?>" method="post">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="toggle_share">
              <button type="submit" class="<?= (int)$trip['is_shared'] === 1 ? 'btn-cin-outline text-danger' : 'btn-cin-amber' ?>">
                <i class="bi <?= (int)$trip['is_shared'] === 1 ? 'bi-slash-circle' : 'bi-share-fill' ?>"></i>
                <?= (int)$trip['is_shared'] === 1 ? 'Tắt liên kết chia sẻ (Chuyển về Riêng tư)' : 'Bật liên kết chia sẻ chỉ đọc' ?>
              </button>
            </form>
          </div>
        </section>
      </div>
    </div>
  <?php endif; ?>
</main>

<!-- MODAL SỬA THÔNG TIN CHUYẾN ĐI & NGÀY ĐI/VỀ -->
<div class="modal fade" id="editTripModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <span class="cin-eyebrow" style="color: var(--cin-amber-bright); font-size: 0.64rem;">ĐIỀU CHỈNH HỒ SƠ · UC07.5</span>
          <h5 class="modal-title mb-0">Cập nhật thông tin chuyến đi</h5>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form action="trip_detail.php?id=<?= $tripId ?>" method="post" class="row g-3">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="update_trip_info">
          <input type="hidden" name="redirect_tab" value="<?= e($activeTab) ?>">

          <div class="col-md-8">
            <label class="form-label">Tên chuyến đi</label>
            <input type="text" name="title" class="form-control" value="<?= e($trip['title']) ?>" required>
          </div>
          <div class="col-md-4">
            <label class="form-label">Số người</label>
            <input type="number" name="pax_count" class="form-control font-monospace" min="1" value="<?= (int)$trip['pax_count'] ?>" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">Điểm đến chính</label>
            <input type="text" name="main_destination" class="form-control" value="<?= e($trip['main_destination']) ?>" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">Điểm xuất phát</label>
            <input type="text" name="departure_point" class="form-control" value="<?= e($trip['departure_point']) ?>" required>
          </div>
          <div class="col-md-4">
            <label class="form-label">Ngày đi</label>
            <input type="date" name="start_date" class="form-control font-monospace" value="<?= e($trip['start_date']) ?>" required>
          </div>
          <div class="col-md-4">
            <label class="form-label">Ngày về</label>
            <input type="date" name="end_date" class="form-control font-monospace" value="<?= e($trip['end_date']) ?>" required>
          </div>
          <div class="col-md-4">
            <label class="form-label">Ngân sách tổng (VNĐ)</label>
            <input type="number" name="total_budget" class="form-control font-monospace" step="50000" value="<?= (float)$trip['total_budget'] ?>" required>
          </div>
          <div class="col-12">
            <label class="form-label">Mô tả chuyến đi</label>
            <textarea name="description" rows="2" class="form-control"><?= e($trip['description']) ?></textarea>
          </div>
          <div class="col-12">
            <div class="field-spec-box mt-0">
              <i class="bi bi-info-circle text-warning me-1"></i>Khi thay đổi ngày đi/về, hệ thống sẽ tự cập nhật ngày cho lịch trình và cảnh báo nếu có ngày vượt ngoài phạm vi mới (không tự xóa mất hoạt động của bạn).
            </div>
          </div>
          <div class="col-12">
            <button type="submit" class="btn-cin-amber w-100 justify-content-center py-2">
              <i class="bi bi-check2-circle"></i>Lưu cập nhật chuyến đi
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- MODAL THÊM NGÀY LỊCH TRÌNH -->
<div class="modal fade" id="addDayModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <span class="cin-eyebrow" style="color: var(--cin-amber-bright); font-size: 0.64rem;">MỞ RỘNG LỘ TRÌNH · UC07.7</span>
          <h5 class="modal-title mb-0">Thêm ngày vào lịch trình</h5>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form action="trip_detail.php?id=<?= $tripId ?>" method="post">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="add_trip_day">
          <div class="mb-3">
            <label class="form-label">Tiêu đề ngày mới</label>
            <input type="text" name="day_title" class="form-control" placeholder="VD: Ngày 4: Khám phá thêm điểm lân cận" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Ghi chú di chuyển trong ngày</label>
            <input type="text" name="day_notes" class="form-control" placeholder="VD: Thuê xe máy đi bản...">
          </div>
          <button type="submit" class="btn-cin-amber w-100 justify-content-center py-2">
            <i class="bi bi-plus-circle"></i>Thêm ngày lịch trình
          </button>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- MODAL THÊM / CHỈNH SỬA HOẠT ĐỘNG VÀO NGÀY (UC07.8 & UC07.9) -->
<div class="modal fade" id="addActivityModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <span class="cin-eyebrow" style="color: var(--cin-amber-bright); font-size: 0.64rem;">ĐIỀU PHỐI HOẠT ĐỘNG · UC07.8 / UC07.9</span>
          <h5 class="modal-title mb-0" id="activityModalTitle">Thêm hoạt động vào lịch trình cá nhân</h5>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form action="trip_detail.php?id=<?= $tripId ?>" method="post" class="row g-3">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="save_activity">
          <input type="hidden" name="activity_id" id="act_id_input" value="0">

          <div class="col-md-6">
            <label class="form-label">Chọn Ngày thực hiện</label>
            <select name="trip_day_id" id="modal_trip_day_id" class="form-select" required>
              <?php foreach ($daysData as $dOpt): ?>
                <option value="<?= (int)$dOpt['id'] ?>">
                  Ngày <?= (int)$dOpt['day_number'] ?> (<?= format_date_vn($dOpt['trip_date']) ?>) — <?= e($dOpt['title']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="col-md-6">
            <label class="form-label">Loại hoạt động</label>
            <select name="activity_type" id="act_type_input" class="form-select">
              <option value="attraction">Tham quan</option>
              <option value="food">Ăn uống</option>
              <option value="checkin_checkout">Nhận / Trả phòng</option>
              <option value="transport">Di chuyển</option>
              <option value="other">Hoạt động khác</option>
            </select>
          </div>

          <div class="col-md-6">
            <label class="form-label">Gắn với Địa điểm trong hệ thống (tùy chọn)</label>
            <select name="location_id" id="act_loc_input" class="form-select" onchange="fillActivityFromLocation(this)">
              <option value="">-- Hoạt động tự nhập (không gắn địa điểm cố định) --</option>
              <?php foreach ($allLocations as $al): ?>
                <option value="<?= (int)$al['id'] ?>" data-name="<?= e($al['name']) ?>" data-price="<?= (float)$al['ref_price'] ?>">
                  [<?= e($al['province_name']) ?>] <?= e($al['name']) ?> (<?= format_vnd($al['ref_price'], true) ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="col-md-6">
            <label class="form-label">Tên hoạt động</label>
            <input type="text" name="title" id="act_title_input" class="form-control" placeholder="VD: Ngắm bình minh đồi chè / Ăn trưa bê chao" required>
          </div>

          <div class="col-md-4">
            <label class="form-label">Giờ bắt đầu</label>
            <input type="time" name="start_time" id="act_start_input" class="form-control font-monospace" value="08:30">
          </div>
          <div class="col-md-4">
            <label class="form-label">Giờ kết thúc</label>
            <input type="time" name="end_time" id="act_end_input" class="form-control font-monospace" value="10:30">
          </div>
          <div class="col-md-4">
            <label class="form-label">Chi phí dự kiến (VNĐ)</label>
            <input type="number" name="estimated_cost" id="act_cost_input" class="form-control font-monospace" step="10000" value="0">
          </div>

          <div class="col-12">
            <label class="form-label">Mô tả hoạt động</label>
            <input type="text" name="description" id="act_desc_input" class="form-control" placeholder="Chi tiết trải nghiệm, món ăn dự định gọi...">
          </div>

          <div class="col-12">
            <label class="form-label">Ghi chú riêng</label>
            <input type="text" name="notes" id="act_notes_input" class="form-control" placeholder="VD: Nhớ mang áo khoác, đặt bàn trước 30p...">
          </div>

          <div class="col-12">
            <div class="form-check field-spec-box mt-0">
              <input class="form-check-input ms-0 me-2" type="checkbox" name="sync_to_expense" value="1" id="syncActExp" checked>
              <label class="form-check-label small fw-semibold" for="syncActExp">
                Đồng bộ chi phí dự kiến này sang bảng <strong>Khoản chi</strong> (liên kết tự động để tính vào tổng ngân sách mà không bị trùng)
              </label>
            </div>
          </div>

          <div class="col-12">
            <button type="submit" class="btn-cin-amber w-100 justify-content-center py-2">
              <i class="bi bi-check2-circle"></i>Lưu hoạt động vào lịch trình
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- MODAL THÊM / SỬA KHOẢN CHI (M6) -->
<div class="modal fade" id="addExpenseModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <span class="cin-eyebrow" style="color: var(--cin-amber-bright); font-size: 0.64rem;">SỔ CÁI NGÂN SÁCH · UC08.2 / UC08.3</span>
          <h5 class="modal-title mb-0" id="expenseModalTitle">Thêm / Cập nhật Khoản chi</h5>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form action="trip_detail.php?id=<?= $tripId ?>" method="post" class="row g-3">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="save_expense">
          <input type="hidden" name="expense_id" id="exp_id_field" value="0">

          <div class="col-md-5">
            <label class="form-label">Nhóm chi phí</label>
            <select name="expense_category" id="exp_cat_field" class="form-select">
              <option value="food">Ăn uống</option>
              <option value="transport">Di chuyển</option>
              <option value="stay">Lưu trú</option>
              <option value="ticket">Vé tham quan</option>
              <option value="shopping">Mua sắm &amp; Quà</option>
              <option value="other">Khác</option>
            </select>
          </div>
          <div class="col-md-7">
            <label class="form-label">Ngày chi</label>
            <input type="date" name="expense_date" id="exp_date_field" class="form-control font-monospace" value="<?= e($trip['start_date']) ?>">
          </div>
          <div class="col-12">
            <label class="form-label">Tên khoản chi</label>
            <input type="text" name="title" id="exp_title_field" class="form-control" placeholder="VD: Ăn trưa cá hồi / Vé đò Tràng An..." required>
          </div>
          <div class="col-md-6">
            <label class="form-label">Số tiền Dự kiến (VNĐ)</label>
            <input type="number" name="estimated_amount" id="exp_est_field" class="form-control font-monospace" step="10000" value="0" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">Số tiền Thực tế đã chi (VNĐ)</label>
            <input type="number" name="actual_amount" id="exp_act_field" class="form-control font-monospace" step="10000" value="0" required>
          </div>
          <div class="col-12">
            <label class="form-label">Ghi chú</label>
            <input type="text" name="notes" id="exp_notes_field" class="form-control" placeholder="Ghi chú hóa đơn, người thanh toán...">
          </div>
          <div class="col-12">
            <button type="submit" class="btn-cin-amber w-100 justify-content-center py-2">
              <i class="bi bi-check2-circle"></i>Lưu khoản chi
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- MODAL THÊM LƯU TRÚ (M6) -->
<div class="modal fade" id="addAccommodationModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <span class="cin-eyebrow" style="color: var(--cin-amber-bright); font-size: 0.64rem;">KẾ HOẠCH LƯU TRÚ · MODULE M6</span>
          <h5 class="modal-title mb-0">Thêm nơi lưu trú cho chuyến đi</h5>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form action="trip_detail.php?id=<?= $tripId ?>" method="post" class="row g-3">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="add_accommodation">

          <div class="col-md-6">
            <label class="form-label">Chọn nơi lưu trú trong hệ thống (tùy chọn)</label>
            <select name="location_id" class="form-select">
              <option value="">-- Tự nhập nơi lưu trú ngoài danh sách --</option>
              <?php foreach ($allLocations as $al): ?>
                <?php if ($al['location_type'] === 'stay'): ?>
                  <option value="<?= (int)$al['id'] ?>">[<?= e($al['province_name']) ?>] <?= e($al['name']) ?> (<?= format_vnd($al['ref_price']) ?>)</option>
                <?php endif; ?>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Tên Homestay / Khách sạn</label>
            <input type="text" name="stay_name" class="form-control" placeholder="Nhập tên hoặc để trống nếu đã chọn ở trên">
          </div>
          <div class="col-md-8">
            <label class="form-label">Địa chỉ</label>
            <input type="text" name="address" class="form-control" placeholder="Địa chỉ nơi lưu trú">
          </div>
          <div class="col-md-4">
            <label class="form-label">Trạng thái đặt phòng</label>
            <select name="booking_status" class="form-select">
              <option value="planned">Dự kiến</option>
              <option value="booked">Đã đặt phòng</option>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">Ngày Check-in</label>
            <input type="date" name="checkin_date" class="form-control font-monospace" value="<?= e($trip['start_date']) ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label">Ngày Check-out</label>
            <input type="date" name="checkout_date" class="form-control font-monospace" value="<?= e($trip['end_date']) ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label">Số lượng phòng</label>
            <input type="number" name="room_count" class="form-control font-monospace" min="1" value="1">
          </div>
          <div class="col-md-6">
            <label class="form-label">Tổng chi phí dự kiến (VNĐ)</label>
            <input type="number" name="estimated_cost" class="form-control font-monospace" step="50000" value="750000">
          </div>
          <div class="col-md-6">
            <label class="form-label">Chi phí thực tế đã trả (VNĐ)</label>
            <input type="number" name="actual_cost" class="form-control font-monospace" step="50000" value="0">
          </div>
          <div class="col-md-6">
            <label class="form-label">Liên hệ chủ nhà / Lễ tân (Ẩn khi chia sẻ)</label>
            <input type="text" name="contact_info" class="form-control font-monospace" placeholder="09xxxxxxxx">
          </div>
          <div class="col-md-6">
            <label class="form-label">Mã đặt chỗ (Ẩn khi chia sẻ)</label>
            <input type="text" name="booking_code" class="form-control font-monospace" placeholder="VD: BKG-8892">
          </div>
          <div class="col-12">
            <label class="form-label">Ghi chú</label>
            <input type="text" name="notes" class="form-control" placeholder="Giờ nhận phòng, tiền cọc...">
          </div>
          <div class="col-12">
            <div class="form-check field-spec-box mt-0">
              <input class="form-check-input ms-0 me-2" type="checkbox" name="sync_to_expense" value="1" id="syncAccExp" checked>
              <label class="form-check-label small fw-semibold" for="syncAccExp">
                Tự động tạo khoản chi Lưu trú liên kết trong bảng Ngân sách (tránh nhập lại 2 lần)
              </label>
            </div>
          </div>
          <div class="col-12">
            <button type="submit" class="btn-cin-amber w-100 justify-content-center py-2">
              <i class="bi bi-check2-circle"></i>Lưu thông tin lưu trú
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- MODAL THÊM PHƯƠNG TIỆN (M6) -->
<div class="modal fade" id="addTransportModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <span class="cin-eyebrow" style="color: var(--cin-amber-bright); font-size: 0.64rem;">PHƯƠNG TIỆN DI CHUYỂN · MODULE M6</span>
          <h5 class="modal-title mb-0">Thêm phương tiện di chuyển</h5>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form action="trip_detail.php?id=<?= $tripId ?>" method="post" class="row g-3">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="add_transport">

          <div class="col-md-4">
            <label class="form-label">Loại phương tiện</label>
            <input type="text" name="transport_type" class="form-control" value="Xe Limousine khứ hồi" required>
          </div>
          <div class="col-md-4">
            <label class="form-label">Điểm đi</label>
            <input type="text" name="departure_point" class="form-control" value="<?= e($trip['departure_point']) ?>" required>
          </div>
          <div class="col-md-4">
            <label class="form-label">Điểm đến</label>
            <input type="text" name="arrival_point" class="form-control" value="<?= e($trip['main_destination']) ?>" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">Thời gian khởi hành</label>
            <input type="datetime-local" name="departure_datetime" class="form-control font-monospace">
          </div>
          <div class="col-md-6">
            <label class="form-label">Thời gian đến dự kiến</label>
            <input type="datetime-local" name="arrival_datetime" class="form-control font-monospace">
          </div>
          <div class="col-md-4">
            <label class="form-label">Chi phí dự kiến (VNĐ)</label>
            <input type="number" name="estimated_cost" class="form-control font-monospace" step="50000" value="500000">
          </div>
          <div class="col-md-4">
            <label class="form-label">Chi phí thực tế (VNĐ)</label>
            <input type="number" name="actual_cost" class="form-control font-monospace" step="50000" value="0">
          </div>
          <div class="col-md-4">
            <label class="form-label">Mã vé / Đặt chỗ (Riêng tư)</label>
            <input type="text" name="booking_code" class="form-control font-monospace" placeholder="VD: LIM-992">
          </div>
          <div class="col-12">
            <label class="form-label">Ghi chú nhà xe / biển số</label>
            <input type="text" name="notes" class="form-control">
          </div>
          <div class="col-12">
            <div class="form-check field-spec-box mt-0">
              <input class="form-check-input ms-0 me-2" type="checkbox" name="sync_to_expense" value="1" id="syncTrExp" checked>
              <label class="form-check-label small fw-semibold" for="syncTrExp">
                Tự động tạo khoản chi Di chuyển liên kết trong bảng Ngân sách
              </label>
            </div>
          </div>
          <div class="col-12">
            <button type="submit" class="btn-cin-amber w-100 justify-content-center py-2">
              <i class="bi bi-check2-circle"></i>Lưu thông tin phương tiện
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>
<script>
function openAddActivityForDay(dayId) {
  document.getElementById('activityModalTitle').textContent = 'Thêm hoạt động vào lịch trình cá nhân';
  document.getElementById('act_id_input').value = 0;
  document.getElementById('act_title_input').value = '';
  document.getElementById('act_cost_input').value = 0;
  document.getElementById('act_desc_input').value = '';
  document.getElementById('act_notes_input').value = '';
  document.getElementById('act_loc_input').value = '';
  const select = document.getElementById('modal_trip_day_id');
  if (select) select.value = String(dayId);
  const modal = new bootstrap.Modal(document.getElementById('addActivityModal'));
  modal.show();
}

function editActivityModal(act) {
  document.getElementById('activityModalTitle').textContent = 'Chỉnh sửa hoạt động (UC07.9)';
  document.getElementById('act_id_input').value = act.id;
  document.getElementById('modal_trip_day_id').value = act.trip_day_id;
  document.getElementById('act_type_input').value = act.activity_type || 'attraction';
  document.getElementById('act_loc_input').value = act.location_id || '';
  document.getElementById('act_title_input').value = act.title || '';
  document.getElementById('act_start_input').value = act.start_time || '08:30';
  document.getElementById('act_end_input').value = act.end_time || '10:30';
  document.getElementById('act_cost_input').value = act.estimated_cost || 0;
  document.getElementById('act_desc_input').value = act.description || '';
  document.getElementById('act_notes_input').value = act.notes || '';
  new bootstrap.Modal(document.getElementById('addActivityModal')).show();
}

function editChecklistItem(ck) {
  document.getElementById('checklistFormTitle').innerHTML = '<i class="bi bi-pencil-square text-primary me-2"></i>Chỉnh sửa mục Checklist (UC08.11)';
  document.getElementById('ck_item_id').value = ck.id;
  document.getElementById('ck_item_name').value = ck.item_name;
  document.getElementById('ck_category').value = ck.category;
  document.getElementById('ck_submit_btn').innerHTML = '<i class="bi bi-check2-circle me-1"></i>Cập nhật mục Checklist';
  document.getElementById('ck_cancel_btn').classList.remove('d-none');
  document.getElementById('ck_item_name').focus();
}

function resetChecklistForm() {
  document.getElementById('checklistFormTitle').innerHTML = '<i class="bi bi-plus-square-fill text-success me-2"></i>Thêm / Cập nhật mục Checklist';
  document.getElementById('ck_item_id').value = 0;
  document.getElementById('ck_item_name').value = '';
  document.getElementById('ck_submit_btn').innerHTML = '<i class="bi bi-plus-circle me-1"></i>Lưu vào Checklist';
  document.getElementById('ck_cancel_btn').classList.add('d-none');
}

function fillActivityFromLocation(selectEl) {
  const opt = selectEl.options[selectEl.selectedIndex];
  if (!opt || !opt.value) return;
  const titleInput = document.getElementById('act_title_input');
  const costInput = document.getElementById('act_cost_input');
  if (titleInput && !titleInput.value) {
    titleInput.value = opt.dataset.name || '';
  }
  if (costInput && parseFloat(opt.dataset.price || '0') > 0) {
    costInput.value = Math.round(parseFloat(opt.dataset.price) * <?= (int)$trip['pax_count'] ?>);
  }
}

function editExpense(exp) {
  document.getElementById('expenseModalTitle').textContent = 'Cập nhật Khoản chi';
  document.getElementById('exp_id_field').value = exp.id;
  document.getElementById('exp_cat_field').value = exp.expense_category;
  document.getElementById('exp_title_field').value = exp.title;
  document.getElementById('exp_est_field').value = exp.estimated_amount;
  document.getElementById('exp_act_field').value = exp.actual_amount;
  document.getElementById('exp_date_field').value = exp.expense_date || '<?= e($trip['start_date']) ?>';
  document.getElementById('exp_notes_field').value = exp.notes || '';
  new bootstrap.Modal(document.getElementById('addExpenseModal')).show();
}

document.addEventListener('DOMContentLoaded', () => {
  <?php if ($activeTab === 'itinerary'): ?>
    const mapPoints = <?= json_encode($mapPoints, JSON_UNESCAPED_UNICODE) ?>;
    BacDuMap.init('tripItineraryMap', mapPoints, [20.84, 104.64], 10);
  <?php endif; ?>

  <?php if ($activeTab === 'budget'): ?>
    const ctx = document.getElementById('budgetChart');
    if (ctx && typeof Chart !== 'undefined') {
      const catData = <?= json_encode(array_values($budgetSummary['by_category']), JSON_UNESCAPED_UNICODE) ?>;
      new Chart(ctx, {
        type: 'doughnut',
        data: {
          labels: catData.map(c => c.label),
          datasets: [{
            data: catData.map(c => c.sum_estimated),
            backgroundColor: catData.map(c => c.color),
            borderWidth: 2
          }]
        },
        options: {
          plugins: {
            legend: { position: 'bottom' }
          }
        }
      });
    }
  <?php endif; ?>
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
