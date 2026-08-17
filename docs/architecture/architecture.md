# `tm` — Architecture

This document describes `tm`'s architecture. For toolset-wide conventions that apply across all four projects, see `../../../ai-toolset-docs/docs/architecture/architecture.md` (sibling repo).

## 1. Role in the toolset

`tm` is the **freeform workflow manager** for the toolset. It exposes the entity model — projects, tickets, phases, tasks, logs — to humans via a CLI and to AI agents via an MCP server. It does not own the data plane; it consumes it from `ai-lib`.

```
┌──────────────────────────────┐
│   tm  (workflow manager)      │
│   ┌──────────┐ ┌───────────┐ │
│   │  Cli/    │ │   Mcp/    │ │
│   └──────────┘ └───────────┘ │
└────────────────┬──────────────┘
                  │ composer require
                  ▼
          ┌────────────────┐
          │    ai-lib      │   data plane (entities, persistence, services)
          └────────────────┘
```

Ticket 194 added a third box here, `Grind/`, a deterministic PHP execution engine; ticket 269 deleted it (§2). `Cli/` and `Mcp/` are `tm`'s only two source directories.

`tm` has no Domain, Repositories, Services, or Schemas of its own. All data-plane operations are delegated to `ai-lib`'s service layer.

Relationships with other toolset projects:

- `wf` (workflow engine) — a separate workflow manager also built on `ai-lib`. It is independent of `tm`.
- `dashboard` (web UI) — reads entities through `ai-lib`'s services directly, never through `tm`. As of ticket 269 (requirement 685), `dashboard`'s `composer.json` does carry a `require` on `ai-toolset/tm` — but only so it can resolve the installed `bin/tm` binary's path via `Composer\InstalledVersions`; it imports none of `tm`'s `Cli`/`Mcp` classes and calls `bin/tm` the same way it always has, as an external process.
- `gd` (guard / rule engine) — independent; does not consume `tm` or `ai-lib`.

`tm` depends on `ai-lib` for all entity model operations. It has no database code of its own; migrations live in `ai-lib` and are run via `vendor/ai-toolset/ai-lib/migrations/`.

## 2. Layered structure

`tm`'s source tree has two parts sitting on top of `ai-lib`, both thin adapters:

```
src/
  Cli/                ← CLI adapter (Symfony Console commands)
  Mcp/                ← MCP server adapter (tool handlers)
```

`Cli/` and `Mcp/` translate external input into `ai-lib` service calls and format the result, with no business logic of their own beyond that translation.

**History: the deterministic `Grind/` engine (ticket 194, removed by ticket 269).** For one release cycle, `tm` carried a third source directory, `Grind/` — a compiled PHP replacement for the grind protocol, wired by a `grind:run` CLI command and driving each task's worker as an independent Claude Code session (an `ai-tmux` tab via `AiTmuxRunner`, or a headless `claude -p` subprocess via `ClaudeProcessRunner`). It performed every `tm` read/write for a run in-process through injected `ai-lib` services and parsed each worker's answer against a JSON outcome contract. Ticket 205 already defaulted `tm_grind` back to the pre-194 interpreted protocol because the `ai-tmux`/`claude -p` backends were unverified on macOS (`docs/decisions.md`); ticket 269 deleted `Grind/`, `grind:run`, and the `tm_grind` `engine` parameter outright, along with `tm`'s runtime dependency on `ai-tmux`. Nothing in `tm` today performs deterministic control flow in compiled code — the grind loop is agent-interpreted protocol text again (§6), the same shape it had before ticket 194.

Was `Grind/`'s existence a new workflow rule, which §5 says `tm` does not have? No, and the reasoning still holds now that it is gone: §5's rule is that `tm` places no guard in front of a status *transition* — every transition `ai-lib` allows was, and remains, reachable by any caller. `Grind/` was a deterministic version of a loop that already existed as agent-followed protocol text; moving it into PHP did not add a new rule, and moving it back out did not remove one — the loop's own shape (walk phases, dispatch a worker per task, apply the budgeted-check model) is unchanged by which layer executes it. Every write the grind loop makes still goes through the same unconstrained `TaskService::set()`/`PhaseService::set()` every other caller uses, whether the caller issuing it is a PHP class or an agent following `tm_grind`'s protocol text.

