import { useState, useCallback, useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { AuthenticatedLayout } from '@/layouts'
import { Main } from '@/components/layout/main'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Sparkles, AlertCircle, History, Settings } from 'lucide-react'
import { ChatMessageList } from './components/chat-message-list'
import { ChatInputBar } from './components/chat-input-bar'
import type {
  Account,
  Category,
  ChatMessage,
  ChatInputType,
  ParsedTransaction,
} from '@modules/Finance/types/finance'

interface HistoryItem {
  id: number
  input_type: ChatInputType
  raw_text?: string
  parsed_result?: Record<string, unknown>
  confidence?: number
  transaction_saved: boolean
  created_at: string
  media_url?: string | null
}

interface Props {
  accounts: Account[]
  categories: Category[]
  recentHistory?: HistoryItem[]
  aiConfigured: boolean
}

function generateId(): string {
  return `${Date.now()}-${Math.random().toString(36).substring(2, 9)}`
}

function getCsrfToken(): string {
  return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
}

function buildHistoryMessages(history: HistoryItem[], t: (key: string) => string): ChatMessage[] {
  const msgs: ChatMessage[] = []

  for (const h of history) {
    const ts = new Date(h.created_at)
    const parsed = h.parsed_result

    // User message
    const userContent = h.input_type === 'voice'
      ? t('page.smart_input.chat_voice_sent')
      : h.input_type === 'image'
        ? t('page.smart_input.chat_image_sent')
        : h.raw_text || ''

    const attachment = h.media_url
      ? { type: (h.input_type === 'voice' ? 'audio' : 'image') as 'image' | 'audio', url: h.media_url }
      : undefined

    msgs.push({
      id: `hist-user-${h.id}`,
      role: 'user',
      content: userContent,
      inputType: h.input_type,
      attachment,
      timestamp: ts,
    })

    // Assistant message with parsed transaction
    if (parsed) {
      msgs.push({
        id: `hist-assist-${h.id}`,
        role: 'assistant',
        content: t('page.smart_input.chat_parsed'),
        inputType: h.input_type,
        historyId: h.id,
        transactionSaved: h.transaction_saved,
        parsedTransaction: {
          type: (parsed.type as 'income' | 'expense' | 'transfer') || 'expense',
          amount: (parsed.amount as number) || 0,
          description: (parsed.description as string) || '',
          transaction_date: (parsed.transaction_date as string) || '',
          confidence: (parsed.confidence as number) || 0,
          raw_text: (parsed.raw_text as string) || undefined,
        },
        timestamp: ts,
      })
    }
  }

  return msgs
}

