import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import DraftPreview from '../src/features/content-preview/components/DraftPreview.vue'
import { previewLinks } from '../src/features/content-preview/services/previewLinks'

describe('saved draft preview', () => {
  it('separates draft and public links and rejects unsafe configuration', () => {
    expect(previewLinks('home', 'https://shop.example/')).toEqual({
      draft: 'https://shop.example/preview/home',
      published: 'https://shop.example/',
    })
    expect(previewLinks('company', 'https://shop.example/shop')).toEqual({
      draft: 'https://shop.example/shop/preview/company',
      published: 'https://shop.example/shop/company',
    })
    for (const url of [
      'javascript:alert(1)',
      'https://user:secret@shop.example',
      'https://shop.example/?token=secret',
      'https://shop.example/#token',
      'invalid',
    ])
      expect(() => previewLinks('home', url)).toThrow()
  })
  it('reloads only saved revisions, preserves safe links, and marks pending edits', async () => {
    const wrapper = mount(DraftPreview, {
      props: { slug: 'company', revision: 0 },
    })
    const original = wrapper.get('iframe').element
    expect(wrapper.get('iframe').attributes('title')).toContain('company')
    expect(wrapper.get('iframe').attributes('referrerpolicy')).toBe(
      'no-referrer',
    )
    for (const link of wrapper.findAll('a')) {
      expect(link.attributes('rel')).toBe('noopener noreferrer')
      expect(link.attributes('target')).toBe('_blank')
      expect(link.attributes('href')).not.toContain('token')
    }
    await wrapper.setProps({ pending: true })
    expect(wrapper.text()).toContain('Несохранённые изменения ещё не вошли')
    expect(wrapper.get('iframe').element).toBe(original)
    await wrapper.setProps({ revision: 1 })
    expect(wrapper.get('iframe').element).not.toBe(original)
    const mobile = wrapper
      .findAll('button')
      .find((button) => button.text() === 'Телефон')!
    await mobile.trigger('click')
    expect(mobile.attributes('aria-pressed')).toBe('true')
    expect(wrapper.get('iframe').classes()).toContain('preview-mobile')
    wrapper.unmount()
  })
  it('keeps the empty state explicit when no page is selected', () => {
    const wrapper = mount(DraftPreview)
    expect(wrapper.find('iframe').exists()).toBe(false)
    expect(wrapper.text()).toContain('Выберите страницу')
    wrapper.unmount()
  })
})
