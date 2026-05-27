<?php
ini_set('display_errors', 0);
error_reporting(0);
ob_clean();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

// 全局异常处理，保证任何错误都返回 JSON
set_exception_handler(function ($e) {
    http_response_code(500);
    echo json_encode([
        'code' => 1,
        'msg' => '服务器内部错误：' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit;
});
set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

try {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (!isAdmin()) {
        echo json_encode(['code' => 1, 'msg' => '无权限', 'data' => [], 'count' => 0], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $db = getDB();
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = $input['action'] ?? $_GET['action'] ?? '';

    switch ($action) {

        // ========== 订单列表 ==========
        case 'list_orders':
            $page = max(1, intval($_GET['page'] ?? 1));
            $limit = max(1, intval($_GET['limit'] ?? 20));
            $status = $_GET['status'] ?? '';
            $keyword = $_GET['keyword'] ?? '';
            $where = []; $params = [];
            if (!empty($status)) { $where[] = 'o.status = ?'; $params[] = $status; }
            if (!empty($keyword)) {
                $where[] = '(o.order_no LIKE ? OR u.username LIKE ?)';
                $params[] = "%{$keyword}%"; $params[] = "%{$keyword}%";
            }
            $whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';
            $countStmt = $db->prepare("SELECT COUNT(*) FROM orders o JOIN users u ON o.user_id = u.id $whereSQL");
            $countStmt->execute($params);
            $total = (int)$countStmt->fetchColumn();
            $offset = ($page - 1) * $limit;
            $sql = "SELECT o.*, u.username AS user_name, b.id AS booster_id, ub.username AS booster_name
                    FROM orders o JOIN users u ON o.user_id = u.id 
                    LEFT JOIN boosters b ON o.booster_id = b.id 
                    LEFT JOIN users ub ON b.user_id = ub.id 
                    $whereSQL ORDER BY o.created_at DESC LIMIT {$offset}, {$limit}";
            $dataStmt = $db->prepare($sql);
            $dataStmt->execute($params);
            $data = $dataStmt->fetchAll();
            echo json_encode(['code' => 0, 'msg' => '', 'count' => $total, 'data' => $data], JSON_UNESCAPED_UNICODE);
            exit;

        // ========== 订单详情 ==========
        case 'order_detail':
            $id = intval($input['order_id'] ?? $_GET['order_id'] ?? 0);
            $order = $db->prepare("SELECT o.*, u.username AS user_name, b.id AS booster_id, ub.username AS booster_name
                                   FROM orders o JOIN users u ON o.user_id = u.id 
                                   LEFT JOIN boosters b ON o.booster_id = b.id 
                                   LEFT JOIN users ub ON b.user_id = ub.id WHERE o.id = ?");
            $order->execute([$id]);
            $order = $order->fetch();
            if (!$order) { echo json_encode(['code' => 1, 'msg' => '订单不存在']); exit; }
            $progress = $db->prepare("SELECT p.*, ub.username AS booster_name FROM progress p 
                                      JOIN boosters b ON p.booster_id = b.id 
                                      JOIN users ub ON b.user_id = ub.id 
                                      WHERE p.order_id = ? ORDER BY p.created_at ASC");
            $progress->execute([$id]);
            $order['progress'] = $progress->fetchAll();
            $txns = $db->prepare("SELECT * FROM transactions WHERE order_id = ? ORDER BY created_at ASC");
            $txns->execute([$id]);
            $order['transactions'] = $txns->fetchAll();
            echo json_encode(['code' => 0, 'data' => $order], JSON_UNESCAPED_UNICODE);
            exit;

        // ========== 更新订单 ==========
        case 'update_order':
            $id = intval($input['order_id'] ?? 0);
            $order = $db->prepare("SELECT * FROM orders WHERE id = ?");
            $order->execute([$id]);
            $order = $order->fetch();
            if (!$order) { echo json_encode(['code' => 1, 'msg' => '订单不存在']); exit; }
            $update = []; $params = [];
            if (isset($input['amount'])) {
                $amount = floatval($input['amount']);
                if ($amount <= 0) { echo json_encode(['code' => 1, 'msg' => '金额必须大于0']); exit; }
                $update[] = 'amount = ?'; $params[] = $amount;
            }
            if (isset($input['status'])) {
                $status = $input['status'];
                $allowed = ['pending','accepted','in_progress','completed','cancelled','disputed'];
                if (!in_array($status, $allowed)) { echo json_encode(['code' => 1, 'msg' => '无效状态']); exit; }
                $update[] = 'status = ?'; $params[] = $status;
                if ($status === 'completed' && $order['status'] !== 'completed' && $order['booster_id']) {
                    $commission = $order['amount'] * COMMISSION_RATE;
                    $boosterEarn = $order['amount'] - $commission;
                    $bu = $db->prepare("SELECT user_id FROM boosters WHERE id = ?");
                    $bu->execute([$order['booster_id']]);
                    $boosterUserId = $bu->fetchColumn();
                    if ($boosterUserId) {
                        addTransaction($boosterUserId, 'earning', $boosterEarn, $id, '管理员强制完成 #' . $order['order_no']);
                    }
                    $update[] = 'completed_at = NOW()';
                }
            }
            if (isset($input['booster_id'])) {
                $boosterId = intval($input['booster_id']);
                if ($boosterId > 0) {
                    $b = $db->prepare("SELECT id FROM boosters WHERE id = ? AND status = 'active'");
                    $b->execute([$boosterId]);
                    if (!$b->fetch()) { echo json_encode(['code' => 1, 'msg' => '打手不存在或未激活']); exit; }
                }
                $update[] = 'booster_id = ?'; $params[] = $boosterId > 0 ? $boosterId : null;
            }
            if (empty($update)) { echo json_encode(['code' => 1, 'msg' => '无修改']); exit; }
            $params[] = $id;
            $db->prepare("UPDATE orders SET " . implode(', ', $update) . " WHERE id = ?")->execute($params);
            publishTableReload('orders');
            publishTableReload('userOrder');
            echo json_encode(['code' => 0, 'msg' => '订单已更新']);
            exit;

        // ========== 退款 ==========
        case 'refund_order':
            $id = intval($input['order_id'] ?? 0);
            $order = $db->prepare("SELECT * FROM orders WHERE id = ?");
            $order->execute([$id]);
            $order = $order->fetch();
            if (!$order || in_array($order['status'], ['completed','cancelled'])) { echo json_encode(['code' => 1, 'msg' => '订单状态不可退款']); exit; }
            $db->beginTransaction();
            try {
                if ($order['payment_status'] === 'paid') {
                    addTransaction($order['user_id'], 'refund', $order['amount'], $id, '管理员退款 #' . $order['order_no']);
                }
                $db->prepare("UPDATE orders SET status = 'cancelled' WHERE id = ?")->execute([$id]);
                $db->commit();
                publishTableReload('orders');
                publishTableReload('userOrder');
                echo json_encode(['code' => 0, 'msg' => '退款成功，订单已取消']);
            } catch (Exception $e) {
                $db->rollBack();
                echo json_encode(['code' => 1, 'msg' => '退款失败']);
            }
            exit;

        case 'cancel_order':
            $db->prepare("UPDATE orders SET status = 'cancelled' WHERE id = ?")->execute([intval($input['order_id'])]);
            publishTableReload('orders');
            publishTableReload('userOrder');
            echo json_encode(['code' => 0, 'msg' => '订单已取消']);
            exit;

        case 'pay_order':
            $orderId = intval($input['order_id']);
            $order = $db->prepare("SELECT * FROM orders WHERE id = ? AND payment_status = 'unpaid'");
            $order->execute([$orderId]);
            $order = $order->fetch();
            if (!$order) { echo json_encode(['code' => 1, 'msg' => '订单不存在或已付款']); exit; }
            $db->prepare("UPDATE orders SET payment_status = 'paid' WHERE id = ?")->execute([$orderId]);
            publishTableReload('orders');
            publishTableReload('userOrder');
            echo json_encode(['code' => 0, 'msg' => '付款成功']);
            exit;

        // ========== 打手审核 ==========
        case 'approve_booster':
            $db->prepare("UPDATE boosters SET status = 'active' WHERE id = ?")->execute([intval($input['booster_id'])]);
            publishTableReload('boosters');
            publishTableReload('profile');
            publishTableReload('users');
            echo json_encode(['code' => 0, 'msg' => '已通过']);
            exit;
        case 'reject_booster':
            $reason = sanitize($input['reason'] ?? '');
            $db->prepare("UPDATE boosters SET status = 'banned', refuse_reason = ? WHERE id = ?")->execute([$reason, intval($input['booster_id'])]);
            publishTableReload('boosters');
            publishTableReload('profile');
            publishTableReload('users');
            echo json_encode(['code' => 0, 'msg' => '已拒绝']);
            exit;
        case 'list_boosters':
            $sql = "SELECT b.*, u.username,
                        CASE WHEN b.deposit_paid > 0 THEN '已缴纳' ELSE '未缴纳' END AS deposit_status,
                        (SELECT code FROM invite_codes WHERE used_by = u.id AND status = 'used' ORDER BY used_at DESC LIMIT 1) AS used_invite_code
                    FROM boosters b 
                    JOIN users u ON b.user_id = u.id 
                    ORDER BY b.created_at DESC";
            $data = $db->query($sql)->fetchAll();
            echo json_encode(['code' => 0, 'msg' => '', 'count' => count($data), 'data' => $data], JSON_UNESCAPED_UNICODE);
            exit;

        // ========== 申诉仲裁 ==========
        case 'list_disputes':
            $data = $db->query("SELECT d.*, o.order_no, u.username AS filed_name FROM disputes d JOIN orders o ON d.order_id = o.id JOIN users u ON d.filed_by = u.id WHERE d.status = 'open' ORDER BY d.created_at DESC")->fetchAll();
            echo json_encode(['code' => 0, 'msg' => '', 'count' => count($data), 'data' => $data], JSON_UNESCAPED_UNICODE);
            exit;
        case 'resolve_dispute':
            $did = intval($input['dispute_id']);
            $res = $input['resolution'] ?? '';
            $ds = $db->prepare("SELECT d.*, o.amount, o.booster_id, o.user_id FROM disputes d JOIN orders o ON d.order_id = o.id WHERE d.id = ?");
            $ds->execute([$did]);
            $dispute = $ds->fetch();
            if (!$dispute) { echo json_encode(['code' => 1, 'msg' => '申诉不存在']); exit; }
            $db->beginTransaction();
            try {
                if ($res === 'refund') {
                    addTransaction($dispute['user_id'], 'refund', $dispute['amount'], $dispute['order_id'], '申诉退款');
                    $db->prepare("UPDATE orders SET status = 'cancelled' WHERE id = ?")->execute([$dispute['order_id']]);
                } elseif ($res === 'pay_booster' && $dispute['booster_id']) {
                    $bu = $db->prepare("SELECT user_id FROM boosters WHERE id = ?");
                    $bu->execute([$dispute['booster_id']]);
                    $boosterUserId = $bu->fetchColumn();
                    if ($boosterUserId) {
                        $commission = $dispute['amount'] * COMMISSION_RATE;
                        addTransaction($boosterUserId, 'earning', $dispute['amount'] - $commission, $dispute['order_id'], '申诉结算');
                    }
                    $db->prepare("UPDATE orders SET status = 'completed' WHERE id = ?")->execute([$dispute['order_id']]);
                }
                $db->prepare("UPDATE disputes SET status = 'resolved', resolution = ?, resolved_at = NOW() WHERE id = ?")->execute([$res, $did]);
                $db->commit();
                publishTableReload('disputes');
                publishTableReload('orders');
                publishTableReload('userOrder');
                echo json_encode(['code' => 0, 'msg' => '处理完成']);
            } catch (Exception $e) {
                $db->rollBack();
                echo json_encode(['code' => 1, 'msg' => '处理失败']);
            }
            exit;

        // ========== 代练类型 ==========
        case 'list_game_types':
            $data = $db->query("SELECT * FROM game_types ORDER BY sort_order")->fetchAll();
            echo json_encode(['code' => 0, 'msg' => '', 'count' => count($data), 'data' => $data], JSON_UNESCAPED_UNICODE);
            exit;
        case 'save_game_type':
            $id = intval($input['id'] ?? 0);
            $name = sanitize($input['name']); $unit = sanitize($input['unit_label']);
            $price = floatval($input['price_per_unit']); $status = $input['status'] ?? 'active'; $sort = intval($input['sort_order'] ?? 0);
            if ($id) {
                $db->prepare("UPDATE game_types SET name=?, unit_label=?, price_per_unit=?, status=?, sort_order=? WHERE id=?")->execute([$name, $unit, $price, $status, $sort, $id]);
            } else {
                $db->prepare("INSERT INTO game_types (name, unit_label, price_per_unit, status, sort_order) VALUES (?,?,?,?,?)")->execute([$name, $unit, $price, $status, $sort]);
            }
            publishTableReload('gameTypes');
            echo json_encode(['code' => 0, 'msg' => '保存成功']);
            exit;

        // ========== 邀请码 ==========
        case 'create_invite':
            $count = intval($input['count'] ?? 1); $expireDays = intval($input['expire_days'] ?? 0);
            $codes = [];
            for ($i = 0; $i < $count; $i++) {
                $code = strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 8));
                $expireAt = $expireDays > 0 ? date('Y-m-d H:i:s', strtotime("+{$expireDays} days")) : null;
                $db->prepare("INSERT INTO invite_codes (code, type, created_by, expire_at) VALUES (?, 'admin', ?, ?)")->execute([$code, $_SESSION['user_id'], $expireAt]);
                $codes[] = $code;
            }
            publishTableReload('invites');
            echo json_encode(['code' => 0, 'msg' => '生成成功', 'data' => $codes]);
            exit;

        case 'list_invites':
            $page = intval($_GET['page'] ?? 1); $limit = intval($_GET['limit'] ?? 20);
            $status = $_GET['status'] ?? ''; $type = $_GET['type'] ?? '';
            $where = []; $params = [];
            if ($status) { $where[] = 'i.status = ?'; $params[] = $status; }
            if ($type) { $where[] = 'i.type = ?'; $params[] = $type; }
            $whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';
            $countStmt = $db->prepare("SELECT COUNT(*) FROM invite_codes i $whereSQL");
            $countStmt->execute($params);
            $total = (int)$countStmt->fetchColumn();
            $sql = "SELECT i.*, u1.username AS created_user, u2.username AS used_user 
                    FROM invite_codes i LEFT JOIN users u1 ON i.created_by = u1.id 
                    LEFT JOIN users u2 ON i.used_by = u2.id 
                    $whereSQL ORDER BY i.created_at DESC LIMIT " . (($page-1)*$limit) . ", $limit";
            $dataStmt = $db->prepare($sql);
            $dataStmt->execute($params);
            $data = $dataStmt->fetchAll();
            echo json_encode(['code' => 0, 'msg' => '', 'count' => $total, 'data' => $data], JSON_UNESCAPED_UNICODE);
            exit;

        // ========== 打手等级 ==========
        case 'list_booster_levels':
            $data = $db->query("SELECT * FROM booster_levels ORDER BY sort_order")->fetchAll();
            echo json_encode(['code' => 0, 'msg' => '', 'count' => count($data), 'data' => $data], JSON_UNESCAPED_UNICODE);
            exit;
        case 'save_booster_level':
            $id = intval($input['id'] ?? 0); $name = sanitize($input['name']); $minOrders = intval($input['min_orders']);
            $minRating = floatval($input['min_rating']); $deposit = floatval($input['deposit_required']); $desc = sanitize($input['description'] ?? '');
            if ($id) {
                $db->prepare("UPDATE booster_levels SET name=?, min_orders=?, min_rating=?, deposit_required=?, description=? WHERE id=?")->execute([$name, $minOrders, $minRating, $deposit, $desc, $id]);
            } else {
                $db->prepare("INSERT INTO booster_levels (name, min_orders, min_rating, deposit_required, description) VALUES (?,?,?,?,?)")->execute([$name, $minOrders, $minRating, $deposit, $desc]);
            }
            publishTableReload('levels');
            echo json_encode(['code' => 0, 'msg' => '保存成功']);
            exit;
        case 'delete_booster_level':
            $db->prepare("DELETE FROM booster_levels WHERE id = ?")->execute([intval($input['level_id'])]);
            publishTableReload('levels');
            echo json_encode(['code' => 0, 'msg' => '已删除']);
            exit;

        // ========== 系统配置 ==========
        case 'get_config':
            $configs = $db->query("SELECT cfg_key, cfg_value FROM system_config")->fetchAll(PDO::FETCH_KEY_PAIR);
            echo json_encode(['code' => 0, 'data' => $configs], JSON_UNESCAPED_UNICODE);
            exit;
        case 'save_config':
            if (!empty($input['config']) && is_array($input['config'])) {
                foreach ($input['config'] as $key => $value) {
                    $db->prepare("UPDATE system_config SET cfg_value=? WHERE cfg_key=?")->execute([$value, $key]);
                }
            }
            echo json_encode(['code' => 0, 'msg' => '配置已更新']);
            exit;

        // ========== 管理员管理 ==========
        case 'list_admins':
            if (!isSuperAdmin()) { echo json_encode(['code' => 1, 'msg' => '仅超级管理员可操作']); exit; }
            $data = $db->query("SELECT id, username, role, status FROM users WHERE role IN ('admin','super_admin')")->fetchAll();
            echo json_encode(['code' => 0, 'msg' => '', 'count' => count($data), 'data' => $data], JSON_UNESCAPED_UNICODE);
            exit;
        case 'add_admin':
            if (!isSuperAdmin()) { echo json_encode(['code' => 1, 'msg' => '仅超级管理员可操作']); exit; }
            $username = sanitize($input['username']); $password = $input['password'];
            if (empty($username) || empty($password)) { echo json_encode(['code' => 1, 'msg' => '用户名和密码不能为空']); exit; }
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $db->prepare("INSERT INTO users (username, password_hash, role) VALUES (?,?, 'admin')")->execute([$username, $hash]);
            publishTableReload('admins');
            echo json_encode(['code' => 0, 'msg' => '管理员已添加']);
            exit;
        case 'delete_admin':
            if (!isSuperAdmin()) { echo json_encode(['code' => 1, 'msg' => '仅超级管理员可操作']); exit; }
            $uid = intval($input['user_id']);
            $user = $db->prepare("SELECT role FROM users WHERE id = ?");
            $user->execute([$uid]);
            $user = $user->fetch();
            if ($user && $user['role'] === 'super_admin') { echo json_encode(['code' => 1, 'msg' => '不能删除超级管理员']); exit; }
            $db->prepare("DELETE FROM users WHERE id = ? AND role = 'admin'")->execute([$uid]);
            publishTableReload('admins');
            echo json_encode(['code' => 0, 'msg' => '已删除']);
            exit;

        // ========== 用户管理 ==========
        case 'list_users':
            $page = intval($_GET['page'] ?? 1);
            $limit = intval($_GET['limit'] ?? 20);
            $keyword = trim($_GET['keyword'] ?? '');
            $status = trim($_GET['status'] ?? '');
            $role = trim($_GET['role'] ?? '');
            $where = []; $params = [];
            if (!empty($keyword)) { $where[] = '(u.username LIKE ? OR u.phone LIKE ?)'; $params[] = "%{$keyword}%"; $params[] = "%{$keyword}%"; }
            if (!empty($status)) {
                if ($status === 'pending_booster') { $where[] = 'u.id IN (SELECT user_id FROM boosters WHERE status = ?)'; $params[] = 'pending'; }
                else { $where[] = 'u.status = ?'; $params[] = $status; }
            }
            if (!empty($role)) { $where[] = 'u.role = ?'; $params[] = $role; }
            $whereSQL = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
            $countStmt = $db->prepare("SELECT COUNT(*) FROM users u {$whereSQL}");
            $countStmt->execute($params);
            $total = (int)$countStmt->fetchColumn();
            $offset = ($page - 1) * $limit;
            $sql = "SELECT u.*,
                        (SELECT COUNT(*) FROM orders WHERE user_id = u.id) AS order_count,
                        (SELECT COUNT(*) FROM orders o JOIN boosters b ON o.booster_id = b.id WHERE b.user_id = u.id) AS boost_count,
                        (SELECT COUNT(*) FROM boosters WHERE user_id = u.id AND status = 'pending') AS pending_booster,
                        (SELECT wechat FROM boosters WHERE user_id = u.id LIMIT 1) AS wechat
                    FROM users u
                    {$whereSQL}
                    ORDER BY u.created_at DESC
                    LIMIT {$offset}, {$limit}";
            $dataStmt = $db->prepare($sql);
            $dataStmt->execute($params);
            $data = $dataStmt->fetchAll();
            echo json_encode(['code' => 0, 'msg' => '', 'count' => $total, 'data' => $data], JSON_UNESCAPED_UNICODE);
            exit;

        case 'user_detail':
            $uid = intval($input['user_id'] ?? $_GET['user_id'] ?? 0);
            if ($uid <= 0) { echo json_encode(['code' => 1, 'msg' => '用户 ID 无效']); exit; }
            $user = $db->prepare("SELECT * FROM users WHERE id = ?");
            $user->execute([$uid]);
            $user = $user->fetch();
            if (!$user) { echo json_encode(['code' => 1, 'msg' => '用户不存在']); exit; }
            $booster = $db->prepare("SELECT b.*, bl.name AS level_name FROM boosters b LEFT JOIN booster_levels bl ON b.level_id = bl.id WHERE b.user_id = ?");
            $booster->execute([$uid]);
            $user['booster'] = $booster->fetch();
            $stats = $db->prepare("SELECT COUNT(*) AS total_orders, SUM(amount) AS total_spent FROM orders WHERE user_id = ?");
            $stats->execute([$uid]);
            $user['stats'] = $stats->fetch();
            $user['order_count'] = $user['stats']['total_orders'] ?? 0;
            $boostStmt = $db->prepare("SELECT COUNT(*) FROM orders o JOIN boosters b ON o.booster_id = b.id WHERE b.user_id = ?");
            $boostStmt->execute([$uid]);
            $user['boost_count'] = (int)$boostStmt->fetchColumn();
            echo json_encode(['code' => 0, 'data' => $user], JSON_UNESCAPED_UNICODE);
            exit;

        case 'update_user':
            $uid = intval($input['user_id'] ?? 0);
            $realName = sanitize($input['real_name'] ?? '');
            $phone = sanitize($input['phone'] ?? '');
            $wechat = sanitize($input['wechat'] ?? '');
            if (empty($realName) && empty($phone) && empty($wechat)) { echo json_encode(['code' => 1, 'msg' => '姓名、手机号和微信号不能都为空']); exit; }
            $db->prepare("UPDATE users SET real_name = ?, phone = ? WHERE id = ?")->execute([$realName, $phone, $uid]);
            $db->prepare("UPDATE boosters SET wechat = ? WHERE user_id = ?")->execute([$wechat, $uid]);
            publishTableReload('users');
            echo json_encode(['code' => 0, 'msg' => '用户信息已更新']);
            exit;

        case 'toggle_user_status':
            $uid = intval($input['user_id']);
            $newStatus = $input['status'] === 'active' ? 'active' : 'banned';
            $user = $db->prepare("SELECT role FROM users WHERE id = ?");
            $user->execute([$uid]);
            $user = $user->fetch();
            if ($user && $user['role'] === 'super_admin') { echo json_encode(['code' => 1, 'msg' => '不能操作超级管理员']); exit; }
            $db->prepare("UPDATE users SET status = ? WHERE id = ?")->execute([$newStatus, $uid]);
            publishTableReload('users');
            echo json_encode(['code' => 0, 'msg' => '状态已更新']);
            exit;

        case 'delete_user':
    if (!isSuperAdmin()) {
        echo json_encode(['code' => 1, 'msg' => '仅超级管理员可操作']);
        exit;
    }
    $uid = intval($input['user_id']);
    $user = $db->prepare("SELECT role FROM users WHERE id = ?");
    $user->execute([$uid]);
    $user = $user->fetch();
    if ($user && $user['role'] === 'super_admin') {
        echo json_encode(['code' => 1, 'msg' => '不能删除超级管理员']);
        exit;
    }

    $db->beginTransaction();
    try {
        // 解除关联订单的用户ID
        $db->prepare("UPDATE orders SET user_id = NULL WHERE user_id = ?")->execute([$uid]);

        // 查询并解除打手关联
        $boosterStmt = $db->prepare("SELECT id FROM boosters WHERE user_id = ?");
        $boosterStmt->execute([$uid]);
        $boosterId = $boosterStmt->fetchColumn();
        if ($boosterId) {
            $db->prepare("UPDATE orders SET booster_id = NULL WHERE booster_id = ?")->execute([$boosterId]);
        }

        $db->prepare("DELETE FROM boosters WHERE user_id = ?")->execute([$uid]);
        $db->prepare("DELETE FROM invite_codes WHERE created_by = ? OR used_by = ?")->execute([$uid, $uid]);
        $db->prepare("DELETE FROM transactions WHERE user_id = ?")->execute([$uid]);
        $db->prepare("DELETE FROM disputes WHERE filed_by = ?")->execute([$uid]);
        $db->prepare("DELETE FROM users WHERE id = ?")->execute([$uid]);

        $db->commit();
        publishTableReload('users');
        echo json_encode(['code' => 0, 'msg' => '用户已删除']);
    } catch (Exception $e) {
        $db->rollBack();
        echo json_encode(['code' => 1, 'msg' => '删除失败: ' . $e->getMessage()]);
    }
    exit;

        case 'balance_adjust':
            $uid = intval($input['user_id'] ?? 0);
            $amount = floatval($input['amount'] ?? 0);
            $remark = sanitize($input['remark'] ?? '');
            if ($uid <= 0 || $amount == 0) { echo json_encode(['code' => 1, 'msg' => '参数错误']); exit; }
            $user = $db->prepare("SELECT role, balance FROM users WHERE id = ?");
            $user->execute([$uid]);
            $target = $user->fetch();
            if (!$target || $target['role'] === 'super_admin') { echo json_encode(['code' => 1, 'msg' => '无法操作该用户']); exit; }
            $newBalance = $target['balance'] + $amount;
            if ($newBalance < 0) { echo json_encode(['code' => 1, 'msg' => '余额不足，扣除后为负']); exit; }
            $db->beginTransaction();
            try {
                addTransaction($uid, ($amount > 0 ? 'recharge' : 'admin_deduct'), $amount, null, $remark ?: '管理员调整余额');
                $db->prepare("UPDATE users SET balance = ? WHERE id = ?")->execute([$newBalance, $uid]);
                $db->commit();
                publishTableReload('users');
                echo json_encode(['code' => 0, 'msg' => '余额调整成功']);
            } catch (Exception $e) {
                $db->rollBack();
                echo json_encode(['code' => 1, 'msg' => '操作失败']);
            }
            exit;

        // 删除操作（仅超管）
        case 'delete_order':
            if (!isSuperAdmin()) { echo json_encode(['code' => 1, 'msg' => '仅超级管理员可操作']); exit; }
            $db->prepare("DELETE FROM orders WHERE id = ?")->execute([intval($input['order_id'])]);
            publishTableReload('orders'); publishTableReload('userOrder');
            echo json_encode(['code' => 0, 'msg' => '已删除']);
            exit;
        case 'delete_booster':
            if (!isSuperAdmin()) { echo json_encode(['code' => 1, 'msg' => '仅超级管理员可操作']); exit; }
            $db->prepare("DELETE FROM boosters WHERE id = ?")->execute([intval($input['booster_id'])]);
            publishTableReload('boosters');
            echo json_encode(['code' => 0, 'msg' => '已删除']);
            exit;
        case 'delete_dispute':
            if (!isSuperAdmin()) { echo json_encode(['code' => 1, 'msg' => '仅超级管理员可操作']); exit; }
            $db->prepare("DELETE FROM disputes WHERE id = ?")->execute([intval($input['dispute_id'])]);
            publishTableReload('disputes');
            echo json_encode(['code' => 0, 'msg' => '已删除']);
            exit;
        case 'delete_game_type':
            if (!isSuperAdmin()) { echo json_encode(['code' => 1, 'msg' => '仅超级管理员可操作']); exit; }
            $db->prepare("DELETE FROM game_types WHERE id = ?")->execute([intval($input['id'])]);
            publishTableReload('gameTypes');
            echo json_encode(['code' => 0, 'msg' => '已删除']);
            exit;
        case 'delete_invite':
            if (!isSuperAdmin()) { echo json_encode(['code' => 1, 'msg' => '仅超级管理员可操作']); exit; }
            $db->prepare("DELETE FROM invite_codes WHERE id = ?")->execute([intval($input['id'])]);
            publishTableReload('invites');
            echo json_encode(['code' => 0, 'msg' => '已删除']);
            exit;
case 'add_user':
    if (!isAdmin()) {
        echo json_encode(['code' => 1, 'msg' => '无权限']);
        exit;
    }
    $username = sanitize($input['username'] ?? '');
    $password = $input['password'] ?? '';
    $role = $input['role'] ?? 'user';
    
    if (empty($username) || empty($password)) {
        echo json_encode(['code' => 1, 'msg' => '用户名和密码不能为空']);
        exit;
    }
    
    $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$username]);
    if ($stmt->fetch()) {
        echo json_encode(['code' => 1, 'msg' => '用户名已存在']);
        exit;
    }
    
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $db->prepare("INSERT INTO users (username, password_hash, role) VALUES (?, ?, ?)");
    $stmt->execute([$username, $hash, $role]);
    
    publishTableReload('users');
    echo json_encode(['code' => 0, 'msg' => '用户添加成功']);
    exit;
            // ========== 仪表盘统计数据 ==========
        case 'dashboard_stats':
            $totalUsers = $db->query("SELECT COUNT(*) FROM users WHERE role='user'")->fetchColumn();
            $totalBoosters = $db->query("SELECT COUNT(*) FROM users WHERE role IN ('booster','pending_booster')")->fetchColumn();
            $totalOrders = $db->query("SELECT COUNT(*) FROM orders")->fetchColumn();
            $totalRevenue = $db->query("SELECT COALESCE(SUM(amount),0) FROM orders WHERE status='completed'")->fetchColumn();
            $pendingBoosters = $db->query("SELECT COUNT(*) FROM boosters WHERE status='pending'")->fetchColumn();

            echo json_encode([
                'code' => 0,
                'data' => [
                    'total_users' => (int)$totalUsers,
                    'total_boosters' => (int)$totalBoosters,
                    'total_orders' => (int)$totalOrders,
                    'total_revenue' => (float)$totalRevenue,
                    'pending_boosters' => (int)$pendingBoosters
                ]
            ], JSON_UNESCAPED_UNICODE);
            exit;
        default:
            echo json_encode(['code' => 1, 'msg' => '未知操作: ' . $action]);
            exit;
    }
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'code' => 1,
        'msg' => '操作失败: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}