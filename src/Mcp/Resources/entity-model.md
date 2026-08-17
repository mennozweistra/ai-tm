# tm — Entity Model

`tm` works with five entities owned by `ai-lib`. The hierarchy is:

```
Project ── Ticket ── Phase ── Task
                │       │       │
                └── LogEntry ───┘   (logs may attach at any level)
```

`tm` does not own the schema. The columns, constraints, and lifecycle rules below are enforced by `ai-lib`. This resource summarises the shape an agent needs to plan and record work; for the full specification see `vendor/ai-toolset/ai-lib/docs/data-model.md`.

## Naming convention shared by every entity

Every entity carries the same uniform label triplet:

- `name` — short human label (one line).
- `description` — longer human description.
- `ai_description` — AI-readable description, often more structured.

Tasks add `result` and `ai_result` for outcomes (same split: `result` is the human one-liner, `ai_result` holds technical detail).

The integer `id` is the public identifier for every entity. There are no caller-supplied slugs. If you need to record an external reference (for example a JIRA id), put it in `name` or `description`.

## Project

| Field | Required | Notes |
|---|---|---|
| `name` | yes | Globally unique human label. |
| `path` | yes | Filesystem location of the project's working directory. Recorded, not validated. |
| `description`, `ai_description` | no | Default empty. |
| `auto_status` | no | Default `true`. When true, parent statuses are derived from children automatically. One-way: `true → false` is allowed, the reverse is refused. |

Projects are soft-archivable (set `archived_at`) and can be restored. They are never deleted.

## Ticket

| Field | Required | Notes |
|---|---|---|
| `project_id` | yes | FK to project. |
| `name` | yes | Short human label. |
| `description`, `ai_description` | no | Default empty. |
| `status` | no | Default `pending`. Validated against config. |

A ticket represents one feature, bug, or chunk of work. Tickets are soft-archivable and never deleted. Their history persists.

## Phase

| Field | Required | Notes |
|---|---|---|
| `ticket_id` | yes | FK to ticket. |
| `name` | yes | Short human label. |
| `order` | server-managed | Contiguous integer per ticket, starting at 1. |
| `description`, `ai_description` | no | Default empty. |
| `status` | no | Default `pending`. |

Phases are ordered children of a ticket. On insert, move, or delete, `order` is renumbered atomically by `ai-lib`. Phases are hard-deleted (the row is removed), but delete is refused if the phase has any tasks or any phase-scoped logs.

## Task

| Field | Required | Notes |
|---|---|---|
| `phase_id` | yes | FK to phase. |
| `name` | yes | Short human label. |
| `order` | server-managed | Contiguous integer per phase. |
| `description`, `ai_description` | no | The instruction. `ai_description` typically carries the detailed agent brief. |
| `result`, `ai_result` | no | Outcome. Filled in when the task is finished. `result` is the dashboard one-liner; `ai_result` holds commits, files, test counts. |
| `status` | no | Default `pending`. |
| `actor` | no | `'agent'` (default) or `'human'`. |

Tasks are the unit of execution. Hard-deleted, but delete is refused if the task has any log entries.

## LogEntry

| Field | Required | Notes |
|---|---|---|
| `ticket_id` | yes | Always populated. |
| `phase_id`, `task_id` | optional | Determine scope (see below). |
| `type` | yes | One of the configured log types: `progress`, `decision`, `blocker`, `note`. |
| `title` | no | Short, human-legible one-liner. |
| `ai_content` | no | Fuller AI-legible detail. |
| `timestamp` | server-set | |

Scope is determined by which ids are populated:

- `task_id` set → task-scoped (also belongs to its phase and ticket).
- `phase_id` set, `task_id` null → phase-scoped (also belongs to its ticket).
- Both null → ticket-scoped.

**Logs are immutable.** Once written, they are never updated and never deleted.

## StatusTransition

Auto-written by `ai-lib` in the same transaction as any status change, including auto-derived parent transitions. Agents do not write to this table directly; they observe it through the dashboard or via `ai-lib` queries.

## Ownership rule

`tm` owns no schema. Every column, default, constraint, and lifecycle rule above is enforced by `ai-lib`. `tm` enforces no workflow rules of its own on top of the data plane; every status transition is the data plane's responsibility.
