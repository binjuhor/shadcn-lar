import { useState, useRef, useEffect } from 'react'
import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { Textarea } from '@/components/ui/textarea'
import { Send } from 'lucide-react'

interface AdvisorInputBarProps {
  onSend: (text: string) => void
  isProcessing: boolean
}

export function AdvisorInputBar({ onSend, isProcessing }: AdvisorInputBarProps) {
  const { t } = useTranslation()
  const [text, setText] = useState('')
  const textareaRef = useRef<HTMLTextAreaElement>(null)

  useEffect(() => {
    const el = textareaRef.current
    if (!el) return

    el.style.height = 'auto'
    el.style.height = `${Math.min(el.scrollHeight, 160)}px`
  }, [text])

  const handleSend = () => {
    const trimmed = text.trim()
    if (!trimmed || isProcessing) return

    onSend(trimmed)
    setText('')

    if (textareaRef.current) {
      textareaRef.current.style.height = 'auto'
    }
  }

  const handleKeyDown = (e: React.KeyboardEvent) => {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault()
      handleSend()
    }
  }

  return (
    <div className="bg-background px-3 sm:px-4 pb-3 pt-2 sm:pb-4">
      <div className="max-w-2xl mx-auto">
        <div className="relative rounded-2xl border bg-muted/30 shadow-sm focus-within:ring-2 focus-within:ring-ring focus-within:ring-offset-1 transition-shadow">
          <Textarea
            ref={textareaRef}
            value={text}
            onChange={(e) => setText(e.target.value)}
            onKeyDown={handleKeyDown}
            placeholder={t('page.advisor.placeholder')}
            rows={1}
            className="min-h-[48px] sm:min-h-[56px] max-h-[160px] resize-none overflow-y-auto border-0 bg-transparent text-base shadow-none focus-visible:ring-0 px-3 sm:px-4 pt-3 sm:pt-4 pb-0"
            disabled={isProcessing}
          />

          <div className="flex items-center justify-end px-2 sm:px-3 py-2">
            <Button
              type="button"
              size="icon"
              className="h-8 w-8 sm:h-9 sm:w-9 rounded-full"
              onClick={handleSend}
              disabled={!text.trim() || isProcessing}
            >
              <Send className="h-4 w-4 sm:h-5 sm:w-5" />
            </Button>
          </div>
        </div>
      </div>
    </div>
  )
}
