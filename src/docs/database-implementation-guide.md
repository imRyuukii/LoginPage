# Database Implementation Guide

**Current System:** JSON-based user storage  
**Target System:** MariaDB/MySQL database  
**Migration Status:** Ready for implementation

---

## 🎯 Overview

This guide outlines the migration from the current JSON-based user storage system to a robust MariaDB database implementation. The current system is already well-structured and will transition smoothly to a database backend.

---

## 📊 Current System Analysis

### ✅ What's Already Working
- **Secure Authentication**: Password hashing with `password_hash()`
- **User Management**: Complete CRUD operations
- **Role-based Access**: Admin/user roles implemented
- **Real-time Features**: Heartbeat system for online status
- **Input Validation**: Comprehensive validation and sanitization
- **Session Management**: Proper session handling

### 🔄 What Needs Migration
- **Data Storage**: JSON → MariaDB
- **File Operations**: File I/O → Database queries
- **Connection Management**: Add a database connection layer

---

## 🗄️ Database Schema

### Users Table Structure
```sql
-- Create database
CREATE DATABASE login_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Use the database
USE login_system;

-- Create users table
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    role ENUM('admin', 'user') DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_active TIMESTAMP NULL,
    last_activity TIMESTAMP NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Indexes for performance
    INDEX idx_username (username),
    INDEX idx_email (email),
    INDEX idx_role (role),
    INDEX idx_created_at (created_at),
    INDEX idx_last_activity (last_activity)
);
```

### Initial Data Migration
```sql
-- Insert existing admin user (update with actual hash from your JSON)
INSERT INTO users (username, password_hash, name, email, role, created_at, last_active, last_activity) 
VALUES (
    'admin', 
    '$2y$10$aZmjRCHgfWL9ZiFCI.YCVuMQxDB.jdOVuG/hEAps2a650T2tiThvW', 
    'admin', 
    'admin@example.com', 
    'admin',
    '2025-09-05 22:14:06',
    '2025-09-06 23:57:59',
    '2025-09-07 00:05:01'
);

-- Insert test user if exists
INSERT INTO users (username, password_hash, name, email, role, created_at, last_active) 
VALUES (
    'test', 
    '$2y$10$A3x68f0NvnkJdZj1hcYG.OP8pz1Jes6Jku0ffNmQeUx/XbKEMoXKu', 
    'test', 
    'test@example.com', 
    'user',
    '2025-09-06 23:53:17',
    '2025-09-06 23:56:59'
);
```

---

## 🔧 Database Configuration

### Database Connection File
```php
<?php
// src/config/database.php
class Database {
    private $host = 'localhost';
    private $dbname = 'login_system';
    private $username = 'root';
    private $password = '';
    private $charset = 'utf8mb4';
    
    private $pdo;
    
    public function __construct() {
        $this->connect();
    }
    
    private function connect() {
        try {
            $dsn = "mysql:host={$this->host};dbname={$this->dbname};charset={$this->charset}";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            
            $this->pdo = new PDO($dsn, $this->username, $this->password, $options);
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            throw new Exception("Database connection failed");
        }
    }
    
    public function getConnection() {
        return $this->pdo;
    }
    
    public function query($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log("Query failed: " . $e->getMessage());
            throw new Exception("Database query failed");
        }
    }
}

// Global database instance
$db = new Database();
?>
```

---

## 🔄 Updated User Functions

