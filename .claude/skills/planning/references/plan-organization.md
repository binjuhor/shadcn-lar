# Plan Creation & Organization

## Directory Structure

### Plan Location
Save plans in `./plans` directory with timestamp and descriptive name.

**Format:** `plans/{date}-your-plan-name/` (date format from `$CK_PLAN_DATE_FORMAT`)

**Example:** `plans/20251101-1505-authentication-and-profile-implementation/`

### File Organization

```
plans/
├── 20251101-1505-authentication-and-profile-implementation/
    ├── research/
    │   ├── researcher-XX-report.md
    │   └── ...
│   ├── reports/
│   │   ├── scout-report.md
│   │   ├── researcher-report.md
│   │   └── ...
│   ├── plan.md                                # Overview access point
│   ├── phase-01-setup-environment.md          # Setup environment
│   ├── phase-02-implement-database.md         # Database models
│   ├── phase-03-implement-api-endpoints.md    # API endpoints
│   ├── phase-04-implement-ui-components.md    # UI components
│   ├── phase-05-implement-authentication.md   # Auth & authorization
│   ├── phase-06-implement-profile.md          # Profile page
│   └── phase-07-write-tests.md                # Tests
└── ...
```

### Active Plan State Tracking

#### Active vs Suggested Plans

| Type | Env Var | Meaning |
|------|---------|---------|
| **Active** | `$CK_ACTIVE_PLAN` | Explicitly set via `set-active-plan.cjs` - use for reports |
| **Suggested** | `$CK_SUGGESTED_PLAN` | Branch-matched, hint only - do NOT auto-use |

Plan context is managed via env vars and session state:
- **`$CK_ACTIVE_PLAN`**: Only set for explicitly activated plans (via session state)
- **`$CK_SUGGESTED_PLAN`**: Branch-matched plans shown as hints, not directives
- **Session temp file**: Stores explicit activations only, not auto-resolved plans

**Pre-Creation Check:**
1. Check `$CK_ACTIVE_PLAN` env var → if set and valid, ask "Continue with existing plan? [Y/n]"
2. Check `$CK_SUGGESTED_PLAN` env var → if set, inform user (hint only, do NOT auto-use)
3. If neither set → create new plan

**After Creating Plan:**
```bash
# Update session state so subagents get the new plan context:
node .claude/scripts/set-active-plan.cjs plans/{date}-plan-name
```

**Report Output Rules:**
1. Check `Plan Context` section injected by hooks for `Reports Path`
2. Only **active** plans (`$CK_ACTIVE_PLAN`) use plan-specific reports path
3. **Suggested** plans use default `plans/reports/` to prevent old plan pollution
4. Use naming: `{agent}-{date}-{slug}.md`

## File Structure

### Overview Plan (plan.md)
- Keep generic and under 80 lines
- List each phase with status/progress
- Link to detailed phase files
- Key dependencies

### Phase Files (phase-XX-name.md)
Fully respect the `./docs/development-rules.md` file.
Each phase file should contain:

**Context Links**
- Links to related reports, files, documentation

**Overview**
- Priority
- Current status
- Brief description

**Key Insights**
- Important findings from research
- Critical considerations

**Requirements**
- Functional requirements
- Non-functional requirements

**Architecture**
- System design
- Component interactions
- Data flow

**Related Code Files**
- List of files to modify
- List of files to create
- List of files to delete

**Implementation Steps**
- Detailed, numbered steps
- Specific instructions

**Todo List**
- Checkbox list for tracking

**Success Criteria**
- Definition of done
- Validation methods

**Risk Assessment**
- Potential issues
- Mitigation strategies

**Security Considerations**
- Auth/authorization
- Data protection

**Next Steps**
- Dependencies
- Follow-up tasks
