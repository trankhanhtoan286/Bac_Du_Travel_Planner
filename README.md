# BẮC DU (TRAVEL PLANNER)
**Nền tảng tham khảo lịch trình 12 tháng và lập kế hoạch du lịch miền Bắc**

---

## 1. Giới thiệu tổng quan
**Bắc Du (Travel Planner)** giúp người dùng trả lời trọn vẹn 4 câu hỏi khi lên kế hoạch du lịch miền Bắc:
1. **Tháng này nên đi đâu ở miền Bắc?** (Bánh xe khám phá 12 tháng theo mùa hoa, mùa lúa chín, săn mây, biển đảo).
2. **Đi mấy ngày, tham quan đâu, ăn ở đâu và nghỉ ở đâu?** (Lịch trình mẫu chi tiết từng ngày do Ban biên tập chuẩn bị + dữ liệu địa điểm 3 nhóm chuẩn hóa).
3. **Người đã trải nghiệm đánh giá như thế nào?** (Cộng đồng chia sẻ bài trải nghiệm thực tế có kiểm duyệt bởi Admin).
4. **Làm sao biến gợi ý đó thành kế hoạch phù hợp với mình?** (Sao chép mẫu thành chuyến đi cá nhân độc lập chỉ với 1 cú nhấp chuột, quản lý ngân sách chống tính trùng, lưu trú, phương tiện, checklist, bản đồ Leaflet và chia sẻ liên kết chỉ đọc).

---

## 2. Cấu trúc thư mục dự án
```text
Travel_Planner/
├── config/
│   └── db.php                  # Kết nối PDO MySQL (XAMPP/Laragon) + Tự động nạp schema/seed
├── database/
│   └── travel_planner.sql      # Toàn bộ 21 bảng CSDL MySQL & Dữ liệu mẫu 12 tháng miền Bắc
├── includes/
│   ├── helpers.php             # Phân quyền Guest/User/Admin, CSRF, Sao chép mẫu, Ngân sách chống trùng
│   ├── header.php              # Navbar ngang Premium Travel responsive & Flash messages
│   └── footer.php              # Footer & Tích hợp Bootstrap 5.3 + Leaflet JS
├── assets/
│   ├── css/style.css           # Hệ thống giao diện Premium Travel
│   └── js/app.js               # Bản đồ tổng hợp OpenStreetMap + Leaflet lọc theo ngày/nhóm
├── index.php                   # Trang chủ: Mix cảm hứng 12 tháng + Công cụ lập kế hoạch nhanh
├── auth.php                    # Đăng nhập (Email/Username), Đăng ký, Đăng xuất & Demo Quick Login
├── profile.php                 # Hồ sơ cá nhân & Đổi mật khẩu (M1)
├── dashboard.php               # User Dashboard tổng hợp chuyến đi, yêu thích, trạng thái duyệt bài (M8)
├── templates.php               # Khám phá lịch trình mẫu theo 12 tháng, lọc điểm đến/thời lượng/ngân sách (M2)
├── template_detail.php         # Chi tiết lịch trình mẫu, bản đồ theo ngày & Luồng "Dùng lịch trình này" (M2/M4)
├── locations.php               # Khám phá 3 nhóm địa điểm (Tham quan, Ăn uống, Lưu trú) & Bản đồ tổng hợp (M5)
├── location_detail.php         # Chi tiết địa điểm, thông tin đặc thù 3 nhóm, Google Maps, bài review (M5)
├── favorites.php               # Lưu yêu thích theo nhóm & Thêm địa điểm vào chuyến đi (M7)
├── trips.php                   # Quản lý danh sách chuyến đi cá nhân & Tạo chuyến đi mới (M4)
├── trip_detail.php             # Trip Dashboard: Timeline, Bản đồ, Ngân sách chống trùng, Lưu trú, Xe, Checklist (M4/M6/M7)
├── share.php                   # Xem chuyến đi qua liên kết chia sẻ chỉ đọc (ẩn mã đặt chỗ & ghi chú riêng tư) (M7)
├── experiences.php             # Đọc & lọc bài trải nghiệm cộng đồng đã duyệt (M3)
├── experience_form.php         # Viết bài trải nghiệm, lưu nháp, gửi duyệt & gửi duyệt lại (M3)
└── admin.php                   # Admin Dashboard: Duyệt bài, Quản lý mẫu 12 tháng & Insights, Địa điểm, User (M8)
```

---

