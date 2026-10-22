import { mount, flushPromises } from '@vue/test-utils'
import { expect, test } from 'vitest'
import UiMonthPicker from '../src/components/ui/UiMonthPicker.vue'

test('month picker selects a month in a directly entered year and restores focus', async () => {
  const wrapper = mount(UiMonthPicker, {
    attachTo: document.body,
    global: { stubs: { Teleport: true } },
    props: { modelValue: '2026-10', accessibleName: 'Период' },
  })
  const trigger = wrapper.get('button[aria-label="Период"]')
  await trigger.trigger('click')
  await flushPromises()
  await wrapper.get('input[aria-label="Год"]').setValue('2025')
  const january = wrapper
    .findAll('button')
    .find((button) => button.text() === 'Январь')!
  await january.trigger('click')
  await flushPromises()
  expect(wrapper.emitted('update:modelValue')).toEqual([['2025-01']])
  expect(wrapper.find('[data-floating-popover]').exists()).toBe(false)
  expect(document.activeElement).toBe(trigger.element)
  wrapper.unmount()
})

test('calendar arrows navigate months and years without committing until a month is selected', async () => {
  const wrapper = mount(UiMonthPicker, {
    attachTo: document.body,
    global: { stubs: { Teleport: true } },
    props: { modelValue: '2026-10' },
  })
  await wrapper.get('button[aria-label="Месяц"]').trigger('click')
  await flushPromises()
  const october = wrapper
    .findAll('button')
    .find((button) => button.text() === 'Октябрь')!
  ;(october.element as HTMLButtonElement).focus()
  await october.trigger('keydown', { key: 'ArrowRight' })
  await flushPromises()
  expect(document.activeElement?.textContent).toBe('Ноябрь')
  const november = wrapper
    .findAll('button')
    .find((button) => button.text() === 'Ноябрь')!
  await november.trigger('keydown', { key: 'PageUp' })
  expect((wrapper.get('input').element as HTMLInputElement).value).toBe('2025')
  expect(wrapper.emitted('update:modelValue')).toBeUndefined()
  await november.trigger('keydown', { key: 'Escape' })
  await flushPromises()
  expect(wrapper.find('[data-floating-popover]').exists()).toBe(false)
  wrapper.unmount()
})

test('invalid years cannot be committed and disabled picker cannot open', async () => {
  const wrapper = mount(UiMonthPicker, {
    attachTo: document.body,
    global: { stubs: { Teleport: true } },
    props: { modelValue: '2026-10' },
  })
  await wrapper.get('button[aria-label="Месяц"]').trigger('click')
  await flushPromises()
  await wrapper.get('input').setValue('1899')
  const january = wrapper
    .findAll('button')
    .find((button) => button.text() === 'Январь')!
  expect(january.attributes('disabled')).toBeDefined()
  await january.trigger('click')
  expect(wrapper.emitted('update:modelValue')).toBeUndefined()
  await wrapper.setProps({ disabled: true })
  await flushPromises()
  expect(wrapper.find('[data-floating-popover]').exists()).toBe(false)
  expect(
    wrapper.get('button[aria-label="Месяц"]').attributes('disabled'),
  ).toBeDefined()
  wrapper.unmount()
})
