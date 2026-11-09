import { mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import { describe, expect, it, vi } from 'vitest'
import UiRichTextEditor from '../src/components/ui/UiRichTextEditor.vue'
import {
  documentContent,
  safeRichTextLink,
} from '../src/components/ui/richText'

describe('UiRichTextEditor', () => {
  it('limits saved link metadata and preserves inline spaces', () => {
    expect(
      documentContent({
        type: 'text',
        text: ' и текст ',
        marks: [
          {
            type: 'link',
            attrs: {
              href: 'https://example.test',
              target: '_blank',
              rel: 'noopener',
              class: null,
            },
          },
        ],
      }),
    ).toEqual({
      type: 'text',
      text: ' и текст ',
      marks: [{ type: 'link', attrs: { href: 'https://example.test' } }],
    })
    for (const href of [
      'javascript:alert(1)',
      '//example.test',
      'https:example.test',
      'https://user:pass@example.test',
      'https://example.test/%0a',
    ])
      expect(safeRichTextLink(href)).toBe(false)
    for (const href of [
      'https://example.test',
      'http://example.test/path',
      '/privacy-policy/',
      'mailto:info@example.test',
    ])
      expect(safeRichTextLink(href)).toBe(true)
  })
  it('renders saved formatting and alignment in read-only preview', async () => {
    const wrapper = mount(UiRichTextEditor, {
      props: {
        readonly: true,
        body: 'Прежний текст',
        modelValue: {
          type: 'doc',
          content: [
            {
              type: 'heading',
              attrs: { level: 2, textAlign: 'right' },
              content: [
                { type: 'text', text: 'Раздел', marks: [{ type: 'bold' }] },
              ],
            },
          ],
        },
      },
    })
    await nextTick()
    await vi.waitFor(() => expect(wrapper.find('h2').exists()).toBe(true))
    expect(wrapper.find('h2').text()).toBe('Раздел')
    expect(wrapper.find('h2').attributes('style')).toContain(
      'text-align: right',
    )
    expect(wrapper.find('strong').exists()).toBe(true)
    expect(wrapper.find('[role="toolbar"]').exists()).toBe(false)
    wrapper.unmount()
  })

  it('keeps legacy text literal and preserves line breaks', async () => {
    const wrapper = mount(UiRichTextEditor, {
      props: {
        readonly: true,
        body: '<script>literal</script>\nВторая строка',
        modelValue: null,
      },
    })
    await nextTick()
    await vi.waitFor(() => expect(wrapper.text()).toContain('Вторая строка'))
    expect(wrapper.find('script').exists()).toBe(false)
    expect(wrapper.text()).toContain('<script>literal</script>')
    expect(wrapper.findAll('p')).toHaveLength(2)
    wrapper.unmount()
  })
})
