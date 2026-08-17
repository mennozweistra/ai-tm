# `tm` — CLI Reference

This document specifies every CLI command `tm` exposes: arguments, output shape, and error codes.

For scope and intent, see `../../spec.md`. For `tm`'s architecture, see `../architecture/architecture.md`. For the data model the commands operate on, see `../data-model.md`. For the equivalent MCP tool surface, see `mcp.md`.

## 1. Conventions

### 1.1 Output

- Default: JSON on stdout. Errors: JSON on stderr.
- `--human` flag on read commands produces human-readable rendering. Not exhaustive; primary path is JSON.
- Exit code: `0` on success, non-zero on error.

### 1.2 Response envelope

Every command produces output matching one of two shapes.

Success (stdout, exit 0):

```json
{ "ok": true, "data": { ... } }
```

Error (stderr, exit non-zero):

```json
{ "ok": false, "error": { "code": "...", "message": "...", "type": "..." } }
```

The `data` payload shape depends on the command. Throughout this document, payloads are shown as the inner object; on the wire they are wrapped in `{"ok": true, "data": ...}`.

### 1.3 Argument conventions

- Every entity is referenced by its integer `id` via a typed flag: `--project <id>`, `--ticket <id>`, `--phase <id>`, `--task <id>`. Ids are returned by the corresponding `*_add` and `*_list` commands.
- `--project` is **not** required when referencing a ticket; ticket ids are globally unique.
- Project creation (`project add`) takes `--name` and `--path` instead of `--project`, since no id exists yet.
- All other parameters are flags: `--name`, `--description`, `--ai-description`, `--result`, `--ai-result`, `--path`, `--auto-status`, `--status`, `--type`, `--title`, `--ai-content`, `--before`, `--after`, `--deep`, `--archived`, `--sort`, `--human`.
- Boolean flags (`--auto-status`) accept `true`, `false`, `1`, or `0`.
- `set` commands take per-field flags. Multiple field flags in one call are applied as a single transaction.
- `--before <id>` and `--after <id>` on `add` and `move` set ordering position. They are mutually exclusive.

### 1.4 Error codes

Errors use a small set of stable codes. Callers branch on `code`, not on `message`.

| Code | Meaning |
|---|---|
| `not_found` | Referenced entity does not exist. |
| `already_exists` | Entity with the given identifier already exists. |
| `invalid_argument` | Argument is missing, malformed, or unparseable. |
| `invalid_status` | Status value not in the configured set for the entity type. |
| `invalid_log_type` | Log type not in the configured set. |
| `invalid_verification` | Verification value not in the configured set (`unverified`, `met`, `unmet`). |
| `forbidden_auto_status_transition` | `project set --auto-status true` refused on a project whose `auto_status` is currently `false`. The toggle is one-way. |
| `has_children` | Hard-delete refused because the entity has child entities (e.g. phase with tasks). |
| `has_logs` | Hard-delete refused because the entity has log entries. |
| `schema_outdated` | Database schema is older than expected. Not reachable from a command since ticket 269: every entry point migrates its own store at start-up. |
| `schema_ahead` | The store carries migrations this release does not ship — `tm` was downgraded below the version that wrote it. Nothing is migrated down automatically; re-install the newer release or restore a backup. Written by `bin/tm` to stderr with exit code 1, before any command runs. |
| `config_invalid` | `ai-lib`'s `LogService` could not resolve the log entry's scope: the given `--task` belongs to a different phase than the given `--phase`, or none of `--ticket`/`--phase`/`--task` was given at all. (Before ticket 172 this code also covered a missing or malformed `~/.ai-tm/config.toml`; that file no longer exists.) |
| `internal` | Unexpected error. |

### 1.5 Database selection (`TM_DB`)

`bin/tm` opens the SQLite database at the path in the `TM_DB` environment variable. If `TM_DB` is unset or empty, it opens `~/.ai-tm/store.db`. This is checked once, when `bin/tm` starts; a fresh install with no `TM_DB` set behaves exactly as before this variable existed.

`TM_DB` selects only the database file. The data directory `tm setup` creates is always at `~/.ai-tm`, regardless of `TM_DB`.

The parent directory of the `TM_DB` path no longer has to exist. Through ticket 269 it did — SQLite creates the database file but not its directory, and `bin/tm` opens the database as it starts, so a path in a missing directory failed immediately with `unable to open database file`. Self-migration (see §9 and `../architecture/architecture.md` §8) now creates the directory, the file, and the schema before the connection is opened, at every entry point. The same holds for `~/.ai-tm` itself on a first run after an install.

