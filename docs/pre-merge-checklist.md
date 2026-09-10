# `tm` — Pre-merge Checklist

Run every item below before merging a change into the main branch.
The checklist enforces the architectural rules in `docs/architecture/architecture.md`.
All commands are run from the `ai-tm` project root.

`tm` is a thin workflow manager (two adapter layers, nothing else) built on top of
`ai-lib`. The key invariants are: no data-plane code in `tm`, adapters stay thin, and
`tm` enforces no workflow rules of its own — every status transition between valid
statuses is allowed.

---

Automated tool checks — the test suite, static analysis, code style, architecture and
dependency-boundary rules, and automated refactor suggestions — each run as their own
dedicated QA task in the ticket's phase. This file covers only what a tool cannot check
by itself.

## Manual checks

These checks require reading code or running a targeted search. They catch violations
that automated tools do not yet cover.

### No data-plane code in `tm`

`tm` must not contain any Domain models, Repositories, Services, Schemas, or migrations
of its own. All data-plane operations must go through `ai-lib`'s service layer.

Verify that none of the following namespaces or directories exist inside `src/`:

```bash
grep -rn "AiToolset\\\\Tm\\\\Domain"        src/ --include="*.php"
grep -rn "AiToolset\\\\Tm\\\\Repositories"  src/ --include="*.php"
grep -rn "AiToolset\\\\Tm\\\\Services"      src/ --include="*.php"
grep -rn "AiToolset\\\\Tm\\\\Schemas"       src/ --include="*.php"
find src/ -type d \( -name Domain -o -name Repositories -o -name Services -o -name Schemas \)
find migrations/ -name "*.php" 2>/dev/null
```

Expected: no output from any of the above commands. If any match is found, that code
belongs in `ai-lib`, not in `tm`.

### No workflow-rule guard layer reintroduced

`tm` enforces no workflow rules of its own. Both adapter layers must call `ai-lib`'s
services (`TaskService::set()` and the equivalent for other entities) directly for
status changes, with no intermediate guard class rejecting a transition. Confirm no
guard layer has crept back in:

```bash
find src/ -type d -name Workflow
grep -rn "Forbidden.*TransitionException" src/ --include="*.php"
```

Expected: no `Workflow/` directory and no reference to a forbidden-transition exception
anywhere in `src/`. If either is found, verify it was approved in an architecture
discussion before being added — see `docs/architecture/architecture.md` §5.

### Adapters stay thin

Logic in an adapter belongs in `ai-lib`'s services (data-plane logic). Adapters must not
contain business logic, multi-step orchestration, or conditional rules beyond error
formatting and argument parsing.

Read any new or modified adapter class and ask: "does this code make a decision that belongs
in a service?" If yes, move it.

### Hook settings collaborator stays beside the commands, out of `ai-lib` and `Mcp`

`Cli\HookSettingsInstaller` (`hook:enable`/`hook:disable`, ticket 269, requirement 682) is
the one place that reads and writes the user's `~/.claude/settings.json`. It must stay in
`Cli/`, not move into an `ai-lib` service (`ai-lib` may have no side effects) or into `Mcp/`
(Deptrac forbids `Cli` depending on `Mcp`), and it must take the settings path as a
constructor argument so no test touches a real home directory.

```bash
find src/ -iname "HookSettingsInstaller.php"
grep -n "getenv('HOME')" tests/Cli/HookSettingsInstallerTest.php tests/Cli/HookCommandsTest.php
```

Expected: the class exists only at `src/Cli/HookSettingsInstaller.php`; neither test file
reads the real `HOME` environment variable.

### Both entry points self-migrate, and no schema gate has crept back in

Every `tm` entry point brings its own store up to date before it opens it, by calling
`ai-lib`'s `SchemaMigrator` (architecture §8). No entry point refuses to run because the
schema is behind, and no entry point configures Phinx itself.

```bash
grep -n "SchemaMigrator" src/Cli/Application.php src/Mcp/McpServer.php src/Cli/Commands/MigrateCommand.php
grep -rn "assertUpToDate\|BOOTSTRAP_COMMANDS\|Phinx" src/ --include="*.php"
```

