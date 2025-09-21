<?php
// Script to manually verify existing user accounts
require_once '../src/config/database.php';

// Function to verify a user by username
function verifyUserByUsername($username) {
    global $db;
    try {
        $stmt = $db->query('UPDATE users SET email_verified = TRUE WHERE username = ?', [$username]);
        return $stmt->rowCount() > 0;
    } catch (Exception $e) {
        error_log('Failed to verify user: ' . $e->getMessage());
        return false;
    }
}

// Function to get user info
function getUserInfo($username) {
    global $db;
    try {
        $stmt = $db->query('SELECT id, username, name, email, email_verified FROM users WHERE username = ?', [$username]);
        return $stmt->fetch();
    } catch (Exception $e) {
        error_log('Failed to get user info: ' . $e->getMessage());
        return null;
    }
}

// Users to verify
$usersToVerify = ['admin', 'test', 'test2'];
$results = [];

echo "<!DOCTYPE html><html><head><title>Verify Existing Users</title>";
echo "<style>body{font-family:Arial,sans-serif;max-width:800px;margin:0 auto;padding:20px;}";
echo ".success{color:green;} .error{color:red;} .info{color:blue;} table{border-collapse:collapse;width:100%;}";
echo "th,td{border:1px solid #ddd;padding:8px;text-align:left;} th{background:#f2f2f2;}</style></head><body>";

echo "<h1>🔧 Verify Existing User Accounts</h1>";

// Show current status first
echo "<h2>Current User Status:</h2>";
echo "<table><tr><th>Username</th><th>Name</th><th>Email</th><th>Verified</th></tr>";

foreach ($usersToVerify as $username) {
    $userInfo = getUserInfo($username);
    if ($userInfo) {
        $verified = $userInfo['email_verified'] ? '✅ Yes' : '❌ No';
        echo "<tr><td>{$userInfo['username']}</td><td>{$userInfo['name']}</td><td>{$userInfo['email']}</td><td>$verified</td></tr>";
        $results[$username] = $userInfo;
    } else {
        echo "<tr><td>$username</td><td colspan='3' class='error'>User not found</td></tr>";
    }
}
echo "</table>";

// Process verification if requested
if (isset($_GET['verify']) && $_GET['verify'] === 'yes') {
    echo "<h2>Verification Results:</h2>";
    foreach ($usersToVerify as $username) {
        if (isset($results[$username])) {
            if ($results[$username]['email_verified']) {
                echo "<p class='info'>✅ <strong>$username</strong> - Already verified</p>";
            } else {
                $success = verifyUserByUsername($username);
                if ($success) {
                    echo "<p class='success'>✅ <strong>$username</strong> - Successfully verified!</p>";
                } else {
                    echo "<p class='error'>❌ <strong>$username</strong> - Failed to verify</p>";
                }
            }
        } else {
            echo "<p class='error'>❌ <strong>$username</strong> - User not found in database</p>";
        }
    }
    
    echo "<h3>Updated Status:</h3>";
    echo "<table><tr><th>Username</th><th>Name</th><th>Email</th><th>Verified</th></tr>";
    
    foreach ($usersToVerify as $username) {
        $userInfo = getUserInfo($username);
        if ($userInfo) {
            $verified = $userInfo['email_verified'] ? '✅ Yes' : '❌ No';
            echo "<tr><td>{$userInfo['username']}</td><td>{$userInfo['name']}</td><td>{$userInfo['email']}</td><td>$verified</td></tr>";
        }
    }
    echo "</table>";
    
    echo "<div style='margin-top:20px; padding:15px; background:#d4edda; border:1px solid #c3e6cb; border-radius:5px;'>";
    echo "<h3>🎉 Done!</h3>";
    echo "<p>Your existing accounts (admin, test, test2) should now be verified and ready to log in!</p>";
    echo "<p><a href='../src/app/controllers/login.php'>→ Go to Login Page</a></p>";
    echo "</div>";
    
} else {
    // Show verification button
    echo "<div style='margin-top:20px; padding:15px; background:#fff3cd; border:1px solid #ffeaa7; border-radius:5px;'>";
    echo "<h3>⚠️ Ready to Verify?</h3>";
    echo "<p>This will mark the accounts <strong>admin</strong>, <strong>test</strong>, and <strong>test2</strong> as email verified.</p>";
    echo "<p><a href='?verify=yes' style='background:#28a745;color:white;padding:10px 20px;text-decoration:none;border-radius:5px;'>✅ Yes, Verify These Accounts</a></p>";
    echo "</div>";
}

// Show all users for reference
echo "<h2>All Users in Database:</h2>";
try {
    global $db;
    $stmt = $db->query('SELECT username, name, email, email_verified, created_at FROM users ORDER BY created_at DESC');
    $allUsers = $stmt->fetchAll();
    
    if ($allUsers) {
        echo "<table><tr><th>Username</th><th>Name</th><th>Email</th><th>Verified</th><th>Created</th></tr>";
        foreach ($allUsers as $user) {
            $verified = $user['email_verified'] ? '✅ Yes' : '❌ No';
            $created = date('Y-m-d H:i', strtotime($user['created_at']));
            echo "<tr><td>{$user['username']}</td><td>{$user['name']}</td><td>{$user['email']}</td><td>$verified</td><td>$created</td></tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No users found in database.</p>";
    }
} catch (Exception $e) {
    echo "<p class='error'>Error fetching users: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "</body></html>";
?>