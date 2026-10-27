<?php
/**
 * ========================================================
 * Database Connection using PDO Singleton Pattern
 * ========================================================
 */

class Database {
    private static ?PDO $instance = null;

    // Cấu hình Database kết nối
    private const DB_HOST = '127.0.0.1';
    private const DB_PORT = '3306';
    private const DB_NAME = 'web_ban_code';
    private const DB_USER = 'root';
    private const DB_PASS = 'phong123';
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
            $dsn = sprintf(
                "mysql:host=%s;port=%s;dbname=%s;charset=%s",
                self::DB_HOST,
                self::DB_PORT,
                self::DB_NAME,
                self::DB_CHARSET
            );

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Quăng Exception khi có lỗi SQL
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Mặc định trả về mảng kết hợp
                PDO::ATTR_EMULATE_PREPARES   => false,                  // Vô hiệu hóa emulate để chống SQL Injection triệt để
                PDO::ATTR_PERSISTENT         => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . self::DB_CHARSET . " COLLATE utf8mb4_unicode_ci"
            ];

            try {
                self::$instance = new PDO($dsn, self::DB_USER, self::DB_PASS, $options);
            } catch (PDOException $e) {
                // Trong môi trường production, log lỗi bảo mật thay vì in thẳng mật khẩu / dsn
                error_log("Database connection error: " . $e->getMessage());
                die(json_encode([
                    'status'  => 'error',
                    'message' => 'Lỗi kết nối cơ sở dữ liệu! Vui lòng kiểm tra lại cấu hình config/database.php'
                ], JSON_UNESCAPED_UNICODE));
            }
        }

        return self::$instance;
    }
}
