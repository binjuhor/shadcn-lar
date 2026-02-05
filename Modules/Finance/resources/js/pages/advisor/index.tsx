import { useState, useCallback } from 'react'
import { useTranslation } from 'react-i18next'
import { router } from '@inertiajs/react'
import { AuthenticatedLayout } from '@/layouts'
import { Main } from '@/components/layout/main'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Bot, Settings, PanelLeftClose, PanelLeft } from 'lucide-react'
import { AdvisorConversationList } from './components/advisor-conversation-list'
import { AdvisorChatArea } from './components/advisor-chat-area'
import { AdvisorInputBar } from './components/advisor-input-bar'
import type {
  AdvisorConversationSummary,
  AdvisorMessage,
} from '@modules/Finance/types/finance'

interface Props {
  conversations: AdvisorConversationSummary[]
  activeConversationId: number | null
  messages: Array<{ id: number; role: string; content: string; created_at: string }>
  aiConfigured: boolean
}

function getCsrfToken(): string {
  return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
}

export default function AdvisorIndex({
  conversations: initialConversations,
  activeConversationId,
  messages: initialMessages,
  aiConfigured,
}: Props) {
  const { t } = useTranslation()
  const [conversations, setConversations] = useState(initialConversations)
  const [conversationId, setConversationId] = useState<number | null>(activeConversationId)
  const [messages, setMessages] = useState<AdvisorMessage[]>(
    initialMessages.map((m) => ({
      id: m.id,
      role: m.role as 'user' | 'assistant',
      content: m.content,
      created_at: m.created_at,
    }))
  )
  const [isProcessing, setIsProcessing] = useState(false)
  const [sidebarOpen, setSidebarOpen] = useState(true)

  const handleNewConversation = useCallback(() => {
    setConversationId(null)
    setMessages([])
    // On mobile, close sidebar when starting new conversation
    if (window.innerWidth < 768) {
      setSidebarOpen(false)
    }
  }, [])

  const handleDeleteConversation = useCallback(
    (id: number) => {
      setConversations((prev) => prev.filter((c) => c.id !== id))
      if (conversationId === id) {
        setConversationId(null)
        setMessages([])
      }
    },
    [conversationId]
  )

  const handleSend = useCallback(
    async (text: string) => {
      // Optimistically add user message
      const tempUserId = Date.now()
      const userMsg: AdvisorMessage = {
        id: tempUserId,
        role: 'user',
        content: text,
        created_at: new Date().toISOString(),
      }

      const tempAssistantId = tempUserId + 1
      const processingMsg: AdvisorMessage = {
        id: tempAssistantId,
        role: 'assistant',
        content: '',
        created_at: new Date().toISOString(),
        isProcessing: true,
      }

      setMessages((prev) => [...prev, userMsg, processingMsg])
      setIsProcessing(true)

      try {
        const response = await fetch(route('dashboard.finance.advisor.send'), {
          method: 'POST',
          body: JSON.stringify({
            message: text,
            conversation_id: conversationId,
          }),
          headers: {
            'X-CSRF-TOKEN': getCsrfToken(),
            Accept: 'application/json',
            'Content-Type': 'application/json',
          },
        })

        const data = await response.json().catch(() => ({
          success: false,
          error: `Server error: ${response.status}`,
        }))

        if (data.success) {
          // Update conversation ID if new
          if (!conversationId) {
            setConversationId(data.conversation_id)

            // Add to conversation list
            setConversations((prev) => [
              {
                id: data.conversation_id,
                title: data.conversation_title || text.slice(0, 60),
                updated_at: new Date().toISOString(),
                preview: data.message.content?.slice(0, 80) || null,
              },
              ...prev,
            ])
          } else {
            // Update preview in conversation list
            setConversations((prev) =>
              prev.map((c) =>
                c.id === conversationId
                  ? {
                      ...c,
                      updated_at: new Date().toISOString(),
                      preview: data.message.content?.slice(0, 80) || c.preview,
                    }
                  : c
              )
            )
          }

          // Replace processing message with actual response
          setMessages((prev) =>
            prev.map((m) =>
              m.id === tempAssistantId
                ? {
                    id: data.message.id,
                    role: 'assistant',
                    content: data.message.content,
                    created_at: data.message.created_at,
                  }
                : m
            )
          )
        } else {
          setMessages((prev) =>
            prev.map((m) =>
              m.id === tempAssistantId
                ? {
                    ...m,
                    isProcessing: false,
                    error: data.error || t('page.advisor.error'),
                  }
                : m
            )
          )
        }
      } catch {
        setMessages((prev) =>
          prev.map((m) =>
            m.id === tempAssistantId
              ? { ...m, isProcessing: false, error: t('page.advisor.error') }
              : m
          )
        )
      } finally {
        setIsProcessing(false)
      }
    },
    [conversationId, t]
  )

  return (
    <AuthenticatedLayout title={t('page.advisor.title')}>
      <Main fixed>
        <div className="flex h-[calc(100vh-4rem-3rem)]">
          {/* Conversation sidebar */}
          <div
            className={`${
              sidebarOpen ? 'w-72 border-r' : 'w-0 overflow-hidden'
            } transition-all duration-200 hidden md:block shrink-0`}
          >
            <AdvisorConversationList
              conversations={conversations}
              activeId={conversationId}
              onNewConversation={handleNewConversation}
              onDeleteConversation={handleDeleteConversation}
            />
          </div>

          {/* Chat area */}
          <div className="flex-1 flex flex-col min-w-0">
            {/* Header */}
            <div className="flex items-center justify-between px-3 sm:px-4 py-2.5 sm:py-3 border-b">
              <div className="flex items-center gap-2">
                <Button
                  variant="ghost"
                  size="icon"
                  className="h-8 w-8 hidden md:flex"
                  onClick={() => setSidebarOpen(!sidebarOpen)}
                >
                  {sidebarOpen ? (
                    <PanelLeftClose className="h-4 w-4" />
                  ) : (
                    <PanelLeft className="h-4 w-4" />
                  )}
                </Button>
                <Bot className="h-5 w-5 text-primary" />
                <h1 className="text-base sm:text-lg font-semibold">
                  {t('page.advisor.heading')}
                </h1>
              </div>

              {/* Mobile: conversation list toggle */}
              <div className="md:hidden flex items-center gap-2">
                <Button
                  variant="ghost"
                  size="sm"
                  onClick={() => {
                    handleNewConversation()
                  }}
                >
                  {t('page.advisor.new_conversation')}
                </Button>
              </div>
            </div>

            {!aiConfigured ? (
              <div className="flex-1 flex items-center justify-center p-4">
                <Alert>
                  <Settings className="h-4 w-4" />
                  <AlertDescription>
                    {t('page.advisor.ai_not_configured')}
                  </AlertDescription>
                </Alert>
              </div>
            ) : (
              <>
                <AdvisorChatArea messages={messages} />
                <AdvisorInputBar onSend={handleSend} isProcessing={isProcessing} />
              </>
            )}
          </div>
        </div>
      </Main>
    </AuthenticatedLayout>
  )
}
