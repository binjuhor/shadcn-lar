import { useEffect, useRef } from 'react'
import { useTranslation } from 'react-i18next'
import { ScrollArea } from '@/components/ui/scroll-area'
import { MessageCircle } from 'lucide-react'
import { AssistantMessageItem } from './assistant-message-item'
import type { Account, AssistantMessage, Category } from '@modules/Finance/types/finance'

interface AssistantChatAreaProps {
  messages: AssistantMessage[]
  accounts: Account[]
  categories: Category[]
  onSaveDraft: (messageId: number, index: number, data: Record<string, unknown>) => Promise<void>
  onConfirm: (token: string) => Promise<void>
}

export function AssistantChatArea({
  messages,
  accounts,
  categories,
  onSaveDraft,
  onConfirm,
}: AssistantChatAreaProps) {
  const { t } = useTranslation()
  const bottomRef = useRef<HTMLDivElement>(null)

  useEffect(() => {
    bottomRef.current?.scrollIntoView({ behavior: 'smooth' })
  }, [messages.length])

  if (messages.length === 0) {
    return (
      <div className="flex-1 flex flex-col items-center justify-center p-6 text-center">
        <div className="rounded-full bg-primary/10 p-4 mb-4">
          <MessageCircle className="h-8 w-8 text-primary" />
        </div>
        <p className="text-muted-foreground max-w-md">{t('page.assistant.welcome')}</p>
      </div>
    )
  }

  return (
    <ScrollArea className="flex-1 px-3 sm:px-4">
      <div className="max-w-2xl mx-auto py-3 sm:py-4 space-y-3">
        {messages.map((message) => (
          <AssistantMessageItem
            key={message.id}
            message={message}
            accounts={accounts}
            categories={categories}
            onSaveDraft={onSaveDraft}
            onConfirm={onConfirm}
          />
        ))}
        <div ref={bottomRef} />
      </div>
    </ScrollArea>
  )
}
