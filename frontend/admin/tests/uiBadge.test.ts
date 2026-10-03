import { mount } from '@vue/test-utils'
import { describe, expect, test } from 'vitest'
import UiBadge from '../src/components/ui/UiBadge.vue'

describe('UiBadge', () => {
  test('uses the blue-light palette for the additional tone', () => {
    const wrapper = mount(UiBadge, {
      props: { tone: 'additional' },
      slots: { default: 'Дополнительный' },
    })

    expect(wrapper.text()).toBe('Дополнительный')
    expect(wrapper.classes()).toContain('bg-blue-light-50')
    expect(wrapper.classes()).toContain('text-blue-light-700')
  })
})
