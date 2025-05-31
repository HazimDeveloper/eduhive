<?php
// config/database.php - Database configuration and connection class

class Database {
    // Database configuration
    private $host = "localhost";
    private $db_name = "eduhive_db";
    private $username = "root";  // Change this to your database username
    private $password = "";      // Change this to your database password
    private $charset = "utf8mb4";
    public $conn;
    
    /**
     * Get database connection
     * @return PDO|null Database connection object
     */
    public function getConnection() {
        $this->conn = null;
        
        try {
            $dsn = "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=" . $this->charset;
            
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
            ];
            
            $this->conn = new PDO($dsn, $this->username, $this->password, $options);
            
        } catch(PDOException $exception) {
            error_log("Database connection error: " . $exception->getMessage());
            
            // In production, don't show detailed error messages
            if (getenv('APP_ENV') === 'production') {
                die("Database connection failed. Please try again later.");
            } else {
                die("Database connection error: " . $exception->getMessage());
            }
        }
        
        return $this->conn;
    }
    
    /**
     * Test database connection
     * @return bool True if connection successful, false otherwise
     */
    public function testConnection() {
        try {
            $conn = $this->getConnection();
            return $conn !== null;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Get database configuration info (without sensitive data)
     * @return array Database info
     */
    public function getInfo() {
        return [
            'host' => $this->host,
            'database' => $this->db_name,
            'charset' => $this->charset
        ];
    }
    
    /**
     * Close database connection
     */
    public function closeConnection() {
        $this->conn = null;
    }
    
    /**
     * Execute a query and return results
     * @param string $query SQL query
     * @param array $params Parameters for prepared statement
     * @return array|false Query results or false on error
     */
    public function query($query, $params = []) {
        try {
            $conn = $this->getConnection();
            $stmt = $conn->prepare($query);
            $stmt->execute($params);
            
            // Return results for SELECT queries
            if (stripos($query, 'SELECT') === 0) {
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            
            // Return affected rows for other queries
            return $stmt->rowCount();
            
        } catch (PDOException $e) {
            error_log("Query error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Execute a query and return single row
     * @param string $query SQL query
     * @param array $params Parameters for prepared statement
     * @return array|false Single row or false on error
     */
    public function queryRow($query, $params = []) {
        try {
            $conn = $this->getConnection();
            $stmt = $conn->prepare($query);
            $stmt->execute($params);
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Query row error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Insert data into table
     * @param string $table Table name
     * @param array $data Associative array of column => value
     * @return int|false Last insert ID or false on error
     */
    public function insert($table, $data) {
        try {
            $conn = $this->getConnection();
            
            $columns = implode(',', array_keys($data));
            $placeholders = ':' . implode(', :', array_keys($data));
            
            $query = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";
            $stmt = $conn->prepare($query);
            
            foreach ($data as $key => $value) {
                $stmt->bindValue(':' . $key, $value);
            }
            
            if ($stmt->execute()) {
                return $conn->lastInsertId();
            }
            
            return false;
            
        } catch (PDOException $e) {
            error_log("Insert error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update data in table
     * @param string $table Table name
     * @param array $data Associative array of column => value
     * @param string $where WHERE clause
     * @param array $whereParams Parameters for WHERE clause
     * @return int|false Number of affected rows or false on error
     */
    public function update($table, $data, $where, $whereParams = []) {
        try {
            $conn = $this->getConnection();
            
            $setClause = [];
            foreach ($data as $key => $value) {
                $setClause[] = "{$key} = :{$key}";
            }
            $setClause = implode(', ', $setClause);
            
            $query = "UPDATE {$table} SET {$setClause} WHERE {$where}";
            $stmt = $conn->prepare($query);
            
            // Bind data parameters
            foreach ($data as $key => $value) {
                $stmt->bindValue(':' . $key, $value);
            }
            
            // Bind where parameters
            foreach ($whereParams as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            
            if ($stmt->execute()) {
                return $stmt->rowCount();
            }
            
            return false;
            
        } catch (PDOException $e) {
            error_log("Update error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Delete data from table
     * @param string $table Table name
     * @param string $where WHERE clause
     * @param array $whereParams Parameters for WHERE clause
     * @return int|false Number of affected rows or false on error
     */
    public function delete($table, $where, $whereParams = []) {
        try {
            $conn = $this->getConnection();
            
            $query = "DELETE FROM {$table} WHERE {$where}";
            $stmt = $conn->prepare($query);
            
            foreach ($whereParams as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            
            if ($stmt->execute()) {
                return $stmt->rowCount();
            }
            
            return false;
            
        } catch (PDOException $e) {
            error_log("Delete error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Start database transaction
     */
    public function beginTransaction() {
        $conn = $this->getConnection();
        return $conn->beginTransaction();
    }
    
    /**
     * Commit database transaction
     */
    public function commit() {
        $conn = $this->getConnection();
        return $conn->commit();
    }
    
    /**
     * Rollback database transaction
     */
    public function rollback() {
        $conn = $this->getConnection();
        return $conn->rollback();
    }
    
    /**
     * Get last insert ID
     * @return string Last insert ID
     */
    public function lastInsertId() {
        $conn = $this->getConnection();
        return $conn->lastInsertId();
    }
    
    /**
     * Escape string for SQL queries (use prepared statements instead when possible)
     * @param string $string String to escape
     * @return string Escaped string
     */
    public function escape($string) {
        $conn = $this->getConnection();
        return $conn->quote($string);
    }
    
    /**
     * Check if table exists
     * @param string $table Table name
     * @return bool True if exists, false otherwise
     */
    public function tableExists($table) {
        try {
            $query = "SHOW TABLES LIKE :table";
            $result = $this->queryRow($query, [':table' => $table]);
            return !empty($result);
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Get table columns
     * @param string $table Table name
     * @return array Array of column information
     */
    public function getTableColumns($table) {
        try {
            $query = "DESCRIBE {$table}";
            return $this->query($query);
        } catch (Exception $e) {
            return [];
        }
    }
    
    /**
     * Get database version
     * @return string Database version
     */
    public function getVersion() {
        try {
            $result = $this->queryRow("SELECT VERSION() as version");
            return $result['version'] ?? 'Unknown';
        } catch (Exception $e) {
            return 'Unknown';
        }
    }
}

// Global function for quick database access (optional)
function getDbConnection() {
    static $database = null;
    if ($database === null) {
        $database = new Database();
    }
    return $database->getConnection();
}

// Database configuration constants (for use in other files)
define('DB_HOST', 'localhost');
define('DB_NAME', 'eduhive_db');
define('DB_USER', 'root');  // Change this
define('DB_PASS', '');      // Change this
define('DB_CHARSET', 'utf8mb4');

?>