Dependencies flow in one direction: `Cli` depends on `ai-lib` only; `Mcp` depends on `ai-lib` only. Nothing in `tm` depends on `ai-lib`'s Domain models or Repositories; those are `ai-lib`-internal. Deptrac's `deptrac.yaml` dropped the `Grind` layer entry and `Cli`'s allowance to depend on it along with the directory.

There is no Domain layer, no Repository layer, no Services layer, and no Schemas layer inside `tm`. Adding any of these would be a layering violation.

Cross-layer violations are caught at CI time by Deptrac (`deptrac.yaml`): `Cli` and `Mcp` are each allowed to depend on `AiLib` only. Ticket 194 added a third `Grind` layer entry, allowed to depend on `AiLib`, with `Cli` additionally allowed to depend on it; ticket 269 removed both along with the `Grind/` directory.

Tests:

```
tests/
  Cli/                ← full CLI commands against in-memory SQLite
  Mcp/                ← full MCP tool calls against in-memory SQLite
  Tooling/             ← repo-wide checks not scoped to one layer (e.g. DeptracGuardTest)
  Support/            ← shared test fixtures (AiLibServices: real ai-lib service wiring)
```

Ticket 194 added `tests/Grind/` (`Orchestrator`, `GrindKernel`, the budgeted procedures, both worker-runner backends, the prompt/outcome contract) and `FakeRunner`/`ScriptedCommandExecutor` fixtures under `tests/Support/`; ticket 269 deleted all of it along with `src/Grind/`.

## 3. Entity model

The entity model — projects, tickets, phases, tasks, log entries, status transitions — is defined and owned by `ai-lib`. For the full specification of entities, columns, relationships, lifecycle rules, ordering, and history, see:

- `vendor/ai-toolset/ai-lib/docs/data-model.md` (installed copy)
- or the `ai-lib` repository at `docs/data-model.md` (canonical source)

`tm` does not duplicate this documentation. The data model is `ai-lib`'s responsibility.

## 4. The CLI is a contract

`tm`'s CLI is the primary surface for humans and external scripts. It is not designed for ergonomic terminal use.

Implications:

- Default output is JSON on stdout. The `--human` flag exists for occasional human-readable rendering but is not the primary path.
- Every command produces output matching the response envelope: `{"ok": true, "data": ...}` or `{"ok": false, "error": {...}}`.
- All entity references are flags (`--project`, `--ticket`, `--phase`, `--task`). No positional entity arguments.
- No CWD inference, no environment-variable current-context, no `tm use <ticket>` shortcuts.

The envelope guarantee is enforced structurally, not just by convention. `bin/tm`'s entrypoint previously registered a `set_error_handler()` (ticket 153 task 1274) that discarded PHP's own routine diagnostics before the autoloader or any vendor code ran, as a workaround for a `yosymfony/toml` deprecation notice under PHP 8.4. That workaround was removed in ticket 154 after `yosymfony/toml` was replaced with `php-collective/toml`, which does not emit the notice. Nothing in the current dependency graph produces diagnostics at the levels that were suppressed.

If a CLI behavior change would alter the JSON output shape, existing callers break. Treat the CLI like an API.

Ticket 194 briefly added an exception here for `grind:run`, whose plain-text stdout (run-start marker, stop lines, final report) bypassed the `{ok, data}`/`{ok, error}` envelope because it was the grind orchestrator process itself, not a data-plane verb. Ticket 269 deleted `grind:run` along with the rest of `Grind/` (§2); there is no CLI command left that runs grind (`tm_grind` is MCP-only, §6), so this exception no longer applies to anything — every CLI command produces the standard envelope, with no exception, exactly as the rule above states.

See `../api/cli.md` for the full CLI surface.

## 5. `tm` enforces no workflow rules

`tm` enforces no workflow rules of its own. Every status transition between valid statuses, on every entity, is permitted through both the `Cli` and `Mcp` adapters. Both adapters call `ai-lib`'s `TaskService::set()` (and the equivalent service method for other entities) directly; there is no guard layer in between.

