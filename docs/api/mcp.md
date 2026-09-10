# `tm` — MCP Reference

This document specifies the MCP tool surface `tm` exposes to AI agents.

For scope and intent, see `../../spec.md`. For `tm`'s architecture, see `../architecture/architecture.md`. For the data model, see `../data-model.md`. For the CLI surface (which mirrors this one), see `cli.md`.

## 1. Relationship to the CLI

The MCP surface mirrors the CLI surface. Every CLI command has a corresponding MCP tool with the same semantics, the same arguments, and the same response envelope. Where the CLI takes flags, the MCP tool takes a JSON arguments object with the same field names (without the `--` prefix).

If you understand `cli.md`, you understand the MCP surface. This document covers what differs:

- The naming convention.
- Argument shape (flags vs JSON object).
- Response shape.
- A few MCP-specific concerns.

The CLI is the canonical surface; the MCP adapter is a thin translation.

## 2. Tool naming

CLI commands map to MCP tool names by replacing spaces with underscores and prefixing `tm_`:

| CLI | MCP tool |
|---|---|
| `tm project add` | `tm_project_add` |
| `tm project list` | `tm_project_list` |
| `tm project show` | `tm_project_show` |
| `tm project set` | `tm_project_set` |
| `tm project archive` | `tm_project_archive` |
| `tm project restore` | `tm_project_restore` |
| `tm ticket add` | `tm_ticket_add` |
| `tm ticket list` | `tm_ticket_list` |
| `tm ticket show` | `tm_ticket_show` |
| `tm ticket set` | `tm_ticket_set` |
| `tm ticket archive` | `tm_ticket_archive` |
| `tm ticket restore` | `tm_ticket_restore` |
| `tm phase add` | `tm_phase_add` |
| `tm phase list` | `tm_phase_list` |
| `tm phase show` | `tm_phase_show` |
| `tm phase set` | `tm_phase_set` |
| `tm phase move` | `tm_phase_move` |
| `tm phase delete` | `tm_phase_delete` |
| `tm task add` | `tm_task_add` |
| `tm task list` | `tm_task_list` |
| `tm task show` | `tm_task_show` |
| `tm task set` | `tm_task_set` |
| `tm task move` | `tm_task_move` |
| `tm task delete` | `tm_task_delete` |
| `tm log add` | `tm_log_add` |
| `tm log list` | `tm_log_list` |
| `tm requirement add` | `tm_requirement_add` |
| `tm requirement list` | `tm_requirement_list` |
| `tm requirement show` | `tm_requirement_show` |
| `tm requirement set` | `tm_requirement_set` |
| `tm requirement move` | `tm_requirement_move` |
| `tm requirement delete` | `tm_requirement_delete` |
| `tm config list` | `tm_config_list` |
| `tm setup` | `tm_setup` |
| `tm migrate` | `tm_migrate` |
| `tm template export` | `tm_template_export` |
| `tm template list` | `tm_template_list` |

## 3. Argument shape

CLI flags become JSON object fields. Flag names without the `--` prefix are the field names.

CLI:
```
tm ticket add --project 7 --name "OAuth migration" --template feature --description "..."
```

MCP arguments:
```json
{
  "project": 7,
  "name": "OAuth migration",
  "template": "feature",
  "description": "..."
}
```

`template` is required: it names an existing template whose phases and tasks are copied onto the new ticket. Omitting it, passing an empty string, or naming a template that does not exist is rejected with `invalid_argument`; the error message lists the available template names. See `cli.md` §3.1.

CLI:
```
tm task add --phase 42 --name "Read OAuth code" --before 17
```

MCP arguments:
```json
{
  "phase": 42,
  "name": "Read OAuth code",
  "before": 17
}
```

Mutually exclusive flag pairs (`--before` / `--after`) are mutually exclusive fields. Providing both is `invalid_argument`.

`tm_task_add`'s `model` field is required, with no schema-level default — the tool call must supply it, matching `--model` on `tm task add` (`cli.md` §5.1). This is the one place the MCP surface diverges from "if you understand `cli.md` you understand the MCP surface": the CLI has no way to pass a literal JSON `null` on a command line, so `tm task add --model null` uses the string `"null"` (case-insensitive) as a sentinel that the CLI adapter converts to storage `NULL`. The MCP surface has no such sentinel and needs none — `model` is a native JSON argument, so a caller passes an actual JSON `null` directly (`{"model": null}`) for the same "no model" case, typically for an `actor: human` task. A blank or whitespace-only string is rejected with `invalid_argument` on both surfaces.

`tm_task_set`'s `model` field is optional: omitting it from the JSON arguments leaves the stored value unchanged, mirroring `--model` on `tm task set` (`cli.md` §5.4). There is no way to clear an already-assigned model back to null through this tool — passing JSON `null` for `model` is indistinguishable from omitting the field entirely, since the tool's `model` parameter defaults to `null` when absent.

Same rule for log entries. `tm_log_add` takes `ticket` / `phase` / `task` (exactly one scope), `type`, and the two optional payload fields `title` and `ai_content` — `title` is the short human-legible one-liner, `ai_content` is the fuller AI-legible detail (the field formerly named `content`). Both default to the empty string.

CLI:
```
tm log add --ticket 23 --type progress --title "task 120 done: documented log fields" --ai-content "Updated mcp.md and cli.md ..."
```

MCP arguments:
```json
{
  "ticket": 23,
  "type": "progress",
  "title": "task 120 done: documented log fields",
  "ai_content": "Updated mcp.md and cli.md ..."
}
```

