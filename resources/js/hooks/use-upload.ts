import { useCallback, useState } from "react"
import { createImageUploader, type BucketId } from "@/lib/upload"

interface UseUploadOptions {
  bucketId?: BucketId
  unique?: boolean
}

interface UseUploadReturn {
  upload: (file: File) => Promise<string | null>
  isUploading: boolean
  progress: number
  error: string | null
  reset: () => void
}

export function useUpload(options?: UseUploadOptions): UseUploadReturn {
  const [isUploading, setIsUploading] = useState(false)
  const [progress, setProgress] = useState(0)
  const [error, setError] = useState<string | null>(null)

  const reset = useCallback(() => {
    setIsUploading(false)
    setProgress(0)
    setError(null)
  }, [])

  const upload = useCallback(
    async (file: File): Promise<string | null> => {
      setIsUploading(true)
      setProgress(0)
      setError(null)

      const uploader = createImageUploader({
        bucketId: options?.bucketId ?? "uploads/images",
        unique: options?.unique ?? true,
        onUploadProgress: (event) => {
          if (event.total) {
            setProgress(Math.round((event.loaded / event.total) * 100))
          }
        },
      })

      try {
        const url = await uploader(file)
        setProgress(100)

        return url
      } catch (err) {
        const message =
          err instanceof Error ? err.message : "Upload failed"
        setError(message)

        return null
      } finally {
        setIsUploading(false)
      }
    },
    [options?.bucketId, options?.unique],
  )

  return { upload, isUploading, progress, error, reset }
}
