import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import UiInput from '../src/components/ui/UiInput.vue'

describe('nullable input values from the API', () => {
  it.each([
    { type: 'text', money: false, empty: '', value: '/images/tile.webp' },
    { type: 'tel', money: false, empty: '+7', value: '+74951204567' },
    { type: 'text', money: true, empty: '', value: '1200.50' },
  ])('renders and edits an empty $type field (money=$money)', async (mode) => {
    const wrapper = mount(UiInput, {
      props: { type: mode.type, money: mode.money, modelValue: null },
      attrs: { 'aria-label': 'Поле' },
    })
    const field = wrapper.get('input')
    expect(field.element.value).toBe(mode.empty)
    expect(field.element.checkValidity()).toBe(true)
    expect(wrapper.find('button').exists()).toBe(false)
    // Rendering an API null must not turn an untouched draft into an edit.
    expect(wrapper.emitted('update:modelValue')).toBeUndefined()
    await field.trigger('blur')
    await field.trigger('keydown', { key: 'Enter' })
    expect(wrapper.emitted('update:modelValue')).toBeUndefined()

    await field.setValue(mode.value)
    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([mode.value])
    await wrapper.setProps({ modelValue: mode.value })
    expect(wrapper.find('button').exists()).toBe(true)
    await wrapper.get('button').trigger('click')
    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([''])

    await wrapper.setProps({ modelValue: null })
    expect(field.element.value).toBe(mode.empty)
    expect(wrapper.find('button').exists()).toBe(false)
    wrapper.unmount()
  })
})