`tm_log_list` returns each entry with the same field names: `id`, `ticket_id`, `phase_id`, `task_id`, `log_type`, `title`, `ai_content`, `timestamp`.

The `--human` flag does not exist in MCP; tool results are always JSON.

## 3a. `tm_task_list` and `tm_task_show`: date filters, ordering, and enriched fields

`tm_task_list` takes several arguments beyond `phase` and `archived`:

| Field | Type | Required | Description |
|---|---|---|---|
| `phase` | int | no | Id of the phase to list tasks from. Now optional — omit it to list across every phase, optionally scoped by `project`. |
| `project` | int | no | Id of the project to scope the list to. Combines with `phase` and every date filter by AND. |
| `created_from` / `created_to` | string (`YYYY-MM-DD`) | no | Plain local date bounds on `created_at`. |
| `started_from` / `started_to` | string (`YYYY-MM-DD`) | no | Plain local date bounds on the derived start date (see `../data-model.md` §3.4: first transition to `active`). |
| `finished_from` / `finished_to` | string (`YYYY-MM-DD`) | no | Plain local date bounds on the derived finish date (last transition to `done`). |
| `order_by` | string | no | One of `order`, `created`, `started`, `finished`. Defaults to `order`. An unknown value is rejected with `invalid_argument`. |

All filters combine with AND, never OR. A call with both `created_from` and `started_from` set returns only tasks that satisfy both bounds at once. There is no way to ask "created in July OR started in July" in one call — a caller who needs an OR across dimensions makes two separate `tm_task_list` calls and merges the results itself (requirement 361).

There is no limit and no offset. A call with no filters and no `phase` returns every task in the database that survives the archive rule below; paging is not implemented (requirement 356).

Ordering: with `phase` set and `order_by: "order"` (the default), tasks come back in phase position, as before this ticket. With no `phase` and `order_by: "order"`, tasks come back ordered by ascending task id, since there is no cross-phase notion of position. For `created`, `started`, or `finished`, tasks come back oldest first; a task with no value for that column (for example, never started) sorts last; task id is the tiebreak.

Archive rule: unless `archived: true` is passed, a task is hidden when the task itself, its phase, its ticket, or its project is archived — this now applies across the whole cross-phase, cross-project listing, not only within one phase.

Dates are plain `YYYY-MM-DD` strings. Matching compares the date part of the stored timestamp — the first ten characters — against the given date; nothing is converted and no timezone is involved anywhere (requirements 345, 346, 347, 362; see `../../ai-lib` `docs/data-model.md` §1).

`tm_task_list`'s task entries gain four fields: `started_at` (nullable, `YYYY-MM-DD HH:MM:SS`), `finished_at` (nullable, `YYYY-MM-DD HH:MM:SS`), `ticket_id`, and `project_id` (requirements 352, 353).

`tm_task_show` needed no argument change for this ticket, but its response shape grew: both the flat view (`deep: false`) and the deep view (`deep: true`) now carry `started_at` and `finished_at`. There is no outline view for tasks — `--outline` is `ticket show`-only (cli.md §12a) — so this has no outline-view counterpart to update.

## 4. Response shape

Every MCP tool returns the same response envelope as the CLI:

Success:
```json
{ "ok": true, "data": { ... } }
```

Error (domain-level):
```json
{ "ok": false, "error": { "code": "...", "message": "...", "type": "..." } }
```

The MCP tool result is the envelope itself, not just the `data` payload. This lets agents branch on `ok` uniformly across all `tm` tools.

Error codes are the same as the CLI surface; see `cli.md` §1.4.

## 5. Tool-call errors vs domain errors

**Domain errors** are returned in the envelope's `error` field with `ok: false`. The MCP tool call itself succeeded; the operation was refused for a domain reason.

```json
{ "ok": false, "error": { "code": "not_found", "message": "Task 42 not found.", "type": "DomainError" } }
```

**Tool-call errors** are returned by the MCP transport when something is wrong with the tool call itself: missing required argument, malformed JSON, schema violation. These are surfaced as MCP-level errors, not as `tm`'s envelope.

Domain errors are the common case and what agents need to handle. Tool-call errors are recoverable by fixing the call structure.

## 6. Idempotency

The same idempotency rules apply as in the CLI:

- `project archive` / `project restore` and `ticket archive` / `ticket restore`: idempotent.
- `setup`: idempotent. Only creates the data directory; nothing to overwrite.
- `migrate`: idempotent.
- All other operations are not idempotent.

Agents that retry calls should check error codes carefully: `already_exists` after an `*_add` call generally means the previous call succeeded.

## 7. Listing tools

The MCP server exposes a tool listing through the standard MCP `tools/list` request. The list is static — every tool in §2 is always available, regardless of what state any ticket is in.

`tm` itself does not gate tool availability based on workflow state. Workflow gating, if needed, is `wf`'s concern.

## 8. Server binary and lifecycle

The MCP server is a separate binary named `tm-mcp`, built from the same codebase as the `tm` CLI but registered as a distinct console script in `composer.json`. This keeps the human-facing CLI surface separate from the MCP surface: `tm --help` does not expose MCP-related options, and `tm-mcp` is not invoked by humans.

Register `tm-mcp` as an MCP server in your client's configuration. The exact configuration file and format depend on the client.

The MCP client launches `tm-mcp` as a subprocess at session start. It runs for the duration of the session and exits when the client disconnects.

Server startup:

