# 禅道自定义状态流转工作流 PRD/Spec

> 本文档定义禅道（ZenTaoPMS）开源版"自定义状态流转工作流"特性的产品需求与技术实现规范，覆盖业务需求（ER）、用户需求（UR）、研发需求（SR）、任务（Task）、缺陷（Bug）共 5 类对象的状态机自定义能力。供后续开发直接照此实现。

| 字段 | 值 |
|---|---|
| **文档版本** | v1.0 |
| **适用分支** | `orig_22_2`（upstream 22.2 基线） |
| **目标读者** | 实现该特性的 PHP 开发、QA、产品经理 |
| **最后更新** | 2026-07-03 |
| **作者** | Claude 与产品团队共建 |
| **审核状态** | 待评审 |

---

## 目录

- [1. 背景与目标](#1-背景与目标)
- [2. 范围与分期](#2-范围与分期)
- [3. 架构总览](#3-架构总览)
- [4. 数据模型](#4-数据模型)
- [5. 核心 API 设计](#5-核心-api-设计)
- [6. 核心模块切入点](#6-核心模块切入点)
- [7. UI 规范](#7-ui-规范)
- [8. 权限与审计](#8-权限与审计)
- [9. 自定义状态机制](#9-自定义状态机制)
- [10. 安装与迁移](#10-安装与迁移)
- [11. 测试策略](#11-测试策略)
- [12. 验收标准](#12-验收标准)
- [13. 风险与缓解](#13-风险与缓解)
- [14. 时间表（粗）](#14-时间表粗)
- [15. Phase 2 扩展点](#15-phase-2-扩展点)
- [16. 附录](#16-附录)

---

## 1. 背景与目标

### 1.1 业务背景

禅道付费的企业版/旗舰版提供了完整的"工作流"自定义能力，包括自定义状态、自定义字段、自定义动作按钮、BPMN 编排等。开源版用户长期反馈希望获得一个"轻量级但够用"的状态流转自定义能力，主要诉求集中在：

1. **状态机自定义**：客户希望根据自身研发流程调整 Bug/Story/Task 的状态转移规则，例如：
   - 增加"待客户确认"、"QA 验证中"、"已发布待回归"等业务自定义状态
   - 禁用某些不需要的状态转移（如"已解决的 Bug 不允许直接重新打开"）
   - 强制某些转移必须填写评论、必须由特定角色操作
2. **产品级差异**：同一禅道实例服务多个产品线时，不同产品的研发流程不同，希望按产品独立配置
3. **可视化配置**：管理员希望在后台看到流程图，而不是改代码或 SQL

### 1.2 与禅道企业版的边界

| 能力 | 本 spec | 禅道企业版（参考） |
|---|---|---|
| 状态/转移自定义 | ✅ | ✅ |
| 自定义状态 | ✅ | ✅ |
| 产品级覆盖 | ✅ | ✅ |
| 角色授权 + 强制评论 | ✅ | ✅ |
| 自定义动作按钮 | ✅ | ✅ |
| 自定义字段 | ❌ Phase 2 | ✅ |
| 表单视图联动（按状态隐藏/必填字段） | ❌ Phase 2 | ✅ |
| BPMN 编排/条件分支 | ❌ Phase 3+ | ✅ |
| 表单视图设计器 | ❌ 永不做 | ✅ |

**不做 BPMN 引擎**：本 spec 只做"是否允许此状态变迁"的 gate + 目标状态解析，不实现业务编排引擎。

### 1.3 设计目标

1. **覆盖 5 类对象**：epic（业务需求）、requirement（用户需求）、story（研发需求）、bug、task
2. **重量版深度**：支持自定义状态，不只是默认状态间的转移规则
3. **产品级覆盖**：系统默认 + 产品级两层，产品级完全覆盖（不合并）
4. **可视化后台**：管理员在 Web 后台配置，无需改代码
5. **零 hook**：所有约束点显式写在核心 model 方法入口，不依赖 `extension/custom/*/ext/*/hook/*`
6. **结构性反 bug**：业务方法不决定目标状态，目标状态完全由工作流模块解析；启用开关与配置同页保存
7. **向前兼容 Phase 2**：数据模型与 API 预留表单联动、自定义字段的扩展点

### 1.4 非目标

- **不覆盖 testcase**：测试用例的状态流转较简单且独立，本次不实现
- **不做项目级工作流**：项目维度不独立配置，仅跟随产品
- **不做流程引擎**：不实现条件分支、字段联动执行、自动化触发
- **不重构现有业务逻辑**：仅在状态变更点切入校验，不动原有代码逻辑
- **不引入新前端框架**：继续使用 ZUI3 / Zin / Mermaid

---

## 2. 范围与分期

### 2.1 Phase 1 范围（本 spec 必须实现）

| 能力 | 说明 |
|---|---|
| 状态转移规则自定义 | 配置"什么状态可以经过什么动作到什么状态" |
| 自定义状态 | 用户可在 5 类对象的默认状态之外新增状态 |
| 自定义动作按钮 | 用户可定义带自定义标签的按钮绑定到转移 |
| 产品级覆盖 | 系统默认 + 产品级两层 |
| 角色授权 | 每条转移可配置允许的角色/账号 |
| 强制评论 | 每条转移可配置是否强制填写评论 |
| 启用开关 | 总开关，可灰度 |
| 可视化后台 | 后台配置页 + Mermaid 流程图预览 |
| 详情页流程图 | 5 类对象详情页底部内嵌只读流程图 |
| 按钮可见性联动 | `isClickable` 根据配置隐藏不允许的按钮 |
| 表单选择器联动 | create/edit/change 表单的 status picker 选项来自配置 |
| 数据迁移 | 从 `zt_workflowflowchart` 自动迁移（若存在） |

### 2.2 Phase 2 范围（未来 spec，本 spec 仅占位）

| 能力 | 说明 |
|---|---|
| 表单视图联动 | 按状态控制字段的显示/隐藏/必填/只读/默认值 |
| 自定义字段 | 用户可新增字段（text/number/date/select/...） |

### 2.3 Phase 3+ 可选能力（视客户需求）

| 能力 | 说明 |
|---|---|
| 简单事件规则 | 转移后触发 webhook / 改字段值（不做可视化编排） |
| BPMN 编排 | 条件分支、并行网关、自动化任务 |

### 2.4 用户故事（典型场景）

#### 故事 1：金融客户增加"待客户确认"状态

> 作为金融产品线的研发负责人，我希望在 SR 流程里增加"待客户确认"状态。当 SR 评审通过后，先进入"待客户确认"而不是直接 active；客户确认后再 active。

**实现路径**：
1. 后台 → 状态流转 → Story → 全局默认
2. 新增状态 `custom_customer_confirm`（Label：待客户确认，Category：normal，Color：橙色）
3. 编辑转移：`reviewing → active (via review/pass)` 改为 `reviewing → custom_customer_confirm (via review/pass)`
4. 新增转移：`custom_customer_confirm → active (via custom_confirm)`，配置自定义按钮"客户已确认"

#### 故事 2：核心产品限制 Bug 重开

> 作为核心产品的 QA 负责人，我希望已解决的 Bug 不能由报告人直接重开，必须由 QA Lead 审核后才能重开。

**实现路径**：
1. 后台 → 状态流转 → Bug → 产品：核心产品
2. 配置 `resolved → active (via activate)` 的 roles 仅包含 `qa_lead`
3. 报告人在 Bug 详情页看不到"激活"按钮，QA Lead 可以看到

#### 故事 3：任务完成必须填实际工时

> 作为项目经理，我希望任务标记完成时必须填写"实际消耗工时"评论。

**实现路径**：
1. 后台 → 状态流转 → Task → 全局默认
2. 编辑转移 `doing → done (via finish)` 的 requireComment = true
3. 完成按钮点击时弹出评论框，强制填写才能提交

#### 故事 4：需求评审多分支

> 作为产品负责人，我希望需求评审可以有"通过/拒绝/需澄清/撤回"4 个分支，且每个分支进入不同状态。

**实现路径**：
1. 后台 → 状态流转 → Story
2. 配置 4 条转移：
   - `reviewing → active (via review, branch: pass)`
   - `reviewing → closed (via review, branch: reject, requireComment: true)`
   - `reviewing → draft (via review, branch: clarify)`
   - `reviewing → reviewing (via review, branch: revert)`
3. 业务侧 `story->review()` 调 `transition(branch: $result)` 自动匹配

#### 故事 5：A/B 产品线不同流程

> 同一禅道实例服务 A、B 两个产品线。A 用敏捷（简化状态），B 用瀑布（完整状态）。

**实现路径**：
1. 全局默认配置为瀑布流程
2. 产品级为产品 A 配置简化流程（覆盖全局）
3. 产品 B 不配置，自动继承全局

---

## 3. 架构总览

### 3.1 命名与边界

- **模块名**：`statetransition`（避免与禅道企业版 `workflow` 概念混淆）
- **特性名**：状态流转自定义（State Transition Customization）
- **覆盖对象**：epic、requirement、story、bug、task 共 5 类（testcase 不在范围）
- **抽象层**：epic/requirement 是 story 的瘦封装（model 继承 + control 转发），统一通过 story 切入；bug/task 各自直接切入

### 3.2 模块拓扑

```
┌──────────────────────── 核心层（native）────────────────────────┐
│                                                                 │
│  module/statetransition/  ← 新增核心模块                          │
│    config/config.php           5 类对象、action 白名单、默认定义   │
│    model.php                   核心：CRUD/解析/执行               │
│    tao.php                     校验/规范化等纯函数                │
│    zen.php                     UI 辅助/选择器数据                 │
│    control.php                 admin UI 路由                      │
│    lang/{zh-cn,en,...}         多语言                             │
│    ui/browse.html.php          ZUI3 后台页（Zin）                 │
│    ui/createstatus.html.php    新增状态 modal                     │
│    ui/createtransition.html.php 新增转移 modal                   │
│    css/browse.ui.css, js/browse.ui.js                            │
│    db/{install,uninstall}.sql                                    │
│    test/{model,tao,zen,integration,ui}/...                      │
│                                                                 │
│  module/story/{model,tao}.php     ← 11 个状态变更方法切入         │
│  module/bug/{model,tao}.php       ← 6 个方法切入                  │
│  module/task/{model,tao}.php      ← 11 个方法切入                 │
│                                                                 │
│  module/custom/                   后台菜单入口集成                │
│  extension/custom/admin/ext/config/statetransition.php  菜单挂载  │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

### 3.3 调用时序（以 `story->review()` 为例）

```
HTTP POST /story-review-{id}
    │
    ▼
story/control->review
    │
    ▼
story/model->review($storyID, $story, $comment)
    │
    ├─ $old = $this->getById($storyID)
    ├─ $result = $this->getReviewResult(...)   // pass|reject|clarify|revert
    │
    ├─ $decision = $this->loadModel('statetransition')
    │     ->transition(
    │         objectType : 'story',
    │         productID  : $old->product,
    │         objectID   : $storyID,
    │         fromStatus : $old->status,
    │         action     : 'review',
    │         branch     : $result,         // ← 业务侧只提供分支语义
    │         comment    : $comment
    │     );
    │
    ├─ if (!$decision->ok) {
    │     dao::setError($decision->errorKey, $decision->errorMessage);
    │     return false;                      // 写库前中止
    │ }
    │
    ├─ $newStatus = $decision->toStatus;     // ← 来自配置，业务不决定
    │
    ├─ ... 原 review 业务逻辑，使用 $newStatus 写库 ...
    ├─ $this->loadModel('action')->create('story', $storyID, 'review', $comment);
    │
    └─ return true;
```

### 3.4 四个设计原则

1. **入口归一**：每个状态变更 action 在 model 层只有一个入口；control/zen/tao 不能直接改 status 字段，必须经此入口。
2. **决策与执行合一**：`transition()` 同时完成目标解析 + 规则校验，业务方法只能接受结果，没有"绕过校验"的路径。
3. **零 hook**：所有调用显式写在核心方法体内，禁用 `extension/custom/*/ext/*/hook/*.statetransition.php`。
4. **可灰度**：定义 `enabled=0` 时仅作可视化展示，不限制任何变迁；定义存在但禁用时，`transition()` 直接返回 `wasUnrestricted=true` + 业务侧默认目标。

### 3.5 与禅道企业版的边界

详见 §1.2。

---

## 4. 数据模型

### 4.1 主表 `zt_workflow_definition`

| 字段 | 类型 | 说明 |
|---|---|---|
| `id` | mediumint unsigned PK AI | |
| `scope` | enum('global','product') NOT NULL DEFAULT 'global' | global=系统默认，product=产品级覆盖 |
| `productID` | mediumint unsigned NOT NULL DEFAULT 0 | scope=product 时为产品 ID；scope=global 必须=0 |
| `objectType` | varchar(30) NOT NULL | `epic` / `requirement` / `story` / `bug` / `task` |
| `name` | varchar(100) NOT NULL DEFAULT '' | 显示名（如"默认 SR 流程"） |
| `enabled` | enum('0','1') NOT NULL DEFAULT '0' | 总开关；0=仅可视化，1=启用约束 |
| `version` | int unsigned NOT NULL DEFAULT 1 | 每次保存 +1，乐观锁 + 审计 |
| `definition` | mediumtext NOT NULL | JSON 结构（见 §4.2） |
| `createdBy` | varchar(30) NOT NULL DEFAULT '' | |
| `createdDate` | datetime DEFAULT NULL | |
| `editedBy` | varchar(30) NOT NULL DEFAULT '' | |
| `editedDate` | datetime DEFAULT NULL | |

**索引**：
- `UNIQUE KEY uk_scope_obj (scope, productID, objectType)` — 同 scope+product+objectType 仅一份
- `KEY idx_obj_lookup (objectType, productID, enabled)` — 运行时查找"产品定义 → 全局回退"用

### 4.2 JSON `definition` 结构

```jsonc
{
  "schemaVersion": 1,
  "statuses": [
    {
      "key": "draft",                    // 状态 key（数据库 status 字段存的值）
      "label": {                         // 多语言显示名
        "zh_cn": "草稿",
        "en": "Draft"
      },
      "category": "normal",              // normal | abnormal | terminal
      "color": "#999999",                // UI 状态徽章色
      "isSystem": true,                  // true=禅道内置不可删除；false=用户自定义
      "isEntry": true,                   // 是否允许作为创建时的入口状态

      // Phase 2 占位字段（schemaVersion=1 时忽略）
      "fieldRules": {}
    }
  ],
  "transitions": [
    {
      "key": "draft-to-reviewing-via-submitreview",  // 同 def 内唯一
      "fromStatus": "draft",
      "toStatus": "reviewing",
      "action": "submitreview",                       // 见 §4.4 action 白名单
      "branch": null,                                 // 多分支 action 时必填
      "label": {
        "zh_cn": "提交评审",
        "en": "Submit for Review"
      },
      "roles": [],                                    // 允许的角色 key 数组，空=不限
      "accounts": [],                                 // 允许的账号数组，空=不限
      "requireComment": false,                        // 是否强制填写评论
      "enabled": true,

      // 自定义动作按钮（Phase 1 实现）
      "isCustom": false,                              // true=作为自定义按钮渲染到 UI
      "buttonLabel": null,                            // 自定义按钮文字（如"标记阻塞"）
      "buttonIcon": null,                             // ZUI3 图标名
      "buttonOrder": 0,                               // 工具栏排序
      "buttonGroup": "primary",                       // primary | more | danger

      // Phase 2/3 占位字段
      "sideEffects": [],
      "condition": null
    }
  ],
  "entries": ["draft"]                                // 入口状态 key 列表（与 statuses[].isEntry 二选一同步）
}
```

**字段约束**：

- `statuses[].key`：`^[a-z][a-z0-9_]{1,29}$`；同一份定义内唯一；系统 key（见 §4.3）不可被自定义状态覆盖
- `statuses[].category`：枚举 `normal` / `abnormal` / `terminal`，影响 UI 徽章样式与统计报表归类
- `statuses[].color`：十六进制 `#RRGGBB`
- `statuses[].isEntry=true`：必须同时出现在 `entries[]` 中（保存时自动同步）
- `transitions[].key`：同一份定义内唯一
- `transitions[].fromStatus` / `toStatus`：必须引用 `statuses[].key`
- `transitions[].action`：必须在该 objectType 的 action 白名单（见 §4.4）中，或匹配 `^custom_[a-z0-9_]+$`
- `transitions[].branch`：当且仅当某 `(fromStatus, action)` 存在多条转移时必填；单分支时建议 null
- `transitions[].roles[]` / `accounts[]`：去重 trim 后的字符串数组，可同时为空（=不限）
- `transitions[].requireComment`：布尔
- `transitions[].enabled`：布尔，默认 true
- `transitions[].isCustom=true`：`buttonLabel` 必填，`action` 必须以 `custom_` 开头

### 4.3 状态 key 命名规则

- 字符集：`^[a-z][a-z0-9_]{1,29}$`
- **系统 key 白名单**（不可被自定义状态覆盖，但 label/color/category 可编辑）：

  | objectType | 系统 key |
  |---|---|
  | story / epic / requirement | `draft`, `reviewing`, `active`, `changing`, `closed` |
  | bug | `active`, `resolved`, `closed` |
  | task | `wait`, `doing`, `done`, `pause`, `cancel`, `closed` |

- 用户自定义状态只能新增 key，**不能复用上述 key**（避免与代码硬编码冲突）
- 删除自定义状态前校验：
  1. 当前 objectType 是否有对象仍处此状态（`SELECT COUNT(*) FROM zt_{object} WHERE status=?`）
  2. 是否有 transition 引用此状态（fromStatus / toStatus）
  3. 任一命中 → 阻止删除，返回 `statusInUse` 错误 + 具体阻塞原因

### 4.4 各 objectType 支持的 action（白名单）

| objectType | 内置 action |
|---|---|
| epic / requirement / story | `submitreview`, `review`, `change`, `recallreview`, `recallchange`, `close`, `activate` |
| bug | `resolve`, `close`, `activate` |
| task | `start`, `restart`, `pause`, `finish`, `close`, `cancel`, `activate` |

**自定义按钮的 action 命名规则**：`custom_{key}`（如 `custom_mark_blocked`），不与内置 action 冲突，且必须在 transitions 数组内 key 唯一。

### 4.5 产品级覆盖解析规则

```
getEffectiveDefinition(objectType, productID):
    1. product 行 = lookup(scope='product', productID, objectType, enabled='1')
    2. global 行 = lookup(scope='global', productID=0, objectType, enabled='1')
    3. if product 行存在: return product 行   (产品级完全覆盖全局，不做 merge)
       else if global 行存在: return global 行
       else: return null                      (无约束)
```

**关键决策：产品级完全覆盖，不合并** —— 避免半覆盖导致的状态错乱（用户在产品级改了 statuses 但 transitions 仍引用全局已删除的 status）。如果管理员要"基于全局微调"，提供"复制全局到产品"按钮。

### 4.6 与现有 status 字段兼容

- `zt_story.status` / `zt_bug.status` / `zt_task.status` 已经是 `varchar(30)`，**无需改 schema**
- 自定义状态直接以 key 存入；查询时通过工作流模块查 label/color
- **关键的代码改动点**：所有 `lang->{module}->statusList` 静态使用的地方（featureBar、列表筛选、统计报表），改为运行时调 `statetransition->getStatusList($objectType, $productID)` 返回合并了自定义状态的列表

### 4.7 数据量与缓存

- 每个 definition 平均大小约 5-15 KB（10 个状态、20 条转移）
- 5 类对象 × (1 global + N product) 行；即使 100 个产品，最多 500 行，全表 < 10 MB
- 缓存策略：进程内 static cache，key 为 `"{scope}:{productID}:{objectType}"`，每次保存 bump version → 自动失效

### 4.8 Phase 2 占位字段

- `statuses[].fieldRules` —— Phase 2 写入 `{字段名: {visible, required, readonly, defaultValue}}`
- `transitions[].sideEffects` —— Phase 3 写入触发动作（webhook / 字段更新 / 创建关联对象）
- `transitions[].condition` —— Phase 3 写入前置条件表达式
- 单独的 `zt_workflow_field` 表 —— Phase 2 自定义字段定义（不在本 spec 范围）

`schemaVersion` 字段守护向前兼容：未来 schema 升级时通过版本号判断是否需要迁移。

---

## 5. 核心 API 设计

### 5.1 API 三层分组

```
┌─────────────────────────────────────────────────────┐
│ 高层 API（业务代码调用）                              │
│   transition()        主入口：解析+校验+返回目标      │
│   assertStatusChange() 自由状态变更（update/batch）   │
│   isActionAllowed()   UI 按钮可见性                   │
├─────────────────────────────────────────────────────┤
│ UI/辅助 API（视图层调用）                             │
│   getStatusList() / getActionList()                 │
│   getCustomButtons() / renderFlowHtml()             │
│   renderMermaid() / getEntryStatusOptions()         │
├─────────────────────────────────────────────────────┤
│ 管理 API（后台）                                     │
│   saveDefinition() / getDefinition()                │
│   copyDefinition() / resetToDefault()               │
│   validateDefinition() / normalizeDefinition()      │
├─────────────────────────────────────────────────────┤
│ 内部低层（private/protected，不对外）                 │
│   resolveDefinition() / findTransition()            │
│   checkActor() / checkComment()                     │
└─────────────────────────────────────────────────────┘
```

### 5.2 主入口：`transition()`

```php
/**
 * 解析并校验一次状态转移。
 *
 * @param  string  $objectType    epic|requirement|story|bug|task
 * @param  int     $productID     产品 ID（0 表示无产品上下文，按全局处理）
 * @param  int     $objectID      对象 ID（仅用于错误信息与日志）
 * @param  string  $fromStatus    当前状态
 * @param  string  $action        动作 key（内置白名单 或 custom_xxx）
 * @param  ?string $branch        业务侧分支（如 review 的 pass/reject/clarify）
 *                                 - 单分支 action 传 null
 *                                 - 多分支 action 必传，否则报 ambiguousBranch
 * @param  string  $comment       评论内容（requireComment=true 时必填）
 * @param  ?object $actor         操作者（默认 global $app->user）
 * @return transitionDecision      决策对象（见 §5.3）
 */
public function transition(
    string  $objectType,
    int     $productID,
    int     $objectID,
    string  $fromStatus,
    string  $action,
    ?string $branch   = null,
    string  $comment  = '',
    ?object $actor    = null
): transitionDecision
```

### 5.3 决策返回对象 `transitionDecision`

```php
<?php
declare(strict_types=1);

final class transitionDecision {
    public bool   $ok;              // 是否通过
    public string $toStatus;        // ok=true: 解析出的目标状态；ok=false: ''
    public ?array $transition;      // ok=true: 匹配到的 transition 行；ok=false: null
    public string $errorKey;        // ok=false: 错误 i18n key
    public string $errorMessage;    // ok=false: 已翻译文案
    public bool   $wasUnrestricted; // true: 定义未启用或不存在，本次未约束（用于审计）

    public function __construct(array $args) {
        $this->ok              = $args['ok'] ?? false;
        $this->toStatus        = $args['toStatus'] ?? '';
        $this->transition      = $args['transition'] ?? null;
        $this->errorKey        = $args['errorKey'] ?? '';
        $this->errorMessage    = $args['errorMessage'] ?? '';
        $this->wasUnrestricted = $args['wasUnrestricted'] ?? false;
    }
}
```

**关键设计**：`$toStatus` 始终来自配置，业务方法只能消费它，不能影响它。`$wasUnrestricted=true` 时 `toStatus` 取业务侧默认值（fallback），便于灰度上线。

### 5.4 自由变更：`assertStatusChange()`

用于 `update` / 批量编辑 / API 直接改 status 字段（不经过 action）的场景：

```php
public function assertStatusChange(
    string  $objectType,
    int     $productID,
    int     $objectID,
    string  $fromStatus,
    string  $toStatus,
    string  $comment = '',
    ?object $actor = null
): transitionDecision
```

匹配规则：在 `transitions[]` 中找 `fromStatus` + `toStatus` 匹配且 `enabled=true` 的边（action 任意），第一条命中即决策。actor/comment 规则同 `transition()`。

### 5.5 按钮可见性：`isActionAllowed()`

```php
public function isActionAllowed(
    string  $objectType,
    int     $productID,
    string  $fromStatus,
    string  $action,
    ?object $actor = null
): bool
```

只做 actor 检查（不检查 comment，因为按钮可见性不该要求"先填评论才能看到按钮"）。被各业务模块的 `isClickable()` 调用。

> 提供 static 包装 `isActionAllowedStatic(...)`，供 `isClickable()` 静态上下文使用。

### 5.6 多分支语义（解决 `review()` 硬编码 bug）

**问题场景**：`story->review()` 一个 action 对应多种结果（pass/reject/clarify/revert），每种结果对应不同目标状态。本 spec 的设计：在 transition 定义里加 `branch` 字段；调用方传 `branch` 参数；工作流模块按 `(fromStatus, action, branch)` 三元组匹配。

```jsonc
// story 工作流定义片段（reviewing 状态的 4 条转移）
"transitions": [
  {"fromStatus":"reviewing","toStatus":"active","action":"review","branch":"pass","label":{"zh_cn":"评审通过"},...},
  {"fromStatus":"reviewing","toStatus":"draft","action":"review","branch":"clarify","label":{"zh_cn":"需要澄清"},...},
  {"fromStatus":"reviewing","toStatus":"closed","action":"review","branch":"reject","label":{"zh_cn":"拒绝"},...},
  {"fromStatus":"reviewing","toStatus":"reviewing","action":"review","branch":"revert","label":{"zh_cn":"撤回评审"},...}
]
```

```php
// story/model.php::review()
$result = $this->getReviewResult(...);  // 'pass' | 'reject' | 'clarify' | 'revert'

$decision = $this->loadModel('statetransition')->transition(
    objectType : 'story',
    productID  : $old->product,
    objectID   : $storyID,
    fromStatus : $old->status,
    action     : 'review',
    branch     : $result,        // ← 业务侧只提供分支语义
    comment    : $comment
);
if (!$decision->ok) {
    dao::setError($decision->errorKey, $decision->errorMessage);
    return false;
}
$newStatus = $decision->toStatus;   // 来自配置，可能是 'closed' 也可能是 'draft'
// 业务代码用 $newStatus，禁止再读 form->status
```

**单分支 action**：`branch` 传 null。匹配规则按 `(fromStatus, action)` 二元组。

**多分支未指定**：若同一 `(fromStatus, action)` 存在多条 enabled 转移但调用方未传 branch，返回 `ambiguousBranch` 错误。

### 5.7 内置默认分支映射（业务侧约定）

为保持业务代码可读性，约定以下 action 的默认 branch 取值（业务侧硬编码 branch 串，但**不硬编码 target**）：

| Action | 业务侧 branch 取值 |
|---|---|
| submitreview | null（单分支） |
| review | `pass` / `reject` / `clarify` / `revert` |
| change | null |
| recallreview / recallchange | null |
| close | `done` / `rejected`（按 closedReason） |
| activate | null |
| resolve | null |
| start / restart / pause / finish / cancel | null |
| 自定义按钮 | null（单分支） |

### 5.8 错误码与 i18n key

| errorKey | zh-cn | en |
|---|---|---|
| `objectTypeInvalid` | 对象类型无效 | Invalid object type |
| `definitionNotFound` | 未配置工作流定义（无约束） | No workflow definition |
| `definitionDisabled` | 工作流未启用（无约束） | Workflow disabled |
| `transitionNotFound` | 当前状态没有匹配的转移规则 | No matching transition |
| `transitionDisabled` | 该转移已被禁用 | Transition disabled |
| `ambiguousBranch` | 同一动作存在多条分支，未指定 branch | Ambiguous branch |
| `actorDenied` | 当前用户不在允许的操作者范围 | Actor not allowed |
| `commentRequired` | 此操作必须填写评论 | Comment required |
| `customStatusInvalid` | 自定义状态不合法 | Invalid custom status |
| `statusInUse` | 状态使用中，无法删除 | Status in use |
| `adminOnly` | 仅管理员可保存 | Admin only |
| `versionConflict` | 定义已被他人修改，请刷新后重试 | Version conflict |
| `invalidDefinition` | 定义 JSON 不合法 | Invalid definition JSON |

### 5.9 缓存策略

```php
// model.php 内 static 缓存，请求生命周期内有效
private static array $defCache  = [];   // key: "{scope}:{productID}:{objectType}" => DefinitionRow|null
private static array $listCache = [];   // key: "{objectType}:{productID}" => statusList array
```

`saveDefinition()` / `resetToDefault()` / `copyDefinition()` 完成后清空对应 key。`transition()` 内首次读 definition 后缓存，后续调用 O(1)。

### 5.10 反 bug 对照表（与有缺陷实现的差异）

| 缺陷实现的做法 | 本 spec 的设计 | 反制了什么 bug |
|---|---|---|
| `assertTransition(source, target, action)` 业务传 target | `transition(action, branch)` 返回 toStatus | "业务硬编码 target" → 业务不再决定 target |
| `resolveConfiguredTarget()` 单独方法 | 合并进 `transition()` 返回值 | 防止业务忘记调 resolve 直接 assert |
| `enabled` 字段在另一页面 | API 不变；UI 强制同页（见 §7.5） | "保存未勾选启用导致约束失效" |
| review 不支持 branch | `branch` 一等公民 | "reject→closed 硬编码" |
| 状态字段 picker 默认值硬编码 | UI 调 `getActionList()` 拿到合法 toStatus | "picker 默认值不一致" |

---

## 6. 核心模块切入点

### 6.1 story 模块（含 epic / requirement 复用）

`module/story/model.php` 修改清单：

| 方法 | 切入方式 | 说明 |
|---|---|---|
| `create(...)` | 调 `assertEntryState($objectType, $productID, $story->status)` | 确保初始状态在 `entries[]` 中；不在则回退到 `entries[0]` |
| `submitreview(...)` | `transition(..., action:'submitreview')` → 用 `$decision->toStatus` | 单分支 |
| `review(...)` | `transition(..., action:'review', branch:$result)` | `$result` 来自 `getReviewResult()`，取值 `pass`/`reject`/`clarify`/`revert` |
| `change(...)` | `transition(..., action:'change')` | 单分支；`change.html.php` 的 status picker 默认值来自 §7.7 |
| `recallReview(...)` | `transition(..., action:'recallreview')` | 单分支 |
| `recallChange(...)` | `transition(..., action:'recallchange')` | 单分支 |
| `close(...)` | `transition(..., action:'close', branch:$closedReason==='rejected'?'rejected':'done')` | 双分支 |
| `activate(...)` | `transition(..., action:'activate')` | 单分支 |
| `update(...)` | 若 `old.status != new.status`：`assertStatusChange(...)` | 自由变更通道 |
| `batchChangeStatus(...)` | 逐条 `assertStatusChange(...)`，任一失败回滚整批 | |
| `isClickable(...)` | 静态调用 `isActionAllowed(...)`；自定义按钮也参与可见性 | 详见 §6.10 |
| `applyWorkflowTransition(...)` 🆕 | 通用入口：供自定义按钮调用 | 详见 §6.8 |

`$objectType` 在每个方法内通过 `$old->type` 动态判定（`epic` / `requirement` / `story`），保证 3 类需求独立校验。

**`getReviewResult()` 兜底修复**：reviewer 名单为空且表单 `result` 字段有值时，尊重表单 `result`，不再静默忽略。

### 6.2 bug 模块

`module/bug/model.php` 修改清单：

| 方法 | 切入方式 | 说明 |
|---|---|---|
| `create(...)` | `assertEntryState('bug', $bug->status)` | |
| `resolve(...)` | `transition(..., action:'resolve')` → 用 `$decision->toStatus` 替代硬编码 `'resolved'` | |
| `close(...)` | `transition(..., action:'close', branch:$closedReason==='rejected'?'rejected':'done')` | 双分支 |
| `activate(...)` | `transition(..., action:'activate')` | |
| `update(...)` | 若 status 变化：`assertStatusChange(...)` | |
| `batchResolve(...)` / `batchClose(...)` / `batchActivate(...)` | 逐条 `transition(...)`，任一失败回滚整批 | |
| `isClickable(...)` | `isActionAllowed(...)` | |
| `applyWorkflowTransition(...)` 🆕 | 通用入口 | |

### 6.3 task 模块

`module/task/model.php` 修改清单：

| 方法 | 切入方式 | 说明 |
|---|---|---|
| `create(...)` | `assertEntryState('task', $task->status)` | |
| `start(...)` | `transition(..., action:'start')` → 用 `$decision->toStatus` | |
| `restart(...)` | `transition(..., action:'restart')` | |
| `pause(...)` | `transition(..., action:'pause')` | |
| `finish(...)` | `transition(..., action:'finish')` | |
| `cancel(...)` | `transition(..., action:'cancel')` | |
| `close(...)` | `transition(..., action:'close')` | 单分支 |
| `activate(...)` | `transition(..., action:'activate')` | |
| `update(...)` | 若 status 变化：`assertStatusChange(...)` | |
| `updateKanbanCell(...)` | 拖拽路径：调 `assertStatusChange(..., comment:$output['comment'] ?? '')`，失败 throw + 回滚 UI 状态 | **关键**：覆盖看板拖拽。若匹配转移 `requireComment=true` 而拖拽未带评论，返回 `commentRequired` 错误，前端 JS 弹出评论框重试或拒绝拖拽 |
| `updateKanbanData(...)` | 同上，批量拖拽逐条校验 | |
| `batchChangeStatus(...)` | 逐条 `assertStatusChange(...)` | |
| `isClickable(...)` | `isActionAllowed(...)` | |
| `applyWorkflowTransition(...)` 🆕 | 通用入口 | |

### 6.4 创建入口 `assertEntryState()`

新增 public 方法（在 statetransition model）：

```php
/**
 * 校验并修正对象的初始状态。
 *
 * @param  string $objectType
 * @param  int    $productID
 * @param  string $status    表单传入的初始状态
 * @return string            校验后的最终状态（可能被回退到 entries[0]）
 */
public function assertEntryState(string $objectType, int $productID, string $status): string
```

逻辑：
1. 若工作流未启用：返回原 status（不约束）
2. 若 status 在 `entries[]` 中：返回原 status
3. 若不在：返回 `entries[0]`（兜底）+ dao::setError 提示已回退

被各模块的 `create()` 调用，确保新建对象不进入"无法转出的死状态"。

### 6.5 看板拖拽覆盖

`task->updateKanbanCell($taskID, $output, $executionID)`：

```php
$old = $this->getById($taskID);
$newStatus = $output['status'] ?? $old->status;
if ($newStatus !== $old->status) {
    $decision = $this->loadModel('statetransition')
        ->assertStatusChange('task', $old->product ?? 0, $taskID, $old->status, $newStatus, $output['comment'] ?? '');
    if (!$decision->ok) {
        dao::setError($decision->errorKey, $decision->errorMessage);
        return;  // 不写入，前端按失败回滚 UI
    }
    $output['status'] = $decision->toStatus;
}
// ... 原 updateKanbanCell 逻辑
```

### 6.6 批量操作

所有 `batch*` 方法遵循统一模式：

```php
$this->dao->beginTransaction();
try {
    foreach ($idList as $i => $id) {
        $old = $this->getById($id);
        $decision = $this->loadModel('statetransition')->transition(...);
        if (!$decision->ok) {
            $this->dao->rollBack();
            dao::setError("批量处理在第 {$i} 条失败: " . $decision->errorMessage);
            return false;
        }
        // 应用 $decision->toStatus 到本条
    }
    $this->dao->commit();
    return true;
} catch (Throwable $e) {
    $this->dao->rollBack();
    throw $e;
}
```

### 6.7 REST API / CLI

- **REST API 写操作**最终调 `model->update()` 或具体 action 方法 → 自动受约束
- **CLI 脚本**若直接 update status 字段（绕过 model）→ **不在本 spec 保护范围**，文档明示
- **数据导入**（`module/convert/`）：导入时若 status 不在 entries[]，由 `assertEntryState` 自动回退

### 6.8 自定义按钮入口

#### 6.8.1 通用入口方法（每个业务 model 加一个）

```php
// module/{story|bug|task}/model.php
/**
 * 应用工作流转移（供自定义按钮调用）。
 *
 * @param  int     $objectID
 * @param  string  $action    custom_xxx 或内置 action
 * @param  ?string $branch
 * @param  string  $comment
 * @return bool
 */
public function applyWorkflowTransition(
    int     $objectID,
    string  $action,
    ?string $branch = null,
    string  $comment = ''
): bool {
    $old = $this->getById($objectID);
    $objectType = $this->getObjectTypeName($old);
    // 每个 business model 自行实现 getObjectTypeName():
    //   - story model: return $old->type ?? 'story';        // epic/requirement/story
    //   - bug model:   return 'bug';
    //   - task model:  return 'task';

    $decision = $this->loadModel('statetransition')->transition(
        objectType : $objectType,
        productID  : $old->product ?? 0,
        objectID   : $objectID,
        fromStatus : $old->status,
        action     : $action,
        branch     : $branch,
        comment    : $comment
    );
    if (!$decision->ok) {
        dao::setError($decision->errorKey, $decision->errorMessage);
        return false;
    }

    // 应用目标状态
    $this->dao->update($this->table)
        ->data([
            'status'        => $decision->toStatus,
            'lastEditedBy'  => $this->app->user->account,
            'lastEditedDate' => helper::now()
        ])
        ->where('id')->eq($objectID)->exec();

    // 写 action 历史（自定义按钮统一记为 customworkflow，comment 存 transition label + 用户评论）
    $actionLogKey = ($decision->transition['isCustom'] ?? false) ? 'customworkflow' : $action;
    $actionComment = $comment;
    if (!empty($decision->transition['label'][str_replace('-', '_', $this->app->getClientLang())])) {
        $actionComment = '[' . $decision->transition['label'][str_replace('-', '_', $this->app->getClientLang())] . '] ' . $comment;
    }
    $this->loadModel('action')->create($objectType, $objectID, $actionLogKey, $actionComment);
    return true;
}
```

#### 6.8.2 control 入口

每个业务模块的 control.php 加一个方法：

```php
// module/story/control.php
public function applyWorkflowTransition(int $objectID, string $action, string $branch = '', string $comment = '')
{
    if (empty($action) || !preg_match('/^(custom_[a-z0-9_]+|submitreview|review|change|recallreview|recallchange|close|activate)$/', $action)) {
        return $this->sendFail(400, 'invalidAction');
    }
    $ok = $this->loadModel('story')->applyWorkflowTransition($objectID, $action, $branch ?: null, $comment);
    if ($ok) return $this->sendSuccess();
    return $this->sendFail(400, dao::getError());
}
```

路由示例：`POST /story-applyWorkflowTransition-123.json` body `action=custom_confirm&comment=xxx`

#### 6.8.3 工具栏注入

`module/{story|bug|task}/ui/view.html.php` 详情页工具栏区：

```php
$customButtons = $this->loadModel('statetransition')->getCustomButtons($objectType, $productID, $object->status);
foreach ($customButtons as $btn) {
    toolbar(
        btn(
            set::text($btn['buttonLabel']),
            set::icon($btn['buttonIcon']),
            setClass('btn ' . $btn['buttonGroup']),
            set::url(createLink($moduleName, 'applyWorkflowTransition', "objectID={$object->id}")),
            setData('action', $btn['action']),
            setData('requireComment', $btn['requireComment'] ? '1' : '0'),
            setData('confirm', $btn['requireComment'] ? $lang->statetransition->requireCommentTip : '')
        )
    );
}
```

JS 端（`module/statetransition/js/view.ui.js`）：拦截按钮点击，若 requireComment 弹出评论框，确认后 POST 到 url。

### 6.9 详情页流程图注入

`module/{story|bug|task}/ui/view.html.php` 末尾统一追加：

```php
echo $this->loadModel('statetransition')->renderFlowHtml($objectType, $productID, $object->status);
```

- 普通用户：只读 Mermaid 图 + 当前状态高亮
- 管理员：追加"配置此工作流"链接（跳 `statetransition->browse`）

`module/common/view/footer.html.php` 不动（避免污染全局）。

### 6.10 `isClickable()` 改造

每个业务模块的 `isClickable()` 静态方法增加工作流可见性判断：

```php
// module/story/model.php
public static function isClickable(object $data, string $action): bool
{
    global $app;
    $app->control->loadModel('statetransition');

    $objectType = isset($data->type) ? $data->type : 'story';
    $productID  = isset($data->product) ? (int)$data->product : 0;

    if (!statetransitionModel::isActionAllowedStatic(
        $objectType,
        $productID,
        $data->status,
        $action,
        $app->user
    )) {
        return false;
    }

    return parent::isClickable($data, $action);  // 原 ZenTao 业务规则继续判断
}
```

`isActionAllowedStatic` 是为了在静态上下文里调用（避免 `$this` 不可用）提供的静态包装。

### 6.11 切入点汇总

| 模块 | 修改方法数 | 新增方法数 | 新文件 |
|---|---|---|---|
| story | 11（含 create） | 1（applyWorkflowTransition） | ui 段落注入 |
| bug | 6 | 1 | ui 段落注入 |
| task | 11（含 updateKanbanCell + 3 个 batch） | 1 | ui 段落注入 |
| statetransition | — | 全新模块（约 15 个 public 方法） | 完整模块 |
| **合计** | **28 个修改点** | **4 个新增** | **1 个新模块** |

---

## 7. UI 规范

### 7.1 路由与入口

- **路由**：`statetransition->browse(objectType='story', productID=0, mode='edit')`
- **入口**：管理后台 → 功能配置 → 状态流转
- **菜单挂载**：通过 `extension/custom/admin/ext/config/statetransition.php` 加菜单项（这是 ZenTao 标准菜单扩展点，**不是 hook**，是允许的）
- **权限**：`statetransition.browse`（查看）+ `statetransition.manage`（保存），admin group 默认拥有两者

### 7.2 页面整体布局（ZUI3 / Zin）

```
┌──────────────────────────────────────────────────────────────────────┐
│ 顶部 Tab                                                              │
│ [Epic] [Requirement] [Story ●] [Bug] [Task]                          │
│ (受 $config->enableER / URAndSR 控制 visibility)                     │
├──────────────────────────────────────────────────────────────────────┤
│ Scope 切换                                                            │
│ ○ Global default    ● Product: [产品 A ▾]    [Reset] [Copy from global]│
├──────────────────────────────────────────────────────────────────────┤
│ ┌─ 启用 ──────────────────────────────────────────────────────────┐  │
│ │ [✓] 启用状态流转约束  (取消勾选则仅作可视化展示，不限制变迁)      │  │
│ │                                            [Save] [Save & close] │  │
│ └─────────────────────────────────────────────────────────────────┘  │
├──────────────────────────────────────────────────────────────────────┤
│ § Statuses 区                              [+ Add custom status]      │
│ ┌────────────┬────────┬──────────┬────────┬──────────┬─────────┐     │
│ │ Key        │ Label  │ Category │ Color  │ Is entry │ Actions │     │
│ ├────────────┼────────┼──────────┼────────┼──────────┼─────────┤     │
│ │ draft      │ 草稿   │ normal   │ ● gray │ ✓        │ Edit    │     │
│ │ reviewing  │ 评审中 │ normal   │ ● blue │          │ Edit    │     │
│ │ custom_qa  │ QA验证 │ normal   │ ● purple│        │ Edit/Delete │  │
│ └────────────┴────────┴──────────┴────────┴──────────┴─────────┘     │
│ Entry states 多选：[✓] draft  [✓] active  [ ] reviewing              │
├──────────────────────────────────────────────────────────────────────┤
│ § Transitions 区                            [+ Add transition]       │
│ ┌──────┬──────┬─────────────┬────────┬──────┬─────────┬─────────┐    │
│ │ From │ → To │ Action      │ Branch │ Label│ Require │ Custom  │    │
│ ├──────┼──────┼─────────────┼────────┼──────┼─────────┼─────────┤    │
│ │ draft│ revi │ submitreview│ -      │提交评审│ No    │ -       │    │
│ │ revi │ activ│ review      │ pass   │评审通过│ No    │ -       │    │
│ │ revi │ closed│ review     │ reject │ 拒绝  │ Yes   │ -       │    │
│ │ revi │ qa   │ custom_clar│ -      │标记澄清│ Yes  │ ✓ Button│    │
│ └──────┴──────┴─────────────┴────────┴──────┴─────────┴─────────┘    │
│ (点击行展开：Roles / Accounts / Button label / Icon / Group)         │
├──────────────────────────────────────────────────────────────────────┤
│ § 流程预览（Mermaid，根据上面配置实时生成）                              │
│ ┌──────────────────────────────────────────────────────────────┐    │
│ │ stateDiagram-v2                                              │    │
│ │   [*] --> draft                                              │    │
│ │   draft --> reviewing : submitreview                         │    │
│ │   reviewing --> active : review/pass                         │    │
│ │   ...                                                        │    │
│ └──────────────────────────────────────────────────────────────┘    │
└──────────────────────────────────────────────────────────────────────┘
```

### 7.3 状态管理（Statuses 区）

#### 7.3.1 系统状态 vs 自定义状态

- **系统状态**（`isSystem=true`）：5 类对象各自默认状态，从 `getDefaultDefinition()` 加载
  - Label 可编辑（覆盖 lang 默认文案）
  - Color 可编辑
  - Category 可编辑
  - **不可删除**（删除按钮置灰 + tooltip 提示"系统状态不可删除"）

- **自定义状态**（`isSystem=false`）：
  - 所有字段可编辑
  - 可删除，但删除前校验（见 §4.3）

#### 7.3.2 新增自定义状态（modal）

```php
modalTrigger(
    set::url(createLink('statetransition', 'createStatus', "objectType=$type&productID=$pid")),
    btn(set::icon('plus'), $lang->statetransition->addStatus)
);
```

Modal 内字段：
- **Key**（手动输入 + 自动从 Label 转 pinyin；唯一性校验）
- **Label**（中英文 i18n 输入，required）
- **Category**（radio：normal/abnormal/terminal）
- **Color**（colorpicker，预设 8 色 + 自定义）
- **Is entry**（checkbox）

### 7.4 转移管理（Transitions 区）

#### 7.4.1 列表展示

每行展示：From → To / Action / Branch / Label / Require comment / Custom button，点击展开完整字段。

#### 7.4.2 新增转移（modal）

字段：
- **From status**（picker，选项 = 当前定义的所有 status）
- **To status**（picker）
- **Action**（picker，选项 = §4.4 白名单 + `custom_xxx`）
- **Branch**（picker，根据 action 自动联动可选项；单分支 action 时禁用）
- **Label**（自动填充"from → to"或 action 默认 label，可改）
- **Roles**（multi-picker，来自 `usergroup`）
- **Accounts**（multi-picker，来自 `user`）
- **Require comment**（checkbox）
- **Enabled**（checkbox）

#### 7.4.3 自定义按钮字段

当 Action 选 `custom_*` 时，展开"自定义按钮配置"区：
- **Button label**（必填，多语言输入）
- **Button icon**（icon picker，ZUI3 内置 ZentaoIcon）
- **Button group**（radio：primary/more/danger，决定按钮颜色和位置）
- **Button order**（数字）

#### 7.4.4 重复转移检测

保存时若发现 `(fromStatus, action, branch)` 三元组重复（且都 enabled），拒绝：
> "已存在相同 From + Action + Branch 的转移，请禁用旧的或修改分支"

### 7.5 启用与保存（反 bug 设计）

#### 7.5.1 启用 checkbox 始终在页面顶部

```php
formGroup(
    setClass('alert alert-warning'),
    checkbox(
        set::name('enabled'),
        set::value(1),
        set::checked($definition['enabled']),
        $lang->statetransition->enableHint
    )
);
```

加 `alert-warning` 视觉强调，避免被忽略。

#### 7.5.2 单次保存所有变更

保存时整个 definition（enabled + statuses + transitions + entries）作为一个 JSON 提交，原子写入。避免"先存 statuses 再存 transitions"中间态的不一致。

#### 7.5.3 版本号乐观锁

POST 时带 `version`，服务端校验：

```php
if ($postVersion !== $dbRow->version) {
    return $this->sendFail(409, 'versionConflict');
}
```

#### 7.5.4 默认 enabled=true（首次保存）

为防止"管理员首次保存忘记勾选启用"，新增定义（如复制全局到产品、或新建空定义）时默认 `enabled=1`。

### 7.6 详情页流程图

#### 7.6.1 调用点

`module/{story|bug|task}/ui/view.html.php` 末尾追加：

```php
echo $this->loadModel('statetransition')->renderFlowHtml($objectType, $productID, $object->status);
```

#### 7.6.2 渲染输出

```html
<section class='statetransition-detail'>
  <h4>状态流转图 <small>当前：评审中</small></h4>
  <div class='mermaid'>stateDiagram-v2 ...</div>
  <!-- admin 可见 -->
  <a href='/statetransition-browse-story-0.html' class='btn btn-sm'>配置此工作流</a>
</section>
```

- 当前状态节点用 `class current` 高亮（Mermaid `classDef` + `class` 语法）
- 普通用户：只读图 + 当前状态文字说明
- 自定义按钮：作为 toolbar 的一部分（见 §6.8），不在这里重复

### 7.7 表单状态选择器联动

#### 7.7.1 编辑/变更表单的 status picker

```php
// module/story/ui/change.html.php
$statusOptions = $this->loadModel('statetransition')->getDefinitionStatusOptions($objectType, $productID);
$defaultTarget = $this->loadModel('statetransition')->getDefaultToStatus($objectType, $productID, $object->status, 'change');

picker(
    set::name('status'),
    set::items($statusOptions),
    set::value($defaultTarget)
);
```

**反 bug**：默认值不再硬编码 `'changing'`，而是从配置解析。

#### 7.7.2 创建表单的 status picker

```php
$entryOptions = $this->loadModel('statetransition')->getEntryStatusOptions($objectType, $productID);
picker(
    set::name('status'),
    set::items($entryOptions),
    set::value($entryOptions[0]['key'] ?? 'draft')
);
```

只允许选 `entries[]` 中的状态。

### 7.8 列表页与 featureBar

#### 7.8.1 状态徽章颜色

列表中 status 列的徽章颜色，调 `getStatusColor(objectType, productID, status)`：
- 系统状态：默认 lang 颜色
- 自定义状态：用 definition 里的 color
- 未配置/未启用：fallback 到禅道默认颜色

#### 7.8.2 featureBar（按状态过滤的 tab）

`module/story/lang/zh-cn.php` 静态 featureBar 的部分（如 `draft`/`reviewing`/`active`）保持不变；动态补丁：在 control 层 `browse()` 内调 `statetransition->extendFeatureBar($featureBar, $objectType, $productID)`，追加自定义状态作为新 tab。

#### 7.8.3 搜索过滤

搜索表单的 status 下拉，与 §7.7.2 一致使用动态 options。

### 7.9 Action 历史（动态记录）

`action` 表的 `action` 字段：
- 内置 action：原样存（`close`/`resolve`/...）
- 自定义 action：存 `customworkflow`，`comment` 存 transition label + 用户填写的评论
- 历史详情页正常显示，无特殊改动

### 7.10 多语言

- `module/statetransition/lang/zh-cn.php`：错误码、按钮、提示
- 自定义状态的 label：在 definition 里以 `{"zh_cn": "...", "en": "..."}` 多语言存（编辑界面提供多语言输入）
- 列表/详情页取 label 时，按当前 `$app->getClientLang()` 取对应串，缺失则 fallback 到 zh_cn，再 fallback 到 key

### 7.11 移动端适配

- 后台管理页：建议桌面端为主，移动端提示"建议在桌面端配置"
- 详情页流程图：Mermaid 自带响应式，无需额外处理
- 自定义按钮：与内置按钮一致，移动端按 dtable 行操作弹出

---

## 8. 权限与审计

### 8.1 权限定义

| 权限 key | 说明 | 默认拥有者 |
|---|---|---|
| `statetransition.browse` | 查看后台配置页（只读） | admin |
| `statetransition.manage` | 保存配置（增删改状态/转移/启用） | admin |
| `statetransition.resetDefault` | 重置为默认定义 | admin |

权限注册在 `zt_grouppriv` 表：

```sql
REPLACE INTO `zt_grouppriv` (`group`, `module`, `method`) VALUES
(1, 'statetransition', 'browse'),
(1, 'statetransition', 'manage'),
(1, 'statetransition', 'resetDefault');
```

### 8.2 角色授权（per-transition）

#### 8.2.1 角色 key 来源

禅道角色 = `zt_group` 表的 `role` 字段 + 自定义角色。本 spec 中：

- transition 的 `roles[]` 存的是 `zt_group.role` 的值（如 `dev`/`qa`/`pm`/`po`/`admin`）
- 一个用户可能多角色，匹配规则：用户的当前角色 ∩ transition.roles 非空即通过

#### 8.2.2 账号授权

transition 的 `accounts[]` 存具体账号（如 `admin`、`zhangsan`），优先级高于 roles：
- accounts 非空时，只检查 accounts
- accounts 为空、roles 非空时，检查 roles
- 两者都为空时，不限制

#### 8.2.3 actor 校验

`checkActor(transition, user)`：
1. transition.accounts 非空 → `in_array(user.account, transition.accounts)`
2. 否则 transition.roles 非空 → `in_array(user.role, transition.roles)`
3. 否则 → true

### 8.3 审计与日志

#### 8.3.1 配置变更日志

`saveDefinition()` 完成后，调 `action->create('statetransition', $defID, 'edited', $diff)`，diff 描述变更摘要（如"新增状态 custom_qa；删除转移 draft-to-reviewing"）。

#### 8.3.2 业务转移日志

业务方法的 `transition()` 调用如果成功：
- action 表照常写入（如 `review`、`close`、`customworkflow`）
- 在 comment 字段前缀 transition label，便于追溯

#### 8.3.3 约束失效日志

`transitionDecision.wasUnrestricted=true`（定义未启用或不存在）时：
- 业务方法不阻塞（按 ZenTao 原生行为走）
- action 表 comment 追加 `[workflow: unrestricted]` 标记，便于审计灰度阶段的行为

---

## 9. 自定义状态机制

### 9.1 与现有 `lang->statusList` 的关系

禅道现状：`module/{story|bug|task}/lang/zh-cn.php` 静态定义 `$lang->xxx->statusList` 数组。

**改造策略**：
- 静态 statusList 保留作为系统状态基础数据（不删除）
- 新增 helper：`statetransition->getStatusList($objectType, $productID): array`
  - 读取 workflow definition，合并系统状态 + 自定义状态
  - 返回 `[key => label]` 数组（label 来自 definition 的多语言字段）
- 所有动态使用 statusList 的地方（featureBar、列表筛选、统计报表）改为调用此 helper

**改造点清单**（需要替换的代码位置）：
- `module/story/control.php::browse()` 的 featureBar
- `module/story/model.php::getStatData()` 等统计方法
- `module/bug/control.php::browse()` 同上
- `module/task/control.php::browse()` 同上
- `module/report/`、`module/chart/`、`module/metric/` 中按状态统计的报表

### 9.2 多语言存储

definition 中 status 的 label 字段格式：

```json
"label": {
  "zh_cn": "待客户确认",
  "zh_tw": "待客戶確認",
  "en":    "Awaiting Customer Confirmation"
}
```

- 编辑界面提供多语言输入（tab 切换）
- 取值时按 `$app->getClientLang()`，缺失 fallback 到 `zh_cn`，再 fallback 到 key
- `en` 必填（作为兜底）

### 9.3 删除/重命名处理

#### 删除自定义状态

校验流程（见 §4.3）：
1. 是否有对象处于此状态 → 阻止
2. 是否有 transition 引用 → 阻止
3. 任一命中返回 `statusInUse`，UI 显示具体阻塞原因

#### 重命名状态 key

不允许直接重命名 key（数据库已存的 status 值会失联）。改用"新增+迁移+删除"模式：
1. 管理员新增目标 key 状态
2. 在 SQL 工具页执行 `UPDATE zt_xxx SET status='新key' WHERE status='旧key'`（禅道已提供 admin/sql 工具）
3. 修改 transitions 引用
4. 删除旧 key 状态

文档明示此流程，UI 不提供"重命名 key"按钮。

### 9.4 SQL 查询兼容

现有 SQL（如 `WHERE status='active'`）继续工作，因为系统状态 key 不变。

自定义状态的查询：
- 列表筛选下拉来自 `getStatusList()`，自动包含自定义状态
- 报表 SQL 按状态分组：改为 `LEFT JOIN` 或 PHP 后处理补全自定义状态

---

## 10. 安装与迁移

### 10.1 install.sql

```sql
CREATE TABLE IF NOT EXISTS `zt_workflow_definition` (
  `id`          mediumint unsigned NOT NULL AUTO_INCREMENT,
  `scope`       enum('global','product') NOT NULL DEFAULT 'global',
  `productID`   mediumint unsigned NOT NULL DEFAULT 0,
  `objectType`  varchar(30) NOT NULL,
  `name`        varchar(100) NOT NULL DEFAULT '',
  `enabled`     enum('0','1') NOT NULL DEFAULT '0',
  `version`     int unsigned NOT NULL DEFAULT 1,
  `definition`  mediumtext NOT NULL,
  `createdBy`   varchar(30) NOT NULL DEFAULT '',
  `createdDate` datetime DEFAULT NULL,
  `editedBy`    varchar(30) NOT NULL DEFAULT '',
  `editedDate`  datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_scope_obj` (`scope`, `productID`, `objectType`),
  KEY `idx_obj_lookup` (`objectType`, `productID`, `enabled`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

REPLACE INTO `zt_grouppriv` (`group`, `module`, `method`) VALUES
(1, 'statetransition', 'browse'),
(1, 'statetransition', 'manage'),
(1, 'statetransition', 'resetDefault'),
(1, 'statetransition', 'createStatus'),
(1, 'statetransition', 'createTransition');

-- 一次性迁移：从旧 zt_workflowflowchart 表导入（若存在）
-- 注意：旧表 JSON 字段命名（nodes/edges）与新 schema（statuses/transitions）不同；
-- 直接搬过来不转换，由 statetransition->normalizeDefinition() 在首次读取时
-- 自动转换并存回（schemaVersion 标记），更鲁棒、不依赖 MySQL JSON 函数版本。
INSERT IGNORE INTO `zt_workflow_definition`
  (`scope`,`productID`,`objectType`,`name`,`enabled`,`version`,`definition`,`createdBy`,`createdDate`,`editedBy`,`editedDate`)
SELECT
  'global'      AS scope,
  0             AS productID,
  `objectType`,
  `name`,
  `enabled`,
  `version`,
  `definition`,  -- 原样存入，由 normalizeDefinition() 在运行时升级
  `createdBy`,`createdDate`,`editedBy`,`editedDate`
FROM `zt_workflowflowchart`
WHERE NOT EXISTS (
  SELECT 1 FROM `zt_workflow_definition` s
  WHERE s.objectType = `zt_workflowflowchart`.`objectType`
);

-- 注：normalizeDefinition() 检测到 schemaVersion 缺失或字段名是 nodes/edges 时，
-- 自动做字段重命名（nodes→statuses、edges→transitions）、补齐新增字段（category、
-- isSystem、isCustom、buttonLabel 等）、设 schemaVersion=1，并 UPDATE 写回。
```

### 10.2 uninstall.sql

```sql
DROP TABLE IF EXISTS `zt_workflow_definition`;
DELETE FROM `zt_grouppriv` WHERE `module` = 'statetransition';
DELETE FROM `zt_action` WHERE `objectType` = 'statetransition';
```

### 10.3 升级路径

#### 从禅道 22.2（无本特性）升级到带本特性的版本

1. 部署新代码（含 statetransition 模块 + 各业务 model 改动）
2. 执行 `db/install.sql`（自动检测并迁移旧 workflowflowchart 数据）
3. 在后台 `statetransition->browse` 检查每个 objectType 的定义
4. 启用 / 调整定义
5. 验证 story/bug/task 的状态变更行为
6. 验证通过后：删除 `extension/custom/workflowflowchart/` 整个目录（如存在）

#### 已有 hook 实现的迁移

如果客户之前使用基于 hook 的自定义工作流（如 workflowflowchart extension）：
- install.sql 自动迁移数据
- 旧 hook 文件需要手动删除（避免双重拦截）：
  - `extension/custom/*/ext/*/hook/*.workflowflowchart.php`
  - `extension/custom/workflowflowchart/` 整个目录
- 其它 hook（如 `objecteffort`）保留不动

### 10.4 与现有数据的兼容

- `zt_story.status` / `zt_bug.status` / `zt_task.status` 字段不动（已 varchar(30)）
- 自定义状态直接以 key 存入；如果迁移前后状态值有差异，由 `assertEntryState` 兜底
- 历史对象状态读取：详情页/列表通过 `getStatusList()` 解析，未配置的 key 显示原 key 文本（不报错）

---

## 11. 测试策略

### 11.1 单元测试

`module/statetransition/test/model/` 下：

| 测试文件 | 覆盖方法 | 测试步骤数 |
|---|---|---|
| `transition.php` | `transition()` 主入口 | ≥ 10 |
| `assertstatuschange.php` | `assertStatusChange()` | ≥ 8 |
| `isactionallowed.php` | `isActionAllowed()` | ≥ 6 |
| `validatedefinition.php` | `validateDefinition()` | ≥ 10 |
| `normalizedefinition.php` | `normalizeDefinition()` | ≥ 6 |
| `defaultdefinition.php` | `getDefaultDefinition()` | ≥ 5 |
| `getstatuslist.php` | `getStatusList()` | ≥ 6 |
| `getcustombuttons.php` | `getCustomButtons()` | ≥ 5 |
| `rendermermaid.php` | `renderMermaid()` | ≥ 4 |
| `assertentrystate.php` | `assertEntryState()` | ≥ 5 |
| `copydefinition.php` | `copyDefinition()` | ≥ 4 |

总计：单元测试用例 ≥ 69 个。

#### 关键测试场景

**transition() 的反 bug 测试**：
```
- 场景：review action 配置 reject→draft，调用 transition(branch='reject')
  期望：toStatus='draft'（不是默认的 'closed'）
- 场景：定义存在但 enabled=0
  期望：wasUnrestricted=true，toStatus 取业务默认
- 场景：(fromStatus, action) 多分支但未传 branch
  期望：返回 ambiguousBranch 错误
- 场景：transition.accounts 非空，当前用户不在
  期望：返回 actorDenied 错误
```

### 11.2 集成测试

`module/statetransition/test/integration/` 下：

| 测试文件 | 场景 |
|---|---|
| `storyintegration.php` | story 全 action 端到端 |
| `bugintegration.php` | bug 全 action 端到端 |
| `taskintegration.php` | task 全 action 端到端（含看板拖拽） |
| `productoverride.php` | 产品级覆盖解析 |
| `custombutton.php` | 自定义按钮点击到写入 |
| `batchoperation.php` | 批量操作的回滚 |

每个集成测试 ≥ 5 个用例，总计 ≥ 30 个。

### 11.3 E2E 浏览器测试

`module/statetransition/test/ui/e2e.mjs`（Playwright）：

| 场景 | 验证点 |
|---|---|
| 登录后访问后台配置页 | 页面渲染、tab 切换、scope 切换 |
| 配置 story 工作流并保存 | 保存成功、刷新后数据持久 |
| 启用工作流后做 review | 业务方法走配置分支 |
| 详情页流程图渲染 | Mermaid 渲染、当前状态高亮 |
| 自定义按钮在详情页可见、点击弹评论 | 评论必填、提交后状态变更 |
| 多分支 review（pass/reject/clarify） | 各分支走配置目标 |
| 批量操作中第 3 条失败 | 整批回滚 |
| 看板拖拽到禁用状态 | 拖拽失败 + UI 回滚 |
| 产品级覆盖 | 不同产品显示不同流程 |
| 自定义状态增删 | 引用检测、删除阻塞 |

总计：≥ 10 个 E2E 场景。

### 11.4 性能测试

| 场景 | 期望 |
|---|---|
| 单次 `transition()` 调用 | < 5ms（含缓存命中） |
| 首次 `transition()` 调用（未缓存） | < 20ms |
| 详情页流程图渲染 | < 50ms（Mermaid 渲染） |
| 后台配置页加载 | < 200ms |
| 100 个产品的批量查询 | < 500ms |

### 11.5 关键 bug 类回归测试

针对旧 impl 已知的 3 类 bug，本 spec 设计专项回归：

| Bug 类 | 回归测试 |
|---|---|
| 业务方法硬编码 target | 单测：所有内置 action 的 transition() 调用都不传 toStatus |
| 启用 checkbox 在另一页面 | E2E：保存定义时不勾选 enabled 必须明确，UI 强制展示 |
| Picker 默认值硬编码 | E2E：change 表单的 status picker 默认值随配置变化 |

---

## 12. 验收标准

### 12.1 功能等价性

- ✅ 5 类对象（epic/requirement/story/bug/task）全部支持状态流转自定义
- ✅ 支持自定义状态（新增/编辑/删除）
- ✅ 支持自定义动作按钮（绑定到转移）
- ✅ 支持产品级覆盖
- ✅ 支持角色授权（per-transition roles）
- ✅ 支持账号授权（per-transition accounts）
- ✅ 支持强制评论（requireComment）
- ✅ 支持总开关（enabled）

### 12.2 反 bug 验收

- ✅ 业务代码不含任何硬编码 target status（grep 验证）
- ✅ 启用 checkbox 与配置在同一页面、同一次保存
- ✅ 所有 status picker 的默认值来自 `getDefaultToStatus()` 或 `getEntryStatusOptions()`
- ✅ `review()` 的 4 个分支（pass/reject/clarify/revert）目标状态完全由配置决定
- ✅ `change()` 的目标状态完全由配置决定
- ✅ 看板拖拽、批量操作、API 三条隐式路径都受约束

### 12.3 性能指标

- ✅ 单次状态变更 RT 增量 < 20ms（p99）
- ✅ 单次状态变更 RT 增量 < 5ms（p99，缓存命中）
- ✅ 详情页加载增量 < 50ms
- ✅ 后台配置页加载 < 200ms

### 12.4 兼容性

- ✅ 现有 `zt_story/bug/task.status` 字段不变
- ✅ 现有 `lang->statusList` 静态数据保留
- ✅ 现有 SQL 查询（`WHERE status='active'`）正常工作
- ✅ 现有 REST API 不受影响
- ✅ 卸载脚本不影响业务数据

### 12.5 代码质量

- ✅ 无 hook 文件（grep `extension/custom/*/ext/*/hook/*.statetransition.php` 为空）
- ✅ statetransition 模块单测覆盖率 ≥ 80%
- ✅ 业务模块（story/bug/task）的 transition 切入点单测覆盖率 ≥ 80%
- ✅ PHP 8.1+ 语法兼容（`declare(strict_types=1)`、typed properties、enum）

### 12.6 用户体验

- ✅ 后台配置页新手管理员 5 分钟内完成"新增一个状态 + 配置转移"
- ✅ 详情页流程图在 1 秒内渲染完成
- ✅ 自定义按钮点击响应时间 < 200ms

---

## 13. 风险与缓解

| 风险类别 | 具体风险 | 缓解 |
|---|---|---|
| **技术** | 核心 model 入口点遗漏（如某 API/CLI 入口未覆盖） | §11 测试计划覆盖所有入口；grep 工具扫描 `function (start\|pause\|finish\|...)` 确保无遗漏 |
| **技术** | 双重校验导致性能下降 | 进程内 static 缓存，单请求每对象类型仅读一次；缓存命中 < 5ms |
| **技术** | 自定义状态在统计报表中遗漏 | §9.1 改造点清单逐项替换；新增 lint 检查 |
| **技术** | 看板拖拽路径绕过校验 | §6.5 显式覆盖 `updateKanbanCell`；E2E 验证 |
| **数据** | 迁移数据损坏 | install.sql 用 `INSERT IGNORE ... WHERE NOT EXISTS`；迁移后 `statetransition->browse` 显示并允许人工核对 |
| **数据** | 旧 hook 残留导致双重报错 | 迁移文档明确删除 hook 文件；CI 检查 `extension/custom/*/ext/*/hook/*.workflowflowchart.php` 不存在 |
| **数据** | 自定义状态 key 与未来内置 key 冲突 | 自定义 key 强制 `^custom_` 前缀（推荐但非强制，本 spec 不强制避免兼容性问题） |
| **业务** | 管理员误删除在用状态 | §4.3 删除前校验，返回 `statusInUse` + 阻塞原因 |
| **业务** | 并发保存覆盖 | §7.5.3 乐观锁，POST 携带 version |
| **业务** | 启用后历史数据状态非法（如旧数据有 `closed` 但新定义删了 `closed`） | 系统状态不可删除；自定义状态删除前校验；详情页对未知 key 容错显示 |
| **UX** | 管理员首次配置忘记勾选启用 | §7.5.4 新建定义默认 enabled=1；UI alert-warning 视觉强调 |
| **UX** | 多分支场景下 branch 概念难理解 | 后台编辑器在 action 选 review 时自动展示 branch 列；tooltip 解释 |

---

## 14. 时间表（粗）

| 阶段 | 内容 | 工时 |
|---|---|---|
| D1 | 完成 spec / 测试计划评审 | 0.5 天 |
| D2 | 数据表 + 模型骨架（CRUD/validate/normalize）+ 默认定义 | 1 天 |
| D3 | 核心 `transition()` / `assertStatusChange()` / `isActionAllowed()` 实现 + 单测 | 1.5 天 |
| D4 | 业务模块切入点（story 11 处 + bug 6 处 + task 11 处） | 2 天 |
| D5 | 后台 UI（browse + createStatus + createTransition modal） | 1.5 天 |
| D6 | 详情页流程图 + 表单 picker 联动 + 自定义按钮注入 | 1 天 |
| D7 | 数据迁移 + 集成测试 | 1 天 |
| D8 | E2E 浏览器测试 + 修缺陷 | 1.5 天 |
| D9 | 性能测试 + 缓存优化 | 0.5 天 |
| D10 | 清理旧 hook（如存在）+ 上线准备 | 0.5 天 |
| **合计** | | **11 天** |

---

## 15. Phase 2 扩展点

本 spec 在数据模型、API 中预留了 Phase 2（表单联动 + 自定义字段）的扩展点，便于未来无缝升级。

### 15.1 表单视图联动

#### 15.1.1 数据扩展

definition 的每个 status 已经预留 `fieldRules` 占位字段，Phase 2 填入：

```jsonc
"fieldRules": {
  "assignedTo":   {"visible": true,  "required": true,  "readonly": false},
  "closedReason": {"visible": true,  "required": true,  "readonly": false, "defaultValue": "done"},
  "storyPoints":  {"visible": false, "required": false, "readonly": false}
}
```

#### 15.1.2 渲染集成

`module/{story|bug|task}/ui/{create,edit,change}.html.php` 的 form 渲染逻辑：
- 读取当前状态的 fieldRules
- 对每个字段注入 `visible` / `required` / `readonly` 属性
- 客户端 JS 在 status 字段 change 时动态调整其他字段

#### 15.1.3 服务端二次校验

提交时 `assertEntryState` 之后，加 `assertFieldRules(objectType, productID, status, formData)` 校验必填/只读约束。

### 15.2 自定义字段

#### 15.2.1 新增数据表

```sql
CREATE TABLE `zt_workflow_field` (
  `id`          mediumint unsigned NOT NULL AUTO_INCREMENT,
  `scope`       enum('global','product') NOT NULL DEFAULT 'global',
  `productID`   mediumint unsigned NOT NULL DEFAULT 0,
  `objectType`  varchar(30) NOT NULL,
  `fieldKey`    varchar(60) NOT NULL,           -- 自定义字段 key（custom_xxx）
  `fieldType`   varchar(20) NOT NULL,           -- text|number|date|select|...
  `label`       text NOT NULL,                  -- 多语言 JSON
  `options`     text,                           -- select 类型的选项 JSON
  `validation`  text,                           -- 校验规则 JSON
  `order`       smallint NOT NULL DEFAULT 0,
  `enabled`     enum('0','1') NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_field` (`scope`, `productID`, `objectType`, `fieldKey`)
);
```

#### 15.2.2 数据存储

每个对象类型一张扩展表（key-value 行存）：

```sql
CREATE TABLE `zt_story_customfield` (
  `id`       mediumint NOT NULL AUTO_INCREMENT,
  `objectID` mediumint NOT NULL,
  `fieldKey` varchar(60) NOT NULL,
  `value`    text,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_obj_field` (`objectID`, `fieldKey`)
);
```

#### 15.2.3 表单注入

`module/{story|bug|task}/ui/edit.html.php` 在基础字段之后追加"扩展信息"区，渲染所有 enabled 自定义字段。

#### 15.2.4 列表与搜索

- dtable 列支持自定义字段
- 搜索表单的下拉选项来自 `zt_workflow_field.options`

### 15.3 升级路径

Phase 1 → Phase 2 升级时：
- 不动 `zt_workflow_definition` schema（已预留 fieldRules）
- 仅新增 `zt_workflow_field` + `zt_{object}_customfield` 两张表
- definition 的 schemaVersion 升级到 2，老定义自动兼容

---

## 16. 附录

### 16.1 默认定义（5 类对象）

#### 16.1.1 story / epic / requirement 默认定义

```jsonc
{
  "schemaVersion": 1,
  "statuses": [
    {"key": "draft",     "label": {"zh_cn":"草稿",    "en":"Draft"},     "category":"normal",  "color":"#999",   "isSystem":true, "isEntry":true,  "fieldRules":{}},
    {"key": "reviewing", "label": {"zh_cn":"评审中",  "en":"Reviewing"}, "category":"normal",  "color":"#3498db","isSystem":true, "isEntry":false, "fieldRules":{}},
    {"key": "active",    "label": {"zh_cn":"激活",    "en":"Active"},    "category":"normal",  "color":"#27ae60","isSystem":true, "isEntry":true,  "fieldRules":{}},
    {"key": "changing",  "label": {"zh_cn":"变更中",  "en":"Changing"},  "category":"abnormal","color":"#f39c12","isSystem":true, "isEntry":false, "fieldRules":{}},
    {"key": "closed",    "label": {"zh_cn":"已关闭",  "en":"Closed"},    "category":"terminal","color":"#7f8c8d","isSystem":true, "isEntry":false, "fieldRules":{}}
  ],
  "transitions": [
    {"key":"draft-to-reviewing-via-submitreview",      "fromStatus":"draft",     "toStatus":"reviewing","action":"submitreview", "branch":null,    "label":{"zh_cn":"提交评审","en":"Submit"},          "roles":[],"accounts":[],"requireComment":false,"enabled":true,"isCustom":false,"buttonLabel":null,"buttonIcon":null,"buttonOrder":0,"buttonGroup":"primary","sideEffects":[],"condition":null},
    {"key":"reviewing-to-active-via-review-pass",      "fromStatus":"reviewing", "toStatus":"active",   "action":"review",       "branch":"pass",  "label":{"zh_cn":"评审通过","en":"Pass"},            "roles":[],"accounts":[],"requireComment":false,"enabled":true,"isCustom":false,"buttonLabel":null,"buttonIcon":null,"buttonOrder":0,"buttonGroup":"primary","sideEffects":[],"condition":null},
    {"key":"reviewing-to-closed-via-review-reject",    "fromStatus":"reviewing", "toStatus":"closed",   "action":"review",       "branch":"reject","label":{"zh_cn":"拒绝",  "en":"Reject"},          "roles":[],"accounts":[],"requireComment":true, "enabled":true,"isCustom":false,"buttonLabel":null,"buttonIcon":null,"buttonOrder":0,"buttonGroup":"primary","sideEffects":[],"condition":null},
    {"key":"reviewing-to-draft-via-review-clarify",    "fromStatus":"reviewing", "toStatus":"draft",    "action":"review",       "branch":"clarify","label":{"zh_cn":"需澄清","en":"Clarify"},        "roles":[],"accounts":[],"requireComment":true, "enabled":true,"isCustom":false,"buttonLabel":null,"buttonIcon":null,"buttonOrder":0,"buttonGroup":"primary","sideEffects":[],"condition":null},
    {"key":"reviewing-to-draft-via-recallreview",      "fromStatus":"reviewing", "toStatus":"draft",    "action":"recallreview", "branch":null,    "label":{"zh_cn":"撤回评审","en":"Recall"},          "roles":[],"accounts":[],"requireComment":false,"enabled":true,"isCustom":false,"buttonLabel":null,"buttonIcon":null,"buttonOrder":0,"buttonGroup":"primary","sideEffects":[],"condition":null},
    {"key":"active-to-changing-via-change",            "fromStatus":"active",    "toStatus":"changing", "action":"change",       "branch":null,    "label":{"zh_cn":"变更",  "en":"Change"},          "roles":[],"accounts":[],"requireComment":true, "enabled":true,"isCustom":false,"buttonLabel":null,"buttonIcon":null,"buttonOrder":0,"buttonGroup":"primary","sideEffects":[],"condition":null},
    {"key":"changing-to-active-via-recallchange",      "fromStatus":"changing",  "toStatus":"active",   "action":"recallchange", "branch":null,    "label":{"zh_cn":"撤回变更","en":"Recall Change"},  "roles":[],"accounts":[],"requireComment":false,"enabled":true,"isCustom":false,"buttonLabel":null,"buttonIcon":null,"buttonOrder":0,"buttonGroup":"primary","sideEffects":[],"condition":null},
    {"key":"active-to-closed-via-close-done",          "fromStatus":"active",    "toStatus":"closed",   "action":"close",        "branch":"done",  "label":{"zh_cn":"关闭",  "en":"Close"},           "roles":[],"accounts":[],"requireComment":true, "enabled":true,"isCustom":false,"buttonLabel":null,"buttonIcon":null,"buttonOrder":0,"buttonGroup":"primary","sideEffects":[],"condition":null},
    {"key":"closed-to-active-via-activate",            "fromStatus":"closed",    "toStatus":"active",   "action":"activate",     "branch":null,    "label":{"zh_cn":"激活",  "en":"Activate"},        "roles":[],"accounts":[],"requireComment":false,"enabled":true,"isCustom":false,"buttonLabel":null,"buttonIcon":null,"buttonOrder":0,"buttonGroup":"primary","sideEffects":[],"condition":null}
  ],
  "entries": ["draft", "active"]
}
```

#### 16.1.2 bug 默认定义

```jsonc
{
  "schemaVersion": 1,
  "statuses": [
    {"key": "active",   "label": {"zh_cn":"激活",   "en":"Active"},   "category":"normal",  "color":"#27ae60","isSystem":true,"isEntry":true,  "fieldRules":{}},
    {"key": "resolved", "label": {"zh_cn":"已解决", "en":"Resolved"}, "category":"normal",  "color":"#3498db","isSystem":true,"isEntry":false, "fieldRules":{}},
    {"key": "closed",   "label": {"zh_cn":"已关闭", "en":"Closed"},   "category":"terminal","color":"#7f8c8d","isSystem":true,"isEntry":false, "fieldRules":{}}
  ],
  "transitions": [
    {"key":"active-to-resolved-via-resolve",   "fromStatus":"active",   "toStatus":"resolved","action":"resolve", "branch":null,    "label":{"zh_cn":"解决","en":"Resolve"},"roles":[],"accounts":[],"requireComment":true, "enabled":true,"isCustom":false,"buttonLabel":null,"buttonIcon":null,"buttonOrder":0,"buttonGroup":"primary","sideEffects":[],"condition":null},
    {"key":"resolved-to-closed-via-close-done","fromStatus":"resolved", "toStatus":"closed",  "action":"close",   "branch":"done",  "label":{"zh_cn":"关闭","en":"Close"},  "roles":[],"accounts":[],"requireComment":false,"enabled":true,"isCustom":false,"buttonLabel":null,"buttonIcon":null,"buttonOrder":0,"buttonGroup":"primary","sideEffects":[],"condition":null},
    {"key":"resolved-to-active-via-activate",  "fromStatus":"resolved", "toStatus":"active",  "action":"activate","branch":null,    "label":{"zh_cn":"激活","en":"Activate"},"roles":[],"accounts":[],"requireComment":true, "enabled":true,"isCustom":false,"buttonLabel":null,"buttonIcon":null,"buttonOrder":0,"buttonGroup":"primary","sideEffects":[],"condition":null},
    {"key":"closed-to-active-via-activate",    "fromStatus":"closed",   "toStatus":"active",  "action":"activate","branch":null,    "label":{"zh_cn":"重开","en":"Reopen"},  "roles":[],"accounts":[],"requireComment":true, "enabled":true,"isCustom":false,"buttonLabel":null,"buttonIcon":null,"buttonOrder":0,"buttonGroup":"primary","sideEffects":[],"condition":null}
  ],
  "entries": ["active"]
}
```

#### 16.1.3 task 默认定义

```jsonc
{
  "schemaVersion": 1,
  "statuses": [
    {"key": "wait",   "label": {"zh_cn":"未开始", "en":"Wait"},   "category":"normal",  "color":"#999",   "isSystem":true,"isEntry":true,  "fieldRules":{}},
    {"key": "doing",  "label": {"zh_cn":"进行中", "en":"Doing"},  "category":"normal",  "color":"#3498db","isSystem":true,"isEntry":false, "fieldRules":{}},
    {"key": "done",   "label": {"zh_cn":"已完成", "en":"Done"},   "category":"normal",  "color":"#27ae60","isSystem":true,"isEntry":false, "fieldRules":{}},
    {"key": "pause",  "label": {"zh_cn":"已暂停", "en":"Pause"},  "category":"abnormal","color":"#f39c12","isSystem":true,"isEntry":false, "fieldRules":{}},
    {"key": "cancel", "label": {"zh_cn":"已取消", "en":"Cancel"}, "category":"terminal","color":"#e74c3c","isSystem":true,"isEntry":false, "fieldRules":{}},
    {"key": "closed", "label": {"zh_cn":"已关闭", "en":"Closed"}, "category":"terminal","color":"#7f8c8d","isSystem":true,"isEntry":false, "fieldRules":{}}
  ],
  "transitions": [
    {"key":"wait-to-doing-via-start",        "fromStatus":"wait",  "toStatus":"doing","action":"start",  "branch":null,"label":{"zh_cn":"开始","en":"Start"},  "roles":[],"accounts":[],"requireComment":false,"enabled":true,"isCustom":false,"buttonLabel":null,"buttonIcon":null,"buttonOrder":0,"buttonGroup":"primary","sideEffects":[],"condition":null},
    {"key":"pause-to-doing-via-restart",     "fromStatus":"pause", "toStatus":"doing","action":"restart","branch":null,"label":{"zh_cn":"继续","en":"Restart"},"roles":[],"accounts":[],"requireComment":false,"enabled":true,"isCustom":false,"buttonLabel":null,"buttonIcon":null,"buttonOrder":0,"buttonGroup":"primary","sideEffects":[],"condition":null},
    {"key":"doing-to-pause-via-pause",       "fromStatus":"doing", "toStatus":"pause","action":"pause", "branch":null,"label":{"zh_cn":"暂停","en":"Pause"}, "roles":[],"accounts":[],"requireComment":true, "enabled":true,"isCustom":false,"buttonLabel":null,"buttonIcon":null,"buttonOrder":0,"buttonGroup":"primary","sideEffects":[],"condition":null},
    {"key":"doing-to-done-via-finish",       "fromStatus":"doing", "toStatus":"done", "action":"finish","branch":null,"label":{"zh_cn":"完成","en":"Finish"},"roles":[],"accounts":[],"requireComment":true, "enabled":true,"isCustom":false,"buttonLabel":null,"buttonIcon":null,"buttonOrder":0,"buttonGroup":"primary","sideEffects":[],"condition":null},
    {"key":"wait-to-closed-via-close",       "fromStatus":"wait",  "toStatus":"closed","action":"close","branch":null,"label":{"zh_cn":"关闭","en":"Close"}, "roles":[],"accounts":[],"requireComment":false,"enabled":true,"isCustom":false,"buttonLabel":null,"buttonIcon":null,"buttonOrder":0,"buttonGroup":"primary","sideEffects":[],"condition":null},
    {"key":"doing-to-closed-via-close",      "fromStatus":"doing", "toStatus":"closed","action":"close","branch":null,"label":{"zh_cn":"关闭","en":"Close"}, "roles":[],"accounts":[],"requireComment":false,"enabled":true,"isCustom":false,"buttonLabel":null,"buttonIcon":null,"buttonOrder":0,"buttonGroup":"primary","sideEffects":[],"condition":null},
    {"key":"done-to-closed-via-close",       "fromStatus":"done",  "toStatus":"closed","action":"close","branch":null,"label":{"zh_cn":"关闭","en":"Close"}, "roles":[],"accounts":[],"requireComment":false,"enabled":true,"isCustom":false,"buttonLabel":null,"buttonIcon":null,"buttonOrder":0,"buttonGroup":"primary","sideEffects":[],"condition":null},
    {"key":"doing-to-cancel-via-cancel",     "fromStatus":"doing", "toStatus":"cancel","action":"cancel","branch":null,"label":{"zh_cn":"取消","en":"Cancel"},"roles":[],"accounts":[],"requireComment":true,"enabled":true,"isCustom":false,"buttonLabel":null,"buttonIcon":null,"buttonOrder":0,"buttonGroup":"danger","sideEffects":[],"condition":null},
    {"key":"pause-to-doing-via-activate",    "fromStatus":"pause", "toStatus":"doing","action":"activate","branch":null,"label":{"zh_cn":"激活","en":"Activate"},"roles":[],"accounts":[],"requireComment":false,"enabled":true,"isCustom":false,"buttonLabel":null,"buttonIcon":null,"buttonOrder":0,"buttonGroup":"primary","sideEffects":[],"condition":null},
    {"key":"cancel-to-doing-via-activate",   "fromStatus":"cancel","toStatus":"doing","action":"activate","branch":null,"label":{"zh_cn":"激活","en":"Activate"},"roles":[],"accounts":[],"requireComment":false,"enabled":true,"isCustom":false,"buttonLabel":null,"buttonIcon":null,"buttonOrder":0,"buttonGroup":"primary","sideEffects":[],"condition":null},
    {"key":"closed-to-doing-via-activate",   "fromStatus":"closed","toStatus":"doing","action":"activate","branch":null,"label":{"zh_cn":"激活","en":"Activate"},"roles":[],"accounts":[],"requireComment":false,"enabled":true,"isCustom":false,"buttonLabel":null,"buttonIcon":null,"buttonOrder":0,"buttonGroup":"primary","sideEffects":[],"condition":null}
  ],
  "entries": ["wait"]
}
```

### 16.2 名词表

| 术语 | 解释 |
|---|---|
| **objectType** | epic / requirement / story / bug / task 之一 |
| **scope** | global（系统默认）或 product（产品级覆盖） |
| **definition** | 一份 JSON，包含 statuses、transitions、entries |
| **status** | 状态节点，对应 `zt_xxx.status` 字段存的 key |
| **transition** | 一条状态转移规则（fromStatus → toStatus via action+branch） |
| **branch** | 同一 action 下的业务侧分支语义（如 review 的 pass/reject） |
| **entry** | 入口状态，对象创建后允许直接进入的状态 |
| **customStatus** | 用户自定义的状态（非禅道内置） |
| **customButton** | 用户自定义的动作按钮（绑定到转移） |
| **transitionDecision** | `transition()` 返回的决策对象（包含 ok/toStatus/error） |
| **isClickable** | ZenTao 静态方法，用于按钮可见性判断 |
| **wasUnrestricted** | 决策对象的字段，true 表示工作流未启用，本次未约束 |

### 16.3 参考资料

- 禅道企业版工作流文档：https://www.zentao.net/book/zentaopmshelp/43.html
- ZUI3 Zin 组件文档：https://openzui.com/zin
- Mermaid stateDiagram 语法：https://mermaid.js.org/syntax/stateDiagram.html
- ZenTao 开发规范：本仓库 `.claude/rules/` 目录

### 16.4 与禅道既有规范的协调

- **菜单挂载**：使用 `extension/custom/admin/ext/config/statetransition.php`（标准菜单扩展点，非 hook）
- **多语言**：使用 `module/statetransition/lang/{locale}.php`（标准 lang 文件）
- **权限注册**：使用 `zt_grouppriv` 表（标准权限机制）
- **审计日志**：使用 `module/action/` 既有机制
- **测试**：使用 `test/runtime/ztf` 运行器（标准测试框架）

### 16.5 反 bug 设计自查清单

实现完成后请逐项确认：

- [ ] 业务方法（story/bug/task 的 review/change/close 等）**不含**硬编码 target status（grep `\$status\s*=\s*['"](closed|changing|...)['"]` 在状态变更方法内为空）
- [ ] 后台配置页的"启用"checkbox 与 statuses/transitions 编辑区在**同一 form**、**同一次 POST** 提交
- [ ] 后台配置页保存按钮无"分别保存 statuses 和 transitions"的中间态
- [ ] `change.html.php` / `edit.html.php` 的 status picker **不**含硬编码默认值，统一调 `getDefaultToStatus()` 或 `getEntryStatusOptions()`
- [ ] `review()` 调用 `transition(branch: $result)`，分支由业务决定、目标由配置决定
- [ ] `updateKanbanCell` 路径调用 `assertStatusChange()`，看板拖拽受约束
- [ ] `batch*` 方法用事务包裹，任一失败回滚整批
- [ ] 自定义按钮点击经 `applyWorkflowTransition()` 通用入口，无直接 SQL update status
- [ ] 删除自定义状态前执行 §4.3 的引用校验
- [ ] 首次保存新定义时 `enabled` 默认为 `'1'`（防止"未勾选启用"灰度陷阱）

### 16.6 实现优先级清单（Milestone 拆分参考）

按依赖与可独立验收程度，建议拆为 5 个 milestone：

#### M1 — 模块骨架与数据层（约 2 天）
- 新建 `module/statetransition/` 目录结构
- `db/install.sql`、`db/uninstall.sql`
- `config/config.php`（对象清单、action 白名单、默认定义）
- `model.php` 基础方法：`getDefaultDefinition` / `validateDefinition` / `normalizeDefinition` / `getDefinition` / `saveDefinition`
- 单测：`validatedefinition.php` / `normalizedefinition.php` / `defaultdefinition.php`

#### M2 — 运行时核心 API（约 2 天）
- `transition()` / `assertStatusChange()` / `isActionAllowed()` / `assertEntryState()`
- `transitionDecision` 类
- 缓存机制
- 单测：`transition.php` / `assertstatuschange.php` / `isactionallowed.php` / `assertentrystate.php`

#### M3 — 业务模块切入点（约 3 天）
- story：11 处方法修改 + `applyWorkflowTransition` + `isClickable` 改造
- bug：6 处方法修改 + `applyWorkflowTransition` + `isClickable` 改造
- task：11 处方法修改（含看板拖拽）+ `applyWorkflowTransition` + `isClickable` 改造
- 集成测试：3 个 integration 文件
- E2E：基础 review/change/close/resolve/start/finish 场景

#### M4 — 后台 UI 与详情页（约 3 天）
- `control.php` + `ui/browse.html.php` + `css/browse.ui.css` + `js/browse.ui.js`
- 新增状态/转移 modal
- 表单 picker 联动（change/edit/create）
- 详情页流程图注入
- 列表 featureBar 扩展
- 多语言
- E2E：后台配置全流程

#### M5 — 自定义按钮、迁移、上线准备（约 1.5 天）
- 自定义按钮渲染 + JS 逻辑
- 旧 hook 数据迁移验证
- 性能测试与缓存调优
- 旧 hook 文件清理脚本
- 文档：管理员手册 + 升级指南

每个 milestone 完成后运行完整测试套件（单测 + 集成 + E2E），全部 PASS 才进入下一个。

---

**文档结束**
