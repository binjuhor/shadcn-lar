# Vue.js → React Migration Research Report

**Date:** 2025-12-19 | **Project:** shadcn-admin (Mokey Finance Module)

## Executive Summary

Your project is already React 19 + TypeScript with shadcn/ui + Inertia.js React. Migration from Vue patterns is straightforward since stack is pre-configured. Focus: component API differences, hook patterns, form handling, and chart libraries.

---

## 1. Component Mapping: shadcn-vue → shadcn/ui

| Vue Pattern | React Pattern | Notes |
|---|---|---|
| `<template>` blocks | JSX/TSX | Functional components, return JSX |
| `ref()` reactive | `useState()` hook | State management |
| `computed` | `useMemo()` or derived state | Memoized values |
| `watch` | `useEffect()` hook | Side effects, dependencies |
| `v-model` | Controlled components + onChange | Props + callbacks |
| `v-if/v-show` | Conditional rendering (&&, ternary) | No v-show equivalent, use CSS |
| `v-for` | `.map()` iteration | Key prop required |
| `@click`, `@input` | `onClick`, `onChange` props | camelCase events |
| Slots | `children` prop + composition | React.ReactNode typing |
| Emits (`defineEmits`) | Callback props | Pass functions as props |

**shadcn Component API:** Both versions expose same component names (Button, Card, Dialog, etc.) but React uses hooks-based state internally. Direct prop API is nearly identical.

---

## 2. State Management Migration

### Vue Ref → React useState
```vue
<!-- Vue -->
<script setup>
const count = ref(0)
const increment = () => count.value++
</script>
<template>
  <button @click="increment">{{ count }}</button>
</template>
```

```tsx
// React
const [count, setCount] = useState(0)
const increment = () => setCount(count + 1)
return <button onClick={increment}>{count}</button>
```

### Vue Reactive → React with Jotai (Already in your stack)
```typescript
// Jotai (preferred for global state in your project)
import { atom, useAtom } from 'jotai'

export const userAtom = atom({ name: '', email: '' })

// In component:
const [user, setUser] = useAtom(userAtom)
```

**Recommendation:** Use jotai (already installed) for cross-component state. Avoid Pinia migration—React has no direct equivalent; jotai is lighter.

---

## 3. Form Handling: vee-validate → react-hook-form + Zod

### Key Differences:
- Vue uses directive binding (`v-model`, custom validators)
- React uses controller pattern + schema validation

```tsx
// react-hook-form + zod (already in stack)
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'

const schema = z.object({
  email: z.string().email('Invalid email'),
  password: z.string().min(8, 'Min 8 chars'),
})

export function LoginForm() {
  const { register, handleSubmit, formState: { errors } } = useForm({
    resolver: zodResolver(schema),
  })

  return (
    <form onSubmit={handleSubmit((data) => console.log(data))}>
      <input {...register('email')} />
      {errors.email && <span>{errors.email.message}</span>}
      <button type="submit">Login</button>
    </form>
  )
}
```

**Pattern:** Use `react-hook-form` for all forms. Define Zod schemas separately for type inference. This avoids imperative validation logic.

---

## 4. Chart Library Migration: ApexCharts (Vue) → Recharts (React)

### API Differences:

| Vue (ApexCharts) | React (Recharts) |
|---|---|
| `ApexChart` component + options object | Composable chart hierarchy (`<BarChart>`, `<CartesianGrid>`, etc.) |
| State: `series`, `options` props | Declarative: pass data to `<BarChart data={data}>` |
| Dynamic updates: mutate state | Re-render via React rendering cycle |
| Type safety: None | Full TypeScript support via generics |

```tsx
// Recharts (declarative, already installed)
import { BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip } from 'recharts'

const data = [
  { name: 'Jan', value: 400 },
  { name: 'Feb', value: 300 },
]

export function Dashboard() {
  return (
    <BarChart width={600} height={300} data={data}>
      <CartesianGrid strokeDasharray="3 3" />
      <XAxis dataKey="name" />
      <YAxis />
      <Tooltip />
      <Bar dataKey="value" fill="#8884d8" />
    </BarChart>
  )
}
```

**No ApexCharts equivalent needed.** Recharts is superior for React; swap all chart logic 1:1.