1. Open the SQLite database at the path `TM_DB` selects, or `~/.ai-tm/store.db` if `TM_DB` is unset or empty (see cli.md §1.5; `tm-mcp` uses the identical selection logic as `bin/tm`).
2. Migrate the store up to the schema this release ships — creating it on a first run — and exit with `schema_ahead` if the store was written by a newer release (ticket 269, requirement 685). Through that ticket this step was a read that exited with `schema_outdated` on a pending migration; a Composer install has nobody to run `tm migrate`, so it now applies the migrations itself. See `../architecture/architecture.md` §8.
3. Register tools and listen for MCP requests over stdio.

There is no config file to load (ticket 172): the server constructs `ai-lib`'s `Config::default()` directly, so there is no `config_invalid` startup failure mode any more.

The server holds an open connection to the SQLite database for its lifetime. SQLite handles concurrent access between the MCP server, CLI invocations, and the dashboard via its built-in locking.

There is no shared in-memory state across MCP server instances. Two simultaneous instances (e.g. two Claude Code sessions) coordinate through the database.

`TM_DB` is read once, at step 1 of startup. It has no effect on a `tm-mcp` process already running: the process reads its environment at launch and keeps that database open for its lifetime.

This matters for the persistent session MCP server Claude Code launches from the toolset root's `.mcp.json`: that server starts once per Claude Code session, with no `TM_DB` in its environment, so it opens `~/.ai-tm/store.db` and stays there for the life of the session — by design. It is the workflow's bookkeeping surface (tickets, phases, tasks, logs for real project work) and is not meant to be redirected mid-session. A functional review that needs a disposable test database launches its own `tm-mcp` process with `TM_DB` set in that process's environment, separate from the session server; see cli.md §1.5 for the test-database workflow.

## 9. Things deliberately not in MCP

- `--human` flag. MCP tool results are always JSON.
- Reading from stdin. MCP tools have no equivalent.
- Exit codes. Success and failure are signaled by the `ok` field.

## 10. Requirement tools

The six `tm_requirement_*` tools mirror the CLI requirement commands. Requirements belong to a ticket and have no children or log entries, so `tm_requirement_delete` is unconditional. All follow the standard response envelope.

### `tm_requirement_add`

Arguments:

| Field | Type | Required | Description |
|---|---|---|---|
| `ticket` | int | yes | Id of the ticket to add the requirement to. |
| `name` | string | yes | Requirement name. |
| `description` | string | no | Human-readable description. Defaults to `""`. |
| `ai_description` | string | no | AI-legible description. Defaults to `""`. |
| `verification` | string | no | Verification state. Must be one of the configured verifications (`unverified`, `met`, `unmet`). Defaults to `unverified`. |
| `before` | int | no | Id of a sibling requirement to insert before. Mutually exclusive with `after`. |
| `after` | int | no | Id of a sibling requirement to insert after. Mutually exclusive with `before`. |

Error codes: `not_found` (ticket), `invalid_argument`, `invalid_verification`.

Success response `data`:
```json
{ "id": 5, "ticket_id": 13, "name": "...", "description": "", "ai_description": "", "order": 1, "verification": "unverified" }
```

### `tm_requirement_list`

Arguments:

| Field | Type | Required | Description |
|---|---|---|---|
| `ticket` | int | yes | Id of the ticket whose requirements to list. |

Returns requirements in `order` as summarized refs.

Error codes: `not_found` (ticket).

Success response `data`:
```json
{ "requirements": [ { "id": 5, "ticket_id": 13, "name": "...", "order": 1, "verification": "unverified" }, ... ] }
```

### `tm_requirement_show`

Arguments:

| Field | Type | Required | Description |
|---|---|---|---|
| `requirement` | int | yes | Id of the requirement to show. |
| `deep` | bool | no | When `true`, adds a `logs` array (currently always empty). Defaults to `false`. |

Error codes: `not_found`.

### `tm_requirement_set`

Arguments:

| Field | Type | Required | Description |
|---|---|---|---|
| `requirement` | int | yes | Id of the requirement to update. |
| `name` | string | no | New name. |
| `description` | string | no | New description. |
| `ai_description` | string | no | New AI-legible description. |
| `verification` | string | no | New verification state. Must be one of the configured verifications. |

Multiple fields in one call are applied as a single transaction.

Error codes: `not_found`, `invalid_argument`, `invalid_verification`.

### `tm_requirement_move`

Arguments:

| Field | Type | Required | Description |
|---|---|---|---|
| `requirement` | int | yes | Id of the requirement to move. |
| `before` | int | no | Id of a sibling requirement to place this one before. Mutually exclusive with `after`. |
| `after` | int | no | Id of a sibling requirement to place this one after. Mutually exclusive with `before`. |

Exactly one of `before` or `after` is required. The reference id must be a sibling requirement in the same ticket.

Error codes: `not_found`, `invalid_argument`.

### `tm_requirement_delete`

Arguments:

| Field | Type | Required | Description |
|---|---|---|---|
| `requirement` | int | yes | Id of the requirement to delete. |

Unconditional hard delete. No `has_children` or `has_logs` guard.

Error codes: `not_found`.

Success response `data`:
```json
{ "deleted": true }
```

## 10a. Question tools

Questions belong to a ticket and optionally reference a task. There is no `tm_question_delete` or `tm_question_move`; a question's lifecycle runs forward only, through `resolve`, `withdraw`, and `process`.