`tm` previously refused the `pending` → non-`active` transition on tasks, to force agents through the `active` state so the dashboard's progression view had a reliable signal. The user decided in ticket 159 to remove that rule entirely and observe. There are no dedicated verbs (`task start`, `task finish`, `task reopen` do not exist); all status changes go through `task set --status`.

If a new workflow rule is needed in the future, it belongs either in a separate workflow manager (like `wf`) or, for toolset-wide data integrity, in `ai-lib` itself after a design discussion. Do not add a guard layer back into `tm` without that discussion.

## 6. The MCP adapter mirrors the CLI

The MCP adapter is a thin translation layer over the CLI semantics. Every CLI command has a corresponding MCP tool with the same name (snake_cased, prefixed `tm_`), the same arguments (as JSON object fields rather than flags), and the same response envelope.

The CLI is canonical. If a CLI flag and an MCP tool argument disagree, `ai-lib`'s service layer is the tiebreaker.

The MCP adapter is built from the same codebase as the CLI but exposed as a separate binary named `tm-mcp`, registered as a distinct console script in `composer.json`. This keeps the human-facing `tm` CLI separate from the agent-facing `tm-mcp` binary — humans invoke `tm`, Claude Code invokes `tm-mcp`.

Besides the CLI-mirroring data-plane tools, the MCP server exposes a few "protocol" tools that return a block of instruction text the agent then follows: `tm_stop_hook` (the end-of-turn logging instruction, called by the `Stop` hook — see §8; no arguments), `tm_grill` (the Discovery interview protocol; no arguments beyond the ticket id), and `tm_grind` (below). None of these has a CLI equivalent — they only make sense as instruction text handed to an agent, so "the MCP adapter mirrors the CLI" does not apply to this group.

`tm_grind` takes no parameters and always returns the same thing: a block of protocol text — the AI-prompt-driven grind loop — that the calling agent interprets and drives itself, in its own conversation, one step at a time. Ticket 194 briefly replaced this with an `engine` parameter selecting between the interpreted protocol and two modes of a deterministic PHP orchestrator (`Grind/`, §2) that ran as a detached background process; ticket 269 deleted the orchestrator and the parameter along with it, restoring `tm_grind` to exactly the shape it had before ticket 194. There is no dispatch stub any more, and no background process to start, stop, or resume — the run lives entirely inside the conversation that called `tm_grind`, from start to finish or to whichever stop condition it hits.

The loop the protocol text describes: for each requested ticket, walk its phases and tasks in order, dispatching each to a fresh `Agent`-tool subagent (`subagent_type: "general-purpose"`) — an in-conversation subagent, not an independent Claude Code session — and recording the outcome via ordinary `tm_task_set` calls the main agent makes itself. It has no built-in planning: a planning task is just an ordinary task — for example the feature template's "Plan the implementation and release" task — whose instructions tell the worker subagent to create other `tm` tasks. A task or a whole phase can carry a retry budget via `max_attempts`/`attempts` (ticket 179): a **budgeted task** or **budgeted phase** (a check-phase) — never both for the same check — is fixed inline, in the same worker run that found the problem, then re-run by a fresh worker subagent judging cold, until it is clean or the budget is spent. `max_attempts` must be `0` (ordinary, no retry) or `2` or more (a real check); exactly `1` is invalid and rejected by `ai-lib`, since a single run can apply a fix but not confirm it. There is no separate fix-work planner and no ticket-wide must-pass loop: the checking worker fixes its own finding directly. Before walking a ticket, the protocol calls `tm_grind_run_add` once to tell a fresh grind from a resume (§12 in `ai-toolset-docs`'s `grind-and-templates.md`; requirement 299). The full behavioral description is in `../api/mcp.md §14`. Shipping the protocol text inside the MCP tool means there is nothing to install under `.claude/commands/`; `tm_grind` supersedes any such slash command.

### Session lifetime

