# GitLab CI 批量登记禅道工时 API 设计方案（worklogs）

> 状态：方案已定稿，待开发　·　分支：custom_22_2　·　约束：开发过程不动 git，由开发者自行提交
> 认证方式：**AK/SK 直传**（内网，无签名，CI 侧零登录、不算签名）

---

## 目录
1. [背景与目标](#1-背景与目标)
2. [总体架构](#2-总体架构)
3. [接口契约](#3-接口契约)
4. [AK/SK 认证机制](#4-aksk-认证机制)
5. [禅道服务端处理流程](#5-禅道服务端处理流程)
6. [GitLab CI 接入流程](#6-gitlab-ci-接入流程)
7. [实现要点](#7-实现要点)
8. [落库说明](#8-落库说明)
9. [验证清单](#9-验证清单)
10. [注意事项与 FAQ](#10-注意事项与-faq)

---

## 1. 背景与目标

GitLab 流水线触发时，**一次可能携带多条工时**（多个 task/需求/bug 混合，每条各自的 account、工时、描述），回调禅道统一登记。禅道接收后，按每条的 `account` 把工时归属到对应开发者名下。

禅道现状：
- 已有 `recordestimate` 接口，但只支持单条、工时只能记在当前登录用户名下，**不支持指定 account**。
- 本定制版（custom_22_2）已自带 `extension/custom/objecteffort/` 扩展：为 story/bug/requirement 实现了完整工时功能（写 `zt_objecteffort` 表），其 `model.php:240` 的 `buildEffort` **原生支持 `$data->account`**。
- task 工时走原生 `task->recordWorkhour`（写 `zt_effort` 表），归属取决于 `$this->app->user->account`。
- 禅道原生 **无 AK/SK 认证**，仅支持会话认证（会过期）。

**目标**：新增一个统一批量接口 `POST /api.php/v1/worklogs`：
- 跨类型（task/story/bug）、多条、每条指定 account；
- **AK/SK 直传认证**（内网，无签名，CI 侧零登录、永不过期）；
- **事务式提交**（整批成功或整批回滚，失败指出第几条）。

---

## 2. 总体架构

```
┌──────────────┐   header 直传 AK/SK（无签名）  ┌─────────────────────────────┐
│  GitLab CI   │ ──────────────────────────> │  禅道 /api.php/v1/worklogs  │
│              │   X-AK / X-SK               │                             │
│  不需登录    │                             │  worklogEntry:              │
│  不算签名    │                             │   ① 比对 AK/SK（+IP 白名单）│
└──────────────┘                             │   ② 注入服务账号 ci-bot     │
                                             │   ③ 事务内逐条处理：        │
                                             │      task  → recordWorkhour │
                                             │      story → objecteffort   │
                                             │      bug   → objecteffort   │
                                             │   ④ commit / rollback       │
                                             └─────────────────────────────┘
                                                          │
                                           ┌──────────────┴──────────────┐
                                           ▼                             ▼
                                    zt_effort（task）         zt_objecteffort（story/bug）
                                    account = 指定用户        account = 指定用户
```

**核心思想**：权限走服务账号、工时归属走每条的 account，二者分离——"用一个服务账号的权限，把工时分配到每个指定用户"。

---

## 3. 接口契约

### 请求
```
POST  http://你的禅道地址/api.php/v1/worklogs
请求头:
  Content-Type: application/json
  X-AK:         <AccessKey，与 config 比对>
  X-SK:         <SecretKey，与 config 比对>
```

### 入参（JSON body）
```json
{
  "worklogs": [
    {"objectType":"task",  "objectID":123, "account":"zhangsan", "consumed":2,   "work":"完成登录模块", "left":5},
    {"objectType":"story", "objectID":45,  "account":"lisi",     "consumed":1.5, "work":"需求评审"},
    {"objectType":"bug",   "objectID":7,   "account":"wangwu",   "consumed":0.5, "work":"修复提交bug"}
  ]
}
```

| 字段 | 必填 | 说明 |
|---|---|---|
| `objectType` | 是 | task / story / bug |
| `objectID` | 是 | 对象 ID |
| `account` | 是 | 工时归属人（禅道 account，必须存在） |
| `consumed` | 是 | 工时（小时，>0） |
| `work` | 是 | 工作内容 |
| `date` | 否 | 默认今天 |
| `left` | 否 | 剩余工时（task 适用） |
| `execution` | 否 | story 关联多个迭代时必填 |

### 返回

**成功**（全部成功才返回 200）：
```json
{
  "message": "登记成功",
  "created": 3,
  "items": [
    {"index":0, "objectType":"task",  "objectID":123, "account":"zhangsan", "consumed":2},
    {"index":1, "objectType":"story", "objectID":45,  "account":"lisi",     "consumed":1.5},
    {"index":2, "objectType":"bug",   "objectID":7,   "account":"wangwu",   "consumed":0.5}
  ]
}
```

**失败**（任一条出错 → 整批回滚，一条都不写，只返回报错信息）：
```json
{"error":"第2条失败: 账号 lisi2 不存在"}
```

**状态码**：
| 码 | 含义 |
|---|---|
| 200 | 全部登记成功 |
| 400 | 业务校验失败（某条出错，已回滚） |
| 401 | 认证失败（AK/SK 不匹配、来源 IP 不允许） |

---

## 4. AK/SK 认证机制

### 4.1 方案说明
内网通讯，**AK/SK 当作一对账号密码直接校验**，CI 不算签名、不调登录接口：
- CI 把 AK/SK 放进请求头直传；
- 禅道比对 config 里的 AK/SK，一致即放行，再注入服务账号身份。

> 本方案**只在 worklogs 接口生效**，不影响禅道其它接口。

### 4.2 配置（config/worklog.php，新增）
```php
$config->worklog->accessKey      = '随机生成的 AccessKey';   // 公开标识
$config->worklog->secretKey      = '随机生成的 SecretKey';   // 密钥
$config->worklog->serviceAccount = 'ci-bot';                 // 认证通过后以此账号身份执行
$config->worklog->allowedIPs     = '';                        // 可选：允许的来源 IP，逗号分隔，留空=不限制
```
> AK/SK 用 `openssl rand -hex 16` 各生成一个。当前**一对固定密钥**所有 CI 共用；后续若要多项目独立管控，可升级为数据库表多对。

### 4.3 服务端校验代码（worklogEntry）
```php
class worklogEntry extends baseEntry   // 注意是 baseEntry，不是 entry（绕开 isLogon）
{
    public function __construct()
    {
        parent::__construct();                          // baseEntry 解析 requestBody
        $this->dao = $this->loadModel('common')->dao;   // baseEntry 未设 dao，自行补
        $this->authenticate();
    }

    private function authenticate(): void
    {
        $ak = $_SERVER['HTTP_X_AK'] ?? '';
        $sk = $_SERVER['HTTP_X_SK'] ?? '';

        if(!$ak || !$sk) $this->deny('缺少认证参数');
        if(!hash_equals($this->config->worklog->accessKey, $ak)) $this->deny('认证失败');
        if(!hash_equals($this->config->worklog->secretKey, $sk)) $this->deny('认证失败');

        /* 可选：IP 白名单 */
        $allowed = trim((string)$this->config->worklog->allowedIPs);
        if($allowed !== '')
        {
            $ips = array_map('trim', explode(',', $allowed));
            if(!in_array($_SERVER['REMOTE_ADDR'] ?? '', $ips, true)) $this->deny('来源 IP 不允许');
        }

        /* 认证通过 → 注入服务账号身份，后续以其权限执行 */
        $account = $this->config->worklog->serviceAccount;
        $this->app->user = $this->loadModel('user')->getById($account);
        $this->app->user->admin = strpos($this->app->company->admins, ",{$account},") !== false;
    }

    private function deny(string $msg): void
    {
        throw EndResponseException::create($this->sendError(401, $msg));
    }

    public function post() { /* 见 §5 */ }
}
```
> `hash_equals` 常量时间比对（防时序攻击，不用 `==`）。认证失败统一回「认证失败」，不区分 AK 还是 SK 错，避免被枚举。

### 4.4 安全性说明（重要）
- **SK 明文上线传输**：直传方案 SK 会随请求传到禅道。**内网可接受**，但务必做到以下任一条加固：
  - **走 HTTPS**（推荐），或
  - **配置 `allowedIPs` 只放 GitLab Runner 的 IP**，或
  - 在 Nginx/网络层做来源限制。
- 否则内网抓包或日志泄露 SK 后，他人可冒用调用。
- SK 仍只存 GitLab CI/CD Variables（masked）和禅道 `config/worklog.php`，不入 git 仓库、不记日志。

---

## 5. 禅道服务端处理流程

```
请求到达 www/api.php
    │
    ▼
① 路由：config/apiv1.php 里 $routes['/worklogs']='worklog'
   → 加载 api/v1/entries/worklog.php → 实例化 worklogEntry
    │
    ▼
② 构造期 AK/SK 校验（见 §4.3）             ✗ → 401
    │
    ▼
③ post()：$items = requestBody->worklogs
   非数组/空                                → 400「worklogs 不能为空」
    │
    ▼
④ $this->dao->beginTransaction()
    │
    ▼
⑤ 遍历每条（i = 序号）：
   ┌─ 通用校验 ─────────────────────────────────────────┐
   │  objectType ∈ {task,story,bug}  ✗ → 回滚+400「第i条: 类型非法」      │
   │  user->getById(account) 存在    ✗ → 回滚+400「第i条: 账号不存在」    │
   │  目标对象存在 & 未关闭          ✗ → 回滚+400「第i条: 对象不存在/已关闭」│
   └─────────────────────────────────────────────────────┘
   ┌─ 分发写入 ─────────────────────────────────────────┐
   │  【task】                                           │
   │    备份 $orig=$this->app->user                      │
   │    切到目标用户 $this->app->user=user->getById(account)│
   │    setPost(date/consumed/left/work)                │
   │    loadController('task','recordWorkhour')         │
   │       ->recordWorkhour(objectID)                   │
   │    恢复 $this->app->user=$orig                      │
   │    → 写入 zt_effort（account=该用户）               │
   │                                                     │
   │  【story / bug】                                     │
   │    构造 $data{account,consumed,work,date,left?,execution?} │
   │    objecteffort->record(objectType, objectID, $data)│
   │    → 写入 zt_objecteffort（account=该用户）         │
   └─────────────────────────────────────────────────────┘
   每条写入后 dao::isError()?  ✗ → rollBack + 400「第i条: {原因}」
    │  （全部条目都成功才继续）
    ▼
⑥ $this->dao->commit()
    │
    ▼
⑦ send(200, {message, created, items})
```

**为什么 task 要切用户、story/bug 不用？**
- task 的 `recordWorkhour` 写工时时归属人取 `$this->app->user->account`（`module/task/tao.php:184`），需临时切到目标 account。
- story/bug 走 objecteffort，`record()` 直接接受 `$data->account`（`objecteffort/model.php:240`），传谁归谁，不用切。

**事务的意义**：任一条失败立即 `rollBack()`，前面已写入的也撤销，对外即"整批没写成"——这正是"失败时一条都不写、还看得出第几条"。

---

## 6. GitLab CI 接入流程

### 6.1 一次性配置
**禅道侧**（管理员）：
1. 生成 AK/SK，写入 `config/worklog.php`（见 §4.2）。
2. 建服务账号 `ci-bot`，授予权限：`任务→记录工时(task->recordWorkhour)` + `objecteffort→记录`（或设管理员）。
3. 把 AK/SK 交给 CI 侧。
4. （建议）配置 `allowedIPs` 为 GitLab Runner 的 IP。

**GitLab 侧**：
5. Settings → CI/CD → Variables 加 `WL_AK`、`WL_SK`（SK 勾选 **Masked**）。
6. 维护人员映射表（GitLab 提交者 → 禅道 account），供脚本查表。

### 6.2 每次流水线
```
代码提交/合并 ──> Pipeline 触发
    │
    ▼
① 收集本次工时数据（谁、关联哪个 task/story/bug、耗了几小时）
    │
    ▼
② 查人员映射表，GitLab 用户名 → 禅道 account
    │
    ▼
③ 组装 body = {"worklogs":[ {objectType,objectID,account,consumed,work}, ... ]}
    │
    ▼
④ 带 X-AK / X-SK 头，POST /api.php/v1/worklogs（不算签名）
    │
    ▼
⑤ 看返回：
   ├ 200 → 成功，工时已记到各 account 名下
   └ 400 → 整批未写入；error 里"第N条失败: 原因"，修正后重跑
```

### 6.3 CI 完整脚本
```bash
#!/usr/bin/env bash
set -euo pipefail

ZENTAO="http://你的禅道地址"
AK="${WL_AK}"; SK="${WL_SK}"          # 来自 CI/CD Variables

# ①②③ 组装请求体（实际按流水线数据动态生成）
BODY=$(cat <<EOF
{"worklogs":[
  {"objectType":"task","objectID":${TASK_ID},"account":"${ACCOUNT}","consumed":${HOURS},"work":"${CI_COMMIT_MESSAGE}"}
]}
EOF
)

# ④ 调用（直接传 AK/SK，无需算签名）
RESP=$(curl -s -w "\n%{http_code}" -X POST "$ZENTAO/api.php/v1/worklogs" \
  -H "Content-Type: application/json" \
  -H "X-AK: $AK" -H "X-SK: $SK" \
  -d "$BODY")

# ⑤ 处理返回
CODE=$(echo "$RESP" | tail -1); BODY_RESP=$(echo "$RESP" | sed '$d')
if [ "$CODE" = "200" ]; then echo "✅ 工时登记成功：$BODY_RESP"
else echo "❌ 登记失败（HTTP $CODE）：$BODY_RESP"; exit 1; fi
```

---

## 7. 实现要点

### 7.1 文件清单
- 新增 `api/v1/entries/worklog.php`（`worklogEntry extends baseEntry`，含 AK/SK 校验 + post 批量逻辑）
- 新增 `config/worklog.php`（accessKey / secretKey / serviceAccount / allowedIPs）
- 修改 `config/apiv1.php`（加一行 `$routes['/worklogs'] = 'worklog';`）
- 不动：objecteffort 扩展、task 模块、禅道其它接口（全部复用现有方法）

### 7.2 关键技术点与坑
- **worklogEntry 必须 extends baseEntry**（不是 entry），绕开 entry 构造里的 `isLogon` 检查；baseEntry 未设 `$this->dao`，构造里自行 `$this->dao = $this->loadModel('common')->dao`。
- **事务兼容**：禅道 DAO 单例，task/objecteffort 共用同一连接，`beginTransaction/commit/rollBack` 跨方法生效。实现时**必须验证** `task->recordWorkhour` 内部不会自行 commit；若会，退化为"两遍扫描"（先全量校验、再全量写入），校验阶段挡掉绝大多数错误。
- **task 切用户**：参考 `user->su()`（`module/user/model.php:2994`）；recordWorkhour 链路只读 `$this->app->user->account`，切 account 即归属正确。切用户时把 `$orig->admin` 带给目标用户，避免 `canOperateEffort` 误判。
- **story/bug 复用 objecteffort**：内部已处理 closed 不可记、consumed/date 校验、product/project/execution 自动填充、story 多迭代需 execution（`buildEffort:160-193`）、action 动态、统计刷新。

---

## 8. 落库说明
- task 工时 → `zt_effort` 表（`account` = 指定用户）
- story/bug 工时 → `zt_objecteffort` 表（`account` = 指定用户，`createdBy` = 服务账号 ci-bot）
- 两套表是本定制版现状，禅道报表对两者的统计口径可能不同，上线后需观察。

---

## 9. 验证清单

**AK/SK 认证**：
- [ ] 正确 AK/SK → 200 通过
- [ ] 缺 X-AK 或 X-SK → 401「缺少认证参数」
- [ ] AK 错 / SK 错 → 401「认证失败」
- [ ] 配了 allowedIPs 时，非白名单 IP → 401「来源 IP 不允许」

**批量逻辑**：
- [ ] 正常批量（混合 task/story/bug，不同 account）→ 200，查 `zt_effort`/`zt_objecteffort` 确认 account/consumed 写入正确
- [ ] 故意第2条 account 不存在 → 400「第2条失败...」，确认第1、3条**未写入**（事务回滚）
- [ ] 故意 objectID 不存在 / consumed≤0 / 对象已 closed → 400 指出第N条
- [ ] 前端确认：task 工时页、需求/bug 工时明细页归属显示为目标用户

---

## 10. 注意事项与 FAQ

1. **story 多迭代**：需求关联多个执行时 objecteffort 要求传 `execution`，否则该条 400；建议 CI 侧从项目映射表补 execution。
2. **事务兼容**：若被 recordWorkhour 内部 commit 破坏，实现时验证并退化为两遍扫描（见 §7.2）。
3. **AK/SK 保管 + 上线风险**：SK 在本方案中**明文随请求传输**（与签名方案不同）。内网务必走 HTTPS 或配置 `allowedIPs`，否则抓包/日志泄露 SK 后他人可冒用。SK 只存 GitLab CI/CD Variables（masked）和禅道 `config/worklog.php`，不入 git 仓库、不记日志。换密钥：改 config + 同步 CI Variables。
4. **CI 要登录吗 / 要算签名吗？** 都不要。AK/SK 直传，CI 把 AK/SK 放 header 调用即可，没有登录环节、不算签名、永不过期。
5. **只有持 AK/SK 的能用？** 是。worklogs 只认 AK/SK，对不上就 401；其它禅道接口不受本方案影响。
6. **项目映射表 / 人员映射表**（GitLab 项目→禅道项目ID、GitLab 提交者→禅道 account）既可在 CI 脚本侧配置，也可在禅道后台维护。禅道后端新增 `zt_gitlabuser` 映射表（后台「人员管理→GitLab用户」管理），worklogs 接口收到 `account` 后会先查该表：命中则转成对应禅道账号，未命中则按原值处理（兼容直接传禅道账号）。