## 3. Hướng dẫn chạy trên XAMPP / Laragon hoặc PHP CLI
### Cách 1: Chạy với XAMPP / Laragon
1. Đặt thư mục dự án vào `C:\xampp\htdocs\Travel_Planner` (hoặc tạo VirtualHost trỏ tới `d:\Travel_Planner`).
2. Bật **Apache** và **MySQL** trong XAMPP Control Panel.
3. Mở trình duyệt truy cập `http://localhost/Travel_Planner/`.
   - **Tự động khởi tạo**: Lần đầu truy cập, `config/db.php` sẽ tự động tạo database `travel_planner` và nạp toàn bộ bảng + dữ liệu mẫu từ `database/travel_planner.sql`.
   - Hoặc bạn có thể import thủ công file `database/travel_planner.sql` trong `phpMyAdmin`.

### Cách 2: Chạy nhanh bằng PHP Built-in Server
```powershell
C:\xampp\php\php.exe -S localhost:8080 -t d:\Travel_Planner
```
Sau đó mở `http://localhost:8080`.

---

## 4. Tài khoản Demo có sẵn
| Vai trò | Username / Email | Mật khẩu | Ghi chú |
| :--- | :--- | :--- | :--- |
| **Admin / Ban biên tập** | `admin` (`admin@bacdu.vn`) | `123456` | Toàn quyền duyệt bài, quản lý mẫu 12 tháng, địa điểm, người dùng |
| **Thành viên 1** | `kien` (`kien@bacdu.vn`) | `123456` | Ngô Xuân Kiên (Chuyến đi Mộc Châu mẫu, sổ tay yêu thích, bài viết) |
| **Thành viên 2** | `anh` (`anh@bacdu.vn`) | `123456` | Lê Thị Ngọc Ánh (Chuyến đi Hà Giang mẫu, nhật ký thực địa) |
| **Thành viên 3** | `tram` (`tram@bacdu.vn`) | `123456` | Lò Thị Trâm (Kế hoạch Sa Pa - Y Tý, review cộng đồng) |
| **Thành viên 4** | `van` (`van@bacdu.vn`) | `123456` | Nguyễn Thị Vân (Kế hoạch Vịnh Hạ Long, bài viết cần chỉnh sửa) |
| **Thành viên 5** | `toan` (`toan@bacdu.vn`) | `123456` | Trần Khánh Toàn (Kế hoạch Ninh Bình, địa điểm đã lưu) |

---

## 5. Kịch bản Demo 14 bước chuẩn theo Đặc tả (Mục 5)
1. Truy cập trang chủ `index.php`, chọn **Tháng 2**.
2. Xem lịch trình mẫu **"Mộc Châu 3 Ngày 2 Đêm: Mùa Hoa Mận Trắng & Săn Mây Cao Nguyên"** (`template_detail.php?id=1`).
3. Xem kế hoạch từng ngày: tham quan, ăn uống, lưu trú và bảng dự toán.
4. Nhấn vào địa điểm **Thung lũng mận Nà Ka** (`location_detail.php?id=1`) để xem thông tin đặc thù và đọc bài trải nghiệm cộng đồng.
5. Nhấn nút **Xem trên Google Maps** hoặc **Chỉ đường**.
6. Đăng nhập tài khoản `kien` / `123456` (hoặc chọn nhanh ở footer), quay lại lịch trình mẫu và nhấn **"Dùng lịch trình này"**.
7. Nhập ngày khởi hành, số người & ngân sách dự kiến.
8. Hệ thống tạo chuyến đi cá nhân độc lập từ mẫu và mở ngay **Trip Dashboard** (`trip_detail.php`).
9. Thêm/điều chỉnh hoạt động, quán ăn hoặc nơi lưu trú trong chuyến đi.
10. Chuyển sang tab **Ngân sách & Khoản chi**, cập nhật chi phí thực tế, xem biểu đồ thống kê và tab **Checklist**.
11. Xem các điểm dừng theo từng ngày trên bản đồ **Leaflet**.
12. Chuyển sang tab **Chia sẻ chỉ đọc**, bật liên kết và mở `share.php?token=...` (kiểm tra mã đặt chỗ đã được ẩn an toàn).
13. Vào `experience_form.php` đăng bài trải nghiệm mới bằng tài khoản người dùng.
14. Đăng nhập tài khoản **Admin** (`admin` / `123456`), vào `admin.php` duyệt bài trải nghiệm và cập nhật lịch trình mẫu.
