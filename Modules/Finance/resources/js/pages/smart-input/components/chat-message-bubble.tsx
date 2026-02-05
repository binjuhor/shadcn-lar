import { useTranslation } from 'react-i18next'
import { Loader2 } from 'lucide-react'
import { cn } from '@/lib/utils'
import { ChatAudioAttachment } from './chat-audio-attachment'
import { ChatImageAttachment } from './chat-image-attachment'
import { ChatTransactionCard } from './chat-transaction-card'
import type {
  Account,
  Category,
  ChatMessage,
} from '@modules/Finance/types/finance'

interface ChatMessageBubbleProps {
  message: ChatMessage
  accounts: Account[]
  categories: Category[]
  onSaveTransaction: (messageId: string, data: Record<string, unknown>) => void
}

export function ChatMessageBubble({
  message,
  accounts,
  categories,
  onSaveTransaction,
}: ChatMessageBubbleProps) {
  const { t } = useTranslation()

  // System message
  if (message.role === 'system') {
    return (
      <div className="flex justify-center py-2">
        <p className="text-xs text-muted-foreground text-center max-w-sm">
          {message.content}
        </p>
      </div>
    )
  }

  const isUser = message.role === 'user'

  return (
    <div className={cn('flex gap-2 py-1', isUser ? 'justify-end' : 'justify-start')}>
      <div
        className={cn(
          'max-w-[85%] rounded-2xl rounded-br-none',
          isUser
            ? 'bg-primary text-primary-foreground px-3.5 py-2'
            : 'w-full max-w-sm'
        )}
      >
        {isUser ? (
          <div className="space-y-2">
            {message.attachment?.type === 'image' && (
              <ChatImageAttachment url={message.attachment.url} />
            )}
            {message.attachment?.type === 'audio' && (
              <ChatAudioAttachment url={message.attachment.url} />
            )}
            <p className="text-sm whitespace-pre-wrap">{message.content}</p>
          </div>
        ) : (
          <div className="space-y-2">
            {message.isProcessing ? (
              <div className="flex items-center gap-2 px-3.5 py-2 bg-muted rounded-2xl">
                <Loader2 className="h-4 w-4 animate-spin text-muted-foreground" />
                <p className="text-sm text-muted-foreground">
                  {t('page.smart_input.chat_processing')}
                </p>
              </div>
            ) : message.error ? (
              <div className="px-3.5 py-2 bg-destructive/10 rounded-2xl">
                <p className="text-sm text-destructive">{message.error}</p>
              </div>
            ) : message.parsedTransaction ? (
              <div className="space-y-1.5">
                <p className="text-sm text-muted-foreground px-1">
                  {t('page.smart_input.chat_parsed')}
                </p>
                <ChatTransactionCard
                  messageId={message.id}
                  parsed={message.parsedTransaction}
                  accounts={accounts}
                  categories={categories}
                  isSaved={message.transactionSaved || false}
                  onSave={onSaveTransaction}
                />
                {message.transactionSaved && (
                  <p className="text-xs text-green-600 dark:text-green-400 px-1">
                    {t('page.smart_input.chat_saved')}
                  </p>
                )}
              </div>
            ) : (
              <div className="px-3.5 py-2 bg-muted rounded-2xl">
                <p className="text-sm">{message.content}</p>
              </div>
            )}
          </div>
        )}
      </div>
    </div>
  )
}
