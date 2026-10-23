import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { Check, Loader2, ShieldCheck } from 'lucide-react'
import { cn } from '@/lib/utils'
import { AdvisorMessageBubble } from './advisor-message-bubble'
import { ChatTransactionCard } from './chat-transaction-card'
import { ChatImageAttachment } from './chat-image-attachment'
import { ChatAudioAttachment } from './chat-audio-attachment'
import type {
  Account,
  AssistantAction,
  AssistantMessage,
  Category,
} from '@modules/Finance/types/finance'

interface AssistantMessageItemProps {
  message: AssistantMessage
  accounts: Account[]
  categories: Category[]
  onSaveDraft: (messageId: number, index: number, data: Record<string, unknown>) => Promise<void>
  onConfirm: (token: string) => Promise<void>
}

/**
 * One chat turn: the text bubble (markdown + charts) plus any attachment,
 * editable transaction drafts, and pending confirmation actions.
 */
export function AssistantMessageItem({
  message,
  accounts,
  categories,
  onSaveDraft,
  onConfirm,
}: AssistantMessageItemProps) {
  const isUser = message.role === 'user'
  const meta = message.meta
  const drafts = meta?.drafts ?? []
  const actions = meta?.actions ?? []

  const attachmentUrl = meta?.media_url ?? message.localAttachment?.url
  const isVoice = meta?.input_type === 'voice' || message.localAttachment?.type === 'audio'

  // Parser errors are stored as assistant messages flagged with meta.error
  const bubbleMessage = meta?.error && !message.error ? { ...message, error: message.content } : message

  return (
    <div className="space-y-2">
      {isUser && attachmentUrl && (
        <div className="flex justify-end">
          <div className="max-w-[85%] sm:max-w-[75%]">
            {isVoice ? <ChatAudioAttachment url={attachmentUrl} /> : <ChatImageAttachment url={attachmentUrl} />}
          </div>
        </div>
      )}

      {(bubbleMessage.content || bubbleMessage.isProcessing || bubbleMessage.error) && (
        <AdvisorMessageBubble message={bubbleMessage} />
      )}

      {drafts.length > 0 && (
        <div className="flex flex-col gap-2 max-w-[85%] sm:max-w-[75%]">
          {drafts.map((draft, index) => (
            <ChatTransactionCard
              key={`${message.id}-${index}`}
              messageId={`${message.id}-${index}`}
              parsed={{ ...draft, currency_code: draft.saved_currency_code ?? undefined }}
              accounts={accounts}
              categories={categories}
              isSaved={Boolean(draft.saved_transaction_id)}
              onSave={(_, data) => onSaveDraft(message.id, index, data)}
            />
          ))}
        </div>
      )}

      {actions.map((action, index) => (
        <ActionCard key={`${message.id}-action-${index}`} action={action} onConfirm={onConfirm} />
      ))}
    </div>
  )
}

function ActionCard({
  action,
  onConfirm,
}: {
  action: AssistantAction
  onConfirm: (token: string) => Promise<void>
}) {
  const { t } = useTranslation()
  const [isConfirming, setIsConfirming] = useState(false)
  const isPending = action.status === 'pending' && Boolean(action.token)

  const handleConfirm = async () => {
    if (!action.token) return
    setIsConfirming(true)
    await onConfirm(action.token)
    setIsConfirming(false)
  }

  return (
    <div className="max-w-[85%] sm:max-w-[75%] rounded-lg border bg-card p-3 shadow-sm">
      <div className="flex items-center gap-2 text-sm font-medium">
        <ShieldCheck className="h-4 w-4 text-primary" />
        {t('page.assistant.pending_change')}
        <span className="text-xs text-muted-foreground font-normal">
          {t(`page.assistant.tool.${action.tool}`, { defaultValue: action.tool })}
        </span>
      </div>

      <dl className="mt-2 grid grid-cols-[auto_1fr] gap-x-3 gap-y-1 text-xs">
        {Object.entries(action.preview).map(([key, value]) => (
          <div key={key} className="contents">
            <dt className="text-muted-foreground">{t(`page.assistant.field.${key}`, { defaultValue: key })}</dt>
            <dd className="break-words">{formatPreviewValue(key, value)}</dd>
          </div>
        ))}
      </dl>

      <div className="mt-3 flex justify-end">
        <Button
          size="sm"
          variant={isPending ? 'default' : 'secondary'}
          disabled={!isPending || isConfirming}
          onClick={handleConfirm}
          className={cn(!isPending && 'pointer-events-none')}
        >
          {isConfirming ? (
            <Loader2 className="h-4 w-4 mr-1 animate-spin" />
          ) : !isPending ? (
            <Check className="h-4 w-4 mr-1" />
          ) : null}
          {isPending ? t('page.assistant.confirm_change') : t('page.assistant.confirmed')}
        </Button>
      </div>
    </div>
  )
}

/** Amounts get thousands separators; nested values fall back to JSON. */
function formatPreviewValue(key: string, value: unknown): string {
  if (key === 'amount' && typeof value === 'number') return value.toLocaleString()
  if (value !== null && typeof value === 'object') return JSON.stringify(value)
  return String(value ?? '')
}