Call `tm_question_add` the moment a question arises — during a grind worker's task, a Devil's Advocate review, QA, planning, grill, or live conversation — not after the fact. Write `question`, `background`, and `recommendation` self-contained for a cold reader who was not in the conversation that produced the question. Call `tm_question_resolve` in the same turn the user answers, so the stored question and its resolution never drift out of sync with the conversation.

### `tm_question_add`

Arguments:

| Field | Type | Required | Description |
|---|---|---|---|
| `ticket` | int | yes | Id of the ticket the question belongs to. |
| `name` | string | yes | Short question name. |
| `question` | string | yes | The question text, self-contained for a cold reader. |
| `kind` | string | yes | Must be one of the configured question kinds (`ask`, `check`). |
| `model` | string | yes | The model that raised the question. |
| `task` | int | no | Id of a task the question relates to. |
| `background` | string | no | Context that produced the question. Defaults to `""`. |
| `recommendation` | string | no | A concrete recommended answer. Defaults to `""`. |

Error codes: `not_found` (ticket or task), `invalid_question_kind`.

Success response `data`:
```json
{ "id": 1, "ticket_id": 13, "task_id": null, "name": "...", "question": "...", "background": "", "recommendation": "...", "kind": "ask", "model": "sonnet", "state": "open", "answer": null, "resolution_quality": null, "resolved_at": null, "processed_at": null, "created_at": "...", "updated_at": "..." }
```

### `tm_question_list`

Arguments:

| Field | Type | Required | Description |
|---|---|---|---|
| `ticket` | int | yes | Id of the ticket whose questions to list. |
| `state` | string | no | Filters on the exact `state` column value. |
| `group` | string | no | Filters on the derived display group: `open`, `resolved_unprocessed`, or `done`. May be combined with `state`. |

Returns questions in creation order as summarized refs.

Error codes: `not_found` (ticket).

Success response `data`:
```json
{ "questions": [ { "id": 1, "ticket_id": 13, "task_id": null, "name": "...", "kind": "ask", "state": "open", "resolution_quality": null, "resolved_at": null, "processed_at": null, "created_at": "..." }, ... ] }
```

### `tm_question_show`

Arguments:

| Field | Type | Required | Description |
|---|---|---|---|
| `question` | int | yes | Id of the question to show. |

Error codes: `not_found`.

### `tm_question_resolve`

Arguments:

| Field | Type | Required | Description |
|---|---|---|---|
| `question` | int | yes | Id of the question to resolve. |
| `state` | string | yes | `accepted` (the stored recommendation stands) or `answered` (a different or richer answer, in `answer`). |
| `resolution_quality` | string | yes | How much the answer cost to get: `direct`, `clarified`, or `deepened`. |
| `answer` | string | no | The user's answer, when `state` is `answered`. |

Only valid from state `open`.

Error codes: `not_found`, `invalid_question_state`, `invalid_resolution_quality`, `forbidden_question_transition` (already resolved).

### `tm_question_withdraw`

Arguments:

| Field | Type | Required | Description |
|---|---|---|---|
| `question` | int | yes | Id of the question to withdraw. |
| `reason` | string | yes | Why the question no longer applies. |

Closes an open question with no user answer, setting both `resolved_at` and `processed_at` in the same call — a withdrawn question needs no follow-up. Only valid from state `open`.

Error codes: `not_found`, `forbidden_question_transition` (already resolved).

### `tm_question_process`

Arguments:

| Field | Type | Required | Description |
|---|---|---|---|
| `question` | int | yes | Id of the question to mark processed. |

Marks a resolved question's follow-up work as done, moving it from `resolved_unprocessed` into `done`. Only valid on a question that has already reached a final state and has not already been processed.

Error codes: `not_found`, `forbidden_question_transition` (still open, or already processed).

## 11. Protocol tools

Besides the data-plane tools in §2, the MCP server exposes a small number of "protocol" tools. They take no arguments and return a static block of instruction text rather than a `{ "ok": ..., "data": ... }` envelope. The agent calls one of them and then follows the text it returns. Shipping the text inside the MCP means it is available on every machine that has the `tm` MCP registered, with nothing to install under `.claude/commands/`.

| MCP tool | What it returns |
|---|---|
| `tm_stop_hook` | The end-of-turn logging instruction. Called by `tm`'s `Stop` hook (see `../architecture/architecture.md` / `../plan.md`); the agent records a `tm_log_add` entry for the ticket the session is working on, or says so and writes nothing if the ticket is unclear, or says there is nothing worth logging. |
| `tm_grind` | Returns the interpreted grind protocol for one or more `tm` tickets: the agent runs it in-session, one `Agent`-tool subagent per task. Took an `engine` parameter through ticket 205–269 selecting between this protocol and a short dispatch stub for a deterministic PHP orchestrator (`grind:run`) run as a background process; ticket 269 deleted the orchestrator and the parameter, so `tm_grind` is back to no arguments, always returning the one protocol — see §14 for the full description. The agent calls `tm_grind` when the user asks for a grind of named ticket ids, then follows the instructions the returned text describes. |
| `tm_grill` | Returns the grill protocol for conducting the Discovery interview on a ticket. The agent calls this when asked to run Discovery on a ticket, then follows the interview protocol the returned text describes. Candidate questions are sorted into two gears — light ones presented as a single numbered batch, heavy ones one per turn — and every question is stored via `tm_question_add` as it is presented and resolved via `tm_question_resolve`/`tm_question_process` the same turn it is answered. The interview closes once requirements are settled — the tool no longer runs a Devil's Advocate verification internally. For tickets built from the "feature" template, a separate "Devil's Advocate review" task in the Discovery phase runs afterward as its own `tm` task; a template without a Discovery phase has no such task. |

