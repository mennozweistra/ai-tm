# `tm` — Specification

This document specifies what `tm` is, what it isn't, and the principles that govern it.

For `tm`'s architecture, see `docs/architecture/architecture.md`. For the entity model (entities, columns, lifecycle rules), see `vendor/ai-toolset/ai-lib/docs/data-model.md` (canonical source in the `ai-lib` repository). For the CLI surface, see `docs/api/cli.md`. For the MCP tool surface, see `docs/api/mcp.md`. For toolset-wide architecture conventions, see `../ai-toolset-docs/docs/architecture/architecture.md` (sibling repo).

## 1. Purpose

`tm` is the freeform task-manager workflow for the toolset. It exposes the entity model — projects, tickets, phases, tasks, logs — to humans via a CLI and to AI agents via an MCP server. The entity model and its persistence are owned by `ai-lib`; `tm` is the thin adapter layer on top.

`tm` is a workflow manager, not a data plane. It does not own entity persistence, schema migrations, or business logic; it enforces no workflow rules of its own (§3). Those responsibilities belong to `ai-lib`. Workflow enforcement, gate evaluation, and code conformance belong to `wf` and `gd` respectively.

## 2. Framing

### 2.1 The CLI is a contract

The primary callers of the CLI are the MCP adapter, the dashboard, and scripts. Humans use it occasionally for queries and manual edits, but it is not designed for ergonomic terminal use. Every command produces JSON on stdout by default.

This framing rules out conveniences that humans would value but introduce ambiguity for programmatic callers: implicit current-pointer behavior, CWD-based resolution, default values that depend on context, shorthand verbs.

### 2.2 Mechanical primitives only

`tm` exposes mechanical operations on the entity model. It does not infer caller intent, does not provide convenience shortcuts, does not derive defaults beyond schema-level `NOT NULL DEFAULT`. Convenience belongs in the calling agent. `tm` itself stays mechanical.

### 2.3 Atomicity is allowed; convenience is not

A composite operation earns its place only when partial failure would leave the store inconsistent. The test for adding a composite operation: would partial failure produce an inconsistent state that the caller cannot recover from? If yes, the composite earns its place. If no, the caller composes it from primitives.

## 3. Scope

### 3.1 In scope

- A CLI adapter exposing every operation that `ai-lib`'s entity model supports.
- An MCP adapter exposing the same operations to AI agents.
- `ai-lib`'s hardcoded `Config::default()`, defining valid statuses per entity type and valid log types. There is no per-installation config file (ticket 172 removed `~/.ai-tm/config.toml` and `ConfigLoader`); `tm` passes `Config::default()` to `ai-lib`'s service container unchanged.
- Setup tooling: `tm setup` creates the data directory on a fresh machine; `tm migrate` applies `ai-lib`'s pending schema migrations.
- A self-installing logging hook. When `tm-mcp` starts in a project it ensures a single Claude Code `Stop` hook is present in that project's `.claude/settings.local.json` — writing it if absent, preserving the rest of the file — and, when it had to write it, refuses to finish starting and asks the user to restart so the hook takes effect, the same shape as the schema-migration gate. The hook's instruction text is inline in the settings entry; there is no separate command file. The hook makes the agent record a `tm` log entry at the end of a turn for the ticket it is working on. See §3.2 for what this is *not*.

### 3.2 Out of scope (deliberate)

- **Owning the entity model.** Projects, tickets, phases, tasks, logs, and their persistence belong to `ai-lib`.
- **Schema migrations.** Migrations live in `ai-lib`. `tm` invokes `ai-lib`'s migration runner but does not define migrations.
- **Workflow enforcement of any kind.** Phase ordering, gate evaluation, transition rules, approval flows — all in `wf`. `tm` enforces no workflow rules of its own.
- **Code conformance checks.** Rule evaluation, codebase scanning, report generation — all in `gd`.
- **Session binding and transcript capture.** A previous implementation bound Claude Code session ids to tasks via hooks and a process registry, and captured per-turn transcripts. That approach was abandoned as fragile. The current `tm` does not bind sessions to tasks, keeps no process registry, and writes no transcript log entries. It does install one Claude Code hook — the logging `Stop` hook in §3.1 — but that is a single self-managed hook for per-turn logging, not the session-binding registry. Which ticket a log entry belongs to is decided by the agent at log time from its own context; `tm` stores no per-session "active ticket" state in v1 (correct under multiple concurrent sessions, since there is no single active ticket to store).
- **A per-project off switch for the logging hook.** If `tm-mcp` does not find its hook it installs it again on the next start. Removing the hook by hand is not a supported way to disable logging. Revisit only on demonstrated need.
- **Cloud sync, multi-user concurrency, remote storage.** `tm` is single-user, local-first.
- **Cross-parent moves.** A task cannot be moved to a different phase via `task move`. The caller does `task set --phase <new-phase>` followed by `task move --before <id>` if needed.
- **CLI conveniences for humans.** No `tm use <ticket>`, no environment variable for current context, no positional argument shortcuts.
- **Dedicated task transition verbs.** No `task start`, `task finish`, or `task reopen`. All status changes go through `task set --status`.
- **Workflow rules of any kind.** No transition graph, no gates, no approvals. Every status transition between valid statuses is allowed.
- **Bulk phase/task creation.** There is no `plan create` or equivalent bulk operation.
- **Filter and pagination on listings.** `log list` filters by scope; nothing else. `ticket list` and `project list` filter only by `--archived`. Add filters when concrete need is demonstrated.
- **A config validator, writer, or per-installation override of any kind.** `Config::default()` is hardcoded in `ai-lib`; `tm setup` has nothing left to write here, only the data directory.

