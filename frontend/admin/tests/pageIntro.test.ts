import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import PageIntroEditor from '../src/features/pages/components/PageIntroEditor.vue'
import type { PageIntro } from '../src/features/pages/types/page.types'

vi.mock('../src/stores/auth', () => ({
  useAuthStore: () => ({ hasPermission: () => true }),
}))

const global = {
  stubs: {
    MediaImageEditor: {
      name: 'MediaImageEditor',
      props: ['modelValue', 'fallbackUrl', 'emptySelectionLabel', 'disabled'],
      emits: ['update:modelValue', 'pending', 'uploading'],
      template: '<div />',
    },
  },
}

describe('page intro image editor', () => {
  it('shows standard-image instructions for a legacy page and creates a separate image reference', async () => {
    const wrapper = mount(PageIntroEditor, {
      props: { modelValue: null },
      global,
    })
    const media = wrapper.findComponent({ name: 'MediaImageEditor' })
    expect(media.props('modelValue')).toBeNull()
    expect(media.props('emptySelectionLabel')).toBe(
      'Стандартная иллюстрация страницы',
    )
    expect(wrapper.text()).toContain('стандартную иллюстрацию по теме страницы')
    media.vm.$emit('update:modelValue', 18)
    expect(wrapper.emitted('update:modelValue')).toEqual([
      [
        {
          image_url: null,
          image_media_id: 18,
          image_alt: '',
        },
      ],
    ])
    wrapper.unmount()
  })

  it('retains custom URL and alt, resets explicitly, and describes a hidden intro', async () => {
    const intro: PageIntro = {
      image_url: '/images/custom-intro.webp',
      image_media_id: null,
      image_alt: 'Рельефная плитка',
    }
    const wrapper = mount(PageIntroEditor, {
      props: { modelValue: intro, hasHero: true },
      global,
    })
    expect(
      wrapper.findComponent({ name: 'MediaImageEditor' }).props('fallbackUrl'),
    ).toBe(intro.image_url)
    expect(wrapper.get('input').element.value).toBe('Рельефная плитка')
    expect(wrapper.text()).toContain('вступительный блок скрыт')
    await wrapper
      .findAll('button')
      .find((button) => button.text() === 'Вернуть стандартную иллюстрацию')!
      .trigger('click')
    expect(wrapper.emitted('update:modelValue')).toEqual([[null]])
    expect(intro.image_url).toBe('/images/custom-intro.webp')
    wrapper.unmount()
  })

  it('forwards pending and uploading state so the workspace can protect unsaved files', async () => {
    const wrapper = mount(PageIntroEditor, {
      props: { modelValue: null, disabled: true },
      global,
    })
    const media = wrapper.findComponent({ name: 'MediaImageEditor' })
    expect(media.props('disabled')).toBe(true)
    media.vm.$emit('pending', true)
    media.vm.$emit('uploading', true)
    expect(wrapper.emitted('pending')).toEqual([[true]])
    expect(wrapper.emitted('uploading')).toEqual([[true]])
    wrapper.unmount()
  })
})
