<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/redis.php';
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');
if (!isLoggedIn()) jsonResponse(['success' => false, 'message' => '请先登录'], 401);

$db = getDB();
$userId = $_SESSION['user_id'];
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $input['action'] ?? $_GET['action'] ?? '';

if (REDIS_ENABLED) {
    $redis = RedisHelper::getInstance();
    if (!$redis->rateLimit('ratelimit:api:' . $userId, 30, 60)) {
        jsonResponse(['success' => false, 'message' => '请求过于频繁'], 429);
    }
}

switch ($action) {

    case 'create':
        $typeId = intval($input['game_type_id'] ?? 0);
        $quantity = intval($input['quantity'] ?? 0);
        $desc = sanitize($input['target_description'] ?? '');
        $type = $db->prepare("SELECT * FROM game_types WHERE id = ? AND status = 'active'");
        $type->execute([$typeId]);
        $type = $type->fetch();
        if (!$type || $quantity <= 0) jsonResponse(['success' => false, 'message' => '参数错误']);
        $amount = $type['price_per_unit'] * $quantity;
        $orderNo = generateOrderNo();
        $stmt = $db->prepare("INSERT INTO orders (order_no, user_id, game_type_id, quantity, game_type, amount, target_description) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$orderNo, $userId, $typeId, $quantity, $type['name'], $amount, $desc]);
        publishTableReload('orders');
        publishTableReload('userOrder');
        jsonResponse(['success' => true, 'order_no' => $orderNo, 'amount' => $amount]);
        break;

    case 'pay':
        $orderId = intval($input['order_id']);
        $stmt = $db->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
        $stmt->execute([$orderId, $userId]);
        $order = $stmt->fetch();
        if (!$order || $order['payment_status'] === 'paid') jsonResponse(['success' => false, 'message' => '订单状态错误']);
        $user = getCurrentUser();
        if ($user['balance'] < $order['amount']) jsonResponse(['success' => false, 'message' => '余额不足，请先充值']);
        addTransaction($userId, 'payment', -$order['amount'], $orderId, '付款 #' . $order['order_no']);
        $db->prepare("UPDATE orders SET payment_status = 'paid' WHERE id = ?")->execute([$orderId]);
        publishTableReload('orders');
        publishTableReload('userOrder');
        jsonResponse(['success' => true, 'message' => '付款成功']);
        break;

    case 'list':
        $role = $_GET['role'] ?? 'user';
        if ($role === 'booster') {
            $stmt = $db->prepare("SELECT o.*, u.username AS user_name FROM orders o JOIN users u ON o.user_id = u.id WHERE o.status = 'pending' AND o.payment_status = 'paid' AND o.user_id != ? ORDER BY o.created_at DESC LIMIT 50");
            $stmt->execute([$userId]);
        } else {
            $stmt = $db->prepare("SELECT o.*, gt.unit_label, 
                                        b.phone AS booster_phone, b.wechat AS booster_wechat, b.qq AS booster_qq
                                 FROM orders o 
                                 LEFT JOIN game_types gt ON o.game_type_id = gt.id 
                                 LEFT JOIN boosters b ON o.booster_id = b.id 
                                 WHERE o.user_id = ? 
                                 ORDER BY o.created_at DESC LIMIT 50");
            $stmt->execute([$userId]);
        }
        $orders = $stmt->fetchAll();
        jsonResponse(['success' => true, 'orders' => $orders]);
        break;

    case 'accept':
        $orderId = intval($input['order_id']);
        $bstmt = $db->prepare("SELECT id FROM boosters WHERE user_id = ? AND status = 'active'");
        $bstmt->execute([$userId]);
        $boosterId = $bstmt->fetchColumn();
        if (!$boosterId) jsonResponse(['success' => false, 'message' => '您不是正式打手，无法接单']);
        $stmt = $db->prepare("UPDATE orders SET booster_id = ?, status = 'accepted', accepted_at = NOW() WHERE id = ? AND status = 'pending' AND payment_status = 'paid'");
        $stmt->execute([$boosterId, $orderId]);
        if ($stmt->rowCount() === 0) jsonResponse(['success' => false, 'message' => '该订单已被抢或状态异常']);
        publishTableReload('orders');
        publishTableReload('boosterOrders');
        jsonResponse(['success' => true, 'message' => '接单成功，请开始代练']);
        break;

    case 'upload_progress':
        $orderId = intval($input['order_id']);
        $desc = sanitize($input['description'] ?? '');
        $url = '';
        if (isset($_FILES['screenshot'])) $url = uploadScreenshot($_FILES['screenshot']);
        $bstmt = $db->prepare("SELECT id FROM boosters WHERE user_id = ? AND status = 'active'");
        $bstmt->execute([$userId]);
        $boosterId = $bstmt->fetchColumn();
        if (!$boosterId) jsonResponse(['success' => false, 'message' => '您不是正式打手']);
        $db->prepare("INSERT INTO progress (order_id, booster_id, description, screenshot_url) VALUES (?, ?, ?, ?)")->execute([$orderId, $boosterId, $desc, $url]);
        $db->prepare("UPDATE orders SET status = 'in_progress' WHERE id = ? AND status = 'accepted'")->execute([$orderId]);
        publishTableReload('orders');
        publishTableReload('boosterProgress');
        jsonResponse(['success' => true, 'message' => '进度已更新']);
        break;

    case 'get_progress':
        $orderId = intval($input['order_id'] ?? 0);
        $stmt = $db->prepare("SELECT p.* FROM progress p WHERE p.order_id = ? ORDER BY p.created_at DESC");
        $stmt->execute([$orderId]);
        jsonResponse(['success' => true, 'progress' => $stmt->fetchAll()]);
        break;

    case 'complete':
        $orderId = intval($input['order_id']);
        $stmt = $db->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ? AND status = 'in_progress'");
        $stmt->execute([$orderId, $userId]);
        $order = $stmt->fetch();
        if (!$order) jsonResponse(['success' => false, 'message' => '订单状态不正确']);
        $commission = $order['amount'] * COMMISSION_RATE;
        $boosterAmount = $order['amount'] - $commission;
        $db->beginTransaction();
        try {
            $db->prepare("UPDATE orders SET status = 'completed', completed_at = NOW() WHERE id = ?")->execute([$orderId]);
            if ($order['booster_id']) {
                $bu = $db->prepare("SELECT user_id FROM boosters WHERE id = ?");
                $bu->execute([$order['booster_id']]);
                $boosterUserId = $bu->fetchColumn();
                if ($boosterUserId) {
                    addTransaction($boosterUserId, 'earning', $boosterAmount, $orderId, '订单结算 #' . $order['order_no']);
                }
            }
            $db->commit();
            publishTableReload('orders');
            publishTableReload('userOrder');
            jsonResponse(['success' => true, 'message' => '验收完成，打手已结算']);
        } catch (Exception $e) {
            $db->rollBack();
            jsonResponse(['success' => false, 'message' => '操作失败']);
        }
        break;

    case 'dispute':
        $orderId = intval($input['order_id']);
        $reason = sanitize($input['reason'] ?? '');
        $db->prepare("INSERT INTO disputes (order_id, filed_by, reason) VALUES (?, ?, ?)")->execute([$orderId, $userId, $reason]);
        $db->prepare("UPDATE orders SET status = 'disputed' WHERE id = ?")->execute([$orderId]);
        publishTableReload('orders');
        publishTableReload('userOrder');
        jsonResponse(['success' => true, 'message' => '申诉已提交']);
        break;

    case 'my_booster_orders':
        $bstmt = $db->prepare("SELECT id FROM boosters WHERE user_id = ? AND status = 'active'");
        $bstmt->execute([$userId]);
        $bid = $bstmt->fetchColumn();
        if (!$bid) jsonResponse(['success' => false, 'message' => '非打手']);
        $stmt = $db->prepare("SELECT o.*, u.username AS user_name, u.phone AS user_phone, u.wechat AS user_wechat, u.qq AS user_qq
                              FROM orders o JOIN users u ON o.user_id = u.id 
                              WHERE o.booster_id = ? AND o.status IN ('accepted','in_progress') 
                              ORDER BY o.created_at DESC");
        $stmt->execute([$bid]);
        jsonResponse(['success' => true, 'orders' => $stmt->fetchAll()]);
        break;

    case 'list_history':
        if (!isLoggedIn()) jsonResponse(['success' => false, 'message' => '请先登录'], 401);
        $status = $_GET['status'] ?? '';
        $type = $_GET['type'] ?? '';
        $page = intval($_GET['page'] ?? 1);
        $limit = intval($_GET['limit'] ?? 20);
        if ($type === 'booster') {
            $boosterStmt = $db->prepare("SELECT id FROM boosters WHERE user_id = ? AND status = 'active'");
            $boosterStmt->execute([$userId]);
            $boosterId = $boosterStmt->fetchColumn();
            if (!$boosterId) jsonResponse(['success' => false, 'message' => '非打手或未激活']);
            $where = "o.booster_id = ? AND o.status IN ('completed','cancelled')";
            $params = [$boosterId];
        } elseif ($type === 'user') {
            $where = "o.user_id = ? AND o.status IN ('completed','cancelled')";
            $params = [$userId];
        } else {
            $boosterStmt = $db->prepare("SELECT id FROM boosters WHERE user_id = ? AND status = 'active'");
            $boosterStmt->execute([$userId]);
            $boosterId = $boosterStmt->fetchColumn();
            if ($boosterId) {
                $where = "o.booster_id = ? AND o.status IN ('completed','cancelled')";
                $params = [$boosterId];
            } else {
                $where = "o.user_id = ? AND o.status IN ('completed','cancelled')";
                $params = [$userId];
            }
        }
        if (!empty($status)) { $where .= " AND o.status = ?"; $params[] = $status; }
        $countStmt = $db->prepare("SELECT COUNT(*) FROM orders o WHERE $where");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();
        $offset = ($page - 1) * $limit;
        $sql = "SELECT o.*, u.username AS user_name, ub.username AS booster_name
                FROM orders o
                JOIN users u ON o.user_id = u.id
                LEFT JOIN boosters b ON o.booster_id = b.id
                LEFT JOIN users ub ON b.user_id = ub.id
                WHERE $where
                ORDER BY o.completed_at DESC
                LIMIT {$offset}, {$limit}";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $data = $stmt->fetchAll();
        jsonResponse(['success' => true, 'orders' => $data, 'total' => $total]);
        break;

    default:
        jsonResponse(['success' => false, 'message' => '未知操作: ' . $action]);
}