### Database-Based User Functions
```php
<?php
// src/includes/user-functions-db.php
require_once __DIR__ . '/../config/database.php';

function getUsersData() {
    global $db;
    $stmt = $db->query("SELECT * FROM users ORDER BY created_at DESC");
    return $stmt->fetchAll();
}

function findUserByUsername($username) {
    global $db;
    $stmt = $db->query("SELECT * FROM users WHERE username = ?", [$username]);
    return $stmt->fetch() ?: null;
}

function findUserByEmail($email) {
    global $db;
    $stmt = $db->query("SELECT * FROM users WHERE email = ?", [$email]);
    return $stmt->fetch() ?: null;
}

function loginUser($username, $password) {
    $user = findUserByUsername($username);
    if ($user && password_verify($password, $user['password_hash'])) {
        return $user;
    }
    return false;
}

function registerUser($username, $password, $name, $email) {
    // Check if username already exists
    if (findUserByUsername($username)) {
        return ['success' => false, 'message' => 'Username already exists'];
    }
    
    // Check if email already exists
    if (findUserByEmail($email)) {
        return ['success' => false, 'message' => 'Email already exists'];
    }
    
    // Validate input
    if (empty($username) || empty($password) || empty($name) || empty($email)) {
        return ['success' => false, 'message' => 'All fields are required'];
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'Invalid email format'];
    }
    
    if (strlen($password) < 6) {
        return ['success' => false, 'message' => 'Password must be at least 6 characters'];
    }
    
    // Add username validation
    if (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) {
        return ['success' => false, 'message' => 'Username must be 3-20 characters, letters, numbers, and underscores only'];
    }
    
    try {
        global $db;
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $now = date('Y-m-d H:i:s');
        
        $stmt = $db->query(
            "INSERT INTO users (username, password_hash, name, email, role, created_at, last_active) VALUES (?, ?, ?, ?, 'user', ?, ?)",
            [$username, $passwordHash, $name, $email, $now, $now]
        );
        
        return ['success' => true, 'message' => 'User registered successfully'];
    } catch (Exception $e) {
        error_log("Registration failed: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to register user'];
    }
}

function getAllUsers() {
    return getUsersData();
}

function updateUser($userId, $name, $email, $role = null) {
    try {
        global $db;
        $sql = "UPDATE users SET name = ?, email = ?, updated_at = NOW()";
        $params = [$name, $email];
        
        if ($role !== null) {
            $sql .= ", role = ?";
            $params[] = $role;
        }
        
        $sql .= " WHERE id = ?";
        $params[] = $userId;
        
        $stmt = $db->query($sql, $params);
        return $stmt->rowCount() > 0;
    } catch (Exception $e) {
        error_log("User update failed: " . $e->getMessage());
        return false;
    }
}

function deleteUser($userId) {
    try {
        global $db;
        $stmt = $db->query("DELETE FROM users WHERE id = ?", [$userId]);
        return $stmt->rowCount() > 0;
    } catch (Exception $e) {
        error_log("User deletion failed: " . $e->getMessage());
        return false;
    }
}

function updateLastActive($userId) {
    try {
        global $db;
        $stmt = $db->query("UPDATE users SET last_active = NOW() WHERE id = ?", [$userId]);
        return $stmt->rowCount() > 0;
    } catch (Exception $e) {
        error_log("Last active update failed: " . $e->getMessage());
        return false;
    }
}

function updateUserActivity($userId) {
    try {
        global $db;
        $stmt = $db->query("UPDATE users SET last_activity = NOW() WHERE id = ?", [$userId]);
        return $stmt->rowCount() > 0;
    } catch (Exception $e) {
        error_log("User activity update failed: " . $e->getMessage());
        return false;
    }
}

// Keep the existing getLastActiveFormatted function - it works with database data too
function getLastActiveFormatted($lastActive, $lastActivity = null) {
    $timeToCheck = $lastActivity ?: $lastActive;
    
    if (!$timeToCheck) return 'Never';
    
    $lastActiveTime = new DateTime($timeToCheck);
    $now = new DateTime();
    $diff = $now->diff($lastActiveTime);
    
    if ($diff->days == 0 && $diff->h == 0 && $diff->i <= 2) {
        return 'Online';
    }
    
    if ($diff->days > 0) {
        return $diff->days . ' day' . ($diff->days > 1 ? 's' : '') . ' ago';
    } elseif ($diff->h > 0) {
        return $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
    } elseif ($diff->i > 0) {
        return $diff->i . ' minute' . ($diff->i > 1 ? 's' : '') . ' ago';
    } else {
        return 'Just now';
    }
}
?>
```

---

## 📁 Updated File Structure

```
src/
├── config/
│   └── database.php              # Database connection class
├── includes/
│   ├── user-functions.php        # Current JSON functions (backup)
│   └── user-functions-db.php     # New database functions
├── assets/
│   ├── site-review.md           # Code review documentation
│   └── database-implementation-guide.md  # This file
├── data/
│   └── users.json               # Current data (backup)
├── heartbeat.php                # Real-time activity tracking
├── login.php                    # Authentication
├── register.php                 # User registration
├── profile.php                  # User profile & admin panel
├── logout.php                   # Session cleanup
└── style.css                    # Styling
```

---

## 🚀 Migration Steps

