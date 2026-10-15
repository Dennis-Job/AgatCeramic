import { mount } from '@vue/test-utils'
import { expect, test } from 'vitest'
import UiSegmentedControl from '../src/components/ui/UiSegmentedControl.vue'

test('import choices label their regions and Home/End skip unavailable options', async () => {
  const wrapper = mount(UiSegmentedControl, {
    attachTo: document.body,
    props: {
      modelValue: 'products',
      name: 'import-type',
      idPrefix: 'import-type',
      label: 'Тип загрузки',
      fitContent: true,
      options: [
        { value: 'unavailable-first', label: 'Недоступно', disabled: true },
        { value: 'products', label: 'Загрузка товаров', panelId: 'products' },
        { value: 'images', label: 'Загрузка изображений', panelId: 'images' },
        { value: 'unavailable-last', label: 'Недоступно', disabled: true },
      ],
    },
  })
  try {
    const first = wrapper.get('#import-type-products')
    const last = wrapper.get('#import-type-images')
    expect(first.attributes('aria-controls')).toBe('products')
    expect(last.attributes('aria-controls')).toBe('images')
    await first.trigger('keydown', { key: 'End' })
    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual(['images'])
    expect(document.activeElement).toBe(last.element)
    await last.trigger('keydown', { key: 'Home' })
    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual(['products'])
    expect(document.activeElement).toBe(first.element)
    await wrapper.setProps({ disabled: true })
    await wrapper.trigger('keydown', { key: 'End' })
    expect(wrapper.emitted('update:modelValue')).toHaveLength(2)
  } finally {
    wrapper.unmount()
  }
})
