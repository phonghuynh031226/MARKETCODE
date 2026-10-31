# Bàn giao source/SQL bảo mật v2 — 31/10/2026

Tài liệu này thay thế hướng dẫn bản v1 trong cuộc trò chuyện. Chỉ mô tả code, dữ liệu và biến môi trường; không hướng dẫn cấu hình server. Không bảo đảm bảo mật tuyệt đối và chưa triển khai lên website đang chạy.

## Cập nhật database hiện có

1. Sao lưu nhất quán code/database và tạm dừng thao tác ghi trong lúc chuyển đổi.
2. Chuẩn bị các biến môi trường có tên trong config/environment.example.env. Tối thiểu cần DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS và PASSWORD_PEPPER. Nếu đang có thẻ cào lưu PIN cũ, migration còn yêu cầu CARD_DATA_KEY. Không gửi giá trị bí mật lên chat.
3. Giữ PASSWORD_PEPPER, CARD_DATA_KEY, RESET_TOKEN_KEY riêng biệt, đủ ngẫu nhiên và ít nhất 32 byte. Không đổi pepper/card data key tùy tiện: dữ liệu đã băm cần chiến lược chuyển khóa. Tạo khóa bằng random_bytes, không dùng mật khẩu tự nghĩ.
4. Chạy PHP CLI: php database/migrate_security.php bằng tài khoản migration có quyền DDL, rồi chạy lại để kiểm tra tính lặp lại. Script bảo toàn ID nội bộ, quan hệ đơn/số dư và UUID đã cấp. DDL auto-commit nên không thể hoàn tác toàn đợt chỉ bằng ROLLBACK.
5. Đưa source mới vào sử dụng cùng schema đã nâng cấp và thông tin môi trường tương ứng. Runtime không tự tạo/sửa bảng và không đọc database.local.php/security.local.php như bản v1.
6. Chạy kiểm tra đăng nhập, mua/tải, hoàn tiền, callback và reset trước khi nhận thao tác ghi bình thường. Không đưa lại code cũ đơn lẻ sau khi đã nâng cấp password/PIN; phương án khôi phục phải nhất quán với bản sao dữ liệu.

Không nhập database.sql hoặc security-schema.sql vào database đang có dữ liệu để nâng cấp. Không tự xóa dòng giao dịch trùng/âm để ép migration chạy: cần đối soát dữ liệu trước. Không tự hợp nhất tài khoản trùng OAuth UID vì có thể làm sai lịch sử tiền.

## Cài mới

Dùng database/security-schema.sql cho database trống, sau đó chạy migration để xác minh. File này không có tài khoản mặc định, mật khẩu mẫu, số dư hoặc dữ liệu khách hàng. database.sql chỉ là baseline CREATE TABLE để kiểm thử tương thích/nâng cấp, không phải dump dữ liệu thật.

Tạo admin đầu tiên bằng PHP CLI: php database/create_admin.php với BOOTSTRAP_ADMIN_USERNAME, BOOTSTRAP_ADMIN_EMAIL, BOOTSTRAP_ADMIN_PASSWORD và PASSWORD_PEPPER. Script từ chối nếu đã có admin, không tự nâng quyền tài khoản trùng email. Xóa các giá trị bootstrap khỏi môi trường sau khi tạo xong.

Dùng database/least_privilege.sql làm mẫu quyền cho tài khoản runtime riêng. Mẫu GRANT không tự thu hồi quyền đã cấp trước đó; migration account và runtime account không được dùng chung quyền DDL. Không áp dụng nguyên tên database/tài khoản minh họa nếu không khớp dự án.

## Thay đổi hành vi