Expected: the first command shows one `SchemaMigrator` call in each of the three files —
in `Application`'s constructor **before** `openDatabase()`, in `McpServer::boot()` on the
no-PDO branch only, and in `MigrateCommand::execute()`. The second command produces no
output: `tm` neither gates on `SchemaChecker::assertUpToDate()` nor wires Phinx.

Confirm the first run after an install still works from a bare home directory, and that a
downgraded store still fails in the CLI's JSON shape:

```bash
vendor/bin/phpunit --filter BinTmEntrypointTest
```

Expected: green. Both tests run the real `bin/tm` against a temporary `HOME` with no
`~/.ai-tm` — one asserts the store is created and migrated, the other asserts `schema_ahead`
on stderr with exit code 1.

### No `getenv()`, `$_ENV`, `$_SERVER` for data-plane config inside `src/`

`ai-lib`'s `Config` value object is always `Config::default()` — hardcoded, with no
per-installation override and no file to read (ticket 172 removed `ConfigLoader` and
`~/.ai-tm/config.toml`). Confirm:

```bash
grep -rn "getenv\|\\$_ENV\|\\$_SERVER" src/ --include="*.php"
```

Expected: no output that relates to `ai-lib` configuration (statuses, log types, requirement
verifications). `TM_DB` (the database path override, `docs/api/cli.md` §1.5) is the one
documented environment-variable read in `src/`, and it selects only the database file, not
anything `ai-lib`'s `Config` carries.

### Logging hook command is one line and delegates to the `tm_stop_hook` tool

`tm hook:enable` writes a `Stop` logging hook into the user-level `~/.claude/settings.json`
(when it is not already there). The hook command must be a single short line — Claude Code
prints its `reason` to the user every turn — and that `reason` must only point the agent at
`mcp__tm__tm_stop_hook`. The actual logging instruction lives in `Mcp/Tools/StopHookTool`, not
in the hook command. The hook references no file under `.claude/commands/`.

```bash
grep -n "HOOK_REASON" src/Cli/HookSettingsInstaller.php
grep -rn "claude/commands" src/ --include="*.php"
grep -n "tm_stop_hook\|INSTRUCTION" src/Mcp/Tools/StopHookTool.php
```

Expected: `HOOK_REASON` is a single short string that names `mcp__tm__tm_stop_hook` and
nothing more; no `.claude/commands` path appears anywhere in `src/`; `StopHookTool` carries
the full instruction and is exposed as the `tm_stop_hook` tool.

### Grind protocol matches specification

If `src/Mcp/Tools/GrindTool.php` was added or modified, verify the behavior against `../api/mcp.md §14`. Key invariants to spot-check manually:

**Pure execution engine — no built-in planning:**

- `tm_grind` takes no parameters. There is no `mode` parameter and no plan-only run.
- The protocol has no planning stage: it does not inspect a ticket's requirements or description, does not detect an Implementation phase with no tasks, and does not dispatch a requirements-to-tasks planner subagent. A planning task, when a ticket has one, is an ordinary task like any other — grind runs it through the standard §4/§5 worker procedure, and it is that task's own `ai_description` that instructs the worker to create further `tm` tasks.
- The §5 worker subagent template grants the worker unrestricted access to every `tm` MCP tool — including write tools such as `tm_task_add`, `tm_phase_add`, and `tm_task_set` — with no per-task allowlist (requirement 96).
- The §5 worker subagent template judges success against the task's own goal rather than against whether a commit was made: a task with no file changes (for example a planning task, or a check that reports a failure with nothing to fix) is a valid outcome, the quality gate and commit step are skipped, and `gate: not run` / `commit: none` is reported without that counting as `REVIEW:` or `FAILED:` (requirement 97).

**The budgeted-task / budgeted-phase model (§14.1 – §14.7):**