Use an absolute path for `TM_DB`. A relative path resolves against the current working directory of whichever process reads it, and `bin/tm`, a freshly launched `bin/tm-mcp`, and the dashboard's `public/index.php` can each be started from a different working directory — so a relative `TM_DB` value can silently resolve to a different file per process instead of the one shared database the workflow below depends on.

### 1.5.1 Test-database workflow for a functional review

`TM_DB` is how a functional review points every part of the toolset at one disposable database instead of the production one at `~/.ai-tm/store.db`.

1. Create a fresh, empty, migrated database:

   ```
   TM_DB=/tmp/review.db bin/tm migrate
   ```

   This creates `/tmp/review.db` if it does not exist and applies every migration to it. Nothing is copied from `~/.ai-tm/store.db`. Since ticket 269 any `bin/tm` command does the same thing on the way to running — this one just reports what it applied.

2. Point every process the review launches at that same path, by setting `TM_DB` in that process's environment: `bin/tm` for further CLI calls, a freshly launched `bin/tm-mcp` if the review needs an MCP server on the test database, and the dashboard (`bin/ai-dashboard`, whose `public/index.php` reads `TM_DB`).

3. Seed only the data the review needs through `bin/tm` calls with `TM_DB` set, for example `TM_DB=/tmp/review.db bin/tm ticket add --name "..." --path "..."`.

`TM_DB` is read once, when a process starts. Every one of these processes needs `TM_DB` set in its own environment when it launches — there is no way to redirect a process already running. In particular, the persistent session `tm-mcp` that Claude Code launches from the toolset root's `.mcp.json` starts with no `TM_DB` set and stays on `~/.ai-tm/store.db` for the life of the session, by design: it is the workflow's bookkeeping surface (tickets, phases, tasks, logs for the review work itself), not the target of the review. See `mcp.md` §8.

## 2. Project

### 2.1 `tm project add`

```
tm project add --name <name> --path <path> [--description "..."] [--ai-description "..."] [--auto-status true|false]
```

Creates a project. `name` must be globally unique. `path` is recorded but not validated for existence. `auto_status` defaults to `true`.

Errors: `already_exists`, `invalid_argument`.

Response:
```json
{ "id": 7, "name": "...", "description": "", "ai_description": "", "path": "...", "auto_status": true, "created_at": "...", "archived_at": null }
```

### 2.2 `tm project list`

```
tm project list [--archived]
```

Lists active projects. `--archived` includes archived ones.

Response:
```json
{ "projects": [ {"id": 7, "name": "...", "path": "...", "auto_status": true, "created_at": "...", "archived_at": null}, ... ] }
```

### 2.3 `tm project show`

```
tm project show --project <id> [--deep] [--human]
```

Returns the project. `--deep` includes summarized child tickets.

Errors: `not_found`.

### 2.4 `tm project set`

```
tm project set --project <id> [--name <name>] [--description "..."] [--ai-description "..."] [--path <path>] [--auto-status true|false]
```

Updates mutable fields. `id` cannot be changed.

`--auto-status` is one-way: `true → false` is allowed; `false → true` is refused with `forbidden_auto_status_transition`. See `../architecture/architecture.md` §8.1.

Errors: `not_found`, `invalid_argument`, `already_exists` (on `name` collision), `forbidden_auto_status_transition`.

### 2.5 `tm project archive` / `tm project restore`

```
tm project archive --project <id>
tm project restore --project <id>
```

Sets or clears `archived_at`. Idempotent.

Errors: `not_found`.

## 3. Ticket

### 3.1 `tm ticket add`

```
tm ticket add --project <id> --name "..." --template <name> [--description "..."] [--ai-description "..."] [--status <s>]
```

Creates a ticket under the given project. `status` defaults to `pending`. `--template` is required: it names an existing template whose phases and tasks are copied onto the new ticket. Passing no template, an empty value, or an unknown name is rejected with `invalid_argument`; the error message lists the available template names.

Errors: `not_found` (project), `invalid_argument`, `invalid_status`.

Response:
```json
{ "id": 13, "project_id": 7, "name": "...", "description": "", "ai_description": "", "status": "pending", "created_at": "...", "updated_at": "...", "archived_at": null }
```

### 3.2 `tm ticket list`

```
tm ticket list --project <id> [--archived]
```

### 3.3 `tm ticket show`