The MCP server sets the session TTL to `PHP_INT_MAX` via `->withSession('array', PHP_INT_MAX)` in `McpServer::boot()`. This prevents the default one-hour session expiry from terminating an agent session that is still actively working.

The large TTL value is safe because the session handler is `array` — PHP's built-in `ArraySessionHandler`, which stores session data in process memory. Session data is destroyed automatically when the `tm-mcp` process exits, regardless of the TTL value. No session data outlives the process, so setting the TTL to `PHP_INT_MAX` creates no stale-session accumulation risk.

See `../api/mcp.md` for the MCP surface.

## 7. Configuration

There is no config file (ticket 172 removed `ConfigLoader` and `~/.ai-tm/config.toml`). `Application` and `McpServer` construct `ai-lib`'s `Config::default()` directly and pass it to the service container — the same value on every machine, with no per-installation override.

`Config::default()` defines:

- Valid statuses per entity type (ticket, phase, task) — the same seven values for all three; see `vendor/ai-toolset/ai-lib/docs/data-model.md` §7.
- Valid log types.

Project paths live on the `Project` row in `ai-lib`'s database, supplied via `tm project add --path` and updated via `tm project set --path`.

`tm config list` exposes read access to `Config::default()`. There is no `tm config set` and no `tm config validate` — there is nothing to validate any more, since there is no file to parse.

## 8. Setup and migrations

**The database maintains itself.** Every `tm` invocation migrates its own store before it opens it — `Cli\Application`'s constructor calls `ai-lib`'s `Services\SchemaMigrator` with the database path and `SchemaChecker::defaultMigrationsPath()`, and only then opens the PDO connection. On a first run that creates `~/.ai-tm/` and `store.db` and applies every migration; on a store that is behind it applies what is missing; on a current store it costs one `SELECT` against `phinxlog` and writes nothing. `tm` therefore has no schema pre-flight gate any more: the check that used to run in `doRun()` and refuse the command, and the `BOOTSTRAP_COMMANDS` list that exempted `setup` and `migrate` from it, are both gone (ticket 269, requirement 685). Nothing can find a stale schema, because nothing gets to run before the migration. See the toolset architecture §4.7 for the rule, the four states, and the concurrency guarantee — an exclusive `flock()` on `<store>.migrate.lock`, held for the migration run.

Two setup-tooling commands survive, both now conveniences rather than prerequisites:

- `tm setup` creates `~/.ai-tm/` if missing. Idempotent. No config file to write; touches nothing else. Self-migration creates the same directory, so no command depends on it having been run.
- `tm migrate` migrates and reports the applied migrations and the current revision. Idempotent, and by the time it executes the constructor has already applied everything pending, so in practice it is a report. It holds no Phinx wiring of its own: it calls `SchemaMigrator` like every other entry point.

Migrations live in `ai-lib`, and `SchemaMigrator` is the only place Phinx is configured. `SchemaChecker::defaultMigrationsPath()` locates the directory from `ai-lib`'s own file location, so it resolves in a checkout and in a Composer vendor tree alike; there are no migration files inside `tm` itself.

One case is not fixed automatically: a store carrying migrations this release does not ship, because the user downgraded `tm`. `SchemaMigrator` never migrates down — it throws `SchemaAheadException`, which `BaseCommand`/`BaseTools` map to `schema_ahead`. That throw happens in the `Application` constructor, before Symfony Console exists to render it, so `bin/tm` catches it and writes the same `{ok:false,error:{…}}` shape to stderr with exit code 1 rather than dying with a PHP fatal.

### Hook enable and disable

`tm hook:enable` and `tm hook:disable` (requirement 682, ticket 269) give the user explicit, opt-in control over `tm`'s three optional Claude Code hooks — the end-of-turn `Stop` logging hook and the two `UserPromptSubmit` hooks (start-of-turn task-status, conditional grind) — in the user-level `~/.claude/settings.json`. This is a different file and a different trigger from the automatic per-project install this section used to describe: that automatic path is removed (ticket 269), so a fresh `tm` install wires no hooks at all; a user who wants them runs `tm hook:enable`. Nothing about `tm-mcp` startup or `bin/tm` writes to `.claude/settings.local.json`, an output style, `working-rules.md`, or a slash command file any more — see "MCP server startup" below.

