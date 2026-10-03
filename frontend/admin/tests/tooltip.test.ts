import { mount } from '@vue/test-utils'
import { afterEach, describe, expect, test, vi } from 'vitest'
import UiButton from '../src/components/ui/UiButton.vue'

afterEach(() => vi.useRealTimers())

describe('Action tooltips', () => {
  test('keyboard focus shows the action and Escape dismisses it without changing focus or existing help', async () => {
    const wrapper = mount(UiButton, {
      attachTo: document.body,
      attrs: {
        'aria-label': 'Копировать товар',
        'aria-describedby': 'existing-help',
      },
    })
    const button = wrapper.get('button')
    ;(button.element as HTMLButtonElement).focus()
    const tooltip = document.querySelector('[role="tooltip"]')!
    expect(tooltip.textContent).toBe('Копировать товар')
    expect(button.attributes('aria-describedby')).toBe(
      `existing-help ${tooltip.id}`,
    )
    window.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }))
    expect(document.querySelector('[role="tooltip"]')).toBeNull()
    expect(button.attributes('aria-describedby')).toBe('existing-help')
    expect(document.activeElement).toBe(button.element)
    wrapper.unmount()
  })

  test('hover is delayed, hoverable, and cleaned up on unmount', async () => {
    vi.useFakeTimers()
    const wrapper = mount(UiButton, {
      attachTo: document.body,
      props: { tooltip: 'Редактировать' },
    })
    await wrapper.trigger('pointerenter')
    vi.advanceTimersByTime(299)
    expect(document.querySelector('[role="tooltip"]')).toBeNull()
    vi.advanceTimersByTime(1)
    const tooltip = document.querySelector('[role="tooltip"]')!
    await wrapper.trigger('pointerleave')
    tooltip.dispatchEvent(new Event('pointerenter'))
    vi.advanceTimersByTime(200)
    expect(tooltip.isConnected).toBe(true)
    tooltip.dispatchEvent(new Event('pointerleave'))
    vi.advanceTimersByTime(150)
    expect(tooltip.isConnected).toBe(false)
    await wrapper.trigger('pointerenter')
    wrapper.unmount()
    vi.runAllTimers()
    expect(document.querySelector('[role="tooltip"]')).toBeNull()
  })

  test('reactive labels update, disabled/loading states hide it and opt-out suppresses it', async () => {
    const wrapper = mount(UiButton, {
      attachTo: document.body,
      props: { tooltip: 'Удалить' },
      attrs: { 'aria-label': 'Удалить товар' },
    })
    await wrapper.trigger('focus')
    await wrapper.setProps({ tooltip: 'Удалить изображение' })
    expect(document.querySelector('[role="tooltip"]')?.textContent).toBe(
      'Удалить изображение',
    )
    await wrapper.setProps({ loading: true })
    expect(document.querySelector('[role="tooltip"]')).toBeNull()
    await wrapper.trigger('focus')
    expect(document.querySelector('[role="tooltip"]')).toBeNull()
    await wrapper.setProps({ loading: false, tooltip: false })
    await wrapper.trigger('focus')
    expect(document.querySelector('[role="tooltip"]')).toBeNull()
    wrapper.unmount()
  })
})
