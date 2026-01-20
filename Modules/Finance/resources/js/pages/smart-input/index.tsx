import { useState } from 'react'
import { AuthenticatedLayout } from '@/layouts'
import { Main } from '@/components/layout/main'
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Textarea } from '@/components/ui/textarea'
import { Mic, Image, Sparkles, AlertCircle, MessageSquare, Loader2 } from 'lucide-react'
import { VoiceRecorder } from './components/voice-recorder'
import { ImageDropzone } from './components/image-dropzone'
import { TransactionPreview } from './components/transaction-preview'
import type { Account, Category, ParsedTransaction } from '@modules/Finance/types/finance'

interface Props {
  accounts: Account[]
  categories: Category[]
}

export default function SmartInputIndex({ accounts, categories }: Props) {
  const [isProcessing, setIsProcessing] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [parsedTransaction, setParsedTransaction] = useState<ParsedTransaction | null>(null)
  const [textInput, setTextInput] = useState('')

  const handleTextSubmit = async () => {
    if (!textInput.trim()) return

    setIsProcessing(true)
    setError(null)

    try {
      const response = await fetch(route('dashboard.finance.smart-input.parse-text'), {
        method: 'POST',
        body: JSON.stringify({ text: textInput, language: 'vi' }),
        headers: {
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
          'Accept': 'application/json',
          'Content-Type': 'application/json',
        },
      })

      if (!response.ok) {
        const errorData = await response.json().catch(() => ({}))
        setError(errorData.error || `Server error: ${response.status}`)
        return
      }

      const data = await response.json()

      if (data.success) {
        setParsedTransaction(data.data)
        setTextInput('')
      } else {
        setError(data.error || 'Failed to parse text input')
      }
    } catch (err) {
      setError('Network error. Please try again.')
      console.error('Text parse error:', err)
    } finally {
      setIsProcessing(false)
    }
  }

  const handleVoiceRecording = async (audioBlob: Blob) => {
    setIsProcessing(true)
    setError(null)

    try {
      const formData = new FormData()
      formData.append('audio', audioBlob, 'recording.webm')
      formData.append('language', 'vi')

      const response = await fetch(route('dashboard.finance.smart-input.parse-voice'), {
        method: 'POST',
        body: formData,
        headers: {
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
          'Accept': 'application/json',
        },
      })

      if (!response.ok) {
        const errorData = await response.json().catch(() => ({}))
        console.error('Voice API error:', response.status, errorData)

        // Provide helpful error message with fallback suggestion
        const errorMsg = errorData.error || `Server error: ${response.status}`
        const isQuotaError = errorMsg.toLowerCase().includes('quota')
        setError(isQuotaError
          ? `${errorMsg} You can use the Text tab to enter transactions manually.`
          : errorMsg
        )
        return
      }

      const data = await response.json()

      if (data.success) {
        setParsedTransaction(data.data)
      } else {
        const isQuotaError = (data.error || '').toLowerCase().includes('quota')
        setError(isQuotaError
          ? `${data.error} You can use the Text tab to enter transactions manually.`
          : data.error || 'Failed to parse voice input'
        )
      }
    } catch (err) {
      setError('Network error. Please try again.')
      console.error('Voice parse error:', err)
    } finally {
      setIsProcessing(false)
    }
  }

  const handleImageSelect = async (file: File) => {
    setIsProcessing(true)
    setError(null)

    try {
      const formData = new FormData()
      formData.append('image', file)
      formData.append('language', 'vi')

      const response = await fetch(route('dashboard.finance.smart-input.parse-receipt'), {
        method: 'POST',
        body: formData,
        headers: {
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
          'Accept': 'application/json',
        },
      })

      if (!response.ok) {
        const errorText = await response.text()
        console.error('Receipt API error:', response.status, errorText)
        setError(`Server error: ${response.status}`)
        return
      }

      const data = await response.json()

      if (data.success) {
        setParsedTransaction(data.data)
      } else {
        setError(data.error || 'Failed to parse receipt')
      }
    } catch (err) {
      setError('Network error. Please try again.')
      console.error('Receipt parse error:', err)
    } finally {
      setIsProcessing(false)
    }
  }

  const handleReset = () => {
    setParsedTransaction(null)
    setError(null)
  }

  return (
    <AuthenticatedLayout title="Smart Transaction Input">
      <Main>
        <div className="mb-6">
          <div className="flex items-center gap-2 mb-2">
            <Sparkles className="h-6 w-6 text-primary" />
            <h1 className="text-2xl font-bold tracking-tight">Smart Input</h1>
          </div>
          <p className="text-muted-foreground">
            Add transactions by text, voice, or receipt photo using AI
          </p>
        </div>

        {accounts.length === 0 ? (
          <Alert>
            <AlertCircle className="h-4 w-4" />
            <AlertDescription>
              Please create at least one account before adding transactions.
            </AlertDescription>
          </Alert>
        ) : parsedTransaction ? (
          <TransactionPreview
            parsed={parsedTransaction}
            accounts={accounts}
            categories={categories}
            onReset={handleReset}
          />
        ) : (
          <Card>
            <CardHeader>
              <CardTitle>Input Method</CardTitle>
              <CardDescription>
                Choose voice or image to extract transaction details
              </CardDescription>
            </CardHeader>
            <CardContent>
              {error && (
                <Alert variant="destructive" className="mb-4">
                  <AlertCircle className="h-4 w-4" />
                  <AlertDescription>{error}</AlertDescription>
                </Alert>
              )}

              <Tabs defaultValue="text" className="w-full">
                <TabsList className="w-full grid grid-cols-3 mb-6">
                  <TabsTrigger value="text" className="flex items-center gap-2">
                    <MessageSquare className="h-4 w-4" />
                    Text
                  </TabsTrigger>
                  <TabsTrigger value="voice" className="flex items-center gap-2">
                    <Mic className="h-4 w-4" />
                    Voice
                  </TabsTrigger>
                  <TabsTrigger value="image" className="flex items-center gap-2">
                    <Image className="h-4 w-4" />
                    Receipt
                  </TabsTrigger>
                </TabsList>

                <TabsContent value="text" className="mt-0">
                  <div className="space-y-4">
                    <Textarea
                      placeholder="Nhập giao dịch... VD: Đi chợ 50k, Cafe 30 nghìn, Lương tháng 15 triệu"
                      value={textInput}
                      onChange={(e) => setTextInput(e.target.value)}
                      onKeyDown={(e) => {
                        if (e.key === 'Enter' && !e.shiftKey) {
                          e.preventDefault()
                          handleTextSubmit()
                        }
                      }}
                      rows={3}
                      disabled={isProcessing}
                    />
                    <Button
                      onClick={handleTextSubmit}
                      disabled={isProcessing || !textInput.trim()}
                      className="w-full"
                    >
                      {isProcessing ? (
                        <>
                          <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                          Processing...
                        </>
                      ) : (
                        'Parse Transaction'
                      )}
                    </Button>
                  </div>
                  <div className="mt-6 p-4 rounded-lg bg-muted">
                    <p className="text-sm font-medium mb-2">Examples:</p>
                    <ul className="text-sm text-muted-foreground space-y-1">
                      <li>"Đi chợ 50k" → Expense 50,000đ</li>
                      <li>"Cafe 30 nghìn hôm qua" → Expense 30,000đ yesterday</li>
                      <li>"Đổ xăng 200k" → Expense 200,000đ</li>
                      <li>"Lương tháng 15 triệu" → Income 15,000,000đ</li>
                    </ul>
                  </div>
                </TabsContent>

                <TabsContent value="voice" className="mt-0">
                  <div className="py-8">
                    <VoiceRecorder
                      onRecordingComplete={handleVoiceRecording}
                      isProcessing={isProcessing}
                    />
                  </div>
                  <div className="mt-6 p-4 rounded-lg bg-muted">
                    <p className="text-sm font-medium mb-2">Examples:</p>
                    <ul className="text-sm text-muted-foreground space-y-1">
                      <li>"Ăn sáng 35 nghìn"</li>
                      <li>"Cafe 50k hôm nay"</li>
                      <li>"Đổ xăng 200k hôm qua"</li>
                      <li>"Lương tháng 15 triệu"</li>
                    </ul>
                  </div>
                </TabsContent>

                <TabsContent value="image" className="mt-0">
                  <ImageDropzone
                    onImageSelect={handleImageSelect}
                    isProcessing={isProcessing}
                  />
                  <div className="mt-6 p-4 rounded-lg bg-muted">
                    <p className="text-sm font-medium mb-2">Tips:</p>
                    <ul className="text-sm text-muted-foreground space-y-1">
                      <li>Take clear photos of receipts</li>
                      <li>Ensure total amount is visible</li>
                      <li>Include store name if possible</li>
                      <li>Supports Vietnamese & English receipts</li>
                    </ul>
                  </div>
                </TabsContent>
              </Tabs>
            </CardContent>
          </Card>
        )}
      </Main>
    </AuthenticatedLayout>
  )
}