export default function SmartInputIndex({ accounts, categories, recentHistory, aiConfigured }: Props) {
  const { t } = useTranslation()

  const initialMessages = useMemo<ChatMessage[]>(() => {
    const welcome: ChatMessage = {
      id: 'welcome',
      role: 'system',
      content: t('page.smart_input.chat_welcome'),
      inputType: 'text',
      timestamp: new Date(),
    }

    if (!recentHistory?.length) {
      return [welcome]
    }

    return [welcome, ...buildHistoryMessages(recentHistory, t)]
  }, []) // eslint-disable-line react-hooks/exhaustive-deps

  const [messages, setMessages] = useState<ChatMessage[]>(initialMessages)
  const [isProcessing, setIsProcessing] = useState(false)

  const addMessage = useCallback((msg: ChatMessage) => {
    setMessages((prev) => [...prev, msg])
  }, [])

  const updateMessage = useCallback((id: string, updates: Partial<ChatMessage>) => {
    setMessages((prev) =>
      prev.map((msg) => (msg.id === id ? { ...msg, ...updates } : msg))
    )
  }, [])

  const handleParseResult = useCallback(
    (assistantId: string, data: { success: boolean; data?: ParsedTransaction; error?: string; history_id?: number }) => {
      if (data.success && data.data) {
        updateMessage(assistantId, {
          isProcessing: false,
          parsedTransaction: data.data,
          content: t('page.smart_input.chat_parsed'),
          historyId: data.history_id,
        })
      } else {
        updateMessage(assistantId, {
          isProcessing: false,
          error: data.error || t('page.smart_input.chat_error'),
        })
      }
    },
    [t, updateMessage]
  )

  const handleSendText = useCallback(
    async (text: string) => {
      const userMsg: ChatMessage = {
        id: generateId(),
        role: 'user',
        content: text,
        inputType: 'text',
        timestamp: new Date(),
      }
      addMessage(userMsg)

      const assistantId = generateId()
      addMessage({
        id: assistantId,
        role: 'assistant',
        content: '',
        inputType: 'text',
        isProcessing: true,
        timestamp: new Date(),
      })
      setIsProcessing(true)

      try {
        const response = await fetch(route('dashboard.finance.smart-input.parse-text'), {
          method: 'POST',
          body: JSON.stringify({ text, language: 'vi' }),
          headers: {
            'X-CSRF-TOKEN': getCsrfToken(),
            Accept: 'application/json',
            'Content-Type': 'application/json',
          },
        })

        const data = await response.json().catch(() => ({ success: false, error: `Server error: ${response.status}` }))
        handleParseResult(assistantId, data)
      } catch {
        updateMessage(assistantId, {
          isProcessing: false,
          error: t('page.smart_input.chat_error'),
        })
      } finally {
        setIsProcessing(false)
      }
    },
    [addMessage, handleParseResult, t, updateMessage]
  )

  const handleSendVoice = useCallback(
    async (blob: Blob) => {
      const userMsg: ChatMessage = {
        id: generateId(),
        role: 'user',
        content: t('page.smart_input.chat_voice_sent'),
        inputType: 'voice',
        timestamp: new Date(),
      }
      addMessage(userMsg)

      const assistantId = generateId()
      addMessage({
        id: assistantId,
        role: 'assistant',
        content: '',
        inputType: 'voice',
        isProcessing: true,
        timestamp: new Date(),
      })
      setIsProcessing(true)

      try {
        const extension = blob.type.includes('mp4') ? 'mp4' : blob.type.includes('ogg') ? 'ogg' : 'webm'
        const formData = new FormData()
        formData.append('audio', blob, `recording.${extension}`)
        formData.append('language', 'vi')

        const response = await fetch(route('dashboard.finance.smart-input.parse-voice'), {
          method: 'POST',
          body: formData,
          headers: {
            'X-CSRF-TOKEN': getCsrfToken(),
            Accept: 'application/json',
          },
        })

        const data = await response.json().catch(() => ({ success: false, error: `Server error: ${response.status}` }))
        handleParseResult(assistantId, data)
      } catch {
        updateMessage(assistantId, {
          isProcessing: false,
          error: t('page.smart_input.chat_error'),
        })
      } finally {
        setIsProcessing(false)
      }
    },
    [addMessage, handleParseResult, t, updateMessage]
  )

  const handleSendImage = useCallback(
    async (file: File) => {
      const previewUrl = URL.createObjectURL(file)
      const userMsg: ChatMessage = {
        id: generateId(),
        role: 'user',
        content: t('page.smart_input.chat_image_sent'),
        inputType: 'image',
        attachment: { type: 'image', url: previewUrl, file },
        timestamp: new Date(),
      }
      addMessage(userMsg)

      const assistantId = generateId()
      addMessage({
        id: assistantId,
        role: 'assistant',
        content: '',
        inputType: 'image',
        isProcessing: true,
        timestamp: new Date(),
      })
      setIsProcessing(true)

      try {
        const formData = new FormData()
        formData.append('image', file)
        formData.append('language', 'vi')

        const response = await fetch(route('dashboard.finance.smart-input.parse-receipt'), {
          method: 'POST',
          body: formData,
          headers: {
            'X-CSRF-TOKEN': getCsrfToken(),
            Accept: 'application/json',
          },
        })

        const data = await response.json().catch(() => ({ success: false, error: `Server error: ${response.status}` }))
        handleParseResult(assistantId, data)
      } catch {
        updateMessage(assistantId, {
          isProcessing: false,
          error: t('page.smart_input.chat_error'),
        })
      } finally {
        setIsProcessing(false)
      }
    },
    [addMessage, handleParseResult, t, updateMessage]
  )

  const handleSendTextImage = useCallback(
    async (text: string, file: File) => {
      const previewUrl = URL.createObjectURL(file)
      const userMsg: ChatMessage = {
        id: generateId(),
        role: 'user',
        content: text,
        inputType: 'text_image',
        attachment: { type: 'image', url: previewUrl, file },
        timestamp: new Date(),
      }
      addMessage(userMsg)

      const assistantId = generateId()
      addMessage({
        id: assistantId,
        role: 'assistant',
        content: '',
        inputType: 'text_image',
        isProcessing: true,
        timestamp: new Date(),
      })
      setIsProcessing(true)

      try {
        const formData = new FormData()
        formData.append('text', text)
        formData.append('image', file)
        formData.append('language', 'vi')

        const response = await fetch(route('dashboard.finance.smart-input.parse-text-image'), {
          method: 'POST',
          body: formData,
          headers: {
            'X-CSRF-TOKEN': getCsrfToken(),
            Accept: 'application/json',
          },
        })

        const data = await response.json().catch(() => ({ success: false, error: `Server error: ${response.status}` }))
        handleParseResult(assistantId, data)
      } catch {
        updateMessage(assistantId, {
          isProcessing: false,
          error: t('page.smart_input.chat_error'),
        })
      } finally {
        setIsProcessing(false)
      }
    },
    [addMessage, handleParseResult, t, updateMessage]
  )

  const handleSaveTransaction = useCallback(
    async (messageId: string, data: Record<string, unknown>) => {
      try {
        // Find the message to get its historyId
        const msg = messages.find((m) => m.id === messageId)
        const payload = { ...data, history_id: msg?.historyId }

        const response = await fetch(route('dashboard.finance.smart-input.store'), {
          method: 'POST',
          body: JSON.stringify(payload),
          headers: {
            'X-CSRF-TOKEN': getCsrfToken(),
            Accept: 'application/json',
            'Content-Type': 'application/json',
          },
        })

        const result = await response.json().catch(() => ({ success: false }))

        if (result.success) {
          updateMessage(messageId, { transactionSaved: true })
        }
      } catch {
        console.error('Failed to save transaction')
      }
    },
    [messages, updateMessage]
  )

  return (
    <AuthenticatedLayout title={t('page.smart_input.title')}>
      <Main fixed>
        <div className="flex flex-col h-[calc(100vh-4rem-3rem)] sm:h-[calc(100vh-4rem-3rem)]">
          {/* Compact header */}
          <div className="flex items-center justify-between px-3 sm:px-4 py-2.5 sm:py-3 border-b">
            <div className="flex items-center gap-2">
              <Sparkles className="h-5 w-5 text-primary" />
              <h1 className="text-base sm:text-lg font-semibold">{t('page.smart_input.heading')}</h1>
            </div>
            <Button variant="ghost" size="sm" asChild>
              <a href={route('dashboard.finance.smart-input-history.index')}>
                <History className="h-4 w-4 mr-1" />
                <span className="hidden sm:inline">{t('page.smart_input.history')}</span>
              </a>
            </Button>
          </div>

          {!aiConfigured ? (
            <div className="flex-1 flex items-center justify-center p-4">
              <Alert>
                <Settings className="h-4 w-4" />
                <AlertDescription>
                  {t('page.smart_input.ai_not_configured')}
                </AlertDescription>
              </Alert>
            </div>
          ) : accounts.length === 0 ? (
            <div className="flex-1 flex items-center justify-center p-4">
              <Alert>
                <AlertCircle className="h-4 w-4" />
                <AlertDescription>
                  {t('page.smart_input.create_account_first')}
                </AlertDescription>
              </Alert>
            </div>
          ) : (
            <>
              <ChatMessageList
                messages={messages}
                accounts={accounts}
                categories={categories}
                onSaveTransaction={handleSaveTransaction}
              />
              <ChatInputBar
                onSendText={handleSendText}
                onSendVoice={handleSendVoice}
                onSendImage={handleSendImage}
                onSendTextImage={handleSendTextImage}
                isProcessing={isProcessing}
              />
            </>
          )}
        </div>
      </Main>
    </AuthenticatedLayout>
  )
}
