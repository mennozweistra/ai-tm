# Composer path repository — moved

The development-vs-committed composer.json states for a consumer of `ai-tm` (a path
repository pointing at the sibling checkout while a ticket is in progress; a VCS
repository with a `dev-main` constraint in the state that ships) are documented in
`AGENTS.md`, "Composer dependency on ai-toolset/ai-lib: development state vs committed
state", and in `ai-dashboard`'s own `AGENTS.md` for the same pattern applied to its
`ai-toolset/tm` dependency. This file used to carry that content on its own, written
for the pre-ticket-269 sibling-checkout arrangement (the `wf` project it names is gone);
it is superseded and kept only as a pointer so an old link does not 404.
