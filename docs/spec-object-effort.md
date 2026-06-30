# Bug/Story Object Effort Spec

## Scope

Add an extension-only effort module under `extension/custom` so Bug and Story/Requirement records can receive workhour entries without changing core Bug, Story or Task table structures.

The extension owns an independent table, `zt_objecteffort`, and links records by `objectType + objectID`. Core views are extended through view hooks. Project and execution statistics are extended by hook files that merge the new table into existing calculation points.

## Decisions

- Object effort is separate from task effort. It never updates `zt_task`, `zt_bug` or `zt_story`.
- Supported object types are `bug`, `story` and `requirement`. Requirement records are stored with `objectType=requirement` while using the Story table as the source object.
- Each effort stores a snapshot of `product`, `project` and `execution` at recording time.
- The record dialog follows the Task workhour batch-entry pattern: it opens with 3 input rows, allows adding rows up to 10, and submits multiple effort records in one save.
- The record dialog does not expose an editable `estimate` column. `left` is optional when recording or editing object effort. If blank, it is calculated from the object's current total estimate minus already consumed hours minus the current submitted consumed hours, with batch rows calculated in submitted order and never below zero.
- A Story/Requirement can belong to more than one execution. The recorder must choose the target execution in that case; the extension never assigns effort to an arbitrary execution. A submitted execution must be an active Story/Requirement association. A single association is selected automatically. A Story/Requirement linked only at project level is attributed to that project without inventing an execution; multiple project associations require explicit selection. A completely unassigned object keeps product-level effort with `project=0` and `execution=0`.
- Statistics use task effort plus object effort. An object effort contributes:
  - `estimate`: the maximum estimate recorded for the object in the execution.
  - `consumed`: the sum of non-deleted consumed hours.
  - `left`: the latest non-deleted left value per object and execution.
- Scope and workhour progress keep their native units separate. Newly linked Story/Requirement estimates continue to change the execution burn `storyPoint` series; object-effort estimate/consumed/left change the hour-based burn and project/execution workhour progress. Story points are never added to hours.
- Closed/deleted Bug or Story objects cannot receive new effort. Existing non-deleted efforts remain auditable.
- Closing/deleting a Bug or Story makes its remaining hours zero in subsequent summaries. Project totals retain historical estimate/consumed, while execution burn no longer treats the closed object's estimate or left as remaining scope.
- If a Bug is converted to a task or a Story has tasks, the extension does not automatically suppress object effort. Avoiding duplicate accounting is an operational rule: use direct object effort only for work that is not already registered on tasks.
- Create, edit and delete refresh affected project statistics and recompute today's burn rows for both old and new executions. Closing, activating or deleting a linked Bug/Story triggers the same refresh from the action hook after its state is persisted. Historical burn rows remain snapshots and are not rewritten.

## Role-based Validation Brainstorm

The acceptance criteria were challenged independently from these roles before implementation:

| Role | Validation focus |
| --- | --- |
| Product owner / customer | New requirements remain visible as scope growth; recording effort does not require converting a Bug or Story into a Task. |
| Project manager | Bug and Story hours roll into the correct project/execution; unplanned project requirements affect project hours without polluting an arbitrary sprint; story points and hours are not mixed. |
| Developer | The implementation lives under `extension/custom`, uses framework hooks and DAO APIs, and does not modify core Bug, Story or Task schemas or behavior. |
| QA engineer | Create/edit/delete, invalid numbers, future dates, closed/deleted objects, multi-execution ambiguity, scope growth and regression of Task effort are testable. |
| Security reviewer | List/detail affordances and direct endpoints enforce privileges; forged object, project and execution IDs are rejected; ownership controls edit/delete; output is escaped and deletion is auditable. |
| Operations / plugin maintainer | Install/uninstall SQL is prefix-safe and idempotent where appropriate; localized permission/action labels exist; generated hook targets parse; upgrades do not require core patches. |

Conflicts were resolved by keeping workhour (`estimate/consumed/left`) and scope (`storyPoint`) as separate series, and by requiring explicit attribution whenever a Story/Requirement has more than one valid target.

## Acceptance Criteria

- Bug and Story detail pages expose an Effort button through hook files when the user has `objecteffort-record` privilege and the object is active.
- Bug and Story list pages expose row-level Effort links through hook-injected script.
- My Work task/story/requirement/bug lists expose direct row-level effort entry. Task rows keep the native Task workhour dialog; Story/Requirement/Bug rows open the object-effort dialog.
- Story/Requirement edit forms expose inline object-effort entry. Bug edit form keeps a single modal workhour icon and does not add `estimate`, `consumed` or `left` fields to the Bug core edit form.
- Direct controller/model access repeats privilege, object type, object existence and status checks.
- Create, edit and delete are auditable through `zt_action`; delete is soft-delete.
- Input validation rejects invalid object types, missing objects, future dates, `consumed <= 0`, `left < 0`, and non-numeric hours.
- Story/Requirement effort is attributed to the explicitly selected linked execution. Missing selection for a multi-execution Story/Requirement and forged/unlinked execution IDs are rejected.
- A user can edit/delete their own effort. Admins can edit/delete any effort.
- Edit/delete also require the corresponding `objecteffort-edit` or `objecteffort-delete` privilege.
- Project stats (`estimate`, `consumed`, `left`, `progress`) include object effort after `program->refreshProjectStats()`.
- Execution rows (`estimate`, `consumed`, `left`, `progress`) include object effort attributed to that execution after the same refresh.
- Execution burn computation includes object effort in today's `zt_burn` row.
- Saving, moving or deleting object effort immediately refreshes affected project statistics and today's execution burn rows.
- Linking another estimated Story/Requirement to an execution increases that execution's current burn `storyPoint`, so ongoing customer scope additions remain visible independently of hour-based progress.
- Core task effort behavior remains unchanged when no object effort exists.
- New user-visible text is in zh-cn and en language files.

## TDD Coverage

- `extension/custom/objecteffort/test/model/record.php`
  - Records Bug effort.
  - Rejects invalid hour values.
  - Records batch Story effort and auto-computes blank `left` values.
  - Records Story effort.
  - Rejects ambiguous or unlinked Story execution attribution and routes selected effort to the correct execution.
  - Updates and soft-deletes effort.
  - Summarizes execution/project object effort.
  - Verifies core Task table rows are not mutated.
- `extension/custom/objecteffort/test/model/discovery.php`
  - Verifies the custom module is resolved from `extension/custom/objecteffort`.
  - Verifies Bug/Product view hooks are discoverable through extension paths.
  - Verifies execution/program/project hook files are merged into framework cache files.
  - Verifies list hooks support dtable cells and async table rendering.
  - Verifies My Work list action config exposes direct effort entry for Task, Story/Requirement and Bug.
  - Verifies the object-effort record view uses batch rows with 3 default rows, a 10-row maximum, no editable `estimate` column and optional `left`.
  - Verifies the native Task record-workhour view supports up to 10 submitted rows.
  - Verifies task edit keeps estimate fields editable for administrators when team or parent-task rules would otherwise make them read-only.

Manual checks:

- Open Bug detail/list and Story detail/list pages and verify the injected button opens the record modal.
- Record effort, run `execution->computeBurn()`, and verify burn, execution and project workhour views reflect the added hours.
- Verify no button is shown and direct save fails for deleted/closed objects or users without privilege.
