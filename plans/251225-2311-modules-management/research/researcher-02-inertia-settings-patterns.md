# Inertia.js + React + Shadcn Settings Patterns Research

**Date:** 2025-12-25
**Focus:** Existing settings implementation patterns in codebase

## Controller Patterns

**File:** `app/Http/Controllers/SettingsController.php`

- **View rendering:** `Inertia::render('settings/{section}/index', ['settings' => [...]])` pattern
- **Settings data structure:** Fetch from authenticated user via `auth()->user()`
- **Response type:** Returns `Inertia\Response` for GET, `RedirectResponse` for mutations
- **Update pattern:** Uses dedicated `UpdateXyzRequest` validation classes
- **Mutation flow:** `validate()` → `$request->user()->update()` → `Redirect::back()->with('success', msg)`

**Key observation:** Minimal controller logic; validation delegated to FormRequest classes. Settings passed as flat array to frontend.

## Inertia Rendering Approach

- **Page component pattern:** Wraps content in context provider + layout wrapper
- **Data passing:** Props typed via TypeScript interfaces matching Inertia data
- **Layout:** `<SettingLayout>` provides header/structure; `<ContentSection>` provides titled subsections
- **Context:** `<SettingsProvider defaultTab="account">` manages sidebar navigation state

**Example flow:**
```
SettingsController → Inertia::render() → Page component → Provider → Layout → ContentSection → Form
```

## Form Handling Patterns

**Library Stack:**
- `react-hook-form` for form state management
- `zod` for schema validation (client + server)
- Shadcn/ui `<Form>` wrapper (headless abstraction over RHF)

**Standard form structure:**
```tsx
const form = useForm<TypeFromSchema>({
  resolver: zodResolver(schema),
  defaultValues: { ...settings }
})

function onSubmit(data) {
  router.patch('/api-route', data, {
    preserveScroll: true,
    onSuccess: () => toast({ title: 'Success' }),
    onError: (errors) => toast({ title: 'Error', description: errorMsg })
  })
}
```

**Field types implemented:**
- Text inputs: `<Input>` with validation messages
- Date pickers: `<Calendar>` popover with date constraints
- Dropdowns: `<Command>` + `<Popover>` for searchable selects
- Toggles: `<Switch>` with descriptions (often in bordered card)
- Radio groups: `<RadioGroup>` for mutually exclusive options
- Checkboxes: `<Checkbox>` for single selections

## TypeScript Types Structure

**Pattern:** Zod schemas as single source of truth

```typescript
// Schema definition
export const accountFormSchema = z.object({
  name: z.string().min(2).max(30),
  dob: z.date({ required_error: '...' }),
  language: z.string({ required_error: '...' })
})

// Inferred type from schema
export type AccountFormValues = z.infer<typeof accountFormSchema>

// Component props
interface Props {
  settings?: Partial<NotificationsFormValues>  // Using inferred type
}
```

**Benefits:** Single validation source used by server (Laravel FormRequest mirrors) and client.

## API Call Patterns

**Primary method:** Inertia `router.patch()` / `router.post()`

```typescript
router.patch('/dashboard/settings/{section}', data, {
  preserveScroll: true,      // Keep scroll position
  onSuccess: () => { /* toast */ },
  onError: (errors) => { /* toast with backend errors */ }
})
```

**Key advantages:**
- Built-in error handling with backend validation errors
- Automatic CSRF token handling
- Preserves page state (scroll, form focus)
- No manual loading states required

**Not used:** Direct `fetch()` API calls; all mutations go through Inertia router for consistency.

---

## Architecture Summary

| Layer | Pattern | Key Files |
|-------|---------|-----------|
| **Backend** | FormRequest validation → User model update | `SettingsController.php` |
| **Data Transfer** | Flat object via Inertia props | Schema-driven interfaces |
| **Frontend** | RHF + Zod + Shadcn Form wrapper | `account-form.tsx`, etc. |
| **UI Components** | Shadcn with Popover/Command composability | Calendar, Select, Switch, Checkbox |
| **Mutations** | Inertia router with callbacks | `router.patch()` with onSuccess/onError |

## Implementation Notes

- **No separate API routes:** Settings updates use controller patch methods directly
- **Validation mirrors:** Laravel FormRequest rules match Zod schemas
- **Composability:** Forms highly composable; each field isolated in FormField render prop
- **State management:** Form state isolated to component; no global store needed for settings
- **Error handling:** Backend validation errors passed to toast notifications automatically

---

## Unresolved Questions

1. How are FormRequest validation error messages mapped to zod error messages?
2. Is there a shared validation schema between Laravel and TypeScript or manual sync?
3. How are nested/complex settings (e.g., JSON columns) validated and typed?
