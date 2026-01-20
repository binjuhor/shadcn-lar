---
description: ⚡⚡⚡ Research, analyze, and create an implementation plan
argument-hint: [task]
---

Think harder.
Activate `planning` skill.

## Your mission
<task>
$ARGUMENTS
</task>

## Pre-Creation Check (Active vs Suggested Plan)

Before creating plan folder, check plan state:

1. **Check `$CK_ACTIVE_PLAN` (explicitly active):**
   - If set AND points to valid directory:
     - Ask user: "Active plan found: {path}. Continue with this? [Y/n]"
     - If Y (default): Use existing path, skip folder creation
     - If n: Proceed to create new plan

2. **Check `$CK_SUGGESTED_PLAN` (branch-matched, NOT active):**
   - If set: Inform user "Found suggested plan from branch: {path}"
   - This is a hint only - do NOT auto-use it
   - Ask user if they want to activate it or create new plan

3. **If neither is set:** Proceed to create new plan

4. **Create plan folder** (only if creating new):
   - Generate: `plans/{date}-plan-name` (date format from `$CK_PLAN_DATE_FORMAT`)
   - Update session state: `node .claude/scripts/set-active-plan.cjs plans/{date}-plan-name`

## Workflow
1. If creating new plan: Create directory `plans/{date}-plan-name` and run `node .claude/scripts/set-active-plan.cjs plans/...`
   If reusing existing: Use the active plan path from `$CK_ACTIVE_PLAN`.
   Make sure you pass the directory path to every subagent during the process.
2. Follow strictly to the "Plan Creation & Organization" rules of `planning` skill.
3. Use multiple `researcher` agents (max 2 agents) in parallel to research for this task: 
   Each agent research for a different aspect of the task and are allowed to perform max 5 tool calls.
4. Analyze the codebase by reading `codebase-summary.md`, `code-standards.md`, `system-architecture.md` and `project-overview-pdr.md` file.
   **ONLY PERFORM THIS FOLLOWING STEP IF `codebase-summary.md` is not available or older than 3 days**: Use `/scout <instructions>` slash command to search the codebase for files needed to complete the task.
5. Main agent gathers all research and scout report filepaths, and pass them to `planner` subagent with the prompt to create an implementation plan of this task.
6. Main agent receives the implementation plan from `planner` subagent, and ask user to review the plan

## Output Requirements

**Plan Directory Structure**
```
plans/
└── {date}-plan-name/
    ├── research/
    │   ├── researcher-XX-report.md
    │   └── ...
    ├── reports/
    │   ├── XX-report.md
    │   └── ...
    ├── scout/
    │   ├── scout-XX-report.md
    │   └── ...
    ├── plan.md
    ├── phase-XX-phase-name-here.md
    └── ...
```

**Research Output Requirements**
- Ensure every research markdown report remains concise (≤150 lines) while covering all requested topics and citations.

**Plan File Specification**
- Save the overview access point at `plans/{date}-plan-name/plan.md`. Keep it generic, under 80 lines, and list each implementation phase with status and progress plus links to phase files.
- For each phase, create `plans/{date}-plan-name/phase-XX-phase-name-here.md` containing the following sections in order: Context links (reference parent plan, dependencies, docs), Overview (date, description, priority, implementation status, review status), Key Insights, Requirements, Architecture, Related code files, Implementation Steps, Todo list, Success Criteria, Risk Assessment, Security Considerations, Next steps.

## Important Notes
**IMPORTANT:** Analyze the skills catalog and activate the skills that are needed for the task during the process.
**IMPORTANT:** Ensure token efficiency while maintaining high quality.
**IMPORTANT:** Sacrifice grammar for the sake of concision when writing reports.
**IMPORTANT:** In reports, list any unresolved questions at the end, if any.
**IMPORTANT**: **Do not** start implementing.
