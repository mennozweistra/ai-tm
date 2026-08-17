# AGENTS.md

This is `tm`, the freeform task-manager workflow for the toolset. It exposes `ai-lib`'s entity model (projects, tickets, phases, tasks, logs, questions) to humans via a CLI and to AI agents via an MCP server. Read this file at the start of every session.

## Documents to read before starting

1. **Toolset-wide architecture** at `../ai-toolset-docs/docs/architecture/architecture.md` (sibling repo `ai-toolset-docs`). Conventions across all toolset projects.
2. **`tm`'s architecture** at `docs/architecture/architecture.md`. Project-specific structure: two thin adapter layers, `Cli/` and `Mcp/` — nothing else.
3. **`ai-lib`'s architecture** at `vendor/ai-toolset/ai-lib/docs/architecture/architecture.md`. The data plane that `tm` depends on.
4. **`ai-lib`'s data model** at `vendor/ai-toolset/ai-lib/docs/data-model.md`. Entities, columns, and lifecycle rules. `tm` has no data model of its own.
5. **`tm`'s spec** at `spec.md`. Scope and decisions.
6. **`tm`'s implementation plan** at `docs/plan.md`. The build sequence with phases, tests, and dogfood steps.
7. Topic documents under `docs/` as needed: `api/cli.md`, `api/mcp.md`.

## Architectural guardrails

- `tm` is a thin workflow manager. It has **no** Domain, Repositories, Services, or Schemas of its own. All data-plane operations are delegated to `ai-lib`.
- The source tree has two parts, both thin adapter layers: `Cli/` and `Mcp/`. Nothing else belongs in `tm`. (Ticket 194 added a third, `Grind/` — a deterministic PHP execution engine; ticket 269 deleted it and restored the grind loop to agent-interpreted protocol text returned by `tm_grind`. See `docs/architecture/architecture.md` §2.)
- `Cli/` and `Mcp/` adapters stay thin. Logic in an adapter belongs in `ai-lib`'s services (data-plane logic) or, if it is a new workflow rule, requires a design discussion first.
- `tm` enforces no workflow rules of its own. Both `Cli` and `Mcp` adapters call `ai-lib`'s services (`TaskService::set()` and the equivalent for other entities) directly for status changes. Every status transition between valid statuses is allowed. This is only about who checks the rule, not about whether the `active` status matters: a task passes through `active` so a reader (a human on the dashboard, or an agent) can see what is being worked on right now, and so `task:list`'s started-date filter has something to find. Not enforced does not mean not required — a task that skips `active` is technically allowed but reports no start date and never shows as in-progress.
- The CLI is a contract. Do not add convenience shortcuts or implicit defaults that change the JSON output shape.
- Mechanical primitives only. Inference and convenience belong in calling agents, not in `tm`.
- `ai-lib`'s Domain models and Repositories are `ai-lib`-internal. `tm` code may only import from `ai-lib`'s `Services`, `Schemas`, `Domain\Exception`, and the root-level `Serializer`. Deptrac enforces this.
- Migrations live in `ai-lib`. `tm migrate` invokes Phinx against `vendor/ai-toolset/ai-lib/migrations/`. There are no migration files inside `tm`.

## Composer dependency on ai-toolset/ai-lib: development state vs committed state

`ai-tm` depends on `ai-toolset/ai-lib`. `composer.json` carries exactly one of two states for that dependency at any time — the `repositories` entry and the `require` constraint for `ai-toolset/ai-lib` always move together, never a mix of the two.

**Development state — while a ticket is in progress.** A path repository entry points at the sibling checkout, with an `@dev` constraint. Composer symlinks `vendor/ai-toolset/ai-lib` to the sibling directory, so an `ai-lib` change made in the same ticket is visible without a `composer update`.

```json
{
    "require": {
        "ai-toolset/ai-lib": "@dev"
    },
    "repositories": [
        {
            "type": "path",
            "url": "../ai-lib"
        }
    ]
}
```

**Committed state — what ships.** A VCS repository entry names the public git URL; `require` tracks `dev-main`. No tags, no Packagist: an update always pulls the latest `main`.

```json
{
    "require": {
        "ai-toolset/ai-lib": "dev-main"
    },
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/mennozweistra/ai-lib"
        }
    ]
}
```

**The working rule.** While a ticket is in progress, `composer.json` in that ticket's worktree stays in the development (path) state — `composer install` must resolve the sibling worktree, because the remote `main` does not yet carry the ticket's `ai-lib` changes. The state that ships — the state committed on the branch that gets merged to `main` — is the versioned state. The swap in either direction is mechanical: replace the `repositories` entry and the `require` constraint for `ai-toolset/ai-lib` with the block shown above. The Release phase performs the swap to the versioned state before merge; the QA phase's pre-merge checklist (`docs/pre-merge-checklist.md`) is what verifies it landed. Do not swap mid-ticket — every intermediate commit on a ticket branch keeps the path state.

