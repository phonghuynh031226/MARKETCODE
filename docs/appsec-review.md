# Rà soát Source Code và SQL — 31/10/2026

Phạm vi: toàn bộ PHP của marketplace hiện có, các truy vấn và schema liên quan; kiểm thử trên PHP 8.2 và MariaDB tạm riêng. Không phải chứng nhận an toàn tuyệt đối hoặc kiểm thử xâm nhập website đang triển khai. Không đánh giá cấu hình server theo yêu cầu.

Phân loại tham khảo [OWASP Top 10:2025](https://top10.owasp.org/2025/0x00_2025-Introduction/). Mức độ bên dưới đánh giá kịch bản của chính ứng dụng, không phải điểm CVSS được đo độc lập. Các lỗi ở bản đầu đã được sửa trong các đợt trước và được giữ nguyên ở bản v2.

## Phát hiện và cách xử lý

| Mức độ trước sửa | OWASP | Phát hiện/kịch bản | Xử lý trong bản bàn giao |
|---|---|---|---|
| Nghiêm trọng | A01/A08 | Webhook ngân hàng thiếu xác thực có thể bị giả để tăng số dư | HMAC có timestamp hoặc API key bắt buộc; kiểm tra tài khoản nhận, chiều tiền vào, mã tham chiếu; lặp/đồng thời không cộng lại |
| Nghiêm trọng | A01/A08 | Callback thẻ bỏ qua chữ ký khi khách không gửi; kiểm tra trạng thái trước transaction tạo nguy cơ cộng nhiều lần | Chữ ký bắt buộc, khớp request/serial/PIN digest, khóa thẻ và user, trạng thái chuyển nguyên tử |
| Nghiêm trọng | A07 | Trang khôi phục hiển thị OTP cho người yêu cầu | OTP chỉ gửi qua mail; không xuất ra HTML/session; HMAC đầy đủ 64 hex, hash_equals, giới hạn lần thử, khóa dòng và dùng một lần |
| Cao | A05 | Fullname/tên dữ liệu được chèn vào onclick bằng addslashes và nối vào innerHTML của admin | JSON theo ngữ cảnh JS, escape thuộc tính HTML, textContent cho dữ liệu; nội dung HTML lưu trữ hiển thị thành văn bản |
| Cao | A06/A08 | Số dư/log trước cập nhật có thể không khớp khi có yêu cầu đồng thời | Transaction và SELECT FOR UPDATE trên số dư/tài nguyên; tính bằng số nguyên đơn vị nhỏ nhất, không cộng/trừ float |
| Cao | A01 | Phân quyền, xóa, duyệt thẻ, hoàn tiền qua GET tạo đường CSRF | POST + CSRF cho thao tác trình duyệt; endpoint GET cũ từ chối; webhook dùng chữ ký và OAuth dùng state một lần |
| Cao | A04 | Khóa từng nằm trong source/dump; PIN thẻ lưu rõ | Bí mật runtime chỉ lấy từ môi trường; SQL bàn giao không có dữ liệu người dùng; migration xóa giá trị secret cũ và chuyển PIN thành HMAC có khóa riêng |
| Cao | A07/A06 | Tự liên kết tài khoản Google với tài khoản local chỉ vì trùng email có thể tạo pre-account hijacking | Tra cứu OAuth theo provider + UID; không tự gộp email; unique index cho định danh OAuth |
| Trung bình | A04/A07 | Bcrypt không có pepper, phiên cũ chưa có cơ chế thu hồi theo mật khẩu | Mật khẩu mới HMAC-SHA256 pepper rồi Argon2id; bcrypt cũ nâng cấp có khóa dòng; đổi/reset mật khẩu làm mất hiệu lực phiên cũ |
| Trung bình | A05/A06 | Upload chỉ kiểm tra đuôi/MIME không loại bỏ nội dung phụ và metadata | finfo + kiểm tra ảnh/kích thước; GD/Imagick dựng lại PNG; tên random_bytes(16); thiếu encoder thì từ chối lưu |
| Trung bình | A01/A06 | Tải file local chưa ràng buộc thư mục riêng | Chỉ basename archive/PDF được phép trong storage/downloads, kiểm tra realpath và quyền sở hữu đơn |
| Trung bình | A09/A10 | Lộ exception ra giao diện và khó kiểm toán thay đổi | Thông báo chung; log nội bộ; audit định danh UUID/IP/User-Agent; ledger/audit không có quyền UPDATE/DELETE trong mẫu GRANT |
| Trung bình | A03 | Script giao diện lấy từ URL CDN có thể thay đổi nội dung mà không qua cập nhật source | Lưu bản JS/CSS dùng thực tế trong assets/vendor, kèm phiên bản, giấy phép và SHA384; layout dùng bản local |
| Thấp/chuẩn hóa | A01 | ID tự tăng được lộ trong form/URL/API; bản thân điều này chưa chứng minh IDOR | UUIDv4 công khai + khóa nội bộ giữ nguyên; quyền sở hữu tải đơn vẫn được kiểm tra độc lập |
| Thấp/chuẩn hóa | A05 | LIKE cho phép %/_ thay đổi nghĩa tìm kiếm; chưa thấy đường SQL Injection được chứng minh ở các truy vấn đã rà | Escape literal với ESCAPE '!'; prepared statements thật; sort/identifier từ whitelist/match; LIMIT/OFFSET bind số nguyên |

Không gán nhãn SQL Injection chỉ vì LIKE có wildcard hoặc vì có ID tuần tự. Kiểm thử quyền sở hữu xác nhận người khác không lấy được link tải bằng UUID đơn của chủ sở hữu.

## Code và SQL chính

- `includes/security.php`: CSRF, xác thực callback, OAuth state, rate limit, digest OTP/PIN, LIKE escaping.
- `includes/passwords.php`: pepper + Argon2id, kiểm tra và nâng cấp bcrypt cũ.
- `includes/identities.php`: UUIDv4, whitelist bảng, ánh xạ định danh, dữ liệu trả trình duyệt và audit.
- `includes/money.php`: tính tiền bằng integer minor units/decimal string; giữ nguyên DECIMAL(15,2).
- `includes/output.php`: htmlspecialchars với ENT_QUOTES | ENT_SUBSTITUTE, JSON an toàn cho JS, URL allowlist.
- `includes/uploads.php`: decode/re-encode ảnh, tên ngẫu nhiên, không lưu byte gốc.
- `database/migrate_security.php`: nâng cấp có thể chạy lại, bảo toàn khóa ngoại nội bộ; dừng khi phát hiện số tiền âm, định danh trùng, PIN thiếu hoặc dữ liệu mồ côi.
- `database/security-schema.sql`: CREATE TABLE của schema đã nâng cấp, không chứa dữ liệu tài khoản.
- `database/least_privilege.sql`: mẫu quyền tối thiểu; tài khoản chạy web không có DDL, DELETE lịch sử hoặc quyền sửa audit.

Mọi PHP runtime có declare(strict_types=1). PDO dùng native prepared statements, utf8mb4, strict SQL mode và tắt multi statements. Không dùng unserialize hoặc mysqli. DDL đã bỏ khỏi request web.

## Kiểm thử và giới hạn

Bộ `tests/run.ps1` chạy các kiểm tra password/pepper, tiền chính xác, HTML/JS/URL, callback, upload, HTTP, schema và Apache cũ. Bộ tích hợp v2 tạo MariaDB riêng và hai PHP server để tránh session lock che khuất race condition; có kiểm tra role MySQL thực sự bị từ chối UPDATE audit, DELETE ledger và CREATE TABLE. Các khóa, tài khoản và mã thẻ trong kiểm thử đều là fixture.

Lượt kiểm thử cuối: 56 kiểm tra tích hợp v2, 19 HTTP, 24 helper security, 22 money, các bộ password/output/upload và 7 kiểm tra Apache đều qua; kiểm tra cú pháp 72 file PHP không có lỗi. Upload được thử cả khi không có encoder (từ chối) và khi nạp GD (dựng lại ảnh thành công).

Đã có lượt rà soát nguồn độc lập; các phát hiện tương thích đã được xử lý và kiểm thử lại. Kiểm thử thực thi bao gồm MariaDB 10.4; chưa chạy trên một MySQL 8 riêng. Migration từ chối phiên bản không bảo đảm thực thi CHECK constraints.

Chưa xác minh thanh toán/mail/OAuth thật với nhà cung cấp, kiểm thử trên hosting thật, hiệu năng tải lớn hoặc kiểm thử xâm nhập độc lập toàn hệ thống. Google Fonts và CSS Font Awesome vẫn là stylesheet bên ngoài; CAPTCHA nếu bật vẫn dùng dịch vụ Google. Các thư viện vendored chưa được kiểm toán mã nguồn độc lập và vẫn cần theo dõi cập nhật. Không diễn giải việc có test hoặc việc không tìm thêm P1/P2 là bằng chứng không còn lỗ hổng.
