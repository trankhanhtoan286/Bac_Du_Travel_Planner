-- Lược đồ CSDL Bắc Du - Nguyễn Thị Vân
CREATE TABLE provinces (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  code VARCHAR(50) NOT NULL UNIQUE
) ENGINE=InnoDB;

CREATE TABLE locations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  province_id INT NOT NULL,
  name VARCHAR(255) NOT NULL,
  category ENUM('sightseeing','dining','stay') NOT NULL,
  address VARCHAR(255),
  ticket_price DECIMAL(10,2) DEFAULT 0
) ENGINE=InnoDB;
