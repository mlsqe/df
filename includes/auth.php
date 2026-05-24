<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
if (session_status() === PHP_SESSION_NONE) session_start();

function login($username, $password) {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    if (!$user) return ['success' => false, 'message' => '用户名或密码错误'];
    if ($user['status'] === 'banned') return ['success' => false, 'message' => '账号已被封禁，请联系管理员'];
    if (password_verify($password, $user['password_hash'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        return ['success' => true, 'user' => $user];
    }
    return ['success' => false, 'message' => '用户名或密码错误'];
}
function register($username, $password, $realName = '', $phone = '', $role = 'user') {
    // 防止空用户名或密码
    $username = trim($username);
    $password = trim($password);
    if ($username === '' || $password === '') {
        return ['success' => false, 'message' => '用户名和密码不能为空'];
    }
    if (mb_strlen($username) < 2) {
        return ['success' => false, 'message' => '用户名至少2个字符'];
    }
    if (mb_strlen($password) < 4) {
        return ['success' => false, 'message' => '密码至少4个字符'];
    }
    
    $db = getDB();
    $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$username]);
    if ($stmt->fetch()) return ['success' => false, 'message' => '用户名已存在'];
    if (!in_array($role, ['user', 'booster'])) $role = 'user';
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $db->prepare("INSERT INTO users (username, password_hash, real_name, phone, role) VALUES (?,?,?,?,?)");
    $stmt->execute([$username, $hash, $realName, $phone, $role]);
    return ['success' => true, 'user_id' => $db->lastInsertId(), 'role' => $role];
}

function logout() {
    session_destroy();
    return ['success' => true];
}