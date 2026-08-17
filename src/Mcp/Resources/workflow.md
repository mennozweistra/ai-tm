# tm — Workflow

This resource describes how an AI agent is expected to use `tm` over the course of a session. It is the daily-usage shape, not the full tool reference; for the latter, list `tm`'s tools via the standard MCP `tools/list` call.

## Daily shape

A normal turn for an agent working in a project that uses `tm` looks like this:

1. **Turn start: call `tm_start_hook`.** This returns a short conditional instruction that the agent follows. It is a hygiene check — at most it tells the agent to flip a task's status to `active` before working on it. It is not an orientation tool and does not return documentation. (For orientation, read these resources.)
2. **Mark the task `active` before working it.** Use `tm_task_set` with `status: active`. This is a convention, not an enforced rule — it lets the dashboard's status timeline reflect when work actually started.
3. **Do the work.** Plan, edit files, run tests, commit. While working, you may write `progress` log entries via `tm_log_add` for noteworthy intermediate events.
4. **Write the task's `result` when done.** Use `tm_task_set` to set `result` (the human one-liner), `ai_result` (the technical detail: commits, files, test counts), and `status: done`.
5. **Turn end: call `tm_stop_hook` and write a turn log.** The Stop hook returns the per-turn logging instruction; follow it by calling `tm_log_add` with the ticket id and a `note`-type entry summarising what happened in the turn. See "Stop hook" below.

## No workflow rules

`tm` enforces no workflow rules of its own. Every status transition between valid statuses is allowed, on tasks and on every other level. Phase ordering and which transition makes sense next are the caller's business, not `tm`'s.

## Parent statuses are derived, never set

Set the status of tasks. Never set the status of a phase, a ticket, or a project. The data plane recomputes those from their children on every task and phase status change, so a hand-set value survives only until the next change and misleads whoever reads it in between.

The derivation takes the highest-priority status present among the children, in this order:

`active` > `blocked` > `failed` > `review` > `pending` > `done` > `skipped`

A phase derives from its tasks, a ticket from its phases, a project from its tickets. What follows from that:

- One `failed` task makes its phase and its ticket read `failed`. Closing that task is the only way to clear them.
- A phase that still holds one `pending` task reads `pending`, however many of its tasks are `done`. A ticket reads `done` only when every task under it is `done` or `skipped`.
- Never tell the user that a phase or a ticket "still has to be set" to something. Read the parent back instead — the recompute has already run.
- The derivation runs only while the project's `auto_status` flag is on, which is its default.

## Planning conventions

These are conventions, not enforced rules, but they keep the entity model legible across humans, agents, and the dashboard:

- **Features go in tickets.** A ticket is one feature, one bug, one chunk of work that has a clear "done".
- **Milestones go in phases.** Phases break a ticket into ordered milestones — typically 1 to 5 per ticket. Each phase has its own `done` and is dogfooded before the next phase begins.
- **Bite-sized steps go in tasks.** A task is one focused unit of work with a clear deliverable: write a test, write a function, refactor a class, draft a document. If you find yourself writing a task that takes hours, split it.

Every ticket should have at least one phase. Tasks always live under a phase.

## `result` versus `ai_result`

Both fields belong to the task and are filled in when the task finishes:

- **`result`** is the human one-liner that shows up in the dashboard's task list. Plain English, scannable. "Wrote four MCP resource markdown files." "Fixed phase ordering bug; renumber now atomic."
- **`ai_result`** holds the technical detail: commit ids, file paths, test counts, configuration changes. Agents and the dashboard's detail view use this; humans scanning the list do not.

Both are caller-managed. `tm` does not auto-fill or auto-clear them.

## Stop hook

`tm-mcp` self-installs a Claude Code `Stop` hook into each project's `.claude/settings.local.json` on first start. The hook tells the agent to call `tm_stop_hook` at the end of every turn. `tm_stop_hook` returns an instruction telling the agent to call `tm_log_add` with a ticket id and a short summary of the turn.

The result is a per-turn log trail attached to the active ticket without any session-binding registry or transcript-capture machinery. Which ticket the log entry belongs to is decided by the agent at log time from its own context — `tm` does not store a per-session "current ticket".

If the hook is missing, `tm-mcp` reinstalls it. Removing it by hand is not a supported way to disable per-turn logging.
