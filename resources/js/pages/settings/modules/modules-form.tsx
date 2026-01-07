import { useState, useCallback } from 'react'
import { router, usePage } from '@inertiajs/react'
import {
  DndContext,
  closestCenter,
  KeyboardSensor,
  PointerSensor,
  useSensor,
  useSensors,
  type DragEndEvent,
} from '@dnd-kit/core'
import {
  arrayMove,
  SortableContext,
  sortableKeyboardCoordinates,
  useSortable,
  verticalListSortingStrategy,
} from '@dnd-kit/sortable'
import { CSS } from '@dnd-kit/utilities'
import { toast } from '@/hooks/use-toast'
import { Switch } from '@/components/ui/switch'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog'
import { IconGripVertical, IconLoader2 } from '@tabler/icons-react'
import { type Module, type PageProps } from '@/types'
import { cn } from '@/lib/utils'

interface Props {
  modules: Module[]
}

interface SortableModuleItemProps {
  module: Module
  processing: string | null
  onToggle: (module: Module) => void
}

function SortableModuleItem({ module, processing, onToggle }: SortableModuleItemProps) {
  const {
    attributes,
    listeners,
    setNodeRef,
    transform,
    transition,
    isDragging,
  } = useSortable({ id: module.name })

  const style = {
    transform: CSS.Transform.toString(transform),
    transition,
  }

  return (
    <div
      ref={setNodeRef}
      style={style}
      className={cn(
        'flex flex-row items-center justify-between rounded-lg border p-4',
        isDragging && 'opacity-50 shadow-lg z-50 bg-background'
      )}
    >
      <div className='flex items-center gap-3'>
        <button
          type='button'
          className='cursor-grab touch-none text-muted-foreground hover:text-foreground'
          {...attributes}
          {...listeners}
        >
          <IconGripVertical size={20} />
        </button>
        <div className='space-y-0.5'>
          <div className='flex items-center gap-2'>
            <span className='text-base font-medium'>{module.name}</span>
            {module.isCore && (
              <Badge variant='secondary' className='text-xs'>
                Core
              </Badge>
            )}
            <Badge
              variant={module.enabled ? 'default' : 'outline'}
              className='text-xs'
            >
              {module.enabled ? 'Enabled' : 'Disabled'}
            </Badge>
          </div>
          {module.description && (
            <p className='text-sm text-muted-foreground'>
              {module.description}
            </p>
          )}
          {module.keywords.length > 0 && (
            <div className='flex gap-1 pt-1'>
              {module.keywords.slice(0, 3).map((keyword) => (
                <Badge key={keyword} variant='outline' className='text-xs'>
                  {keyword}
                </Badge>
              ))}
            </div>
          )}
        </div>
      </div>
      <div className='flex items-center gap-2'>
        {processing === module.name && (
          <IconLoader2 className='h-4 w-4 animate-spin text-muted-foreground' />
        )}
        <Switch
          checked={module.enabled}
          onCheckedChange={() => onToggle(module)}
          disabled={module.isCore || processing === module.name}
          aria-readonly={module.isCore}
        />
      </div>
    </div>
  )
}

