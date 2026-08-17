# tm

`tm` is a task manager for your AI agent. It gives you projects, tickets,
phases, tasks, logs, requirements and questions. You share these with your
AI agent through an MCP server. There is also a command line tool, if you
want one. Everything is kept in one SQLite database on your own machine.

A taste of what it can do:

<table><tr>
<td align="center" width="50%">
<img src="img/grill-master.svg" alt="The grill master" width="260"><br>
<i><b>Grill</b> — the agent interviews you about what the work must do.
Your answers become requirements on the ticket.</i>
</td>
<td align="center" width="50%">
<img src="img/grind-master.svg" alt="The grind master" width="260"><br>
<i><b>Grind</b> — an autonomous agent works through the ticket's tasks
one by one and logs what it does on each task.</i>
</td>
</tr></table>
<br>

`tm` is easy to install and easy to remove, so you can try it out without
risk. This file covers installing, updating and removing it on your own
machine. It works the same on macOS and on Linux.

`tm` is written in PHP. It was built with the help of AI. If that is a
problem for you, do not use it.

After the install, [docs/getting-started.md](docs/getting-started.md)
shows you how to use `tm` with your agent.

## What you need

PHP 8.4 or newer, and Composer 2.

```
php -v
composer --version
```

## 1. Add the repositories to Composer

Open Composer's global `composer.json`. Create the file if it is not there.

- macOS: `~/.composer/composer.json`
- Linux: `~/.config/composer/composer.json`

Add all three repositories:

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/mennozweistra/ai-tm"
        },
        {
            "type": "vcs",
            "url": "https://github.com/mennozweistra/ai-lib"
        },
        {
            "type": "vcs",
            "url": "https://github.com/mennozweistra/ai-dashboard"
        }
    ],
    "minimum-stability": "dev",
    "prefer-stable": true
}
```

This checks that the file is still valid:

```
# macOS
composer validate --no-check-all --no-check-publish --working-dir ~/.composer

# Linux
composer validate --no-check-all --no-check-publish --working-dir ~/.config/composer
```

## 2. Install

```
composer global require ai-toolset/tm:dev-main ai-toolset/ai-dashboard:dev-main
```

You now have three commands: `tm`, `tm-mcp` and `ai-dashboard`. Add their
directory to your `PATH`:

```
# macOS
echo 'export PATH="$HOME/.composer/vendor/bin:$PATH"' >> ~/.zshrc

# Linux
echo 'export PATH="$HOME/.config/composer/vendor/bin:$PATH"' >> ~/.bashrc
```

Open a new terminal afterwards.

## 3. Register the MCP server with Claude Code

```
# macOS
claude mcp add --scope user tm ~/.composer/vendor/bin/tm-mcp

# Linux
claude mcp add --scope user tm ~/.config/composer/vendor/bin/tm-mcp
```

This makes `tm` available in every project on your machine. For one
project only, replace `--scope user` with `--scope local` and run the
command in that project's directory.

Start Claude Code again afterwards. It reads MCP servers when it starts.

## 4. Turn on the hooks

The hooks make your agent write to `tm` while it works. Without them your
log stays empty, so we advise you to turn them on. You decide, and you can
change your mind at any time.

```
tm hook:enable
```

To turn the hooks off again:

```
tm hook:disable
```

## 5. Start the dashboard

The dashboard shows your projects, tickets and tasks in a web browser.

```
ai-dashboard
```

Open `http://127.0.0.1:8766` in your browser.

To use another port:

```
ai-dashboard --port 9000
```

## Update

Run this when you want the newest version:

```
composer global update ai-toolset/tm ai-toolset/ai-dashboard
```

## Remove

```
# Turn the hooks off first. After the next command there is no tm to do it.
tm hook:disable

# Remove both packages.
composer global remove ai-toolset/tm ai-toolset/ai-dashboard

# Remove the MCP server from Claude Code.
claude mcp remove tm

# This is your database, with every project, ticket, task and log entry,
# and any templates you added. Run this only if you want to lose all of it.
rm -rf ~/.ai-tm
```

## If something is wrong

```
tm info
```

This command collects the information we may ask for when you ask for
help. It reports no tasks and no secrets.

## More documentation

- [docs/getting-started.md](docs/getting-started.md) — how to use `tm`
  with your agent.
- `docs/api/cli.md` — every command line command.
- `docs/api/mcp.md` — every MCP tool.
- `AGENTS.md` — how `tm` is built.

## Licence

MIT. See `LICENSE`.
