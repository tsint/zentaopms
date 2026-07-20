#!/usr/bin/env php
<?php
/**
 * 添加ZenTao测试用户脚本
 * 密码统一为: NewP@ss
 */

// 定义密码MD5值
define('TEST_PASSWORD_MD5', '74d6d491d3c2fb8d76acf1ab8bbc2c8d'); // NewP@ss

// 测试用户数据
$testUsers = array(
    array('id' => 101, 'account' => 'productmanager1', 'realname' => '产品经理一', 'email' => 'pm1@test.com', 'mobile' => '13800138001', 'role' => 'po'),
    array('id' => 102, 'account' => 'productmanager2', 'realname' => '产品经理二', 'email' => 'pm2@test.com', 'mobile' => '13800138002', 'role' => 'po'),
    array('id' => 103, 'account' => 'projectmanager1', 'realname' => '项目经理一', 'email' => 'pjm1@test.com', 'mobile' => '13800138003', 'role' => 'pm'),
    array('id' => 104, 'account' => 'projectmanager2', 'realname' => '项目经理二', 'email' => 'pjm2@test.com', 'mobile' => '13800138004', 'role' => 'pm'),
    array('id' => 105, 'account' => 'developer1', 'realname' => '研发工程师一', 'email' => 'dev1@test.com', 'mobile' => '13800138005', 'role' => 'dev'),
    array('id' => 106, 'account' => 'developer2', 'realname' => '研发工程师二', 'email' => 'dev2@test.com', 'mobile' => '13800138006', 'role' => 'dev'),
    array('id' => 107, 'account' => 'developer3', 'realname' => '研发工程师三', 'email' => 'dev3@test.com', 'mobile' => '13800138007', 'role' => 'dev'),
    array('id' => 108, 'account' => 'tester1', 'realname' => '测试工程师一', 'email' => 'qa1@test.com', 'mobile' => '13800138008', 'role' => 'qa'),
    array('id' => 109, 'account' => 'tester2', 'realname' => '测试工程师二', 'email' => 'qa2@test.com', 'mobile' => '13800138009', 'role' => 'qa'),
    array('id' => 110, 'account' => 'techdirector', 'realname' => '技术总监', 'email' => 'td@test.com', 'mobile' => '13800138010', 'role' => 'td'),
    array('id' => 111, 'account' => 'productdirector', 'realname' => '产品总监', 'email' => 'pd@test.com', 'mobile' => '13800138011', 'role' => 'pd'),
    array('id' => 112, 'account' => 'qualitydirector', 'realname' => '质量总监', 'email' => 'qd@test.com', 'mobile' => '13800138012', 'role' => 'qd'),
);

echo "=== 添加ZenTao测试用户 ===" . PHP_EOL;
echo "密码统一设置为: NewP@ss" . PHP_EOL . PHP_EOL;

// 从环境变量获取数据库配置
$dbHost = getenv('ZT_DB_HOST') ?: 'localhost';
$dbPort = getenv('ZT_DB_PORT') ?: '3306';
$dbName = getenv('ZT_DB_NAME') ?: 'zentao';
$dbUser = getenv('ZT_DB_USER') ?: 'root';
$dbPass = getenv('ZT_DB_PASSWORD') ?: '';

try {
    // 连接数据库
    $dsn = "mysql:host=$dbHost;port=$dbPort;dbname=$dbName;charset=utf8";
    $pdo = new PDO($dsn, $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "已连接到数据库: $dbName" . PHP_EOL . PHP_EOL;

    // 开始事务
    $pdo->beginTransaction();

    // 检查用户是否已存在
    $existingAccounts = array();
    $checkSQL = "SELECT account FROM zt_user WHERE id BETWEEN 101 AND 112";
    $result = $pdo->query($checkSQL)->fetchAll(PDO::FETCH_COLUMN);
    $existingAccounts = $result;

    if (!empty($existingAccounts)) {
        echo "以下用户已存在，将被跳过: " . implode(', ', $existingAccounts) . PHP_EOL . PHP_EOL;
    }

    // 添加用户
    $addedCount = 0;
    foreach ($testUsers as $user) {
        if (in_array($user['account'], $existingAccounts)) {
            echo "跳过已存在的用户: {$user['account']}" . PHP_EOL;
            continue;
        }

        $insertSQL = "INSERT INTO zt_user (id, company, dept, account, password, role, realname, email, mobile, gender, join, visions) VALUES "
                   . "('{$user['id']}', 1, 0, '{$user['account']}', '" . TEST_PASSWORD_MD5 . "', '{$user['role']}', '{$user['realname']}', '{$user['email']}', '{$user['mobile']}', 'm', CURDATE(), 'rnd')";

        $pdo->exec($insertSQL);
        echo "添加用户: {$user['account']} ({$user['realname']})" . PHP_EOL;
        $addedCount++;
    }

    echo PHP_EOL;

    // 为用户分配角色组
    foreach ($testUsers as $user) {
        if (in_array($user['account'], $existingAccounts)) continue;

        // 获取角色组ID
        $groupSQL = "SELECT id FROM zt_group WHERE role = '{$user['role']}' AND vision = 'rnd' LIMIT 1";
        $groupResult = $pdo->query($groupSQL)->fetch(PDO::FETCH_ASSOC);

        if ($groupResult) {
            $groupId = $groupResult['id'];
            $userGroupSQL = "INSERT INTO zt_usergroup (account, `group`) VALUES ('{$user['account']}', $groupId)";
            $pdo->exec($userGroupSQL);
            echo "  → {$user['account']} 分配角色组: {$user['role']}" . PHP_EOL;
        } else {
            echo "  ⚠ {$user['account']} 未找到角色组: {$user['role']}" . PHP_EOL;
        }
    }

    // 提交事务
    $pdo->commit();

    echo PHP_EOL . "✓ 成功添加 $addedCount 个测试用户！" . PHP_EOL;
    echo PHP_EOL . "用户登录信息:" . PHP_EOL;
    foreach ($testUsers as $user) {
        if (!in_array($user['account'], $existingAccounts)) {
            echo "  账号: {$user['account']}, 密码: NewP@ss, 角色: {$user['realname']}" . PHP_EOL;
        }
    }

    echo PHP_EOL . "如需删除这些测试用户，可执行: DELETE FROM zt_user WHERE id BETWEEN 101 AND 112;" . PHP_EOL;

} catch (PDOException $e) {
    // 回滚事务
    $pdo->rollBack();
    echo "✗ 数据库错误: " . $e->getMessage() . PHP_EOL;
    echo "请检查数据库连接配置是否正确" . PHP_EOL;
    exit(1);
} catch (Exception $e) {
    $pdo->rollBack();
    echo "✗ 添加用户失败: " . $e->getMessage() . PHP_EOL;
    exit(1);
}