## 4. Architectural principles

`tm` follows the toolset-wide principles in `../ai-toolset-docs/docs/architecture/architecture.md` and the project-specific architecture in `docs/architecture/architecture.md`. The principles most load-bearing for `tm` specifically:

- **Two adapter layers, nothing else.** `Cli/` and `Mcp/` are thin adapters, calling `ai-lib`'s services directly. There is no Domain, Repository, Service, or Schema layer inside `tm`, and no workflow-rule layer.
- **Delegate to `ai-lib`.** All entity model operations go through `ai-lib`'s service layer. `tm` imports from `ai-lib`'s `Services`, `Schemas`, `Domain\Exception`, and root-level `Serializer` — never from `ai-lib`'s Domain models or Repositories.
- **DTOs at every `ai-lib` boundary.** `tm` calls `ai-lib` services with DTOs and receives DTOs back. It does not pass raw arrays or models across this boundary.
- **Logs are immutable.** Once written, never updated, never deleted.
- **Status transitions are auto-recorded by `ai-lib`.** Any status change on a ticket, phase, or task causes `ai-lib` to write a `StatusTransition` row in the same transaction.

## 5. Public surface

`tm` exposes its functionality through two surfaces:

- **CLI** — for shell scripts, external tools, and humans. Documented in `docs/api/cli.md`.
- **MCP** — for AI agents. Documented in `docs/api/mcp.md`.

The CLI and MCP surfaces expose the same operations with the same semantics. They differ only in the transport. Both use the same response envelope.

`tm` does not expose a PHP service interface to other projects. Other projects that need the entity model consume `ai-lib` directly, not `tm`.

## 6. Configuration

There is no config file (ticket 172). `tm` constructs `ai-lib`'s `Config::default()` directly and passes it to `ai-lib`'s service container — the same value on every machine, with no hand-editing and no per-installation override. `Config::default()` defines valid statuses per entity type and valid log types. Project paths live on the `Project` row in `ai-lib`'s database, not in `Config`.

The CLI exposes `tm config list`, which returns `Config::default()`. There is no `tm config show`, no `tm config set`, and no `tm config validate`.

## 7. Storage

- Binary name: `tm` (CLI), `tm-mcp` (MCP server).
- Data directory: `~/.ai-tm/`.
- SQLite database: path taken from the `TM_DB` environment variable if it is set and non-empty; otherwise `~/.ai-tm/store.db`. `tm`, `tm-mcp`, and the dashboard all honor `TM_DB` the same way, so setting it redirects every part of the toolset to one database. See `docs/api/cli.md` §1.5 for the full selection rule and the test-database workflow it enables.

The setup-tooling commands are two:

- `tm setup` — creates `~/.ai-tm/` if missing. Idempotent. Never touches the database. No config file to write any more.
- `tm migrate` — applies pending schema migrations from `ai-lib`. Idempotent. Every other command checks the schema revision on connection open and exits with a clear error pointing at `tm migrate` if the database is out of date.

There is no hook installer and no bootstrap step beyond these two.

## 8. Entity model overview

The full entity model specification lives in `ai-lib`. See `vendor/ai-toolset/ai-lib/docs/data-model.md`.

Summary: five entities (Project, Ticket, Phase, Task, LogEntry) plus two history tables (StatusTransition). Every entity has an integer `id` as its public identifier. Project and Ticket are soft-archivable; Phase and Task are hard-deletable (subject to guards). LogEntry and StatusTransition are immutable.

Every entity carries a uniform label triplet: `name` (short human label), `description` (longer human description), `ai_description` (AI-readable description). Tasks add `result` and `ai_result` for outcomes. Human-facing fields use the plain word; AI-facing variants use the `ai_` prefix.

Phase and Task children of a parent are ordered by a contiguous integer (`order`), renumbered on insert/move/delete.

## 9. Decisions

- **Migration runner: Phinx (via `ai-lib`).** `tm migrate` invokes Phinx against `ai-lib`'s migrations directory in vendor. `tm` has no migration files of its own.
- **MCP server library: `php-mcp/server`.** Mature community library. Uses ReactPHP for the persistent stdio subprocess that Claude Code launches for each session. Tool handlers are plain PHP methods.
- **No workflow rules in `tm` (ticket 159).** `tm` originally reinstated a done-requires-active check in a `TaskWorkflow` guard layer at the entry point of both adapter layers, after the equivalent check was removed from `ai-lib`'s `TaskService` during the rewire. Ticket 159 removed `TaskWorkflow` entirely: both adapters now call `ai-lib`'s services directly, and every status transition between valid statuses is allowed.

## 10. Resolved

- **MCP server entry point: `tm-mcp`.** A separate binary registered as a distinct console script in `composer.json`. Humans invoke `tm`; Claude Code invokes `tm-mcp`.
- **Composer package name: `ai-toolset/tm`.**
- **Task result fields: `result` and `ai_result`.** Two separate fields owned by `ai-lib`. `result` is the human-scannable one-liner shown in the dashboard. `ai_result` holds technical detail for AI and dashboard detail views. Callers manage them via `task set`; `ai-lib` does not auto-fill or auto-clear them.
- **`tm` no longer exposes a PHP service interface.** Other projects depend on `ai-lib` directly. `tm` is CLI + MCP only.

## 11. Build approach

The detailed build plan, with phases, tests, and dogfood steps, lives in `docs/plan.md`. Each phase is dogfooded before the next is built.