The instruction text lives in the tool handler class (`Mcp/Tools/StopHookTool`, `Mcp/Tools/GrindTool`, `Mcp/Tools/GrillTool`), not in any hook command or slash command, so the client's per-turn printout stays short. A `tm_style_hook` tool existed until ticket 165; writing-style guidance now lives in a Claude Code output style, and the hook installer removes the old writing-style hook entry when it finds one.

## 12. Template tools

The two template tools operate on TOML files, read and written through `ai-lib`'s `TemplateRepository`. As of ticket 269 (requirement 692), templates resolve from two sources in order — `~/.ai-tm/templates/` (the user's own, read first) and the `templates/` directory inside the installed `tm` package (shipped, read second) — and every write goes to `~/.ai-tm/templates/` only. Templates capture the structural shape of a ticket — its phases and tasks — and are used to reproduce that structure as a new ticket. There is no `tm_template_import` tool: a template is applied to create a new ticket via the `template` argument of `tm_ticket_add` (§3), not through a separate import call. See `../architecture/architecture.md` §12 for the full design.

### `tm_template_export`

Reads an existing ticket's phases and tasks and writes them to a new TOML template file.

Arguments:

| Field | Type | Required | Description |
|---|---|---|---|
| `ticket` | int | yes | Id of the ticket to export. |
| `name` | string | yes | Template name (the filename without the `.toml` extension). |

Behaviour:

- Fetches the full deep read of the ticket (phases and their tasks).
- Captures `name`, `description`, `ai_description`, and `order` for each phase and task.
- Ticket name, ticket description, and all statuses are **not** captured; only structure is saved.
- Writes a file `<name>.toml` under `~/.ai-tm/templates/` in TOML format — never to the package's shipped `templates/` directory, even when a template of that name already exists there.
- If a template with that name already exists at the write target, it is overwritten — no confirmation flag or dry-run mode; user templates are not git-tracked by the package, so back up a file before overwriting it if it matters.

Success response:

```json
{ "ok": true, "data": { "path": "/home/user/.ai-tm/templates/<name>.toml" } }
```

Error codes: `not_found` (ticket does not exist).

To create a ticket from a template, see `tm_ticket_add`'s `template` argument (§3) — there is no `tm_template_import` tool.

### `tm_template_list`

Returns the names of all available templates.

Arguments: none.

Behaviour:

- Reads both `~/.ai-tm/templates/` and the package's shipped `templates/` directory, merges the names, and returns them de-duplicated and sorted alphabetically — a name present in both is listed once.
- Returns an empty list when neither directory exists or contains any `.toml` files.

Success response:

```json
{ "ok": true, "data": { "templates": ["quarterly-review", "sprint-setup"] } }
```

Error codes: none (returns an empty list on any read failure).

## 13. Grind run tools

A grind run record marks that a grind session for a ticket has started. Each ticket may have at most one grind run record; a second `add` call for the same ticket fails with `already_exists`, which is how the prompt-original grind protocol (`GrindTool.php` §3c) tells a fresh grind from a resume (requirement 299). There is no CLI equivalent — `grind-run:*` commands existed only for the retired PHP orchestrator engines (ticket 269) and were removed with them, along with the read-back and mutation tools (`tm_grind_run_show`, `tm_grind_run_set`, `tm_grind_run_list`, `tm_grind_run_delete`) nothing calls any more. `tm_grind_run_add` is the one surviving tool, and it follows the standard response envelope.

### `tm_grind_run_add`

Creates a grind run record for a ticket.

Arguments:

| Field | Type | Required | Description |
|---|---|---|---|
| `ticket_id` | int | yes | Id of the ticket this grind run belongs to. |
| `type` | string | yes | Grind run type (caller-defined label, e.g. `"full"`). |

Each ticket may have at most one grind run record. Passing `ticket_id` for a ticket that already has a grind run record returns `already_exists` instead of creating a duplicate.

Error codes: `not_found` (ticket does not exist), `already_exists` (ticket already has a grind run record).

Success response `data`:
```json
{ "id": 1, "ticket_id": 42, "type": "full" }
```

## 14. Grind: the interpreted protocol

**History (ticket 194 – ticket 269).** For one release cycle, grind was split into a short `tm_grind` dispatch stub plus a deterministic PHP orchestrator (`AiToolset\Tm\Grind\Orchestrator`, wired by a `grind:run` CLI command) that performed the task walk, the budgeted-check pass/retry model, and the final report itself, dispatching each task to an independent Claude Code session (an `ai-tmux` tab or a `claude -p` subprocess) via a `WorkerRunner` interface. Ticket 269 deleted all of it — the orchestrator, both worker backends, `grind:run`, and the `tm_grind` `engine` parameter. This section describes what replaced it: `tm_grind` (`AiToolset\Tm\Mcp\Tools\GrindTool`) again returns one fixed, static block of protocol text, and the calling agent interprets it directly, one step at a time, in its own conversation — the same shape grind had before ticket 194. There is no PHP orchestrator, no background process, and no JSON worker-outcome contract; a worker subagent's answer is a summary beginning with one of four tokens, read as plain text.

### 14.1 Budgeted tasks and budgeted phases

