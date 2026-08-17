# Getting started

You use `tm` by talking to your AI agent. The agent creates the projects,
tickets and tasks, works on them, and writes a log of what it did. You
read along in the dashboard. This walkthrough shows that flow once, from
nothing to a first worked ticket.

Install `tm` first and turn the hooks on; the [README](../README.md)
explains how.

## 1. Create a project and a ticket

Create a directory named `hello-world` and start Claude Code inside it.
Then say:

> Create a tm project to develop a hello world page in this directory.

The agent registers the project in `tm`. Now describe a first ticket:

> Create a ticket for making a hello world web page. Use the
> simple-feature template.

Every ticket starts from a template. The template fills the ticket with
phases and tasks, so you do not build that structure yourself. There is
also a `blank` template, for a ticket you build from scratch. Add two
small tasks of your own, so there is something to watch later:

> Add a task to create hello.html with "Hello, world" as a heading.

> Add a task to give the page a background color and center the heading.

Ask the agent to show the result:

> Show me the ticket.

## 2. Watch it in the dashboard

Open a terminal and start the dashboard:

```
ai-dashboard
```

Open `http://127.0.0.1:8766` in your browser. You see the project, the
ticket, its phases and its tasks, and later the log of the work.

The dashboard shows your work. It does not edit it. Changes come from
your agent.

## 3. Settle the requirements

<img src="../img/grill-master.svg" alt="The grill master" width="300">

Your ticket starts with a Discovery phase, and its first task is a grill
session: an interview where the agent asks what the work must do, before
it builds anything. Start it with:

> Grill ticket 1.

The agent asks you questions, one at a time, each with a recommended
answer. Your answers become requirements on the ticket. Bring in a wish
or two of your own during the interview — a name on the page, a second
line of text — and refresh the ticket in the dashboard: your wishes are
now requirements. The interview ends when the requirements are settled.

The dashboard does not refresh itself. That is intentional: you can read
without the page changing under you. Refresh when you want the current
state.

## 4. Let the agent do the work

<img src="../img/grind-master.svg" alt="The grind master" width="300">

> Grind ticket 1.

The agent works through the ticket's tasks one by one and logs what it
does on each task. Watch the tasks turn green in the dashboard. When the
grind is done, open `hello.html` in your browser: the page exists and is
styled, one task at a time. When the agent needs a decision only you can
take, it stores the question and continues with what it can. You review
the result at the end, with the log and the questions next to the code.

With the hooks on, the agent also writes a log entry at the end of every
turn you have with it. The log is the memory of the work: your next
session reads it and continues where the last one stopped.

## The templates

Ask the agent to list the templates when you need one. These ship with
`tm`:

- `blank` — an empty ticket. You add every phase and task yourself.
- `simple-feature` — a small feature: implement, test, review.
- `feature` — a full feature: discovery, planning, implementation, QA,
  review.
- `autonomous` — a feature the agent builds largely on its own. You
  approve the requirements and the design; the agent runs the rest and
  stops at human review.

Start with `simple-feature`. Use `feature` when the work is big enough
that the requirements need their own discussion first.

You can also make your own templates. Build a ticket the way you like,
then ask the agent to export it as a template. Every next ticket of that
kind starts from your own structure. This is what makes `tm` fit a great
range of projects: software development, a server installation, or
running your household.

There is also a command line tool, `tm`, with the same abilities as the
MCP server. You do not need it for any of the above; `docs/api/cli.md`
describes it.
