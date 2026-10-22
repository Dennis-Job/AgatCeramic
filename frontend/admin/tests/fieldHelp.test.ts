import { mount } from '@vue/test-utils'
import { afterEach, expect, test, vi } from 'vitest'
import UiField from '../src/components/ui/UiField.vue'

const help =
  'Служебный адрес для уведомлений о новых заявках. На сайте не публикуется.'
const wrappers: ReturnType<typeof mount>[] = []
function field(error?: string) {
  const wrapper = mount(UiField, {
    attachTo: document.body,
    props: { label: 'Email для уведомлений', help, error },
    slots: { default: '<input type="email" />' },
  })
  wrappers.push(wrapper)
  return wrapper
}
afterEach(() => {
  wrappers.splice(0).forEach((wrapper) => wrapper.unmount())
  vi.useRealTimers()
})

test('field help is hidden until requested and its button does not replace the input label', () => {
  const wrapper = field()
  expect(wrapper.text()).not.toContain(help)
  const button = wrapper.get(
    'button[aria-label="Подсказка: Email для уведомлений"]',
  )
  expect(button.attributes('type')).toBe('button')
  expect(button.element.closest('label')).toBeNull()
  const input = wrapper.get('input').element as HTMLInputElement
  expect(input.labels?.length).toBe(1)
  expect(input.labels?.[0]?.textContent).toBe('Email для уведомлений')
  expect(document.querySelector('[role="tooltip"]')).toBeNull()
})

test('keyboard focus reveals the help and Escape dismisses it without moving focus', async () => {
  const wrapper = field()
  const button = wrapper.get('button')
  ;(button.element as HTMLButtonElement).focus()
  const tooltip = document.querySelector('[role="tooltip"]')!
  expect(tooltip?.textContent).toBe(help)
  expect(button.attributes('aria-describedby')).toBe(tooltip.id)
  window.dispatchEvent(
    new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }),
  )
  expect(document.querySelector('[role="tooltip"]')).toBeNull()
  expect(document.activeElement).toBe(button.element)
  expect(button.attributes('aria-describedby')).toBeUndefined()
  await wrapper.get('input').trigger('focus')
})

test('stationary hover shows help and keeps it open while the pointer enters the popup', async () => {
  vi.useFakeTimers()
  const wrapper = field()
  const button = wrapper.get('button')
  await button.trigger('pointerenter', { pointerType: 'mouse' })
  vi.advanceTimersByTime(300)
  const tooltip = document.querySelector('[role="tooltip"]')!
  expect(tooltip?.textContent).toBe(help)
  await button.trigger('pointerleave')
  tooltip.dispatchEvent(new Event('pointerenter'))
  vi.advanceTimersByTime(500)
  expect(tooltip.isConnected).toBe(true)
  tooltip.dispatchEvent(new Event('pointerleave'))
  vi.advanceTimersByTime(150)
  expect(document.querySelector('[role="tooltip"]')).toBeNull()
})

test('tap opens and closes help and outside interaction dismisses it', async () => {
  const wrapper = field()
  const button = wrapper.get('button')
  ;(button.element as HTMLButtonElement).focus()
  await button.trigger('click')
  expect(document.querySelector('[role="tooltip"]')?.textContent).toBe(help)
  await button.trigger('click')
  expect(document.querySelector('[role="tooltip"]')).toBeNull()
  await button.trigger('click')
  expect(document.querySelector('[role="tooltip"]')).not.toBeNull()
  wrapper
    .get('input')
    .element.dispatchEvent(new Event('pointerdown', { bubbles: true }))
  expect(document.querySelector('[role="tooltip"]')).toBeNull()
})

test('validation errors remain visible and changing or removing help cleans up its popup', async () => {
  const wrapper = field('Введите корректный email.')
  expect(wrapper.get('[role="alert"]').text()).toBe('Введите корректный email.')
  ;(wrapper.get('button').element as HTMLButtonElement).focus()
  expect(document.querySelector('[role="tooltip"]')?.textContent).toBe(help)
  await wrapper.setProps({ help: 'Новая подсказка' })
  expect(document.querySelector('[role="tooltip"]')?.textContent).toBe(
    'Новая подсказка',
  )
  await wrapper.setProps({ help: undefined })
  expect(wrapper.find('button').exists()).toBe(false)
  expect(document.querySelector('[role="tooltip"]')).toBeNull()
  expect(wrapper.get('[role="alert"]').text()).toBe('Введите корректный email.')
})
