<?php
/**
 * Database Configuration Class
 * DarkDate - Подключение к MySQL базе данных
 * 
 * ВАЖНО: Замените плейсхолдеры на ваши реальные данные БД
 */

class Database {
    private $host = "YOUR_DB_HOST";
    private $db_name = "YOUR_DB_NAME";
    private $username = "YOUR_DB_USER";
    private $password = "YOUR_DB_PASS";
    private $conn;

    /**
     * Получить соединение с базой данных
     * @return PDO|null
     */
    public function getConnection() {
        $this->conn = null;

        try {
            $dsn = "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4";
            $this->conn = new PDO($dsn, $this->username, $this->password);
            
            // Настройка PDO для работы с ошибками
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $this->conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        } catch(PDOException $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Database connection failed: ' . $e->getMessage()
            ]);
            exit;
        }

        return $this->conn;
    }

    /**
     * Геттеры для конфигурации
     */
    public function getHost() { return $this->host; }
    public function getDbName() { return $this->db_name; }
    public function getUsername() { return $this->username; }
}
?>