- Tài khoản bcrypt cũ được kiểm tra theo hành vi legacy trong giới hạn đầu vào rồi nâng cấp toàn bộ mật khẩu người dùng nhập sang pepper + Argon2id sau đăng nhập thành công. Không thể chuyển offline toàn bộ hash cũ khi không biết mật khẩu. Pepper là bắt buộc kể cả luồng tương thích.
- Mật khẩu mới/đổi/reset có giới hạn 12–1024 byte. Đổi/reset mật khẩu thu hồi phiên dùng mật khẩu cũ.
- URL/form/API tài nguyên dùng UUIDv4; link ID số cũ không còn hợp lệ. Slug công khai của sản phẩm vẫn là tên điều hướng, không phải quyền truy cập.
- Nội dung chuyển khoản mới dùng NAPTIEN <UUID tài khoản> hoặc prefix đã lưu. Không dùng ID số cũ cho webhook mới. Giao dịch cũ vẫn được giữ nguyên để đối soát.
- Xóa sản phẩm/danh mục là xóa mềm; dữ liệu đơn/ledger/audit được giữ. Những khóa ngoại lịch sử chuyển sang RESTRICT.
- PIN thẻ chỉ gửi đến cổng và được so sánh bằng HMAC trong database; mã gốc được xóa khỏi bản ghi khi migration. Không có chế độ giả lập nhận thẻ khi thiếu thông tin cổng.
- Mọi thao tác trình duyệt thay đổi dữ liệu, kể cả logout, cần POST + CSRF. Callback đối tác dùng xác thực chữ ký; callback OAuth dùng state một lần.
- Google/Facebook không tự gộp tài khoản local theo email. Khi email đã có ở tài khoản local, dùng phương thức đăng nhập/khôi phục tài khoản đó; bản này chưa có màn hình liên kết tài khoản đã xác thực.
- Nội dung rich HTML trong mô tả/popup hiển thị như văn bản đã escape theo yêu cầu bảo mật. Không cho chạy HTML/script lưu trong database.
- Upload mới cần GD hoặc Imagick, dựng lại thành PNG tên 32 ký tự hex; không nhận ICO/SVG gốc, không giữ EXIF/byte phụ. Thiếu encoder thì từ chối upload.
- File tải local chỉ lấy từ storage/downloads bằng basename archive/PDF hợp lệ. File ngoài dùng URL http/https đã kiểm tra; quyền tải trong ứng dụng luôn gắn với chủ đơn.
- Cron thay đổi trạng thái thanh toán chỉ chạy PHP CLI: php api/cron_bank.php. Endpoint GET không còn chạy tác vụ.

## Bí mật theo chức năng

PASSWORD_PEPPER bắt buộc cho đăng nhập/đăng ký/mật khẩu OAuth mới. CARD_DATA_KEY cần cho dữ liệu thẻ. RESET_TOKEN_KEY + RESET_MAIL_FROM cần cho OTP qua mail; thiếu cấu hình thì khôi phục bị khóa. Mail thật chưa được kiểm thử ở môi trường này.

MOMO_PARTNER_CODE, MOMO_ACCESS_KEY, MOMO_SECRET_KEY phải cùng môi trường đối tác; lỗi chữ ký do bộ khóa sai vẫn cần bộ khóa đúng. IPN hợp lệ trả HTTP204 và đối chiếu số tiền/order/request trước khi cộng.

Webhook ngân hàng xác thực theo SePay bằng BANK_WEBHOOK_HMAC_SECRET hoặc BANK_WEBHOOK_API_KEY (ít nhất32 ký tự); HMAC được ưu tiên nếu cấu hình. Khóa phải khớp phía đối tác. Hợp đồng xác thực Casso/Web2M khác cần adapter riêng; không bỏ xác thực để chạy. Tham khảo: https://developer.sepay.vn/vi/sepay-webhooks/xac-thuc

CARD_PARTNER_ID/CARD_PARTNER_KEY, GOOGLE_CLIENT_ID/GOOGLE_CLIENT_SECRET, FACEBOOK_APP_ID/FACEBOOK_APP_SECRET, ZALO_APP_ID/ZALO_SECRET_KEY, CAPTCHA_SECRET_KEY chỉ lấy từ môi trường. Admin không còn hiển thị/lưu các khóa này trong settings.

Bộ khóa từng nằm trong dump/lịch sử cũ cần được đổi theo quy trình của nhà cung cấp; xóa khỏi source hiện tại không xóa được các bản sao cũ. Bản ZIP bàn giao không chứa file cấu hình riêng đã điền, dữ liệu khách hàng hoặc khóa thật.

## Kiểm tra source

Chạy tests/run.ps1 trên môi trường kiểm thử. Các bài HTTP/MariaDB dùng localhost và database tạm riêng; không dùng database ứng dụng. PHP 8.2+, native PDO MySQL, Argon2id, fileinfo, mbstring và GD/Imagick là các yêu cầu của code. Migration yêu cầu MySQL từ8.0.16 hoặc MariaDB từ10.2.1 để CHECK có hiệu lực; thực thi kiểm thử hiện tại dùng MariaDB10.4.

Chi tiết phát hiện và mức độ: docs/appsec-review.md. Các kết quả kiểm thử không thay thế kiểm chứng tích hợp thật hoặc cam kết không còn lỗ hổng.