A task or a phase whose own `max_attempts` field is greater than zero is **budgeted**: a real check with a retry budget. `max_attempts` equal to zero or absent means an ordinary task or phase — the loop never fixes it; a failure stops the ticket and waits for a human. `max_attempts` of exactly 1 is rejected by `ai-lib` (`InvalidAttemptsException`): a single run can apply a fix but not confirm it, so the only valid budgets are 0 (no retry) and 2 or more (a real check).

A ticket budgets a check at exactly one of these two levels, never both:

- **A budgeted task** — an individual task's own `max_attempts` is above zero while its phase is ordinary. Only that task retries; the rest of the phase's task walk is unaffected.
- **A budgeted phase (check-phase)** — the phase's own `max_attempts` is above zero. Every task inside it is walked once per pass, and a full pass — every task in the phase, not just the one that failed — is what the loop judges as clean or not, because a fix can break a check that was previously green.

**Double-budget conflict.** If a phase is budgeted and one or more of its own tasks also carry `max_attempts` above zero, the phase's number is authoritative: those tasks run budget-less for this ticket regardless of their own `max_attempts`. The main agent notes the conflict once for the final report and continues.

### 14.2 Fresh grind vs. resume

Before walking a ticket's phases, the main agent calls `tm_grind_run_add` for the ticket (`type: "full"`; §13). Success means this is a **fresh grind**: no grind run record existed for this ticket, so it resets `attempts` to 0 on every budgeted phase and budgeted task (even ones currently `done`). An `already_exists` error means this is a **resume**: attempts counters are left untouched, because they carry the budget across the interruption. Any other error retries up to twice more; three failures in a row abort the whole run.

### 14.3 The task walk (ordinary phases, and one pass of a budgeted phase)

The main agent re-fetches each phase (`tm_phase_show`, `deep: true`) for an authoritative task list, then walks its tasks once, in the returned order:

