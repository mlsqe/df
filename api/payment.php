<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');
if (!isLoggedIn()) jsonResponse(['success'=>false,'message'=>'请登录'], 401);

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

switch ($action) {
    case 'recharge':
        $amount = floatval($input['amount']);
        if ($amount <= 0) jsonResponse(['success'=>false,'message'=>'金额错误']);
        $orderNo = 'R' . date('YmdHis') . rand(100, 999);
        $payParams = [
            'pid' => PAYMENT_MERCHANT_ID,
            'type' => 'alipay',
            'out_trade_no' => $orderNo,
            'notify_url' => 'https://yourdomain.com/api/payment.php?action=notify',
            'return_url' => 'https://yourdomain.com/index.php',
            'name' => '账户充值',
            'money' => $amount,
        ];
        $signStr = '';
        foreach ($payParams as $v) $signStr .= $v;
        $payParams['sign'] = md5($signStr . PAYMENT_SECRET_KEY);
        $payUrl = PAYMENT_API_URL . 'submit.php?' . http_build_query($payParams);
        $db = getDB();
        $db->prepare("INSERT INTO recharge_log (user_id, order_no, amount, pay_type) VALUES (?,?,?,'epay')")->execute([$_SESSION['user_id'], $orderNo, $amount]);
        jsonResponse(['success'=>true, 'pay_url'=>$payUrl]);
        break;

    case 'notify':
        // 易支付异步通知处理（伪代码）
        $data = $_GET;
        // 验签 & 更新余额 & 记录日志
        echo 'success';
        break;
}