`hook:enable` is idempotent: an entry whose command already matches exactly is left alone, a marker present with a stale command is replaced in place, and a missing marker is appended — running it twice writes nothing new. `hook:disable` removes exactly the three marker-tagged entries and leaves every other key and every other hook untouched, dropping a hook group left empty by the removal. Both stop without writing anything if the settings file exists but fails to parse as JSON.

**Placement rule:** a collaborator that reads and writes a file outside the database belongs beside the CLI commands that use it, in `Cli/` — not inside an `ai-lib` service, since `ai-lib` may have no side effects, and not inside `Mcp/`, since Deptrac forbids `Cli` from depending on `Mcp`. `Cli\HookSettingsInstaller` owns the settings-file read/merge/write logic and takes the settings path as a constructor argument, so tests point it at a temp file and never touch a real home directory. It is the only collaborator of its kind left in `tm`: the retired `Mcp\HookInstaller` and `Mcp\StyleInstaller` it was originally modeled on are deleted (ticket 269), along with the personal content under `resources/` they used to distribute. `HookEnableCommand` and `HookDisableCommand` stay thin: argument parsing and `{ok, data}` result formatting only.

### MCP server startup

When `tm-mcp` starts, it does exactly one thing before it builds the tool container or begins serving:

**Schema revision.** As above — it migrates its own store up to the schema this release ships, then opens it. Through ticket 269 this was a read: the server refused to start on a pending migration and pointed the caller at `tm migrate`. That was safe but useless under a Composer install, where nobody runs `tm migrate`, so it is now a write (requirement 685). It stays a fail-to-start gate in the one case that cannot be fixed automatically — a store written by a newer release, which throws `SchemaAheadException` and maps to `schema_ahead` in `BaseTools`. A caller that hands `boot()` its own PDO owns its schema: a connection carries no path to migrate, so nothing runs, which is how the test suite drives the server against `InMemoryDatabase`.

`tm-mcp` writes nothing outside the database it opens. Through ticket 269, startup also silently maintained a `Stop` logging hook and two `UserPromptSubmit` hooks in the working directory's `.claude/settings.local.json`, and installed a personal output style, `working-rules.md`, and slash commands into `~/.claude` — content that assumed the developer running `tm-mcp` was its author. That entire install path, and the `resources/` content it distributed, is deleted. Hooks are now opt-in only, via `tm hook:enable`/`tm hook:disable` above; the two Claude Code hooks that remain relevant to `tm` — `tm_stop_hook` and `tm_start_hook` — are still exposed as MCP tools (see §6) so a hook `hook:enable` wires can call them, but nothing installs the hooks themselves without the user asking for it.

The hook command `hook:enable` writes for the `Stop` event is one line: it blocks the end of a turn and tells the agent to call the `tm_stop_hook` MCP tool, which returns the logging instruction the agent then follows. The instruction lives in the tool (`Mcp/Tools/StopHookTool`), not in the hook command, so what Claude Code prints to the user every turn stays short. The hook references no file under `.claude/commands/`. It does depend on the `tm` MCP being up — when it is not (for example while the server is still starting, or between Claude Code session restarts), the agent reports the tool is unavailable and the turn ends without a log entry; this is a rare, self-resolving condition. The instruction itself makes the agent, at the end of a turn, record a `tm` log entry for the ticket the session is working on (the agent supplies the ticket id from its own context), or say so visibly and write nothing when it cannot identify the ticket, or say there is nothing worth logging.

The task-status hook fires on `UserPromptSubmit` and echoes its instruction straight into the agent's context (a non-blocking `additionalContext`), reminding it to set a pending task active before it starts work. As with the `Stop` hook, the rule's text lives in its tool class (`Mcp/Tools/StartHookTool`), not in the hook command, so what Claude Code prints stays short.

## 9. Single-process, multi-process

`tm` runs in two process shapes:

- **CLI invocation** — short-lived, one command, exits.
- **MCP server** — long-lived subprocess of an MCP client (typically Claude Code), runs for the duration of the client session.

