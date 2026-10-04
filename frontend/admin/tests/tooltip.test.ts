import { mount } from '@vue/test-utils'
import { afterEach, describe, expect, test, vi } from 'vitest'
import UiButton from '../src/components/ui/UiButton.vue'

afterEach(() => vi.useRealTimers())

describe('Action tooltips', () => {
  test('accessible names do not create tooltips on focus or hover by default', async () => {
    vi.useFakeTimers()
    const wrapper = mount(UiButton, {
      attachTo: document.body,
      attrs: { 'aria-label': 'Закрыть окно' },
    })
    const button = wrapper.get('button')
    ;(button.element as HTMLButtonElement).focus()
    await wrapper.trigger('pointerenter')
    await wrapper.trigger('pointermove', { pointerType: 'mouse' })
    vi.advanceTimersByTime(500)
    expect(document.querySelector('[role="tooltip"]')).toBeNull()
    wrapper.unmount()
  })

  test('only pointer hover shows the action and Escape preserves existing help', async () => {
    vi.useFakeTimers()
    const wrapper = mount(UiButton, {
      attachTo: document.body,
      props: { tooltip: 'Копировать товар' },
      attrs: { 'aria-describedby': 'existing-help' },
    })
    const button = wrapper.get('button')
    ;(button.element as HTMLButtonElement).focus()
    expect(document.querySelector('[role="tooltip"]')).toBeNull()
    await wrapper.trigger('pointerenter')
    await wrapper.trigger('pointermove', { pointerType: 'mouse' })
    vi.advanceTimersByTime(299)
    expect(document.querySelector('[role="tooltip"]')).toBeNull()
    vi.advanceTimersByTime(1)
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

  test('tooltip can be hovered, then hides outside both targets and activation leaves no stale tooltip', async () => {
    vi.useFakeTimers()
    const wrapper = mount(UiButton, {
      attachTo: document.body,
      props: { tooltip: 'Редактировать' },
    })
    await wrapper.trigger('pointerenter')
    await wrapper.trigger('pointermove', { pointerType: 'mouse' })
    vi.advanceTimersByTime(300)
    const tooltip = document.querySelector('[role="tooltip"]')!
    await wrapper.trigger('pointerleave')
    vi.advanceTimersByTime(149)
    expect(tooltip.isConnected).toBe(true)
    tooltip.dispatchEvent(new Event('pointerenter'))
    vi.advanceTimersByTime(300)
    expect(tooltip.isConnected).toBe(true)
    tooltip.dispatchEvent(new Event('pointerleave'))
    vi.advanceTimersByTime(150)
    expect(document.querySelector('[role="tooltip"]')).toBeNull()

    await wrapper.trigger('pointerenter')
    await wrapper.trigger('pointermove', { pointerType: 'mouse' })
    vi.advanceTimersByTime(300)
    await wrapper.trigger('click')
    expect(document.querySelector('[role="tooltip"]')).toBeNull()
    await wrapper.trigger('pointerenter')
    await wrapper.trigger('pointermove', { pointerType: 'mouse' })
    vi.advanceTimersByTime(300)
    expect(document.querySelector('[role="tooltip"]')).toBeNull()

    await wrapper.trigger('pointerleave')
    vi.advanceTimersByTime(150)
    await wrapper.trigger('pointerenter')
    await wrapper.trigger('pointermove', { pointerType: 'mouse' })
    vi.advanceTimersByTime(300)
    expect(document.querySelector('[role="tooltip"]')).not.toBeNull()
    wrapper.unmount()
    vi.runAllTimers()
    expect(document.querySelector('[role="tooltip"]')).toBeNull()
  })

  test('reactive labels update, disabled/loading states hide it and opt-out suppresses it', async () => {
    vi.useFakeTimers()
    const wrapper = mount(UiButton, {
      attachTo: document.body,
      props: { tooltip: 'Удалить' },
      attrs: { 'aria-label': 'Удалить товар' },
    })
    await wrapper.trigger('pointerenter')
    await wrapper.trigger('pointermove', { pointerType: 'mouse' })
    vi.advanceTimersByTime(300)
    await wrapper.setProps({ tooltip: 'Удалить изображение' })
    expect(document.querySelector('[role="tooltip"]')?.textContent).toBe(
      'Удалить изображение',
    )
    await wrapper.setProps({ loading: true })
    expect(document.querySelector('[role="tooltip"]')).toBeNull()
    await wrapper.setProps({ loading: false, tooltip: false })
    await wrapper.trigger('pointerenter')
    await wrapper.trigger('pointermove', { pointerType: 'mouse' })
    vi.advanceTimersByTime(300)
    expect(document.querySelector('[role="tooltip"]')).toBeNull()
    wrapper.unmount()
  })
})
