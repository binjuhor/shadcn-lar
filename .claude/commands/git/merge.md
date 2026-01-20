---
description: ⚠️ Merge code from one branch to another
argument-hint: [branch] [from-branch]
---

## Variables

TO_BRANCH: $1 (defaults to `main`)
FROM_BRANCH: $2 (defaults to current branch)

## Workflow
- Use `git-manager` agent to merge {FROM_BRANCH} to {TO_BRANCH} branch and resolve all conflicts if any.

## Notes
- If `gh` command is not available, instruct the user to install and authorize GitHub CLI first.
- If you need more clarifications, use `AskUserQuestion` tool to ask the user for more details.