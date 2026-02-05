import { useState } from 'react'
import {
  Dialog,
  DialogContent,
  DialogTitle,
} from '@/components/ui/dialog'
import { VisuallyHidden } from '@radix-ui/react-visually-hidden'

interface ChatImageAttachmentProps {
  url: string
}

export function ChatImageAttachment({ url }: ChatImageAttachmentProps) {
  const [open, setOpen] = useState(false)

  return (
    <>
      <img
        src={url}
        alt="Attached image"
        className="max-h-48 rounded-lg object-contain cursor-zoom-in"
        onClick={() => setOpen(true)}
      />

      <Dialog open={open} onOpenChange={setOpen}>
        <DialogContent className="max-w-[90vw] max-h-[90vh] w-auto p-0 border-0 bg-transparent shadow-none [&>button]:text-white [&>button]:bg-black/50 [&>button]:rounded-full [&>button]:p-1.5 [&>button]:top-2 [&>button]:right-2">
          <VisuallyHidden>
            <DialogTitle>Image preview</DialogTitle>
          </VisuallyHidden>
          <img
            src={url}
            alt="Attached image"
            className="max-w-[90vw] max-h-[85vh] object-contain rounded-lg"
          />
        </DialogContent>
      </Dialog>
    </>
  )
}
