import { h, type VNodeChild } from 'vue'

export type FormattedTextNode = {
  type?: string
  text?: string
  attrs?: Record<string, unknown>
  content?: FormattedTextNode[]
  marks?: { type: string; attrs?: Record<string, unknown> }[]
}

function safeLink(value: unknown): value is string {
  return (
    typeof value === 'string' &&
    !/[\s\u0000-\u001f\u007f\\]/.test(value) &&
    !/%(?:0[0-9a-f]|1[0-9a-f]|7f)/i.test(value) &&
    (/^https?:\/\/[^/]+/i.test(value) ||
      /^mailto:[^@]+@[^@]+$/i.test(value) ||
      /^\/(?![\/\\])/.test(value))
  )
}

// No HTML or arbitrary attributes are accepted, including for older API responses.
export function renderFormattedText(
  node: FormattedTextNode,
  depth = 0,
): VNodeChild {
  if (!node || depth > 16) return null
  if (node.type === 'text') {
    let text: VNodeChild = typeof node.text === 'string' ? node.text : ''
    for (const mark of (node.marks ?? []).slice(0, 3)) {
      if (mark.type === 'bold') text = h('strong', null, [text])
      else if (mark.type === 'italic') text = h('em', null, [text])
      else if (mark.type === 'link' && safeLink(mark.attrs?.href))
        text = h('a', { href: mark.attrs.href, rel: 'noopener noreferrer' }, [
          text,
        ])
    }
    return text
  }
  const children = (Array.isArray(node.content) ? node.content : [])
    .slice(0, 5000)
    .map((child) => renderFormattedText(child, depth + 1))
  const tags: Record<string, string> = {
    paragraph: 'p',
    bulletList: 'ul',
    orderedList: 'ol',
    listItem: 'li',
    hardBreak: 'br',
  }
  if (node.type === 'doc') return children
  const tag =
    node.type === 'heading'
      ? node.attrs?.level === 3
        ? 'h3'
        : 'h2'
      : tags[node.type ?? '']
  if (!tag) return null
  const attrs: Record<string, unknown> = {}
  if (
    ['paragraph', 'heading'].includes(node.type ?? '') &&
    ['left', 'right', 'justify'].includes(String(node.attrs?.textAlign))
  )
    attrs.style = { textAlign: node.attrs?.textAlign }
  if (
    node.type === 'orderedList' &&
    Number.isInteger(node.attrs?.start) &&
    Number(node.attrs?.start) > 0
  )
    attrs.start = node.attrs?.start
  return h(tag, attrs, tag === 'br' ? undefined : children)
}
