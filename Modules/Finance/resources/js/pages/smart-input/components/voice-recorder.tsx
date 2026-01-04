import { useState, useRef, useCallback, useEffect } from 'react'
import { Button } from '@/components/ui/button'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Mic, MicOff, Loader2, AlertCircle } from 'lucide-react'
import { cn } from '@/lib/utils'

interface VoiceRecorderProps {
  onRecordingComplete: (audioBlob: Blob) => void
  isProcessing?: boolean
  disabled?: boolean
}

export function VoiceRecorder({
  onRecordingComplete,
  isProcessing = false,
  disabled = false,
}: VoiceRecorderProps) {
  const [isRecording, setIsRecording] = useState(false)
  const [recordingTime, setRecordingTime] = useState(0)
  const [isSupported, setIsSupported] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const mediaRecorderRef = useRef<MediaRecorder | null>(null)
  const chunksRef = useRef<Blob[]>([])
  const timerRef = useRef<NodeJS.Timeout | null>(null)

  useEffect(() => {
    // Check if mediaDevices API is available (requires HTTPS or localhost)
    if (!navigator.mediaDevices?.getUserMedia) {
      setIsSupported(false)
      setError('Voice recording requires HTTPS. Please access via https:// or localhost.')
    }
  }, [])

  const startRecording = useCallback(async () => {
    if (!navigator.mediaDevices?.getUserMedia) {
      setError('Voice recording is not supported in this browser or requires HTTPS.')
      return
    }

    setError(null)

    try {
      const stream = await navigator.mediaDevices.getUserMedia({ audio: true })

      // Check for supported mime types
      const mimeType = MediaRecorder.isTypeSupported('audio/webm;codecs=opus')
        ? 'audio/webm;codecs=opus'
        : MediaRecorder.isTypeSupported('audio/webm')
          ? 'audio/webm'
          : 'audio/mp4'

      const mediaRecorder = new MediaRecorder(stream, { mimeType })

      chunksRef.current = []

      mediaRecorder.ondataavailable = (event) => {
        if (event.data.size > 0) {
          chunksRef.current.push(event.data)
        }
      }

      mediaRecorder.onstop = () => {
        const audioBlob = new Blob(chunksRef.current, { type: 'audio/webm' })
        onRecordingComplete(audioBlob)
        stream.getTracks().forEach((track) => track.stop())
      }

      mediaRecorderRef.current = mediaRecorder
      mediaRecorder.start(100)
      setIsRecording(true)
      setRecordingTime(0)

      timerRef.current = setInterval(() => {
        setRecordingTime((prev) => prev + 1)
      }, 1000)
    } catch (err) {
      console.error('Failed to start recording:', err)
      if (err instanceof DOMException && err.name === 'NotAllowedError') {
        setError('Microphone access denied. Please allow microphone permissions.')
      } else if (err instanceof DOMException && err.name === 'NotFoundError') {
        setError('No microphone found. Please connect a microphone.')
      } else {
        setError('Could not access microphone. Please check permissions.')
      }
    }
  }, [onRecordingComplete])

  const stopRecording = useCallback(() => {
    if (mediaRecorderRef.current && isRecording) {
      mediaRecorderRef.current.stop()
      setIsRecording(false)
      if (timerRef.current) {
        clearInterval(timerRef.current)
        timerRef.current = null
      }
    }
  }, [isRecording])

  const formatTime = (seconds: number) => {
    const mins = Math.floor(seconds / 60)
    const secs = seconds % 60
    return `${mins}:${secs.toString().padStart(2, '0')}`
  }

  const handleMouseDown = () => {
    if (!disabled && !isProcessing) {
      startRecording()
    }
  }

  const handleMouseUp = () => {
    if (isRecording) {
      stopRecording()
    }
  }

  return (
    <div className="flex flex-col items-center gap-4">
      {error && (
        <Alert variant="destructive" className="mb-2">
          <AlertCircle className="h-4 w-4" />
          <AlertDescription>{error}</AlertDescription>
        </Alert>
      )}

      <Button
        type="button"
        size="lg"
        variant={isRecording ? 'destructive' : 'default'}
        className={cn(
          'h-24 w-24 rounded-full transition-all',
          isRecording && 'animate-pulse scale-110',
          (disabled || !isSupported) && 'opacity-50 cursor-not-allowed'
        )}
        onMouseDown={handleMouseDown}
        onMouseUp={handleMouseUp}
        onMouseLeave={handleMouseUp}
        onTouchStart={handleMouseDown}
        onTouchEnd={handleMouseUp}
        disabled={disabled || isProcessing || !isSupported}
      >
        {isProcessing ? (
          <Loader2 className="h-10 w-10 animate-spin" />
        ) : isRecording ? (
          <MicOff className="h-10 w-10" />
        ) : (
          <Mic className="h-10 w-10" />
        )}
      </Button>

      <div className="text-center">
        {!isSupported ? (
          <p className="text-sm text-muted-foreground">
            Voice recording unavailable
          </p>
        ) : isRecording ? (
          <div className="space-y-1">
            <p className="text-lg font-medium text-destructive">
              Recording... {formatTime(recordingTime)}
            </p>
            <p className="text-sm text-muted-foreground">Release to stop</p>
          </div>
        ) : isProcessing ? (
          <p className="text-sm text-muted-foreground">Processing audio...</p>
        ) : (
          <div className="space-y-1">
            <p className="text-sm font-medium">Hold to speak</p>
            <p className="text-xs text-muted-foreground">
              "Cafe 50k hôm nay"
            </p>
          </div>
        )}
      </div>
    </div>
  )
}
