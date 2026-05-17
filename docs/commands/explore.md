---
eyebrow: 'Docs · Command · explore'
lede:    'Interactive terminal browser — tree pane on the left, value pane on the right, keystroke navigation. The fastest way to learn an unfamiliar address space.'

see_also:
  - { href: './browse.md',                         meta: '4 min' }
  - { href: '../recipes/inventory-with-dump-and-grep.md', meta: '4 min' }
  - { href: 'https://github.com/php-tui/php-tui',  meta: 'external', label: 'php-tui (terminal library)' }

prev: { label: 'watch', href: './watch.md' }
next: { label: 'trust', href: './trust.md' }
---

# `explore`

A full-screen terminal UI for browsing the address space.

## Usage

<!-- @code-block language="text" label="signature" -->
```text
opcua-cli explore <endpoint> [global-options]
```
<!-- @endcode-block -->

One argument, no extra flags beyond the globals. Connects,
starts the TUI, exits when you press `q`.

## What it looks like

<!-- @code-block language="text" label="layout" -->
```text
┌ Address space ──────────────────────┐┌ Details ────────────────────────────┐
│ ▾ Objects                           ││ NodeId:      ns=2;s=PLC/Speed       │
│   ▾ Server                          ││ BrowseName:  2:Speed                │
│     • ServerArray                   ││ DisplayName: Speed                  │
│     • NamespaceArray                ││ NodeClass:   Variable               │
│     • ServerStatus                  ││                                     │
│   ▾ DeviceSet                       ││ Value:       42.5                   │
│     ▾ PLC1                          ││ Type:        Double                 │
│       › Speed                       ││ Status:      Good (0x00000000)      │
│       • Mode                        ││ Source:      2026-05-15T10:30:00+00:00 │
│       • Health                      ││                                     │
└─────────────────────────────────────┘└─────────────────────────────────────┘
┌ Log — q=quit  ↑↓=move  →/Enter=expand  ←=collapse/up  r=refresh ───────────┐
│ [10:30:00] Connected. Browsing Objects folder (i=85)...                    │
│ [10:30:00] Loaded 4 root children.                                          │
│ [10:30:02] Browsed ns=2;s=PLC: 3 children.                                  │
└─────────────────────────────────────────────────────────────────────────────┘
```
<!-- @endcode-block -->

The screen has three regions: the address-space tree (left,
scrollable), the selected node's details (right), and the log
pane (bottom). The pane titles are literally `Address space`,
`Details`, and `Log — …` (the log border carries the keybinding
hint).

Only `browse` / `read` / connection-level events appear in the
log — moving the selection with the arrow keys doesn't log
anything.

## Keybindings

| Key                  | Action                                                    |
| -------------------- | --------------------------------------------------------- |
| `↑` / `↓`            | Move selection                                             |
| `→` or `Enter`       | Expand the selected node (loads its children on first use) |
| `←`                  | Collapse the current node; if already collapsed, move selection up to the parent |
| `r`                  | Refresh the value of the selected Variable (force a fresh read) |
| `q` or `Esc`         | Quit and disconnect                                        |

The TUI is keyboard-only — no mouse handler is wired up
(`php-tui` exposes mouse events, but `ExploreApp` does not
consume them).

## What happens under the covers

`explore` runs on top of [php-tui](https://github.com/php-tui/php-tui).
The application:

<!-- @steps -->
- **Browses lazily.** Only the currently-expanded folders are
  fetched. Expanding a folder issues a `browse()` for that NodeId
  and renders the children.

- **Caches values.** Once a Variable node's value has been read,
  it's cached for the session. Reload with `r` to force a fresh
  read.

- **Logs at the bottom.** Every action (browse, read, error)
  appears in the log pane. Useful for understanding what
  triggered each round-trip.

- **Drains input then sleeps 50 ms** per loop iteration —
  effectively a ~20 Hz tick. Snappy on every modern terminal.
<!-- @endsteps -->

## When to use it

- **First exploration of an unfamiliar server.** Faster than
  `browse --recursive` because you only descend into the parts you
  care about.
- **Verifying a path.** Click through to confirm
  `/Objects/PLC1/Speed` exists and has the value you expect.
- **Demoing to non-developers.** The TUI is intuitive enough
  that a process engineer can use it without reading docs.

## Limitations

- **Read-only.** No write support. Use `opcua-cli write` for
  modifications.
- **No subscriptions.** Values are point-in-time on read. Press
  `r` to refresh; for live updates use `opcua-cli watch`.
- **Terminal-dependent rendering.** Best on a 100×30+ terminal
  with UTF-8 + true-color. Tiny terminals will clip the panes.
- **No search.** Browse manually; no global node-name search yet.

## Common pitfalls

- **Slow expansion on huge folders.** A folder with 10 000
  children fetches them all on expand. Cap your patience or
  prefer `dump:nodeset` for full-address-space work.
- **Connection drops are not recovered.** A network blip during
  exploration disconnects the session. Press `q` and re-launch.
- **Untrusted certificates.** `explore` follows the same trust
  rules as the other commands. If the server cert is not yet
  trusted, the session fails to open and the TUI never starts —
  the CLI prints the helpful `trust <endpoint>` hint and exits.
  See [Trust store workflow](../connecting/trust-store-workflow.md).