**Note for install documentation.** Composer honours a `repositories` entry only from the *root* package; a dependency's own `repositories` block is ignored by whatever depends on it. Neither state above ever reaches someone who installs `tm` as a dependency (`composer global require ai-toolset/tm`) — it only takes effect while `ai-tm`'s own `composer.json` is the root package, i.e. during `ai-tm` development. A user installing `tm` declares the VCS repositories in their own global `composer.json`, plus a dev stability flag, for the `dev-main` constraint to resolve. That is the install documentation's job (requirement 685), not this section's.

## The `tm_grind` protocol tool

`tm_grind` is a protocol tool alongside the data-plane tools: it takes no parameters and always returns the same block of instruction text — the AI-prompt-driven grind loop — for the calling agent to interpret and drive itself, in its own conversation. There is no run-mode choice, no background process, and no separate orchestrator: an agent calls `tm_grind` when the user asks for a grind of named ticket ids, then follows the protocol the returned text describes.

**History (ticket 194 – ticket 269).** For one release cycle, grind was split into a short dispatch stub (returned by `tm_grind`'s `engine` parameter, ticket 205) that told the calling agent to start `<bin/tm> grind:run <ticket-ids>` as a background process, and a deterministic PHP orchestrator (`AiToolset\Tm\Grind\Orchestrator`, `src/Grind/`) that walked a ticket's phases and tasks and ran each through an independent Claude Code session (an `ai-tmux` tab or a `claude -p` subprocess). Ticket 205 already defaulted the parameter back to the interpreted protocol because the orchestrator's worker backends were unverified on macOS (`docs/decisions.md`); ticket 269 deleted `Grind/`, `grind:run`, and the `engine` parameter outright, along with `tm`'s runtime dependency on `ai-tmux`.

The current protocol dispatches each task to a fresh `Agent`-tool subagent (`subagent_type: "general-purpose"`) — an in-conversation subagent, not an independent session — and the main agent (the calling conversation) makes every `tm` read and write for the run itself via ordinary MCP calls, following the protocol text step by step; no model call is part of the main agent's own bookkeeping beyond what each subagent does for its task. The protocol has no built-in planning step: planning happens as an ordinary task in a ticket's Planning phase, created from the feature template like any other task — its instructions tell the worker to create the ticket's other `tm` tasks, and the worker may call any `tm` MCP tool to do this (it is dispatched with unrestricted `tm` tool access, not a narrower allowlist).

A task or a whole phase can carry a retry budget (`max_attempts`/`attempts`, ticket 179): a **budgeted task** or **budgeted phase** ("check-phase") that fails is fixed inline by the same worker run that found the problem, then re-run — a fresh worker subagent judging cold each time — until it is clean or the budget is spent. There is no separate planner-created fix task and no ticket-wide "must-pass set" loop; see `docs/api/mcp.md` §14 for the full behavioral description.

A worker stores every question it hits through `tm_question_add` the moment it arises, instead of only reporting it in the task's `result` — `check` for a decision it already made and applied on its own judgment, `ask` for one that needs the user (ticket 192). The final report (§14.8 of `docs/api/mcp.md`) is a helicopter view: it counts open questions per ticket, it does not list task-by-task detail or reproduce question text.

Because the protocol text ships inside the MCP server, there is no install step and no per-machine file to keep in sync. Any old grind slash command is superseded by `tm_grind` and can be deleted by hand on machines that still have one. See the "Protocol tools" section of `docs/api/mcp.md` for the tool surface, and `docs/decisions.md` for the design rationale behind the ticket-194/269 round trip.

## The `tm_grill` protocol tool

The `tm` MCP server also exposes a protocol tool named `tm_grill` alongside the data-plane tools and `tm_grind`. It accepts one required parameter: the ticket id (integer) whose Discovery phase is to be conducted.

The tool validates the ticket id and returns an error for unknown or archived tickets. For a valid ticket it returns a block of protocol text — the instructions an agent follows to conduct the Discovery interview for that ticket, sorting candidate questions into two gears (ticket 192): **light** questions the architecture already points to a clear answer for are presented as one numbered batch, and **heavy** questions whose answer genuinely shapes the design are presented one per turn; both gears carry a recommended answer. Every question is stored through `tm_question_add` the moment it is presented, and resolved through `tm_question_resolve` (and `tm_question_process`, where no further follow-up is needed) the same turn the user answers it, so the stored record never drifts from the conversation. Requirement entities are written back to the ticket live as the interview progresses. The interview closes once requirements are settled; the Devil's Advocate review that challenges the captured requirements is no longer an internal step of the protocol — it is a separate "Devil's Advocate review" task in the ticket's Discovery phase, added for tickets built from the "feature" template, and it runs after the grill task as its own tm task.

An agent calls `tm_grill` when the user asks to grill a ticket or to start its Discovery phase, then drives the interview the returned text describes. The tool is the only correct entry point for Discovery: do not improvise a question sequence from memory.

Because the protocol ships inside the MCP server there is no install step and no per-machine file to keep in sync. Any old grill slash command is superseded by `tm_grill`. See the "Protocol tools" section of `docs/api/mcp.md` for the tool surface.

## Build discipline

**Follow `docs/plan.md` in order.** Each phase has a goal, the tests to write, the implementation tasks, and a manual dogfood step. Do not skip phases or reorder them.

**TDD for every method.** Red → green → refactor. Write the failing test first. Run it and watch it fail. Implement the smallest thing that turns it green. Refactor with tests passing.

**No mocks. No exceptions.** All tests run against real SQLite in-memory via `ai-lib`'s `InMemoryDatabase` fixture. No Mockery, no `createMock()`, no `createStub()` for `ai-lib` services. The test fixture creates the schema and exercises the real classes.

**Test naming.** Methods start with `it_`. Marked with PHPUnit's `#[Test]` attribute. Tests live under `tests/Cli/` or `tests/Mcp/` matching the source layer.

**Useful tests only.** Tests must catch a real bug. No tests for getters, framework plumbing, static constants, or coverage padding.

## Quality gates

`composer ci` must pass before any commit. It runs:

- `composer test` — PHPUnit, all tests green.
- `composer stan` — PHPStan at level `max`, zero violations, no baseline.
- `composer fix --dry-run` — PHP CS Fixer (PER 2.0), zero unfixed violations.
- `composer deptrac` — layer-boundary enforcement, zero violations. A green result also means every file under `deptrac.yaml`'s scanned paths was actually parsed — the script fails on an unparseable file instead of silently excluding it (ticket 167; see `docs/decisions.md`, 2026-07-10).
- `composer rector --dry-run` — zero suggested upgrades.

If a gate fails, fix the underlying problem. Do **not** add a PHPStan baseline. Do **not** suppress Deptrac rules. Do **not** disable Rector rules. If you genuinely cannot satisfy a rule, stop and surface the issue per "When to stop and ask" below.

## Commit policy

**You have upfront permission to commit at the end of each completed phase.** A phase is complete when:

1. All tests for the phase exist and pass.
2. `composer ci` is fully green.
3. The dogfood step for the phase has been performed and produced the expected result.

Commit message format:

```
Phase N: <phase title>

<one short paragraph: what was built, what was tested, anything notable>
```

One commit per phase. Do not amend across phases. Do not push — the user pushes manually.

You do **not** have permission to commit mid-phase. If you need an interim checkpoint for safety, finish to a green-test state and commit only at the phase boundary.

You do **not** have permission for any other git operation that rewrites history (rebase, reset --hard, force-push, branch deletion). If something goes wrong, stop and surface the issue.

## Recording small judgment calls

Small decisions you make during implementation that the architecture did not anticipate go in `docs/decisions.md`, appended to the end. Format:

```markdown
## YYYY-MM-DD — Phase N — <short title>

**Decision:** <what you chose>

**Reason:** <why; one paragraph>

**Where:** <file:line or file path>
```

Examples of "small": where to put a private helper, naming a test fixture, picking between two equivalent code idioms. Examples of "not small" (these are stop-and-ask): a public service method's signature, a new error code, a column added to the schema, a change to JSON output shape, a new workflow rule.

## Chat output cadence

Default: silent. Do the work, commit per phase, continue. Do not write progress narration mid-phase. Do not ask the user for acknowledgement.

At each phase boundary, write one short message to chat so the user can glance at the terminal and see progress:

```
Phase N complete. Commit <short-sha>. composer ci green.
Dogfood: <one-line outcome>.
Starting Phase N+1.
```

The dogfood line should be the actual outcome (for example "5 phases inserted in non-sequential order, order column stayed contiguous through 3 moves and 2 deletes"), not generic ("dogfood succeeded"). Keep the whole boundary message under five lines.

If a stop-and-ask case arises (see below), break this cadence and write the full message that case requires. Otherwise, the boundary line is the only chat output.

## When to stop and ask

Per your global `CLAUDE.md` collaboration model: small unanticipated decisions get recorded and you continue; large architectural decisions stop the work.

**Continue and record:**
- A naming choice (variable, helper class, test method).
- Where to put a private helper.
- A minor refactor that arose from the work.
- A library quirk that has an obvious workaround.

**Stop and surface in chat:**
- A library does not behave as documented and there is no obvious workaround.
- PHPStan max cannot be satisfied without a baseline.
- A test reveals an architectural gap (something the architecture document did not anticipate).
- A public-interface change (CLI flag, MCP tool argument) is needed.
- A new error code or schema column is needed (those live in `ai-lib`, not here).
- The dogfood step at the end of a phase produces unexpected output.
- You realise an earlier phase has a bug that has been propagating.
- A new workflow rule is needed (stop; design discussion required before writing code).

When stopping, write a clear chat message: what you tried, what failed, what the options are, and your lean. Do not write multi-page analyses; one screenful is enough.

## Plain English in user-facing text

The active output style governs chat replies fully, including its format. Commit messages, log entries, and task results are not chat replies, so that format does not apply to them — but they follow the same plain-English language rules: short sentences, everyday words, no idioms, precise technical verbs. This repo adds no writing rules of its own. Code comments are unaffected: write minimal comments and only when the *why* is non-obvious.
