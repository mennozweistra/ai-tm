# tm — Overview

`tm` is a workflow manager for plans, tickets, and tasks. It is not a database, not an issue tracker, and not a project-management product. It is a thin tool that lets a human or an AI agent record, find, and update the work they are doing in a stable, queryable shape.

## What tm is

`tm` exposes five entities — projects, tickets, phases, tasks, and log entries — through two surfaces:

- A **CLI** (`tm`) for shell scripts, the dashboard, and humans doing occasional manual edits.
- An **MCP server** (`tm-mcp`) for AI agents working through Claude Code or another MCP host.

Both surfaces expose the same operations with the same semantics. They produce the same JSON envelope and call the same underlying services. There is no separate "agent API" with extra magic; what an agent can do, the CLI can do, and the other way around.

## What tm is not

`tm` is a workflow manager, not a data plane. It does not own the entity schema, does not run migrations, and does not implement business logic. Persistence, schema, services, and the entity model itself live in a separate library called **`ai-lib`**. `tm` is a thin adapter on top of `ai-lib`'s service layer.

`tm` enforces no workflow rules of its own: every status transition — phase ordering, status transitions, log immutability, the auto-derivation of parent statuses from children — is enforced by `ai-lib`. `tm` is mechanical: it does not infer caller intent, does not add convenience defaults, and does not provide shorthand verbs. Convenience belongs in the calling agent.

## Who calls tm

- **Humans** — occasionally, via the CLI, for ad-hoc queries and manual edits. The CLI is designed as a contract for programmatic callers, not for ergonomic terminal use.
- **AI agents** — routinely, via the MCP server. Every operation an agent needs to plan, execute, and report on work is available as an MCP tool.
- **The dashboard** — a separate web UI (`ai-dashboard`) that reads from `ai-lib` directly and renders tickets, phases, tasks, and logs. The dashboard does not talk to `tm`; it shares the same data store.

## How agents use it

An agent connecting to `tm-mcp` has three things to do over the course of a session:

1. Read the four resources under `tm://docs/` (this overview, the entity model, the workflow, and the anti-patterns) to orient itself in the data model and the conventions.
2. Use the CRUD tools (`tm_project_*`, `tm_ticket_*`, `tm_phase_*`, `tm_task_*`, `tm_log_*`) to plan and record work.
3. At the end of each turn, call `tm_stop_hook` and write a `tm_log_add` entry for the ticket it is working on.

One protocol tool sits alongside the CRUD tools: `tm_grind`. The user invokes it by saying "grind these tickets" — it takes no parameters and returns the grind instructions for the named tickets: the AI-prompt-driven protocol the agent follows in-session, dispatching each task to a fresh `Agent`-tool subagent. There is no background process and no run-mode choice — the run stays in the calling conversation from start to finish. It is not a documentation fetch and is not called for orientation.

For deeper architectural detail beyond what these resources cover, see `spec.md` and `docs/` in the `tm` repository.