- A budget lives on exactly one level per check — a phase's own `max_attempts`, or an individual task's own `max_attempts` inside an otherwise ordinary phase — never both (requirement 296). If a template sets both, the phase's number is authoritative and its inner tasks run budget-less for that ticket; the conflict is noted once in the final report.
- `max_attempts` of 0 or absent means an ordinary, non-budgeted task or phase; grind never fixes it — a failure stops and waits for a human. `max_attempts` of exactly 1 is rejected by `ai-lib` (`InvalidAttemptsException`); valid budgets are 0 or 2 and above.
- Before walking a ticket, grind calls `tm_grind_run_add`. Success means a **fresh grind**: `attempts` is reset to 0 on every budgeted phase and budgeted task, even ones currently `done`. An `already_exists` error means a **resume**: `attempts` counters are left untouched (requirement 299).
- For a **budgeted phase** (check-phase, e.g. QA): every pass resets every task in the phase to `pending` first — including on resume after an interruption, which restarts the phase from its first task rather than continuing mid-pass (requirement 307) — then walks the phase once. `attempts` is incremented first, regardless of whether a fix was applied during the pass. A pass with no fix applied and every task `done`/`review` is clean; a pass with no fix but a task `failed` is terminal; a pass with at least one fix applied re-runs to confirm while budget remains — on exhaustion nothing is forced to `failed`: the repairing tasks end `review` with unconfirmed-repair `check` questions and the run continues (requirement 770, ticket 301).
- For a **budgeted task** outside a check-phase (e.g. Devil's Advocate review): the same clean/terminal/re-run decision applies to that one task, without resetting or re-running the rest of its phase (requirement 295).
- A check fixes its own concern inline, in the same run — there is no separate fix-work task or planner subagent (requirements 293, 295, 300). It locates the root cause, repairs it, runs the project's quality gate, commits the repair separately from any other commit that run makes, and logs a `code_fix` entry (`tm_log_add`, `type: "code_fix"`) scoped to the task, carrying the finding, the repair, the commit id, who registered it, and the round (requirements 297, 304). One code fix is always exactly one commit and one `code_fix` log entry.
- The run that applies a fix never also counts as confirmation that the fix holds — the next pass or run always uses a fresh subagent judging cold. The one exception is the AI Review phase (requirements traceability, AI functional review, AI code review): those tasks carry `max_attempts = 0` and never fix anything inline, not even something small.
- The old ticket-wide must-pass set, the fix-and-recheck loop, and the planner subagent that wrote separate fix tasks are removed entirely — there is no code path left that re-runs a whole ticket's must-pass set or dispatches a fix-task planner (requirement 303). Any trace of that model remaining in `GrindTool.php`'s `PROTOCOL` constant is a regression.

**Stop conditions (§14.7):**

Confirm the stop conditions match `mcp.md` §14.7: (1) every task in every requested ticket has been processed — final report emitted; (2) a budgeted phase's pass found a task `failed` with no fix applied — ticket run stops, stop line names the phase; (3) a budgeted task's run failed with no fix applied — ticket run stops, stop line names the task (budget exhaustion with a fix still applied stops nothing: the check ends `review` and the run continues); (4) a subagent's summary began `STOPPED:` — the entire loop aborts, not just the current ticket; (5) a `tm` MCP call (including `tm_grind_run_add`) failed 3 times in a row for the same task or ticket-level call. A task with `max_attempts` 0 that fails is recorded `failed` and stops the ticket — the run continues with the next requested ticket, if any (requirement 513); it never enters the self-fixing check model. A task already `failed` or `blocked` when the walk reaches it is reset to `pending` and run like any other pending task (§14.3) — there is no distinct `blocked` worker outcome, no blocked-so-far list injected into later prompts, and no check-vs-plain split for it; that machinery belonged to the retired PHP orchestrator.

If this tool was not touched in the current change, this check can be skipped.

### `composer.json`'s `ai-toolset/ai-lib` dependency is in the committed (versioned) state, not the development (path) state

`AGENTS.md` ("Composer dependency on ai-toolset/ai-lib") documents two states: a path
repository pointing at `../ai-lib` with an `@dev` constraint while a ticket is in
progress, and a VCS repository with a `dev-main` constraint in what ships. A ticket
branch about to merge to `main` must carry the versioned state — the path state only
resolves on a machine with the sibling checkout present.

```bash
grep -n '"type": "path"' composer.json
grep -n '"type": "vcs"' composer.json
grep -n '"ai-toolset/ai-lib"' composer.json
```

Expected: no `"type": "path"` entry; one `"type": "vcs"` entry with
`https://github.com/mennozweistra/ai-lib`; the `ai-toolset/ai-lib` require constraint
reads `dev-main`, not `@dev`.

### Template claims match engine behavior

If a template was changed — a file under `templates/`, or a template ticket edited in the
database and re-exported — verify every claim the template's text makes about run behavior
against what the engine actually does. Template prose is injected verbatim into every
worker's prompt, so a wrong claim does not just mislead the reader: it instructs the
worker to expect or produce behavior the engine does not have.

Check each of these against `GrindTool.php`'s `PROTOCOL` constant — the current, sole
source of the grind loop since ticket 269 removed the deterministic orchestrator
(`Orchestrator.php`, `BudgetedPhaseProcedure.php`, `WorkerPromptBuilder.php`, and the rest
of `src/Grind/`, all deleted) — not against memory:

- What ends the whole run, what ends one ticket, and what lets the walk proceed. (Today:
  a worker summary beginning `STOPPED:` ends the entire run; a `failed` plain task ends the
  ticket; a human-actor task ends the ticket; a task already `failed` or `blocked` when the
  walk reaches it is reset to `pending` and retried, the same treatment for both — there is
  no separate `blocked` worker outcome or blocked-so-far prompt injection any more — that
  mechanism belonged to the retired orchestrator; `done` and `review` proceed.)
- Who fixes what, where: only budgeted check-phase tasks fix inline; AI Review tasks are
  judge-only and there is no loop-back or fix-task mechanism.
- Network and privilege rules: the template's forbidden-actions wording must not be
  broader or narrower than the worker prompt's hard rules.
- The ticket-level, phase-level, and task-level texts must agree with each other — all
  three land in the same worker prompt.

If no template was touched in the current change, this check can be skipped.

---

## Sign-off

Mark each item before merging:

- [ ] No data-plane code in `tm` (no Domain, Repositories, Services, Schemas, or migrations)
- [ ] No workflow-rule guard layer reintroduced; both adapter layers call `ai-lib`'s services directly
- [ ] No new workflow rule added without a test and an architecture discussion
- [ ] Adapters contain no business logic — only argument parsing and error formatting
- [ ] Every entry point self-migrates via `SchemaMigrator` before opening the store; no `assertUpToDate()` gate, no `BOOTSTRAP_COMMANDS` exemption list, no Phinx wiring in `tm`
- [ ] No `getenv()` / `$_ENV` / `$_SERVER` reads for data-plane configuration
- [ ] `HookSettingsInstaller` stays in `Cli/`, takes the settings path as a constructor argument, and no test touches a real home directory
- [ ] Logging hook command is one line, delegates to `mcp__tm__tm_stop_hook`, references no `.claude/commands/` file; the instruction lives in `StopHookTool`
- [ ] If a template was changed: every claim its text makes about engine behavior (what stops a run/ticket/task, who fixes what where, forbidden-action wording) verified against the current engine code, and ticket/phase/task texts agree with each other
- [ ] If `GrindTool.php` was modified: behavior verified against `mcp.md §14` — no parameters and no built-in planning, worker subagent has unrestricted `tm` tool access (requirement 96), worker judges success by its own goal and a no-file-change task is a valid outcome (requirement 97), budget lives on exactly one level per check — a phase or its tasks, never both (requirement 296), fresh-vs-resume `attempts` reset via `tm_grind_run_add` (requirement 299), phase-reset-per-pass including on resume (requirement 307), checks fix their own concern inline with no separate fix-work planner (requirements 293, 295, 300), each code fix is one commit and one task-scoped `code_fix` log entry (requirements 297, 304), the old must-pass/fix-and-recheck loop and separate fix-work planner are fully removed (requirement 303), stop conditions match §14.7
- [ ] `composer.json`'s `ai-toolset/ai-lib` dependency is in the committed (versioned) state: one `"type": "vcs"` repository entry, `dev-main` constraint, no `"type": "path"` entry
