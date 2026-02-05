import { useEffect, useRef } from 'react'
import { useTranslation } from 'react-i18next'
import { ScrollArea } from '@/components/ui/scroll-area'
import { Bot } from 'lucide-react'
import { AdvisorMessageBubble } from './advisor-message-bubble'
import type { AdvisorMessage } from '@modules/Finance/types/finance'

interface AdvisorChatAreaProps {
  messages: AdvisorMessage[]
}

export function AdvisorChatArea({ messages }: AdvisorChatAreaProps) {
  const { t } = useTranslation()
  const bottomRef = useRef<HTMLDivElement>(null)

  useEffect(() => {
    bottomRef.current?.scrollIntoView({ behavior: 'smooth' })
  }, [messages])

  if (messages.length === 0) {
    return (
      <div className="flex-1 flex flex-col items-center justify-center p-6 text-center">
        <div className="rounded-full bg-primary/10 p-4 mb-4">
          <Bot className="h-8 w-8 text-primary" />
        </div>
        <p className="text-muted-foreground max-w-md">
          {t('page.advisor.welcome')}
        </p>
      </div>
    )
  }

  return (
    <ScrollArea className="flex-1 px-3 sm:px-4">
      <div className="max-w-2xl mx-auto py-3 sm:py-4 space-y-3">
        {messages.map((message) => (
          <AdvisorMessageBubble key={message.id} message={message} />
        ))}
        <div ref={bottomRef} />
      </div>
    </ScrollArea>
  )
}
