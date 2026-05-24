<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');
if (!isLoggedIn()) { echo json_encode(['code' => 1, 'msg' => '请先登录']); exit; }

$db = getDB();
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $input['action'] ?? '';

switch ($action) {
    case 'update_avatar':
        $avatar = sanitize($input['avatar']);
        $db->prepare("UPDATE users SET avatar=? WHERE id=?")->execute([$avatar, $_SESSION['user_id']]);
        echo json_encode(['code' => 0, 'msg' => '头像已更新']);
        break;

    case 'reset_avatar':
        $db->prepare("UPDATE users SET avatar = '' WHERE id=?")->execute([$_SESSION['user_id']]);
        echo json_encode(['code' => 0, 'msg' => '头像已恢复默认']);
        break;

    case 'update_profile':
        $real = sanitize($input['real_name']);
        $phone = sanitize($input['phone']);
        $db->prepare("UPDATE users SET real_name=?, phone=? WHERE id=?")->execute([$real, $phone, $_SESSION['user_id']]);
        echo json_encode(['code' => 0, 'msg' => '信息已更新']);
        break;

    case 'change_password':
        $old = trim($input['old_password'] ?? '');
        $new = trim($input['new_password'] ?? '');
        if ($old === '' || $new === '') { echo json_encode(['code' => 1, 'msg' => '旧密码和新密码不能为空']); exit; }
        $user = getCurrentUser();
        if (!password_verify($old, $user['password_hash'])) { echo json_encode(['code' => 1, 'msg' => '旧密码错误']); exit; }
        $hash = password_hash($new, PASSWORD_DEFAULT);
        $db->prepare("UPDATE users SET password_hash=? WHERE id=?")->execute([$hash, $_SESSION['user_id']]);
        echo json_encode(['code' => 0, 'msg' => '密码修改成功']);
        break;

    // 预备打手缴纳押金
    case 'pay_deposit':
        $booster = $db->prepare("SELECT * FROM boosters WHERE user_id = ? AND status = 'pending'");
        $booster->execute([$_SESSION['user_id']]);
        $booster = $booster->fetch();
        if (!$booster) { echo json_encode(['code' => 1, 'msg' => '您不是预备打手']); exit; }

        $depositAmount = $db->query("SELECT cfg_value FROM system_config WHERE cfg_key='deposit_amount'")->fetchColumn() ?: DEPOSIT_AMOUNT;
        $user = getCurrentUser();
        if ($user['balance'] < $depositAmount) { echo json_encode(['code' => 1, 'msg' => '余额不足，请先充值']); exit; }

        $db->beginTransaction();
        try {
            addTransaction($user['id'], 'deposit', -$depositAmount, null, '缴纳打手保证金');
            $db->prepare("UPDATE boosters SET status = 'active', deposit_paid = ? WHERE id = ?")->execute([$depositAmount, $booster['id']]);
            $db->commit();
            echo json_encode(['code' => 0, 'msg' => '保证金已缴纳，您已成为正式打手']);
        } catch (Exception $e) {
            $db->rollBack();
            echo json_encode(['code' => 1, 'msg' => '操作失败']);
        }
        exit;

    // 预备打手填写邀请码
    case 'apply_invite':
        $booster = $db->prepare("SELECT * FROM boosters WHERE user_id = ? AND status = 'pending'");
        $booster->execute([$_SESSION['user_id']]);
        $booster = $booster->fetch();
        if (!$booster) { echo json_encode(['code' => 1, 'msg' => '您不是预备打手']); exit; }

        $code = sanitize($input['invite_code'] ?? '');
        if (empty($code)) { echo json_encode(['code' => 1, 'msg' => '请填写邀请码']); exit; }

        $stmt = $db->prepare("SELECT * FROM invite_codes WHERE code = ? AND status = 'unused' AND (expire_at IS NULL OR expire_at > NOW())");
        $stmt->execute([$code]);
        $inv = $stmt->fetch();
        if (!$inv) { echo json_encode(['code' => 1, 'msg' => '邀请码无效或已过期']); exit; }

        $db->beginTransaction();
        try {
            $db->prepare("UPDATE invite_codes SET used_by = ?, status = 'used', used_at = NOW() WHERE id = ?")->execute([$_SESSION['user_id'], $inv['id']]);
            $db->prepare("UPDATE boosters SET status = 'active' WHERE id = ?")->execute([$booster['id']]);
            $db->commit();
            echo json_encode(['code' => 0, 'msg' => '邀请码验证成功，您已成为正式打手']);
        } catch (Exception $e) {
            $db->rollBack();
            echo json_encode(['code' => 1, 'msg' => '操作失败']);
        }
        exit;
    case 'reapply_booster':
    $booster = $db->prepare("SELECT * FROM boosters WHERE user_id = ? AND status = 'banned'");
    $booster->execute([$_SESSION['user_id']]);
    $booster = $booster->fetch();
    if (!$booster) {
        echo json_encode(['code' => 1, 'msg' => '您没有被打手拒绝记录']);
        exit;
    }
    $db->prepare("UPDATE boosters SET status = 'pending', deposit_paid = 0 WHERE id = ?")->execute([$booster['id']]);
    echo json_encode(['code' => 0, 'msg' => '已重新提交申请，您现在可以缴纳押金或填写邀请码']);
    exit;
    case 'get_profile':
    $userId = $_SESSION['user_id'];
    $user = getCurrentUser();
    unset($user['password_hash']);
    // 补充打手信息
    $boosterInfo = $db->prepare("SELECT * FROM boosters WHERE user_id = ?");
    $boosterInfo->execute([$userId]);
    $user['booster'] = $boosterInfo->fetch();
    // 补充统计
    $stats = $db->prepare("SELECT COUNT(*) AS total_orders, SUM(amount) AS total_spent FROM orders WHERE user_id = ?");
    $stats->execute([$userId]);
    $user['stats'] = $stats->fetch();
    echo json_encode(['code' => 0, 'data' => $user]);
    exit;
    default:
        echo json_encode(['code' => 1, 'msg' => '未知操作']);
        exit;
}