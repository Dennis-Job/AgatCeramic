import { mount } from '@vue/test-utils'
import { describe, expect, test } from 'vitest'
import UiDatePicker from '../src/components/ui/UiDatePicker.vue'
import UiInput from '../src/components/ui/UiInput.vue'
import UiSelect from '../src/components/ui/UiSelect.vue'

describe('UI-kit disabled contract', () => {
  test('UiInput disables both native input and clear action without emitting changes', async () => {
    const wrapper = mount(UiInput, {
      props: { modelValue: 'Керамогранит', searchable: true },
      attrs: { disabled: true, 'aria-label': 'Поиск товара' },
    })

    const input = wrapper.get('input')
    const clear = wrapper.get('button[aria-label="Очистить поле"]')
    expect(input.attributes()).toHaveProperty('disabled')
    expect(clear.attributes()).toHaveProperty('disabled')
    expect(wrapper.get('div').classes()).toContain('cursor-not-allowed')

    ;(input.element as HTMLInputElement).value = 'Изменено'
    await input.trigger('input')
    await clear.trigger('click')
    expect(wrapper.emitted('update:modelValue')).toBeUndefined()
  })

  test('UiSelect disables trigger and clear action and closes an open menu when disabled', async () => {
    const wrapper = mount(UiSelect, {
      attachTo: document.body,
      props: {
        modelValue: 'matte',
        accessibleName: 'Поверхность',
        clearable: true,
        options: [{ value: 'matte', label: 'Матовая' }],
      },
    })

    const trigger = wrapper.get('button[aria-label="Поверхность"]')
    await trigger.trigger('click')
    expect(wrapper.find('[role="group"]').exists()).toBe(true)

    await wrapper.setProps({ disabled: true })
    expect(wrapper.find('[role="group"]').exists()).toBe(false)
    expect(trigger.attributes()).toHaveProperty('disabled')

    const clear = wrapper.get('button[aria-label="Очистить выбор: Поверхность"]')
    expect(clear.attributes()).toHaveProperty('disabled')
    await clear.trigger('click')
    expect(wrapper.emitted('update:modelValue')).toBeUndefined()
    expect(wrapper.emitted('change')).toBeUndefined()
    wrapper.unmount()
  })

  test('UiDatePicker disables input and clear action without opening or emitting changes', async () => {
    const wrapper = mount(UiDatePicker, {
      props: { modelValue: '2026-09-13', accessibleName: 'Дата публикации', disabled: true },
    })

    const input = wrapper.get('input')
    const clear = wrapper.get('button[aria-label="Очистить дату"]')
    expect(input.attributes()).toHaveProperty('disabled')
    expect(clear.attributes()).toHaveProperty('disabled')
    expect(wrapper.get('.flex.h-11').classes()).toContain('cursor-not-allowed')

    await input.trigger('focus')
    ;(input.element as HTMLInputElement).value = '14.09.2026'
    await input.trigger('input')
    await clear.trigger('click')
    expect(wrapper.find('[role="dialog"]').exists()).toBe(false)
    expect(wrapper.emitted('update:modelValue')).toBeUndefined()
  })

  test('UiDatePicker keeps Escape close and focus return while enabled', async () => {
    const wrapper = mount(UiDatePicker, {
      attachTo: document.body,
      props: { modelValue: '2026-09-13', accessibleName: 'Дата публикации' },
    })
    const input = wrapper.get('input')

    ;(input.element as HTMLInputElement).focus()
    await wrapper.vm.$nextTick()
    expect(wrapper.find('[role="dialog"]').exists()).toBe(true)

    document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }))
    await wrapper.vm.$nextTick()
    expect(wrapper.find('[role="dialog"]').exists()).toBe(false)
    expect(document.activeElement).toBe(input.element)

    ;(input.element as HTMLInputElement).blur()
    ;(input.element as HTMLInputElement).focus()
    await wrapper.vm.$nextTick()
    expect(wrapper.find('[role="dialog"]').exists()).toBe(true)
    await wrapper.setProps({ disabled: true })
    expect(wrapper.find('[role="dialog"]').exists()).toBe(false)
    expect(input.attributes()).toHaveProperty('disabled')
    wrapper.unmount()
  })
})