- `done` or `review`: skipped without modification, regardless of other fields — the user may deliberately pre-mark a task done to exclude it from the run.
- `skipped` (ticket 183): skipped without modification, counted separately in the final report (`<s>`) — a task deliberately excluded (a precondition not met, or the user's choice), terminal the same as `done`/`review`.
- `active`: skipped without modification — another runner owns it, or a previous run was interrupted mid-task; the final report flags this as a crash-recovery hint (§14.8).
- `actor: human`: stops the current ticket — the run moves on to the next requested ticket, if any (§14.7).
- `failed` or `blocked`: reset to `pending`, then run per the bullet below as if newly pending. (There is no separate `"blocked"` worker outcome any more — a task only reaches `blocked` status by some other means, and grind's only handling of it is this same reset-and-retry, identical to `failed`.)
- otherwise (`pending`): run.

Immediately before dispatch, for an agent-actor task whose `model` is null: the main agent picks one itself — the cheapest model capable of the task, judged from the task's own text (§4.2a of `ai-toolset-docs`'s `grind-and-templates.md`) — and writes it back with `tm_task_set` before dispatching, so it is not re-decided on a later run.

For an **ordinary phase** (`max_attempts` 0 or absent), this walk runs once, top to bottom. A task that ends `failed` is recorded and stops the current ticket — later tasks in the ticket are not dispatched, since they may depend on the failed one; the run moves on to the next requested ticket, if any (§14.7). For a **budgeted phase**, this same walk is what one pass consists of — see §14.4.

### 14.4 The self-fixing pass (budgeted phases)

**Skip-if-done.** Before entering the pass loop, the main agent reads the budgeted phase's own `status`. If it is already `done` — under `auto_status` this means every task in it is already `done` — the phase is skipped entirely: nothing is reset, no pass runs, `attempts` is untouched. This guard is what makes a resume safe: on a resume `attempts` is left untouched (§14.2), so without it a re-walk would reset every task in an already-passing check-phase, re-run all of them, and increment `attempts` again, risking a spurious budget-exhaustion failure on a phase that already passed. Only a phase whose `status` is not `done` enters the pass loop below.

For such a phase, the main agent runs a sequence of passes:

1. **Phase-reset.** At the start of every pass — including the first and every resume of an interrupted pass — every task in the phase is reset to `pending`, even ones `done` from a previous pass, **except** a task whose status is `skipped` (left exactly as it is, so it stays excluded from every pass, not just the one that first saw it). This is a deliberate, scoped exception to "never re-run a done task": a check-phase pass can only be judged clean as one complete unit.
2. **Walk the pass**, per §14.3, with the worker subagent's prompt carrying a CHECK BUDGET block (this pass's number, the phase's `max_attempts`): fix inline if broken, and report it. After each subagent returns, the main agent reads whether its raw summary contains the literal line `fix applied: yes` — the worker's own report, not an inference from git state. `fix applied: yes` covers any real repair: a committed code change, **or** a `tm` data change (a rewritten requirement, a corrected task description). `fix applied: no`, or the line's absence entirely, both count as no fix.
3. **Increment `attempts` first**, via `tm_phase_set`, regardless of whether any task in the pass applied a fix — `attempts` counts finished passes, not fixes.
4. **Decide:**
   - No fix applied and every task ended `done`/`review`: the phase is clean. Move to the next phase.
   - No fix applied but a task ended `failed`: terminal — re-running an unchanged phase cannot help. Stop the entire ticket run (not just this phase).
   - At least one fix was applied: the phase must re-run to confirm, even though every task may currently read `done`. If the budget just written in step 3 is exhausted, the run does not stop and nothing is marked `failed`: the final pass's repairing workers each stored an unconfirmed-repair `check` question and returned `REVIEW:`, any of their tasks still reading `done` is set to `review`, the exhaustion is noted for the final report, and the run moves to the next phase. Otherwise, go back to step 1 for another pass.

### 14.5 The self-contained budgeted task (outside a check-phase)

An individual task with its own `max_attempts` above zero, sitting in an otherwise ordinary phase, retries the same way but without a phase reset — only that one task re-runs, using the same `fix applied: yes`/`no` signal as §14.4 (a `tm` data repair counts as `yes`, not only a committed code change), the same attempts-incremented-first rule (via `tm_task_set`), and the same clean/terminal/re-run decision as §14.4, scoped to the single task. This is the mechanism that makes the budget real for the review tasks it exists for — Devil's Advocate, grind-compatibility review — which repair requirements or task descriptions through `tm` calls and commit no code: they report `fix applied: yes` and trigger a confirming re-run instead of reading as clean after one run. A run after the first confirms the previous run's repairs rather than re-reviewing the whole surface: a new finding outside those repairs is stored as a question, never repaired. On exhaustion the same continue rule as §14.4 applies: the task ends `review` with its unconfirmed-repair question, and the run continues with the next task.

### 14.6 Reviewer role and the code_fix log entry

A check worker subagent fixes its own concern inline when it finds one broken. For a code check that is a code change: it locates the root cause, makes the repair, runs the project's quality gate, and commits the repair separately from any other commit the same run makes. For a check whose subject is `tm` data (a Devil's Advocate or grind-compatibility review), the repair is a data edit made through `tm` MCP calls — a rewritten requirement, a corrected task description — with no code commit and no quality gate. Either kind of repair is logged as a `tm_log_add` entry (`type: "code_fix"` for a code repair, `type: "assumption"` for a Devil's Advocate finding) scoped to the task, recording the finding, the repair, the commit id (or `none` for a data-only repair), who registered it, and the round number, and either kind makes the worker report `fix applied: yes`. A repair is only made for a finding that would change what gets built or executed; a finding below that threshold — wording, polish, a sharper formulation — is recorded (in the summary, a verdict entry, or a question) without a repair and does not count as a fix, so it triggers no re-run. On its final allowed attempt a worker that still repairs something also stores a `check` question naming the repair as unconfirmed and returns `REVIEW:`. What is never true is that the same run both applies a fix and counts as confirmation the fix holds — the next pass or run always dispatches a fresh worker subagent judging cold (§14.4/§14.5). The one exception is the AI Review phase (requirements traceability, AI functional review, AI code review): its tasks carry `max_attempts = 0` and its reviewer never fixes anything, not even a small one — a finding there goes back through Implementation instead.

### 14.7 Stop conditions

The run stops when one of these is true:

| Condition | Behaviour |
|---|---|
| Every task in every requested ticket has been processed | The normal end; final report emitted. |
| A budgeted phase's pass found a task failed with no fix applied | Ticket run stopped; stop line names the phase; final report emitted. |
| A budgeted task's run failed with no fix applied | Ticket run stopped; stop line names the task; final report emitted. |
| A worker subagent's summary began `STOPPED:` (refused a prohibited action) | Task recorded `failed`; the entire loop aborts (not just the current ticket); final report emitted. |
| A `tm` MCP call failed 3 times in a row for the same task, or for the ticket-level `tm_grind_run_add` call | Loop aborted with an abort line naming the call; final report emitted. |

A human-actor task, or a failed task with `max_attempts` equal to zero, stops only the current ticket (§14.3) — the loop then moves on to the next requested ticket, if any, rather than aborting outright; neither is one of the five reasons above. The failed task never enters the self-fixing check model; it, and the ticket's remaining work, are left for the user.

**Budget exhaustion does not stop the run.** A budget spent with a fix still applied leaves the check in `review` with an unconfirmed-repair `check` question stored; the run continues and the final report notes the exhaustion. Nothing is marked `failed`, so an exhausted check cannot strand a later re-grind: a fresh grind resets `attempts` (§14.2), and a resume skips the check because its tasks read `done`/`review`.

There is no interruption/resume mechanism to describe beyond the ordinary one: the run lives entirely inside one conversation, so "interrupted" means the conversation itself was interrupted (the user sent a message, rejected a tool call) — resuming is a matter of the same conversation continuing, on the user's explicit instruction, not a separate process to restart.

### 14.8 Final report — a helicopter view, not a walk of the parts

After every requested ticket has been processed (or the loop stopped early), the main agent outputs a single block to chat — not to any process's stdout, since there is no longer a separate process. The report gives a top-level view of how the run went and per-ticket counts; it does not enumerate individual tasks and it does not print question text — that detail lives in the dashboard and in the questions table.

It opens with one or two lines: which ticket ids were grinded, and whether the run finished normally or stopped early (naming the reason, from §14.7, if it did). Then one block per ticket:

```
grind finished
note: tickets were processed in the order supplied; per-task revert path is `git revert <commit-sha>` (the commit sha appears in each task's result line in the dashboard).

ticket <id>: <n> tasks run, <r> review, <f> failed, <s_db> skipped done/review, <s_a> skipped active, <s_h> skipped human, <s> skipped
  questions: <a> open to answer, <c> decisions to confirm
ticket <id>: ...
```

The question counts come from `tm_question_list` (`ticket: <id>`, `group: "open"`), counted by `kind`: `<a>` is the number of kind `ask` — an open question still needing a real answer from the user — and `<c>` is the number of kind `check` — a decision a worker already took and applied so the run could proceed, awaiting the user's confirm-or-reverse. Both counts are shown even when zero (`0 open to answer, 0 decisions to confirm`); the report never prints the question text itself. `<s_a>` (skipped active) non-zero on a ticket gets an appended crash-recovery note; `<s_h>` (skipped human) non-zero gets an appended note that those tasks are for the user, not a subagent; `<s>` (skipped, ticket 183) needs no such note — it is a task correctly excluded from the run, not a crash artifact.

If a budgeted phase or task stopped the run, the report names it and its `max_attempts` limit. If a double-budget conflict was noted (§14.1), the report names the phase and the conflicting task ids. Every inline fix a check applied during the run is separately traceable via that task's `code_fix` log entries (§14.6) and the dashboard's "Code Fixes" view — the final report itself does not repeat that detail. Tasks that ended `done`, `review`, or `failed` are all visible in the dashboard; the report does not list them individually. If a ticket had zero pending tasks, its line says so.

**On-demand listing.** The report shows counts only. When the user then asks to see the questions, the calling agent calls `tm_question_list` for the ticket and presents each stored question exactly as it was written — numbered so the user can refer to items, each self-contained with its recommendation. It never summarizes, rephrases, or reconstructs a question from a task's `result` or from memory; it replays the stored `question` and `recommendation` text verbatim.

### 14.9 Worker subagents and no-file-change tasks

The protocol has no built-in planning stage and no run modes. Planning a ticket's implementation is not a grind capability; it is the job of an ordinary task (typically in a Planning phase, created from the feature template) whose `ai_description` instructs the worker to create the ticket's other tasks.

A dispatched worker is an in-conversation `Agent`-tool subagent (`subagent_type: "general-purpose"`, `model: <task.model>`), not an independent Claude Code session — ticket 194's `ai-tmux`/`claude -p` backends are both gone (§14, `../architecture/architecture.md` §2). It does not see the main agent's conversation; its prompt is fully self-contained. It has the same broad tool access any Claude Code session has, including every `tm` MCP tool: read tools and write tools such as `tm_task_add`, `tm_phase_add`, and `tm_task_set` — there is no per-task allowlist. This is what makes worker-driven planning possible: a planning worker creates the ticket's other tasks the same way a human would, through ordinary `tm` calls. The guardrail against a worker doing something it should not is the prohibition family baked into its prompt, not a tool allowlist.

A task that makes no file changes — for example a planning task that only creates `tm` rows via MCP calls, or a review task that only reports findings — is a valid, successful outcome, not a failure and not a reason for the `REVIEW:` token. The worker judges success against its own task's goal, not against whether it produced a commit: it skips the quality gate and the commit step when the task made no file changes, and reports `gate: not run`, `commit: none`.

A worker subagent's final answer is a summary beginning with exactly one of four tokens: `OK:` (completed correctly, or nothing to gate/commit and nothing to flag), `REVIEW:` (completed and committed but something needs the user's eye), `FAILED:` (could not complete; nothing committed or reverted), `STOPPED:` (refused a prohibited action). A summary that does not begin with one of these, or a subagent that returns nothing, is treated as `FAILED:` with a fixed `result` string noting the format was not followed. There is no re-prompt of the same subagent and no retry of the parse — the main agent starts a fresh subagent for the next task.