```
tm ticket show --ticket <id> [--deep] [--outline] [--human]
```

`--deep` adds: phases, tasks, log entries (all scopes), status transitions for the ticket.

`--outline` returns a compact tree instead: the ticket, its phases (each with its tasks), and its requirements, with long-form fields (`description`, `ai_description`, `result`, `ai_result` and their per-node equivalents) omitted — their keys are absent from the response, not present with an empty value. Logs and status transitions are omitted entirely. This is for orienting on a ticket's shape without pulling in enough text to exceed an MCP client's output limit; fetch a specific task or requirement in full with `tm task show` / `tm requirement show` once you know its id. If both `--outline` and `--deep` are passed, `--outline` wins — there is no combined shape.

### 3.4 `tm ticket set`

```
tm ticket set --ticket <id> [--name "..."] [--description "..."] [--ai-description "..."] [--status <s>]
```

Multiple field flags are applied transactionally.

Errors: `not_found`, `invalid_argument`, `invalid_status`.

### 3.5 `tm ticket archive` / `tm ticket restore`

```
tm ticket archive --ticket <id>
tm ticket restore --ticket <id>
```

Sets or clears `archived_at`. Idempotent.

Errors: `not_found`.

## 4. Phase

### 4.1 `tm phase add`

```
tm phase add --ticket <id> --name "..." [--description "..."] [--ai-description "..."] [--status <s>] [--max-attempts <n>] [--before <id> | --after <id>]
```

Position defaults to append. `status` defaults to `pending`. `--max-attempts` sets the phase's retry budget (ticket 179): 0 (the default) is an ordinary phase; 2 or more makes it a budgeted phase (check-phase) that grind re-runs as a full pass until clean or the budget is spent. Exactly 1 is rejected.

### 4.2 `tm phase list`

```
tm phase list --ticket <id>
```

Returns phases in `order`.

### 4.3 `tm phase show`

```
tm phase show --phase <id> [--deep] [--human]
```

`--deep` adds: tasks, log entries scoped to the phase, status transitions for the phase.

### 4.4 `tm phase set`

```
tm phase set --phase <id> [--name "..."] [--description "..."] [--ai-description "..."] [--status <s>] [--max-attempts <n>] [--attempts <n>]
```

When the phase's project has `auto_status = true`, a `--status` change triggers recompute of the parent ticket status in the same transaction. See `../architecture/architecture.md` §8.1.

`--max-attempts` updates the phase's retry budget. `--attempts` updates the running counter of finished passes grind has made against this phase's budget — mirroring the same fields on Task.

Errors: `not_found`, `invalid_argument`, `invalid_status`.

### 4.5 `tm phase move`

```
tm phase move --phase <id> (--before <id> | --after <id>)
```

Intra-parent only. The reference id must be a sibling phase.

Errors: `not_found`, `invalid_argument`.

### 4.6 `tm phase delete`

```
tm phase delete --phase <id>
```

Hard delete. Refused if the phase has child tasks (`has_children`) or any phase-scoped log entries (`has_logs`).

Errors: `not_found`, `has_children`, `has_logs`.

## 5. Task

### 5.1 `tm task add`

```
tm task add --phase <id> --name "..." --model <model> [--description "..."] [--ai-description "..."] [--status <s>] [--max-attempts <n>] [--before <id> | --after <id>]
```

`name` is required. `status` defaults to `pending`. `--max-attempts` sets the maximum number of grind retries; 0 (the default) means stop for a human on failure.

`--model` is required: a free-form model identifier string (for example `haiku`, `sonnet`, `opus`) naming which model grind should use to run this task, or the literal value `null` (case-insensitive: `null`, `NULL`, `Null` all match) to mean no model — the correct value for `actor: human` tasks, which no agent ever runs. There is no schema-level default the way there is for `--actor`: omitting `--model` entirely is rejected with `invalid_argument`, it does not silently fall back to anything. A blank or whitespace-only value (other than the `null` sentinel) is also rejected with `invalid_argument`.

The `null` string sentinel is a CLI-only convention, needed because CLI option values are always strings and there is no other way to express the storage-level `NULL` on this command line. The MCP surface has no equivalent sentinel: `tm_task_add` takes a native JSON argument and a caller passes a real JSON `null` directly for the same "no model" case. See `mcp.md` §3.

### 5.2 `tm task list`

```
tm task list [--phase <id>] [--project <id>] [--archived] [--created-from <date>] [--created-to <date>] [--started-from <date>] [--started-to <date>] [--finished-from <date>] [--finished-to <date>] [--order-by <name>]
```

