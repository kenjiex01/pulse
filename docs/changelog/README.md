# Daily Code Changelog

Append-only log of all `pulse/` code changes, maintained by the Cursor agent.

## Files

| Pattern | Purpose |
|---------|---------|
| `YYYY-MM-DD.md` | One file per calendar day |

## For Agents

See `.cursor/rules/code-changelog.mdc` — append an entry after every coding task.

## For Humans

**Read today's log:**

```bash
cat docs/changelog/$(date +%Y-%m-%d).md
```

**Generate a machine snapshot (commits + modified files + diff stats):**

```bash
./scripts/changelog-today.sh
```

**List all changelog days:**

```bash
ls docs/changelog/*.md
```
