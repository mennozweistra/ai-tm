# tm — Anti-patterns

Concrete mistakes agents make when first using `tm`. Each entry names the mistake, why it is wrong, and what to do instead.

## Inventing slugs

**Mistake:** picking a short string identifier (for example `auth-refactor` or `BBDEV-1234`) and passing it as the ticket, phase, or task id.

**Why it is wrong:** `tm` and `ai-lib` use integer ids only. There are no caller-supplied slugs anywhere in the entity model. Every entity is referenced by its server-assigned integer `id`.

**Do instead:** pass `name` on creation; capture the integer `id` from the response and use it for every subsequent call. If you need to record an external reference (for example a JIRA id), put it in `name` or `description`.

## Setting a phase or ticket status by hand

**Mistake:** calling `tm_phase_set` or `tm_ticket_set` with a `status` — flipping a phase to `active` when starting its first task, or a ticket to `done` after closing its last one. A second form of the same mistake: telling the user that a phase or a ticket "still reads failed" and asking whether he wants it changed.

**Why it is wrong:** phase, ticket, and project statuses are derived from their children, not stored decisions. The data plane recomputes them on every task and phase status change, taking the highest-priority status present: `active` > `blocked` > `failed` > `review` > `pending` > `done` > `skipped`. A hand-set value lasts until the next child changes, and a report about a stale parent status wastes the user's attention on a value that has already been recomputed.

**Do instead:** set task statuses only. When you need a parent's status, read it back with `tm_phase_show` or `tm_ticket_show` after the child change. See "Parent statuses are derived, never set" in `tm://workflow`.

## Skipping phases

**Mistake:** creating a ticket and adding tasks directly to it, expecting `tm` to auto-create a default phase or accept tasks without a phase id.

**Why it is wrong:** tasks live under phases. Every task has a `phase_id`. There is no "default phase" and no auto-creation. A ticket with no phases has no place to put tasks.

**Do instead:** every ticket gets at least one phase before it gets any tasks. For a small ticket, that is one phase named after the work itself (for example `implement` or `fix-bug`). For a larger ticket, break it into 2–5 ordered phases and dogfood each one before starting the next.

## Amending `result` after a task is done

**Mistake:** setting a task to `done`, then later updating its `result` because new information came in or the wording was unclear.

**Why it is wrong:** `result` is meant to be the final outcome, written once when the task transitions to `done`. Logs are immutable for the same reason: the audit trail is only useful if it reflects what was true at the time. Amending `result` later breaks the dashboard's history view.

**Do instead:** write `result` correctly the first time, just before flipping status to `done`. If new information arrives later, write it as a `note` log entry against the ticket, not by editing the closed task.

## Calling `tm_grind` for orientation

**Mistake:** calling `tm_grind` early in a session, expecting it to return documentation about what `tm` is or how to use it.

**Why it is wrong:** `tm_grind` is a protocol tool, not documentation. It returns the grind instructions for named tickets — a background-orchestrator dispatch stub, or the in-session interpreted protocol, depending on its `engine` parameter. The user invokes it explicitly by saying something like "grind these tickets" with ticket ids; an agent does not call it on its own initiative.

**Do instead:** for orientation, read the four resources under `tm://docs/` (`overview`, `entity-model`, `workflow`, `anti-patterns`). For tool surface, list the MCP tools and read their descriptions. Call `tm_grind` only when the user asks for a grind of named tickets.

## Writing planning notes in scratch files outside tm

**Mistake:** when planning a feature, opening a scratch markdown file in the project (or in `/tmp`) and writing the plan there.

**Why it is wrong:** the plan is invisible to the dashboard, the user, and any other agent. It does not persist as part of the ticket's history. It bypasses the entire reason `tm` exists.

**Do instead:** put the feature description in the ticket's `description` and `ai_description`. Put each milestone in a phase's `description` and `ai_description`. Put each step in a task's `description` and `ai_description`. The plan and the work happen in the same place.

## Calling `tm_start_hook` expecting orientation

**Mistake:** calling `tm_start_hook` at the start of a session and expecting it to return a description of what `tm` is, how to use it, or what the current ticket is about.

**Why it is wrong:** `tm_start_hook` is a hygiene check, not a documentation fetch. It returns at most a short conditional instruction (for example, "if you are about to start a task, mark it `active` first"). It returns nothing about the data model, the workflow, or the active ticket. This specific mistake — assuming `tm_start_hook` is the orientation surface — is what motivated the resources you are reading now.

**Do instead:** read the four `tm://docs/` resources for orientation. Use `tm_start_hook` for the per-turn hygiene check it was designed for, and nothing else.
