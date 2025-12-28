import { useState } from 'react'
import { router } from '@inertiajs/react'
import { toast } from '@/hooks/use-toast'
import { Switch } from '@/components/ui/switch'
import { Badge } from '@/components/ui/badge'
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
import { IconLoader2 } from '@tabler/icons-react'
import { type Module } from '@/types'

interface Props {
  modules: Module[]
}

export function ModulesForm({ modules: initialModules }: Props) {
  const [modules, setModules] = useState<Module[]>(initialModules)
  const [processing, setProcessing] = useState<string | null>(null)
  const [pendingDisable, setPendingDisable] = useState<Module | null>(null)

  function handleToggle(module: Module) {
    if (module.isCore) {
      toast({
        title: 'Cannot disable core module',
        description: `${module.name} is required for system operation.`,
        variant: 'destructive',
      })
      return
    }

    // Show warning when disabling
    if (module.enabled) {
      setPendingDisable(module)
      return
    }

    executeToggle(module)
  }

  function executeToggle(module: Module) {
    const previousState = [...modules]

    setModules(prev =>
      prev.map(m =>
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
        {modules.map((module) => (
          <div
            key={module.name}
            className='flex flex-row items-center justify-between rounded-lg border p-4'
          >
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
            <div className='flex items-center gap-2'>
              {processing === module.name && (
                <IconLoader2 className='h-4 w-4 animate-spin text-muted-foreground' />
              )}
              <Switch
                checked={module.enabled}
                onCheckedChange={() => handleToggle(module)}
                disabled={module.isCore || processing === module.name}
                aria-readonly={module.isCore}
              />
            </div>
          </div>
        ))}
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
