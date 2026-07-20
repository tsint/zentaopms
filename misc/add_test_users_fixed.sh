#!/bin/bash

# ZenTao 测试用户添加脚本
# 密码统一为: NewP@ss

echo "=== 添加ZenTao测试用户 ==="
echo "密码统一设置为: NewP@ss"
echo ""

# 使用Docker执行SQL，指定正确的字符集
docker exec zentaopms-db-1 mysql -uroot -pzentao123456 --default-character-set=utf8mb4 zentao << 'EOF'
-- 开始事务
START TRANSACTION;

-- 添加测试用户
INSERT INTO zt_user (id, company, dept, account, password, role, realname, email, mobile, gender, \`join\`, visions) VALUES
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

-- 删除旧的用户组分配
DELETE FROM zt_usergroup WHERE account IN ('productmanager1', 'productmanager2', 'projectmanager1', 'projectmanager2', 'developer1', 'developer2', 'developer3', 'tester1', 'tester2', 'techdirector', 'productdirector', 'qualitydirector');

-- 为用户分配角色组
INSERT INTO zt_usergroup (account, \`group\`) VALUES
('productmanager1', 5), ('productmanager2', 5),
('projectmanager1', 4), ('projectmanager2', 4),
('developer1', 2), ('developer2', 2), ('developer3', 2),
('tester1', 3), ('tester2', 3),
('techdirector', 6),
('productdirector', 7),
('qualitydirector', 8);

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