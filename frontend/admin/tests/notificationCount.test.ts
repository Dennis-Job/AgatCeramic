import { mount } from '@vue/test-utils'
import { expect, test } from 'vitest'
import UiNotificationCount from '../src/components/ui/UiNotificationCount.vue'

test('notification count hides zero and formats large counts without losing the full value', async () => {
  const wrapper = mount(UiNotificationCount, { props: { count: 0 } })
  expect(wrapper.find('span').exists()).toBe(false)
  await wrapper.setProps({ count: 9857 })
  expect(wrapper.text().replace(/\s/g, ' ')).toBe('9 857')
  expect(wrapper.attributes('aria-hidden')).toBe('true')
  await wrapper.setProps({ count: 150, max: 99 })
  expect(wrapper.text()).toBe('99+')
  expect(wrapper.attributes('title')).toBe('150')
})
