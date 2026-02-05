import { useTranslation } from 'react-i18next'
import { router } from '@inertiajs/react'
import { cn } from '@/lib/utils'
import { Button } from '@/components/ui/button'
import { ScrollArea } from '@/components/ui/scroll-area'
import { Plus, Trash2, MessageSquare } from 'lucide-react'
import type { AdvisorConversationSummary } from '@modules/Finance/types/finance'

interface AdvisorConversationListProps {
  conversations: AdvisorConversationSummary[]
  activeId: number | null
  onNewConversation: () => void
  onDeleteConversation: (id: number) => void
}

function getCsrfToken(): string {
  return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
}

export function AdvisorConversationList({
  conversations,
  activeId,
  onNewConversation,
  onDeleteConversation,
}: AdvisorConversationListProps) {
  const { t } = useTranslation()

  const handleSelect = (id: number) => {
    router.get(
      route('dashboard.finance.advisor', { conversation: id }),
      {},
      { preserveState: false }
    )
  }

  const handleDelete = async (e: React.MouseEvent, id: number) => {
    e.stopPropagation()
    if (!confirm(t('page.advisor.delete_confirm'))) return

    try {
      await fetch(route('dashboard.finance.advisor.conversations.destroy', id), {
        method: 'DELETE',
        headers: {
          'X-CSRF-TOKEN': getCsrfToken(),
          Accept: 'application/json',
        },
      })
      onDeleteConversation(id)
    } catch {
      // Silently fail
    }
  }

  return (
    <div className="flex flex-col h-full">
      <div className="p-3 border-b">
        <Button
          variant="outline"
          size="sm"
          className="w-full"
          onClick={onNewConversation}
        >
          <Plus className="h-4 w-4 mr-1" />
          {t('page.advisor.new_conversation')}
        </Button>
      </div>

      <ScrollArea className="flex-1">
        {conversations.length === 0 ? (
          <div className="p-4 text-center text-sm text-muted-foreground">
            {t('page.advisor.no_conversations')}
          </div>
        ) : (
          <div className="p-1 w-72 pr-4">
            {conversations.map((conv) => (
              <div
                key={conv.id}
                role="button"
                tabIndex={0}
                onClick={() => handleSelect(conv.id)}
                onKeyDown={(e) => { if (e.key === 'Enter') handleSelect(conv.id) }}
                className={cn(
                  'w-full text-left mb-3 px-3 py-2.5 rounded-lg text-sm transition-colors group cursor-pointer',
                  'hover:bg-accent',
                  activeId === conv.id && 'bg-accent'
                )}
              >
                <div className="flex items-start gap-2">
                  <MessageSquare className="h-4 w-4 mt-0.5 shrink-0 text-muted-foreground" />
                  <div className="flex-1 min-w-0">
                    <div className="font-medium truncate">{conv.title}</div>
                    {conv.preview && (
                      <div className="text-xs text-muted-foreground truncate mt-0.5">
                        {conv.preview}
                      </div>
                    )}
                  </div>
                  <button
                    onClick={(e) => handleDelete(e, conv.id)}
                    className="opacity-0 group-hover:opacity-100 p-1 rounded hover:bg-destructive/10 transition-opacity shrink-0"
                  >
                    <Trash2 className="h-3.5 w-3.5 text-muted-foreground hover:text-destructive" />
                  </button>
                </div>
              </div>
            ))}
          </div>
        )}
      </ScrollArea>
    </div>
  )
}