`--phase` is now optional: omit it to list tasks across every phase, optionally scoped by `--project`. `--project` scopes the list to one project. `--created-from`/`--created-to`, `--started-from`/`--started-to`, and `--finished-from`/`--finished-to` are plain `YYYY-MM-DD` dates bounding `created_at`, the derived start date, and the derived finish date (see `../data-model.md` §3.4 — start is the first transition to `active`, finish is the last transition to `done`). Matching compares the date part of the stored timestamp — the first ten characters — against the given date; nothing is converted and no timezone is involved. `--order-by` is one of `order` (default), `created`, `started`, `finished`; an unknown value is `invalid_argument`.

All date and scope filters combine with AND, never OR — a call cannot ask for "created in July OR started in July" in one invocation. There is no `--limit` or `--offset`: a call with no filters and no `--phase` returns every task in the database that survives the archive rule. Unless `--archived` is passed, a task is hidden when the task, its phase, its ticket, or its project is archived.

Ordering: scoped to a phase with the default `--order-by order`, tasks come back in phase position as before. With no `--phase` and `--order-by order`, tasks come back by ascending task id. For `created`, `started`, or `finished`, tasks come back oldest first, a task with no value for that column sorts last, and task id is the tiebreak. See `mcp.md` §3a for the identical rule stated once for both surfaces.

Dates are plain `YYYY-MM-DD` strings compared against the date part of the stored timestamp. No timezone is involved anywhere in this comparison.

Response tasks now include `started_at` (nullable), `finished_at` (nullable), `ticket_id`, and `project_id` alongside the existing fields.

### 5.3 `tm task show`

```
tm task show --task <id> [--deep] [--human]
```

`--deep` adds: log entries scoped to the task, status transitions for the task.

Both the flat response (no `--deep`) and the deep response now include the derived `started_at` and `finished_at` fields (see `../data-model.md` §3.4).

### 5.4 `tm task set`

```
tm task set --task <id> [--name "..."] [--description "..."] [--ai-description "..."] [--result "..."] [--ai-result "..."] [--status <s>] [--max-attempts <n>] [--attempts <n>] [--actor <agent|human>] [--model <model>] [--phase <new-phase-id>]
```

`--phase` reassigns the task to a different phase, appending it. The old phase's remaining siblings are renumbered to close the gap. To position the task within the new phase, follow with `task move`.

Every status change between valid statuses is permitted, including `pending → done`, `pending → active`, `active → blocked`, `done → pending`, and `blocked → active`. `tm` enforces no transition rule of its own.

`--max-attempts` updates the maximum number of grind retries. `--attempts` updates the running counter of fix attempts; grind uses this to track how many retries have been spent.

`--actor` updates who is expected to work the task next. Accepts `agent` or `human` only; any other value is refused with `invalid_status`.

`--model` updates the task's model choice. Optional: omitting it leaves the stored value unchanged, the same omit-means-unchanged convention every other `set` flag follows — the required-with-no-default rule in §5.1 applies only at `task add`. A blank or whitespace-only value is refused with `invalid_argument`, same as on `task add`. Unlike `task add`, `task set` has no `null` string sentinel: the literal word `null` passed to `--model` is stored as that literal three-character string, not converted to storage `NULL`. There is no flag or value that clears an already-assigned model back to null.

When the task's project has `auto_status = true`, status changes and cross-phase reassignment trigger recompute of parent phase (and the new phase, on cross-phase move) and ticket status in the same transaction. See `../architecture/architecture.md` §8.1.

Errors: `not_found`, `invalid_argument`, `invalid_status`.

### 5.5 `tm task move`

```
tm task move --task <id> (--before <id> | --after <id>)
```

Intra-parent only. The reference id must be a sibling task in the same phase.

### 5.6 `tm task delete`

```
tm task delete --task <id>
```

Hard delete. Refused if the task has any log entries.

Errors: `not_found`, `has_logs`.

## 6. Log

### 6.1 `tm log add`

```
tm log add (--ticket <id> | --phase <id> | --task <id>) --type <name> [--title "..."] [--ai-content "..."]
```

Exactly one scope flag is required. The other parents are inferred from the supplied entity. `--title` and `--ai-content` are both optional and default to the empty string.

Errors: `not_found`, `invalid_argument`, `invalid_log_type`.

### 6.2 `tm log list`

