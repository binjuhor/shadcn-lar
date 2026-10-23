import {
  Apple,
  Baby,
  Banknote,
  Beer,
  Bike,
  Book,
  Briefcase,
  Building2,
  Bus,
  Calculator,
  Car,
  Cat,
  CirclePlus,
  Coffee,
  Coins,
  CreditCard,
  Dog,
  Droplet,
  Dumbbell,
  Ellipsis,
  Film,
  Flame,
  Fuel,
  Gamepad2,
  Gem,
  Gift,
  GraduationCap,
  Hammer,
  HandCoins,
  HandHeart,
  Heart,
  Hotel,
  House,
  Landmark,
  Laptop,
  Music,
  Package,
  PawPrint,
  Percent,
  PiggyBank,
  Pill,
  Pizza,
  Plane,
  Receipt,
  Repeat,
  Scissors,
  Shield,
  Shirt,
  ShoppingBag,
  ShoppingCart,
  Smartphone,
  Sofa,
  Sparkles,
  Stethoscope,
  Tag,
  Ticket,
  TrendingUp,
  Tv,
  Umbrella,
  Users,
  Utensils,
  Wallet,
  Wifi,
  Wrench,
  Zap,
  type LucideIcon,
} from 'lucide-react'
import { cn } from '@/lib/utils'

/**
 * Category icon keys (kebab-case Lucide names) stored in `finance_categories.icon`.
 * Explicit map keeps the bundle tree-shaken; keys must stay stable once saved.
 */
export const CATEGORY_ICONS: Record<string, LucideIcon> = {
  // Income & money
  wallet: Wallet,
  briefcase: Briefcase,
  'trending-up': TrendingUp,
  'plus-circle': CirclePlus,
  'piggy-bank': PiggyBank,
  landmark: Landmark,
  banknote: Banknote,
  coins: Coins,
  'hand-coins': HandCoins,
  'credit-card': CreditCard,
  percent: Percent,
  calculator: Calculator,
  receipt: Receipt,
  gem: Gem,
  // Food & shopping
  utensils: Utensils,
  coffee: Coffee,
  pizza: Pizza,
  apple: Apple,
  beer: Beer,
  'shopping-bag': ShoppingBag,
  'shopping-cart': ShoppingCart,
  shirt: Shirt,
  tag: Tag,
  package: Package,
  // Home & bills
  home: House,
  'building-2': Building2,
  sofa: Sofa,
  zap: Zap,
  droplet: Droplet,
  flame: Flame,
  wifi: Wifi,
  smartphone: Smartphone,
  tv: Tv,
  wrench: Wrench,
  hammer: Hammer,
  repeat: Repeat,
  // Transport & travel
  car: Car,
  fuel: Fuel,
  bus: Bus,
  bike: Bike,
  plane: Plane,
  hotel: Hotel,
  // Health & personal
  heart: Heart,
  stethoscope: Stethoscope,
  pill: Pill,
  dumbbell: Dumbbell,
  scissors: Scissors,
  sparkles: Sparkles,
  shield: Shield,
  umbrella: Umbrella,
  // Family, education & leisure
  users: Users,
  baby: Baby,
  book: Book,
  'graduation-cap': GraduationCap,
  laptop: Laptop,
  dog: Dog,
  cat: Cat,
  'paw-print': PawPrint,
  film: Film,
  music: Music,
  'gamepad-2': Gamepad2,
  ticket: Ticket,
  gift: Gift,
  'hand-heart': HandHeart,
  'more-horizontal': Ellipsis,
}

export const DEFAULT_CATEGORY_ICON = 'more-horizontal'
export const DEFAULT_CATEGORY_COLOR = '#6b7280'

/** Returns a known icon key, mapping empty or legacy free-text values to the default. */
export function normalizeCategoryIcon(icon?: string | null): string {
  return icon && Object.hasOwn(CATEGORY_ICONS, icon) ? icon : DEFAULT_CATEGORY_ICON
}

/** Initial form value: "" (same as parent) for subcategories without their own icon. */
export function initialCategoryIcon(category?: { icon?: string | null; parent_id?: number | null } | null): string {
  return category?.parent_id && !category.icon ? '' : normalizeCategoryIcon(category?.icon)
}

/**
 * Keeps the icon sensible when the parent changes: a new subcategory still on the default
 * switches to "same as parent", and a category that loses its parent gets a real icon back.
 */
export function iconForParent(icon: string, parentId: string): string {
  if (parentId) {
    return icon === DEFAULT_CATEGORY_ICON ? '' : icon
  }
  return icon || DEFAULT_CATEGORY_ICON
}

const sizes = {
  sm: { box: 'h-6 w-6', icon: 'h-3.5 w-3.5' },
  md: { box: 'h-8 w-8', icon: 'h-4 w-4' },
  lg: { box: 'h-10 w-10', icon: 'h-5 w-5' },
}

interface CategoryIconProps {
  icon?: string | null
  color?: string | null
  size?: keyof typeof sizes
  className?: string
}

/** Lucide icon on a tinted background of the category color; unknown icons fall back to the default. */
export function CategoryIcon({ icon, color, size = 'md', className }: CategoryIconProps) {
  const Icon = CATEGORY_ICONS[normalizeCategoryIcon(icon)]
  const tint = color || DEFAULT_CATEGORY_COLOR

  return (
    <span
      aria-hidden="true"
      className={cn('inline-flex shrink-0 items-center justify-center rounded-lg', sizes[size].box, className)}
      style={{ backgroundColor: `${tint}26`, color: tint }}
    >
      <Icon className={sizes[size].icon} />
    </span>
  )
}

interface CategoryIconPickerProps {
  /** Selected icon key; empty string means "same as parent". */
  value: string
  color?: string | null
  onChange: (icon: string) => void
  /** Parent's icon when the category is a subcategory; enables the "same as parent" option. */
  parentIcon?: string | null
  parentLabel?: string
}

const tileClass = (selected: boolean) =>
  cn(
    'flex h-10 w-10 items-center justify-center rounded-lg border-2 transition-all',
    selected ? 'border-primary bg-primary/10' : 'border-transparent bg-muted hover:border-muted-foreground/30'
  )

/** Grid of selectable category icons, previewed in the current category color. */
export function CategoryIconPicker({ value, color, onChange, parentIcon, parentLabel }: CategoryIconPickerProps) {
  const ParentIcon = CATEGORY_ICONS[normalizeCategoryIcon(parentIcon)]

  return (
    <div className="space-y-1.5">
      <div className="flex max-h-48 flex-wrap gap-2 overflow-y-auto p-0.5">
        {parentIcon !== undefined && (
          <button
            type="button"
            title={parentLabel}
            aria-label={parentLabel}
            aria-pressed={value === ''}
            className={cn(tileClass(value === ''), 'border-dashed', value !== '' && 'border-muted-foreground/40')}
            style={value === '' ? { color: color || undefined } : undefined}
            onClick={() => onChange('')}
          >
            <ParentIcon className="h-4 w-4" />
          </button>
        )}
        {Object.entries(CATEGORY_ICONS).map(([key, Icon]) => (
          <button
            key={key}
            type="button"
            title={key}
            aria-label={key}
            aria-pressed={value === key}
            className={tileClass(value === key)}
            style={value === key ? { color: color || undefined } : undefined}
            onClick={() => onChange(key)}
          >
            <Icon className="h-4 w-4" />
          </button>
        ))}
      </div>
      {parentIcon !== undefined && value === '' && <p className="text-xs text-muted-foreground">{parentLabel}</p>}
    </div>
  )
}
