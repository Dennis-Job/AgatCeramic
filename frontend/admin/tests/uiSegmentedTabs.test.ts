import { mount } from '@vue/test-utils'
import { describe, expect, test } from 'vitest'
import UiSegmentedTabs from '../src/components/ui/UiSegmentedTabs.vue'

const options = [
  {
    id: 'products',
    label: 'Загрузка товаров',
    panelId: 'import-panel-products',
  },
  {
    id: 'images',
    label: 'Загрузка изображений',
    panelId: 'import-panel-images',
  },
]

describe('UiSegmentedTabs', () => {
  test('links accessible tab buttons to their panels and uses a one-pixel outline', () => {
    const wrapper = mount(UiSegmentedTabs, {
      attachTo: document.body,
      props: {
        modelValue: 'products',
        idPrefix: 'import-tab',
        label: 'Тип загрузки',
        options,
      },
    })
    const selected = wrapper.get('#import-tab-products')
    const inactive = wrapper.get('#import-tab-images')

    expect(wrapper.get('[role="tablist"]').attributes('aria-label')).toBe(
      'Тип загрузки',
    )
    expect(selected.attributes()).toMatchObject({
      role: 'tab',
      'aria-selected': 'true',
      'aria-controls': 'import-panel-products',
      tabindex: '0',
    })
    expect(inactive.attributes()).toMatchObject({
      role: 'tab',
      'aria-selected': 'false',
      'aria-controls': 'import-panel-images',
      tabindex: '-1',
    })
    expect(selected.classes()).toContain('outline-1')
    expect(selected.classes()).toContain('outline-primary-500')
    expect(selected.classes()).not.toContain('outline-2')
    wrapper.unmount()
  })

  test('emits tab selection on click and supports arrow, Home, and End keys', async () => {
    const wrapper = mount(UiSegmentedTabs, {
      attachTo: document.body,
      props: {
        modelValue: 'products',
        idPrefix: 'import-tab',
        label: 'Тип загрузки',
        options,
      },
    })
    const tabList = wrapper.get('[role="tablist"]')

    await wrapper.get('#import-tab-images').trigger('click')
    expect(wrapper.emitted('update:modelValue')).toEqual([['images']])

    await tabList.trigger('keydown', { key: 'ArrowRight' })
    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual(['images'])
    expect(document.activeElement).toBe(
      wrapper.get('#import-tab-images').element,
    )

    await tabList.trigger('keydown', { key: 'Home' })
    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual(['products'])
    expect(document.activeElement).toBe(
      wrapper.get('#import-tab-products').element,
    )

    await tabList.trigger('keydown', { key: 'End' })
    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual(['images'])
    expect(document.activeElement).toBe(
      wrapper.get('#import-tab-images').element,
    )
    wrapper.unmount()
  })
})
