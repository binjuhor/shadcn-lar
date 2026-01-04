import { useCallback, useState } from 'react'
import { useDropzone } from 'react-dropzone'
import { Button } from '@/components/ui/button'
import { Camera, Upload, X, Loader2, Image as ImageIcon } from 'lucide-react'
import { cn } from '@/lib/utils'

interface ImageDropzoneProps {
  onImageSelect: (file: File) => void
  isProcessing?: boolean
  disabled?: boolean
}

export function ImageDropzone({
  onImageSelect,
  isProcessing = false,
  disabled = false,
}: ImageDropzoneProps) {
  const [preview, setPreview] = useState<string | null>(null)
  const [selectedFile, setSelectedFile] = useState<File | null>(null)

  const onDrop = useCallback(
    (acceptedFiles: File[]) => {
      const file = acceptedFiles[0]
      if (file) {
        setSelectedFile(file)
        setPreview(URL.createObjectURL(file))
      }
    },
    []
  )

  const { getRootProps, getInputProps, isDragActive, open } = useDropzone({
    onDrop,
    accept: {
      'image/*': ['.jpeg', '.jpg', '.png', '.webp', '.heic'],
    },
    maxSize: 10 * 1024 * 1024, // 10MB
    multiple: false,
    disabled: disabled || isProcessing,
  })

  const handleClear = () => {
    setPreview(null)
    setSelectedFile(null)
  }

  const handleSubmit = () => {
    if (selectedFile) {
      onImageSelect(selectedFile)
    }
  }

  const handleCameraCapture = () => {
    const input = document.createElement('input')
    input.type = 'file'
    input.accept = 'image/*'
    input.capture = 'environment'
    input.onchange = (e) => {
      const file = (e.target as HTMLInputElement).files?.[0]
      if (file) {
        setSelectedFile(file)
        setPreview(URL.createObjectURL(file))
      }
    }
    input.click()
  }

  return (
    <div className="space-y-4">
      {preview ? (
        <div className="relative">
          <img
            src={preview}
            alt="Receipt preview"
            className="w-full max-h-64 object-contain rounded-lg border"
          />
          <Button
            type="button"
            variant="destructive"
            size="icon"
            className="absolute top-2 right-2"
            onClick={handleClear}
            disabled={isProcessing}
          >
            <X className="h-4 w-4" />
          </Button>
        </div>
      ) : (
        <div
          {...getRootProps()}
          className={cn(
            'border-2 border-dashed rounded-lg p-8 text-center cursor-pointer transition-colors',
            isDragActive
              ? 'border-primary bg-primary/5'
              : 'border-muted-foreground/25 hover:border-primary/50',
            (disabled || isProcessing) && 'opacity-50 cursor-not-allowed'
          )}
        >
          <input {...getInputProps()} />
          <div className="flex flex-col items-center gap-4">
            {isProcessing ? (
              <Loader2 className="h-12 w-12 text-muted-foreground animate-spin" />
            ) : (
              <ImageIcon className="h-12 w-12 text-muted-foreground" />
            )}
            {isDragActive ? (
              <p className="text-sm text-primary font-medium">
                Drop receipt here...
              </p>
            ) : isProcessing ? (
              <p className="text-sm text-muted-foreground">
                Processing image...
              </p>
            ) : (
              <div className="space-y-2">
                <p className="text-sm font-medium">
                  Drag & drop receipt here
                </p>
                <p className="text-xs text-muted-foreground">
                  or click to select file
                </p>
              </div>
            )}
          </div>
        </div>
      )}

      <div className="flex gap-2">
        {!preview ? (
          <>
            <Button
              type="button"
              variant="outline"
              className="flex-1"
              onClick={open}
              disabled={disabled || isProcessing}
            >
              <Upload className="mr-2 h-4 w-4" />
              Upload
            </Button>
            <Button
              type="button"
              variant="outline"
              className="flex-1"
              onClick={handleCameraCapture}
              disabled={disabled || isProcessing}
            >
              <Camera className="mr-2 h-4 w-4" />
              Camera
            </Button>
          </>
        ) : (
          <Button
            type="button"
            className="w-full"
            onClick={handleSubmit}
            disabled={isProcessing}
          >
            {isProcessing ? (
              <>
                <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                Processing...
              </>
            ) : (
              'Extract from Receipt'
            )}
          </Button>
        )}
      </div>
    </div>
  )
}
