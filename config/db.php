<?php
/**
 * Cấu hình kết nối Cơ sở dữ liệu an toàn (PDO) - Dự án Bắc Du (Travel Planner)
 * Ưu tiên kết nối MySQL (XAMPP / Laragon) và tự động khởi tạo schema + dữ liệu mẫu từ database/travel_planner.sql.
 * Tích hợp cơ chế tự động đồng bộ SQLite dự phòng nếu dịch vụ MySQL chưa được bật trong XAMPP Control Panel.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set('Asia/Ho_Chi_Minh');

define('APP_NAME', 'Bắc Du');
define('APP_TAGLINE', 'Lịch trình theo mùa & Kế hoạch vi vu miền Bắc');
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'travel_planner');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');

/**
 * Trả về đối tượng PDO duy nhất trong suốt vòng đời request
 */
function get_db(): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $sqlFile = dirname(__DIR__) . '/database/travel_planner.sql';

    // 1. Thử kết nối MySQL (XAMPP / Laragon)
    try {
        $dsnNoDb = sprintf('mysql:host=%s;port=%s;charset=utf8mb4', DB_HOST, DB_PORT);
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_TIMEOUT            => 2,
        ];

        $rootPdo = new PDO($dsnNoDb, DB_USER, DB_PASS, $options);
        $rootPdo->exec('CREATE DATABASE IF NOT EXISTS `' . DB_NAME . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $rootPdo->exec('USE `' . DB_NAME . '`');

        // Kiểm tra xem bảng users đã tồn tại chưa, nếu chưa thì tự động nạp travel_planner.sql
        $stmt = $rootPdo->query("SHOW TABLES LIKE 'users'");
        if (!$stmt->fetch() && is_file($sqlFile)) {
            import_mysql_sql_file($rootPdo, $sqlFile);
        }

        $pdo = $rootPdo;
        if (!defined('ACTIVE_DB_DRIVER')) {
            define('ACTIVE_DB_DRIVER', 'mysql');
        }
        return $pdo;
    } catch (Throwable $mysqlErr) {
        // 2. Fallback an toàn sang PDO SQLite nếu dịch vụ MySQL trong XAMPP chưa bật
        $sqlitePath = dirname(__DIR__) . '/database/travel_planner_runtime.sqlite';
        $needInit = !is_file($sqlitePath) || filesize($sqlitePath) < 4096;

        $pdo = new PDO('sqlite:' . $sqlitePath, null, null, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('PRAGMA foreign_keys = ON');

        if ($needInit && is_file($sqlFile)) {
            import_sqlite_from_mysql_dump($pdo, $sqlFile);
        } else {
            // Kiểm tra bảng users
            $check = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='users'")->fetch();
            if (!$check && is_file($sqlFile)) {
                import_sqlite_from_mysql_dump($pdo, $sqlFile);
            }
        }

        if (!defined('ACTIVE_DB_DRIVER')) {
            define('ACTIVE_DB_DRIVER', 'sqlite');
        }
        return $pdo;
    }
}

/**
 * Nạp file SQL chuẩn vào MySQL
 */
function import_mysql_sql_file(PDO $pdo, string $sqlFile): void
{
    $sql = file_get_contents($sqlFile);
    if ($sql === false) {
        return;
    }
    $statements = split_sql_statements($sql);
    foreach ($statements as $stmt) {
        $stmt = trim($stmt);
        if ($stmt === '') {
            continue;
        }
        $pdo->exec($stmt);
    }
}

/**
 * Chuyển đổi và nạp file travel_planner.sql vào SQLite khi MySQL chưa khởi chạy
 */
function import_sqlite_from_mysql_dump(PDO $pdo, string $sqlFile): void
{
    $sql = file_get_contents($sqlFile);
    if ($sql === false) {
        return;
    }

    $pdo->exec('PRAGMA foreign_keys = OFF');
    $statements = split_sql_statements($sql);

    foreach ($statements as $rawStmt) {
        $stmt = trim($rawStmt);
        if ($stmt === '') {
            continue;
        }
        if (preg_match('/^(SET\s+|CREATE\s+DATABASE|USE\s+)/i', $stmt)) {
            continue;
        }

        if (preg_match('/^CREATE\s+TABLE/i', $stmt)) {
            // Chuyển cú pháp MySQL CREATE TABLE sang tương thích SQLite
            $stmt = preg_replace('/`id`\s+INT\s+AUTO_INCREMENT\s+PRIMARY\s+KEY/i', '`id` INTEGER PRIMARY KEY AUTOINCREMENT', $stmt);
            $stmt = preg_replace('/ENUM\s*\([^)]+\)/i', 'TEXT', $stmt);
            $stmt = preg_replace('/TINYINT\(\d+\)/i', 'INTEGER', $stmt);
            $stmt = preg_replace('/\bTINYINT\b/i', 'INTEGER', $stmt);
            $stmt = preg_replace('/\bSMALLINT\b/i', 'INTEGER', $stmt);
            $stmt = preg_replace('/DECIMAL\(\d+,\s*\d+\)/i', ' NUMERIC', $stmt);
            $stmt = preg_replace('/ON\s+UPDATE\s+CURRENT_TIMESTAMP/i', '', $stmt);
            $stmt = preg_replace('/,\s*UNIQUE\s+KEY\s+`[^`]+`\s*(\([^)]+\))/i', ', UNIQUE $1', $stmt);
            $stmt = preg_replace('/,\s*CONSTRAINT\s+`[^`]+`\s+FOREIGN\s+KEY[^,\n]+/i', '', $stmt);
            $stmt = preg_replace('/\)\s*ENGINE=[^;]+$/i', ')', $stmt);
        }

        $pdo->exec($stmt);
    }
    $pdo->exec('PRAGMA foreign_keys = ON');
}

/**
 * Tách các câu lệnh SQL an toàn (bỏ qua comment và tôn trọng chuỗi có dấu nháy đơn)
 */
function split_sql_statements(string $sql): array
{
    $lines = explode("\n", $sql);
    $cleanLines = [];
    foreach ($lines as $line) {
        $trimmed = ltrim($line);
        if (str_starts_with($trimmed, '--') || str_starts_with($trimmed, '#')) {
            continue;
        }
        $cleanLines[] = $line;
    }
    $cleanSql = implode("\n", $cleanLines);

    $statements = [];
    $buffer = '';
    $inString = false;
    $len = strlen($cleanSql);

    for ($i = 0; $i < $len; $i++) {
        $ch = $cleanSql[$i];
        if ($ch === "'") {
            if ($inString && $i + 1 < $len && $cleanSql[$i + 1] === "'") {
                $buffer .= "''";
                $i++;
                continue;
            }
            $inString = !$inString;
        }
        if ($ch === ';' && !$inString) {
            if (trim($buffer) !== '') {
                $statements[] = trim($buffer);
            }
            $buffer = '';
        } else {
            $buffer .= $ch;
        }
    }
    if (trim($buffer) !== '') {
        $statements[] = trim($buffer);
    }

    return $statements;
}
