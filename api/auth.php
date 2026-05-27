<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $input['action'] ?? '';

switch ($action) {
    case 'login':
        $username = trim($input['username'] ?? '');
        $password = trim($input['password'] ?? '');
        if ($username === '' || $password === '') jsonResponse(['success' => false, 'message' => '用户名和密码不能为空']);
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        if (!$user) jsonResponse(['success' => false, 'message' => '用户名或密码错误']);
        if ($user['status'] === 'banned') jsonResponse(['success' => false, 'message' => '账号已被封禁，请联系管理员']);
        if (password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            jsonResponse(['success' => true, 'user' => $user]);
        }
        jsonResponse(['success' => false, 'message' => '用户名或密码错误']);
        break;

    case 'register':
        $username = trim($input['username'] ?? '');
        $password = trim($input['password'] ?? '');
        $role = $input['role'] ?? 'user';
        $phone = trim($input['phone'] ?? '');
        $wechat = trim($input['wechat'] ?? '');
        $qq = trim($input['qq'] ?? '');
        $inviteCode = trim($input['invite_code'] ?? '');
        $boosterMode = $input['booster_mode'] ?? 'invite';
        $userSelectMode = $input['user_select_mode'] ?? '';

        if ($username === '' || $password === '') jsonResponse(['success' => false, 'message' => '用户名和密码不能为空']);
        if (mb_strlen($username) < 2) jsonResponse(['success' => false, 'message' => '用户名至少2个字符']);
        if (mb_strlen($password) < 4) jsonResponse(['success' => false, 'message' => '密码至少4个字符']);

        $db = getDB();
        $boosterStatus = 'pending';
        $inviteUsed = false;
        $finalRegisterMode = $boosterMode;
        // 默认角色为 user，若选择打手则改为 pending_booster
        $finalRole = 'user';

        if ($role === 'booster') {
            $finalRole = 'pending_booster';   // 预备打手独立角色
            if (empty($phone) && empty($wechat) && empty($qq)) jsonResponse(['success' => false, 'message' => '请至少填写一种联系方式']);
            switch ($boosterMode) {
                case 'invite':
                    if ($inviteCode === '') jsonResponse(['success' => false, 'message' => '请填写邀请码']);
                    $stmt = $db->prepare("SELECT * FROM invite_codes WHERE code=? AND status='unused' AND (expire_at IS NULL OR expire_at > NOW())");
                    $stmt->execute([$inviteCode]);
                    $inv = $stmt->fetch();
                    if (!$inv) jsonResponse(['success' => false, 'message' => '邀请码无效或已过期']);
                    $inviteUsed = true;
                    break;
                case 'deposit':
                    break;
                case 'invite_or_deposit':
                    if ($userSelectMode === 'invite') {
                        if ($inviteCode === '') jsonResponse(['success' => false, 'message' => '请填写邀请码']);
                        $stmt = $db->prepare("SELECT * FROM invite_codes WHERE code=? AND status='unused' AND (expire_at IS NULL OR expire_at > NOW())");
                        $stmt->execute([$inviteCode]);
                        $inv = $stmt->fetch();
                        if (!$inv) jsonResponse(['success' => false, 'message' => '邀请码无效或已过期']);
                        $inviteUsed = true;
                        $finalRegisterMode = 'invite';
                    } elseif ($userSelectMode === 'deposit') {
                        $finalRegisterMode = 'deposit';
                    } else jsonResponse(['success' => false, 'message' => '请选择邀请码或押金']);
                    break;
                case 'free':
                default:
                    $finalRegisterMode = 'free';
                    break;
            }
        }

        $res = register($username, $password, '', '', $finalRole);
        if ($res['success']) {
            $userId = $res['user_id'];
            if ($finalRole === 'pending_booster') {
                if ($inviteUsed && isset($inv)) {
                    $db->prepare("UPDATE invite_codes SET used_by=?, status='used', used_at=NOW() WHERE id=?")->execute([$userId, $inv['id']]);
                }
                $defaultLevel = $db->query("SELECT id FROM booster_levels ORDER BY sort_order ASC LIMIT 1")->fetchColumn();
                $db->prepare("INSERT INTO boosters (user_id, real_name, phone, wechat, qq, level_id, status, deposit_paid, register_mode) VALUES (?,?,?,?,?,?,?,?,?)")
                   ->execute([$userId, '', $phone, $wechat, $qq, $defaultLevel ?: null, $boosterStatus, 0, $finalRegisterMode]);
                publishTableReload('boosters');
                publishTableReload('users');
            }
            $msg = '注册成功';
            if ($finalRole === 'pending_booster') $msg = "注册成功！您的打手申请已提交，请等待管理员审核。";
            jsonResponse(['success' => true, 'message' => $msg]);
        } else jsonResponse($res);
        break;

    case 'logout':
        logout();
        jsonResponse(['code' => 0, 'msg' => '已退出']);
        break;

    default:
        jsonResponse(['success' => false, 'message' => '未知操作: ' . $action]);
}