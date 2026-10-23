import { useMemo } from 'react'
import { cn } from '@/lib/utils'
import { Loader2 } from 'lucide-react'
import type { AdvisorMessage } from '@modules/Finance/types/finance'
import { AdvisorChart, type AdvisorChartBlock } from './advisor-chart'

interface AdvisorMessageBubbleProps {
  message: AdvisorMessage
}

/**
 * Lightweight markdown-to-HTML converter for AI responses.
 * Handles: headers, bold, italic, code blocks, inline code,
 * unordered/ordered lists, horizontal rules, links, and paragraphs.
 */
function renderMarkdown(text: string): string {
  // Escape HTML entities to prevent XSS
  const escape = (s: string) =>
    s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;')

  const escaped = escape(text)

  // Extract fenced code blocks before processing other rules
  const codeBlocks: string[] = []
  let processed = escaped.replace(/```(?:\w*)\n([\s\S]*?)```/g, (_, code) => {
    codeBlocks.push(code.replace(/\n$/, ''))
    return `__CODE_BLOCK_${codeBlocks.length - 1}__`
  })

  // Process line-by-line for block elements
  const lines = processed.split('\n')
  const result: string[] = []
  let inList = false
  let listType = ''

  for (let i = 0; i < lines.length; i++) {
    let line = lines[i]

    // Code block placeholder
    const codeMatch = line.match(/^__CODE_BLOCK_(\d+)__$/)
    if (codeMatch) {
      if (inList) { result.push(listType === 'ul' ? '</ul>' : '</ol>'); inList = false }
      result.push(`<pre class="my-2 p-2.5 rounded-lg bg-background/50 overflow-x-auto text-xs"><code>${codeBlocks[parseInt(codeMatch[1])]}</code></pre>`)
      continue
    }

    // Horizontal rule
    if (/^[-*_]{3,}\s*$/.test(line)) {
      if (inList) { result.push(listType === 'ul' ? '</ul>' : '</ol>'); inList = false }
      result.push('<hr class="my-2 border-current opacity-20" />')
      continue
    }

    // Headers (## Header)
    const headerMatch = line.match(/^(#{1,4})\s+(.+)$/)
    if (headerMatch) {
      if (inList) { result.push(listType === 'ul' ? '</ul>' : '</ol>'); inList = false }
      const level = headerMatch[1].length
      const sizes = ['text-base font-bold', 'text-sm font-bold', 'text-sm font-semibold', 'text-sm font-medium']
      result.push(`<p class="${sizes[level - 1]} mt-2 mb-1">${applyInline(headerMatch[2])}</p>`)
      continue
    }

    // Unordered list items (- item or * item)
    const ulMatch = line.match(/^[\s]*[-*+]\s+(.+)$/)
    if (ulMatch) {
      if (!inList || listType !== 'ul') {
        if (inList) result.push(listType === 'ul' ? '</ul>' : '</ol>')
        result.push('<ul class="my-1 ml-4 list-disc space-y-0.5">')
        inList = true; listType = 'ul'
      }
      result.push(`<li>${applyInline(ulMatch[1])}</li>`)
      continue
    }

    // Ordered list items (1. item)
    const olMatch = line.match(/^\s*\d+[.)]\s+(.+)$/)
    if (olMatch) {
      if (!inList || listType !== 'ol') {
        if (inList) result.push(listType === 'ul' ? '</ul>' : '</ol>')
        result.push('<ol class="my-1 ml-4 list-decimal space-y-0.5">')
        inList = true; listType = 'ol'
      }
      result.push(`<li>${applyInline(olMatch[1])}</li>`)
      continue
    }

    // Close list if line is not a list item
    if (inList) {
      result.push(listType === 'ul' ? '</ul>' : '</ol>')
      inList = false
    }

    // Table (| a | b | ... |)
    if (isTableRow(line)) {
      const tableRows = [line]
      let j = i + 1
      while (j < lines.length && isTableRow(lines[j])) {
        tableRows.push(lines[j])
        j++
      }
      const tableHtml = renderTable(tableRows)
      if (tableHtml) {
        result.push(tableHtml)
        i = j - 1
        continue
      }
    }

    // Empty line = spacing
    if (line.trim() === '') {
      result.push('<div class="h-2"></div>')
      continue
    }

    // Regular paragraph
    result.push(`<p>${applyInline(line)}</p>`)
  }

  if (inList) result.push(listType === 'ul' ? '</ul>' : '</ol>')

  return result.join('')
}

function extractChartBlocks(text: string): {
  markdown: string
  charts: AdvisorChartBlock[]
} {
  const charts: AdvisorChartBlock[] = []
  const markdown = text.replace(/```finance-chart\n([\s\S]*?)```/g, (_, json) => {
    try {
      const parsed = JSON.parse(json) as AdvisorChartBlock

      if (
        (parsed.type === 'bar' || parsed.type === 'line') &&
        typeof parsed.title === 'string' &&
        typeof parsed.currency === 'string' &&
        Array.isArray(parsed.data) &&
        parsed.series &&
        typeof parsed.series === 'object'
      ) {
        charts.push(parsed)
      }
    } catch {
      return ''
    }

    return ''
  })

  return { markdown, charts }
}

function isTableRow(line: string): boolean {
  return /^\s*\|.*\|\s*$/.test(line)
}

function isSeparatorRow(line: string): boolean {
  return /^\s*\|?\s*:?-{2,}:?\s*(\|\s*:?-{2,}:?\s*)+\|?\s*$/.test(line)
}

function splitTableRow(line: string): string[] {
  return line
    .trim()
    .replace(/^\|/, '')
    .replace(/\|$/, '')
    .split('|')
    .map((cell) => cell.trim())
}

function renderTable(rows: string[]): string | null {
  if (rows.length < 2) return null

  let sepIndex = -1
  for (let i = 1; i < rows.length; i++) {
    if (isSeparatorRow(rows[i])) {
      sepIndex = i
      break
    }
  }
  if (sepIndex === -1) return null

  const header = splitTableRow(rows[0])
  const aligns = splitTableRow(rows[sepIndex]).map((cell) => {
    if (cell.startsWith(':') && cell.endsWith(':')) return 'text-center'
    if (cell.endsWith(':')) return 'text-right'
    return 'text-left'
  })
  const dataRows = rows.slice(sepIndex + 1)

  const headerHtml =
    '<thead><tr>' +
    header
      .map((cell, i) => `<th class="border border-border px-2 py-1 text-left font-semibold bg-background/50 ${aligns[i] ?? 'text-left'}">${applyInline(cell)}</th>`)
      .join('') +
    '</tr></thead>'

  const bodyHtml =
    '<tbody>' +
    dataRows
      .map((row) => {
        const cells = splitTableRow(row)
        return (
          '<tr>' +
          cells
            .map((cell, i) => `<td class="border border-border px-2 py-1 align-top ${aligns[i] ?? 'text-left'}">${applyInline(cell)}</td>`)
            .join('') +
          '</tr>'
        )
      })
      .join('') +
    '</tbody>'

  return `<div class="my-2 overflow-x-auto rounded-lg border border-border"><table class="w-full border-collapse text-xs">${headerHtml}${bodyHtml}</table></div>`
}

const latexSymbols: Record<string, string> = {
  '\\rightarrow': '→',
  '\\Rightarrow': '⇒',
  '\\leftarrow': '←',
  '\\Leftarrow': '⇐',
  '\\leftrightarrow': '↔',
  '\\Leftrightarrow': '⇔',
  '\\to': '→',
  '\\approx': '≈',
  '\\neq': '≠',
  '\\ne': '≠',
  '\\leq': '≤',
  '\\le': '≤',
  '\\geq': '≥',
  '\\ge': '≥',
  '\\times': '×',
  '\\cdot': '·',
  '\\pm': '±',
  '\\div': '÷',
}

function convertLatex(inner: string): string {
  return inner
    .replace(/\\text\{([^}]*)\}/g, '$1')
    .replace(/\\frac\{([^}]+)\}\{([^}]+)\}/g, '$1/$2')
    .replace(/\\mathbf\{([^}]*)\}/g, '<strong>$1</strong>')
    .replace(/\\%/g, '%')
    .replace(/\\(?=\d)/g, '')
    .replace(
      /\\(?:rightarrow|Rightarrow|leftarrow|Leftarrow|leftrightarrow|Leftrightarrow|to|approx|neq|ne|leq|le|geq|ge|times|cdot|pm|div)/g,
      (match) => latexSymbols[match] ?? match
    )
}