### 14.10 Question duties

Every worker subagent runs as the model the main agent resolved for its task (`<task.model>`, §14.3). Because a subagent cannot reliably name its own model, the main agent injects that same value into the subagent's prompt so it can pass it as `model` on every `tm_question_add` call it makes.

When a worker hits an issue while doing its task, it decides which of two kinds it is:

- **Resolvable within the task's own scope** — a naming choice, where a helper goes, an equivalent idiom, a minor local refactor, a library quirk with an obvious workaround. The worker decides, applies the decision, and keeps working — it does not stop and does not wait. It then stores the issue as a `check` question: `kind: "check"`, a short `name`, a `question` text explaining what was hit and how it came up, and `recommendation` set to the decision taken and why. A `check` question means the decision is already applied and the run continued; the user later confirms it (nothing to do) or reverses it (the reversal becomes follow-up work).
- **Would create a new requirement or change the architecture** — above the task's own scope, so the worker does not decide it. It stores the point as an `ask` question instead (`kind: "ask"`): an answer is needed before its consequence can be built. If it blocks the task, the worker's summary begins `FAILED:`; if the task can still finish around it, the worker continues and lets the stored question carry the point.

A worker whose summary begins `FAILED:` (proposing an architectural question) or `REVIEW:` (flagging something for the user's eye) also stores that same point as an `ask` question before it returns — the task's `result`/`ai_result` fields are no longer the only record of it.

Every stored question is written for a cold reader who was not in the conversation: it explains when the issue occurs and how it unfolds, in plain everyday words, and depends on nothing outside the question entity itself. The `recommendation` names the choice proposed (or, for a `check`, the decision already taken) and its reason, in one or two sentences. The question is stored once, at the moment it is settled, and never paraphrased afterward — the final report (§14.8) counts questions and the on-demand listing replays their stored text unchanged, it never rewrites it. The main agent itself never turns a question's follow-up into a new task mid-run; any resulting task is created later, after the run, when the question is processed.

## 15. Open questions

- **Streaming responses.** None of the current tools produce large responses, but `*_show --deep` could grow. Whether to support streaming or paginated responses is undecided.
- **Tool annotations.** MCP supports per-tool metadata. The current spec does not declare these formally; they live in source as PHPDoc on the tool handler classes.
