import { useCallback, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { usePage } from '@inertiajs/react'
import { AuthenticatedLayout } from '@/layouts'
import { Main } from '@/components/layout/main'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Sheet, SheetContent, SheetHeader, SheetTitle } from '@/components/ui/sheet'
import { useToast } from '@/hooks/use-toast'
import {
  AlertCircle,
  History,
  MessageCircle,
  PanelLeft,
  PanelLeftClose,
  Plus,
  Settings,
} from 'lucide-react'
import {
  AdvisorConversationList,
  type ConversationRouteNames,
} from './components/advisor-conversation-list'
import { ChatInputBar } from './components/chat-input-bar'
import { AssistantChatArea } from './components/assistant-chat-area'
import type {
  Account,
  AdvisorConversationSummary,
  AssistantMessage,
  Category,
  ChatAttachment,
  ChatInputType,
} from '@modules/Finance/types/finance'

interface Props {
  conversations: AdvisorConversationSummary[]
  activeConversationId: number | null
  messages: AssistantMessage[]
  accounts: Account[]
  categories: Category[]
  aiConfigured: boolean
  voiceEnabled: boolean
}

interface SendResponse {
  success: boolean
  conversation_id?: number
  conversation_title?: string
  user_message?: AssistantMessage | null
  message?: AssistantMessage
  error?: string
}

const ROUTES: ConversationRouteNames = {
  show: 'dashboard.finance.assistant.index',
  destroy: 'dashboard.finance.assistant.conversations.destroy',
  rename: 'dashboard.finance.assistant.conversations.rename',
}

function getCsrfToken(): string {
  return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
}

/**
 * Parse a fetch Response as JSON; a non-JSON body (e.g. an HTML error page)
 * becomes a failed response instead of throwing.
 */
async function readJson<T extends { success: boolean; error?: string }>(response: Response): Promise<T> {
  const text = await response.text()

  try {
    return JSON.parse(text) as T
  } catch {
    return { success: false, error: `HTTP ${response.status}` } as T
  }
}

