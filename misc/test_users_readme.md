# ZenTao 测试用户说明

## 用户列表

已成功为ZenTao项目添加以下测试用户，统一密码：**NewP@ss**

### 产品经理 (PO)
- **账号**: `productmanager1`, `productmanager2`
- **姓名**: 产品经理一，产品经理二
- **角色**: 产品负责人
- **邮箱**: pm1@test.com, pm2@test.com

### 项目经理 (PM)
- **账号**: `projectmanager1`, `projectmanager2`
- **姓名**: 项目经理一，项目经理二
- **角色**: 项目经理
- **邮箱**: pjm1@test.com, pjm2@test.com

### 研发工程师 (DEV)
- **账号**: `developer1`, `developer2`, `developer3`
- **姓名**: 研发工程师一，研发工程师二，研发工程师三
- **角色**: 开发人员
- **邮箱**: dev1@test.com, dev2@test.com, dev3@test.com

### 测试工程师 (QA)
- **账号**: `tester1`, `tester2`
- **姓名**: 测试工程师一，测试工程师二
- **角色**: 测试人员
- **邮箱**: qa1@test.com, qa2@test.com

### 技术总监 (TD)
- **账号**: `techdirector`
- **姓名**: 技术总监
- **角色**: 技术总监
- **邮箱**: td@test.com

### 产品总监 (PD)
- **账号**: `productdirector`
- **姓名**: 产品总监
- **角色**: 产品总监
- **邮箱**: pd@test.com

### 质量总监 (QD)
- **账号**: `qualitydirector`
- **姓名**: 质量总监
- **角色**: 质量总监
- **邮箱**: qd@test.com

## 使用说明

1. **登录地址**: 访问您的ZenTao系统登录页面
2. **统一密码**: 所有用户的密码均为 `NewP@ss`
3. **用户ID**: 测试用户的ID范围为 101-112
4. **字符集问题**: 如遇到中文姓名显示乱码，请执行修复脚本：
   ```bash
   misc/fix_user_names.sh
   ```

## 删除测试用户

如需删除这些测试用户，可执行以下SQL：

```sql
-- 删除用户
DELETE FROM zt_user WHERE id BETWEEN 101 AND 112;

-- 删除用户组分配
DELETE FROM zt_usergroup WHERE account IN (
    'productmanager1', 'productmanager2', 
    'projectmanager1', 'projectmanager2', 
    'developer1', 'developer2', 'developer3', 
    'tester1', 'tester2', 
    'techdirector', 'productdirector', 'qualitydirector'
);
```

## 重新添加用户

如果需要重新添加这些测试用户，可以执行：

```bash
cd /home/david/opensource/github/zentaopms
misc/add_test_users.sh
```

或者手动执行SQL文件：

```bash
docker exec zentaopms-db-1 mysql -uroot -pzentao123456 zentao < misc/test_users.sql
```

## 注意事项

1. 这些测试用户仅用于开发和测试环境
2. 生产环境请勿使用这些默认密码
3. 用户已分配对应的角色组权限
4. 手机号码和邮箱为虚拟信息，仅用于测试