# Workflow Flowchart Spec

## Scope

Provide an open-source custom extension named `workflowflowchart` for administrators to configure and visualize the state transitions of Epic, Requirement, Story, Bug, and Task objects. Definitions are stored independently and do not alter core tables.

## Definition

Each object type has one definition containing:

- `enabled`: disabled definitions are visual only and do not restrict existing behavior.
- `nodes`: every node has a unique ID, a supported ZenTao status, label, and optional layout position.
- `edges`: every edge has a unique ID, source status, target status, ZenTao action, label, optional allowed roles/accounts, optional required comment, and enabled flag.
- audit fields: creator/editor and timestamps.

Supported actions:

- Bug: `resolve`, `close`, `activate`.
- Epic, Story, and Requirement: `submitreview`, `review`, `change`, `recallreview`, `recallchange`, `close`, `activate`.
- Task: `start`, `restart`, `pause`, `finish`, `close`, `cancel`, `activate`.

## Validation

1. Object type must be `epic`, `story`, `requirement`, `bug`, or `task`.
2. Definition JSON must contain arrays for nodes and edges.
3. Node and edge IDs must be non-empty and unique.Markdown PDF
4. Node statuses and edge actions must belong to the selected object type.
5. Every edge endpoint must refer to an existing node.
6. Duplicate enabled edges with the same source, target, and action are rejected.
7. Roles and accounts are normalized to unique non-empty strings.
8. Invalid definitions are rejected atomically and leave the previous definition unchanged.

## Runtime Rules

1. Missing or disabled definitions preserve core ZenTao behavior.
2. An enabled definition allows a transition only when an enabled edge matches source, target, and action.
3. If an edge restricts roles or accounts, satisfying either list grants actor access.
4. A required-comment edge rejects an empty comment.
5. Denials populate DAO validation errors before any object update or action history is written.
6. Non-transition operations remain unaffected.

## UI and Permissions

1. Administrators can switch among Epic, Requirement, Story, Bug, and Task, add/edit/delete edges, enable enforcement, reset defaults, and save.
2. The workflow page must start at the top of its content: the tabs, title/action area, and first workflow panel are visible without scrolling past a large blank area.
3. The editor renders the flow directly as a readable status board plus transition list; the configured process must remain visible without relying on a canvas library or manually positioned overlapping connector lines.
4. The state board groups transitions by source status. Each source status is a stable column/card containing its outgoing transitions as `action -> target` rows, making dense flows readable and easy to adjust.
5. Users who can open Epic/Requirement/Story/Bug/Task details can see a read-only embedded workflow section for that object type; the section must not depend on toolbar injection.
6. Only administrators with `manage` permission can save definitions.
7. All user-visible text is provided in Simplified Chinese and English language files.

## Acceptance Evidence

- Model tests cover defaults, schema validation, duplicate edges, authorization, comment requirements, disabled behavior, and denied transitions.
- Hook discovery verifies every supported transition action is guarded.
- Tests verify all five object types are available, render readable grouped flow HTML, and include the embedded detail workflow for Epic/Requirement/Story/Bug/Task.
- Browser/page checks verify the workflow page has no large blank first viewport, object switching includes Epic/Requirement/Story/Bug/Task, edge editing remains available, read-only view renders immediately, and mobile layout does not force unusable horizontal scrolling.
- SQL installation and uninstallation are reversible and do not change core table schemas.
