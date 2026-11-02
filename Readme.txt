================================================================================
503073 - WEB PROGRAMMING & APPLICATIONS (FINAL PROJECT - SEMESTER I/2026-2027)
APPLICATION: CraftNotes - Note Management Web Application (PHP + MySQL + PWA)
================================================================================

I. TÀI KHOẢN DỮ LIỆU MẪU ĐỂ GIẢNG VIÊN CHẤM BÀI (PRE-LOADED GRADING ACCOUNTS)
--------------------------------------------------------------------------------
Tất cả tài khoản mẫu bên dưới đã được nạp sẵn trong `database/schema.sql`:

1. Tài khoản chính (Chủ sở hữu ghi chú, đã kích hoạt):
   - Email:       alice@tdtu.edu.vn
   - Mật khẩu:    Password123!
   - Dữ liệu sẵn: Có ghi chú đã ghim (Pinned), ghi chú gắn nhiều nhãn (Labels),
                  ghi chú khóa bằng mật khẩu riêng, và ghi chú đang chia sẻ cho Bob.

2. Tài khoản người nhận chia sẻ (Đã kích hoạt):
   - Email:       bob@tdtu.edu.vn
   - Mật khẩu:    Password123!
   - Dữ liệu sẵn: Có 2 ghi chú trong mục "Được chia sẻ với tôi" (1 ghi chú quyền
                  Chỉnh sửa - Edit và 1 ghi chú quyền Chỉ đọc - Read-only).

3. Tài khoản chưa xác thực (Để kiểm tra ngay Banner "Tài khoản chưa xác thực"):
   - Email:       unverified@tdtu.edu.vn
   - Mật khẩu:    Password123!
   - Link kích hoạt mẫu: activate.php?token=sample_activation_token_503073

4. Mật khẩu của Ghi chú đang bị khóa (Dành cho tài khoản alice@tdtu.edu.vn):
   - Tên ghi chú: "Ghi chú bảo mật có khóa mật khẩu"
   - Mật khẩu mở khóa ghi chú: SecretNote123

5. Hộp thư cục bộ (Local Mail Box - Kiểm tra Kích hoạt / Quên mật khẩu OTP / Chia sẻ):
   - Mọi email hệ thống gửi đi đều được lưu tự động thành file HTML tại:
     `logs/mails/latest.html` (Hoặc nhấn biểu tượng Hộp thư 📫 ngay trên thanh Header).


II. HƯỚNG DẪN CHẠY DỰ ÁN BẰNG DOCKER DESKTOP (KHUYÊN DÙNG - CHỈ 1 LỆNH)
--------------------------------------------------------------------------------
1. Mở ứng dụng **Docker Desktop** trên máy tính và đợi biểu tượng Docker báo "Engine running".
2. Mở Terminal / PowerShell tại thư mục chứa mã nguồn (thư mục có file `docker-compose.yml`,
   tức thư mục `Ket_Qua` hoặc `source`):
   ```bash
   docker compose up -d --build
   ```
3. Đợi khoảng 15-20 giây để MySQL 8.0 tự động khởi tạo CSDL và nạp sẵn `database/schema.sql`.
4. Mở trình duyệt truy cập:
   - Ứng dụng Web:  http://localhost:8080/
   - phpMyAdmin:    http://localhost:8081/  (User: `root` / Password: `rootpassword`)


III. HƯỚNG DẪN CHẠY TRỰC TIẾP TRÊN XAMPP (KHÔNG DÙNG DOCKER)
--------------------------------------------------------------------------------
Dự án tuân thủ tuyệt đối mục III. Coding Guideline (100% đường dẫn tương đối, không gắn cứng cổng):
1. Sao chép toàn bộ các tệp trong thư mục mã nguồn đặt trực tiếp vào thư mục gốc `htdocs` của XAMPP
   (ví dụ `C:\xampp\htdocs\`, không đặt trong thư mục con).
2. Bật **Apache** và **MySQL** trong XAMPP Control Panel.
3. Mở `http://localhost/phpmyadmin`, nhấn tab **Import** và chọn file `database/schema.sql`
   (File SQL sẽ tự động tạo database `note_app`, tạo 6 bảng và nạp sẵn dữ liệu mẫu).
4. Mặc định `config/db.php` kết nối tới `127.0.0.1`, database `note_app`, user `root`, password rỗng `""`.
5. Truy cập ngay tại địa chỉ gốc: `http://localhost/`


IV. KIỂM THỬ TỰ ĐỘNG 28 TIÊU CHÍ (AUTOMATED TEST SUITE)
--------------------------------------------------------------------------------
Để chạy bộ test tự động kiểm tra toàn bộ 8 Task (28 tiêu chí Rubric):
```bash
php tests/run_tests.php --all
```
