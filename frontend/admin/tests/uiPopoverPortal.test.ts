import { mount } from '@vue/test-utils'
import { defineComponent, h, nextTick, ref } from 'vue'
import { expect, test } from 'vitest'
import UiPopover from '../src/components/ui/UiPopover.vue'

test('a portalled popover stays open for its own actions and returns focus on Escape', async () => {
  const open = ref(true)
  const wrapper = mount(
    defineComponent(
      () => () =>
        h(
          UiPopover,
          {
            open: open.value,
            teleport: true,
            label: 'Действия',
            'onUpdate:open': (value: boolean) => {
              open.value = value
            },
          },
          {
            trigger: ({ trigger }: { trigger: Record<string, unknown> }) =>
              h('button', trigger, 'Открыть'),
            default: () =>
              h('button', { 'data-portal-action': '' }, 'Действие'),
          },
        ),
    ),
    { attachTo: document.body },
  )
  try {
    await nextTick()
    const panel = document.querySelector<HTMLElement>(
      '[data-floating-popover]',
    )!
    expect(panel.parentElement).toBe(document.body)
    const action = panel.querySelector<HTMLButtonElement>('button')!
    action.focus()
    action.click()
    await nextTick()
    expect(open.value).toBe(true)
    action.dispatchEvent(
      new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }),
    )
    await nextTick()
    await nextTick()
    expect(open.value).toBe(false)
    expect(document.activeElement?.textContent).toBe('Открыть')
  } finally {
    wrapper.unmount()
  }
})
