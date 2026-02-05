import { useRef, useState, useEffect, useCallback } from 'react'
import { Play, Pause } from 'lucide-react'

interface ChatAudioAttachmentProps {
  url: string
}

export function ChatAudioAttachment({ url }: ChatAudioAttachmentProps) {
  const audioRef = useRef<HTMLAudioElement>(null)
  const [isPlaying, setIsPlaying] = useState(false)
  const [currentTime, setCurrentTime] = useState(0)
  const [duration, setDuration] = useState(0)

  const formatTime = useCallback((seconds: number): string => {
    const mins = Math.floor(seconds / 60)
    const secs = Math.floor(seconds % 60)

    return `${mins}:${secs.toString().padStart(2, '0')}`
  }, [])

  useEffect(() => {
    const audio = audioRef.current

    if (!audio) {
      return
    }

    const onLoadedMetadata = () => setDuration(audio.duration)
    const onTimeUpdate = () => setCurrentTime(audio.currentTime)
    const onEnded = () => setIsPlaying(false)

    audio.addEventListener('loadedmetadata', onLoadedMetadata)
    audio.addEventListener('timeupdate', onTimeUpdate)
    audio.addEventListener('ended', onEnded)

    return () => {
      audio.removeEventListener('loadedmetadata', onLoadedMetadata)
      audio.removeEventListener('timeupdate', onTimeUpdate)
      audio.removeEventListener('ended', onEnded)
    }
  }, [])

  const togglePlay = () => {
    const audio = audioRef.current

    if (!audio) {
      return
    }

    if (isPlaying) {
      audio.pause()
    } else {
      audio.play()
    }

    setIsPlaying(!isPlaying)
  }

  const handleSeek = (e: React.MouseEvent<HTMLDivElement>) => {
    const audio = audioRef.current

    if (!audio || !duration) {
      return
    }

    const rect = e.currentTarget.getBoundingClientRect()
    const ratio = (e.clientX - rect.left) / rect.width

    audio.currentTime = ratio * duration
  }

  const progress = duration > 0 ? (currentTime / duration) * 100 : 0

  return (
    <div className="flex items-center gap-2 min-w-[180px]">
      <audio ref={audioRef} src={url} preload="metadata" />

      <button
        type="button"
        onClick={togglePlay}
        className="shrink-0 w-8 h-8 rounded-full bg-primary-foreground/20 flex items-center justify-center hover:bg-primary-foreground/30 transition-colors"
      >
        {isPlaying ? (
          <Pause className="w-3.5 h-3.5 fill-current" />
        ) : (
          <Play className="w-3.5 h-3.5 fill-current ml-0.5" />
        )}
      </button>

      <div className="flex-1 flex flex-col gap-1">
        <div
          className="h-1.5 rounded-full bg-primary-foreground/20 cursor-pointer"
          onClick={handleSeek}
        >
          <div
            className="h-full rounded-full bg-primary-foreground/70 transition-[width] duration-100"
            style={{ width: `${progress}%` }}
          />
        </div>

        <span className="text-[10px] leading-none opacity-70">
          {formatTime(currentTime)} / {formatTime(duration)}
        </span>
      </div>
    </div>
  )
}
