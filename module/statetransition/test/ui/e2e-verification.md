# Statetransition E2E Verification Playbook

Use this playbook when validating statetransition UI changes.

## Playwright Runtime

- Prefer Playwright MCP for ad hoc browser inspection during implementation.
- Do not create temporary npm projects just to run tests.
- Test scripts must import Playwright through `./playwright-loader.mjs`.
- `playwright-loader.mjs` resolves Playwright in this order:
  1. `PLAYWRIGHT_MODULE`, when explicitly provided.
  2. Project/local `playwright` package.
  3. Global npm packages from `npm root -g`.
  4. Global Bun packages inferred from `bun pm -g bin`.
  5. The real path behind the `playwright` command on `PATH`.

## Running

Run against an already-started ZenTao dev server:

```bash
node module/statetransition/test/ui/browse_mermaid_tabs_test.mjs
node module/statetransition/test/ui/mermaid_repeat_switch_test.mjs
node module/statetransition/test/ui/detail_actions_filter_test.mjs
```

For ER/UR coverage, provide DB connection values if they are not the defaults:

```bash
DB_HOST=127.0.0.1 DB_USER=zentao DB_PASSWORD=zentao123456 DB_NAME=zentao \
  node module/statetransition/test/ui/er_ur_object_types_mermaid_test.mjs
```

To let the detail action test create its own fixtures inside a Docker Compose DB container:

```bash
BASE_URL=http://127.0.0.1:8080 DB_CONTAINER=zentaopms-db-1 DB_ROOT_PASSWORD=Root1234! \
  node module/statetransition/test/ui/detail_actions_filter_test.mjs
```

To temporarily enable ER/UR object types during that test and restore the original switches afterwards:

```bash
BASE_URL=http://127.0.0.1:8080 DB_CONTAINER=zentaopms-db-1 E2E_ENABLE_ER_UR=1 \
  node module/statetransition/test/ui/detail_actions_filter_test.mjs
```

## Cleanup

If a test starts services manually, stop them before finishing the task. Do not leave PHP dev servers, DB containers, or temporary Playwright installs running.
