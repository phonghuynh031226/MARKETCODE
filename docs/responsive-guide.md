# Bản cập nhật giao diện responsive

## Những nguyên nhân đã xử lý

- Menu, ví và tên tài khoản cạnh tranh chiều rộng trên màn hình nhỏ; menu quản trị còn chiếm chỗ khi đã ẩn.
- Thanh lọc, cột Grid/Flex và chuỗi dữ liệu dài có kích thước tối thiểu làm đẩy khung trang.
- Bảng nhiều cột thiếu khung cuộn riêng; biểu mẫu và một số nút nhỏ gây khó thao tác trên điện thoại.
- Hộp thoại, thông báo và chiều cao theo `vh` chưa thích ứng tốt với màn hình hẹp.

## Mã nguồn

Mã đã được tích hợp trực tiếp trong các trang PHP, thư mục `includes` và `admin/includes`. Các thành phần dùng chung:

- `assets/css/responsive.css`: nền tảng co giãn, bảng cuộn, biểu mẫu, vùng chạm, ảnh, chuỗi dài và hộp thoại.
- `assets/css/navigation.css`: menu công khai và thanh điều hướng quản trị.
- `assets/js/navigation.js`: menu hamburger, Escape, trạng thái ARIA và quản lý focus của menu quản trị.
- `assets/js/image-fallbacks.js`: ảnh thay thế cục bộ khi ảnh gốc không tải được.
- `assets/images/default-avatar.svg`, `default-thumbnail.svg`: ảnh thay thế.

Sử dụng Grid/Flex với cột có thể thu nhỏ, `clamp()` và giới hạn chiều rộng bằng rem/phần trăm. Bảng dữ liệu cuộn trong khung riêng; trang không dùng `overflow-x: hidden` để che lỗi. Các vùng chạm tối thiểu 44px và ô nhập liệu tối thiểu 16px. Breakpoint bao gồm mobile dưới 576px, tablet 768–992px và desktop từ 1024px; menu công khai chuyển sang hàng ngang khi đủ không gian ở 1280px.

## Giữ nguyên xử lý PHP

Không thay đổi truy vấn SQL, biến nghiệp vụ, vòng lặp hay luồng thanh toán trong lần cập nhật giao diện này. Kiểm tra token PHP đối chiếu 66 tệp với bản trước khi sửa giao diện. Một ngoại lệ đã ghi rõ: thêm `require_once __DIR__ . '/config/config.php';` ở trang chủ trước header để khắc phục lỗi 404 do header có kiểm tra khởi tạo cấu hình.

Gói mã nguồn đầy đủ kế thừa các thay đổi bảo mật đã có trong dự án. Không chứa mật khẩu, cấu hình `.local.php`, dữ liệu tài khoản hoặc ảnh tải lên của người dùng. Khi cập nhật website đang chạy, giữ cấu hình riêng và thư mục upload hiện có; sao lưu trước khi chép đè mã nguồn. Không nhập lại `database.sql` vào cơ sở dữ liệu đang sử dụng chỉ để cập nhật giao diện.

## Kiểm chứng

- Chromium: 75 tổ hợp trang/kích thước, từ 320px đến 3840px, gồm trang công khai, tài khoản và quản trị; không phát hiện tràn ngang toàn trang.
- Dữ liệu thử gồm tiêu đề dài, chuỗi không khoảng trắng, số dư lớn và lịch sử nhiều dòng.
- Kiểm tra menu, vùng chạm chính, cỡ chữ ô nhập liệu, khung bảng và lỗi PHP.
- Đã xem ảnh chụp giao diện mobile, tablet, desktop và quản trị.
- Chạy lại bộ kiểm tra bảo mật/chức năng PHP có sẵn và kiểm tra cú pháp.

Chưa kiểm thử trực tiếp trên thiết bị iOS/Safari hoặc Android thật. Bộ kiểm tra trình duyệt dùng dữ liệu thử và máy chủ tạm, không thực hiện thanh toán thật.

Lệnh kiểm tra tại máy phát triển Windows có PHP/XAMPP, MariaDB, Node.js và Chrome tương ứng:

```powershell
C:/xampp/php/php.exe tests/php_logic_guard.php
powershell -ExecutionPolicy Bypass -File tests/run.ps1
node tests/responsive_browser_test.mjs
```
