# Old tm Installation Survey

Survey results for ticket 1 ("Run old tm dashboard as side-by-side reference, deactivate system install"), phase 1 ("Survey leftovers"). Each task in this phase records its findings here. Phase 2 uses this inventory to decide what to delete.

---

## Task 4: Shell configuration files

**Files searched:**

| File | Exists |
|---|---|
| `~/.bashrc` | yes |
| `~/.profile` | yes |
| `~/.zshrc` | no |
| `~/.bash_profile` | no |
| `~/.zprofile` | no |
| `~/.config/fish/` | no |
| `~/.bash_aliases` (sourced by `~/.bashrc`) | yes |
| `~/.config/envman/load.sh` (sourced by both `~/.bashrc` and `~/.profile`) | yes |
| `~/.config/envman/PATH.env` | yes |
| `~/.config/envman/ENV.env` | yes |
| `~/.config/envman/alias.env` | yes |
| `~/.config/envman/function.sh` | yes |

**Result: zero tm-related entries found in any of these files.**

No PATH additions pointing to a tm scripts directory, no `alias tm=...` entries, no `source`/`.` calls for any tm-related file, and no `TM_*` or `AI_TM_*` environment variable exports. The shell environment is clean with respect to tm.

**Deletions required by phase 2 from shell config:** none.