export default function AssistantIndex({
  conversations: initialConversations,
  activeConversationId,
  messages: initialMessages,
  accounts,
  categories,
  aiConfigured,
  voiceEnabled,
}: Props) {
  const { t } = useTranslation()
  const { toast } = useToast()
  const { locale } = usePage().props
  const language = locale === 'en' ? 'en' : 'vi'

  const [conversations, setConversations] = useState(initialConversations)
  const [conversationId, setConversationId] = useState<number | null>(activeConversationId)
  const [messages, setMessages] = useState<AssistantMessage[]>(initialMessages)
  const [isProcessing, setIsProcessing] = useState(false)
  const [sidebarOpen, setSidebarOpen] = useState(true)
  const [mobileHistoryOpen, setMobileHistoryOpen] = useState(false)

  const replaceMessage = useCallback((id: number, next: AssistantMessage) => {
    setMessages((prev) => prev.map((m) => (m.id === id ? next : m)))
  }, [])

  const handleNewConversation = useCallback(() => {
    setConversationId(null)
    setMessages([])
    setMobileHistoryOpen(false)
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

  const handleRenameConversation = useCallback((id: number, title: string) => {
    setConversations((prev) => prev.map((c) => (c.id === id ? { ...c, title } : c)))
  }, [])

  /**
   * Send one turn (text, voice, image, or text + image) with optimistic bubbles,
   * then swap them for the stored messages returned by the server.
   */
  const send = useCallback(
    async (
      fields: { message?: string; image?: File; audio?: Blob },
      inputType: ChatInputType,
      localAttachment?: ChatAttachment
    ) => {
      const now = new Date().toISOString()
      const tempUserId = -Date.now()
      const tempAssistantId = tempUserId - 1
      const userContent =
        fields.message ||
        (inputType === 'voice' ? t('page.assistant.voice_note') : t('page.assistant.receipt_photo'))

      setMessages((prev) => [
        ...prev,
        { id: tempUserId, role: 'user', content: userContent, created_at: now, meta: { input_type: inputType }, localAttachment },
        { id: tempAssistantId, role: 'assistant', content: '', created_at: now, isProcessing: true },
      ])
      setIsProcessing(true)

      const form = new FormData()
      if (fields.message) form.append('message', fields.message)
      if (fields.image) form.append('image', fields.image)
      if (fields.audio) {
        const type = fields.audio.type
        const extension = type.includes('mp4') ? 'mp4' : type.includes('ogg') ? 'ogg' : 'webm'
        form.append('audio', fields.audio, `recording.${extension}`)
      }
      if (conversationId) form.append('conversation_id', String(conversationId))
      form.append('language', language)

      try {
        const response = await fetch(route('dashboard.finance.assistant.send'), {
          method: 'POST',
          body: form,
          headers: { 'X-CSRF-TOKEN': getCsrfToken(), Accept: 'application/json' },
        })
        const data = await readJson<SendResponse>(response)

        // The conversation exists even when the AI call failed, so keep using it
        const newConversationId = data.conversation_id
        if (!conversationId && newConversationId) {
          setConversationId(newConversationId)
          setConversations((prev) => [
            {
              id: newConversationId,
              title: data.conversation_title || userContent.slice(0, 60),
              updated_at: now,
              preview: data.message?.content?.slice(0, 80) || null,
            },
            ...prev,
          ])
        } else if (conversationId && data.message) {
          setConversations((prev) =>
            prev.map((c) =>
              c.id === conversationId
                ? { ...c, updated_at: now, preview: data.message?.content?.slice(0, 80) || c.preview }
                : c
            )
          )
        }

        if (data.success && data.message) {
          const assistantMessage = data.message
          setMessages((prev) =>
            prev.map((m) => {
              if (m.id === tempUserId && data.user_message) {
                return { ...data.user_message, localAttachment: data.user_message.meta?.media_url ? undefined : localAttachment }
              }
              return m.id === tempAssistantId ? assistantMessage : m
            })
          )
        } else {
          setMessages((prev) =>
            prev.map((m) =>
              m.id === tempAssistantId
                ? { ...m, isProcessing: false, error: data.error || t('page.assistant.error') }
                : m
            )
          )
        }
      } catch {
        setMessages((prev) =>
          prev.map((m) =>
            m.id === tempAssistantId ? { ...m, isProcessing: false, error: t('page.assistant.error') } : m
          )
        )
      } finally {
        setIsProcessing(false)
      }
    },
    [conversationId, language, t]
  )

  const handleSendText = useCallback((text: string) => send({ message: text }, 'text'), [send])

  const handleSendVoice = useCallback(
    (blob: Blob) => send({ audio: blob }, 'voice', { type: 'audio', url: URL.createObjectURL(blob) }),
    [send]
  )

  const handleSendImage = useCallback(
    (file: File) => send({ image: file }, 'image', { type: 'image', url: URL.createObjectURL(file), file }),
    [send]
  )

  const handleSendTextImage = useCallback(
    (text: string, file: File) =>
      send({ message: text, image: file }, 'text_image', { type: 'image', url: URL.createObjectURL(file), file }),
    [send]
  )

  const handleSaveDraft = useCallback(
    async (messageId: number, index: number, data: Record<string, unknown>) => {
      try {
        const response = await fetch(
          route('dashboard.finance.assistant.drafts.save', { message: messageId, index }),
          {
            method: 'POST',
            body: JSON.stringify(data),
            headers: {
              'X-CSRF-TOKEN': getCsrfToken(),
              Accept: 'application/json',
              'Content-Type': 'application/json',
            },
          }
        )
        const result = await readJson<{ success: boolean; error?: string; message?: string | AssistantMessage }>(response)

        if (result.success && result.message && typeof result.message === 'object') {
          replaceMessage(messageId, result.message)
          return
        }

        // Laravel validation errors use `message` as a string
        const error = result.error || (typeof result.message === 'string' ? result.message : null)
        toast({ variant: 'destructive', title: t('page.assistant.save_failed'), description: error ?? undefined })
      } catch {
        toast({ variant: 'destructive', title: t('page.assistant.save_failed') })
      }
    },
    [replaceMessage, t, toast]
  )

  const handleConfirm = useCallback(
    async (token: string) => {
      if (!conversationId) return

      try {
        const response = await fetch(route('dashboard.finance.assistant.confirm'), {
          method: 'POST',
          body: JSON.stringify({ conversation_id: conversationId, token }),
          headers: {
            'X-CSRF-TOKEN': getCsrfToken(),
            Accept: 'application/json',
            'Content-Type': 'application/json',
          },
        })
        const result = await readJson<{ success: boolean; error?: string; message?: AssistantMessage }>(response)

        if (!result.success || !result.message) {
          toast({ variant: 'destructive', title: result.error || t('page.assistant.error') })
          return
        }

        const reply = result.message
        setMessages((prev) => [
          // Mark the action resolved locally; the server already did so
          ...prev.map((m) =>
            m.meta?.actions?.some((a) => a.token === token)
              ? {
                  ...m,
                  meta: {
                    ...m.meta,
                    actions: m.meta.actions.map((a) =>
                      a.token === token ? { ...a, status: 'resolved' as const, token: undefined } : a
                    ),
                  },
                }
              : m
          ),
          reply,
        ])
      } catch {
        toast({ variant: 'destructive', title: t('page.assistant.error') })
      }
    },
    [conversationId, t, toast]
  )

  const conversationList = (onSelect?: () => void) => (
    <AdvisorConversationList
      conversations={conversations}
      activeId={conversationId}
      onNewConversation={handleNewConversation}
      onDeleteConversation={handleDeleteConversation}
      onRenameConversation={handleRenameConversation}
      onSelectConversation={onSelect}
      routeNames={ROUTES}
    />
  )

  return (
    <AuthenticatedLayout title={t('page.assistant.title')}>
      <Main fixed>
        <div className="flex h-[calc(100vh-4rem-3rem)]">
          {/* Conversation sidebar */}
          <div
            className={`${
              sidebarOpen ? 'w-72 border-r' : 'w-0 overflow-hidden'
            } transition-all duration-200 hidden md:block shrink-0`}
          >
            {conversationList()}
          </div>

          {/* Chat area */}
          <div className="flex-1 flex flex-col min-w-0">
            <div className="flex items-center justify-between px-3 sm:px-4 py-2.5 sm:py-3 border-b">
              <div className="flex items-center gap-2">
                <Button
                  variant="ghost"
                  size="icon"
                  className="h-8 w-8 hidden md:flex"
                  aria-label={t('page.advisor.conversations')}
                  onClick={() => setSidebarOpen(!sidebarOpen)}
                >
                  {sidebarOpen ? <PanelLeftClose className="h-4 w-4" /> : <PanelLeft className="h-4 w-4" />}
                </Button>
                <MessageCircle className="h-5 w-5 text-primary" />
                <h1 className="text-base sm:text-lg font-semibold">{t('page.assistant.heading')}</h1>
              </div>

              <div className="flex items-center gap-1">
                <Button variant="ghost" size="sm" asChild>
                  <a href={route('dashboard.finance.smart-input-history.index')}>
                    <History className="h-4 w-4 sm:mr-1" />
                    <span className="hidden sm:inline">{t('page.assistant.history')}</span>
                  </a>
                </Button>
                <div className="md:hidden flex items-center gap-1">
                  <Button
                    variant="ghost"
                    size="icon"
                    className="h-8 w-8"
                    aria-label={t('page.advisor.conversations')}
                    onClick={() => setMobileHistoryOpen(true)}
                  >
                    <PanelLeft className="h-4 w-4" />
                  </Button>
                  <Button
                    variant="ghost"
                    size="icon"
                    className="h-8 w-8"
                    aria-label={t('page.advisor.new_conversation')}
                    onClick={handleNewConversation}
                  >
                    <Plus className="h-4 w-4" />
                  </Button>
                </div>
              </div>
            </div>

            <Sheet open={mobileHistoryOpen} onOpenChange={setMobileHistoryOpen}>
              <SheetContent side="left" className="w-80 p-0 md:hidden">
                <SheetHeader className="border-b px-4 py-3 text-left">
                  <SheetTitle>{t('page.advisor.conversations')}</SheetTitle>
                </SheetHeader>
                <div className="h-[calc(100%-57px)]">{conversationList(() => setMobileHistoryOpen(false))}</div>
              </SheetContent>
            </Sheet>

            {!aiConfigured ? (
              <div className="flex-1 flex items-center justify-center p-4">
                <Alert>
                  <Settings className="h-4 w-4" />
                  <AlertDescription>{t('page.assistant.ai_not_configured')}</AlertDescription>
                </Alert>
              </div>
            ) : accounts.length === 0 ? (
              <div className="flex-1 flex items-center justify-center p-4">
                <Alert>
                  <AlertCircle className="h-4 w-4" />
                  <AlertDescription>{t('page.assistant.create_account_first')}</AlertDescription>
                </Alert>
              </div>
            ) : (
              <>
                <AssistantChatArea
                  messages={messages}
                  accounts={accounts}
                  categories={categories}
                  onSaveDraft={handleSaveDraft}
                  onConfirm={handleConfirm}
                />
                <ChatInputBar
                  onSendText={handleSendText}
                  onSendVoice={handleSendVoice}
                  onSendImage={handleSendImage}
                  onSendTextImage={handleSendTextImage}
                  isProcessing={isProcessing}
                  voiceEnabled={voiceEnabled}
                />
              </>
            )}
          </div>
        </div>
      </Main>
    </AuthenticatedLayout>
  )
}