export function ModulesForm({ modules: initialModules }: Props) {
  const { sidebarSettings } = usePage<PageProps>().props
  const savedOrder = sidebarSettings?.module_order || []

  // Sort modules based on saved order, fallback to priority
  const sortModules = useCallback((mods: Module[]) => {
    if (savedOrder.length === 0) {
      return [...mods].sort((a, b) => a.priority - b.priority)
    }
    return [...mods].sort((a, b) => {
      const aIndex = savedOrder.indexOf(a.name)
      const bIndex = savedOrder.indexOf(b.name)
      if (aIndex === -1 && bIndex === -1) return a.priority - b.priority
      if (aIndex === -1) return 1
      if (bIndex === -1) return -1
      return aIndex - bIndex
    })
  }, [savedOrder])

  const [modules, setModules] = useState<Module[]>(() => sortModules(initialModules))
  const [processing, setProcessing] = useState<string | null>(null)
  const [pendingDisable, setPendingDisable] = useState<Module | null>(null)
  const [orderChanged, setOrderChanged] = useState(false)
  const [savingOrder, setSavingOrder] = useState(false)

  const sensors = useSensors(
    useSensor(PointerSensor),
    useSensor(KeyboardSensor, {
      coordinateGetter: sortableKeyboardCoordinates,
    })
  )

  function handleDragEnd(event: DragEndEvent) {
    const { active, over } = event

    if (over && active.id !== over.id) {
      setModules((items) => {
        const oldIndex = items.findIndex((item) => item.name === active.id)
        const newIndex = items.findIndex((item) => item.name === over.id)
        return arrayMove(items, oldIndex, newIndex)
      })
      setOrderChanged(true)
    }
  }

  function saveOrder() {
    setSavingOrder(true)
    const order = modules.map((m) => m.name)

    router.patch(
      '/dashboard/settings/modules/reorder',
      { order },
      {
        preserveScroll: true,
        onSuccess: () => {
          toast({
            title: 'Order saved',
            description: 'Sidebar module order has been updated.',
          })
          setOrderChanged(false)
          setSavingOrder(false)
        },
        onError: (errors) => {
          toast({
            title: 'Error saving order',
            description: Object.values(errors).flat().join(', '),
            variant: 'destructive',
          })
          setSavingOrder(false)
        },
      }
    )
  }

  function handleToggle(module: Module) {
    if (module.isCore) {
      toast({
        title: 'Cannot disable core module',
        description: `${module.name} is required for system operation.`,
        variant: 'destructive',
      })
      return
    }

    if (module.enabled) {
      setPendingDisable(module)
      return
    }

    executeToggle(module)
  }

  function executeToggle(module: Module) {
    const previousState = [...modules]

    setModules((prev) =>
      prev.map((m) =>
        m.name === module.name ? { ...m, enabled: !m.enabled } : m
      )
    )
    setProcessing(module.name)
    setPendingDisable(null)

    router.patch(
      '/dashboard/settings/modules/toggle',
      { name: module.name },
      {
        preserveScroll: true,
        onSuccess: () => {
          const action = module.enabled ? 'disabled' : 'enabled'
          toast({
            title: `Module ${action}`,
            description: `${module.name} has been ${action} successfully.`,
          })
          setProcessing(null)
        },
        onError: (errors) => {
          setModules(previousState)
          toast({
            title: 'Error toggling module',
            description: Object.values(errors).flat().join(', '),
            variant: 'destructive',
          })
          setProcessing(null)
        },
      }
    )
  }

  return (
    <>
      <div className='space-y-4'>
        <div className='flex items-center justify-between'>
          <p className='text-sm text-muted-foreground'>
            Drag modules to reorder them in the sidebar.
          </p>
          {orderChanged && (
            <Button
              size='sm'
              onClick={saveOrder}
              disabled={savingOrder}
            >
              {savingOrder && <IconLoader2 className='mr-2 h-4 w-4 animate-spin' />}
              Save Order
            </Button>
          )}
        </div>

        <DndContext
          sensors={sensors}
          collisionDetection={closestCenter}
          onDragEnd={handleDragEnd}
        >
          <SortableContext
            items={modules.map((m) => m.name)}
            strategy={verticalListSortingStrategy}
          >
            <div className='space-y-2'>
              {modules.map((module) => (
                <SortableModuleItem
                  key={module.name}
                  module={module}
                  processing={processing}
                  onToggle={handleToggle}
                />
              ))}
            </div>
          </SortableContext>
        </DndContext>
      </div>

      <AlertDialog
        open={!!pendingDisable}
        onOpenChange={(open) => !open && setPendingDisable(null)}
      >
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Disable {pendingDisable?.name}?</AlertDialogTitle>
            <AlertDialogDescription>
              Disabling this module will remove its functionality from the system.
              Other features or modules may depend on it. Are you sure you want to continue?
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>Cancel</AlertDialogCancel>
            <AlertDialogAction
              onClick={() => pendingDisable && executeToggle(pendingDisable)}
              className='bg-destructive text-destructive-foreground hover:bg-destructive/90'
            >
              Disable Module
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </>
  )
}
