#!/bin/bash

# ZenTao 用户姓名乱码修复脚本
# 用于修复中文字符显示乱码问题

echo "=== 修复ZenTao用户姓名乱码 ==="
echo ""

# 使用正确的字符集更新用户姓名
docker exec zentaopms-db-1 mysql -uroot -pzentao123456 --default-character-set=utf8mb4 zentao << 'EOF'
-- 更新测试用户姓名
UPDATE zt_user SET realname = '产品经理一' WHERE account = 'productmanager1';
UPDATE zt_user SET realname = '产品经理二' WHERE account = 'productmanager2';
UPDATE zt_user SET realname = '项目经理一' WHERE account = 'projectmanager1';
UPDATE zt_user SET realname = '项目经理二' WHERE account = 'projectmanager2';
UPDATE zt_user SET realname = '研发工程师一' WHERE account = 'developer1';
UPDATE zt_user SET realname = '研发工程师二' WHERE account = 'developer2';
UPDATE zt_user SET realname = '研发工程师三' WHERE account = 'developer3';
UPDATE zt_user SET realname = '测试工程师一' WHERE account = 'tester1';
UPDATE zt_user SET realname = '测试工程师二' WHERE account = 'tester2';
UPDATE zt_user SET realname = '技术总监' WHERE account = 'techdirector';
UPDATE zt_user SET realname = '产品总监' WHERE account = 'productdirector';
UPDATE zt_user SET realname = '质量总监' WHERE account = 'qualitydirector';

-- 验证修复结果
SELECT account, realname, role FROM zt_user WHERE id BETWEEN 101 AND 112 ORDER BY id;
EOF

echo ""
echo "✓ 用户姓名修复完成！"