<?php
/*
 * GitLab CI 批量登记工时接口（/api.php/v1/worklogs）的配置。
 * Config for the worklogs batch entry of GitLab CI.
 *
 * 部署步骤：
 * 1. 用 openssl rand -hex 16 各生成一个 AccessKey / SecretKey，填入下方；
 * 2. 在禅道中创建服务账号（如 ci_bot），授予「objecteffort->记录」权限（task 工时不查服务账号权限），
 *    或将该账号加为公司管理员（company->admins）；
 * 3. 建议配置 allowedIPs 为 GitLab Runner 的内网 IP，多个用英文逗号分隔；
 * 4. SK 只会明文随请求传输，务必走 HTTPS 或配置 allowedIPs 加固。
 *
 * 注意：本文件含密钥，不得提交到 git 仓库外的公开位置，不得写入日志。
 */
$config->worklog = new stdclass();

$config->worklog->accessKey      = 'caa25a70a8e7a6ee017f85b563b16b2c';       // AccessKey，公开标识，openssl rand -hex 16 生成。
$config->worklog->secretKey      = '74468150f4ce1d2eabade3d5dc9bc30b';       // SecretKey，密钥，openssl rand -hex 16 生成。
$config->worklog->serviceAccount = 'ci_bot'; // 认证通过后以此账号身份执行（权限来源）。
$config->worklog->allowedIPs     = '';       // 可选：允许的来源 IP，逗号分隔，留空 = 不限制。