Both shapes share the same SQLite database file, whose path is configured by the user. SQLite's file locking handles concurrent access. There is no shared in-memory state across processes; coordination happens through the database.

Start-up is the one moment where SQLite's own locking is not enough, because both shapes — plus `dashboard`, on the same file — now migrate the schema before they use it (§8). Two processes starting on the same not-yet-migrated store would both read `phinxlog` as empty and both apply the same migration. `SchemaMigrator`'s exclusive `flock()` on `<store>.migrate.lock` serialises that window; everything after it is ordinary SQLite concurrency.

Two simultaneous MCP server instances (for example, two Claude Code sessions) coordinate through the database, not through any cooperative protocol at the `tm` layer.

The database file's path is selected by the `TM_DB` environment variable, read once at process start: `bin/tm` and a freshly launched `bin/tm-mcp` open the path it names, falling back to `~/.ai-tm/store.db` when it is unset or empty. `ai-dashboard`'s `public/index.php` honors the same variable and fallback. The persistent `tm-mcp` session Claude Code launches from the toolset root's `.mcp.json` is deliberately not redirected by `TM_DB`: it starts once per Claude Code session before a review sets the variable, and stays on the production database for that session's lifetime by design. See `../api/cli.md` §1.5 and `../api/mcp.md` §8 for the full selection rule and the test-database workflow it enables.

## 10. Testing

