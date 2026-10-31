<?php
declare(strict_types=1);
/**
 * ========================================================
 * Database Connection using PDO Singleton Pattern
 * ========================================================
 */

class Database {
    private static ?PDO $instance = null;

    // Cấu hình Database kết nối
    private const DB_CHARSET = 'utf8mb4';

    private function __construct() {}
    private function __clone() {}

    /**
     * Lấy thực thể kết nối PDO duy nhất (Singleton)
     *
     * @return PDO
     * @throws PDOException
     */
    public static function getInstance(): PDO {
        if (self::$instance === null) {
            $setting = static function (string $key, string $fallback = ''): string {
                if (isset($_ENV[$key])) {
                    if (!is_string($_ENV[$key])) { throw new RuntimeException('Invalid database environment'); }
                    return $_ENV[$key];
                }
                $value = getenv($key);
                return $value !== false ? $value : $fallback;
            };
            if ($setting('DB_NAME') === '' || $setting('DB_USER') === '') {
                throw new RuntimeException('Database environment not configured');
            }
            $dsn = sprintf(
                "mysql:host=%s;port=%s;dbname=%s;charset=%s",
                $setting('DB_HOST', '127.0.0.1'),
                $setting('DB_PORT', '3306'),
                $setting('DB_NAME'),
                self::DB_CHARSET
            );

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Quăng Exception khi có lỗi SQL
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Mặc định trả về mảng kết hợp
                PDO::ATTR_EMULATE_PREPARES   => false,                  // Vô hiệu hóa emulate để chống SQL Injection triệt để
                PDO::ATTR_PERSISTENT         => false,
                PDO::ATTR_TIMEOUT            => 5,
                PDO::MYSQL_ATTR_MULTI_STATEMENTS => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . self::DB_CHARSET . " COLLATE utf8mb4_unicode_ci"
            ];

            try {
                self::$instance = new PDO($dsn, $setting('DB_USER'), $setting('DB_PASS'), $options);
                self::$instance->exec("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
            } catch (PDOException $e) {
                // Trong môi trường production, log lỗi bảo mật thay vì in thẳng mật khẩu / dsn
                error_log("Database connection error: " . $e->getMessage());
                throw $e;
            }
        }

        return self::$instance;
    }
}
