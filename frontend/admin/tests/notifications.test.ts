import { afterEach, beforeEach, describe, expect, test, vi } from 'vitest'
import { mount, flushPromises, type VueWrapper } from '@vue/test-utils'
import { nextTick } from 'vue'
import UiNotification from '../src/components/ui/UiNotification.vue'

let wrapper: VueWrapper | undefined
const card = () => document.querySelector<HTMLElement>('[data-notification]')!
beforeEach(() => {
  vi.useFakeTimers()
  document.body.innerHTML =
    '<button id="opener">Экспорт</button><aside id="admin-notifications"></aside>'
  document.getElementById('opener')?.focus()
})
afterEach(() => {
  wrapper?.unmount()
  wrapper = undefined
  vi.useRealTimers()
  document.body.innerHTML = ''
})
async function notify(tone: 'success' | 'error' = 'success') {
  wrapper = mount(UiNotification, {
    props: { tone, duration: 1000 },
    slots: { default: 'Файл готов.' },
  })
  await flushPromises()
}
describe('notification lifetime', () => {
  test('restores a persistent popover trigger after its action control is removed', async () => {
    document.body.innerHTML =
      '<button id="trigger" aria-controls="menu">Пользователь</button><section id="menu" data-floating-popover><button id="logout">Выйти</button></section><aside id="admin-notifications"></aside>'
    document.getElementById('logout')?.focus()
    await notify('error')
    document.getElementById('menu')?.remove()
    const close = card().querySelector('button')!
    close.focus()
    close.dispatchEvent(
      new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }),
    )
    await nextTick()
    expect(document.activeElement?.id).toBe('trigger')
  })
  test('announces success politely and dismisses once after its duration', async () => {
    await notify()
    expect(card().getAttribute('role')).toBe('status')
    expect(card().getAttribute('aria-live')).toBe('polite')
    await vi.advanceTimersByTimeAsync(1000)
    expect(card().style.display).toBe('none')
    expect(wrapper?.emitted('dismiss')).toHaveLength(1)
  })
  test('retains an error until manual dismissal and cleans its timer on unmount', async () => {
    await notify('error')
    expect(card().getAttribute('role')).toBe('alert')
    expect(card().getAttribute('aria-live')).toBe('assertive')
    await vi.advanceTimersByTimeAsync(10000)
    expect(card().style.display).not.toBe('none')
    card().querySelector('button')?.click()
    await nextTick()
    expect(wrapper?.emitted('dismiss')).toHaveLength(1)
    wrapper?.unmount()
    wrapper = undefined
    expect(document.querySelector('[data-notification]')).toBeNull()
    expect(vi.getTimerCount()).toBe(0)
  })
  test('hover pauses and resumes the remaining time', async () => {
    await notify()
    await vi.advanceTimersByTimeAsync(600)
    card().dispatchEvent(new MouseEvent('mouseenter'))
    await vi.advanceTimersByTimeAsync(5000)
    expect(card().style.display).not.toBe('none')
    card().dispatchEvent(new MouseEvent('mouseleave'))
    await vi.advanceTimersByTimeAsync(399)
    expect(card().style.display).not.toBe('none')
    await vi.advanceTimersByTimeAsync(1)
    expect(card().style.display).toBe('none')
  })
  test('focused content survives auto-dismiss and Escape restores its opener', async () => {
    await notify()
    const close = card().querySelector('button')!
    close.focus()
    await vi.advanceTimersByTimeAsync(10000)
    expect(card().style.display).not.toBe('none')
    close.dispatchEvent(
      new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }),
    )
    await nextTick()
    expect(card().style.display).toBe('none')
    expect(document.activeElement?.id).toBe('opener')
  })
})
