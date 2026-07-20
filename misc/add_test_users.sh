#!/bin/bash

# ZenTao 测试用户添加脚本
# 密码统一为: NewP@ss

echo "=== 添加ZenTao测试用户 ==="
echo "密码统一设置为: NewP@ss"
echo ""

# 使用Docker执行SQL
docker exec zentaopms-db-1 mysql -uroot -pzentao123456 zentao << 'EOF'
-- 开始事务
START TRANSACTION;

-- 添加测试用户
INSERT INTO zt_user (id, company, dept, account, password, role, realname, email, mobile, gender, join, visions) VALUES
(101, 1, 0, 'productmanager1', '74d6d491d3c2fb8d76acf1ab8bbc2c8d', 'po', '产品经理一', 'pm1@test.com', '13800138001', 'm', CURDATE(), 'rnd'),
(102, 1, 0, 'productmanager2', '74d6d491d3c2fb8d76acf1ab8bbc2c8d', 'po', '产品经理二', 'pm2@test.com', '13800138002', 'f', CURDATE(), 'rnd'),
(103, 1, 0, 'projectmanager1', '74d6d491d3c2fb8d76acf1ab8bbc2c8d', 'pm', '项目经理一', 'pjm1@test.com', '13800138003', 'm', CURDATE(), 'rnd'),
(104, 1, 0, 'projectmanager2', '74d6d491d3c2fb8d76acf1ab8bbc2c8d', 'pm', '项目经理二', 'pjm2@test.com', '13800138004', 'f', CURDATE(), 'rnd'),
(105, 1, 0, 'developer1', '74d6d491d3c2fb8d76acf1ab8bbc2c8d', 'dev', '研发工程师一', 'dev1@test.com', '13800138005', 'm', CURDATE(), 'rnd'),
(106, 1, 0, 'developer2', '74d6d491d3c2fb8d76acf1ab8bbc2c8d', 'dev', '研发工程师二', 'dev2@test.com', '13800138006', 'm', CURDATE(), 'rnd'),
(107, 1, 0, 'developer3', '74d6d491d3c2fb8d76acf1ab8bbc2c8d', 'dev', '研发工程师三', 'dev3@test.com', '13800138007', 'f', CURDATE(), 'rnd'),
(108, 1, 0, 'tester1', '74d6d491d3c2fb8d76acf1ab8bbc2c8d', 'qa', '测试工程师一', 'qa1@test.com', '13800138008', 'm', CURDATE(), 'rnd'),
(109, 1, 0, 'tester2', '74d6d491d3c2fb8d76acf1ab8bbc2c8d', 'qa', '测试工程师二', 'qa2@test.com', '13800138009', 'f', CURDATE(), 'rnd'),
(110, 1, 0, 'techdirector', '74d6d491d3c2fb8d76acf1ab8bbc2c8d', 'td', '技术总监', 'td@test.com', '13800138010', 'm', CURDATE(), 'rnd'),
(111, 1, 0, 'productdirector', '74d6d491d3c2fb8d76acf1ab8bbc2c8d', 'pd', '产品总监', 'pd@test.com', '13800138011', 'f', CURDATE(), 'rnd'),
(112, 1, 0, 'qualitydirector', '74d6d491d3c2fb8d76acf1ab8bbc2c8d', 'qd', '质量总监', 'qd@test.com', '13800138012', 'm', CURDATE(), 'rnd')
ON DUPLICATE KEY UPDATE realname=VALUES(realname), email=VALUES(email);

-- 为用户分配角色组
INSERT INTO zt_usergroup (account, group_id)
SELECT user.account, group_tbl.id
FROM (
    SELECT 'productmanager1' as account UNION
    SELECT 'productmanager2' UNION
    SELECT 'projectmanager1' UNION
    SELECT 'projectmanager2' UNION
    SELECT 'developer1' UNION
    SELECT 'developer2' UNION
    SELECT 'developer3' UNION
    SELECT 'tester1' UNION
    SELECT 'tester2' UNION
    SELECT 'techdirector' UNION
    SELECT 'productdirector' UNION
    SELECT 'qualitydirector'
) user
JOIN zt_user user_tbl ON user.account = user_tbl.account
JOIN zt_group group_tbl ON (
    (user_tbl.role = 'po' AND group_tbl.role = 'po') OR
    (user_tbl.role = 'pm' AND group_tbl.role = 'pm') OR
    (user_tbl.role = 'dev' AND group_tbl.role = 'dev') OR
    (user_tbl.role = 'qa' AND group_tbl.role = 'qa') OR
    (user_tbl.role = 'td' AND group_tbl.role = 'td') OR
    (user_tbl.role = 'pd' AND group_tbl.role = 'pd') OR
    (user_tbl.role = 'qd' AND group_tbl.role = 'qd')
) AND group_tbl.vision = 'rnd'
ON DUPLICATE KEY UPDATE group_id=VALUES(group_id);

-- 提交事务
COMMIT;

-- 显示添加的用户
SELECT CONCAT('账号: ', account, ', 密码: NewP@ss, 角色: ', realname) as '登录信息' FROM zt_user WHERE id BETWEEN 101 AND 112;
EOF

echo ""
echo "✓ 测试用户添加完成！"
echo ""
echo "用户登录信息:"
echo "  产品经理: productmanager1, productmanager2"
echo "  项目经理: projectmanager1, projectmanager2"
echo "  研发工程师: developer1, developer2, developer3"
echo "  测试工程师: tester1, tester2"
echo "  技术总监: techdirector"
echo "  产品总监: productdirector"
echo "  质量总监: qualitydirector"
echo ""
echo "统一密码: NewP@ss"