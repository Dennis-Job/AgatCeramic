import type { JSONContent } from '@tiptap/vue-3'

export function safeRichTextLink(value: string): boolean {
  if (
    /\s|\\/.test(value) ||
    Array.from(value).some(
      (char) => char.charCodeAt(0) < 32 || char.charCodeAt(0) === 127,
    ) ||
    /%(?:0[0-9a-f]|1[0-9a-f]|7f)/i.test(value)
  )
    return false
  if (value.startsWith('/') && !value.startsWith('//')) return true
  if (/^mailto:/i.test(value)) return /^mailto:[^@]+@[^@]+\.[^@]+$/i.test(value)
  if (!/^https?:\/\//i.test(value)) return false
  try {
    const url = new URL(value)
    return (
      ['https:', 'http:'].includes(url.protocol) &&
      Boolean(url.host) &&
      !url.username &&
      !url.password
    )
  } catch {
    return false
  }
}

// Keep only attributes in the public document contract, rather than editor metadata.
export function documentContent(node: JSONContent): JSONContent {
  const result: JSONContent = { type: node.type }
  if (node.text !== undefined) result.text = node.text
  if (node.content) result.content = node.content.map(documentContent)
  const attrs: Record<string, unknown> = {}
  if (node.type === 'heading') attrs.level = node.attrs?.level
  if (
    ['paragraph', 'heading'].includes(node.type ?? '') &&
    node.attrs?.textAlign
  )
    attrs.textAlign = node.attrs.textAlign
  if (node.type === 'orderedList' && node.attrs?.start !== 1)
    attrs.start = node.attrs?.start
  if (Object.keys(attrs).length) result.attrs = attrs
  if (node.marks?.length)
    result.marks = node.marks.flatMap((mark) => {
      if (mark.type === 'link')
        return safeRichTextLink(mark.attrs?.href ?? '')
          ? [{ type: 'link', attrs: { href: mark.attrs?.href } }]
          : []
      return ['bold', 'italic'].includes(mark.type) ? [{ type: mark.type }] : []
    })
  return result
}

export function plainDocument(body: string): JSONContent {
  return {
    type: 'doc',
    content: body.split('\n').map((text) => ({
      type: 'paragraph',
      ...(text ? { content: [{ type: 'text', text }] } : {}),
    })),
  }
}