All tests run against a real SQLite database (in-memory, via `ai-lib`'s `InMemoryDatabase` fixture). There are no mocked repositories.

Two categories:

**CLI tests** (`tests/Cli/`): invoke full CLI commands end-to-end against an in-memory SQLite database. Verify the command-to-service wiring and the JSON output shape.

**MCP tests** (`tests/Mcp/`): invoke full MCP tool calls end-to-end against an in-memory SQLite database. Verify the tool-to-service wiring and the JSON output shape.

Both categories follow the same conventions:

- Method names start with `it_`. Marked with `#[Test]`.
- Only test behaviour that catches real bugs. No tests for getters, framework plumbing, or static constants.
- No Mockery. No `createMock()` or `createStub()` for `ai-lib` services — use the real service stack against in-memory SQLite.

## 11. Code quality tools

Five tools run as part of the development workflow. All are Composer dev dependencies.

**PHPUnit** — test runner. All tests live under `tests/`. See §10 for testing conventions.

**PHPStan** — static analysis. Runs at maximum level (`max`). No baseline file — every violation is fixed before merging.

**PHP CS Fixer** — code style enforcement. Configured with the PER Coding Style 2.0 ruleset. Fixes are applied automatically; the CI gate checks that no unfixed violations remain.

**Deptrac** — layer boundary enforcement. Verifies that `Cli` and `Mcp` adapters do not call `ai-lib`'s Domain models or Repositories directly. Configured in `deptrac.yaml`.

**Rector** — automated code upgrades and refactoring. Configured to target PHP 8.4 and enforce modern PHP idioms.

## 12. Template system

The template system provides a way to capture the structural shape of a ticket — its phases and tasks — and reproduce that structure as a new ticket. Templates are committed to version control, so the set of available templates is managed through git rather than through any `tm` tool.

### Storage location

As of ticket 269 (requirement 692), templates resolve from two directories, in order:

1. `~/.ai-tm/templates/` — the user's own templates, read first.
2. `templates/` inside the installed `tm` package — the shipped templates, read second.

The first source with a file for a given name wins on a collision, so a user template with the same name as a shipped one is used in preference to it. Every write — `tm_template_export` / `template:export` — goes to `~/.ai-tm/templates/` only, regardless of where an existing template of the same name was found on read; the package's shipped `templates/` directory is never written to, which matters under a Composer install, where that directory sits in the vendor tree and gets replaced by `composer update`. `tm template list` / `tm_template_list` merges both sources and de-duplicates by name. Both `Cli\Application` and `Mcp\McpServer` derive the user path with a pure `userTemplatesPath()` function and wire `ai-lib`'s `TemplateRepository` with both sources (§13). Each template is a TOML file whose filename (without the `.toml` extension) is the template name.

Before ticket 269, templates lived only in `templates/` inside the `tm` repository/package, tracked by git and shared only by committing template files to that repository. That single-directory shipped path still works as the fallback source above; the ticket added the user directory ahead of it so a template a user creates locally is available without a `tm` release.

Shipped template files are **not** edited through any `tm` tool — modifications, deletions, and additions to `templates/` inside the package go through git in the normal way. User templates under `~/.ai-tm/templates/` are written by `tm_template_export` / `template:export` and may also be edited by hand, since they are not git-tracked by the package repository.

### TOML format

A template file contains only structural content: phases and their tasks. It does not store a ticket name, a ticket description, or any statuses. The format is:

```toml
[[phases]]
name = "Foundation"
description = "..."
ai_description = "..."
max_attempts = 0
order = 1

[[phases.tasks]]
name = "Set up database"
description = "..."
ai_description = "..."
actor = "agent"
max_attempts = 0
order = 1
```

Each phase entry has `name`, `description`, `ai_description`, `order`, and `max_attempts`. Each task entry inside a phase has `name`, `description`, `ai_description`, `actor`, `max_attempts`, and `order`. Statuses are absent; import always creates all entities with status `pending`.

`actor` is either `"agent"` (the task is executed by a fresh grind worker session) or `"human"` (the task is left for the user; grind skips it). `max_attempts` is authored on a task or on a phase (a **budgeted task** or **budgeted phase**, req 296) — never meaningfully on both for the same check, since a budgeted phase's number is authoritative over any budget on its own tasks. `0` marks a plain task or an ordinary phase: on failure grind stops and waits for a human rather than fixing it. `2` or more marks a real check: on failure grind fixes it inline, in the same worker run that found it, then re-runs a fresh worker session, up to that many rounds, until clean or the budget is spent. Exactly `1` is invalid and rejected by `ai-lib`, since a single run can apply a fix but never confirm it held. Import defaults `max_attempts` to `0` when the field is absent, so templates authored before this field was introduced import correctly. See `../api/mcp.md` §14 for the full behavioral description.

### Workflow

The intended workflow has two steps:

1. Export an existing ticket to a template by calling `tm_template_export` with the ticket id and a template name. This writes a file `templates/<name>.toml`. The file captures the phases and tasks of that ticket. If a template with that name already exists, export overwrites it — no confirmation flag or dry-run mode; templates are git-tracked, so an unwanted overwrite is recoverable via git.
2. Commit the template file to the repository in the normal way so it is available on all machines.

There is no separate import step. Creating a ticket from a template is done by calling `tm_ticket_add` / `tm ticket add` with a `template` argument naming the template — see `../api/mcp.md` §3 and `../api/cli.md` §3.1. `--template` is required on every ticket creation; there is no way to create a ticket with a blank structure.

### Implementation

The template tools are implemented in `src/Mcp/Tools/TemplateExportTool.php` and `src/Mcp/Tools/TemplateListTool.php` in `ai-tm`. There is no `TemplateImportTool`: creating a ticket from a template is handled by `TicketService::addFromTemplate()` in `ai-lib`, invoked from `tm_ticket_add` / `tm ticket add`. Both `TemplateExportTool` and `TemplateListTool` delegate to `TicketService`, which in turn delegates file I/O to `ai-lib`'s `src/Repositories/TemplateRepository.php` — the only class that reads from and writes template files. `TemplateRepository` moved from `ai-tm` into `ai-lib` so `TicketService` could own it directly; it is constructed at DI-wiring time with the ordered read sources and the write path described above, so tests can point either or both at a temporary directory.

See `../api/mcp.md` §12 for the MCP tool surface.

## 13. Public surface

See `../api/cli.md` and `../api/mcp.md` for the full CLI and MCP surfaces. These are the high-churn documents — updated whenever a command, flag, or tool changes.

The `ai-lib` service interface that `tm` depends on is documented in `ai-lib`'s source via PHPDoc on the service classes, and at a higher level in `ai-lib`'s `docs/architecture/architecture.md` §5.
