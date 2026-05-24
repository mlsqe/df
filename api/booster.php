<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');
if (!isLoggedIn()) jsonResponse(['success' => false, 'message' => '请先登录'], 401);

$db = getDB();
$userId = $_SESSION['user_id'];
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $input['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    // 入驻申请
    case 'apply':
        $real = sanitize($input['real_name'] ?? '');
        $phone = sanitize($input['phone'] ?? '');
        if (empty($real) || empty($phone)) jsonResponse(['success' => false, 'message' => '请填写姓名和手机号']);
        $defaultLevel = $db->query("SELECT id FROM booster_levels ORDER BY sort_order ASC LIMIT 1")->fetchColumn();
        $stmt = $db->prepare("INSERT INTO boosters (user_id, real_name, phone, level_id, status) VALUES (?,?,?,?,'pending')");
        $stmt->execute([$userId, $real, $phone, $defaultLevel ?: null]);
        jsonResponse(['success' => true, 'message' => '申请已提交']);
        break;

    // 生成邀请码（仅正式打手）
    case 'create_invite':
        $bstmt = $db->prepare("SELECT id, status FROM boosters WHERE user_id = ? AND status = 'active'");
        $bstmt->execute([$userId]);
        $booster = $bstmt->fetch();
        if (!$booster) jsonResponse(['success' => false, 'message' => '仅正式打手可生成邀请码']);

        // 获取打手周期限制
        $limit = intval($db->query("SELECT cfg_value FROM system_config WHERE cfg_key='booster_invite_limit'")->fetchColumn() ?: 5);
        $periodDays = intval($db->query("SELECT cfg_value FROM system_config WHERE cfg_key='booster_invite_period_days'")->fetchColumn() ?: 30);
        $startDate = date('Y-m-d H:i:s', strtotime("-{$periodDays} days"));
        $used = $db->prepare("SELECT COUNT(*) FROM invite_codes WHERE created_by=? AND created_at >= ?");
        $used->execute([$userId, $startDate]);
        $usedCount = $used->fetchColumn();

        $count = intval($input['count'] ?? 1);
        $expireDays = intval($input['expire_days'] ?? 30);

        if ($usedCount + $count > $limit) {
            $remain = $limit - $usedCount;
            jsonResponse(['success' => false, 'message' => "本周期剩余可创建 {$remain} 个邀请码"]);
        }

        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $code = strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 8));
            $expireAt = date('Y-m-d H:i:s', strtotime("+{$expireDays} days"));
            $db->prepare("INSERT INTO invite_codes (code, type, created_by, expire_at) VALUES (?, 'booster', ?, ?)")
               ->execute([$code, $userId, $expireAt]);
            $codes[] = $code;
        }
        // 通知打手自己刷新邀请码列表（如果有打开的话）
        publishTableReload('boosterInvites');
        jsonResponse(['success' => true, 'codes' => $codes]);
        break;

    // 查看我的邀请码
    case 'my_invites':
        $stmt = $db->prepare("SELECT code, status, expire_at, created_at FROM invite_codes WHERE created_by=? AND type='booster' ORDER BY created_at DESC LIMIT 50");
        $stmt->execute([$userId]);
        jsonResponse(['success' => true, 'data' => $stmt->fetchAll()]);
        break;

    // 获取周期限制信息
    case 'invite_limit_info':
        $limit = intval($db->query("SELECT cfg_value FROM system_config WHERE cfg_key='booster_invite_limit'")->fetchColumn() ?: 5);
        $periodDays = intval($db->query("SELECT cfg_value FROM system_config WHERE cfg_key='booster_invite_period_days'")->fetchColumn() ?: 30);
        $startDate = date('Y-m-d H:i:s', strtotime("-{$periodDays} days"));
        $used = $db->prepare("SELECT COUNT(*) FROM invite_codes WHERE created_by=? AND created_at >= ?");
        $used->execute([$userId, $startDate]);
        $usedCount = $used->fetchColumn();
        jsonResponse(['success' => true, 'data' => ['limit' => $limit, 'used' => $usedCount, 'remain' => max(0, $limit - $usedCount), 'periodDays' => $periodDays]]);
        break;

    default:
        jsonResponse(['success' => false, 'message' => '未知操作: ' . $action]);
}