---

## 5. Inertia.js Integration Patterns

### State Flow: Laravel → Inertia → React

```tsx
// Laravel Controller
public function dashboard(): Response
{
    return Inertia::render('Dashboard', [
        'charts' => $this->getChartData(),
        'users' => User::all(),
    ])
}

// React Component
import { usePage } from '@inertiajs/react'

export default function Dashboard() {
  const { props } = usePage()
  const { charts, users } = props

  return (
    <div>
      {/* Use charts & users data */}
    </div>
  )
}
```

**Key patterns:**
- `usePage()` hook replaces Vue's `$page`
- `useForm()` hook replaces vee-validate for Inertia forms
- Server-driven rendering with type-safe Laravel → React flow

---

## 6. Critical Gotchas & Recommendations

### Gotcha 1: Event Handler Binding
```tsx
// WRONG: Function called on render
<button onClick={handleClick()}>Click</button>

// CORRECT: Function passed, not invoked
<button onClick={handleClick}>Click</button>
<button onClick={() => handleClick(id)}>Click</button> // With params
```

### Gotcha 2: Dependency Arrays in useEffect
```tsx
// WRONG: Missing dependencies, stale closures
useEffect(() => {
  fetch('/api/data').then(setData)
}, []) // Will never refetch if deps change

// CORRECT: Include all external dependencies
useEffect(() => {
  if (id) fetch(`/api/data/${id}`).then(setData)
}, [id]) // Refetches when id changes
```

### Gotcha 3: Conditional Rendering
```tsx
// WRONG: Fragments need keys in lists
{items.map(item => (
  <>{item.name}</> // Bad pattern
))}

// CORRECT: Use a proper container or assign key
{items.map(item => (
  <div key={item.id}>{item.name}</div>
))}
```

### Gotcha 4: Form Value Synchronization
```tsx
// WRONG: Manual sync with onChange
const [email, setEmail] = useState('')
<input value={email} onChange={e => setEmail(e.target.value)} />

// CORRECT: Use react-hook-form for complex forms
const { register } = useForm()
<input {...register('email')} />
```

---

## 7. Migration Checklist by Component Type

### Dashboard with Charts
- [ ] Replace ApexCharts with Recharts composition
- [ ] Move chart data fetching to `useEffect` with dependencies
- [ ] Use `@tanstack/react-query` (installed) for server data
- [ ] Apply real-time updates via Inertia polling or WebSockets

### Forms with Validation
- [ ] Define Zod schemas
- [ ] Replace vee-validate with `useForm` + `zodResolver`
- [ ] Use `<Controller>` for custom inputs (shadcn components)
- [ ] Migrate error display via `formState.errors`

### Data Tables
- [ ] Adopt `@tanstack/react-table` (v8, installed)
- [ ] Define column configs with TypeScript generics
- [ ] Implement filtering/sorting via table instance
- [ ] No breaking changes; straightforward mapping

### CRUD Operations
- [ ] Keep Inertia.js form helpers (`useForm()`)
- [ ] Use React Query for fetch caching (optional but recommended)
- [ ] Callbacks: replace `$emit` with prop callbacks

---

## 8. Technology Stack Summary

| Layer | Vue Version | React Version | Status |
|---|---|---|---|
| UI Components | shadcn-vue | shadcn/ui | ✅ Installed |
| State | ref/reactive | useState + jotai | ✅ Ready |
| Forms | vee-validate + yup | react-hook-form + zod | ✅ Installed |
| Charts | ApexCharts | Recharts | ✅ Installed |
| Tables | Any | @tanstack/react-table | ✅ Installed |
| Server | Inertia Vue | Inertia React | ✅ Configured |
| Query | TanStack Query | TanStack Query v5 | ✅ Installed |

---

## Unresolved Questions

1. **ApexCharts to Recharts: Custom themes?** Check if ApexCharts uses custom colors; Recharts supports via `<Bar fill="#color" />` props.
2. **Real-time updates:** Will you use Inertia polling, WebSockets, or Server-Sent Events?
3. **Type generation:** Automate Laravel → TypeScript types via `laravel-typescript` or manual Zod?

---

**Next Step:** Implement migration using react skill (component-by-component refactor pattern).
