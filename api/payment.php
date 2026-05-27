<?php
// api/payment.php - 支付接口（发起支付 / 回调处理）
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

$db = getDB();
$action = $_GET['action'] ?? ($_POST['action'] ?? '');

// 获取当前登录用户ID（需自行实现或从 session 中读取）
$user_id = get_user_id();

// 允许未登录访问的 action（如支付回调）
if (!in_array($action, ['callback', 'notify']) && !$user_id) {
    echo json_encode(['code' => -1, 'msg' => '请先登录']);
    exit;
}

switch ($action) {
    case 'create':
        // 发起支付
        $amount = floatval($_POST['amount'] ?? 0);
        $channel_code = trim($_POST['channel'] ?? 'default');
        if ($amount <= 0) {
            echo json_encode(['code' => -1, 'msg' => '金额错误']);
            exit;
        }
        // 获取支付渠道配置
        $stmt = $db->prepare("SELECT * FROM payment_channels WHERE channel_code = ? AND status = 'enabled'");
        $stmt->execute([$channel_code]);
        $channel = $stmt->fetch();
        if (!$channel) {
            echo json_encode(['code' => -1, 'msg' => '支付渠道不可用']);
            exit;
        }
        // 生成平台订单号
        $order_no = 'R' . date('YmdHis') . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
        // 写入充值日志
        $stmt = $db->prepare("INSERT INTO recharge_log (user_id, order_no, amount, pay_type, channel_code, status) VALUES (?, ?, ?, ?, ?, 'pending')");
        $stmt->execute([$user_id, $order_no, $amount, $channel['pay_type'], $channel['channel_code']]);
        $log_id = $db->lastInsertId();

        // 构建易支付请求参数
        $notify_url = 'https://' . $_SERVER['HTTP_HOST'] . '/api/payment.php?action=notify';
        $return_url = 'https://' . $_SERVER['HTTP_HOST'] . '/?module=dashboard';

        $params = [
            'pid'        => $channel['merchant_id'],
            'type'       => $channel['pay_type'],
            'out_trade_no' => $order_no,
            'notify_url' => $notify_url,
            'return_url' => $return_url,
            'name'       => '账户充值',
            'money'      => $amount,
            'sitename'   => SITE_NAME,
        ];
        // 生成签名
        ksort($params);
        $sign_str = urldecode(http_build_query($params)) . $channel['secret_key'];
        $params['sign'] = md5($sign_str);
        $params['sign_type'] = 'MD5';

        echo json_encode([
            'code' => 0,
            'msg'  => '订单创建成功',
            'data' => [
                'order_no' => $order_no,
                'amount'   => $amount,
                'pay_url'  => $channel['api_url'] . 'submit.php?' . http_build_query($params)
            ]
        ]);
        break;

    case 'notify':
        // 支付异步通知回调
        $raw = file_get_contents('php://input');
        $data = $_POST;
        if (empty($data)) {
            echo 'fail';
            exit;
        }
        $order_no = $data['out_trade_no'] ?? '';
        // 查找本地订单
        $stmt = $db->prepare("SELECT r.*, c.secret_key FROM recharge_log r JOIN payment_channels c ON r.channel_code = c.channel_code WHERE r.order_no = ?");
        $stmt->execute([$order_no]);
        $order = $stmt->fetch();
        if (!$order || $order['status'] !== 'pending') {
            echo 'fail';
            exit;
        }
        // 验签
        $sign = $data['sign'] ?? '';
        unset($data['sign'], $data['sign_type']);
        ksort($data);
        $sign_str = urldecode(http_build_query($data)) . $order['secret_key'];
        if (md5($sign_str) != $sign) {
            echo 'sign error';
            exit;
        }
        // 支付成功，更新订单
        $db->beginTransaction();
        try {
            // 更新 recharge_log 状态
            $stmt = $db->prepare("UPDATE recharge_log SET status = 'success', notify_data = ?, paid_at = NOW() WHERE id = ?");
            $stmt->execute([json_encode($data), $order['id']]);
            // 增加用户余额（注意幂等，避免重复加款）
            $stmt = $db->prepare("UPDATE users SET balance = balance + ? WHERE id = ? AND (SELECT status FROM recharge_log WHERE id = ?) = 'pending'");
            $stmt->execute([$order['amount'], $order['user_id'], $order['id']]);
            // 写入交易记录
            $stmt = $db->prepare("INSERT INTO transactions (user_id, type, amount, balance_after, description) VALUES (?, 'recharge', ?, (SELECT balance FROM users WHERE id = ?), '在线充值')");
            $stmt->execute([$order['user_id'], $order['amount'], $order['user_id']]);
            $db->commit();
        } catch (Exception $e) {
            $db->rollBack();
            echo 'fail';
            exit;
        }
        echo 'success';
        break;

    case 'query':
        // 查询订单状态（供前端轮询）
        $order_no = $_GET['order_no'] ?? '';
        $stmt = $db->prepare("SELECT * FROM recharge_log WHERE order_no = ? AND user_id = ?");
        $stmt->execute([$order_no, $user_id]);
        $order = $stmt->fetch();
        if ($order) {
            echo json_encode(['code' => 0, 'status' => $order['status']]);
        } else {
            echo json_encode(['code' => -1, 'msg' => '订单不存在']);
        }
        break;

    default:
        echo json_encode(['code' => -1, 'msg' => '未知操作']);
}