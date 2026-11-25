# BẮC DU (TRAVEL PLANNER)
**Nền tảng lịch trình 12 tháng & Kế hoạch du lịch miền Bắc (Cinematic Web Application)**

> Bài tập lớn môn: **Phát triển ứng dụng trên nền Web**  
> Repository: [https://github.com/trankhanhtoan286/Bac_Du_Travel_Planner](https://github.com/trankhanhtoan286/Bac_Du_Travel_Planner)  
> Live Demo: [https://bacdu.onrender.com/](https://bacdu.onrender.com/)

---

## 1. Giới thiệu tổng quan
**Bắc Du** là ứng dụng web quản lý lịch trình du lịch thông minh, giúp người dùng giải quyết trọn vẹn 4 bài toán khi lên kế hoạch khám phá miền Bắc:
1. **Khám phá thời điểm lý tưởng 12 tháng:** Bánh xe tương tác 12 tháng theo mùa vụ (mùa hoa mận, mùa lúa chín, săn mây cao nguyên, lễ hội đầu xuân, vịnh biển mùa hè).
2. **Lịch trình mẫu chi tiết (Itinerary Templates):** Tham khảo lộ trình theo từng chặng ngày được biên soạn chuẩn xác kèm sổ cái dự toán kinh phí không tính trùng.
3. **Mạng lưới 44 tọa độ thực địa 3 nhóm:** Danh bạ địa điểm chuẩn hóa phân theo 3 nhóm (Tham quan, Ăn uống, Lưu trú) tích hợp bản đồ Leaflet OpenStreetMap và điều hướng GPS.
4. **Cá nhân hóa & Điều hành chuyến đi (Trip Coordinator):** Sao chép mẫu thành chuyến đi cá nhân chỉ với 1 cú click, đối soát ngân sách thực tế vs dự kiến, checklist hành lý, và chia sẻ liên kết chỉ đọc an toàn bằng Token bảo mật.

---

## 2. Bảng phân công thành viên & 5 Phân hệ phát triển

| STT | Thành viên | Vai trò | Phân hệ đảm nhiệm chính | Các tệp mã nguồn phụ trách |
| :---: | :--- | :--- | :--- | :--- |
| **1** | **Trần Khánh Toàn** | **Tech Lead** | **Kiến trúc hệ thống & Module Chuyến đi cá nhân**<br>- Thiết lập kiến trúc Modular PHP thuần & PDO Prepared Statements.<br>- Module Chuyến đi cá nhân, bàn điều phối lộ trình chi tiết, đối soát ngân sách và checklist.<br>- Đóng gói Dockerfile, cấu hình dynamic port và triển khai cloud Render.com. | `trips.php`<br>`trip_detail.php`<br>`dashboard.php`<br>`config/db.php`<br>`includes/helpers.php`<br>`Dockerfile` |
| **2** | **Nguyễn Thị Vân** | **Database & Location Dev** | **Cơ sở dữ liệu & Module Địa điểm thực địa 3 nhóm**<br>- Thiết kế lược đồ CSDL quan hệ 21 bảng chuẩn hóa 3NF.<br>- Chuẩn hóa danh bạ 44 tọa độ thực địa miền Bắc phân theo 3 nhóm.<br>- Module hồ sơ chi tiết tọa độ tích hợp bản đồ Leaflet GPS và chỉ đường Google Maps. | `locations.php`<br>`location_detail.php`<br>`database/travel_planner.sql` |
| **3** | **Lò Thị Trâm** | **Frontend & UI Dev** | **Giao diện Cinematic UI & Lịch trình 12 tháng**<br>- Xây dựng ngôn ngữ thiết kế điện ảnh (Cinematic UI) cảm hứng phim Trần Anh Hùng.<br>- Hệ màu Split-Tone, Typography lining-nums thẳng hàng.<br>- Bánh xe thời tiết La bàn 12 tháng trang chủ, danh mục lịch trình mẫu và sổ cái dự toán. | `index.php`<br>`templates.php`<br>`template_detail.php`<br>`assets/css/cinematic-landing.css`<br>`assets/css/cinematic-subpages.css`<br>`assets/kit/` |
| **4** | **Lê Thị Ngọc Ánh** | **Security & Admin Dev** | **Bảo mật, Xác thực & Bàn quản trị Admin**<br>- Module đăng nhập, đăng ký, mã hóa mật khẩu Bcrypt và xác thực token CSRF.<br>- Quản lý hồ sơ cá nhân và phân quyền 3 cấp độ (Guest/User/Admin).<br>- Bàn điều phối Quản trị Admin kiểm duyệt bài viết cộng đồng (Duyệt/Từ chối có lý do/Ẩn bài). | `auth.php`<br>`admin.php`<br>`profile.php`<br>`includes/header.php`<br>`includes/footer.php` |
| **5** | **Ngô Xuân Kiên** | **Community Dev** | **Tương tác Cộng đồng, Sổ tay Yêu thích & Chia sẻ**<br>- Form viết bài trải nghiệm thực địa, chấm điểm sao và cập nhật chi phí.<br>- Bảng tin Nhật ký cộng đồng kèm bộ lọc đa tiêu chí.<br>- Sổ tay lưu tọa độ yêu thích và chèn nhanh vào lịch trình chuyến đi.<br>- Cơ chế sinh mã token bảo mật chia sẻ hành trình chỉ đọc. | `experiences.php`<br>`experience_form.php`<br>`favorites.php`<br>`share.php` |

---

## 3. Cấu trúc thư mục mã nguồn

```text
Travel_Planner/
├── config/
│   └── db.php                  # Kết nối CSDL PDO (MySQL/SQLite), auto-init & fallback
├── database/
│   └── travel_planner.sql      # Lược đồ 21 bảng quan hệ chuẩn 3NF & 44 địa điểm mẫu
├── includes/
│   ├── helpers.php             # Hàm tiện ích hệ thống: CSRF, sao chép lịch trình, đối soát ngân sách
│   ├── header.php              # Thanh điều hướng Cinematic Navbar & phân quyền đăng nhập
│   └── footer.php              # Chân trang & tích hợp thư viện hỗ trợ
├── assets/
│   ├── css/
│   │   ├── style.css           # Cấu hình biến màu theme & typography chung
│   │   ├── cinematic-landing.css # Giao diện trang chủ điện ảnh, bánh xe 12 tháng
│   │   └── cinematic-subpages.css# Giao diện các trang con, thẻ lộ trình, sổ cái
│   ├── js/
│   │   ├── app.js              # Xử lý bản đồ Leaflet OpenStreetMap & tương tác chung
│   │   └── cinematic-app.js    # Hiệu ứng điều phối góc nhìn điện ảnh
│   └── kit/                    # Bộ công cụ xử lý đồ họa, font chữ & camera visual
├── index.php                   # Trang chủ: Bánh xe thời tiết 12 tháng & Cuộn phim lộ trình
├── auth.php                    # Đăng nhập, đăng ký tài khoản, mã hóa Bcrypt & CSRF Token
├── profile.php                 # Quản lý hồ sơ cá nhân & đổi mật khẩu
├── dashboard.php               # Bảng tổng quan cá nhân: chuyến đi, bài viết, địa điểm đã lưu
├── templates.php               # Khám phá danh mục lịch trình mẫu theo mùa vụ 12 tháng
├── template_detail.php         # Chi tiết lịch trình mẫu, phân chặng ngày & nút "Dùng lịch trình này"
├── locations.php               # Danh bạ 44 tọa độ thực địa theo 3 nhóm & bộ lọc 8 tỉnh thành
├── location_detail.php         # Hồ sơ tọa độ, bản đồ Leaflet GPS, giờ mở cửa & chỉ đường Google Maps
├── favorites.php               # Sổ tay tọa độ yêu thích & chèn trực tiếp vào chuyến đi
├── trips.php                   # Quản lý danh sách chuyến đi cá nhân & tạo chuyến đi mới
├── trip_detail.php             # Bàn điều phối hành trình: Timeline chặng ngày, checklist, đối soát ngân sách
├── share.php                   # Chia sẻ chuyến đi công khai qua mã token chỉ đọc an toàn
├── experiences.php             # Bảng tin Nhật ký cộng đồng hiển thị bài viết đã duyệt
├── experience_form.php         # Form đăng bài trải nghiệm thực địa, chấm điểm sao & chi phí
├── admin.php                   # Bàn điều phối Quản trị Admin: Hàng đợi duyệt bài, quản trị users & thống kê
├── Dockerfile                  # Đóng gói container Docker (PHP 8.2 Apache + dynamic port)
└── README.md                   # Tổng quan dự án & cấu trúc mã nguồn
```

---

## 4. Hướng dẫn cài đặt & Chạy ứng dụng

### 1. Chạy trực tiếp qua XAMPP
1. Copy toàn bộ thư mục `Travel_Planner` vào thư mục `htdocs` của XAMPP (`C:\xampp\htdocs\Travel_Planner`).
2. Khởi động **Apache** và **MySQL** trong XAMPP Control Panel.
3. Mở trình duyệt web và truy cập: `http://localhost/Travel_Planner/`.
   - *Hệ thống tự động kích hoạt kết nối PDO. Nếu CSDL chưa có, `config/db.php` sẽ tự khởi tạo database `travel_planner` và import dữ liệu mẫu tự động.*
   - *Hoặc bạn có thể import thủ công tệp `database/travel_planner.sql` qua giao diện phpMyAdmin.*

### 2. Chạy qua Docker Container
```bash
docker build -t bacdu-travel-planner .
docker run -p 8080:80 bacdu-travel-planner
```
Sau đó truy cập: `http://localhost:8080`.

---

## 5. Danh sách tài khoản Demo thử nghiệm

| Cấp quyền | Tên đăng nhập / Email | Mật khẩu | Chức năng kiểm thử |
| :--- | :--- | :---: | :--- |
| **Ban biên tập (Admin)** | `admin` (`admin@bacdu.vn`) | `123456` | Toàn quyền kiểm duyệt bài viết cộng đồng (Duyệt/Từ chối), quản lý tài khoản, xem thống kê hệ thống. |
| **Thành viên 1** | `kien` (`kien@bacdu.vn`) | `123456` | Tài khoản có sẵn bài viết trải nghiệm, sổ tay địa điểm yêu thích Mộc Châu. |
| **Thành viên 2** | `van` (`van@bacdu.vn`) | `123456` | Tài khoản có sẵn bài viết thực địa Hạ Long, danh sách địa điểm đã lưu. |
| **Thành viên 3** | `tram` (`tram@bacdu.vn`) | `123456` | Tài khoản phụ trách trải nghiệm Sa Pa - Y Tý. |
| **Thành viên 4** | `anh` (`anh@bacdu.vn`) | `123456` | Tài khoản trải nghiệm Hà Giang, kiểm tra luồng đổi mật khẩu. |
| **Thành viên 5** | `toan` (`toan@bacdu.vn`) | `123456` | Tài khoản có sẵn chuyến đi Ninh Bình chi tiết, đối soát ngân sách thực tế. |