```
tm log list (--ticket <id> | --phase <id> | --task <id>) [--sort asc|desc]
```

Default sort is `asc` (oldest first). No type filter, time window, pagination, or limit in v1.

Response:
```json
{ "logs": [ { "id": ..., "ticket_id": ..., "phase_id": ..., "task_id": ..., "log_type": "...", "title": "...", "ai_content": "...", "timestamp": "..." }, ... ] }
```

## 7. Config

Read-only. There is no config file any more (ticket 172): `tm config list` returns `ai-lib`'s hardcoded `Config::default()` directly.

### 7.1 `tm config list`

```
tm config list
```

Returns the full config as JSON.

## 8. Setup

### 8.1 `tm setup`

```
tm setup
```

Creates `~/.ai-tm/` if missing. Idempotent. Never touches `~/.ai-tm/store.db`. Ignores `TM_DB`: the data directory is always at `~/.ai-tm` (see §1.5).

Response:
```json
{ "data_directory_created": true }
```

### 8.2 `tm hook:enable` / `tm hook:disable`

```
tm hook:enable
tm hook:disable
```

Add or remove tm's three optional Claude Code hooks — the end-of-turn `Stop` logging hook and the two `UserPromptSubmit` hooks (start-of-turn task-status, conditional grind) — in the user-level `~/.claude/settings.json` (requirement 682, ticket 269). Hooks are off by default; a fresh `tm` install wires nothing. Neither command reads `TM_DB` or touches the database, so both run even when migrations are pending.

`hook:enable` is idempotent: an entry whose command already matches exactly is left alone, a marker present with a stale command is replaced in place, and a missing marker is appended. `hook:disable` removes exactly the three marker-tagged entries and leaves every other key and every other hook untouched. Both stop without writing anything, and return the `internal` error code, when the settings file exists and is not valid JSON.

Response (both commands):
```json
{ "written": true }
```

`written` is `true` when the file changed, `false` when the command was a no-op (`hook:enable` found all three already current; `hook:disable` found none of the three present).

### 8.3 `tm info`

```
tm info
```

Read-only installation report for a support request (requirement 694, ticket 269): a user with a problem runs `tm info` and sends the output to the owner, who diagnoses from it. A flat map of installation facts — no scoring, no advice engine, safe to paste to someone else (no secrets, no personal data, no ticket/task content).

Unlike every other command, `info` never goes through the self-migrating `Cli\Application` constructor: it is dispatched directly from `bin/tm` before `Application` is built, specifically so that running it on a machine with no `~/.ai-tm` creates nothing. It never calls `SchemaMigrator`; when the database file does not exist, it is never opened either — a `PDO` SQLite connection creates the file on connect, which `info` avoids by checking `is_file()` first. When the database file does exist, `info` opens it read-only and reports its migration level via `ai-lib`'s `SchemaChecker` — the pre-migration state, i.e. what pending/unknown migrations exist right now, not the state a normal command would leave it in after self-migrating.

Response:
```json
{
  "tm_version": "development checkout (a1b2c3d)",
  "php_version": "8.4.1",
  "os": "Linux",
  "install_layout": "development checkout",
  "data_directory": { "path": "/home/user/.ai-tm", "present": true },
  "database": {
    "path": "/home/user/.ai-tm/store.db",
    "present": true,
    "readable": true,
    "pending_migrations": [],
    "unknown_migrations": []
  },
  "hooks": {
    "files": {
      "settings_json": { "path": "/home/user/.claude/settings.json", "exists": true, "valid_json": true },
      "settings_local_json": { "path": "/home/user/.claude/settings.local.json", "exists": false, "valid_json": null }
    },
    "markers": {
      "logging_hook": { "found_in": ["settings_json"] },
      "start_hook": { "found_in": ["settings_json"] },
      "grind_hook": { "found_in": ["settings_json"] }
    }
  },
  "mcp_registration": {
    "user": {
      "path": "/home/user/.claude.json",
      "exists": true,
      "valid_json": true,
      "tm_entry": { "command": "/home/user/.composer/vendor/bin/tm-mcp", "args": [] }
    },
    "local": {
      "path": "/home/user/.claude.json",
      "directory": "/home/user/work/project",
      "exists": true,
      "valid_json": true,
      "tm_entry": null
    },
    "project": {
      "path": "/home/user/work/project/.mcp.json",
      "exists": false,
      "valid_json": null,
      "tm_entry": null
    }
  },
  "user_templates_directory": { "path": "/home/user/.ai-tm/templates", "present": false },
  "tm_db_env": null
}
```

