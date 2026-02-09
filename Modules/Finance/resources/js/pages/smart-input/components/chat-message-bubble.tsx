import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Image, Mic, Type, Loader2 } from 'lucide-react'
import { cn } from '@/lib/utils'
import {
  Dialog,
  DialogContent,
  DialogTitle,
} from '@/components/ui/dialog'
import { VisuallyHidden } from '@radix-ui/react-visually-hidden'
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
  const [mediaOpen, setMediaOpen] = useState(false)

  const inputTypeConfig = {
    image: { icon: Image, label: t('page.smart_input.type_image'), color: 'text-blue-600 bg-blue-50 dark:bg-blue-950 hover:bg-blue-100 dark:hover:bg-blue-900' },
    text_image: { icon: Image, label: t('page.smart_input.type_text_image'), color: 'text-blue-600 bg-blue-50 dark:bg-blue-950 hover:bg-blue-100 dark:hover:bg-blue-900' },
    voice: { icon: Mic, label: t('page.smart_input.type_voice'), color: 'text-purple-600 bg-purple-50 dark:bg-purple-950 hover:bg-purple-100 dark:hover:bg-purple-900' },
    text: { icon: Type, label: t('page.smart_input.type_text'), color: 'text-gray-600 bg-gray-50 dark:bg-gray-900' },
  }

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
                <div className="flex items-center gap-2 px-1">
                  <p className="text-sm text-muted-foreground">
                    {t('page.smart_input.chat_parsed')}
                  </p>
                  {message.inputType && message.inputType !== 'text' && (() => {
                    const config = inputTypeConfig[message.inputType]
                    const Icon = config.icon
                    const hasMedia = message.attachment?.url
                    return (
                      <>
                        <button
                          type="button"
                          onClick={() => hasMedia && setMediaOpen(true)}
                          className={cn(
                            'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-medium transition-colors',
                            config.color,
                            hasMedia && 'cursor-pointer'
                          )}
                        >
                          <Icon className="h-3 w-3" />
                          {config.label}
                        </button>

                        {hasMedia && (
                          <Dialog open={mediaOpen} onOpenChange={setMediaOpen}>
                            <DialogContent className="max-w-[90vw] max-h-[90vh] w-auto p-0 border-0 bg-transparent shadow-none [&>button]:text-white [&>button]:bg-black/50 [&>button]:rounded-full [&>button]:p-1.5 [&>button]:top-2 [&>button]:right-2">
                              <VisuallyHidden>
                                <DialogTitle>{config.label}</DialogTitle>
                              </VisuallyHidden>
                              {message.attachment?.type === 'image' ? (
                                <img
                                  src={message.attachment.url}
                                  alt={config.label}
                                  className="max-w-[90vw] max-h-[85vh] object-contain rounded-lg"
                                />
                              ) : message.attachment?.type === 'audio' ? (
                                <div className="bg-background rounded-lg p-6">
                                  <audio controls src={message.attachment.url} className="w-full" />
                                </div>
                              ) : null}
                            </DialogContent>
                          </Dialog>
                        )}
                      </>
                    )
                  })()}
                </div>
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
