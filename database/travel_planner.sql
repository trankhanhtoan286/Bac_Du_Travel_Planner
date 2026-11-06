-- ============================================================================
-- BẮC DU (TRAVEL PLANNER) - CƠ SỞ DỮ LIỆU HỆ THỐNG (MySQL / MariaDB)
-- Nền tảng tham khảo lịch trình 12 tháng và lập kế hoạch du lịch miền Bắc
-- Đầy đủ 20 bảng theo đặc tả hệ thống + Dữ liệu mẫu miền Bắc
-- ============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS `travel_planner`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
USE `travel_planner`;

-- ----------------------------------------------------------------------------
-- 1. Bảng users: Tài khoản, hồ sơ, phân quyền (M1)
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `full_name` VARCHAR(120) NOT NULL,
  `username` VARCHAR(60) NOT NULL UNIQUE,
  `email` VARCHAR(150) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `role` ENUM('user', 'admin') NOT NULL DEFAULT 'user',
  `status` ENUM('active', 'locked') NOT NULL DEFAULT 'active',
  `avatar_url` VARCHAR(500) DEFAULT NULL,
  `bio` TEXT DEFAULT NULL,
  `phone` VARCHAR(30) DEFAULT NULL,
  `hometown` VARCHAR(120) DEFAULT 'Hà Nội',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 2. Bảng provinces: Tỉnh/thành trong phạm vi triển khai miền Bắc (M5)
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `provinces`;
CREATE TABLE `provinces` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `slug` VARCHAR(120) NOT NULL UNIQUE,
  `subregion` VARCHAR(80) NOT NULL DEFAULT 'Tây Bắc',
  `description` TEXT DEFAULT NULL,
  `best_months` VARCHAR(100) DEFAULT NULL,
  `image_url` VARCHAR(500) DEFAULT NULL,
  `latitude` DECIMAL(10, 7) DEFAULT NULL,
  `longitude` DECIMAL(10, 7) DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 3. Bảng categories: Danh mục phù hợp với từng loại địa điểm (M5)
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `categories`;
CREATE TABLE `categories` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `slug` VARCHAR(120) NOT NULL UNIQUE,
  `location_type` ENUM('attraction', 'food', 'stay') NOT NULL,
  `icon` VARCHAR(60) DEFAULT 'bi-geo-alt',
  `description` VARCHAR(255) DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 4. Bảng locations: Điểm tham quan, ăn uống và lưu trú (M5)
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `locations`;
CREATE TABLE `locations` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `province_id` INT NOT NULL,
  `category_id` INT NOT NULL,
  `name` VARCHAR(180) NOT NULL,
  `slug` VARCHAR(200) NOT NULL UNIQUE,
  `location_type` ENUM('attraction', 'food', 'stay') NOT NULL,
  `thumbnail_url` VARCHAR(500) DEFAULT NULL,
  `short_desc` VARCHAR(300) DEFAULT NULL,
  `description` TEXT DEFAULT NULL,
  `address` VARCHAR(300) DEFAULT NULL,
  `latitude` DECIMAL(10, 7) NOT NULL,
  `longitude` DECIMAL(10, 7) NOT NULL,
  `opening_hours` VARCHAR(150) DEFAULT NULL,
  `contact_info` VARCHAR(180) DEFAULT NULL,
  `google_maps_url` VARCHAR(500) DEFAULT NULL,
  `ref_price` DECIMAL(12, 0) NOT NULL DEFAULT 0,
  `price_unit` VARCHAR(60) NOT NULL DEFAULT 'người',
  `price_updated_at` DATE DEFAULT NULL,
  -- Thông tin đặc thù nhóm Tham quan (attraction)
  `ticket_price_info` VARCHAR(200) DEFAULT NULL,
  `visit_duration` VARCHAR(100) DEFAULT NULL,
  `seasonal_note` TEXT DEFAULT NULL,
  -- Thông tin đặc thù nhóm Ăn uống (food)
  `signature_dishes` VARCHAR(255) DEFAULT NULL,
  `restaurant_type` VARCHAR(120) DEFAULT NULL,
  `price_range_per_person` VARCHAR(120) DEFAULT NULL,
  -- Thông tin đặc thù nhóm Lưu trú (stay)
  `stay_type` VARCHAR(120) DEFAULT NULL,
  `room_price_info` VARCHAR(200) DEFAULT NULL,
  `checkin_checkout_hours` VARCHAR(120) DEFAULT NULL,
  `amenities` VARCHAR(300) DEFAULT NULL,
  -- Quản lý trạng thái (ưu tiên ngừng hiển thị/lưu trữ thay vì xóa cứng)
  `status` ENUM('active', 'archived') NOT NULL DEFAULT 'active',
  `created_by` INT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_locations_province` FOREIGN KEY (`province_id`) REFERENCES `provinces` (`id`),
  CONSTRAINT `fk_locations_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 5. Bảng location_images: Ảnh của địa điểm (M5)
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `location_images`;
CREATE TABLE `location_images` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `location_id` INT NOT NULL,
  `image_url` VARCHAR(500) NOT NULL,
  `caption` VARCHAR(200) DEFAULT NULL,
  `sort_order` INT NOT NULL DEFAULT 1,
  CONSTRAINT `fk_loc_images_location` FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 6. Bảng itinerary_templates: Thông tin tổng quan lịch trình mẫu (M2)
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `itinerary_templates`;
CREATE TABLE `itinerary_templates` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(200) NOT NULL,
  `slug` VARCHAR(220) NOT NULL UNIQUE,
  `province_id` INT NOT NULL,
  `main_destination` VARCHAR(150) NOT NULL,
  `thumbnail_url` VARCHAR(500) DEFAULT NULL,
  `duration_days` INT NOT NULL DEFAULT 3,
  `duration_nights` INT NOT NULL DEFAULT 2,
  `assumed_departure` VARCHAR(120) NOT NULL DEFAULT 'Hà Nội',
  `suggested_transport` VARCHAR(180) NOT NULL DEFAULT 'Xe limousine / Xe máy tại điểm đến',
  `ref_budget_per_person` DECIMAL(12, 0) NOT NULL DEFAULT 2500000,
  `base_pax_count` INT NOT NULL DEFAULT 2,
  `seasonal_reason` TEXT NOT NULL,
  `summary` TEXT DEFAULT NULL,
  `essential_notes` TEXT DEFAULT NULL,
  `status` ENUM('draft', 'published', 'archived') NOT NULL DEFAULT 'published',
  `author_id` INT NOT NULL DEFAULT 1,
  `views_count` INT NOT NULL DEFAULT 0,
  `copies_count` INT NOT NULL DEFAULT 0,
  `updated_date` DATE NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_templates_province` FOREIGN KEY (`province_id`) REFERENCES `provinces` (`id`),
  CONSTRAINT `fk_templates_author` FOREIGN KEY (`author_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 7. Bảng template_months: Các tháng phù hợp với từng mẫu (1..12) (M2)
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `template_months`;
CREATE TABLE `template_months` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `template_id` INT NOT NULL,
  `month_number` TINYINT NOT NULL,
  `month_reason` VARCHAR(255) DEFAULT NULL,
  UNIQUE KEY `uk_template_month` (`template_id`, `month_number`),
  CONSTRAINT `fk_tmonths_template` FOREIGN KEY (`template_id`) REFERENCES `itinerary_templates` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 8. Bảng template_days: Ngày thứ nhất, thứ hai... trong mẫu (M2)
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `template_days`;
CREATE TABLE `template_days` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `template_id` INT NOT NULL,
  `day_number` INT NOT NULL,
  `title` VARCHAR(200) NOT NULL,
  `summary` TEXT DEFAULT NULL,
  `transport_note` VARCHAR(255) DEFAULT NULL,
  CONSTRAINT `fk_tdays_template` FOREIGN KEY (`template_id`) REFERENCES `itinerary_templates` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 9. Bảng template_activities: Hoạt động và địa điểm theo ngày mẫu (M2)
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `template_activities`;
CREATE TABLE `template_activities` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `template_day_id` INT NOT NULL,
  `location_id` INT DEFAULT NULL,
  `title` VARCHAR(200) NOT NULL,
  `activity_type` ENUM('attraction', 'food', 'checkin_checkout', 'transport', 'other') NOT NULL DEFAULT 'attraction',
  `start_time` VARCHAR(10) DEFAULT '08:00',
  `end_time` VARCHAR(10) DEFAULT '10:00',
  `description` TEXT DEFAULT NULL,
  `transport_note` VARCHAR(255) DEFAULT NULL,
  `estimated_cost` DECIMAL(12, 0) NOT NULL DEFAULT 0,
  `important_note` VARCHAR(255) DEFAULT NULL,
  `sort_order` INT NOT NULL DEFAULT 1,
  CONSTRAINT `fk_tact_day` FOREIGN KEY (`template_day_id`) REFERENCES `template_days` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_tact_location` FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 10. Bảng template_accommodations: Gợi ý lưu trú theo đêm, số phòng, chi phí (M2/M6)
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `template_accommodations`;
CREATE TABLE `template_accommodations` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `template_id` INT NOT NULL,
  `night_number` INT NOT NULL DEFAULT 1,
  `location_id` INT DEFAULT NULL,
  `stay_name` VARCHAR(200) NOT NULL,
  `room_count` INT NOT NULL DEFAULT 1,
  `base_cost` DECIMAL(12, 0) NOT NULL DEFAULT 0,
  `notes` VARCHAR(255) DEFAULT NULL,
  CONSTRAINT `fk_tacc_template` FOREIGN KEY (`template_id`) REFERENCES `itinerary_templates` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_tacc_location` FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 11. Bảng template_expenses: Các khoản dự toán của lịch trình mẫu (M2/M6)
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `template_expenses`;
CREATE TABLE `template_expenses` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `template_id` INT NOT NULL,
  `expense_category` ENUM('food', 'transport', 'stay', 'ticket', 'shopping', 'other') NOT NULL DEFAULT 'other',
  `title` VARCHAR(200) NOT NULL,
  `estimated_amount` DECIMAL(12, 0) NOT NULL DEFAULT 0,
  `linked_activity_id` INT DEFAULT NULL,
  `linked_accommodation_id` INT DEFAULT NULL,
  `notes` VARCHAR(255) DEFAULT NULL,
  CONSTRAINT `fk_texp_template` FOREIGN KEY (`template_id`) REFERENCES `itinerary_templates` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 12. Bảng trips: Thông tin chuyến đi cá nhân, mẫu nguồn, cấu hình chia sẻ (M4/M7)
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `trips`;
CREATE TABLE `trips` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `source_template_id` INT DEFAULT NULL,
  `province_id` INT DEFAULT NULL,
  `title` VARCHAR(200) NOT NULL,
  `main_destination` VARCHAR(150) NOT NULL,
  `start_date` DATE NOT NULL,
  `end_date` DATE NOT NULL,
  `pax_count` INT NOT NULL DEFAULT 2,
  `departure_point` VARCHAR(120) NOT NULL DEFAULT 'Hà Nội',
  `total_budget` DECIMAL(12, 0) NOT NULL DEFAULT 0,
  `description` TEXT DEFAULT NULL,
  `cover_image_url` VARCHAR(500) DEFAULT NULL,
  `share_token` VARCHAR(80) DEFAULT NULL UNIQUE,
  `is_shared` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_trips_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_trips_template` FOREIGN KEY (`source_template_id`) REFERENCES `itinerary_templates` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 13. Bảng trip_days: Các ngày cụ thể của chuyến đi (M4)
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `trip_days`;
CREATE TABLE `trip_days` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `trip_id` INT NOT NULL,
  `day_number` INT NOT NULL,
  `trip_date` DATE NOT NULL,
  `title` VARCHAR(200) NOT NULL,
  `notes` TEXT DEFAULT NULL,
  CONSTRAINT `fk_tripdays_trip` FOREIGN KEY (`trip_id`) REFERENCES `trips` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 14. Bảng activities: Hoạt động trong lịch trình cá nhân (M4)
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `activities`;
CREATE TABLE `activities` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `trip_day_id` INT NOT NULL,
  `location_id` INT DEFAULT NULL,
  `title` VARCHAR(200) NOT NULL,
  `activity_type` ENUM('attraction', 'food', 'checkin_checkout', 'transport', 'other') NOT NULL DEFAULT 'attraction',
  `start_time` VARCHAR(10) DEFAULT '08:00',
  `end_time` VARCHAR(10) DEFAULT '10:00',
  `description` TEXT DEFAULT NULL,
  `estimated_cost` DECIMAL(12, 0) NOT NULL DEFAULT 0,
  `notes` VARCHAR(255) DEFAULT NULL,
  `sort_order` INT NOT NULL DEFAULT 1,
  CONSTRAINT `fk_activities_day` FOREIGN KEY (`trip_day_id`) REFERENCES `trip_days` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_activities_location` FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 15. Bảng accommodations: Kế hoạch lưu trú của chuyến đi (M6)
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `accommodations`;
CREATE TABLE `accommodations` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `trip_id` INT NOT NULL,
  `location_id` INT DEFAULT NULL,
  `stay_name` VARCHAR(200) NOT NULL,
  `address` VARCHAR(255) DEFAULT NULL,
  `checkin_date` DATE DEFAULT NULL,
  `checkin_time` VARCHAR(10) DEFAULT '14:00',
  `checkout_date` DATE DEFAULT NULL,
  `checkout_time` VARCHAR(10) DEFAULT '12:00',
  `room_count` INT NOT NULL DEFAULT 1,
  `estimated_cost` DECIMAL(12, 0) NOT NULL DEFAULT 0,
  `actual_cost` DECIMAL(12, 0) NOT NULL DEFAULT 0,
  `contact_info` VARCHAR(150) DEFAULT NULL,
  `booking_code` VARCHAR(80) DEFAULT NULL,
  `booking_status` ENUM('planned', 'booked') NOT NULL DEFAULT 'planned',
  `notes` VARCHAR(255) DEFAULT NULL,
  CONSTRAINT `fk_acc_trip` FOREIGN KEY (`trip_id`) REFERENCES `trips` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_acc_location` FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 16. Bảng transports: Thông tin phương tiện của chuyến đi (M6)
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `transports`;
CREATE TABLE `transports` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `trip_id` INT NOT NULL,
  `transport_type` VARCHAR(100) NOT NULL DEFAULT 'Xe limousine',
  `departure_point` VARCHAR(150) NOT NULL,
  `arrival_point` VARCHAR(150) NOT NULL,
  `departure_datetime` DATETIME DEFAULT NULL,
  `arrival_datetime` DATETIME DEFAULT NULL,
  `estimated_cost` DECIMAL(12, 0) NOT NULL DEFAULT 0,
  `actual_cost` DECIMAL(12, 0) NOT NULL DEFAULT 0,
  `booking_code` VARCHAR(80) DEFAULT NULL,
  `notes` VARCHAR(255) DEFAULT NULL,
  CONSTRAINT `fk_trans_trip` FOREIGN KEY (`trip_id`) REFERENCES `trips` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 17. Bảng expenses: Các khoản chi dự kiến/thực tế - Nguồn chuẩn tính tổng ngân sách (M6)
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `expenses`;
CREATE TABLE `expenses` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `trip_id` INT NOT NULL,
  `expense_category` ENUM('food', 'transport', 'stay', 'ticket', 'shopping', 'other') NOT NULL DEFAULT 'other',
  `title` VARCHAR(200) NOT NULL,
  `estimated_amount` DECIMAL(12, 0) NOT NULL DEFAULT 0,
  `actual_amount` DECIMAL(12, 0) NOT NULL DEFAULT 0,
  `linked_activity_id` INT DEFAULT NULL,
  `linked_accommodation_id` INT DEFAULT NULL,
  `linked_transport_id` INT DEFAULT NULL,
  `expense_date` DATE DEFAULT NULL,
  `notes` VARCHAR(255) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_exp_trip` FOREIGN KEY (`trip_id`) REFERENCES `trips` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_exp_activity` FOREIGN KEY (`linked_activity_id`) REFERENCES `activities` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_exp_acc` FOREIGN KEY (`linked_accommodation_id`) REFERENCES `accommodations` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_exp_trans` FOREIGN KEY (`linked_transport_id`) REFERENCES `transports` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 18. Bảng checklists: Đồ dùng, công việc cần chuẩn bị (M7)
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `checklists`;
CREATE TABLE `checklists` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `trip_id` INT NOT NULL,
  `item_name` VARCHAR(200) NOT NULL,
  `category` VARCHAR(80) NOT NULL DEFAULT 'Đồ dùng cá nhân',
  `is_completed` TINYINT(1) NOT NULL DEFAULT 0,
  `sort_order` INT NOT NULL DEFAULT 1,
  CONSTRAINT `fk_checklist_trip` FOREIGN KEY (`trip_id`) REFERENCES `trips` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 19. Bảng favorite_locations: Địa điểm yêu thích của người dùng (M7)
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `favorite_locations`;
CREATE TABLE `favorite_locations` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `location_id` INT NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uk_user_fav_location` (`user_id`, `location_id`),
  CONSTRAINT `fk_fav_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_fav_location` FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 20. Bảng experience_posts: Bài trải nghiệm, đánh giá, trạng thái duyệt (M3)
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `experience_posts`;
CREATE TABLE `experience_posts` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `location_id` INT NOT NULL,
  `title` VARCHAR(220) NOT NULL,
  `visit_month` TINYINT NOT NULL DEFAULT 2,
  `visit_year` SMALLINT NOT NULL DEFAULT 2026,
  `content` TEXT NOT NULL,
  `rating` TINYINT NOT NULL DEFAULT 5,
  `ref_cost` DECIMAL(12, 0) DEFAULT NULL,
  `cost_unit` VARCHAR(60) DEFAULT 'người',
  `pros_liked` TEXT DEFAULT NULL,
  `cons_inconvenient` TEXT DEFAULT NULL,
  `tips_advice` TEXT DEFAULT NULL,
  `status` ENUM('draft', 'pending', 'approved', 'rejected', 'hidden') NOT NULL DEFAULT 'pending',
  `rejection_reason` TEXT DEFAULT NULL,
  `reviewed_by` INT DEFAULT NULL,
  `reviewed_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_exppost_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_exppost_location` FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 21. Bảng experience_images: Ảnh thực tế trong bài trải nghiệm (M3)
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `experience_images`;
CREATE TABLE `experience_images` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `post_id` INT NOT NULL,
  `image_url` VARCHAR(500) NOT NULL,
  `caption` VARCHAR(200) DEFAULT NULL,
  `sort_order` INT NOT NULL DEFAULT 1,
  CONSTRAINT `fk_expimg_post` FOREIGN KEY (`post_id`) REFERENCES `experience_posts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================================
-- DỮ LIỆU MẪU MIỀN BẮC (12 THÁNG - ĐỊA ĐIỂM - LỊCH TRÌNH MẪU - CỘNG ĐỒNG)
-- Mật khẩu mặc định cho tất cả tài khoản demo: 123456
-- ============================================================================

INSERT INTO `users` (`id`, `full_name`, `username`, `email`, `password_hash`, `role`, `status`, `avatar_url`, `bio`, `phone`, `hometown`) VALUES
(1, 'Quản Trị Viên (Admin)', 'admin', 'admin@bacdu.vn', '$2y$10$UPGEOqnz9toQAlTbioLUIu0v6jX5QmgiZIjmIC2PzZcPEvqBbaUSC', 'admin', 'active', 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=300&auto=format&fit=crop&q=80', 'Quản trị viên & Ban biên tập nội dung lịch trình du lịch miền Bắc theo 12 tháng.', '0901234567', 'Hà Nội'),
(2, 'Ngô Xuân Kiên', 'kien', 'kien@gmail.com', '$2y$10$UPGEOqnz9toQAlTbioLUIu0v6jX5QmgiZIjmIC2PzZcPEvqBbaUSC', 'user', 'active', 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=300&auto=format&fit=crop&q=80', 'Yêu những cung đường đèo Tây Bắc, săn mây Tà Xùa và mùa hoa mận Mộc Châu.', '0987654321', 'Hà Nội'),
(3, 'Lê Thị Ngọc Ánh', 'anh', 'anh@gmail.com', '$2y$10$UPGEOqnz9toQAlTbioLUIu0v6jX5QmgiZIjmIC2PzZcPEvqBbaUSC', 'user', 'active', 'https://images.unsplash.com/photo-1517841905240-472988babdf9?w=300&auto=format&fit=crop&q=80', 'Đam mê nhiếp ảnh phong cảnh Đông - Tây Bắc và trải nghiệm văn hóa bản địa.', '0912345688', 'Hải Phòng'),
(4, 'Lò Thị Trâm', 'tram', 'tram@gmail.com', '$2y$10$UPGEOqnz9toQAlTbioLUIu0v6jX5QmgiZIjmIC2PzZcPEvqBbaUSC', 'user', 'active', 'https://images.unsplash.com/photo-1544005313-94ddf0286df2?w=300&auto=format&fit=crop&q=80', 'Người con vùng cao Tây Bắc, thích chia sẻ ẩm thực và homestay bản làng.', '0978112233', 'Sơn La'),
(5, 'Nguyễn Thị Vân', 'van', 'van@gmail.com', '$2y$10$UPGEOqnz9toQAlTbioLUIu0v6jX5QmgiZIjmIC2PzZcPEvqBbaUSC', 'user', 'active', 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?w=300&auto=format&fit=crop&q=80', 'Thích du lịch nghỉ dưỡng sinh thái Ninh Bình, Hạ Long và Pù Luông.', '0965432189', 'Quảng Ninh'),
(6, 'Trần Khánh Toàn', 'toan', 'toan@gmail.com', '$2y$10$UPGEOqnz9toQAlTbioLUIu0v6jX5QmgiZIjmIC2PzZcPEvqBbaUSC', 'user', 'active', 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=300&auto=format&fit=crop&q=80', 'Phượt thủ mê cung đường Hà Giang, Cao Bằng và săn mùa vàng Sa Pa.', '0934567890', 'Thái Nguyên');

INSERT INTO `provinces` (`id`, `name`, `slug`, `subregion`, `description`, `best_months`, `image_url`, `latitude`, `longitude`, `is_active`) VALUES
(1, 'Sơn La (Mộc Châu - Tà Xùa)', 'son-la-moc-chau', 'Tây Bắc', 'Cao nguyên xanh mát với đồi chè trái tim, mùa hoa mận trắng tháng 1-2, mùa hồng chín thu đông và thiên đường mây Tà Xùa.', '1,2,3,10,11,12', 'https://images.unsplash.com/photo-1528127269322-539801943592?w=900&auto=format&fit=crop&q=80', 20.8448000, 104.6391000, 1),
(2, 'Hà Giang', 'ha-giang', 'Đông Bắc', 'Cao nguyên đá Đồng Văn hùng vĩ, đèo Mã Pí Lèng, sông Nho Quế xanh ngọc bích và mùa hoa tam giác mạch tháng 10-11.', '3,9,10,11,12', 'https://images.unsplash.com/photo-1559592413-7cec4d0cae2b?w=900&auto=format&fit=crop&q=80', 23.2100000, 105.3350000, 1),
(3, 'Lào Cai (Sa Pa - Y Tý)', 'lao-cai-sapa', 'Tây Bắc', 'Thị trấn trong sương với đỉnh Fansipan, bản Cát Cát, thung lũng Mường Hoa mùa lúa chín vàng tháng 9 và săn tuyết mùa đông.', '4,5,9,10,12', 'https://images.unsplash.com/photo-1583417319070-4a69db38a482?w=900&auto=format&fit=crop&q=80', 22.3364000, 103.8438000, 1),
(4, 'Ninh Bình', 'ninh-binh', 'Đồng bằng sông Hồng', 'Di sản kép Tràng An, Tam Cốc mùa lúa chín vàng tháng 5-6, hang Múa và cố đô Hoa Lư trầm mặc.', '2,3,5,6', 'https://images.unsplash.com/photo-1570366583862-f91883984fde?w=900&auto=format&fit=crop&q=80', 20.2506000, 105.9745000, 1),
(5, 'Quảng Ninh (Hạ Long - Cô Tô)', 'quang-ninh-ha-long', 'Đông Bắc', 'Kỳ quan vịnh Hạ Long, đảo Cô Tô biển xanh cát trắng mùa hè và non thiêng Yên Tử mùa lễ hội xuân.', '1,6,7,8', 'https://images.unsplash.com/photo-1528127269322-539801943592?w=900&auto=format&fit=crop&q=80', 20.9505000, 107.0734000, 1),
(6, 'Cao Bằng', 'cao-bang', 'Đông Bắc', 'Thác Bản Giốc hùng vĩ đẹp nhất tháng 8-10, suối Lê Nin xanh ngọc và núi Mắt Thần.', '8,9,10', 'https://images.unsplash.com/photo-1506744038136-46273834b3fb?w=900&auto=format&fit=crop&q=80', 22.8557000, 106.7225000, 1),
(7, 'Hà Nội & Phụ cận', 'ha-noi', 'Đồng bằng sông Hồng', 'Thủ đô nghìn năm văn hiến với mùa thu cốm mới, phố cổ, hồ Tây và làng cổ Đường Lâm.', '3,9,10,11', 'https://images.unsplash.com/photo-1509030450996-dd1a26dda07a?w=900&auto=format&fit=crop&q=80', 21.0285000, 105.8542000, 1),
(8, 'Thanh Hóa (Pù Luông) & Hòa Bình (Mai Châu)', 'mai-chau-pu-luong', 'Tây Bắc', 'Thung lũng bình yên với bản Lác Mai Châu và khu bảo tồn thiên nhiên Pù Luông hai mùa lúa tháng 5 và tháng 10.', '4,5,9,10', 'https://images.unsplash.com/photo-1500382017468-9049fed747ef?w=900&auto=format&fit=crop&q=80', 20.4600000, 105.1200000, 1);

INSERT INTO `categories` (`id`, `name`, `slug`, `location_type`, `icon`, `description`) VALUES
(1, 'Danh thắng thiên nhiên', 'danh-thang-thien-nhien', 'attraction', 'bi-tree', 'Đồi chè, thung lũng hoa, thác nước, đèo núi và vịnh biển'),
(2, 'Di tích & Bản làng văn hóa', 'di-tich-ban-lang', 'attraction', 'bi-bank', 'Bản làng dân tộc, phố cổ, đền chùa và di tích lịch sử'),
(3, 'Điểm săn mây & Check-in', 'diem-san-may-check-in', 'attraction', 'bi-camera', 'Đỉnh núi săn mây, cầu kính, quán cà phê view thung lũng'),
(4, 'Đặc sản bản địa', 'dac-san-ban-dia', 'food', 'bi-egg-fried', 'Nhà hàng, quán ăn phục vụ món đặc sản truyền thống địa phương'),
(5, 'Quán cà phê & Ăn vặt', 'ca-phe-an-vat', 'food', 'bi-cup-hot', 'Quán cà phê ngắm cảnh, trà chiều và ẩm thực đường phố'),
(6, 'Homestay & Farmstay bản địa', 'homestay-farmstay', 'stay', 'bi-house-heart', 'Homestay gỗ ấm cúng, nhà sàn và farmstay giữa thiên nhiên'),
(7, 'Khách sạn & Eco Resort', 'khach-san-eco-resort', 'stay', 'bi-building', 'Khách sạn tiện nghi, khu nghỉ dưỡng sinh thái và du thuyền');

INSERT INTO `locations` (
  `id`, `province_id`, `category_id`, `name`, `slug`, `location_type`,
  `thumbnail_url`, `short_desc`, `description`, `address`, `latitude`, `longitude`,
  `opening_hours`, `contact_info`, `google_maps_url`, `ref_price`, `price_unit`, `price_updated_at`,
  `ticket_price_info`, `visit_duration`, `seasonal_note`,
  `signature_dishes`, `restaurant_type`, `price_range_per_person`,
  `stay_type`, `room_price_info`, `checkin_checkout_hours`, `amenities`,
  `status`, `created_by`
) VALUES
-- MỘC CHÂU - TÀ XÙA (SƠN LA)
(1, 1, 1, 'Thung lũng mận Nà Ka', 'thung-lung-man-na-ka', 'attraction',
 'https://images.unsplash.com/photo-1490750967868-88aa4486c946?w=800&auto=format&fit=crop&q=80',
 'Thung lũng hoa mận trắng đẹp nhất Mộc Châu mỗi độ xuân về và mùa hái mận chín đỏ tháng 5.',
 'Nằm trên cung đường vào xã Tân Lập, thung lũng mận Nà Ka rộng hơn 100ha phủ kín sắc trắng tinh khôi của hoa mận vào cuối tháng 1 đến hết tháng 2. Sang tháng 4-5, du khách có thể trải nghiệm đeo gùi hái mận hậu chín đỏ ngay tại vườn.',
 'Đường tỉnh 104, Thị trấn Nông trường Mộc Châu, Mộc Châu, Sơn La', 20.8812000, 104.6925000,
 '06:30 - 18:00', 'Ban quản lý vườn Nà Ka: 0974 556 123', 'https://www.google.com/maps?q=20.8812,104.6925', 30000, 'người', '2026-02-01',
 '30.000đ/người lớn (miễn phí trẻ em dưới 1m). Thuê trang phục dân tộc: 50.000đ/bộ.', '1.5 - 2.5 giờ', 'Hoa mận nở rộ từ cuối tháng 1 đến giữa tháng 2; mùa quả chín từ cuối tháng 4 đến tháng 6.',
 NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'active', 1),

(2, 1, 1, 'Đồi chè Trái Tim Mộc Châu', 'doi-che-trai-tim-moc-chau', 'attraction',
 'https://images.unsplash.com/photo-1500382017468-9049fed747ef?w=800&auto=format&fit=crop&q=80',
 'Biểu tượng xanh mướt của cao nguyên Mộc Châu với những luống chè uốn lượn hình trái tim.',
 'Đồi chè Trái Tim Đài Loan nằm tại khu nông trường Mộc Châu, cách trung tâm thị trấn khoảng 10km. Thời điểm đẹp nhất để tham quan là sáng sớm từ 7h00 - 9h00 khi sương còn đọng trên búp chè non hoặc chiều hoàng hôn.',
 'Bản Ôn, Thị trấn Nông trường Mộc Châu, Mộc Châu, Sơn La', 20.8691000, 104.6778000,
 '06:00 - 18:00', 'Hợp tác xã chè Mộc Châu', 'https://www.google.com/maps?q=20.8691,104.6778', 0, 'người', '2026-02-01',
 'Miễn phí vé vào cổng. Gửi xe: 10.000đ/xe máy, 30.000đ/ô tô.', '1 - 1.5 giờ', 'Đẹp quanh năm; lưu ý tháng 12 - đầu tháng 1 một số khu vực có thể đốn chè đông.',
 NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'active', 1),

(3, 1, 1, 'Thác Dải Yếm & Cầu Kính Bạch Long', 'thac-dai-yem-moc-chau', 'attraction',
 'https://images.unsplash.com/photo-1432405972618-c60b0225b8f9?w=800&auto=format&fit=crop&q=80',
 'Ngọn thác 9 tầng thơ mộng giữa núi rừng xã Mường Sang kết hợp khu du lịch sinh thái.',
 'Thác Dải Yếm (hay thác Nàng) tung bọt trắng xóa giữa thung lũng xanh mát. Du khách có thể dạo bộ dưới tán cây cổ thụ, chụp ảnh bên suối và kết hợp trải nghiệm cầu kính hoặc khu vườn hoa ngay cạnh thác.',
 'Quốc lộ 43, Xã Mường Sang, Mộc Châu, Sơn La', 20.8054000, 104.6021000,
 '07:00 - 18:00', 'KDL Thác Dải Yếm: 0212 3866 789', 'https://www.google.com/maps?q=20.8054,104.6021', 100000, 'người', '2026-02-01',
 'Vé tham quan thác Dải Yếm: 100.000đ/người (vé cầu kính mua riêng nếu đi).', '2 giờ', 'Nước thác trong và hiền hòa vào mùa xuân - thu; nước đổ mạnh hùng vĩ vào tháng 6 - 9.',
 NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'active', 1),

(4, 1, 1, 'Rừng thông Bản Áng', 'rung-thong-ban-ang', 'attraction',
 'https://images.unsplash.com/photo-1448375240586-882707db888b?w=800&auto=format&fit=crop&q=80',
 'Hồ nước phẳng lặng bao quanh bởi đồi thông xanh rì và vườn dâu tây Chimi Farm.',
 'Được mệnh danh là Đà Lạt thu nhỏ của Tây Bắc, Rừng thông Bản Áng sở hữu hồ nước tự nhiên rộng 5ha, đường đạp xe dưới tán thông và vườn dâu tây chín mọng từ tháng 12 đến tháng 3.',
 'Xã Đông Sang, Mộc Châu, Sơn La', 20.8189000, 104.6342000,
 '07:00 - 21:00', 'BQL Rừng thông Bản Áng: 0968 112 334', 'https://www.google.com/maps?q=20.8189,104.6342', 80000, 'người', '2026-02-01',
 'Vé vào cổng: 80.000đ/người. Thuê xe đạp đôi: 50.000đ/giờ. Hái dâu tây tính theo kg.', '2 - 3 giờ', 'Mùa dâu tây ngon nhất từ tháng 1 đến tháng 3; buổi sáng sớm mặt hồ có sương mờ rất đẹp.',
 NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'active', 1),

(5, 1, 4, 'Nhà hàng Cá Hồi Xuân Bắc 181', 'nha-hang-ca-hoi-xuan-bac-181', 'food',
 'https://images.unsplash.com/photo-1544025162-d76694265947?w=800&auto=format&fit=crop&q=80',
 'Thưởng thức set cá hồi tươi sống 6 món đặc sản Mộc Châu trong không gian nhà sàn gỗ.',
 'Nhà hàng nổi tiếng lâu năm tại tiểu khu Chiềng Đi với bể nuôi cá hồi, cá tầm nước lạnh tự nhiên. Một set cá hồi đầy đủ gồm gỏi cá hồi, da cá chiên giòn, cá hồi nướng mắc khén, chả cá, lẩu cá hồi và cháo cá.',
 'Tiểu khu Chiềng Đi, Quốc lộ 6, Thị trấn Nông trường Mộc Châu, Sơn La', 20.8315000, 104.6612000,
 '08:30 - 22:00', 'Đặt bàn: 0912 889 181', 'https://www.google.com/maps?q=20.8315,104.6612', 250000, 'người', '2026-02-01',
 NULL, NULL, NULL,
 'Set cá hồi 6 món, lẩu cá tầm măng chua, bê chao Mộc Châu, cải mèo luộc chấm trứng', 'Nhà hàng đặc sản Tây Bắc', '220.000đ - 300.000đ/người',
 NULL, NULL, NULL, NULL, 'active', 1),

(6, 1, 4, 'Quán Bê Chao 70 Mộc Châu', 'quan-be-chao-70-moc-chau', 'food',
 'https://images.unsplash.com/photo-1555939594-58d7cb561ad1?w=800&auto=format&fit=crop&q=80',
 'Đặc sản bê non chao dầu nóng hổi ăn kèm tương gừng, cá suối nướng và xôi ngũ sắc.',
 'Quán ăn địa phương đông khách ngay trục đường quốc lộ 6, nổi tiếng với món bê sữa chao giòn bì mềm thịt, thơm lừng sả gừng và sữa tươi Mộc Châu chiên.',
 'Km70 Quốc lộ 6, Thị trấn Nông trường Mộc Châu, Sơn La', 20.8390000, 104.6510000,
 '07:00 - 21:30', 'Liên hệ: 0984 070 070', 'https://www.google.com/maps?q=20.8390,104.6510', 180000, 'người', '2026-02-01',
 NULL, NULL, NULL,
 'Bê chao thảo quả, cá suối chiên giòn, gà đồi nướng mắc khén, sữa tươi chiên', 'Quán ăn gia đình & đặc sản', '150.000đ - 220.000đ/người',
 NULL, NULL, NULL, NULL, 'active', 1),

(7, 1, 6, 'Mama''s House Hostel & Homestay Mộc Châu', 'mamas-house-moc-chau', 'stay',
 'https://images.unsplash.com/photo-1587061949409-02df41d5e562?w=800&auto=format&fit=crop&q=80',
 'Homestay những căn nhà gỗ nhỏ xinh nằm trên sườn đồi cạnh rừng thông Bản Áng và vườn mơ.',
 'Mama''s House mang phong cách Hàn Quốc ấm áp với các bungalow gỗ áp mái nằm giữa vườn mơ, vườn mận. Buổi tối có khu vực đốt lửa trại, nướng BBQ ngoài trời và quán cà phê sân vườn cực chill.',
 'Bản Áng 3, Xã Đông Sang, Mộc Châu, Sơn La', 20.8224000, 104.6368000,
 'Mở cửa 24/7', 'Hotline đặt phòng: 0989 331 556', 'https://www.google.com/maps?q=20.8224,104.6368', 750000, 'phòng/đêm', '2026-02-01',
 NULL, NULL, NULL, NULL, NULL, NULL,
 'Homestay & Bungalow gỗ', 'Phòng đôi: 650.000đ - 850.000đ/đêm; Phòng gia đình 4 người: 1.200.000đ/đêm (gồm ăn sáng).', 'Nhận phòng 14:00 - Trả phòng 12:00', 'Wifi miễn phí, Bữa sáng bản địa, Sân nướng BBQ, Cho thuê xe máy, Bình nóng lạnh',
 'active', 1),

(8, 1, 3, 'Sống lưng khủng long Tà Xùa & Đỉnh Gió', 'song-lung-khung-long-ta-xua', 'attraction',
 'https://images.unsplash.com/photo-1464822759023-fed622ff2c3b?w=800&auto=format&fit=crop&q=80',
 'Thiên đường săn biển mây bồng bềnh ở độ cao hơn 1.800m tại Bắc Yên, Sơn La.',
 'Cung đường sống lưng khủng long Háng Đồng và khu vực Đỉnh Gió Tà Xùa là tọa độ săn mây nổi tiếng nhất miền Bắc từ tháng 10 đến tháng 3 năm sau.',
 'Xã Háng Đồng, Huyện Bắc Yên, Sơn La', 21.3125000, 104.4589000,
 '05:00 - 18:30', 'Hiệp hội du lịch Tà Xùa', 'https://www.google.com/maps?q=21.3125,104.4589', 0, 'người', '2026-01-20',
 'Tham quan tự do miễn phí. Xe ôm bản địa chở xuống sống lưng: 60.000đ/khứ hồi.', '2 - 3 giờ', 'Tỷ lệ săn mây thành công cao nhất từ tháng 11 đến tháng 3, đặc biệt sau ngày có mưa phùn nhẹ và nắng sớm.',
 NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'active', 1),

-- HÀ GIANG
(9, 2, 1, 'Đèo Mã Pí Lèng & Sông Nho Quế (Hẻm Tu Sản)', 'deo-ma-pi-leng-song-nho-que', 'attraction',
 'https://images.unsplash.com/photo-1559592413-7cec4d0cae2b?w=800&auto=format&fit=crop&q=80',
 'Tứ đại đỉnh đèo Việt Nam nhìn xuống dòng sông Nho Quế xanh màu ngọc bích và hẻm vực Tu Sản.',
 'Hành trình đi thuyền trên sông Nho Quế qua hẻm vực Tu Sản sâu nhất Đông Nam Á kết hợp ngắm toàn cảnh từ Panorama Mã Pí Lèng là trải nghiệm không thể bỏ lỡ khi đến Hà Giang.',
 'Quốc lộ 4C, Xã Pả Vi, Huyện Mèo Vạc, Hà Giang', 23.2295000, 105.4112000,
 '07:00 - 17:30', 'HTX Nông Lâm Dịch vụ Tu Sản: 0965 224 111', 'https://www.google.com/maps?q=23.2295,105.4112', 120000, 'người', '2026-02-01',
 'Vé thuyền sông Nho Quế + xe điện trung chuyển: 120.000đ/người.', '2.5 - 3 giờ', 'Mùa hoa gạo nở đỏ bên bờ sông vào tháng 3; mùa hoa tam giác mạch từ tháng 10 đến tháng 12.',
 NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'active', 1),

(10, 2, 2, 'Cột cờ Lũng Cú & Bản Lô Lô Chải', 'cot-co-lung-cu-lo-lo-chai', 'attraction',
 'https://images.unsplash.com/photo-1506744038136-46273834b3fb?w=800&auto=format&fit=crop&q=80',
 'Điểm cực Bắc thiêng liêng của Tổ quốc và ngôi làng cổ tích của người Lô Lô dưới chân núi Rồng.',
 'Từ đỉnh cột cờ Lũng Cú nhìn xuống là hai hồ nước mắt rồng và bản Lô Lô Chải với những nếp nhà trình tường đất vàng, hàng rào đá và quán cà phê Cực Bắc.',
 'Xã Lũng Cú, Huyện Đồng Văn, Hà Giang', 23.3632000, 105.3174000,
 '07:00 - 18:00', 'BQL Cột cờ Lũng Cú', 'https://www.google.com/maps?q=23.3632,105.3174', 40000, 'người', '2026-02-01',
 'Vé tham quan: 40.000đ/người; xe điện lên chân cột cờ: 15.000đ/lượt.', '2 giờ', 'Tháng 10-11 tam giác mạch nở tím sườn đồi; tháng 2-3 hoa đào hoa lê nở quanh hàng rào đá.',
 NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'active', 1),

(11, 2, 4, 'Nhà hàng Lẩu Gà Đen & Thắng Cố Phố Cổ Đồng Văn', 'lau-ga-den-pho-co-dong-van', 'food',
 'https://images.unsplash.com/photo-1547592180-85f173990554?w=800&auto=format&fit=crop&q=80',
 'Nồi lẩu gà đen H''Mông hầm thuốc bắc nóng hổi giữa tiết trời se lạnh phố cổ Đồng Văn.',
 'Nằm ngay trung tâm khu phố cổ Đồng Văn trăm tuổi, quán phục vụ lẩu gà đen bản địa ăn kèm rau cải mèo, mèn mén, bánh tam giác mạch nướng và cháo ấu tẩu.',
 'Số 18 Phố Cổ Đồng Văn, Thị trấn Đồng Văn, Hà Giang', 23.2775000, 105.3621000,
 '09:00 - 22:30', 'Đặt bàn: 0945 881 229', 'https://www.google.com/maps?q=23.2775,105.3621', 200000, 'người', '2026-02-01',
 NULL, NULL, NULL,
 'Lẩu gà đen H''Mông, cháo ấu tẩu, thịt bò khô gác bếp, bánh cuốn trứng Đồng Văn', 'Nhà hàng ẩm thực vùng cao', '180.000đ - 250.000đ/người',
 NULL, NULL, NULL, NULL, 'active', 1),

(12, 2, 6, 'Làng H''Mông Pả Vi Homestay Mèo Vạc', 'lang-hmong-pa-vi-homestay', 'stay',
 'https://images.unsplash.com/photo-1566073771259-6a8506099945?w=800&auto=format&fit=crop&q=80',
 'Làng văn hóa du lịch cộng đồng dưới chân đèo Mã Pí Lèng với kiến trúc trình tường đặc trưng.',
 'Khu homestay yên bình được quy hoạch theo hình bông hoa đào dưới thung lũng Pả Vi, thuận tiện di chuyển ra bến thuyền sông Nho Quế và phố cổ Đồng Văn.',
 'Thôn Pả Vi Hạ, Xã Pả Vi, Huyện Mèo Vạc, Hà Giang', 23.2140000, 105.4150000,
 'Mở cửa 24/7', 'Lễ tân: 0973 445 889', 'https://www.google.com/maps?q=23.2140,105.4150', 700000, 'phòng/đêm', '2026-02-01',
 NULL, NULL, NULL, NULL, NULL, NULL,
 'Homestay văn hóa bản địa', 'Phòng đôi khép kín: 650.000đ - 800.000đ/đêm; Giường dorm: 180.000đ/người.', 'Nhận phòng 14:00 - Trả phòng 11:30', 'Ăn sáng bánh cuốn nóng, Sưởi ấm mùa đông, Sân giao lưu văn nghệ dân tộc',
 'active', 1),

-- NINH BÌNH
(13, 4, 1, 'Quần thể Danh thắng Tràng An', 'quan-the-danh-thang-trang-an', 'attraction',
 'https://images.unsplash.com/photo-1570366583862-f91883984fde?w=800&auto=format&fit=crop&q=80',
 'Di sản văn hóa và thiên nhiên thế giới với hành trình ngồi đò xuyên hang động đá vôi kỳ vĩ.',
 'Du khách ngồi thuyền chòi tay 3 tiếng theo 1 trong 3 tuyến khám phá hệ thống hang động xuyên thủy, đền Trình, đền Trần và hành cung Vũ Lâm giữa non nước hữu tình.',
 'Đại lộ Tràng An, Xã Trường Yên, Hoa Lư, Ninh Bình', 20.2541000, 105.9154000,
 '07:00 - 16:30', 'Bến thuyền Tràng An: 0229 3622 226', 'https://www.google.com/maps?q=20.2541,105.9154', 250000, 'người', '2026-02-01',
 'Vé đò trọn gói: 250.000đ/người lớn (1 đò ghép 4 khách). Thuê đò riêng: 1.250.000đ/đò.', '3 - 3.5 giờ', 'Đẹp nhất vào mùa lễ hội xuân (tháng 2-4) và mùa lúa chín vàng Tam Cốc lân cận (tháng 5-6).',
 NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'active', 1),

(14, 4, 3, 'Hang Múa & Đầm Sen Ngọa Long', 'hang-mua-ninh-binh', 'attraction',
 'https://images.unsplash.com/photo-1528127269322-539801943592?w=800&auto=format&fit=crop&q=80',
 'Chinh phục gần 500 bậc đá lên đỉnh núi Ngọa Long ngắm toàn cảnh dòng Ngô Đồng và cánh đồng lúa Tam Cốc.',
 'Dưới chân núi Ngọa Long là đầm sen rộng lớn nở hoa từ tháng 5 đến tháng 7; trên đỉnh núi là tháp sống ảo và tượng rồng đá uy nghiêm ôm trọn tầm nhìn 360 độ.',
 'Thôn Khê Đầu Hạ, Xã Ninh Xuân, Hoa Lư, Ninh Bình', 20.2314000, 105.9367000,
 '06:00 - 19:00', 'KDL Hang Múa: 0229 3618 678', 'https://www.google.com/maps?q=20.2314,105.9367', 100000, 'người', '2026-02-01',
 'Vé tham quan: 100.000đ/người.', '1.5 - 2 giờ', 'Tháng 5-6 ngắm lúa vàng Tam Cốc từ đỉnh núi và chụp sen nở dưới chân núi.',
 NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'active', 1),

(15, 4, 4, 'Nhà hàng Dê Núi Chính Thư Ninh Bình', 'nha-hang-de-nui-chinh-thu', 'food',
 'https://images.unsplash.com/photo-1544025162-d76694265947?w=800&auto=format&fit=crop&q=80',
 'Thương hiệu dê núi đá Trường Yên và cơm cháy sốt tim cật trứ danh đất cố đô.',
 'Thịt dê núi chăn thả tự nhiên trên núi đá vôi Ninh Bình săn chắc, chế biến thành dê tái chanh, dê nướng tảng, dê ủ trấu và ăn kèm cơm cháy giòn rụm.',
 'Thôn Khê Thượng, Xã Ninh Xuân, Hoa Lư, Ninh Bình', 20.2460000, 105.9280000,
 '08:00 - 22:00', 'Đặt bàn: 0948 331 555', 'https://www.google.com/maps?q=20.2460,105.9280', 220000, 'người', '2026-02-01',
 NULL, NULL, NULL,
 'Dê tái chanh, cơm cháy sốt dê, dê nướng tảng, canh sơn dược thịt dê', 'Nhà hàng đặc sản cố đô', '180.000đ - 250.000đ/người',
 NULL, NULL, NULL, NULL, 'active', 1),

(16, 4, 7, 'Tam Cốc Garden & Eco Bungalow', 'tam-coc-eco-bungalow', 'stay',
 'https://images.unsplash.com/photo-1582719508461-905c673771fd?w=800&auto=format&fit=crop&q=80',
 'Khu nghỉ dưỡng sinh thái ẩn mình giữa thung lũng lúa và dãy núi đá vôi Tam Cốc.',
 'Những căn nhà mái ngói truyền thống Bắc Bộ có bể bơi vô cực nhìn thẳng ra núi đá vôi và cánh đồng lúa, miễn phí xe đạp dạo quanh làng quê.',
 'Thôn Hải Nham, Xã Ninh Hải, Hoa Lư, Ninh Bình', 20.2185000, 105.9210000,
 'Mở cửa 24/7', 'Đặt phòng: 0229 3889 999', 'https://www.google.com/maps?q=20.2185,105.9210', 1100000, 'phòng/đêm', '2026-02-01',
 NULL, NULL, NULL, NULL, NULL, NULL,
 'Eco Lodge & Bungalow', 'Phòng Deluxe view đồng lúa: 950.000đ - 1.300.000đ/đêm (bao gồm bữa sáng).', 'Nhận phòng 14:00 - Trả phòng 12:00', 'Hồ bơi ngoài trời, Xe đạp miễn phí, Nhà hàng sân vườn, Wifi tốc độ cao',
 'active', 1),

-- SA PA (LÀO CAI)
(17, 3, 1, 'Thung lũng Mường Hoa & Bản Cát Cát', 'thung-lung-muong-hoa-cat-cat', 'attraction',
 'https://images.unsplash.com/photo-1583417319070-4a69db38a482?w=800&auto=format&fit=crop&q=80',
 'Ruộng bậc thang kỳ vĩ đẹp nhất thế giới và bản làng cổ của người H''Mông bên thác Tiên Sa.',
 'Cung đường trekking từ bản Cát Cát qua Lao Chải - Tả Van mở ra khung cảnh ruộng bậc thang nối tiếp nhau đến tận chân mây trên dãy Hoàng Liên Sơn.',
 'Xã San Sả Hồ & Lao Chải, Thị xã Sa Pa, Lào Cai', 22.3210000, 103.8315000,
 '06:00 - 18:30', 'KDL Bản Cát Cát: 0214 3871 999', 'https://www.google.com/maps?q=22.3210,103.8315', 150000, 'người', '2026-02-01',
 'Vé tham quan Bản Cát Cát: 150.000đ/người lớn.', '2.5 - 3.5 giờ', 'Mùa nước đổ lấp lánh tháng 4-5; mùa lúa chín vàng rực rỡ từ cuối tháng 8 đến hết tháng 9.',
 NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'active', 1),

(18, 3, 3, 'Đỉnh Fansipan - Nóc nhà Đông Dương', 'dinh-fansipan-sapa', 'attraction',
 'https://images.unsplash.com/photo-1464822759023-fed622ff2c3b?w=800&auto=format&fit=crop&q=80',
 'Chinh phục độ cao 3.143m bằng tàu hỏa leo núi Mường Hoa và cáp treo xuyên biển mây.',
 'Tổ hợp Sun World Fansipan Legend với quần thể tâm linh kỳ vĩ trên mây, vườn hoa đỗ quyên cổ thụ nở rộ tháng 3-4 và cơ hội săn băng giá mùa đông.',
 'Khu du lịch Nguyễn Chí Thanh, Thị xã Sa Pa, Lào Cai', 22.3033000, 103.7750000,
 '07:30 - 17:30', 'Sun World Fansipan: 0948 308 888', 'https://www.google.com/maps?q=22.3033,103.7750', 800000, 'người', '2026-02-01',
 'Vé cáp treo khứ hồi: 800.000đ/người lớn; Tàu hỏa Mường Hoa: 150.000đ/khứ hồi.', '4 - 5 giờ', 'Tháng 3-4 mùa hoa đỗ quyên; tháng 9-11 trời trong xanh ít mù; tháng 12-1 săn băng tuyết.',
 NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'active', 1),

-- QUẢNG NINH (HẠ LONG - CÔ TÔ)
(19, 5, 1, 'Vịnh Hạ Long - Hang Sửng Sốt & Đảo Ti Tốp', 'vinh-ha-long-hang-sung-sot-ti-top', 'attraction',
 'https://images.unsplash.com/photo-1528127269322-539801943592?w=800&auto=format&fit=crop&q=80',
 'Hành trình du thuyền tuyến 2 ngắm hàng nghìn đảo đá vôi, chèo kayak hang Luồn và tắm biển Ti Tốp.',
 'Tuyến tham quan số 2 trên vịnh Hạ Long đưa du khách qua Hòn Gà Chọi, khám phá hang Sửng Sốt rộng lớn, chèo kayak tại Hang Luồn và leo đỉnh đảo Ti Tốp ngắm toàn cảnh vịnh.',
 'Cảng tàu khách quốc tế Hạ Long, Bãi Cháy, TP. Hạ Long, Quảng Ninh', 20.8482000, 107.0912000,
 '07:00 - 17:30', 'Ban quản lý Vịnh Hạ Long: 0203 3824 867', 'https://www.google.com/maps?q=20.8482,107.0912', 490000, 'người', '2026-02-01',
 'Vé tham quan tuyến 2 (290.000đ) + Vé tàu ghép 6 tiếng (200.000đ) = 490.000đ/người.', '5 - 6 giờ', 'Tháng 4 - tháng 8 nắng đẹp thích hợp tắm biển, chèo kayak; tháng 10 - 11 tiết trời mát mẻ nghỉ dưỡng du thuyền.',
 NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'active', 1),

-- CAO BẰNG & PÙ LUÔNG & HÀ NỘI
(20, 6, 1, 'Thác Bản Giốc & Động Ngườm Ngao', 'thac-ban-gioc-cao-bang', 'attraction',
 'https://images.unsplash.com/photo-1432405972618-c60b0225b8f9?w=800&auto=format&fit=crop&q=80',
 'Thác nước tự nhiên lớn nhất Đông Nam Á nằm bên dòng sông Quây Sơn màu ngọc bích.',
 'Thác Bản Giốc đổ xuống qua nhiều tầng đá vôi giữa cánh đồng lúa Trùng Khánh. Cách thác 3km là động Ngườm Ngao với hệ thống nhũ đá hình đài sen độc đáo.',
 'Xã Đàm Thủy, Huyện Trùng Khánh, Cao Bằng', 22.8557000, 106.7225000,
 '07:00 - 17:30', 'KDL Thác Bản Giốc: 0206 3826 111', 'https://www.google.com/maps?q=22.8557,106.7225', 90000, 'người', '2026-02-01',
 'Vé thác Bản Giốc: 45.000đ/người; Vé bè mảng ra chân thác: 50.000đ/người.', '2.5 - 3 giờ', 'Đẹp nhất từ tháng 8 đến tháng 10 khi nước thác xanh trong kết hợp mùa lúa chín vàng dưới chân thác.',
 NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'active', 1),

(21, 8, 1, 'Khu bảo tồn thiên nhiên Pù Luông & Bản Đôn', 'khu-bao-ton-pu-luong-ban-don', 'attraction',
 'https://images.unsplash.com/photo-1500382017468-9049fed747ef?w=800&auto=format&fit=crop&q=80',
 'Thung lũng ruộng bậc thang ôm trọn bản làng người Thái và những guồng nước tre bên suối Chàm.',
 'Pù Luông hấp dẫn du khách bởi không khí trong lành, những thửa ruộng bậc thang ngay trước hiên homestay tại Bản Đôn, thác Hiêu và phiên chợ Phố Đoàn sáng thứ 5, Chủ nhật.',
 'Xã Thành Lâm, Huyện Bá Thước, Thanh Hóa', 20.4530000, 105.1610000,
 '06:00 - 19:00', 'BQL Pù Luông: 0237 3556 789', 'https://www.google.com/maps?q=20.4530,105.1610', 0, 'người', '2026-02-01',
 'Miễn phí tham quan Bản Đôn; đi bè tre suối Chàm: 100.000đ/người.', '3 giờ', 'Hai mùa lúa chín đẹp nhất trong năm: vụ chiêm (cuối tháng 5 - đầu tháng 6) và vụ mùa (tháng 9 - tháng 10).',
 NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'active', 1),

(22, 7, 2, 'Phố cổ Hà Nội - Hồ Hoàn Kiếm & Phố Phan Đình Phùng', 'pho-co-ha-noi-ho-hoan-kiem', 'attraction',
 'https://images.unsplash.com/photo-1509030450996-dd1a26dda07a?w=800&auto=format&fit=crop&q=80',
 'Trải nghiệm mùa thu Hà Nội với hàng sấu cổ thụ Phan Đình Phùng, cốm làng Vòng và cà phê trứng phố cổ.',
 'Dạo bước quanh Hồ Gươm, ghé Nhà Thờ Lớn thưởng thức xôi cốm, chụp ảnh dưới nắng thu vàng trên đường Phan Đình Phùng và ngắm hoàng hôn Hồ Tây.',
 'Quận Hoàn Kiếm & Ba Đình, TP. Hà Nội', 21.0285000, 105.8542000,
 'Mở cửa cả ngày', 'Trung tâm thông tin du lịch Hà Nội', 'https://www.google.com/maps?q=21.0285,105.8542', 0, 'người', '2026-02-01',
 'Tham quan tự do miễn phí; vé đền Ngọc Sơn: 30.000đ/người.', '3 - 4 giờ', 'Tháng 9 - tháng 11 là mùa thu Hà Nội đẹp nhất trong năm, tiết trời mát dịu, hương hoa sữa và cốm mới.',
 NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'active', 1),

(23, 3, 4, 'Nhà hàng Lẩu Cá Tầm & Cá Hồi Vua Sa Pa', 'lau-ca-tam-ca-hoi-vua-sapa', 'food',
 'https://images.unsplash.com/photo-1547592180-85f173990554?w=800&auto=format&fit=crop&q=80',
 'Đặc sản lẩu cá tầm, gỏi cá hồi tươi sống nuôi bằng nguồn nước lạnh thác Bạc Sa Pa.',
 'Không gian nhà gỗ ấm cúng ngay trung tâm thị xã Sa Pa, nổi tiếng với nồi lẩu cá tầm măng chua cay nồng ăn kèm rau su su, cải mèo và nấm hương rừng.',
 'Số 08 Xuân Viên, Thị xã Sa Pa, Lào Cai', 22.3352000, 103.8415000,
 '09:00 - 22:30', 'Đặt bàn: 0214 3872 555', 'https://www.google.com/maps?q=22.3352,103.8415', 250000, 'người', '2026-02-01',
 NULL, NULL, NULL,
 'Lẩu cá tầm măng chua, gỏi cá hồi Tây Bắc, gà đen nướng tiêu rừng, ngọn su su xào tỏi', 'Nhà hàng đặc sản Tây Bắc', '200.000đ - 300.000đ/người',
 NULL, NULL, NULL, NULL, 'active', 1),

(24, 3, 6, 'Eco Palms House - Sapa Retreat View Mường Hoa', 'eco-palms-house-sapa-retreat', 'stay',
 'https://images.unsplash.com/photo-1582719508461-905c673771fd?w=800&auto=format&fit=crop&q=80',
 'Bungalow tre mộc mạc nằm trên sườn đồi bản Lao Chải nhìn trọn dãy Hoàng Liên Sơn và thung lũng Mường Hoa.',
 'Mỗi căn bungalow lấy cảm hứng từ kiến trúc nhà truyền thống của 5 dân tộc thiểu số tại Sa Pa, có ban công riêng đón biển mây sớm mai và ngắm ruộng bậc thang.',
 'Bản Lao Chải, Thị xã Sa Pa, Lào Cai', 22.3125000, 103.8490000,
 'Mở cửa 24/7', 'Lễ tân: 0987 622 334', 'https://www.google.com/maps?q=22.3125,103.8490', 950000, 'phòng/đêm', '2026-02-01',
 NULL, NULL, NULL, NULL, NULL, NULL,
 'Eco Bungalow & Retreat', 'Bungalow view thung lũng Mường Hoa: 900.000đ - 1.250.000đ/đêm (gồm bữa sáng).', 'Nhận phòng 14:00 - Trả phòng 12:00', 'Ban công săn mây, Bữa sáng bản địa, Ngâm chân lá thuốc Dao đỏ',
 'active', 1),

(25, 5, 4, 'Nhà hàng Hải Sản Hồng Hạnh 3 & Chả Mực Giã Tay Hạ Long', 'nha-hang-hong-hanh-3-ha-long', 'food',
 'https://images.unsplash.com/photo-1544025162-d76694265947?w=800&auto=format&fit=crop&q=80',
 'Thương hiệu ẩm thực hải sản tươi sống và chả mực giã tay trứ danh bên bờ vịnh Hạ Long.',
 'Thực đơn nổi bật với chả mực giã tay vàng ruộm, bề bề rang muối, cù kỳ hấp sả, hà nướng mỡ hành và canh ngán nấu mồng tơi.',
 'Số 50 Hạ Long, Bãi Cháy, TP. Hạ Long, Quảng Ninh', 20.9558000, 107.0462000,
 '09:30 - 22:00', 'Đặt bàn: 0203 3812 345', 'https://www.google.com/maps?q=20.9558,107.0462', 300000, 'người', '2026-02-01',
 NULL, NULL, NULL,
 'Chả mực giã tay Hạ Long, bề bề rang muối, tôm hùm nướng phô mai, bún bề bề', 'Nhà hàng hải sản vịnh biển', '250.000đ - 400.000đ/người',
 NULL, NULL, NULL, NULL, 'active', 1),

(26, 5, 7, 'DeLaSea Ha Long Hotel & Bay View Bãi Cháy', 'delasea-ha-long-hotel-bai-chay', 'stay',
 'https://images.unsplash.com/photo-1566073771259-6a8506099945?w=800&auto=format&fit=crop&q=80',
 'Khách sạn nghỉ dưỡng hiện đại sát bờ biển Bãi Cháy với tầm nhìn toàn cảnh Vịnh Hạ Long.',
 'Chỉ cách Cảng tàu khách quốc tế Hạ Long và bãi tắm Bãi Cháy 5 phút di chuyển, thuận tiện cho lịch trình kết hợp du thuyền vịnh và khám phá phố biển.',
 'Đại lộ Hạ Long Marine, Bãi Cháy, TP. Hạ Long, Quảng Ninh', 20.9585000, 107.0520000,
 'Mở cửa 24/7', 'Đặt phòng: 0203 3668 899', 'https://www.google.com/maps?q=20.9585,107.0520', 1200000, 'phòng/đêm', '2026-02-01',
 NULL, NULL, NULL, NULL, NULL, NULL,
 'Khách sạn 4-5 sao view vịnh', 'Phòng Deluxe Ocean View: 1.100.000đ - 1.400.000đ/đêm (gồm buffet sáng).', 'Nhận phòng 14:00 - Trả phòng 12:00', 'Hồ bơi vô cực, Buffet sáng, Xe điện ra bãi biển',
 'active', 1),

(27, 6, 2, 'Khu di tích Quốc gia Đặc biệt Pác Bó & Suối Lê Nin', 'khu-di-tich-pac-bo-suoi-le-nin', 'attraction',
 'https://images.unsplash.com/photo-1506744038136-46273834b3fb?w=800&auto=format&fit=crop&q=80',
 'Dòng suối Lê Nin xanh ngọc bích dưới chân núi Các Mác và hang Cốc Bó lịch sử.',
 'Nơi Chủ tịch Hồ Chí Minh từng sống và làm việc sau 30 năm bôn ba tìm đường cứu nước, nổi bật với làn nước suối Lê Nin trong vắt nhìn thấu đáy đá cuội.',
 'Bản Pác Bó, Xã Trường Hà, Huyện Hà Quảng, Cao Bằng', 22.9792000, 106.0528000,
 '07:00 - 17:30', 'BQL Di tích Pác Bó: 0206 3862 215', 'https://www.google.com/maps?q=22.9792,106.0528', 45000, 'người', '2026-02-01',
 'Vé tham quan + xe điện khứ hồi: 45.000đ/người.', '2 - 2.5 giờ', 'Nước suối Lê Nin xanh trong nhất từ tháng 8 đến tháng 4 năm sau (tránh những ngày mưa lũ tháng 6-7).',
 NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'active', 1),

(28, 6, 4, 'Nhà hàng Vịt Quay 7 Vị & Phở Chua Cao Bằng', 'vit-quay-7-vi-pho-chua-cao-bang', 'food',
 'https://images.unsplash.com/photo-1547592180-85f173990554?w=800&auto=format&fit=crop&q=80',
 'Đặc sản vịt quay 7 vị ướp lá mắc mật rừng và phở chua gia truyền miền non nước Cao Bằng.',
 'Thưởng thức vịt quay 7 vị da giòn thơm lừng hương mắc mật, phở chua trộn thịt ba chỉ quay, khâu nhục, lạp sườn hun khói và hạt dẻ Trùng Khánh rang bơ.',
 'Phố Kim Đồng, Phường Hợp Giang, TP. Cao Bằng', 22.6655000, 106.2580000,
 '08:00 - 22:00', 'Đặt bàn: 0915 442 668', 'https://www.google.com/maps?q=22.6655,106.2580', 160000, 'người', '2026-02-01',
 NULL, NULL, NULL,
 'Vịt quay 7 vị mắc mật, phở chua Cao Bằng, bánh cuốn canh xương, hạt dẻ Trùng Khánh', 'Nhà hàng ẩm thực Đông Bắc', '120.000đ - 200.000đ/người',
 NULL, NULL, NULL, NULL, 'active', 1),

(29, 6, 6, 'Khuổi Ky Eco Homestay - Làng Đá Cổ Bản Giốc', 'khuoi-ky-eco-homestay-ban-gioc', 'stay',
 'https://images.unsplash.com/photo-1587061949409-02df41d5e562?w=800&auto=format&fit=crop&q=80',
 'Nghỉ đêm trong những ngôi nhà sàn đá 400 năm tuổi của người Tày cách thác Bản Giốc chỉ 2km.',
 'Làng đá cổ Khuổi Ky nằm giữa thác Bản Giốc và động Ngườm Ngao, giữ nguyên kiến trúc nhà sàn bằng đá độc đáo cùng suối nhỏ chảy quanh bản.',
 'Làng đá Khuổi Ky, Xã Đàm Thủy, Huyện Trùng Khánh, Cao Bằng', 22.8485000, 106.7115000,
 'Mở cửa 24/7', 'Lễ tân: 0984 312 779', 'https://www.google.com/maps?q=22.8485,106.7115', 700000, 'phòng/đêm', '2026-02-01',
 NULL, NULL, NULL, NULL, NULL, NULL,
 'Homestay nhà sàn đá truyền thống', 'Phòng đôi khép kín nhà đá: 650.000đ - 750.000đ/đêm (gồm bữa sáng).', 'Nhận phòng 14:00 - Trả phòng 11:30', 'Trải nghiệm hát Then đàn Tính, Bữa tối gia đình người Tày, Gần sát Thác Bản Giốc',
 'active', 1),

(30, 1, 6, 'Mando Homestay & Tiệm Cà Phê Đỉnh Gió Tà Xùa', 'mando-homestay-dinh-gio-ta-xua', 'stay',
 'https://images.unsplash.com/photo-1587061949409-02df41d5e562?w=800&auto=format&fit=crop&q=80',
 'Tổ hợp bungalow săn mây và quán cà phê ngắm hoàng hôn đẹp nhất trên Đỉnh Gió Tà Xùa.',
 'Nằm ở độ cao hơn 1.800m, ngay từ cửa sổ phòng nghỉ du khách có thể chiêm ngưỡng biển mây cuồn cuộn dưới thung lũng và thưởng thức trà Shan Tuyết cổ thụ.',
 'Khu Đỉnh Gió, Xã Tà Xùa, Huyện Bắc Yên, Sơn La', 21.2582000, 104.4412000,
 'Mở cửa 24/7', 'Đặt phòng: 0968 552 119', 'https://www.google.com/maps?q=21.2582,104.4412', 650000, 'phòng/đêm', '2026-02-01',
 NULL, NULL, NULL, NULL, NULL, NULL,
 'Homestay & Cloud Cafe', 'Phòng gỗ kính săn mây: 600.000đ - 800.000đ/đêm (2 người).', 'Nhận phòng 13:30 - Trả phòng 11:30', 'View biển mây trực diện, Lẩu gà đen buổi tối, Cho thuê xe máy đi Sống lưng khủng long',
 'active', 1),

(31, 8, 7, 'Pù Luông Eco Garden & Retreat Bản Đôn', 'pu-luong-eco-garden-ban-don', 'stay',
 'https://images.unsplash.com/photo-1582719508461-905c673771fd?w=800&auto=format&fit=crop&q=80',
 'Khu nghỉ dưỡng sinh thái có hồ bơi vô cực tràn bờ nhìn thẳng ra thung lũng ruộng bậc thang Bản Đôn.',
 'Được bao quanh bởi những thửa ruộng bậc thang lúa chín vàng óng vào tháng 5-6 và tháng 9-10, không gian yên tĩnh tách biệt hoàn toàn với phố thị.',
 'Bản Đôn, Xã Thành Lâm, Huyện Bá Thước, Thanh Hóa', 20.4512000, 105.1645000,
 'Mở cửa 24/7', 'Lễ tân: 0979 884 321', 'https://www.google.com/maps?q=20.4512,105.1645', 950000, 'phòng/đêm', '2026-02-01',
 NULL, NULL, NULL, NULL, NULL, NULL,
 'Eco Lodge & Infinity Pool', 'Bungalow view ruộng bậc thang: 850.000đ - 1.150.000đ/đêm (gồm bữa sáng).', 'Nhận phòng 14:00 - Trả phòng 12:00', 'Bể bơi vô cực nước suối tự nhiên, Trekking bản làng, Nhà hàng nhà sàn Thái',
 'active', 1),

(32, 8, 4, 'Nhà hàng Ẩm thực Vịt Cổ Lũng & Cá Suối Nướng Bản Đôn', 'nha-hang-vit-co-lung-pu-luong', 'food',
 'https://images.unsplash.com/photo-1544025162-d76694265947?w=800&auto=format&fit=crop&q=80',
 'Thưởng thức vịt Cổ Lũng quay lá mắc mật, cá suối nướng pa pỉnh tộp và cơm lam ống nứa của người Thái.',
 'Giống vịt Cổ Lũng bản địa nổi tiếng thịt dày, xương nhỏ, thơm ngọt tự nhiên được nuôi thả trên các dòng suối mát lạnh của khu bảo tồn Pù Luông.',
 'Trung tâm Bản Đôn, Xã Thành Lâm, Bá Thước, Thanh Hóa', 20.4545000, 105.1595000,
 '08:00 - 21:30', 'Đặt bàn: 0942 667 881', 'https://www.google.com/maps?q=20.4545,105.1595', 180000, 'người', '2026-02-01',
 NULL, NULL, NULL,
 'Vịt Cổ Lũng quay mắc mật, cá suối nướng, măng đắng chấm chẩm chéo, cơm lam Pù Luông', 'Nhà hàng nhà sàn dân tộc Thái', '150.000đ - 220.000đ/người',
 NULL, NULL, NULL, NULL, 'active', 1),

(33, 2, 5, 'Tiệm Cà Phê Phố Cổ Đồng Văn & Bánh Cuốn Trứng Bà Hà', 'ca-phe-pho-co-banh-cuon-ba-ha-dong-van', 'food',
 'https://images.unsplash.com/photo-1501339847302-ac426a4a7cbb?w=800&auto=format&fit=crop&q=80',
 'Thưởng thức bánh cuốn trứng nóng chan nước xương hầm và cà phê trong ngôi nhà trình tường hơn 100 năm tuổi.',
 'Buổi sáng sớm tại phố cổ Đồng Văn se lạnh, ngồi bên bếp than hồng đợi từng đĩa bánh cuốn trứng lòng đào nóng hổi rồi nhâm nhi ly cà phê tại ngôi nhà cổ họ Lương.',
 'Số 31 Phố Cổ Đồng Văn, Thị trấn Đồng Văn, Hà Giang', 23.2779000, 105.3626000,
 '06:00 - 22:00', 'Quán Bà Hà: 0978 214 336', 'https://www.google.com/maps?q=23.2779,105.3626', 65000, 'người', '2026-02-01',
 NULL, NULL, NULL,
 'Bánh cuốn trứng nước xương, cháo ấu tẩu, cà phê Phố Cổ, trà gừng mật ong bạc hà', 'Quán ăn sáng & Cà phê di sản', '45.000đ - 90.000đ/người',
 NULL, NULL, NULL, NULL, 'active', 1),

(34, 3, 5, 'Viettrekking Coffee & Restaurant Săn Mây Hoàng Liên', 'viettrekking-coffee-sapa', 'food',
 'https://images.unsplash.com/photo-1501339847302-ac426a4a7cbb?w=800&auto=format&fit=crop&q=80',
 'Tọa độ ngắm biển mây đỉnh Fansipan và đoàn tàu hỏa leo núi Mường Hoa đỏ rực chạy ngang thung lũng.',
 'Quán cà phê và nhà hàng có ban công ngoài trời rộng lớn nhìn thẳng ra dãy Hoàng Liên Sơn và tuyến đường sắt leo núi Mường Hoa đẹp như khung cảnh châu Âu.',
 'Số 33 Hoàng Liên, Thị xã Sa Pa, Lào Cai', 22.3328000, 103.8385000,
 '06:30 - 22:00', 'Hotline: 0214 6525 555', 'https://www.google.com/maps?q=22.3328,103.8385', 95000, 'người', '2026-02-01',
 NULL, NULL, NULL,
 'Trà quế táo đỏ Tây Bắc, Cacao nóng kem phô mai, Bánh sừng bò nướng, Set ăn sáng săn mây', 'Quán cà phê & Nhà hàng săn mây', '65.000đ - 180.000đ/người',
 NULL, NULL, NULL, NULL, 'active', 1),

(35, 4, 5, 'Phố Cổ Hoa Lư & Tiệm Bánh Đa Cá Rô Đồng Ninh Bình', 'pho-co-hoa-lu-am-thuc-ninh-binh', 'food',
 'https://images.unsplash.com/photo-1509030450996-dd1a26dda07a?w=800&auto=format&fit=crop&q=80',
 'Không gian phố cổ lên đèn lung linh bên hồ Kỳ Lân cùng hàng chục gian hàng đặc sản cố đô Hoa Lư.',
 'Điểm dạo chơi và ẩm thực đêm nổi bật nhất Ninh Bình với hai tòa bảo tháp phát sáng giữa hồ, thưởng thức bánh đa cá rô đồng, bánh mật, chè sen long nhãn và ngồi thuyền hồ Kỳ Lân.',
 'Khu núi Kỳ Lân, Phường Tân Thành, TP. Ninh Bình', 20.2582000, 105.9685000,
 '07:00 - 23:00', 'BQL Phố cổ Hoa Lư: 0941 332 889', 'https://www.google.com/maps?q=20.2582,105.9685', 85000, 'người', '2026-02-01',
 NULL, NULL, NULL,
 'Bánh đa cá rô đồng, miến lươn Ninh Bình, chè sen long nhãn, bánh cuốn chả nướng', 'Phố ẩm thực & Không gian văn hóa', '50.000đ - 120.000đ/người',
 NULL, NULL, NULL, NULL, 'active', 1),

(36, 5, 2, 'Khu Di Tích Danh Thắng Yên Tử & Đỉnh Chùa Đồng', 'khu-di-tich-danh-thang-yen-tu', 'attraction',
 'https://images.unsplash.com/photo-1528127269322-539801943592?w=800&auto=format&fit=crop&q=80',
 'Kinh đô Phật giáo Trúc Lâm Việt Nam với hành trình cáp treo xuyên rừng tùng cổ thụ 700 năm lên Chùa Đồng.',
 'Nằm trên độ cao 1.068m của dãy núi Đông Triều, Yên Tử vừa mang vẻ đẹp linh thiêng trầm mặc mùa lễ hội xuân (tháng 1-3) vừa là điểm nghỉ dưỡng tĩnh tâm dưới chân núi.',
 'Xã Thượng Yên Công, TP. Uông Bí, Quảng Ninh', 21.1585000, 106.7165000,
 '05:30 - 18:30', 'KDL Yên Tử: 0203 3669 789', 'https://www.google.com/maps?q=21.1585,106.7165', 350000, 'người', '2026-02-01',
 'Vé thắng cảnh: 40.000đ + Cáp treo khứ hồi 2 tuyến: 310.000đ/người.', '4 - 5 giờ', 'Đẹp nhất từ tháng 1 đến tháng 3 mùa lễ hội xuân và tháng 10-11 mùa thu mây phủ đỉnh Chùa Đồng.',
 NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'active', 1),

(37, 5, 5, 'Phố Ẩm Thực Bánh Cuốn Chả Mực Bà Ngân & Sữa Chua Trân Châu Hạ Long', 'banh-cuon-cha-muc-sua-chua-ha-long', 'food',
 'https://images.unsplash.com/photo-1544025162-d76694265947?w=800&auto=format&fit=crop&q=80',
 'Hai món ăn biểu tượng không thể bỏ lỡ khi ghé Hạ Long: bánh cuốn nóng ăn kèm chả mực giã tay và sữa chua trân châu cốt dừa.',
 'Bánh cuốn tráng mỏng tang rắc hành phi thơm lừng ăn cùng miếng chả mực giã tay giòn sần sật, tráng miệng bằng cốc sữa chua dẻo mát lạnh với trân châu cốt dừa nóng hổi.',
 'Số 34 Đoàn Thị Điểm, Phường Bạch Đằng, Hòn Gai, TP. Hạ Long', 20.9512000, 107.0785000,
 '06:00 - 22:30', 'Quán Bà Ngân: 0203 3825 112', 'https://www.google.com/maps?q=20.9512,107.0785', 75000, 'người', '2026-02-01',
 NULL, NULL, NULL,
 'Bánh cuốn chả mực giã tay, bún cù kỳ, sữa chua trân châu cốt dừa Hạ Long', 'Quán đặc sản địa phương', '55.000đ - 100.000đ/người',
 NULL, NULL, NULL, NULL, 'active', 1),

(38, 6, 5, 'Quán Bánh Cuốn Canh Xương & Bánh Áp Chao Cô Xuân Cao Bằng', 'banh-cuon-canh-xuong-ap-chao-cao-bang', 'food',
 'https://images.unsplash.com/photo-1547592180-85f173990554?w=800&auto=format&fit=crop&q=80',
 'Thưởng thức bánh cuốn chan nước ninh xương ống ngọt thanh kèm trứng chần, giò lụa và bánh áp chao thịt vịt.',
 'Khác với bánh cuốn miền xuôi chấm nước mắm, bánh cuốn Cao Bằng ăn cùng bát nước dùng ninh từ xương ống lợn bản trong vắt, thêm quả trứng gà ta lòng đào và măng ớt mắc mật.',
 'Số 12 Phố Vườn Cam, Phường Hợp Giang, TP. Cao Bằng', 22.6672000, 106.2610000,
 '06:00 - 21:00', 'Cô Xuân: 0983 712 445', 'https://www.google.com/maps?q=22.6672,106.2610', 55000, 'người', '2026-02-01',
 NULL, NULL, NULL,
 'Bánh cuốn canh xương trứng chần, bánh áp chao nhân thịt vịt, thạch đen Trùng Khánh', 'Quán quà sáng & Ăn vặt bản địa', '40.000đ - 70.000đ/người',
 NULL, NULL, NULL, NULL, 'active', 1),

(39, 7, 2, 'Làng Cổ Đường Lâm & Thành Cổ Sơn Tây', 'lang-co-duong-lam-son-tay', 'attraction',
 'https://images.unsplash.com/photo-1509030450996-dd1a26dda07a?w=800&auto=format&fit=crop&q=80',
 'Ngôi làng cổ đá ong 400 năm tuổi ven đô Hà Nội với cổng làng Mông Phụ, đình cổ và tương nếp truyền thống.',
 'Chỉ cách trung tâm Hà Nội 45km, Đường Lâm lưu giữ gần 100 ngôi nhà cổ bằng đá ong vàng óng, giếng nước sân đình, chùa Mía và những chum tương nếp phơi nắng thơm nồng.',
 'Xã Đường Lâm, Thị xã Sơn Tây, TP. Hà Nội', 21.1562000, 105.4685000,
 '07:00 - 18:00', 'BQL Di tích Đường Lâm: 024 3361 4888', 'https://www.google.com/maps?q=21.1562,105.4685', 20000, 'người', '2026-02-01',
 'Vé tham quan làng cổ: 20.000đ/người; thuê xe đạp dạo làng: 40.000đ/buổi.', '3 - 4 giờ', 'Đẹp nhất vào mùa thu (tháng 9-11) và mùa xuân lễ hội (tháng 1-3).',
 NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'active', 1),

(40, 7, 5, 'Phở Thìn Bờ Hồ & Cà Phê Trứng Giảng Phố Cổ Hà Nội', 'pho-thin-bo-ho-ca-phe-trung-giang', 'food',
 'https://images.unsplash.com/photo-1501339847302-ac426a4a7cbb?w=800&auto=format&fit=crop&q=80',
 'Trải nghiệm trọn vẹn buổi sáng Hà Nội cổ điển với bát phở bò nước trong truyền thống và tách cà phê trứng kem béo ngậy.',
 'Nằm ngay sát Hồ Gươm và phố Nguyễn Hữu Huân, đây là hai địa chỉ ẩm thực di sản gắn liền với ký ức phố cổ Hà Nội suốt hơn nửa thế kỷ.',
 'Số 61 Đinh Tiên Hoàng & 39 Nguyễn Hữu Huân, Quận Hoàn Kiếm, Hà Nội', 21.0318000, 105.8532000,
 '06:30 - 22:00', 'Hotline: 024 3824 5115', 'https://www.google.com/maps?q=21.0318,105.8532', 95000, 'người', '2026-02-01',
 NULL, NULL, NULL,
 'Phở bò chín tái truyền thống, Cà phê trứng nóng/đá, Cacao trứng, Xôi cốm hạt sen', 'Ẩm thực di sản Phố cổ Hà Nội', '60.000đ - 120.000đ/người',
 NULL, NULL, NULL, NULL, 'active', 1),

(41, 7, 4, 'Chả Cá Thăng Long & Bún Chả Hàng Mành Phố Cổ', 'cha-ca-thang-long-bun-cha-hang-manh', 'food',
 'https://images.unsplash.com/photo-1544025162-d76694265947?w=800&auto=format&fit=crop&q=80',
 'Tinh hoa ẩm thực đất Kinh Kỳ với chả cá lăng nướng nghệ thì là và bún chả nướng than hoa truyền thống.',
 'Cá lăng tươi ướp riềng mẻ nghệ vàng ươm đảo nóng trên chảo gang cùng thì là, hành hoa, ăn kèm bún rối, lạc rang và mắm tôm đánh sủi bọt chanh cốm.',
 'Số 21 Đường Thành, Quận Hoàn Kiếm, TP. Hà Nội', 21.0332000, 105.8478000,
 '10:00 - 21:30', 'Đặt bàn: 024 3828 6007', 'https://www.google.com/maps?q=21.0332,105.8478', 180000, 'người', '2026-02-01',
 NULL, NULL, NULL,
 'Chả cá lăng Thăng Long, lòng cá xào giòn, bún chả que tre Hàng Mành, nem cua bể', 'Nhà hàng ẩm thực truyền thống Hà Nội', '150.000đ - 250.000đ/người',
 NULL, NULL, NULL, NULL, 'active', 1),

(42, 7, 7, 'Hanoi La Siesta Classic Ma May Boutique Hotel', 'hanoi-la-siesta-classic-ma-may', 'stay',
 'https://images.unsplash.com/photo-1566073771259-6a8506099945?w=800&auto=format&fit=crop&q=80',
 'Khách sạn Boutique phong cách Đông Dương nằm ngay trung tâm 36 phố phường Hà Nội, cách Hồ Gươm 5 phút đi bộ.',
 'Không gian kiến trúc Indochine sang trọng, ấm cúng với nhà hàng Red Bean và rooftop bar ngắm toàn cảnh mái ngói phố cổ Hà Nội.',
 'Số 94 Mã Mây, Phường Hàng Buồm, Quận Hoàn Kiếm, Hà Nội', 21.0345000, 105.8538000,
 'Mở cửa 24/7', 'Lễ tân: 024 3926 3641', 'https://www.google.com/maps?q=21.0345,105.8538', 1350000, 'phòng/đêm', '2026-02-01',
 NULL, NULL, NULL, NULL, NULL, NULL,
 'Boutique Hotel Phố Cổ', 'Phòng Deluxe Indochine: 1.250.000đ - 1.600.000đ/đêm (bao gồm bữa sáng).', 'Nhận phòng 14:00 - Trả phòng 12:00', 'Rooftop Bar view phố cổ, Buffet sáng phở Hà Nội, Spa thảo mộc, Đi bộ ra Hồ Gươm',
 'active', 1),

(43, 8, 2, 'Thung Lũng Bản Lác Mai Châu & Đèo Đá Trắng Thung Khe', 'thung-lung-ban-lac-mai-chau', 'attraction',
 'https://images.unsplash.com/photo-1500382017468-9049fed747ef?w=800&auto=format&fit=crop&q=80',
 'Bản làng người Thái trắng yên bình giữa cánh đồng lúa Mai Châu và cung đèo Đá Trắng phủ sương mù.',
 'Dạo xe đạp xuyên qua những nếp nhà sàn gỗ tại Bản Lác - Pom Coọng, ngắm dệt thổ cẩm truyền thống và dừng chân thưởng thức ngô luộc, cơm lam trên đỉnh đèo Thung Khe.',
 'Xã Chiềng Châu, Huyện Mai Châu, Hòa Bình', 20.6565000, 105.0755000,
 '06:00 - 21:00', 'BQL Du lịch Mai Châu: 0218 3867 214', 'https://www.google.com/maps?q=20.6565,105.0755', 20000, 'người', '2026-02-01',
 'Vé vệ sinh môi trường bản: 20.000đ/người; thuê xe đạp: 30.000đ/buổi.', '2.5 - 3 giờ', 'Tháng 3-4 hoa ban nở trắng đèo; tháng 5 và tháng 9-10 cánh đồng lúa Mai Châu chín vàng rực rỡ.',
 NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'active', 1),

(44, 8, 4, 'Nhà Sàn Ẩm Thực Cơm Lam & Lợn Mán Bản Lác Mai Châu', 'nha-san-am-thuc-ban-lac-mai-chau', 'food',
 'https://images.unsplash.com/photo-1547592180-85f173990554?w=800&auto=format&fit=crop&q=80',
 'Mâm cỗ lá người Thái Mai Châu với cơm lam ống nứa, thịt lợn mán thui luộc, gà đồi nướng mắc khén và xôi nếp nương.',
 'Trải nghiệm dùng bữa trên nhà sàn gỗ thoáng mát nhìn ra cánh đồng lúa Bản Lác, thưởng thức xôi ngũ sắc dẻo thơm và rượu cần Mai Châu êm dịu.',
 'Bản Lác 1, Xã Chiềng Châu, Huyện Mai Châu, Hòa Bình', 20.6558000, 105.0748000,
 '08:00 - 22:00', 'Nhà sàn số 19: 0963 441 228', 'https://www.google.com/maps?q=20.6558,105.0748', 150000, 'người', '2026-02-01',
 NULL, NULL, NULL,
 'Mâm cỗ lá lợn mán, cơm lam Mai Châu, xôi nếp nương ngũ sắc, cá suối nướng, ong rừng xào măng', 'Nhà hàng nhà sàn Thái trắng', '120.000đ - 180.000đ/người',
 NULL, NULL, NULL, NULL, 'active', 1);

-- Hình ảnh bổ sung cho địa điểm tại tất cả các khu vực
INSERT INTO `location_images` (`location_id`, `image_url`, `caption`, `sort_order`) VALUES
(1, 'https://images.unsplash.com/photo-1490750967868-88aa4486c946?w=900&auto=format&fit=crop&q=80', 'Hoa mận nở trắng thung lũng Nà Ka tháng 2', 1),
(1, 'https://images.unsplash.com/photo-1528127269322-539801943592?w=900&auto=format&fit=crop&q=80', 'Đường vào bản mùa xuân Tây Bắc', 2),
(2, 'https://images.unsplash.com/photo-1500382017468-9049fed747ef?w=900&auto=format&fit=crop&q=80', 'Đồi chè Trái Tim buổi sớm mai', 1),
(5, 'https://images.unsplash.com/photo-1544025162-d76694265947?w=900&auto=format&fit=crop&q=80', 'Set cá hồi tươi 6 món đặc sản Mộc Châu', 1),
(7, 'https://images.unsplash.com/photo-1587061949409-02df41d5e562?w=900&auto=format&fit=crop&q=80', 'Bungalow gỗ giữa vườn mơ Mộc Châu', 1),
(8, 'https://images.unsplash.com/photo-1464822759023-fed622ff2c3b?w=900&auto=format&fit=crop&q=80', 'Biển mây Sống lưng khủng long Háng Đồng - Tà Xùa', 1),
(9, 'https://images.unsplash.com/photo-1559592413-7cec4d0cae2b?w=900&auto=format&fit=crop&q=80', 'Hẻm vực Tu Sản và dòng sông Nho Quế', 1),
(10, 'https://images.unsplash.com/photo-1506744038136-46273834b3fb?w=900&auto=format&fit=crop&q=80', 'Bản Lô Lô Chải dưới chân Cột cờ Lũng Cú', 1),
(13, 'https://images.unsplash.com/photo-1570366583862-f91883984fde?w=900&auto=format&fit=crop&q=80', 'Thuyền chèo trên dòng nước xanh Tràng An', 1),
(14, 'https://images.unsplash.com/photo-1528127269322-539801943592?w=900&auto=format&fit=crop&q=80', 'Toàn cảnh Tam Cốc nhìn từ đỉnh Ngọa Long - Hang Múa', 1),
(17, 'https://images.unsplash.com/photo-1583417319070-4a69db38a482?w=900&auto=format&fit=crop&q=80', 'Ruộng bậc thang Mường Hoa mùa lúa chín vàng tháng 9', 1),
(18, 'https://images.unsplash.com/photo-1464822759023-fed622ff2c3b?w=900&auto=format&fit=crop&q=80', 'Biển mây trên đỉnh Fansipan 3.143m', 1),
(19, 'https://images.unsplash.com/photo-1528127269322-539801943592?w=900&auto=format&fit=crop&q=80', 'Du thuyền giữa hàng nghìn đảo đá vôi Vịnh Hạ Long', 1),
(20, 'https://images.unsplash.com/photo-1432405972618-c60b0225b8f9?w=900&auto=format&fit=crop&q=80', 'Thác Bản Giốc mùa nước xanh ngọc bích tháng 9', 1),
(21, 'https://images.unsplash.com/photo-1500382017468-9049fed747ef?w=900&auto=format&fit=crop&q=80', 'Thung lũng ruộng bậc thang Bản Đôn - Pù Luông', 1),
(22, 'https://images.unsplash.com/photo-1509030450996-dd1a26dda07a?w=900&auto=format&fit=crop&q=80', 'Nắng thu vàng trên phố Phan Đình Phùng - Hà Nội', 1);

-- ============================================================================
-- LỊCH TRÌNH MẪU THEO 12 THÁNG (M2)
-- ============================================================================
INSERT INTO `itinerary_templates` (
  `id`, `title`, `slug`, `province_id`, `main_destination`, `thumbnail_url`,
  `duration_days`, `duration_nights`, `assumed_departure`, `suggested_transport`,
  `ref_budget_per_person`, `base_pax_count`, `seasonal_reason`, `summary`, `essential_notes`,
  `status`, `author_id`, `views_count`, `copies_count`, `updated_date`
) VALUES
(1, 'Mộc Châu 3 Ngày 2 Đêm: Mùa Hoa Mận Trắng & Săn Mây Cao Nguyên', 'moc-chau-3n2d-mua-hoa-man-trang', 1, 'Mộc Châu - Sơn La',
 'https://images.unsplash.com/photo-1490750967868-88aa4486c946?w=900&auto=format&fit=crop&q=80',
 3, 2, 'Hà Nội', 'Xe limousine khứ hồi + Thuê xe máy tại Mộc Châu',
 2450000, 2,
 'Tháng 1 và Tháng 2 là thời điểm hoa mận, hoa mơ nở trắng xóa khắp thung lũng Nà Ka, Mu Náu cùng mùa dâu tây chín đỏ tại Bản Áng. Tháng 5 lại là mùa hái mận hậu chín đỏ rực.',
 'Lịch trình chuẩn bị kỹ lưỡng cho chuyến đi Mộc Châu 3 ngày 2 đêm xuất phát từ Hà Nội: khám phá thung lũng mận Nà Ka, đồi chè Trái Tim, rừng thông Bản Áng, thác Dải Yếm, thưởng thức cá hồi Xuân Bắc, bê chao và nghỉ tại homestay gỗ ấm cúng.',
 'Nhiệt độ Mộc Châu tháng 1-2 ban đêm xuống khoảng 10-14 độ C, cần mang áo khoác ấm, giày thể thao đế bám tốt để đi bộ trong vườn mận. Đặt trước phòng homestay ít nhất 1-2 tuần nếu đi cuối tuần.',
 'published', 1, 342, 48, '2026-02-01'),

(2, 'Hà Giang 3 Ngày 2 Đêm: Cung Đường Hạnh Phúc & Sông Nho Quế', 'ha-giang-3n2d-cung-duong-hanh-phuc', 2, 'Đồng Văn - Mèo Vạc (Hà Giang)',
 'https://images.unsplash.com/photo-1559592413-7cec4d0cae2b?w=900&auto=format&fit=crop&q=80',
 3, 2, 'Hà Nội', 'Xe giường nằm đêm Hà Nội - Hà Giang + Xe máy/Ô tô vòng lặp',
 3100000, 2,
 'Tháng 3 mùa hoa đào, hoa lê và hoa gạo đỏ bên sông Nho Quế; Tháng 10 - 11 là mùa lễ hội hoa tam giác mạch phủ hồng khắp cao nguyên đá Đồng Văn.',
 'Chinh phục dốc Bắc Sum, cổng trời Quản Bạ, dinh thự Vua Mèo, cột cờ Lũng Cú, bản Lô Lô Chải, đèo Mã Pí Lèng và đi thuyền xuyên hẻm vực Tu Sản trên sông Nho Quế.',
 'Đường đèo Hà Giang nhiều cua gấp, nếu tay lái chưa vững nên thuê người bản địa chở (easy rider) hoặc đi xe 7-16 chỗ. Mang theo CCCD bản gốc để đăng ký lưu trú vùng biên.',
 'published', 1, 289, 39, '2026-02-01'),

(3, 'Ninh Bình 2 Ngày 1 Đêm: Tràng An - Hang Múa Mùa Lúa Vàng & Lễ Hội Xuân', 'ninh-binh-2n1d-trang-an-hang-mua', 4, 'Tràng An - Tam Cốc (Ninh Bình)',
 'https://images.unsplash.com/photo-1570366583862-f91883984fde?w=900&auto=format&fit=crop&q=80',
 2, 1, 'Hà Nội', 'Xe limousine Hà Nội - Ninh Bình (1h30p) + Xe đạp/Xe máy',
 1650000, 2,
 'Tháng 2 - 3 tiết trời xuân mát mẻ du xuân Tràng An - Bái Đính; Tháng 5 - 6 là mùa lúa chín vàng rực dọc dòng sông Ngô Đồng và mùa sen nở dưới chân Hang Múa.',
 'Kỳ nghỉ cuối tuần nhẹ nhàng cách Hà Nội chỉ 95km: ngồi đò Tràng An xuyên thủy động, leo đỉnh Ngọa Long ngắm toàn cảnh Tam Cốc, thưởng thức dê núi Chính Thư và nghỉ dưỡng tại Eco Bungalow.',
 'Nên đi đò Tràng An vào khung giờ sáng sớm (7h30 - 8h30) để tránh nắng và đông khách. Leo Hang Múa đẹp nhất lúc 16h00 đón hoàng hôn.',
 'published', 1, 215, 31, '2026-02-01'),

(4, 'Sa Pa 3 Ngày 2 Đêm: Chinh Phục Đỉnh Fansipan & Mùa Vàng Mường Hoa', 'sapa-3n2d-fansipan-muong-hoa', 3, 'Thị xã Sa Pa - Lào Cai',
 'https://images.unsplash.com/photo-1583417319070-4a69db38a482?w=900&auto=format&fit=crop&q=80',
 3, 2, 'Hà Nội', 'Xe cabin đôi Hà Nội - Sa Pa + Tàu hỏa leo núi Mường Hoa',
 2950000, 2,
 'Tháng 4 - 5 mùa nước đổ và hoa đỗ quyên trên đỉnh Fansipan; Tháng 9 là mùa lúa chín vàng óng ả khắp thung lũng Mường Hoa; Tháng 12 săn mây và băng giá.',
 'Trải nghiệm trọn vẹn Sa Pa từ bản Cát Cát, thung lũng Mường Hoa đến nóc nhà Đông Dương Fansipan 3.143m, thưởng thức lẩu cá tầm và đồ nướng phố Cầu Mây.',
 'Kiểm tra dự báo thời tiết đỉnh Fansipan trước khi mua vé cáp treo để chọn khung giờ nắng quang mây.',
 'published', 1, 198, 27, '2026-02-01'),

(5, 'Vịnh Hạ Long 2 Ngày 1 Đêm: Du Thuyền Ngắm Kỳ Quan & Chèo Kayak Mùa Hè', 'vinh-ha-long-2n1d-du-thuyen-mua-he', 5, 'Vịnh Hạ Long - Quảng Ninh',
 'https://images.unsplash.com/photo-1528127269322-539801943592?w=900&auto=format&fit=crop&q=80',
 2, 1, 'Hà Nội', 'Xe limousine cao tốc Hà Nội - Hạ Long (2h15p) + Tàu tham quan vịnh',
 2350000, 2,
 'Tháng 6, 7, 8 là mùa hè lý tưởng nhất để giải nhiệt biển miền Bắc, tắm biển đảo Ti Tốp, chèo kayak hang Luồn và thưởng thức hải sản Hạ Long.',
 'Hành trình 2 ngày 1 đêm khám phá tuyến 2 Vịnh Hạ Long (Hang Sửng Sốt - Hang Luồn - Đảo Ti Tốp), dạo phố biển Bãi Cháy và thưởng thức chả mực giã tay.',
 'Tháng 7-8 cần theo dõi thông tin thời tiết vịnh trước chuyến đi 2-3 ngày.',
 'published', 1, 176, 22, '2026-02-01'),

(6, 'Cao Bằng - Thác Bản Giốc 3 Ngày 2 Đêm: Mùa Nước Xanh Ngọc & Lúa Chín', 'cao-bang-thac-ban-gioc-3n2d', 6, 'Trùng Khánh - Cao Bằng',
 'https://images.unsplash.com/photo-1432405972618-c60b0225b8f9?w=900&auto=format&fit=crop&q=80',
 3, 2, 'Hà Nội', 'Xe khách giường nằm / Ô tô tự lái',
 2650000, 2,
 'Tháng 8, 9, 10 là thời điểm thác Bản Giốc đầy nước xanh màu ngọc bích kết hợp cánh đồng lúa Trùng Khánh chín vàng ngay dưới chân thác.',
 'Khám phá trọn vẹn miền non nước Cao Bằng: thác Bản Giốc, động Ngườm Ngao, núi Mắt Thần và khu di tích Pác Bó - suối Lê Nin.',
 'Mang theo nón, áo khoác nhẹ vì bụi nước chân thác Bản Giốc khá mạnh.',
 'published', 1, 154, 19, '2026-02-01'),

(7, 'Tà Xùa - Bắc Yên 2 Ngày 1 Đêm: Săn Biển Mây Sống Lưng Khủng Long', 'ta-xua-2n1d-san-bien-may', 1, 'Tà Xùa - Sơn La',
 'https://images.unsplash.com/photo-1464822759023-fed622ff2c3b?w=900&auto=format&fit=crop&q=80',
 2, 1, 'Hà Nội', 'Xe limousine Hà Nội - Tà Xùa + Xe máy',
 1750000, 2,
 'Tháng 1, 2, 11, 12 là mùa săn mây đỉnh cao tại Tà Xùa khi nhiệt độ chênh lệch ngày đêm tạo nên biển mây dày đặc.',
 'Dành trọn cuối tuần đón bình minh trên biển mây Sống lưng khủng long Háng Đồng, ngắm hoàng hôn tại Đỉnh Gió và thưởng thức trà cổ thụ Shan Tuyết.',
 'Nên dậy từ 5h15 sáng để kịp đón bình minh và biển mây tại sống lưng khủng long.',
 'published', 1, 165, 24, '2026-02-01'),

(8, 'Pù Luông - Hà Nội Thu Vàng 2 Ngày 1 Đêm: Mùa Lúa Chín & Hương Cốm Mới', 'pu-luong-ha-noi-2n1d-mua-thu', 8, 'Pù Luông & Hà Nội',
 'https://images.unsplash.com/photo-1500382017468-9049fed747ef?w=900&auto=format&fit=crop&q=80',
 2, 1, 'Hà Nội', 'Xe limousine Hà Nội - Pù Luông',
 1850000, 2,
 'Tháng 5-6 (lúa vụ chiêm) và Tháng 9-10 (lúa vụ mùa + mùa thu Hà Nội) mang lại khung cảnh ruộng bậc thang vàng óng và tiết trời thu dịu mát.',
 'Kết hợp nghỉ dưỡng giữa thung lũng lúa Bản Đôn Pù Luông, đi bè tre suối Chàm và dạo phố thu Hà Nội.',
 'Phù hợp cho cả nhóm bạn và gia đình muốn tìm không gian yên tĩnh, gần gũi thiên nhiên.',
 'published', 1, 140, 18, '2026-02-01');

-- Gán các lịch trình mẫu cho đủ 12 tháng trong năm (Một tháng có nhiều mẫu, một mẫu thuộc nhiều tháng)
INSERT INTO `template_months` (`template_id`, `month_number`, `month_reason`) VALUES
-- Tháng 1
(1, 1, 'Hoa mận, hoa mơ bắt đầu bung nở trắng xóa khắp các bản làng Mộc Châu và dâu tây vào chính vụ.'),
(7, 1, 'Thời điểm săn biển mây dày và đẹp nhất tại Sống lưng khủng long Tà Xùa.'),
-- Tháng 2 (Trọng tâm kịch bản Demo Mộc Châu 3N2N)
(1, 2, 'Tháng 2 là đỉnh điểm mùa hoa mận trắng Nà Ka, tiết trời xuân se lạnh cực kỳ lý tưởng cho hành trình 3 ngày 2 đêm.'),
(3, 2, 'Du xuân đầu năm lễ chùa Bái Đính và ngồi đò vãn cảnh non nước Tràng An.'),
(7, 2, 'Săn mây mùa xuân kết hợp ngắm hoa đào rừng nở muộn trên đỉnh Tà Xùa.'),
-- Tháng 3
(2, 3, 'Mùa hoa đào, hoa lê nở bên hàng rào đá Lô Lô Chải và hoa gạo đỏ rực bờ sông Nho Quế.'),
(3, 3, 'Tiết trời xuân mát mẻ, thuận lợi leo Hang Múa và khám phá cố đô Hoa Lư.'),
-- Tháng 4
(4, 4, 'Mùa hoa đỗ quyên nở rộ trên đỉnh Fansipan và bắt đầu mùa nước đổ ruộng bậc thang Sa Pa.'),
(8, 4, 'Không khí đầu hạ trong lành giữa thung lũng xanh mướt Bản Đôn - Pù Luông.'),
-- Tháng 5
(3, 5, 'Mùa lúa chín vàng rực hai bên dòng sông Ngô Đồng (Tam Cốc - Hang Múa).'),
(1, 5, 'Mùa lễ hội hái quả mận hậu chín đỏ tại thung lũng Nà Ka Mộc Châu.'),
(4, 5, 'Mùa nước đổ lấp lánh như gương trời tại thung lũng Mường Hoa - Sa Pa.'),
(8, 5, 'Mùa lúa chín vụ chiêm tuyệt đẹp tại khu bảo tồn thiên nhiên Pù Luông.'),
-- Tháng 6
(5, 6, 'Nắng hè rực rỡ, nước biển trong xanh lý tưởng để du thuyền, tắm biển Ti Tốp và chèo kayak Vịnh Hạ Long.'),
(3, 6, 'Mùa sen nở hồng rực dưới chân núi Ngọa Long (Hang Múa - Ninh Bình).'),
-- Tháng 7
(5, 7, 'Cao điểm du lịch biển đảo Quảng Ninh - Hạ Long giải nhiệt mùa hè.'),
(1, 7, 'Tránh nóng mùa hè trên cao nguyên Mộc Châu mát mẻ với đồi chè xanh mướt và thác Dải Yếm đầy nước.'),
-- Tháng 8
(6, 8, 'Thác Bản Giốc bước vào mùa nước đẹp nhất năm, dòng sông Quây Sơn xanh màu ngọc bích.'),
(5, 8, 'Du thuyền ngắm hoàng hôn cuối hạ trên Vịnh Hạ Long.'),
-- Tháng 9
(4, 9, 'Tháng vàng rực rỡ nhất của ruộng bậc thang Mường Hoa - Bản Cát Cát (Sa Pa).'),
(6, 9, 'Lúa chín vàng dưới chân thác Bản Giốc (Cao Bằng), thời tiết thu khô ráo mát mẻ.'),
(8, 9, 'Đón thu Hà Nội cốm mới và mùa lúa chín vàng tại Pù Luông.'),
-- Tháng 10
(2, 10, 'Mùa hoa tam giác mạch bắt đầu nở hồng khắp cao nguyên đá Đồng Văn - Hà Giang.'),
(6, 10, 'Mùa thu biên viễn Cao Bằng nước thác trong vắt, nắng vàng dịu.'),
(8, 10, 'Đỉnh điểm mùa lúa chín Pù Luông và tiết trời thu Hà Nội đẹp nhất năm.'),
-- Tháng 11
(2, 11, 'Lễ hội hoa tam giác mạch Hà Giang và săn mây đèo Mã Pí Lèng.'),
(7, 11, 'Khởi đầu mùa săn biển mây bồng bềnh tại Tà Xùa - Bắc Yên.'),
(1, 11, 'Mùa hoa cải trắng, hoa dã quỳ vàng và vườn hồng chín đỏ tại Mộc Châu.'),
-- Tháng 12
(7, 12, 'Săn biển mây mùa đông và thưởng trà cổ thụ ấm áp tại Đỉnh Gió Tà Xùa.'),
(4, 12, 'Trải nghiệm mùa đông sương mù, săn băng tuyết trên đỉnh Fansipan Sa Pa.'),
(1, 12, 'Mùa hoa cải trắng và những vườn dâu tây đầu mùa chín mọng tại Mộc Châu.');

-- ============================================================================
-- CHI TIẾT NGÀY & HOẠT ĐỘNG CHO MẪU SỐ 1: MỘC CHÂU 3 NGÀY 2 ĐÊM (KỊCH BẢN DEMO)
-- ============================================================================
INSERT INTO `template_days` (`id`, `template_id`, `day_number`, `title`, `summary`, `transport_note`) VALUES
(1, 1, 1, 'Ngày 1: Hà Nội – Đèo Đá Trắng – Rừng Thông Bản Áng – Thưởng Thức Cá Hồi', 'Khởi hành từ Hà Nội theo QL6, dừng chân đèo Thung Khe, nhận phòng homestay gỗ tại Đông Sang, dạo Rừng thông Bản Áng & hái dâu tây.', 'Xe limousine Hà Nội - Mộc Châu (4 giờ), nhận xe máy thuê tại homestay'),
(2, 1, 2, 'Ngày 2: Đồi Chè Trái Tim – Thung Lũng Mận Nà Ka – Thác Dải Yếm – Bê Chao', 'Đón bình minh tại Đồi chè Trái Tim, chụp ảnh hoa mận trắng xóa tại thung lũng Nà Ka, chiều khám phá thác Dải Yếm.', 'Di chuyển bằng xe máy hoặc taxi địa phương cung Nông trường - Mường Sang (~25km)'),
(3, 1, 3, 'Ngày 3: Săn Mây Chiềng Đi – Mua Đặc Sản Sữa & Mận – Trở Về Hà Nội', 'Ngắm sương sớm cao nguyên, thưởng thức bữa trưa đặc sản bê chao, mua quà sữa tươi/bánh sữa Mộc Châu và lên xe về Hà Nội.', 'Xe limousine Mộc Châu - Hà Nội chuyến 14:30');

INSERT INTO `template_activities` (
  `id`, `template_day_id`, `location_id`, `title`, `activity_type`,
  `start_time`, `end_time`, `description`, `transport_note`, `estimated_cost`, `important_note`, `sort_order`
) VALUES
-- Ngày 1 Mẫu 1
(1, 1, NULL, 'Khởi hành Hà Nội – Mộc Châu qua đèo Thung Khe', 'transport', '07:00', '11:30',
 'Xuất phát từ Nguyễn Trãi / BigC Thăng Long theo Quốc lộ 6. Dừng nghỉ 20 phút tại đèo Đá Trắng (Thung Khe) ngắm thung lũng Mai Châu và thưởng thức ngô luộc, cơm lam nóng.',
 'Xe limousine 9 chỗ đón tận nơi', 250000, 'Nên ngồi ghế giữa xe nếu dễ say đường đèo.', 1),
(2, 1, 5, 'Ăn trưa Set Cá Hồi tươi tại Nhà hàng Xuân Bắc 181', 'food', '11:45', '13:15',
 'Thưởng thức set cá hồi 6 món đặc sản Mộc Châu (gỏi cá hồi, da cá chiên, cá nướng mắc khén, lẩu cá hồi rau cải mèo).',
 'Cách trung tâm thị trấn Nông trường 3km', 250000, 'Gọi điện đặt bàn trước 30 phút để nhà hàng chuẩn bị cá tươi.', 2),
(3, 1, 7, 'Nhận phòng tại Mama''s House Homestay & Nghỉ ngơi', 'checkin_checkout', '13:30', '14:45',
 'Nhận phòng bungalow gỗ áp mái giữa vườn mơ, nhận xe máy thuê ngay tại homestay.',
 'Taxi từ nhà hàng về Bản Áng (~4km)', 0, 'Chi phí phòng đã tính trong mục Lưu trú.', 3),
(4, 1, 4, 'Dạo Rừng thông Bản Áng & Hái dâu tây Chimi Farm', 'attraction', '15:00', '18:00',
 'Đạp xe đôi quanh hồ Bản Áng dưới tán thông xanh, ghé vườn dâu tây Chimi ngay trong khuôn viên thưởng thức sữa chua dâu tây và tự tay hái dâu.',
 'Đi bộ hoặc xe máy 500m từ homestay', 80000, 'Ánh nắng hoàng hôn lúc 16h30 - 17h30 chụp ảnh bên hồ rất đẹp.', 4),
(5, 1, 7, 'Tiệc nướng BBQ sân vườn & Lửa trại tối', 'food', '19:00', '21:30',
 'Thưởng thức thịt nướng tảng, lợn bản nướng mắc khén và khoai nướng trong tiết trời se lạnh 12 độ C tại sân vườn homestay.',
 'Tại khuôn viên Mama''s House', 200000, 'Nhớ mặc áo phao hoặc áo giữ nhiệt vì tối sương xuống nhanh.', 5),

-- Ngày 2 Mẫu 1
(6, 2, 2, 'Đón bình minh & Sương sớm tại Đồi chè Trái Tim', 'attraction', '07:30', '09:30',
 'Ngắm những luống chè xanh mướt còn đọng sương mai, chụp ảnh tại khu vực đồi chè hình trái tim và thưởng thức chén trà nóng.',
 'Xe máy theo đường tỉnh 104 (~10km)', 20000, 'Miễn phí vé vào; chi phí gửi xe và thuê phụ kiện gùi chè.', 1),
(7, 2, 1, 'Khám phá Thung lũng mận Nà Ka mùa hoa trắng', 'attraction', '09:45', '12:00',
 'Đi tiếp 6km từ đồi chè vào thung lũng Nà Ka. Toàn bộ thung lũng rộng hơn 100ha phủ sắc trắng tinh khôi của hoa mận (tháng 1-2) hoặc mận chín đỏ (tháng 5).',
 'Đường bê tông dễ đi đến tận cổng vườn', 30000, 'Có thể thuê trang phục Mông/Thái ngay cổng vườn (50.000đ/bộ).', 2),
(8, 2, 6, 'Ăn trưa đặc sản Bê Chao 70 & Cá suối nướng', 'food', '12:30', '14:00',
 'Thưởng thức đĩa bê chao nóng hổi giòn bì mềm thịt, cá suối chiên giòn, gà đồi nướng và sữa tươi chiên.',
 'Quay về trục QL6 trung tâm thị trấn', 180000, 'Món bê chao ngon nhất khi ăn ngay lúc vừa bắc khỏi chảo.', 3),
(9, 2, 3, 'Tham quan Thác Dải Yếm & Khu sinh thái Mường Sang', 'attraction', '14:30', '17:30',
 'Chiêm ngưỡng dòng thác 9 tầng tung bọt trắng xóa, check-in suối Hẹn và trải nghiệm không gian xanh mát xã Mường Sang.',
 'Đi theo QL43 hướng cửa khẩu Lóng Sập (~7km)', 100000, 'Mang giày đế thấp chống trơn trượt khi đi bậc đá gần thác.', 4),
(10, 2, NULL, 'Dạo phố đêm Mộc Châu & Thưởng thức Sữa nóng', 'other', '19:30', '21:30',
 'Thưởng thức lẩu gà đồi măng chua bữa tối, sau đó ghé quán sữa tươi nóng chấm bánh mì tại thị trấn.',
 'Xe máy quanh thị trấn', 170000, 'Nên về homestay trước 22h00 để đảm bảo an toàn đường sương mù.', 5),

-- Ngày 3 Mẫu 1
(11, 3, NULL, 'Ngắm bình minh vườn mơ & Thưởng thức bún cá lăng sáng', 'food', '07:30', '09:00',
 'Dạo bộ quanh vườn mơ ngay sau homestay, dùng bữa sáng bún cá lăng hoặc phở bò nóng hổi.',
 'Tại homestay & khu vực Đông Sang', 50000, 'Bữa sáng cơ bản đã kèm theo phòng hoặc gọi thêm món đặc sản.', 1),
(12, 3, NULL, 'Trả phòng & Mua quà tại Mộc Châu Milk / Đặc sản Tây Bắc', 'checkin_checkout', '09:30', '11:30',
 'Trả phòng homestay, ghé cửa hàng giới thiệu sản phẩm Mộc Châu mua sữa tươi thanh trùng, bánh sữa, mận sấy dẻo, thịt trâu gác bếp.',
 'Dọc Quốc lộ 6 tiểu khu Chiềng Đi', 200000, 'Sữa tươi thanh trùng cần bảo quản mát nếu đi đường dài.', 2),
(13, 3, 6, 'Ăn trưa nhẹ & Lên xe Limousine về lại Hà Nội', 'transport', '12:00', '18:30',
 'Ăn trưa cơm bản địa, lúc 14:00 xe limousine đón tại điểm hẹn khởi hành về Hà Nội. Kết thúc hành trình 3N2Đ.',
 'Xe limousine Mộc Châu - Hà Nội', 370000, 'Gồm 120.000đ ăn trưa + 250.000đ vé xe limousine chiều về.', 3);

-- Ngày & hoạt động chi tiết cho tất cả các Lịch trình mẫu (Mẫu 2 đến Mẫu 8)
INSERT INTO `template_days` (`id`, `template_id`, `day_number`, `title`, `summary`, `transport_note`) VALUES
-- Mẫu 2: Hà Giang 3N2Đ
(4, 2, 1, 'Ngày 1: Hà Giang – Cổng Trời Quản Bạ – Yên Minh – Phố Cổ Đồng Văn', 'Check-in Km0 Hà Giang, dốc Bắc Sum, núi đôi Quản Bạ, dốc Thẩm Mã và nghỉ đêm tại Phố cổ Đồng Văn.', 'Xe máy hoặc ô tô du lịch theo QL4C (145km)'),
(5, 2, 2, 'Ngày 2: Cột Cờ Lũng Cú – Lô Lô Chải – Đèo Mã Pí Lèng – Sông Nho Quế', 'Thăm điểm cực Bắc Lũng Cú, bản cổ tích Lô Lô Chải, chinh phục đèo Mã Pí Lèng và đi thuyền hẻm Tu Sản.', 'Cung Đồng Văn - Lũng Cú - Mèo Vạc (~70km)'),
(6, 2, 3, 'Ngày 3: Chợ Phiên Mèo Vạc – Cua Chữ M – TP. Hà Giang – Hà Nội', 'Khám phá văn hóa chợ phiên vùng cao, ngắm thung lũng Đường Thượng, trả xe tại TP. Hà Giang và lên xe về Hà Nội.', 'Mèo Vạc - TP. Hà Giang (~155km) + Xe cabin về Hà Nội'),
-- Mẫu 3: Ninh Bình 2N1Đ
(7, 3, 1, 'Ngày 1: Hà Nội – Danh Thắng Tràng An – Hoàng Hôn Đỉnh Hang Múa', 'Ngồi đò xuyên thủy động Tràng An buổi sáng, ăn trưa dê núi Chính Thư và leo đỉnh Ngọa Long ngắm hoàng hôn.', 'Limousine Hà Nội - Ninh Bình + Xe đạp quanh Tam Cốc'),
(8, 3, 2, 'Ngày 2: Đạp Xe Thung Lũng Lúa Tam Cốc – Cố Đô Hoa Lư – Hà Nội', 'Thưởng thức không khí yên bình giữa đồng lúa và núi đá vôi Tam Cốc, thăm đền Vua Đinh - Vua Lê và về Hà Nội.', 'Xe đạp + Limousine chiều về'),
-- Mẫu 4: Sa Pa 3N2Đ
(9, 4, 1, 'Ngày 1: Hà Nội – Thị Trấn Sương Mù Sa Pa – Bản Cát Cát – Lẩu Cá Tầm', 'Khởi hành cao tốc Nội Bài - Lào Cai, nhận phòng bungalow view Mường Hoa, khám phá Bản Cát Cát và thưởng thức lẩu cá tầm.', 'Xe cabin đôi Hà Nội - Sa Pa (5h30p) + Xe điện/Taxi bản'),
(10, 4, 2, 'Ngày 2: Tàu Hỏa Leo Núi Mường Hoa – Cáp Treo Chinh Phục Đỉnh Fansipan 3.143m', 'Đi tàu hỏa leo núi qua thung lũng Mường Hoa, đi cáp treo 3 dây lên nóc nhà Đông Dương và dạo chợ đêm Sa Pa.', 'Tàu hỏa leo núi Mường Hoa + Cáp treo Fansipan'),
(11, 4, 3, 'Ngày 3: Săn Mây Bản Lao Chải – Cà Phê View Núi – Trở Về Hà Nội', 'Ngắm bình minh ruộng bậc thang từ ban công Eco Palms House, mua đặc sản thịt trâu gác bếp và lên xe về Hà Nội.', 'Xe cabin Sa Pa - Hà Nội chuyến 14:00'),
-- Mẫu 5: Vịnh Hạ Long 2N1Đ
(12, 5, 1, 'Ngày 1: Hà Nội – Du Thuyền Tuyến 2 Vịnh Hạ Long – Hang Sửng Sốt – Đảo Ti Tốp', 'Di chuyển cao tốc 2 tiếng tới Cảng tàu quốc tế Hạ Long, lên du thuyền khám phá Hang Sửng Sốt, chèo kayak Hang Luồn và tắm biển Ti Tốp.', 'Limousine cao tốc Hà Nội - Hạ Long + Tàu tham quan tuyến 2'),
(13, 5, 2, 'Ngày 2: Bình Minh Bãi Cháy – Đường Bao Biển – Đặc Sản Chả Mực – Hà Nội', 'Tắm biển sớm hoặc bơi hồ bơi vô cực ngắm vịnh, thưởng thức hải sản Hồng Hạnh và mua chả mực giã tay về làm quà.', 'Taxi điện quanh Bãi Cháy - Hòn Gai + Limousine về Hà Nội'),
-- Mẫu 6: Cao Bằng - Thác Bản Giốc 3N2Đ
(14, 6, 1, 'Ngày 1: Hà Nội – TP. Cao Bằng – Khu Di Tích Pác Bó & Suối Lê Nin', 'Khởi hành đi Cao Bằng, thăm suối Lê Nin xanh ngọc bích, núi Các Mác và thưởng thức phở chua, vịt quay 7 vị tại trung tâm thành phố.', 'Ô tô du lịch / Xe khách Hà Nội - Cao Bằng'),
(15, 6, 2, 'Ngày 2: Đèo Mã Phục – Thác Bản Giốc – Động Ngườm Ngao – Làng Đá Cổ Khuổi Ky', 'Vượt đèo Mã Phục đến Trùng Khánh, đi bè mảng sát chân thác Bản Giốc, khám phá động Ngườm Ngao và nghỉ đêm tại nhà sàn đá cổ 400 năm.', 'Cung TP. Cao Bằng - Trùng Khánh - Đàm Thủy (~85km)'),
(16, 6, 3, 'Ngày 3: Núi Mắt Thần (Thung Lũng Thủng) – Mua Hạt Dẻ Trùng Khánh – Hà Nội', 'Check-in thảo nguyên Núi Mắt Thần tuyệt đẹp buổi sớm, mua hạt dẻ Trùng Khánh, bánh khảo và khởi hành về Hà Nội.', 'Trùng Khánh - TP. Cao Bằng - Hà Nội'),
-- Mẫu 7: Tà Xùa 2N1Đ
(17, 7, 1, 'Ngày 1: Hà Nội – Bắc Yên – Thảo Nguyên Tà Xùa – Ngắm Hoàng Hôn Đỉnh Gió', 'Di chuyển theo cung Thanh Sơn - Thu Cúc - Bắc Yên lên đỉnh Tà Xùa, check-in Cây Cô Đơn và thưởng trà Shan Tuyết ngắm hoàng hôn biển mây.', 'Xe limousine Hà Nội - Tà Xùa (4h30p) + Xe máy'),
(18, 7, 2, 'Ngày 2: Săn Bình Minh Biển Mây Sống Lưng Khủng Long – Thưởng Thức Cơm Bản – Hà Nội', 'Dậy sớm 5h15 đón biển mây cuồn cuộn tại Sống lưng khủng long Háng Đồng, ăn trưa đặc sản vùng cao và về lại Hà Nội.', 'Xe máy Háng Đồng (12km) + Limousine Tà Xùa - Hà Nội'),
-- Mẫu 8: Pù Luông - Hà Nội 2N1Đ
(19, 8, 1, 'Ngày 1: Phố Thu Hà Nội Phan Đình Phùng – Đèo Thung Khe – Bản Đôn Pù Luông', 'Chụp ảnh nắng thu và thưởng thức cốm mới trên phố Phan Đình Phùng buổi sớm, sau đó lên đường về thung lũng ruộng bậc thang Bản Đôn.', 'Limousine Hà Nội - Pù Luông qua lối Lũng Vân / Mai Châu'),
(20, 8, 2, 'Ngày 2: Đi Bè Tre Suối Chàm & Guồng Nước Cổ – Ẩm Thực Vịt Cổ Lũng – Hà Nội', 'Dạo bộ xuyên ruộng bậc thang lúa chín, trải nghiệm bè tre suối Chàm, ăn trưa vịt Cổ Lũng quay mắc mật và trở về Hà Nội.', 'Xe điện bản làng + Limousine Pù Luông - Hà Nội');

INSERT INTO `template_activities` (
  `id`, `template_day_id`, `location_id`, `title`, `activity_type`,
  `start_time`, `end_time`, `description`, `transport_note`, `estimated_cost`, `important_note`, `sort_order`
) VALUES
-- Mẫu 2: Hà Giang 3N2Đ (Days 4, 5, 6)
(14, 4, NULL, 'Check-in Cột mốc Km0 Hà Giang & Chinh phục Dốc Bắc Sum – Cổng Trời Quản Bạ', 'attraction', '07:30', '11:30', 'Khởi đầu cung Đường Hạnh Phúc tại Km0, vượt dốc Bắc Sum ngắm Núi Đôi Cô Tiên từ Cổng trời Quản Bạ.', 'Xe máy / Ô tô theo QL4C', 50000, 'Kiểm tra kỹ phanh và lốp xe trước khi lên đèo.', 1),
(15, 4, NULL, 'Đi qua Rừng thông Yên Minh – Dốc Thẩm Mã – Dinh thự Vua Mèo', 'attraction', '13:30', '17:00', 'Chụp ảnh tại khúc cua chữ S dốc Thẩm Mã nổi tiếng và tham quan kiến trúc cổ Dinh thự họ Vương tại Sà Phìn.', 'Cung Yên Minh - Đồng Văn', 30000, 'Vé tham quan Dinh Vua Mèo: 30.000đ/người.', 2),
(16, 4, 11, 'Nhận phòng Đồng Văn & Thưởng thức Lẩu Gà Đen Phố Cổ', 'food', '18:30', '21:00', 'Thưởng thức nồi lẩu gà đen H''Mông nóng hổi và dạo phố cổ Đồng Văn buổi tối.', 'Đi bộ trong phố cổ', 200000, 'Buổi tối cuối tuần phố cổ có giao lưu văn nghệ khèn Mông.', 3),
(17, 5, 10, 'Chinh phục Cột cờ Lũng Cú & Dạo bản Lô Lô Chải', 'attraction', '08:00', '11:30', 'Lên đỉnh cột cờ cực Bắc ngắm toàn cảnh biên cương và uống cà phê tại bản Lô Lô Chải.', 'Đường đèo 24km từ Đồng Văn', 55000, 'Ghé quán Cà phê Cực Bắc trong bản Lô Lô Chải.', 1),
(18, 5, 9, 'Ngắm Đèo Mã Pí Lèng & Đi thuyền Sông Nho Quế qua Hẻm Tu Sản', 'attraction', '14:00', '17:00', 'Đi thuyền máy trên dòng sông Nho Quế màu ngọc bích xuyên qua hẻm vực sâu nhất Đông Nam Á.', 'Xuống bến thuyền Tà Làng / Pả Vi', 120000, 'Nên đi khung giờ 14h00 - 15h30 khi nắng chiếu thẳng xuống lòng sông.', 2),
(19, 5, 12, 'Nghỉ đêm & Ăn tối tại Làng văn hóa H''Mông Pả Vi (Mèo Vạc)', 'checkin_checkout', '17:30', '21:30', 'Nhận phòng homestay trình tường tại thung lũng Pả Vi và thưởng thức lẩu bò vùng cao.', 'Cách bến thuyền 4km', 180000, 'Chi phí phòng đã tính trong mục Lưu trú, chi phí 180k là bữa tối.', 3),
(20, 6, 12, 'Trải nghiệm Chợ phiên vùng cao Mèo Vạc & Bánh cuốn trứng nóng', 'food', '07:00', '09:30', 'Hòa mình vào không khí chợ phiên vùng cao rực rỡ sắc váy thổ cẩm, ăn sáng bánh cuốn trứng nước xương.', 'Trung tâm thị trấn Mèo Vạc', 50000, 'Chợ phiên chính họp đông nhất vào sáng Chủ nhật.', 1),
(21, 6, NULL, 'Hành trình Mèo Vạc – Cua Chữ M – Đường Thượng – TP. Hà Giang – Hà Nội', 'transport', '10:00', '17:30', 'Di chuyển về lại TP. Hà Giang qua cung Mậu Duệ - Đường Thượng hùng vĩ, ăn trưa dọc đường và lên xe cabin về Hà Nội.', 'Cung Mèo Vạc - TP. Hà Giang (155km)', 450000, 'Bao gồm ăn trưa và vé xe giường nằm/cabin chiều về.', 2),

-- Mẫu 3: Ninh Bình 2N1Đ (Days 7, 8)
(22, 7, 13, 'Ngồi đò khám phá Quần thể Danh thắng Tràng An (Tuyến 2 hoặc 3)', 'attraction', '08:30', '11:45', 'Hành trình 3 tiếng ngồi đò chèo tay qua các hang động xuyên thủy và hành cung Vũ Lâm.', 'Bến thuyền Tràng An', 250000, 'Mang theo ô/mũ rộng vành và nước uống.', 1),
(23, 7, 15, 'Ăn trưa Dê núi Trường Yên & Cơm cháy tại Nhà hàng Chính Thư', 'food', '12:00', '13:30', 'Thưởng thức dê tái chanh, dê nướng tảng và cơm cháy sốt tim cật.', 'Cách Tràng An 2.5km', 220000, 'Quán rộng rãi, phục vụ nhanh.', 2),
(24, 7, 16, 'Nhận phòng Tam Cốc Garden & Eco Bungalow nghỉ trưa', 'checkin_checkout', '14:00', '15:15', 'Nhận phòng bungalow mái ngói giữa cánh đồng lúa và núi đá vôi Tam Cốc.', 'Xã Ninh Hải, Hoa Lư', 0, 'Chi phí phòng đã tính trong mục Lưu trú.', 3),
(25, 7, 14, 'Leo 486 bậc đá Đỉnh Hang Múa & Chụp ảnh Đầm Sen Ngọa Long', 'attraction', '15:30', '18:00', 'Ngắm toàn cảnh dòng Ngô Đồng uốn lượn qua cánh đồng lúa Tam Cốc từ đỉnh núi Ngọa Long.', 'Cách khu nghỉ dưỡng 3km', 100000, 'Đi lúc 15h30 - 16h00 nắng dịu và đón hoàng hôn rất đẹp.', 4),
(26, 8, 16, 'Đạp xe buổi sớm quanh thung lũng Tam Cốc – Bích Động', 'attraction', '07:00', '09:30', 'Đạp xe miễn phí từ Tam Cốc Eco Bungalow qua các con đường làng rợp bóng núi đá vôi và chùa Bích Động.', 'Xe đạp của khu nghỉ', 0, 'Không khí buổi sớm trong lành, rất hợp chụp ảnh.', 1),
(27, 8, NULL, 'Tham quan Cố đô Hoa Lư (Đền Vua Đinh – Vua Lê)', 'attraction', '10:00', '11:30', 'Tìm hiểu lịch sử kinh đô đầu tiên của nhà nước phong kiến tập quyền Việt Nam dưới chân núi Mã Yên.', 'Xã Trường Yên (~7km)', 20000, 'Vé tham quan di tích cố đô: 20.000đ/người.', 2),
(28, 8, 15, 'Ăn trưa miến lươn / dê núi & Lên xe Limousine về Hà Nội', 'transport', '12:00', '16:30', 'Trả phòng, dùng bữa trưa nhẹ, mua cơm cháy Ninh Bình làm quà và lên xe limousine về Hà Nội.', 'Limousine Ninh Bình - Hà Nội', 330000, 'Gồm ăn trưa + vé xe limousine chiều về.', 3),

-- Mẫu 4: Sa Pa 3N2Đ (Days 9, 10, 11)
(29, 9, 24, 'Đến Sa Pa & Nhận phòng Eco Palms House View Thung Lũng Mường Hoa', 'checkin_checkout', '12:30', '14:00', 'Di chuyển từ bến xe Sa Pa xuống bản Lao Chải, nhận bungalow tre nhìn thẳng ra dãy Hoàng Liên Sơn.', 'Xe cabin Hà Nội - Sa Pa + Taxi bản', 350000, '350.000đ là vé xe cabin chiều đi Hà Nội - Sa Pa.', 1),
(30, 9, 17, 'Khám phá Bản Cát Cát & Thác Tiên Sa dưới chân Hoàng Liên Sơn', 'attraction', '14:30', '17:30', 'Dạo bước trên con đường đá cổ bản Cát Cát, ngắm guồng nước khổng lồ, thác Tiên Sa và ruộng bậc thang Mường Hoa.', 'Cách thị xã Sa Pa 2.5km', 150000, 'Có thể thuê trang phục thổ cẩm chụp ảnh bên suối Hoa.', 2),
(31, 9, 23, 'Thưởng thức Lẩu Cá Tầm & Gỏi Cá Hồi Vua Sa Pa buổi tối', 'food', '18:30', '21:00', 'Thưởng thức nồi lẩu cá tầm măng chua nóng hổi giữa tiết trời se lạnh và dạo quanh Nhà thờ Đá Sa Pa.', 'Số 08 Xuân Viên, trung tâm Sa Pa', 250000, 'Đặt bàn tầng 2 có cửa sổ nhìn ra phố sương mù.', 3),
(32, 10, 18, 'Đi Tàu hỏa Mường Hoa & Cáp treo chinh phục Đỉnh Fansipan 3.143m', 'attraction', '08:00', '13:30', 'Trải nghiệm tàu hỏa leo núi Mường Hoa từ Sun Plaza, đi cáp treo xuyên biển mây lên chạm tay cột mốc Nóc nhà Đông Dương.', 'Ga tàu hỏa Sun Plaza trung tâm Sa Pa', 950000, 'Đã bao gồm vé cáp treo khứ hồi (800k) + tàu hỏa Mường Hoa (150k).', 1),
(33, 10, 23, 'Ăn trưa Buffet Vân Sam hoặc Cơm bản địa & Nghỉ chiều', 'food', '13:30', '15:30', 'Nạp năng lượng sau hành trình leo đỉnh Fansipan và thư giãn ngâm chân lá thuốc người Dao đỏ.', 'Khu du lịch Fansipan / Thị xã Sa Pa', 200000, 'Ngâm chân thảo dược giúp hồi phục sức chân rất tốt.', 2),
(34, 10, NULL, 'Dạo chợ đêm Sa Pa & Thưởng thức đồ nướng xiên que Cầu Mây', 'food', '19:00', '21:30', 'Thưởng thức thịt lợn bản cuốn cải mèo nướng, cơm lam, trứng nướng và hạt dẻ nóng.', 'Khu phố Cầu Mây - Hồ Sa Pa', 150000, 'Hỏi giá trước khi chọn xiên nướng.', 3),
(35, 11, 17, 'Đón bình minh biển mây & Trekking nhẹ Thung lũng Mường Hoa', 'attraction', '07:00', '10:30', 'Ngắm nắng sớm chiếu xuống những thửa ruộng bậc thang vàng óng tại Lao Chải - Tả Van.', 'Ngay trước khuôn viên Eco Palms House', 0, 'Thời điểm 7h00 - 8h30 sáng mây luồn qua thung lũng đẹp nhất.', 1),
(36, 11, 24, 'Trả phòng & Mua đặc sản Thịt trâu gác bếp, Nấm hương rừng, Tương ớt Mường Khương', 'transport', '11:30', '19:00', 'Ăn trưa, mua quà Tây Bắc tại chợ Sa Pa và lên xe cabin đôi chuyến 14h00 về lại Hà Nội.', 'Xe cabin Sa Pa - Hà Nội', 450000, 'Gồm ăn trưa + vé xe cabin chiều về.', 2),

-- Mẫu 5: Vịnh Hạ Long 2N1Đ (Days 12, 13)
(37, 12, 19, 'Lên Du thuyền Tuyến 2: Khám phá Hang Sửng Sốt – Chèo Kayak Hang Luồn – Đảo Ti Tốp', 'attraction', '09:30', '16:30', 'Hành trình 6 tiếng trên Vịnh Hạ Long qua Hòn Gà Chọi, thăm hang Sửng Sốt, chèo kayak tại Hang Luồn và tắm biển/leo đỉnh đảo Ti Tốp.', 'Cảng tàu khách quốc tế Hạ Long', 590000, 'Gồm vé vịnh + tàu tham quan (490k) + chèo kayak (100k).', 1),
(38, 12, 26, 'Nhận phòng Khách sạn DeLaSea Ha Long View Vịnh & Bơi hồ vô cực', 'checkin_checkout', '16:45', '18:15', 'Nhận phòng Deluxe hướng biển tại Bãi Cháy, thư giãn tại hồ bơi vô cực ngắm hoàng hôn buông xuống vịnh.', 'Đại lộ Hạ Long Marine, Bãi Cháy', 0, 'Chi phí phòng tính trong mục Lưu trú.', 2),
(39, 12, 25, 'Ăn tối Hải sản tươi sống & Chả mực giã tay tại Nhà hàng Hồng Hạnh 3', 'food', '18:45', '21:00', 'Thưởng thức bề bề rang muối, chả mực giã tay Hạ Long, hà nướng mỡ hành và dạo phố cổ Bãi Cháy.', 'Số 50 Hạ Long, Bãi Cháy', 300000, 'Nên đặt bàn trước nếu đi tối thứ 7.', 3),
(40, 13, 26, 'Dạo biển Bãi Cháy buổi sớm & Thưởng thức Bún bề bề / Bánh cuốn chả mực', 'food', '06:30', '09:00', 'Tắm biển sớm tại bãi tắm Bãi Cháy và dùng bữa sáng đặc sản Quảng Ninh.', 'Bãi biển Bãi Cháy', 60000, 'Miễn phí nếu dùng buffet sáng tại khách sạn.', 1),
(41, 13, NULL, 'Check-in Cung đường bao biển Trần Quốc Nghiễn & Bảo tàng Quảng Ninh', 'attraction', '09:15', '11:30', 'Chụp ảnh tại kiến trúc "Viên ngọc đen" Bảo tàng - Thư viện Quảng Ninh bên bờ vịnh Hòn Gai.', 'Qua cầu Bãi Cháy sang Hòn Gai (~7km)', 40000, 'Vé vào bên trong Bảo tàng Quảng Ninh: 40.000đ/người.', 2),
(42, 13, 25, 'Mua quà Chả mực giã tay chợ Hạ Long & Lên Limousine về Hà Nội', 'transport', '12:00', '16:30', 'Ăn trưa sữa chua trân châu Hạ Long + hải sản nhẹ, mua chả mực đóng hút chân không và về Hà Nội.', 'Cao tốc Hạ Long - Hải Phòng - Hà Nội', 460000, 'Gồm ăn trưa + quà + vé xe limousine chiều về.', 3),

-- Mẫu 6: Cao Bằng - Thác Bản Giốc 3N2Đ (Days 14, 15, 16)
(43, 14, 27, 'Tham quan Khu di tích Quốc gia Đặc biệt Pác Bó & Suối Lê Nin xanh ngọc', 'attraction', '13:00', '16:30', 'Chiêm ngưỡng dòng suối Lê Nin trong vắt màu ngọc bích, núi Các Mác, hang Cốc Bó và cột mốc Km0 đường Hồ Chí Minh.', 'Xã Trường Hà, Hà Quảng (cách TP. Cao Bằng 50km)', 45000, 'Nước suối Lê Nin lên màu xanh ngọc đẹp nhất khi trời nắng.', 1),
(44, 14, 28, 'Thưởng thức Vịt Quay 7 Vị & Phở Chua gia truyền tại TP. Cao Bằng', 'food', '18:30', '21:00', 'Ăn tối đặc sản vịt quay 7 vị lá mắc mật, phở chua Cao Bằng, khâu nhục và dạo phố đi bộ Kim Đồng.', 'Phố Kim Đồng, TP. Cao Bằng', 160000, 'Phố đi bộ Kim Đồng nhộn nhịp vào tối thứ 6 và thứ 7.', 2),
(45, 15, 20, 'Chiêm ngưỡng Thác Bản Giốc & Đi bè mảng ra sát chân thác', 'attraction', '09:00', '12:00', 'Ngắm thác nước tự nhiên lớn nhất Đông Nam Á đổ bọt trắng xóa giữa cánh đồng lúa Trùng Khánh và dòng sông Quây Sơn.', 'Xã Đàm Thủy, Trùng Khánh', 95000, 'Gồm vé vào cổng (45k) và vé bè mảng chụp ảnh sát thác (50k).', 1),
(46, 15, 20, 'Khám phá Động Ngườm Ngao (Động Hổ) kỳ ảo nhũ đá đài sen', 'attraction', '13:30', '15:30', 'Đi bộ khám phá hang động đá vôi dài hơn 2km với khối thạch nhũ hoa sen úp ngược độc nhất vô nhị.', 'Cách Thác Bản Giốc 3km', 45000, 'Trong động mát lạnh quanh năm, đường đi lát bê tông có đèn chiếu sáng.', 2),
(47, 15, 29, 'Nhận phòng Nhà sàn đá cổ Khuổi Ky & Ăn tối ẩm thực người Tày', 'checkin_checkout', '16:00', '21:00', 'Lưu trú tại ngôi làng đá cổ 400 năm tuổi của người Tày, thưởng thức cá trầm hương, rau dạ hiến và nghe hát Then.', 'Làng đá Khuổi Ky, Đàm Thủy', 180000, 'Chi phí phòng tính trong mục Lưu trú; 180k là bữa tối bản địa.', 3),
(48, 16, NULL, 'Đón bình minh tại Thảo nguyên Núi Mắt Thần (Núi Thủng) Trà Lĩnh', 'attraction', '07:30', '10:30', 'Ngắm ngọn núi có lỗ thủng tròn xuyên tâm kỳ lạ giữa thung lũng cỏ xanh mướt và hồ nước phẳng lặng.', 'Xã Quốc Toản, Trà Lĩnh (trên cung về TP. Cao Bằng)', 0, 'Miễn phí vé tham quan, cảnh sắc cực kỳ điện ảnh.', 1),
(49, 16, 28, 'Ăn trưa Bánh cuốn canh xương, Mua Hạt dẻ Trùng Khánh & Về Hà Nội', 'transport', '11:30', '18:30', 'Thưởng thức bánh cuốn nóng chan nước xương hầm trứng tại TP. Cao Bằng, mua hạt dẻ Trùng Khánh và lên xe về Hà Nội.', 'Cao Bằng - Hà Nội', 450000, 'Gồm ăn trưa + quà hạt dẻ + vé xe chiều về.', 2),

-- Mẫu 7: Tà Xùa 2N1Đ (Days 17, 18)
(50, 17, 8, 'Check-in Thảo nguyên Tà Xùa, Cây Cô Đơn & Mỏm Cá Heo', 'attraction', '13:30', '16:30', 'Sau khi lên tới đỉnh Tà Xùa và ăn trưa, di chuyển cung thủy điện Suối Sập chụp ảnh Cây Cô Đơn và thảo nguyên xanh.', 'Xe máy từ trung tâm xã Tà Xùa (~11km)', 30000, 'Đường dễ đi, góc chụp nhìn thẳng xuống lòng hồ sông Đà.', 1),
(51, 17, 30, 'Nhận phòng Mando Homestay & Ngắm hoàng hôn biển mây tại Đỉnh Gió', 'checkin_checkout', '16:45', '18:30', 'Nhận phòng gỗ kính săn mây, thưởng thức trà Shan Tuyết cổ thụ nóng hổi ngắm mặt trời lặn xuống biển mây tím.', 'Khu Đỉnh Gió, Xã Tà Xùa', 60000, '60.000đ đồ uống tại tiệm cà phê Đỉnh Gió; tiền phòng tính ở mục Lưu trú.', 2),
(52, 17, 30, 'Ăn tối Lẩu Gà Đen H''Mông giữa sương mù Tà Xùa', 'food', '19:00', '21:30', 'Quây quần bên nồi lẩu gà đen nóng hổi trong tiết trời vùng cao 10-14 độ C.', 'Tại nhà hàng Đỉnh Gió Tà Xùa', 180000, 'Nên đi ngủ sớm trước 22h30 để sáng hôm sau dậy lúc 5h00 đi săn mây.', 3),
(53, 18, 8, 'Săn Bình Minh Biển Mây trên Sống Lưng Khủng Long Háng Đồng', 'attraction', '05:15', '08:30', 'Khoảnh khắc đắt giá nhất chuyến đi: đứng trên sống núi hẹp vươn ra giữa đại dương mây trắng bồng bềnh lúc bình minh.', 'Xe máy từ Tà Xùa vào Háng Đồng (~13km)', 50000, 'Nếu tay lái yếu nên thuê xe ôm bản địa tại chân dốc (50.000đ khứ hồi).', 1),
(54, 18, 30, 'Thưởng thức Mì tôm săn mây, Trả phòng & Lên Limousine về Hà Nội', 'transport', '09:00', '17:00', 'Về lại homestay thu dọn hành lý, ăn trưa gà nướng mắc khén tại thị trấn Bắc Yên và lên xe limousine về Hà Nội.', 'Limousine Tà Xùa - Bắc Yên - Hà Nội', 450000, 'Gồm ăn trưa + vé xe limousine chiều về.', 2),

-- Mẫu 8: Pù Luông - Hà Nội 2N1Đ (Days 19, 20)
(55, 19, 22, 'Dạo phố thu Phan Đình Phùng – Thưởng thức Cốm Làng Vòng & Cà phê trứng Hà Nội', 'attraction', '07:00', '09:00', 'Chụp ảnh bên những xe hoa mùa thu dưới hàng sấu cổ thụ Phan Đình Phùng, nhâm nhi cà phê trứng và xôi cốm trước giờ khởi hành.', 'Quận Ba Đình & Hoàn Kiếm, Hà Nội', 80000, 'Nắng sớm xuyên qua vòm cây lúc 7h00 - 8h30 là khung giờ vàng.', 1),
(56, 19, 31, 'Khởi hành đi Pù Luông & Nhận phòng Eco Garden View Ruộng Bậc Thang Bản Đôn', 'checkin_checkout', '09:15', '14:30', 'Theo cung đường đèo Thung Khe - Lũng Vân về Bản Đôn Pù Luông, nhận bungalow sát thung lũng lúa chín và bơi hồ vô cực.', 'Limousine Hà Nội - Pù Luông (4 giờ)', 250000, '250.000đ là vé xe limousine chiều đi Hà Nội - Pù Luông.', 2),
(57, 19, 32, 'Trekking Bản Đôn & Ăn tối Đặc sản Vịt Cổ Lũng quay lá mắc mật', 'food', '16:00', '20:30', 'Đi bộ len lỏi giữa những thửa ruộng bậc thang vàng óng và thưởng thức bữa tối vịt Cổ Lũng trứ danh bên nhà sàn Thái.', 'Trung tâm Bản Đôn, Thành Lâm', 180000, 'Vịt Cổ Lũng ngon nhất khi đặt nhà hàng nướng than hoa trước 1 tiếng.', 3),
(58, 20, 21, 'Trải nghiệm Bè tre Suối Chàm & Ngắm Guồng nước tre khổng lồ', 'attraction', '08:00', '11:00', 'Ngồi bè mảng bằng tre xuôi dòng suối Chàm trong xanh và tìm hiểu hệ thống cọn nước (guồng nước) dẫn nước vào ruộng của người Thái.', 'Suối Chàm - Thác Hiêu, Pù Luông', 100000, 'Vé đi bè tre suối Chàm: 100.000đ/người.', 1),
(59, 20, 32, 'Ăn trưa Cá suối nướng, Cơm lam & Lên xe Limousine trở về Hà Nội', 'transport', '11:30', '17:30', 'Trả phòng khu nghỉ dưỡng, dùng bữa trưa cá suối pa pỉnh tộp, măng rừng và lên xe limousine về lại Hà Nội.', 'Limousine Pù Luông - Hà Nội', 390000, 'Gồm ăn trưa (140k) + vé xe limousine chiều về (250k).', 2);

-- Gợi ý lưu trú của tất cả 8 lịch trình mẫu (M2/M6)
INSERT INTO `template_accommodations` (`id`, `template_id`, `night_number`, `location_id`, `stay_name`, `room_count`, `base_cost`, `notes`) VALUES
(1, 1, 1, 7, 'Mama''s House Hostel & Homestay Mộc Châu (Đêm 1)', 1, 750000, 'Phòng đôi Bungalow gỗ view đồi mơ (cho 2 người, đã gồm ăn sáng)'),
(2, 1, 2, 7, 'Mama''s House Hostel & Homestay Mộc Châu (Đêm 2)', 1, 750000, 'Tiếp tục lưu trú đêm thứ 2 tại Bản Áng để tiện di chuyển các điểm'),
(3, 2, 1, 12, 'Homestay Phố Cổ Đồng Văn (Đêm 1)', 1, 650000, 'Phòng đôi ngay khu phố cổ thuận tiện dạo tối và thưởng thức lẩu gà đen'),
(4, 2, 2, 12, 'Làng H''Mông Pả Vi Homestay Mèo Vạc (Đêm 2)', 1, 700000, 'Không gian kiến trúc trình tường dưới chân đèo Mã Pí Lèng'),
(5, 3, 1, 16, 'Tam Cốc Garden & Eco Bungalow (Đêm 1)', 1, 1100000, 'Phòng Deluxe view đồng lúa và núi đá vôi cho 2 người (gồm xe đạp & bữa sáng)'),
(6, 4, 1, 24, 'Eco Palms House - Sapa Retreat View Mường Hoa (Đêm 1)', 1, 950000, 'Bungalow tre ban công nhìn thẳng ra dãy Hoàng Liên Sơn và thung lũng Mường Hoa'),
(7, 4, 2, 24, 'Eco Palms House - Sapa Retreat View Mường Hoa (Đêm 2)', 1, 950000, 'Đêm thứ 2 nghỉ dưỡng giữa biển mây bản Lao Chải (gồm bữa sáng)'),
(8, 5, 1, 26, 'DeLaSea Ha Long Hotel & Bay View Bãi Cháy (Đêm 1)', 1, 1200000, 'Phòng Deluxe Ocean View sát biển Bãi Cháy cho 2 người (gồm buffet sáng & hồ bơi vô cực)'),
(9, 6, 1, 29, 'Khách sạn trung tâm Phố đi bộ Kim Đồng - TP. Cao Bằng (Đêm 1)', 1, 600000, 'Phòng đôi trung tâm thành phố thuận tiện đi Pác Bó và dạo phố đêm'),
(10, 6, 2, 29, 'Khuổi Ky Eco Homestay - Làng Đá Cổ Bản Giốc (Đêm 2)', 1, 700000, 'Nghỉ đêm tại nhà sàn đá cổ 400 năm tuổi của người Tày cách Thác Bản Giốc 2km'),
(11, 7, 1, 30, 'Mando Homestay & Tiệm Cà Phê Đỉnh Gió Tà Xùa (Đêm 1)', 1, 650000, 'Phòng gỗ kính view biển mây trực diện trên Đỉnh Gió (cho 2 người)'),
(12, 8, 1, 31, 'Pù Luông Eco Garden & Retreat Bản Đôn (Đêm 1)', 1, 950000, 'Bungalow view ruộng bậc thang Bản Đôn có hồ bơi vô cực nước suối tự nhiên');

-- Dự toán mẫu chi tiết cho tất cả 8 Lịch trình mẫu (M2/M6)
INSERT INTO `template_expenses` (`id`, `template_id`, `expense_category`, `title`, `estimated_amount`, `linked_activity_id`, `linked_accommodation_id`, `notes`) VALUES
-- Mẫu 1: Mộc Châu 3N2Đ (Tổng 4.900.000đ / 2 người = 2.450.000đ/người)
(1, 1, 'transport', 'Vé xe Limousine Hà Nội - Mộc Châu khứ hồi (2 người)', 1000000, 1, NULL, '250.000đ/người/chiều x 2 chiều x 2 người'),
(2, 1, 'transport', 'Thuê xe máy 2 ngày tại Mộc Châu + Xăng xe', 400000, NULL, NULL, '150.000đ/ngày x 2 ngày + 100.000đ xăng'),
(3, 1, 'stay', 'Phòng Bungalow gỗ Mama''s House (2 đêm)', 1500000, NULL, 1, '750.000đ/đêm x 2 đêm (cho 2 người)'),
(4, 1, 'food', 'Ăn uống trọn gói 3 ngày 2 đêm (Cá hồi, BBQ, Bê chao, Ăn sáng)', 1340000, 2, NULL, 'Tính cho 2 người trong 3 ngày'),
(5, 1, 'ticket', 'Vé tham quan Nà Ka, Bản Áng, Thác Dải Yếm & Đồi chè (2 người)', 460000, 4, NULL, 'Tổng vé tham quan các điểm cho 2 người'),
(6, 1, 'shopping', 'Mua quà đặc sản sữa Mộc Châu & dâu tây', 200000, 12, NULL, 'Chi phí mua quà dự kiến'),

-- Mẫu 2: Hà Giang 3N2Đ (Tổng 6.200.000đ / 2 người = 3.100.000đ/người)
(7, 2, 'transport', 'Xe cabin đôi Hà Nội - TP. Hà Giang khứ hồi (2 người)', 1400000, 21, NULL, '350.000đ/người/chiều x 2 chiều x 2 người'),
(8, 2, 'transport', 'Thuê xe máy côn/số đời mới 3 ngày + Xăng xe đường đèo', 950000, 14, NULL, '250.000đ/ngày x 3 ngày + 200.000đ xăng'),
(9, 2, 'stay', 'Lưu trú 2 đêm tại Phố cổ Đồng Văn & Làng H''Mông Pả Vi', 1350000, NULL, 3, '650.000đ (Đêm 1) + 700.000đ (Đêm 2) cho 2 người'),
(10, 2, 'food', 'Ăn uống 3N2Đ (Lẩu gà đen, Thắng cố, Bánh cuốn trứng, Cơm bản)', 1690000, 16, NULL, 'Tính cho 2 người trong suốt hành trình'),
(11, 2, 'ticket', 'Vé thuyền Sông Nho Quế, Cột cờ Lũng Cú & Dinh Vua Mèo (2 người)', 510000, 18, NULL, 'Gồm vé thuyền hẻm Tu Sản + xe điện + vé điểm tham quan'),
(12, 2, 'shopping', 'Mua quà thịt trâu gác bếp, bánh tam giác mạch & mật ong bạc hà', 300000, NULL, NULL, 'Chi phí mua đặc sản vùng cao dự kiến'),

-- Mẫu 3: Ninh Bình 2N1Đ (Tổng 3.300.000đ / 2 người = 1.650.000đ/người)
(13, 3, 'transport', 'Vé xe Limousine Hà Nội - Ninh Bình khứ hồi + Thuê xe máy/xe đạp (2 người)', 850000, 28, NULL, 'Limousine 2 chiều cho 2 người + phương tiện nội khu'),
(14, 3, 'stay', 'Nghỉ dưỡng 1 đêm tại Tam Cốc Garden & Eco Bungalow', 1100000, NULL, 5, 'Phòng Deluxe cho 2 người (đã gồm bữa sáng)'),
(15, 3, 'ticket', 'Vé đò Danh thắng Tràng An, Đỉnh Hang Múa & Cố đô Hoa Lư (2 người)', 740000, 22, NULL, '(250k + 100k + 20k) x 2 người'),
(16, 3, 'food', 'Thưởng thức Đặc sản Dê núi Chính Thư & Cơm cháy Ninh Bình (2 người)', 610000, 23, NULL, 'Ăn trưa, ăn tối và mua cơm cháy làm quà'),

-- Mẫu 4: Sa Pa 3N2Đ (Tổng 5.900.000đ / 2 người = 2.950.000đ/người)
(17, 4, 'transport', 'Xe cabin đôi Hà Nội - Sa Pa khứ hồi & Taxi đi bản (2 người)', 1200000, 29, NULL, '300.000đ/người/chiều x 2 chiều x 2 người'),
(18, 4, 'stay', 'Lưu trú 2 đêm tại Eco Palms House View Mường Hoa', 1900000, NULL, 6, '950.000đ/đêm x 2 đêm (cho 2 người, gồm ăn sáng)'),
(19, 4, 'ticket', 'Combo Cáp treo Fansipan + Tàu hỏa Mường Hoa + Vé Bản Cát Cát (2 người)', 1900000, 32, NULL, 'Vé cáp treo khứ hồi + tàu hỏa Mường Hoa + vé Cát Cát cho 2 người'),
(20, 4, 'food', 'Ẩm thực Lẩu cá tầm, Gỏi cá hồi & Đồ nướng Sa Pa (2 người)', 900000, 31, NULL, 'Các bữa ăn đặc sản Tây Bắc tại Sa Pa cho 2 người'),

-- Mẫu 5: Vịnh Hạ Long 2N1Đ (Tổng 4.700.000đ / 2 người = 2.350.000đ/người)
(21, 5, 'transport', 'Xe Limousine cao tốc Hà Nội - Hạ Long khứ hồi (2 người)', 920000, 42, NULL, '230.000đ/người/chiều x 2 chiều x 2 người'),
(22, 5, 'stay', 'Khách sạn DeLaSea Ha Long Ocean View Bãi Cháy (1 đêm)', 1200000, NULL, 8, 'Phòng đôi hướng vịnh kèm buffet sáng'),
(23, 5, 'ticket', 'Du thuyền Vịnh Hạ Long Tuyến 2 + Chèo Kayak + Bảo tàng Quảng Ninh (2 người)', 1260000, 37, NULL, '(490k tàu vịnh + 100k kayak + 40k bảo tàng) x 2 người'),
(24, 5, 'food', 'Hải sản Hồng Hạnh 3, Bún bề bề & Quà Chả mực giã tay (2 người)', 1320000, 39, NULL, 'Gồm các bữa ăn hải sản và mua chả mực làm quà'),

-- Mẫu 6: Cao Bằng - Thác Bản Giốc 3N2Đ (Tổng 5.300.000đ / 2 người = 2.650.000đ/người)
(25, 6, 'transport', 'Xe giường nằm Hà Nội - Cao Bằng khứ hồi + Thuê xe máy 3 ngày (2 người)', 1950000, 49, NULL, 'Vé xe khách 2 chiều + xe máy đi Pác Bó - Bản Giốc'),
(26, 6, 'stay', 'Lưu trú 2 đêm tại TP. Cao Bằng & Làng đá cổ Khuổi Ky Bản Giốc', 1300000, NULL, 10, '600.000đ (Đêm 1) + 700.000đ (Đêm 2)'),
(27, 6, 'ticket', 'Vé Thác Bản Giốc, Bè mảng chân thác, Động Ngườm Ngao & Pác Bó (2 người)', 370000, 45, NULL, '(95k + 45k + 45k) x 2 người'),
(28, 6, 'food', 'Ẩm thực Vịt quay 7 vị, Phở chua, Cơm Tày & Quà Hạt dẻ Trùng Khánh (2 người)', 1680000, 44, NULL, 'Trọn gói ăn uống 3N2Đ và mua đặc sản'),

-- Mẫu 7: Tà Xùa 2N1Đ (Tổng 3.500.000đ / 2 người = 1.750.000đ/người)
(29, 7, 'transport', 'Xe Limousine Hà Nội - Tà Xùa khứ hồi + Thuê xe máy săn mây (2 người)', 1350000, 54, NULL, 'Limousine 2 chiều cho 2 người + xe máy đi Háng Đồng'),
(30, 7, 'stay', 'Phòng gỗ kính săn mây tại Mando Homestay Đỉnh Gió (1 đêm)', 650000, NULL, 11, 'Phòng đôi view biển mây trực diện'),
(31, 7, 'food', 'Lẩu gà đen H''Mông, Trà Shan Tuyết Đỉnh Gió & Ăn trưa Bắc Yên (2 người)', 1240000, 52, NULL, 'Ăn uống trọn gói 2 ngày 1 đêm cho 2 người'),
(32, 7, 'ticket', 'Chi phí gửi xe, check-in Thảo nguyên & Xe ôm hỗ trợ Sống lưng khủng long', 260000, 53, NULL, 'Tính cho 2 người'),

-- Mẫu 8: Pù Luông - Hà Nội 2N1Đ (Tổng 3.700.000đ / 2 người = 1.850.000đ/người)
(33, 8, 'transport', 'Xe Limousine Hà Nội - Bản Đôn Pù Luông khứ hồi (2 người)', 1000000, 56, NULL, '250.000đ/người/chiều x 2 chiều x 2 người'),
(34, 8, 'stay', 'Nghỉ dưỡng Bungalow tại Pù Luông Eco Garden & Retreat (1 đêm)', 950000, NULL, 12, 'Phòng view ruộng bậc thang kèm bể bơi vô cực & bữa sáng'),
(35, 8, 'ticket', 'Đi bè tre Suối Chàm, Guồng nước cổ & Xe điện bản làng (2 người)', 350000, 58, NULL, 'Vé bè tre suối Chàm + xe điện tham quan cho 2 người'),
(36, 8, 'food', 'Ẩm thực Vịt Cổ Lũng quay mắc mật, Cá suối nướng & Cốm thu Hà Nội (2 người)', 1400000, 57, NULL, 'Gồm thưởng thức cốm/cà phê trứng Hà Nội và các bữa đặc sản tại Pù Luông');

-- ============================================================================
-- CHUYẾN ĐI CÁ NHÂN MẪU ĐÃ TẠO TỪ LỊCH TRÌNH MẪU (M4, M6, M7)
-- ============================================================================
INSERT INTO `trips` (
  `id`, `user_id`, `source_template_id`, `province_id`, `title`, `main_destination`,
  `start_date`, `end_date`, `pax_count`, `departure_point`, `total_budget`,
  `description`, `cover_image_url`, `share_token`, `is_shared`
) VALUES
(1, 2, 1, 1, 'Chuyến đi Mộc Châu săn hoa mận cùng hội bạn', 'Mộc Châu - Sơn La',
 '2026-11-20', '2026-11-22', 2, 'Hà Nội', 5200000,
 'Kế hoạch cá nhân được tạo từ lịch trình mẫu "Mộc Châu 3 Ngày 2 Đêm", đã điều chỉnh thêm quán cà phê săn mây và cập nhật chi phí thực tế đặt cọc.',
 'https://images.unsplash.com/photo-1490750967868-88aa4486c946?w=900&auto=format&fit=crop&q=80',
 'bacdu-mocchau-2026-demo', 1);

INSERT INTO `trip_days` (`id`, `trip_id`, `day_number`, `trip_date`, `title`, `notes`) VALUES
(1, 1, 1, '2026-11-20', 'Ngày 1: Hà Nội – Đèo Đá Trắng – Rừng Thông Bản Áng', 'Nhớ ra điểm đón limousine trước 6h45 sáng'),
(2, 1, 2, '2026-11-21', 'Ngày 2: Đồi Chè Trái Tim – Thung Lũng Mận Nà Ka – Thác Dải Yếm', 'Chuẩn bị trang phục sáng màu để chụp ảnh đồi chè và vườn mận'),
(3, 1, 3, '2026-11-22', 'Ngày 3: Mua quà đặc sản Mộc Châu – Trở về Hà Nội', 'Mua sữa tươi thanh trùng và mận sấy cho gia đình');

INSERT INTO `activities` (
  `id`, `trip_day_id`, `location_id`, `title`, `activity_type`,
  `start_time`, `end_time`, `description`, `estimated_cost`, `notes`, `sort_order`
) VALUES
(1, 1, NULL, 'Di chuyển Hà Nội – Mộc Châu bằng xe Limousine Xuân Tráng', 'transport', '07:00', '11:30', 'Xe đón tại cổng BigC Thăng Long, dừng nghỉ đèo Thung Khe 20 phút.', 500000, 'Đã đặt 2 ghế giữa', 1),
(2, 1, 5, 'Ăn trưa Set Cá Hồi tại Nhà hàng Xuân Bắc 181', 'food', '11:45', '13:15', 'Set cá hồi tươi 6 món cho 2 người.', 500000, 'Đã gọi điện dặn trước nhà hàng', 2),
(3, 1, 7, 'Check-in Mama''s House Homestay Bản Áng', 'checkin_checkout', '13:30', '14:30', 'Nhận phòng gỗ áp mái số 05 và nhận xe máy tay ga.', 0, 'Tiền phòng quản lý ở bảng Lưu trú/Khoản chi để không cộng trùng', 3),
(4, 1, 4, 'Đạp xe Rừng thông Bản Áng & Hái dâu Chimi Farm', 'attraction', '15:00', '18:00', 'Tham quan hồ Bản Áng, ăn sữa chua dâu tây.', 260000, 'Vé cổng 2 người + 1 hộp dâu tây', 4),
(5, 2, 2, 'Chụp ảnh bình minh Đồi chè Trái Tim', 'attraction', '07:30', '09:30', 'Đi sớm đón nắng mai trên đồi chè.', 40000, 'Tiền gửi xe và thuê gùi chè', 1),
(6, 2, 1, 'Khám phá Thung lũng mận Nà Ka', 'attraction', '09:45', '12:00', 'Tham quan vườn mận lớn nhất Mộc Châu.', 160000, 'Vé vào vườn 2 người + thuê váy dân tộc', 2),
(7, 2, 6, 'Ăn trưa Bê Chao 70 Mộc Châu', 'food', '12:30', '14:00', 'Bê chao, cải mèo luộc và cá suối nướng.', 360000, 'Quán ngay trục QL6', 3),
(8, 2, 3, 'Tham quan Thác Dải Yếm', 'attraction', '14:30', '17:30', 'Ngắm thác nước 9 tầng tại xã Mường Sang.', 200000, 'Vé tham quan 2 người', 4),
(9, 3, NULL, 'Mua đặc sản Mộc Châu Milk & Lên xe về Hà Nội', 'other', '09:30', '17:30', 'Mua bánh sữa, thịt trâu gác bếp và lên xe limousine chuyến 14h00.', 850000, 'Gồm quà 350k + vé xe chiều về 500k', 1);

INSERT INTO `accommodations` (
  `id`, `trip_id`, `location_id`, `stay_name`, `address`,
  `checkin_date`, `checkin_time`, `checkout_date`, `checkout_time`,
  `room_count`, `estimated_cost`, `actual_cost`, `contact_info`, `booking_code`, `booking_status`, `notes`
) VALUES
(1, 1, 7, 'Mama''s House Hostel & Homestay Mộc Châu', 'Bản Áng 3, Xã Đông Sang, Mộc Châu, Sơn La',
 '2026-11-20', '14:00', '2026-11-22', '12:00',
 1, 1500000, 1500000, '0989 331 556', 'MMH-2026-889', 'booked', 'Đã chuyển khoản cọc 50% (750.000đ), phòng Bungalow gỗ số 05 có bình nóng lạnh.');

INSERT INTO `transports` (
  `id`, `trip_id`, `transport_type`, `departure_point`, `arrival_point`,
  `departure_datetime`, `arrival_datetime`, `estimated_cost`, `actual_cost`, `booking_code`, `notes`
) VALUES
(1, 1, 'Xe Limousine Xuân Tráng (Khứ hồi)', 'BigC Thăng Long - Hà Nội', 'Bản Áng - Mộc Châu',
 '2026-11-20 07:00:00', '2026-11-20 11:15:00', 1000000, 1000000, 'XTLIM-9921', 'Đã đặt khứ hồi 2 ghế giữa cho 2 người, SĐT nhà xe: 0981 552 222'),
(2, 1, 'Thuê xe máy tay ga tại Homestay', 'Mama''s House Bản Áng', 'Các điểm tham quan Mộc Châu',
 '2026-11-20 14:00:00', '2026-11-22 11:30:00', 400000, 380000, NULL, 'Xe Vision mới, mũ bảo hiểm có kính chắn gió.');

-- Khoản chi của chuyến đi (Bảng nguồn tính tổng ngân sách - liên kết chặt chẽ tránh cộng trùng)
INSERT INTO `expenses` (
  `id`, `trip_id`, `expense_category`, `title`, `estimated_amount`, `actual_amount`,
  `linked_activity_id`, `linked_accommodation_id`, `linked_transport_id`, `expense_date`, `notes`
) VALUES
(1, 1, 'stay', 'Phòng Bungalow Mama''s House (2 đêm)', 1500000, 1500000, NULL, 1, NULL, '2026-11-20', 'Liên kết với mục Lưu trú #1'),
(2, 1, 'transport', 'Vé xe Limousine khứ hồi Hà Nội - Mộc Châu (2 người)', 1000000, 1000000, 1, NULL, 1, '2026-11-20', 'Liên kết với Phương tiện #1 & Hoạt động #1'),
(3, 1, 'transport', 'Thuê xe máy 2 ngày + đổ xăng', 400000, 380000, NULL, NULL, 2, '2026-11-20', 'Liên kết với Phương tiện #2'),
(4, 1, 'food', 'Ăn trưa Cá hồi Xuân Bắc 181 + BBQ tối Ngày 1', 900000, 880000, 2, NULL, NULL, '2026-11-20', 'Chi phí ăn uống ngày 1 cho 2 người'),
(5, 1, 'food', 'Ăn trưa Bê Chao 70 + Lẩu gà đồi tối Ngày 2', 700000, 0, 7, NULL, NULL, '2026-11-21', 'Dự kiến chi ngày 2'),
(6, 1, 'ticket', 'Vé tham quan Bản Áng, Nà Ka, Đồi chè, Thác Dải Yếm', 460000, 200000, 6, NULL, NULL, '2026-11-21', 'Vé các điểm tham quan trong lịch trình'),
(7, 1, 'shopping', 'Mua đặc sản sữa Mộc Châu, bánh sữa, dâu tây làm quà', 350000, 0, 9, NULL, NULL, '2026-11-22', 'Quà mang về');

INSERT INTO `checklists` (`id`, `trip_id`, `item_name`, `category`, `is_completed`, `sort_order`) VALUES
(1, 1, 'Căn cước công dân (CCCD) & Bằng lái xe máy', 'Giấy tờ & Tiền mặt', 1, 1),
(2, 1, 'Áo khoác giữ nhiệt, khăn choàng cổ & mũ len chụp ảnh', 'Trang phục', 1, 2),
(3, 1, 'Giày thể thao đế bám tốt (đi bộ vườn mận Nà Ka & thác Dải Yếm)', 'Trang phục', 1, 3),
(4, 1, 'Sạc dự phòng, máy ảnh & thẻ nhớ trống', 'Thiết bị điện tử', 0, 4),
(5, 1, 'Thuốc say xe, băng cá nhân & kem dưỡng ẩm chống nẻ', 'Y tế cá nhân', 0, 5);

INSERT INTO `favorite_locations` (`user_id`, `location_id`) VALUES
(2, 1), (2, 2), (2, 7), (3, 9), (3, 13), (4, 5), (4, 7), (5, 13), (5, 20), (6, 9), (6, 10);

-- ============================================================================
-- BÀI TRẢI NGHIỆM CỘNG ĐỒNG (M3) - Đủ trạng thái: Đã duyệt, Chờ duyệt, Từ chối, Bản nháp
-- ============================================================================
INSERT INTO `experience_posts` (
  `id`, `user_id`, `location_id`, `title`, `visit_month`, `visit_year`,
  `content`, `rating`, `ref_cost`, `cost_unit`,
  `pros_liked`, `cons_inconvenient`, `tips_advice`,
  `status`, `rejection_reason`, `reviewed_by`, `reviewed_at`
) VALUES
(1, 2, 1, 'Kinh nghiệm săn hoa mận trắng Nà Ka tháng 2: Đi giờ nào nắng đẹp và vắng người?', 2, 2026,
 'Mình vừa trở về từ chuyến đi Mộc Châu 3 ngày 2 đêm đầu tháng 2 và thực sự choáng ngợp trước vẻ đẹp của thung lũng mận Nà Ka. Cả thung lũng rộng hàng trăm hecta nở trắng muốt như tuyết phủ. Đường từ đồi chè Trái Tim đi vào Nà Ka hiện đã đổ bê tông rất êm, xe máy hay ô tô 16 chỗ đều vào tận cổng vườn dễ dàng. Buổi sáng từ 8h30 đến 10h30 là lúc sương vừa tan, nắng vàng chiếu xuyên qua tán hoa mận lên ảnh trong veo.',
 5, 80000, 'người (vé + thuê đồ)',
 'Không gian thung lũng cực kỳ rộng nên dù đông khách vẫn có rất nhiều góc chụp vắng người; người dân bản địa thân thiện, giá vé chỉ 30.000đ/người.',
 'Buổi trưa nắng khá gắt và cuối tuần đoạn cua vào cổng vườn hơi đông xe ô tô tránh nhau.',
 'Nên mặc trang phục màu trắng, be, đỏ trầm hoặc xanh pastel để nổi bật trên nền hoa trắng. Đi giày thể thao vì đi sâu vào sườn đồi cảnh càng đẹp!',
 'approved', NULL, 1, '2026-02-05 10:30:00'),

(2, 4, 5, 'Review thực tế Set Cá Hồi Xuân Bắc 181 Mộc Châu: Đầy đặn, tươi ngọt đáng tiền', 2, 2026,
 'Đi Mộc Châu mùa lạnh mà ngồi bên nồi lẩu cá hồi nghi ngút khói thì chuẩn bài! Nhóm mình đi 4 người gọi set cá hồi 6 món tại nhà hàng Xuân Bắc 181 ngay tiểu khu Chiềng Đi. Cá hồi được bắt trực tiếp từ bể nước lạnh nên lát gỏi sashimi tươi rói, không hề tanh. Món mình ấn tượng nhất là da cá hồi chiên giòn cuộn rau rừng chấm xì dầu mù tạt và cá hồi nướng mắc khén thơm lừng.',
 5, 250000, 'người',
 'Cá tươi sống chế biến tại chỗ, lên món nhanh dù quán đông, nhân viên nhiệt tình châm thêm nước lẩu và rau cải mèo miễn phí.',
 'Giờ cao điểm trưa thứ Bảy (12h00 - 13h00) khá ồn và phải đợi bàn khoảng 10-15 phút nếu chưa đặt trước.',
 'Nhớ gọi điện đặt bàn trước khi từ đèo Thung Khe xuống hoặc từ đồi chè ra để nhà hàng chuẩn bị sẵn không phải chờ.',
 'approved', NULL, 1, '2026-02-06 14:15:00'),

(3, 3, 7, 'Ở 2 đêm tại Mama''s House Bản Áng: Những căn nhà gỗ ấm áp ngay dưới tán rừng mơ', 2, 2026,
 'Lần thứ hai quay lại Mộc Châu mình vẫn chọn Mama''s House ở Bản Áng 3. Homestay nằm trên sườn đồi thoai thoải, mỗi căn bungalow gỗ đều có cửa sổ lớn nhìn thẳng ra vườn mơ, vườn mận. Thích nhất là buổi tối đặt tiệc nướng BBQ ngay sân chung, nhâm nhi chút rượu mơ Mộc Châu bên bếp lửa củi ấm sực.',
 5, 750000, 'phòng/đêm',
 'Phòng sạch sẽ, chăn đệm thơm tho, có bình nóng lạnh khỏe và máy sấy tóc; bữa sáng mì/bún nóng miễn phí rất ngon.',
 'Vì nằm trên sườn đồi nên có một số bậc thang dốc, nếu mang vali quá to sẽ hơi mỏi chân khi xách lên phòng cao.',
 'Nên đặt phòng Bungalow áp mái tầng trên để có tầm nhìn thoáng nhất ra thung lũng Bản Áng.',
 'approved', NULL, 1, '2026-02-07 09:00:00'),

(4, 6, 9, 'Đi thuyền sông Nho Quế ngắm hẻm vực Tu Sản: Hướng dẫn xuống bến mới thuận tiện nhất', 10, 2025,
 'Dòng sông Nho Quế nhìn từ đỉnh đèo Mã Pí Lèng đã đẹp, nhưng khi ngồi trên thuyền trôi giữa hai vách đá dựng đứng cao hàng trăm mét của hẻm vực Tu Sản mới thấy hết sự kỳ vĩ của thiên nhiên Hà Giang. Hiện tại xe điện trung chuyển đưa khách từ bãi đỗ xe Pả Vi xuống thẳng bến thuyền rất an toàn.',
 5, 120000, 'người',
 'Nước sông xanh màu ngọc bích quanh năm, lái thuyền hỗ trợ chụp ảnh ở mũi thuyền rất nhiệt tình.',
 'Bậc thang từ bến chờ xuống thuyền hơi dốc, cần chú ý khi đi cùng người lớn tuổi.',
 'Canh thời gian xuống bến thuyền khoảng 13h30 - 15h00 khi ánh nắng chiếu thẳng vào khe núi Tu Sản để màu nước lên ảnh rực rỡ nhất.',
 'approved', NULL, 1, '2026-01-18 16:00:00'),

(5, 5, 13, 'Trải nghiệm đi đò Tràng An tuyến 3: Hành cung Vũ Lâm và hang Vân Trên dài 1km', 5, 2025,
 'Nếu bạn đã từng đi tuyến 1 hoặc tuyến 2 Tràng An thì rất nên thử tuyến 3. Tuyến này đi qua hang Đột – hang nước dài nhất quần thể dài tới 1.000m mát lạnh và dừng chân tại Hành cung Vũ Lâm cổ kính.',
 5, 250000, 'người',
 'Bến thuyền tổ chức văn minh, không chèo kéo tiền tip, cảnh sắc non nước yên bình.',
 'Thời gian ngồi đò khoảng 3 tiếng nên nếu đi giữa trưa tháng 6 sẽ hơi nắng.',
 'Đi chuyến sáng sớm 7h30 vừa mát mẻ, vắng đò lại chụp ảnh tại thủy đình Hành cung Vũ Lâm không bị vướng người.',
 'approved', NULL, 1, '2026-01-22 11:20:00'),

(6, 2, 8, 'Săn mây Tà Xùa tháng 12: Kinh nghiệm chọn giờ xuống sống lưng khủng long', 12, 2025,
 'Mình lên Tà Xùa đúng hôm tối trước có mưa phùn nhẹ, sáng hôm sau 5h30 dậy ra sống lưng khủng long Háng Đồng thì gặp biển mây cuồn cuộn tràn qua sườn núi đẹp như tranh vẽ.',
 5, 60000, 'người',
 'Biển mây giữ được lâu đến gần 9h30 sáng, các quán cà phê quanh Đỉnh Gió có view cực kỳ thoáng.',
 'Đường xuống sống lưng khủng long hơi nhỏ và dốc.',
 'Nếu tay lái yếu, bạn nên gửi xe ở đầu dốc và thuê xe ôm người Mông bản địa chở xuống (60.000đ khứ hồi) cho yên tâm.',
 'pending', NULL, NULL, NULL),

(7, 6, 2, 'Chụp ảnh đồi chè Trái Tim buổi chiều', 1, 2026,
 'Đồi chè đẹp nhưng bài viết này mình mới ghi ngắn gọn vài dòng để test chức năng duyệt bài.',
 4, 20000, 'người',
 'Đồi chè rộng, không khí mát mẻ.',
 'Chiều muộn hơi ngược sáng.',
 'Nên đi buổi sáng.',
 'rejected', 'Nội dung bài chia sẻ còn quá ngắn và thiếu thông tin chi tiết trải nghiệm thực tế (giờ mở cửa, góc chụp, lưu ý di chuyển). Bạn vui lòng bổ sung thêm mô tả và gửi duyệt lại nhé!', 1, '2026-02-08 08:30:00'),

(8, 3, 18, 'Kinh nghiệm đi cáp treo Fansipan & tàu hỏa Mường Hoa không phải xếp hàng lâu', 9, 2025,
 'Tháng 9 vừa rồi mình đi Sa Pa đúng mùa lúa chín vàng ở thung lũng Mường Hoa. Từ ga Sun Plaza ngay trung tâm thị xã, ngồi tàu hỏa leo núi Mường Hoa 6 phút nhìn xuống thung lũng đẹp như Thụy Sĩ. Lên tới đỉnh Fansipan 3.143m trời nắng trong vắt, biển mây trắng xóa nằm ngay dưới chân Đại tượng Phật A Di Đà.',
 5, 950000, 'người (Cáp treo + Tàu hỏa)',
 'Hệ thống cáp treo và tàu hỏa hiện đại, vận hành êm; cảnh sắc từ độ cao 3.000m hùng vĩ choáng ngợp.',
 'Trên đỉnh gió khá mạnh và nhiệt độ thấp hơn thị xã khoảng 8-10 độ C.',
 'Nên có mặt tại ga Sun Plaza lúc 7h30 sáng để đi chuyến sớm nhất, vừa vắng khách vừa dễ săn được biển mây trước khi nắng lên cao.',
 'approved', NULL, 1, '2026-01-25 10:00:00'),

(9, 4, 23, 'Lẩu cá tầm Vua Sa Pa số 08 Xuân Viên: Nước lẩu măng chua chuẩn vị vùng cao', 11, 2025,
 'Buổi tối ở Sa Pa sương mù lạnh 11 độ mà gọi một nồi lẩu cá tầm măng chua kèm đĩa gỏi cá hồi thì ấm bụng vô cùng. Thịt cá tầm tươi giòn sần sật, không hề bị bở, nhúng cùng rau cải mèo và ngọn su su ngọt lịm.',
 5, 250000, 'người',
 'Cá tầm bắt tại bể, nước lẩu đậm đà, không gian gỗ ấm cúng và sạch sẽ.',
 'Cuối tuần khá đông khách đoàn nên cần đặt bàn trước.',
 'Nhớ gọi thêm đĩa nấm hương rừng tươi nhúng lẩu, thơm gấp nhiều lần nấm khô.',
 'approved', NULL, 1, '2026-01-26 15:30:00'),

(10, 5, 19, 'Lịch trình 6 tiếng đi tàu Tuyến 2 Vịnh Hạ Long: Hang Sửng Sốt – Hang Luồn – Đảo Ti Tốp', 6, 2025,
 'Tuyến 2 là tuyến đáng đi nhất nếu bạn muốn trải nghiệm đủ cả hang động, chèo kayak và tắm biển ngắm toàn cảnh vịnh. Đứng từ chòi vọng cảnh trên đỉnh đảo Ti Tốp nhìn xuống làn nước xanh ngọc bích và hàng trăm cánh buồm du thuyền thực sự xứng danh kỳ quan thiên nhiên thế giới.',
 5, 590000, 'người (Tàu + Vé vịnh + Kayak)',
 'Cảng tàu quốc tế Hạ Long khang trang, tàu sạch đẹp có điều hòa và bữa trưa hải sản trên tàu.',
 'Leo hơn 400 bậc đá lên đỉnh đảo Ti Tốp vào mùa hè khá tốn sức.',
 'Chuẩn bị sẵn túi chống nước cho điện thoại khi chèo kayak ở Hang Luồn và mang theo khăn tắm nhỏ.',
 'approved', NULL, 1, '2026-01-28 09:45:00'),

(11, 6, 20, 'Thác Bản Giốc tháng 9: Khi dòng Quây Sơn xanh ngọc ôm trọn đồng lúa vàng Trùng Khánh', 9, 2025,
 'Mình đã đi nhiều thác nước ở miền Bắc nhưng Thác Bản Giốc vào tháng 9 thực sự ở một đẳng cấp khác. Nước thác vừa đầy, vừa trong xanh màu ngọc bích chứ không đục như tháng 7, còn ngay dưới chân thác là thảm lúa chín vàng rực của bà con người Tày xã Đàm Thủy.',
 5, 950000, 'người (Trọn gói ngày tại Bản Giốc)',
 'Thác hùng vĩ, đi bè mảng ra gần chân thác cảm nhận hơi nước mát lạnh cực kỳ sảng khoái.',
 'Khu vực bến bè mảng buổi trưa nắng khá gắt.',
 'Kết hợp đi Động Ngườm Ngao và làng đá cổ Khuổi Ky ngay gần đó trong cùng một buổi chiều là hợp lý nhất.',
 'approved', NULL, 1, '2026-01-30 14:20:00'),

(12, 2, 40, 'Sáng mùa thu Hà Nội: Ăn phở Bờ Hồ, nhâm nhi cà phê trứng Giảng rồi dạo Phan Đình Phùng', 10, 2025,
 'Không đâu có mùa thu dịu dàng như Hà Nội. 7h00 sáng dạo một vòng Hồ Gươm, ghé ăn bát phở bò nóng hổi rồi vào ngõ nhỏ Nguyễn Hữu Huân thưởng thức tách cà phê trứng kem bông mịn thơm lừng, sau đó qua phố Phan Đình Phùng mua gói cốm non làng Vòng gói lá sen.',
 5, 95000, 'người',
 'Hương vị truyền thống giữ nguyên qua nhiều thập kỷ, giá cả hợp lý ngay trung tâm phố cổ.',
 'Quán cà phê Giảng buổi sáng cuối tuần rất đông, gửi xe máy ở ngoài mặt phố rồi đi bộ vào ngõ.',
 'Nên uống cà phê trứng lúc còn nóng trong bát nước ấm để lớp kem trứng béo thơm không bị tanh.',
 'approved', NULL, 1, '2026-02-02 08:45:00'),

(13, 5, 21, 'Nghỉ dưỡng cuối tuần tại Bản Đôn Pù Luông: Sáng săn mây trên ruộng bậc thang, chiều thưởng thức vịt Cổ Lũng', 10, 2025,
 'Chỉ mất 4 tiếng đi limousine từ Hà Nội là tới Bản Đôn – trái tim của khu bảo tồn thiên nhiên Pù Luông. Mở cửa phòng ra là cả thung lũng ruộng bậc thang đang vào mùa lúa chín vàng óng và những dải mây vắt ngang sườn núi.',
 5, 180000, 'người (Bữa ăn đặc sản)',
 'Không khí yên bình chưa bị bê tông hóa, món vịt Cổ Lũng quay lá mắc mật da giòn thịt ngọt cực kỳ xuất sắc.',
 'Đường dốc xuống một số homestay ở Bản Đôn khá cao, xe gầm thấp cần chú ý.',
 'Đừng bỏ lỡ trải nghiệm đi bè tre trên suối Chàm và chụp ảnh bên những guồng nước tre khổng lồ của người Thái.',
 'approved', NULL, 1, '2026-02-04 17:10:00');

INSERT INTO `experience_images` (`post_id`, `image_url`, `caption`, `sort_order`) VALUES
(1, 'https://images.unsplash.com/photo-1490750967868-88aa4486c946?w=800&auto=format&fit=crop&q=80', 'Thung lũng mận Nà Ka nở trắng xóa đầu tháng 2', 1),
(1, 'https://images.unsplash.com/photo-1528127269322-539801943592?w=800&auto=format&fit=crop&q=80', 'Đường vào bản mùa hoa xuân Tây Bắc', 2),
(2, 'https://images.unsplash.com/photo-1544025162-d76694265947?w=800&auto=format&fit=crop&q=80', 'Set cá hồi tươi 6 món tại Xuân Bắc 181', 1),
(3, 'https://images.unsplash.com/photo-1587061949409-02df41d5e562?w=800&auto=format&fit=crop&q=80', 'Bungalow gỗ Mama''s House nhìn ra vườn mơ', 1),
(4, 'https://images.unsplash.com/photo-1559592413-7cec4d0cae2b?w=800&auto=format&fit=crop&q=80', 'Thuyền đi qua hẻm vực Tu Sản trên sông Nho Quế', 1),
(5, 'https://images.unsplash.com/photo-1570366583862-f91883984fde?w=800&auto=format&fit=crop&q=80', 'Bến thuyền Tràng An buổi sớm mai', 1),
(6, 'https://images.unsplash.com/photo-1464822759023-fed622ff2c3b?w=800&auto=format&fit=crop&q=80', 'Biển mây Sống lưng khủng long Tà Xùa', 1);
