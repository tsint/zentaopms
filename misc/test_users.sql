-- ZenTao 测试用户添加脚本
-- 密码统一为: NewP@ss
-- 注意：执行时需要使用 --default-character-set=utf8mb4 参数
-- 创建时间: 2026-07-17

-- 设置字符集
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

-- 添加测试用户
INSERT INTO `zt_user` (`id`, `company`, `dept`, `account`, `password`, `role`, `realname`, `email`, `mobile`, `gender`, `join`, `visions`) VALUES
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
(112, 1, 0, 'qualitydirector', '74d6d491d3c2fb8d76acf1ab8bbc2c8d', 'qd', '质量总监', 'qd@test.com', '13800138012', 'm', CURDATE(), 'rnd');

-- 为用户分配角色组
-- 产品经理
INSERT INTO `zt_usergroup` (`account`, `group`) VALUES
('productmanager1', (SELECT id FROM zt_group WHERE role = 'po' LIMIT 1)),
('productmanager2', (SELECT id FROM zt_group WHERE role = 'po' LIMIT 1));

-- 项目经理
INSERT INTO `zt_usergroup` (`account`, `group`) VALUES
('projectmanager1', (SELECT id FROM zt_group WHERE role = 'pm' LIMIT 1)),
('projectmanager2', (SELECT id FROM zt_group WHERE role = 'pm' LIMIT 1));

-- 研发工程师
INSERT INTO `zt_usergroup` (`account`, `group`) VALUES
('developer1', (SELECT id FROM zt_group WHERE role = 'dev' LIMIT 1)),
('developer2', (SELECT id FROM zt_group WHERE role = 'dev' LIMIT 1)),
('developer3', (SELECT id FROM zt_group WHERE role = 'dev' LIMIT 1));

-- 测试工程师
INSERT INTO `zt_usergroup` (`account`, `group`) VALUES
('tester1', (SELECT id FROM zt_group WHERE role = 'qa' LIMIT 1)),
('tester2', (SELECT id FROM zt_group WHERE role = 'qa' LIMIT 1));

-- 技术总监
INSERT INTO `zt_usergroup` (`account`, `group`) VALUES
('techdirector', (SELECT id FROM zt_group WHERE role = 'td' LIMIT 1));

-- 产品总监
INSERT INTO `zt_usergroup` (`account`, `group`) VALUES
('productdirector', (SELECT id FROM zt_group WHERE role = 'pd' LIMIT 1));

-- 质量总监
INSERT INTO `zt_usergroup` (`account`, `group`) VALUES
('qualitydirector', (SELECT id FROM zt_group WHERE role = 'qd' LIMIT 1));

-- 说明：
-- 1. 所有用户的密码都是: NewP@ss
-- 2. 用户ID从101开始，避免与现有用户冲突
-- 3. 每个用户都分配了对应的角色组
-- 4. 用户可以根据需要修改姓名、邮箱、手机号等信息
-- 5. 如果需要删除这些测试用户，执行以下SQL：
--    DELETE FROM zt_user WHERE id BETWEEN 101 AND 112;
--    DELETE FROM zt_usergroup WHERE account IN ('productmanager1', 'productmanager2', 'projectmanager1', 'projectmanager2', 'developer1', 'developer2', 'developer3', 'tester1', 'tester2', 'techdirector', 'productdirector', 'qualitydirector');