/** Apply inline markdown: bold, italic, inline code, links */
function applyInline(text: string): string {
  return text
    // LaTeX math notation ($...$ → plain symbols)
    .replace(/\$([^$]*\\[a-zA-Z][^$]*)\$/g, (_, inner) => convertLatex(inner))
    // Malformed LaTeX: trailing $ after \mathbf{...} → bold with leading $ (e.g. \mathbf{\8.37}$ → $8.37)
    .replace(/\\mathbf\{([^}]*)\}\$/g, (_, inner) => `<strong>$${inner.replace(/\\(?=\d)/g, '')}</strong>`)
    // Standalone LaTeX symbols outside $...$ (e.g. 524 \div 8.37 → 524 ÷ 8.37)
    .replace(/\\(?:rightarrow|Rightarrow|leftarrow|Leftarrow|leftrightarrow|Leftrightarrow|to|approx|neq|ne|leq|le|geq|ge|times|cdot|pm|div)/g, (match) => latexSymbols[match] ?? match)
    // Standalone \mathbf{...} outside $...$ (e.g. \mathbf{8.37} → 8.37 bold)
    .replace(/\\mathbf\{([^}]*)\}/g, (_, inner) => `<strong>${inner.replace(/\\(?=\d)/g, '')}</strong>`)
    // Inline code (`code`)
    .replace(/`([^`]+)`/g, '<code class="px-1 py-0.5 rounded bg-background/50 text-xs">$1</code>')
    // Bold + italic (***text***)
    .replace(/\*\*\*(.+?)\*\*\*/g, '<strong><em>$1</em></strong>')
    // Bold (**text**)
    .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
    // Italic (*text*)
    .replace(/\*(.+?)\*/g, '<em>$1</em>')
    // Links [text](url)
    .replace(/\[([^\]]+)]\(([^)]+)\)/g, '<a href="$2" target="_blank" rel="noopener noreferrer" class="underline">$1</a>')
}

export function AdvisorMessageBubble({ message }: AdvisorMessageBubbleProps) {
  const isUser = message.role === 'user'

  const renderedMessage = useMemo(() => {
    if (isUser || !message.content) return { html: null, charts: [] }

    const { markdown, charts } = extractChartBlocks(message.content)

    return {
      html: renderMarkdown(markdown.trim()),
      charts,
    }
  }, [message.content, isUser])

  return (
    <div className={cn('flex', isUser ? 'justify-end' : 'justify-start')}>
      <div
        className={cn(
          'max-w-[85%] sm:max-w-[75%] rounded-2xl px-4 py-2.5 text-sm',
          isUser
            ? 'bg-primary text-primary-foreground rounded-br-md'
            : 'bg-muted rounded-bl-md'
        )}
      >
        {message.isProcessing ? (
          <div className="flex items-center gap-2 text-muted-foreground">
            <Loader2 className="h-4 w-4 animate-spin" />
            <span className="text-sm animate-pulse">...</span>
          </div>
        ) : message.error ? (
          <p className="text-destructive text-sm">{message.error}</p>
        ) : isUser ? (
          <div className="whitespace-pre-wrap break-words">{message.content}</div>
        ) : (
          <>
            <div
              className="break-words advisor-markdown"
              dangerouslySetInnerHTML={{ __html: renderedMessage.html ?? '' }}
            />
            {renderedMessage.charts.map((chart, index) => (
              <AdvisorChart key={`${chart.title}-${index}`} chart={chart} />
            ))}
          </>
        )}

        <div
          className={cn(
            'text-[10px] mt-1',
            isUser ? 'text-primary-foreground/60' : 'text-muted-foreground'
          )}
        >
          {new Date(message.created_at).toLocaleTimeString([], {
            hour: '2-digit',
            minute: '2-digit',
          })}
        </div>
      </div>
    </div>
  )
}