### Phase 1: Database Setup
1. **Create Database**
   ```sql
   CREATE DATABASE login_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

2. **Create Tables**
   - Run the table creation SQL from the schema section

3. **Migrate Existing Data**
   - Export current users from `users.json`
   - Insert into a database using the migration SQL

### Phase 2: Code Migration
1. **Create Database Config**
   - Add `src/config/database.php`

2. **Create Database Functions**
   - Add `src/includes/user-functions-db.php`

3. **Update File Includes**
   - Change `require_once './includes/user-functions.php';` to `require_once './includes/user-functions-db.php';`

### Phase 3: Testing
1. **Test All Functions**
   - Login/logout
   - Registration
   - Admin panel
   - Heartbeat system
   - User management

2. **Performance Testing**
   - Database query performance
   - Connection handling
   - Error handling

### Phase 4: Cleanup
1. **Backup JSON Files**
   - Move `users.json` to back-up location

2. **Remove Old Code**
   - Keep `user-functions.php` as backup
   - Update documentation

---

## 🔒 Security Enhancements

### Database Security
- **Prepared Statements**: All queries use prepared statements
- **Input Validation**: Enhanced validation in database functions
- **Error Logging**: Comprehensive error logging
- **Connection Security**: Secure database connection handling

### Additional Security Features
```php
// Rate limiting for login attempts
function checkLoginAttempts($username, $ip) {
    global $db;
    $stmt = $db->query(
        "SELECT COUNT(*) as attempts FROM login_attempts 
         WHERE username = ? AND ip_address = ? AND attempt_time > DATE_SUB(NOW(), INTERVAL 15 MINUTE)",
        [$username, $ip]
    );
    return $stmt->fetch()['attempts'] < 5;
}

// Log login attempts
function logLoginAttempt($username, $ip, $success) {
    global $db;
    $db->query(
        "INSERT INTO login_attempts (username, ip_address, success, attempt_time) VALUES (?, ?, ?, NOW())",
        [$username, $ip, $success ? 1 : 0]
    );
}
```

---

## 📊 Performance Benefits

### Current JSON System
- ✅ Simple and fast for small datasets
- ❌ File I/O overhead
- ❌ No concurrent access handling
- ❌ Limited scalability

### Database System
- ✅ Optimized queries with indexes
- ✅ Concurrent access handling
- ✅ Scalable to thousands of users
- ✅ Built-in data integrity
- ✅ Advanced querying capabilities
- ✅ Backup and recovery options

---

## 🎯 XAMPP Integration

### MariaDB Setup
- **Port**: 3306 (default)
- **phpMyAdmin**: http://localhost/phpmyadmin
- **Root Access**: No password (default XAMPP)
- **Character Set**: utf8mb4 for full Unicode support

### Configuration
```php
// For XAMPP default setup
$host = 'localhost';
$dbname = 'login_system';
$username = 'root';
$password = '';  // Empty for XAMPP default
```

---

## 🔮 Future Enhancements

### Advanced Features
1. **User Activity Logs**
   ```sql
   CREATE TABLE user_activity_logs (
       id INT AUTO_INCREMENT PRIMARY KEY,
       user_id INT,
       action VARCHAR(100),
       ip_address VARCHAR(45),
       user_agent TEXT,
       created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
       FOREIGN KEY (user_id) REFERENCES users(id)
   );
   ```

2. **Password Reset System**
   ```sql
   CREATE TABLE password_resets (
       id INT AUTO_INCREMENT PRIMARY KEY,
       user_id INT,
       token VARCHAR(255),
       expires_at TIMESTAMP,
       used BOOLEAN DEFAULT FALSE,
       created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
       FOREIGN KEY (user_id) REFERENCES users(id)
   );
   ```

3. **User Sessions**
   ```sql
   CREATE TABLE user_sessions (
       id VARCHAR(128) PRIMARY KEY,
       user_id INT,
       ip_address VARCHAR(45),
       user_agent TEXT,
       last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
       created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
       FOREIGN KEY (user_id) REFERENCES users(id)
   );
   ```

---

## ✅ Migration Checklist

### Pre-Migration
- [ ] Backup current `users.json` file
- [ ] Test current system functionality
- [ ] Create a database backup plan

### Database Setup
- [ ] Create `login_system` database
- [ ] Create `users` table with proper schema
- [ ] Add indexes for performance
- [ ] Migrate existing user data

### Code Migration
- [ ] Create `database.php` configuration
- [ ] Create `user-functions-db.php`
- [ ] Update file includes in all PHP files
- [ ] Test all functionality

### Post-Migration
- [ ] Verify all features work correctly
- [ ] Test performance
- [ ] Update documentation
- [ ] Backup old files
- [ ] Monitor error logs

---

## 🎉 Conclusion

The migration from JSON to MariaDB will provide:

- **Better Performance**: Optimized queries and indexing
- **Enhanced Security**: Prepared statements and proper error handling
- **Scalability**: Support for thousands of users
- **Data Integrity**: ACID compliance and foreign key constraints
- **Advanced Features**: Complex queries, reporting, and analytics

The current system is already well-architected, making this migration straightforward and low risk. All existing functionality will be preserved while gaining the benefits of a proper database backend.

---

**Migration Guide Created:** January 27, 2025,  
**Current System Status:** Production-ready JSON system  
**Target System:** MariaDB with enhanced features
