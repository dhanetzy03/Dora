<?php
/**
 * Database Helper Functions
 * Shukran Café Inventory Tracking System
 */

require_once __DIR__ . '/db_connect.php';

/**
 * Execute a prepared statement and return results
 */
function db_query($sql, $params = [], $types = '') {
    global $conn;
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return ['error' => $conn->error];
    }
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result) {
        return $result;
    }
    
    return ['affected_rows' => $stmt->affected_rows, 'insert_id' => $stmt->insert_id];
}

/**
 * Fetch single row from database
 */
function db_fetch_one($sql, $params = [], $types = '') {
    $result = db_query($sql, $params, $types);
    
    if (is_array($result) && isset($result['error'])) {
        return null;
    }
    
    return $result->fetch_assoc();
}

/**
 * Fetch all rows from database
 */
function db_fetch_all($sql, $params = [], $types = '') {
    $result = db_query($sql, $params, $types);
    
    if (is_array($result) && isset($result['error'])) {
        return [];
    }
    
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    
    return $rows;
}

/**
 * Insert data into database
 */
function db_insert($table, $data) {
    global $conn;
    
    $fields = array_keys($data);
    $values = array_values($data);
    
    $placeholders = implode(',', array_fill(0, count($fields), '?'));
    $field_list = implode(',', array_map(function($field) {
        return "`$field`";
    }, $fields));
    
    $sql = "INSERT INTO `$table` ($field_list) VALUES ($placeholders)";
    
    $types = '';
    foreach ($values as $value) {
        if (is_int($value)) {
            $types .= 'i';
        } elseif (is_float($value)) {
            $types .= 'd';
        } else {
            $types .= 's';
        }
    }
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return ['success' => false, 'error' => $conn->error];
    }
    
    $stmt->bind_param($types, ...$values);
    $success = $stmt->execute();
    
    return [
        'success' => $success,
        'insert_id' => $stmt->insert_id,
        'error' => $success ? null : $stmt->error
    ];
}

/**
 * Update data in database
 */
function db_update($table, $data, $where, $where_params = []) {
    global $conn;
    
    $set_parts = [];
    $values = [];
    
    foreach ($data as $field => $value) {
        $set_parts[] = "`$field` = ?";
        $values[] = $value;
    }
    
    $set_clause = implode(', ', $set_parts);
    $sql = "UPDATE `$table` SET $set_clause WHERE $where";
    
    // Merge data values with where parameters
    $all_values = array_merge($values, $where_params);
    
    $types = '';
    foreach ($all_values as $value) {
        if (is_int($value)) {
            $types .= 'i';
        } elseif (is_float($value)) {
            $types .= 'd';
        } else {
            $types .= 's';
        }
    }
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return ['success' => false, 'error' => $conn->error];
    }
    
    $stmt->bind_param($types, ...$all_values);
    $success = $stmt->execute();
    
    return [
        'success' => $success,
        'affected_rows' => $stmt->affected_rows,
        'error' => $success ? null : $stmt->error
    ];
}

/**
 * Delete data from database
 */
function db_delete($table, $where, $where_params = [], $types = '') {
    global $conn;
    
    $sql = "DELETE FROM `$table` WHERE $where";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return ['success' => false, 'error' => $conn->error];
    }
    
    if (!empty($where_params)) {
        $stmt->bind_param($types, ...$where_params);
    }
    
    $success = $stmt->execute();
    
    return [
        'success' => $success,
        'affected_rows' => $stmt->affected_rows,
        'error' => $success ? null : $stmt->error
    ];
}

/**
 * Log activity to database
 */
function log_activity($user_id, $action, $module, $description = '') {
    global $conn;
    
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    
    $sql = "INSERT INTO activity_logs (user_id, action, module, description, ip_address) 
            VALUES (?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('issss', $user_id, $action, $module, $description, $ip_address);
    $stmt->execute();
}

/**
 * Validate user credentials
 */
function validate_user($username, $password) {
    $sql = "SELECT user_id, username, password, full_name, role, status 
            FROM users 
            WHERE username = ? AND status = 'active'";
    
    $user = db_fetch_one($sql, [$username], 's');
    
    if ($user && password_verify($password, $user['password'])) {
        unset($user['password']);
        return $user;
    }
    
    return null;
}

/**
 * Check if user has permission
 */
function check_permission($user_role, $required_role = 'staff') {
    $roles = ['staff' => 1, 'admin' => 2];
    
    return $roles[$user_role] >= $roles[$required_role];
}

/**
 * Sanitize input data
 */
function sanitize_input($data) {
    global $conn;
    
    if (is_array($data)) {
        return array_map('sanitize_input', $data);
    }
    
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

/**
 * Generate unique transaction number
 */
function generate_transaction_number($prefix = 'TXN') {
    return $prefix . date('Ymd') . strtoupper(substr(uniqid(), -6));
}

/**
 * Check database connection
 */
function check_db_connection() {
    global $conn;
    return $conn->ping();
}

/**
 * Check if a column exists in a table
 */
function column_exists($table, $column) {
    global $conn;
    $table = $conn->real_escape_string($table);
    $column = $conn->real_escape_string($column);
    $res = $conn->query("SHOW COLUMNS FROM `{$table}` LIKE '{$column}'");
    return ($res && $res->num_rows > 0);
}
?>
