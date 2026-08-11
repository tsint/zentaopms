# Worklogs API 接口说明

## 基本信息

| 项 | 值 |
|---|---|
| **地址** | `POST http://你的禅道地址/api.php/v1/worklogs` |
| **认证** | 请求头传 AK/SK（一对固定凭证，永不过期） |
| **功能** | 批量登记工时（支持 task/story/bug 混合提交） |
| **提交方式** | JSON |

---

## 请求头

| 参数 | 值 | 说明 |
|---|---|---|
| `Content-Type` | `application/json` | 固定 |
| `X-AK` | 你的 AccessKey | 认证用，与禅道 config 比对 |
| `X-SK` | 你的 SecretKey | 认证用，与禅道 config 比对 |

---

## 请求体

```json
{
  "worklogs": [
    {
      "objectType": "task",
      "objectID": 123,
      "account": "zhangsan",
      "consumed": 2,
      "work": "完成登录模块开发"
    }
  ]
}
```

---

## 参数说明

| 字段 | 必填 | 类型 | 说明 | 示例 |
|---|---|---|---|---|
| `objectType` | 是 | string | 对象类型：`task` / `story` / `bug` | `"task"` |
| `objectID` | 是 | int | 对象 ID | `123` |
| `account` | 是 | string | 工时归属人：可填**禅道账号**或 **GitLab用户名**（GitLab用户名会按后台「人员管理→GitLab用户」映射表自动转成禅道账号） | `"zhangsan"` 或 `"zhangsan_gl"` |
| `consumed` | 是 | number | 工时（小时，>0） | `2` |
| `work` | 是 | string | 工作内容 | `"修复登录bug"` |
| `date` | 否 | string | 日期，默认今天 | `"2026-08-01"` |
| `left` | 否 | number | 剩余工时（仅 task 适用） | `3` |
| `execution` | 否 | int | 需求关联多个迭代时必填 | `101` |

---

## 返回值

### 成功（200）

```json
{
  "message": "登记成功",
  "created": 2,
  "items": [
    {"index": 0, "objectType": "task", "objectID": 123, "account": "zhangsan", "consumed": 2},
    {"index": 1, "objectType": "bug", "objectID": 7, "account": "lisi", "consumed": 0.5}
  ]
}
```

### 业务校验失败（400）

```json
{"error": "第2条失败: 账号 lisi2 不存在"}
```

### 认证失败（401）

```json
{"error": "认证失败"}
```

---

## 状态码

| 码 | 含义 |
|---|---|
| 200 | 全部登记成功 |
| 400 | 业务校验失败（整批回滚，一条都没写入） |
| 401 | 认证失败（AK/SK 错误或来源 IP 不允许） |

---

## 调用示例

### 单条

```bash
curl -X POST "http://你的禅道/api.php/v1/worklogs" \
  -H "X-AK: 你的AK" \
  -H "X-SK: 你的SK" \
  -H "Content-Type: application/json" \
  -d '{
    "worklogs": [
      {"objectType":"task","objectID":123,"account":"zhangsan","consumed":2,"work":"开发登录接口"}
    ]
  }'
```

### 多条（混合类型）

```bash
curl -X POST "http://你的禅道/api.php/v1/worklogs" \
  -H "X-AK: 你的AK" \
  -H "X-SK: 你的SK" \
  -H "Content-Type: application/json" \
  -d '{
    "worklogs": [
      {"objectType":"task","objectID":123,"account":"zhangsan","consumed":2,"work":"开发登录接口"},
      {"objectType":"story","objectID":45,"account":"lisi","consumed":1,"work":"需求评审"},
      {"objectType":"bug","objectID":7,"account":"wangwu","consumed":0.5,"work":"修空指针"}
    ]
  }'
```

### 指定日期 + 剩余工时

```bash
curl -X POST "http://你的禅道/api.php/v1/worklogs" \
  -H "X-AK: 你的AK" \
  -H "X-SK: 你的SK" \
  -H "Content-Type: application/json" \
  -d '{
    "worklogs": [
      {"objectType":"task","objectID":123,"account":"zhangsan","consumed":2,"work":"写接口","date":"2026-08-01","left":3}
    ]
  }'
```

---

## 注意事项

- 同一批次内所有工时**要么全部成功，要么全部失败**（事务式）
- `account` 可填禅道账号或 GitLab用户名；GitLab用户名会按后台「GitLab用户」映射表自动转成禅道账号，转换后该禅道账号必须存在，否则整批失败
- `objectID` 必须是对应类型存在的对象，否则整批失败
- `consumed` 必须 > 0
- `date` 默认今天，不填即可
- `left` 仅对 task 有效，story/bug 忽略
- `execution` 仅当 story 关联多个迭代时必填，否则不填
