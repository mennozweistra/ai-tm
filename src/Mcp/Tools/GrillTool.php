<?php

declare(strict_types=1);

namespace AiToolset\Tm\Mcp\Tools;

use AiToolset\AiLib\Services\TicketService;
use AiToolset\Tm\Mcp\BaseTools;
use PhpMcp\Server\Attributes\McpTool;
use PhpMcp\Server\Attributes\Schema;

/**
 * Returns the "grill" protocol: how to conduct the Discovery interview on a single tm ticket,
 * refining its requirements through a two-gear interview — settled questions decided and logged, heavy ones
 * put to the user one per turn — and storing every question it presents as a question entity.
 *
 * Pre-condition: the calling agent must be able to spawn sub-agents (Agent tool available).
 * Without that capability, the codebase-exploration step cannot run.
 *
 * The protocol is returned as a plain string. The ticket id supplied by the caller is embedded
 * in place of the '<ticket.id>' placeholder before the text is returned. The ticket is validated
 * against the database before the protocol is returned; archived tickets are rejected.
 */
final class GrillTool extends BaseTools
{
    private const string PROTOCOL = <<<'PROTOCOL'
# grill — conduct a requirements interview on ticket <ticket.id>

Pre-condition: you must be able to spawn sub-agents (Agent tool). If you cannot, stop and tell the user.

## Purpose

Grill conducts an interview with the user to capture and settle ticket <ticket.id>'s requirements. Your responsibility ends at requirements — you do not create phases or tasks.

## Step 1 — Fetch the ticket

Call `mcp__tm__tm_ticket_show` with `ticket: <ticket.id>` and `deep: true`. Examine the existing requirements. Treat them as mutable: you will add, change, and delete them during the interview. A cold start with no description, no requirements, and no material pasted by the user means the interview has not been seeded yet — ask the user what the ticket is about before proceeding.

## Step 2 — Read the project architecture

Read `AGENTS.md` and the architecture documents it references. Note every settled rule that constrains the design (conventions, layers, forbidden patterns, required test discipline). These are not open questions — do not re-litigate them in the interview.

## Step 3 — Explore the codebase in a sub-agent

Before interviewing the user, dispatch a fresh sub-agent to explore the codebase. Give it focused instructions: which areas of the code the ticket is likely to touch, what to look for, and what questions to answer. The sub-agent runs in isolation and does not return full file contents — only a brief, structured summary of the system parts relevant to this ticket. Read that summary before writing a single interview question.

## Step 4 — Sort the questions into two gears

Before asking anything, list your candidate questions and sort each into one of two gears:

- **Settled** — you can answer it yourself from the requirements, the architecture or the code, and a wrong answer would show up in a test, a diff, or a later ticket. Do not put it to the user at all. Decide it, record it with `mcp__tm__tm_log_add` as a decision naming what you chose and why, and move on. Most candidate questions land here.
- **Heavy** — the user would have to live with a wrong answer: money spent, an outside system touched, wording he is stuck with, a direction the work cannot come back from. Only these reach him.

The line is what the wrong answer costs, not how confident your recommendation is. A question you could answer but would rather have confirmed is settled — confirmation is work handed back to the user, and the log entry already lets him reverse you.

Present the **heavy** questions **one per turn**. Open each with the picture a cold reader needs — when the issue occurs and how it unfolds, in plain everyday words, inventing no shorthand for things the user never named — then your recommendation and its reason.

Ask in plain conversational text. Do not use the AskUserQuestion widget. Walk the design tree yourself: functional scope, then behaviour, then edge cases and error handling — settling each point as you go and logging the decision. Questions that surface mid-interview join the flow in whichever gear fits them. The user can steer depth, skip questions, or stop the interview at any point.

## Step 5 — Store every question as you present it

Store each heavy question with `mcp__tm__tm_question_add` at the moment you present it, no exception. A settled question is never stored as a question; it is a decision log entry:

- `ticket: <ticket.id>`, `kind: 'ask'`, and `model:` the model you are running as.
- `name:` the short name; `question:` the question itself; `background:` the context a cold reader needs; `recommendation:` the choice you propose and its reason.
- Leave `task` empty for a conversational question. If a grill task is active you may pass its id instead.

The stored text is final — a report replays it unchanged, so write it complete the first time.

## Step 6 — Close each question the same turn it is answered

When the user answers, resolve the question in that same turn with `mcp__tm__tm_question_resolve`:

- `state: 'accepted'` when your recommendation stood; `state: 'answered'` with the answer in `answer:` when he chose differently or added more.
- `resolution_quality:` one of — `direct` (answered on the spot), `clarified` (the extra exchange only repaired understanding the question should have delivered itself), `deepened` (the exchange improved the outcome beyond your recommendation). The test for clarified versus deepened: did the follow-up change the outcome, or only the understanding?

In the same turn, also call `mcp__tm__tm_question_process` when answering needed no further follow-up work. For a grill interview this is normally the case: the answer's consequence is captured immediately in the requirement you write next. Leave the question unprocessed only when real follow-up work remains after the interview.

## Step 7 — Write requirements live

As alignment on each point is reached, write it immediately:

- `mcp__tm__tm_requirement_add` — add a new requirement.
- `mcp__tm__tm_requirement_set` — update an existing requirement. When you materially change the meaning of a requirement, set `verification` to `unverified` so the reviewer knows to check it. Cosmetic rewording does not require a verification reset.
- `mcp__tm__tm_requirement_delete` — remove a requirement that no longer applies.

Announce each change in one line of chat (for example: "Added requirement: …"). Do not reproduce the full requirement list in chat — that is visible in the dashboard.

## Step 8 — Edit the ticket description and name

Edit the ticket description freely via `mcp__tm__tm_ticket_set` as the scope becomes clearer. For the ticket name (title field): propose the new name first and wait for explicit user confirmation before setting it.

## Step 9 — Completion

Before declaring the interview complete, re-verify once: if the project has reference documents (specs, architecture docs, requirement sheets), reread the specific ones any question or requirement relied on, rather than trusting a prior summary of them — a summary can omit or compress a detail it wasn't specifically asked about. If no gap analysis between the interview's conclusions and the actual source material has been done yet, do one now. Do this even when the interview already feels finished: treat "I think I'm done" as the prompt to check once more, not as the signal to stop.

Tell the user this task is complete. Do not summarise what was settled — the requirements are visible in the dashboard. Remind the user in one line that any question left open at the end of the interview stays open in the question table and is visible in the dashboard. Ask the user whether you may set this task to done.

## Hard rules

- Do not create or delete phases or tasks. Your responsibility ends at requirements.
- Store every question you present through `mcp__tm__tm_question_add` the moment you present it. Settled questions are decided and logged, never stored as questions.
- Do not reproduce the full requirement list in chat.
PROTOCOL;

    public function __construct(private readonly TicketService $ticketService)
    {
        parent::__construct();
    }

    /** @return array<string, mixed>|string */
    #[McpTool(name: 'tm_grill', description: 'Returns the grill protocol: how to conduct the Discovery interview on a single tm ticket, refining its requirements through a two-gear interview (settled questions decided and logged, heavy ones put to the user one per turn) and storing every question as a question entity as it is presented. Call this when the user asks to grill a ticket or start its Discovery.')]
    #[Schema(additionalProperties: false)]
    public function protocol(
        #[Schema(description: 'Id of the ticket to grill.')]
        int $ticket,
    ): array|string {
        try {
            $out = $this->ticketService->show($ticket);
            if ($out->archivedAt instanceof \DateTimeImmutable) {
                throw new \InvalidArgumentException("Ticket {$ticket} is archived.");
            }
        } catch (\Throwable $e) {
            return $this->handleError($e);
        }

        return str_replace('<ticket.id>', (string) $ticket, self::PROTOCOL);
    }
}