Notes on the fields:

- `tm_version` — `"development checkout (<git short sha>)"` in a checkout layout (no sha suffix if `git rev-parse` finds no repository or fails); the composer-resolved pretty version plus `@<short commit>` (via `Composer\InstalledVersions`) in a vendor install. There are no release tags yet, so this is the cheapest honest fact available in each layout, not a semantic version.
- `install_layout` — `"development checkout"` or `"composer vendor install"`, detected structurally (whether the package's own root carries a `vendor/autoload.php`), the same two-candidate check `bin/tm` itself uses to find the autoloader.
- `database.present` is `false` when the file does not exist; no other `database` key is present in that case. When `present` is `true` but the file is not a valid SQLite database (or otherwise fails to open), `readable` is `false` and an `error` key carries the underlying message instead of `pending_migrations`/`unknown_migrations`.
- `hooks.files.*` reports whether each of `~/.claude/settings.json` and `~/.claude/settings.local.json` exists and, if it exists, whether it parses as JSON. `hooks.markers.*` reports, per marker (`Cli\HookSettingsInstaller`'s three constants), which of the two files it was found in — `["settings_json", "settings_local_json"]` means the hook is wired in both, which Claude Code merges, so it fires twice. This is the double-fire state `hook:enable` leaves a machine in on first run, and precisely what a support report needs to show.
- `mcp_registration.*` reports whether the `tm` MCP server is registered with Claude Code, in each of the three scopes `claude mcp add` writes to: `user` (the `mcpServers` map in `~/.claude.json`, which the README's install instructions use), `local` (the same file's `projects.<working directory>.mcpServers`, private to one directory, hence the extra `directory` field) and `project` (a `.mcp.json` in the working directory, checked into a repository). All three are reported because a report that looked only at the user scope would call a working local- or project-scope registration "not registered". `exists`/`valid_json` describe the file, exactly as under `hooks.files.*`; `tm_entry` is `null` when that scope has no `tm` server, and otherwise carries the `command` and `args` Claude Code launches — which is where a wrong or stale binary path becomes visible. The entry's `env` block is never reported: it can hold an API key, and this report is meant to be pasted to someone else.
- `tm_db_env` is the raw `TM_DB` value when set to a non-empty string, `null` otherwise (mirrors §1.5's fallback rule).

There is no `--human` rendering and no MCP tool mirror: like `tm setup` and `tm migrate`, this is local-machine diagnostics, not a data-plane operation an agent needs mid-session.

## 9. Migrate

```
tm migrate
```

Applies pending schema migrations and reports the result. Idempotent. Runs against the database `TM_DB` selects (see §1.5); running `TM_DB=/path/to/review.db tm migrate` against a path that does not exist yet creates a fresh, empty, fully migrated database there — including its parent directory — without touching `~/.ai-tm/store.db`.

Since ticket 269 this command is a convenience, not a prerequisite: **every** `tm` command migrates its own store before it runs, as do `tm-mcp` and the dashboard (see `../architecture/architecture.md` §8). `tm migrate` is what to run when you want the applied list and the current revision reported; any other command would have applied the same migrations silently. Both go through the same code — `ai-lib`'s `SchemaMigrator`.

Response:
```json
{ "applied": ["20260101000001_initial.php", "20260108000001_add_result.php"], "current_revision": "20260108000001" }
```

## 10. Requirement

Requirements belong to a ticket. They have no child entities and do not accept log entries, so `requirement:delete` is unconditional.

### 10.1 `tm requirement add`

```
tm requirement add --ticket <id> --name "..." [--description "..."] [--ai-description "..."] [--verification <v>] [--before <id> | --after <id>]
```

Creates a requirement under the given ticket. `verification` defaults to `unverified`. Position defaults to append. `--before` and `--after` are mutually exclusive.

Errors: `not_found` (ticket), `invalid_argument`, `invalid_verification`.

Response:
```json
{ "id": 5, "ticket_id": 13, "name": "...", "description": "", "ai_description": "", "order": 1, "verification": "unverified" }
```

### 10.2 `tm requirement list`

```
tm requirement list --ticket <id>
```

Returns requirements in `order` as summarized refs (id, ticket_id, name, order, verification).

Errors: `not_found` (ticket).

Response:
```json
{ "requirements": [ { "id": 5, "ticket_id": 13, "name": "...", "order": 1, "verification": "unverified" }, ... ] }
```

### 10.3 `tm requirement show`

```
tm requirement show --requirement <id> [--deep] [--human]
```

Returns the requirement. `--deep` adds a `logs` array (currently always empty; requirements do not yet accept log entries).

Errors: `not_found`.

### 10.4 `tm requirement set`

```
tm requirement set --requirement <id> [--name "..."] [--description "..."] [--ai-description "..."] [--verification <v>]
```

Updates mutable fields. Multiple field flags are applied as a single transaction.

Errors: `not_found`, `invalid_argument`, `invalid_verification`.

### 10.5 `tm requirement move`

```
tm requirement move --requirement <id> (--before <id> | --after <id>)
```

Reorders the requirement relative to a sibling within the same ticket. Exactly one of `--before` or `--after` is required; the reference id must be a sibling requirement.

Errors: `not_found`, `invalid_argument`.

### 10.6 `tm requirement delete`

```
tm requirement delete --requirement <id>
```

Hard delete. Unconditional — requirements have no children and no log entries, so no `has_children` or `has_logs` guard applies.

Errors: `not_found`.

Response:
```json
{ "deleted": true }
```

## 11. Template

Templates capture the structural shape of a ticket (phases and tasks) as a TOML file. As of ticket 269, templates resolve from `~/.ai-tm/templates/` first and the installed package's shipped `templates/` directory second, and every write goes to `~/.ai-tm/templates/`. See `../architecture/architecture.md` §12 for the full design and `mcp.md` §12 for the equivalent MCP tools (`tm_template_export`, `tm_template_list`).

### 11.1 `tm template list`

```
tm template list
```

Returns the names of every template available to `tm ticket add --template`, sorted alphabetically. There is no CLI equivalent of `tm_template_export`; exporting a ticket to a template is MCP-only (`tm_template_export`).

Response:
```json
{ "templates": ["feature", "quarterly-review", "sprint-setup"] }
```

## 12. `--deep` semantics

Uniform across every `show` command:

| Command | `--deep` adds |
|---|---|
| `project show --project N --deep` | Tickets in the project (summarized: id, name, status). |
| `ticket show --ticket N --deep` | Phases, tasks, log entries (all scopes), status transitions for the ticket. |
| `phase show --phase N --deep` | Tasks, log entries scoped to the phase, status transitions for the phase. |
| `task show --task N --deep` | Log entries scoped to the task, status transitions for the task. |
| `requirement show --requirement N --deep` | `logs` array (currently always empty). |

Status transitions appear in `--deep` output only — there is no dedicated `transitions` verb.

## 12a. `--outline` semantics (`ticket show` only)

`tm ticket show --ticket N --outline` returns the ticket's structure as a tree, without the long-form fields that make `--deep` responses grow unbounded:

| Node | Fields kept | Fields omitted |
|---|---|---|
| Ticket | `id`, `project_id`, `name`, `type`, `status`, `priority`, `description`, `ai_description`, `created_at`, `updated_at`, `archived_at`, `phases`, `requirements` | `logs`, `transitions` |
| Phase | `id`, `ticket_id`, `name`, `status`, `order`, `max_attempts`, `attempts`, `created_at`, `updated_at`, `archived_at`, `tasks` | `description`, `ai_description`, `logs`, `transitions` |
| Task | `id`, `phase_id`, `name`, `status`, `actor`, `order`, `max_attempts`, `attempts`, `created_at`, `updated_at`, `archived_at` | `description`, `ai_description`, `result`, `ai_result`, `logs`, `transitions` |
| Requirement | `id`, `ticket_id`, `name`, `verification`, `order` | `description`, `ai_description` (the entity has no timestamps to omit) |

An omitted field's key is absent from the JSON, not present with an empty string or `null`. Empty containers (a ticket with no phases, a phase with no tasks, a ticket with no requirements) still appear as empty arrays. `--outline` is only implemented for `ticket show`; the other `show` commands are unaffected.

## 13. JSON shapes

The shapes shown in this document are illustrative. The canonical shapes are defined by the DTO classes in `src/Schemas/` and serialized via a single shared serializer at the CLI layer. If a CLI response and a DTO disagree, the DTO is authoritative.

## 14. Things explicitly excluded

- Status transition shortcuts on phases and tickets. Use `phase set --status` and `ticket set --status`.
- Dedicated task transition verbs (`task start`, `task finish`, `task reopen`). All status changes go through `task set --status`.
- Forbidden transition rules other than the one on `task set --status done`. Tickets and phases have no transition rules at the `tm` layer.
- `--position <int>` on add / move. Use `--before <id>` / `--after <id>`.
- CWD inference, `tm use`, env-var current-ticket. The agent supplies every reference.
- Append-mode flags on `set`. `set` always replaces.
- Bulk phase/task creation. Callers compose individual `phase add` and `task add` calls.
- Filtering on `tm log list` beyond scope and sort.
- `tm config show` for individual keys, `tm config validate`, `tm config set`. Only `tm config list` exists; the file is hand-edited.
- Cross-parent task move via `task move`. Use `task set --phase` then `task move`.
- Caller-supplied timestamps. All `created_at`, `updated_at`, `timestamp`, and `archived_at` columns are server-generated.
- Per-task transcript capture, session binding. `hook:enable`/`hook:disable` (§8.2) is the one exception to the general absence of installer verbs — it is explicit, user-invoked, and idempotent, not an automatic install.

## 15. Question

Questions belong to a ticket and optionally reference a task. They have no `--deep` variant and no `move`/`delete` commands: a question is immutable once resolved except through the lifecycle verbs below (`resolve`, `withdraw`, `process`).

### 15.1 `tm question add`

```
tm question add --ticket <id> [--task <id>] --name "..." --question "..." [--background "..."] [--recommendation "..."] --kind <ask|check> --model <name>
```

Creates an open question under the given ticket. `background` and `recommendation` default to `""`. `kind` must be one of the configured question kinds (`ask`, `check` by default).

Errors: `not_found` (ticket or task), `invalid_question_kind`.

Response:
```json
{ "id": 1, "ticket_id": 13, "task_id": null, "name": "...", "question": "...", "background": "", "recommendation": "...", "kind": "ask", "model": "sonnet", "state": "open", "answer": null, "resolution_quality": null, "resolved_at": null, "processed_at": null, "created_at": "...", "updated_at": "..." }
```

### 15.2 `tm question list`

```
tm question list --ticket <id> [--state <state>] [--group <open|resolved_unprocessed|done>]
```

Returns the ticket's questions in creation order, as summarized refs. `--state` filters on the exact `state` column value. `--group` filters on the derived display grouping: `open` (state is `open`), `resolved_unprocessed` (a final state, not yet processed), `done` (a final state, processed). Both filters may be combined.

Errors: `not_found` (ticket).

Response:
```json
{ "questions": [ { "id": 1, "ticket_id": 13, "task_id": null, "name": "...", "kind": "ask", "state": "open", "resolution_quality": null, "resolved_at": null, "processed_at": null, "created_at": "..." }, ... ] }
```

### 15.3 `tm question show`

```
tm question show --question <id>
```

Returns the full question.

Errors: `not_found`.

### 15.4 `tm question resolve`

```
tm question resolve --question <id> --state <accepted|answered> --resolution-quality <direct|clarified|deepened> [--answer "..."]
```

Closes an open question. `accepted` means the stored recommendation stands as-is; `answered` means the user gave a different or richer answer, passed in `--answer`. Only valid from state `open`.

Errors: `not_found`, `invalid_question_state`, `invalid_resolution_quality`, `forbidden_question_transition` (already resolved).

### 15.5 `tm question withdraw`

```
tm question withdraw --question <id> --reason "..."
```

Closes an open question as no longer relevant, with no user answer. Sets both `resolved_at` and `processed_at` in the same call — a withdrawn question needs no follow-up. Only valid from state `open`.

Errors: `not_found`, `forbidden_question_transition` (already resolved).

### 15.6 `tm question process`

```
tm question process --question <id>
```

Marks a resolved question's follow-up work as done, moving it from the `resolved_unprocessed` group into `done`. Only valid on a question that has already reached a final state and has not already been processed.

Errors: `not_found`, `forbidden_question_transition` (still open, or already processed).

## 16. `grind:run` — removed (ticket 269)

Ticket 194 added `grind:run <ticket-ids>... [--runner=tmux|process] [--timeout=<seconds>]`, a CLI command that ran the deterministic grind orchestrator (`AiToolset\Tm\Grind\Orchestrator`) as a long-running process with plain-text stdout instead of the usual `{ok, data}`/`{ok, error}` envelope. Ticket 269 deleted the command along with the rest of `Grind/` (`../architecture/architecture.md` §2). Grind has no CLI surface any more: `tm_grind` is an MCP-only protocol tool that returns instruction text for the calling agent to interpret in-session — see `mcp.md` §14.
