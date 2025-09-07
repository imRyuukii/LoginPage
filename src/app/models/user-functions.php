<?php
// User management functions for JSON storage

function getUsersData() {
    $file = __DIR__ . '/../../data/users.json';
    if (!file_exists($file)) {
        return [];
    }
    $json = file_get_contents($file);
    return json_decode($json, true) ?: [];
}

function saveUsersData($users): bool
{
    $file = __DIR__ . '/../../data/users.json';
    $json = json_encode($users, JSON_PRETTY_PRINT);
    return file_put_contents($file, $json) !== false;
}

function findUserByUsername($username) {
    $users = getUsersData();
    foreach ($users as $user) {
        if ($user['username'] === $username) {
            return $user;
        }
    }
    return null;
}

function findUserByEmail($email) {
    $users = getUsersData();
    foreach ($users as $user) {
        if ($user['email'] === $email) {
            return $user;
        }
    }
    return null;
}

function loginUser($username, $password) {
    $user = findUserByUsername($username);
    if ($user && password_verify($password, $user['password_hash'])) {
        return $user;
    }
    return false;
}

function registerUser($username, $password, $name, $email): array
{
    // Check if a username already exists
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
    
    // Get existing users
    $users = getUsersData();
    
    // Generate new user ID
    $newId = 1;
    if (!empty($users)) {
        $newId = max(array_column($users, 'id')) + 1;
    }
    
    // Create a new user
    $newUser = [
        'id' => $newId,
        'username' => $username,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'name' => $name,
        'email' => $email,
        'role' => 'user',
        'created_at' => date('Y-m-d H:i:s'),
        'last_active' => date('Y-m-d H:i:s')
    ];
    
    // Add user to array
    $users[] = $newUser;
    
    // Save to file
    if (saveUsersData($users)) {
        return ['success' => true, 'message' => 'User registered successfully'];
    } else {
        return ['success' => false, 'message' => 'Failed to save user data'];
    }
}

function getAllUsers() {
    return getUsersData();
}

function updateUser($userId, $name, $email, $role = null): bool
{
    $users = getUsersData();
    foreach ($users as &$user) {
        if ($user['id'] == $userId) {
            $user['name'] = $name;
            $user['email'] = $email;
            if ($role !== null) {
                $user['role'] = $role;
            }
            $user['updated_at'] = date('Y-m-d H:i:s');
            break;
        }
    }
    return saveUsersData($users);
}

function deleteUser($userId): bool
{
    $users = getUsersData();
    $users = array_filter($users, function($user) use ($userId) {
        return $user['id'] != $userId;
    });
    return saveUsersData(array_values($users));
}

// Function to update the last active timestamp
function updateLastActive($userId): bool
{
    $users = getUsersData();
    foreach ($users as &$user) {
        if ($user['id'] == $userId) {
            $user['last_active'] = date('Y-m-d H:i:s');
            break;
        }
    }
    return saveUsersData($users);
}

// Function to update user activity (for a heartbeat system)
function updateUserActivity($userId): bool
{
    $users = getUsersData();
    foreach ($users as &$user) {
        if ($user['id'] == $userId) {
            $user['last_activity'] = date('Y-m-d H:i:s');
            break;
        }
    }
    return saveUsersData($users);
}

// Function to get formatted last active time
/**
 * @throws Exception
 */
function getLastActiveFormatted($lastActive, $lastActivity = null): string
{
    // Use last_activity if available (from heartbeat), otherwise fall back to last_active (login time)
    $timeToCheck = $lastActivity ?: $lastActive;
    
    if (!$timeToCheck) return 'Never';
    
    $lastActiveTime = new DateTime($timeToCheck);
    $now = new DateTime();
    $diff = $now->diff($lastActiveTime);
    
    // If a user was active within the last 2 minutes, show "Online"
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

