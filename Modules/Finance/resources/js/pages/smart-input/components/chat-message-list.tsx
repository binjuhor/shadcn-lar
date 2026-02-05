import { useEffect, useRef } from 'react'
import { ScrollArea } from '@/components/ui/scroll-area'
import { ChatMessageBubble } from './chat-message-bubble'
import type {
  Account,
  Category,
  ChatMessage,
} from '@modules/Finance/types/finance'

interface ChatMessageListProps {
  messages: ChatMessage[]
  accounts: Account[]
  categories: Category[]
  onSaveTransaction: (messageId: string, data: Record<string, unknown>) => void
}

export function ChatMessageList({
  messages,
  accounts,
  categories,
  onSaveTransaction,
}: ChatMessageListProps) {
  const bottomRef = useRef<HTMLDivElement>(null)

  useEffect(() => {
    bottomRef.current?.scrollIntoView({ behavior: 'smooth' })
  }, [messages])

  return (
    <ScrollArea className="flex-1 px-3 sm:px-4">
      <div className="max-w-[calc(100vw-3.5rem)] mx-auto py-3 sm:py-4 space-y-1">
        {messages.map((message) => (
          <ChatMessageBubble
            key={message.id}
            message={message}
            accounts={accounts}
            categories={categories}
            onSaveTransaction={onSaveTransaction}
          />
        ))}
        <div ref={bottomRef} />
      </div>
    </ScrollArea>
  )
}
