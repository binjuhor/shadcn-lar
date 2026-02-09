import { useState, useRef, useCallback, useEffect } from 'react'
import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { Textarea } from '@/components/ui/textarea'
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from '@/components/ui/popover'
import {
  Paperclip,
  Send,
  Mic,
  Square,
  Camera,
  Upload,
  X,
} from 'lucide-react'

interface ChatInputBarProps {
  onSendText: (text: string) => void
  onSendVoice: (blob: Blob) => void
  onSendImage: (file: File) => void
  onSendTextImage: (text: string, file: File) => void
  isProcessing: boolean
}

export function ChatInputBar({
  onSendText,
  onSendVoice,
  onSendImage,
  onSendTextImage,
  isProcessing,
}: ChatInputBarProps) {
  const { t } = useTranslation()
  const [text, setText] = useState('')
  const [imageFile, setImageFile] = useState<File | null>(null)
  const [imagePreview, setImagePreview] = useState<string | null>(null)
  const [isRecording, setIsRecording] = useState(false)
  const [attachOpen, setAttachOpen] = useState(false)
  const mediaRecorderRef = useRef<MediaRecorder | null>(null)
  const chunksRef = useRef<Blob[]>([])
  const textareaRef = useRef<HTMLTextAreaElement>(null)
  const fileInputRef = useRef<HTMLInputElement>(null)

  const [isDragging, setIsDragging] = useState(false)
  const dragCounterRef = useRef(0)

  const hasContent = text.trim().length > 0 || imageFile !== null

  // Auto-resize textarea as content grows
  useEffect(() => {
    const el = textareaRef.current
    if (!el) return

    el.style.height = 'auto'
    el.style.height = `${Math.min(el.scrollHeight, 160)}px`
  }, [text])

  const handleSend = () => {
    if (isProcessing) return

    const trimmedText = text.trim()

    if (trimmedText && imageFile) {
      onSendTextImage(trimmedText, imageFile)
    } else if (imageFile) {
      onSendImage(imageFile)
    } else if (trimmedText) {
      onSendText(trimmedText)
    }

    setText('')
    clearImage()

    // Reset textarea height after send
    if (textareaRef.current) {
      textareaRef.current.style.height = 'auto'
    }
  }

  const handleKeyDown = (e: React.KeyboardEvent) => {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault()
      if (hasContent && !isProcessing) {
        handleSend()
      }
    }
  }

  const clearImage = () => {
    if (imagePreview) {
      URL.revokeObjectURL(imagePreview)
    }
    setImageFile(null)
    setImagePreview(null)
  }

  const handleFileSelect = useCallback((file: File) => {
    if (!file.type.startsWith('image/')) {
      return
    }

    setImageFile(file)
    setImagePreview(URL.createObjectURL(file))
    setAttachOpen(false)
  }, [])

  const handleDragEnter = useCallback((e: React.DragEvent) => {
    e.preventDefault()
    e.stopPropagation()
    dragCounterRef.current++
    if (e.dataTransfer.types.includes('Files')) {
      setIsDragging(true)
    }
  }, [])

  const handleDragLeave = useCallback((e: React.DragEvent) => {
    e.preventDefault()
    e.stopPropagation()
    dragCounterRef.current--
    if (dragCounterRef.current === 0) {
      setIsDragging(false)
    }
  }, [])

  const handleDragOver = useCallback((e: React.DragEvent) => {
    e.preventDefault()
    e.stopPropagation()
  }, [])

  const handleDrop = useCallback((e: React.DragEvent) => {
    e.preventDefault()
    e.stopPropagation()
    setIsDragging(false)
    dragCounterRef.current = 0

    const file = e.dataTransfer.files?.[0]
    if (file && file.type.startsWith('image/')) {
      handleFileSelect(file)
    }
  }, [handleFileSelect])

  const handleUploadClick = () => {
    fileInputRef.current?.click()
  }

  const handleFileInputChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0]
    if (file) {
      handleFileSelect(file)
    }
    e.target.value = ''
  }

  const handleCameraCapture = () => {
    const input = document.createElement('input')
    input.type = 'file'
    input.accept = 'image/*'
    input.capture = 'environment'
    input.onchange = (e) => {
      const file = (e.target as HTMLInputElement).files?.[0]
      if (file) {
        handleFileSelect(file)
      }
    }
    input.click()
    setAttachOpen(false)
  }

  const toggleRecording = async () => {
    if (isRecording) {
      mediaRecorderRef.current?.stop()
      setIsRecording(false)
      return
    }

    try {
      const stream = await navigator.mediaDevices.getUserMedia({ audio: true })
      const mimeType = MediaRecorder.isTypeSupported('audio/webm;codecs=opus')
        ? 'audio/webm;codecs=opus'
        : MediaRecorder.isTypeSupported('audio/mp4')
          ? 'audio/mp4'
          : 'audio/webm'

      const recorder = new MediaRecorder(stream, { mimeType })
      chunksRef.current = []

      recorder.ondataavailable = (e) => {
        if (e.data.size > 0) {
          chunksRef.current.push(e.data)
        }
      }

      recorder.onstop = () => {
        const blob = new Blob(chunksRef.current, { type: mimeType })
        stream.getTracks().forEach((track) => track.stop())
        onSendVoice(blob)
      }

      recorder.start()
      mediaRecorderRef.current = recorder
      setIsRecording(true)
    } catch {
      console.error('Failed to start recording')
    }
  }

  return (
    <div
      className="bg-background px-3 sm:px-4 pb-3 pt-2 sm:pb-4"
      onDragEnter={handleDragEnter}
      onDragLeave={handleDragLeave}
      onDragOver={handleDragOver}
      onDrop={handleDrop}
    >
      <div className="max-w-2xl mx-auto">
        <div className={`relative rounded-2xl border shadow-sm focus-within:ring-2 focus-within:ring-ring focus-within:ring-offset-1 transition-all ${isDragging ? 'border-primary border-dashed bg-primary/5 ring-2 ring-primary/20' : 'bg-muted/30'}`}>
          {/* Drop zone overlay */}
          {isDragging && (
            <div className="absolute inset-0 z-10 flex items-center justify-center rounded-2xl pointer-events-none">
              <div className="flex items-center gap-2 text-primary font-medium">
                <Upload className="h-5 w-5" />
                <span>{t('page.smart_input.drop_image')}</span>
              </div>
            </div>
          )}

          {/* Image preview strip */}
          {imagePreview && (
            <div className="px-3 pt-3 sm:px-4 sm:pt-4">
              <div className="flex items-center gap-2">
                <div className="relative">
                  <img
                    src={imagePreview}
                    alt="Attachment"
                    className="h-16 w-16 sm:h-20 sm:w-20 rounded-lg object-cover border"
                  />
                  <button
                    type="button"
                    onClick={clearImage}
                    className="absolute -top-1.5 -right-1.5 rounded-full bg-destructive text-destructive-foreground p-0.5"
                    disabled={isProcessing}
                  >
                    <X className="h-3 w-3" />
                  </button>
                </div>
                <span className="text-xs text-muted-foreground">
                  {t('page.smart_input.chat_image_sent')}
                </span>
              </div>
            </div>
          )}

          {/* Textarea */}
          <Textarea
            ref={textareaRef}
            value={text}
            onChange={(e) => setText(e.target.value)}
            onKeyDown={handleKeyDown}
            placeholder={t('page.smart_input.chat_placeholder')}
            rows={1}
            className="min-h-[48px] sm:min-h-[56px] max-h-[160px] resize-none overflow-y-auto border-0 bg-transparent text-base shadow-none focus-visible:ring-0 px-3 sm:px-4 pt-3 sm:pt-4 pb-0"
            disabled={isProcessing || isRecording}
          />

          {/* Action row */}
          <div className="flex items-center justify-between px-2 sm:px-3 py-2">
            <div className="flex items-center gap-1">
              {/* Attach button */}
              <Popover open={attachOpen} onOpenChange={setAttachOpen}>
                <PopoverTrigger asChild>
                  <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    className="h-8 w-8 sm:h-9 sm:w-9 rounded-full text-muted-foreground hover:text-foreground"
                    disabled={isProcessing}
                  >
                    <Paperclip className="h-4 w-4 sm:h-5 sm:w-5" />
                  </Button>
                </PopoverTrigger>
                <PopoverContent className="w-40 p-1" align="start">
                  <Button
                    variant="ghost"
                    className="w-full justify-start text-sm h-8"
                    onClick={handleUploadClick}
                  >
                    <Upload className="h-4 w-4 mr-2" />
                    {t('page.smart_input.upload')}
                  </Button>
                  <Button
                    variant="ghost"
                    className="w-full justify-start text-sm h-8"
                    onClick={handleCameraCapture}
                  >
                    <Camera className="h-4 w-4 mr-2" />
                    {t('page.smart_input.camera')}
                  </Button>
                </PopoverContent>
              </Popover>

              <input
                ref={fileInputRef}
                type="file"
                accept="image/*"
                className="hidden"
                onChange={handleFileInputChange}
              />

              {/* Mic button */}
              <Button
                type="button"
                variant={isRecording ? 'destructive' : 'ghost'}
                size="icon"
                className="h-8 w-8 sm:h-9 sm:w-9 rounded-full text-muted-foreground hover:text-foreground"
                onClick={toggleRecording}
                disabled={isProcessing}
              >
                {isRecording ? (
                  <Square className="h-3.5 w-3.5 sm:h-4 sm:w-4" />
                ) : (
                  <Mic className="h-4 w-4 sm:h-5 sm:w-5" />
                )}
              </Button>

              {isRecording && (
                <span className="text-xs text-destructive animate-pulse ml-1">
                  {t('page.smart_input.chat_recording')}
                </span>
              )}
            </div>

            {/* Send button */}
            <Button
              type="button"
              size="icon"
              className="h-8 w-8 sm:h-9 sm:w-9 rounded-full"
              onClick={handleSend}
              disabled={!hasContent || isProcessing}
            >
              <Send className="h-4 w-4 sm:h-5 sm:w-5" />
            </Button>
          </div>
        </div>
      </div>
    </div>
  )
}
