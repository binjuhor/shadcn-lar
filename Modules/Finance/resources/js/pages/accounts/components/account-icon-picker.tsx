import { useEffect, useMemo, useRef, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { ImageIcon, Images, Search, Upload, X } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from '@/components/ui/popover'
import { ScrollArea } from '@/components/ui/scroll-area'
import { cn } from '@/lib/utils'
import type { BankIcon } from '@modules/Finance/types/finance'

interface AccountIconPickerProps {
  bankIcons: BankIcon[]
  iconFile: File | null
  bankIconKey: string
  existingIconUrl?: string | null
  onFileChange: (file: File | null) => void
  onBankIconChange: (key: string) => void
  onClear?: () => void
  disabled?: boolean
}

export function AccountIconPicker({
  bankIcons,
  iconFile,
  bankIconKey,
  existingIconUrl,
  onFileChange,
  onBankIconChange,
  onClear,
  disabled,
}: AccountIconPickerProps) {
  const { t } = useTranslation()
  const [libraryOpen, setLibraryOpen] = useState(false)
  const [search, setSearch] = useState('')
  const fileInputRef = useRef<HTMLInputElement>(null)

  const selectedBankIcon = bankIcons.find((icon) => icon.key === bankIconKey) ?? null

  const blobUrl = useMemo(() => {
    if (iconFile instanceof File) {
      return URL.createObjectURL(iconFile)
    }
    return null
  }, [iconFile])

  useEffect(() => {
    return () => {
      if (blobUrl) {
        URL.revokeObjectURL(blobUrl)
      }
    }
  }, [blobUrl])

  const preview = blobUrl ?? selectedBankIcon?.url ?? existingIconUrl ?? null

  const filteredIcons = useMemo(() => {
    const query = search.trim().toLowerCase()
    if (!query) {
      return bankIcons
    }
    return bankIcons.filter((icon) => icon.name.toLowerCase().includes(query) || icon.key.toLowerCase().includes(query))
  }, [bankIcons, search])

  const hasSelection = !!iconFile || !!bankIconKey

  return (
    <div className="flex items-start gap-3">
      <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-lg border bg-muted/40">
        {preview ? (
          <img src={preview} alt="" className="h-8 w-8 object-contain" />
        ) : (
          <ImageIcon className="h-5 w-5 text-muted-foreground" />
        )}
      </div>

      <div className="flex-1 space-y-2">
        <div className="flex flex-wrap gap-2">
          <Button
            type="button"
            variant="outline"
            size="sm"
            disabled={disabled}
            onClick={() => fileInputRef.current?.click()}
          >
            <Upload className="mr-2 h-4 w-4" />
            {t('form.icon.upload_file')}
          </Button>

          <Popover open={libraryOpen} onOpenChange={setLibraryOpen}>
            <PopoverTrigger asChild>
              <Button type="button" variant="outline" size="sm" disabled={disabled}>
                <Images className="mr-2 h-4 w-4" />
                {t('form.icon.choose_from_library')}
              </Button>
            </PopoverTrigger>
            <PopoverContent className="w-72 p-0" align="start">
              <div className="flex items-center border-b px-3">
                <Search className="mr-2 h-4 w-4 shrink-0 opacity-50" />
                <Input
                  value={search}
                  onChange={(e) => setSearch(e.target.value)}
                  placeholder={t('form.icon.search_placeholder')}
                  className="h-9 border-0 bg-transparent px-0 focus-visible:ring-0 focus-visible:ring-offset-0"
                />
              </div>
              <ScrollArea className="h-64">
                <div className="grid grid-cols-4 gap-2 p-3">
                  {filteredIcons.map((icon) => (
                    <button
                      key={icon.key}
                      type="button"
                      title={icon.name}
                      onClick={() => {
                        onBankIconChange(icon.key)
                        setLibraryOpen(false)
                      }}
                      className={cn(
                        'flex h-14 items-center justify-center rounded-md border p-1.5 transition-colors hover:bg-accent',
                        bankIconKey === icon.key && 'border-primary bg-accent'
                      )}
                    >
                      <img src={icon.url} alt={icon.name} className="h-full w-full object-contain" />
                    </button>
                  ))}
                  {filteredIcons.length === 0 && (
                    <p className="col-span-4 py-6 text-center text-sm text-muted-foreground">
                      {t('form.icon.no_icons_found')}
                    </p>
                  )}
                </div>
              </ScrollArea>
            </PopoverContent>
          </Popover>

          <Input
            ref={fileInputRef}
            type="file"
            accept=".svg,image/svg+xml,image/png,image/jpeg,image/webp"
            className="hidden"
            onChange={(e) => onFileChange(e.target.files?.[0] ?? null)}
          />

          {(hasSelection || existingIconUrl) && onClear && (
            <Button type="button" variant="ghost" size="sm" disabled={disabled} onClick={onClear}>
              <X className="mr-2 h-4 w-4" />
              {t('form.icon.clear')}
            </Button>
          )}
        </div>

        {selectedBankIcon && (
          <p className="text-xs text-muted-foreground">{selectedBankIcon.name}</p>
        )}
      </div>
    </div>
  )
}
