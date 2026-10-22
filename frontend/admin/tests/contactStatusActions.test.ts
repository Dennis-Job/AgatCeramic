import { mount } from '@vue/test-utils'
import { expect, test } from 'vitest'
import ContactStatusActions from '../src/features/contacts/components/ContactStatusActions.vue'

const statuses = [
  {
    code: 'new',
    name: 'Новое',
    is_terminal: false,
    allowed_transitions: ['processing', 'rejected'],
  },
  {
    code: 'processing',
    name: 'В обработке',
    is_terminal: false,
    allowed_transitions: ['awaiting_client', 'completed', 'rejected'],
  },
  {
    code: 'awaiting_client',
    name: 'Ожидает клиента',
    is_terminal: false,
    allowed_transitions: ['processing', 'completed', 'rejected'],
  },
  {
    code: 'completed',
    name: 'Завершено',
    is_terminal: true,
    allowed_transitions: [],
  },
  {
    code: 'rejected',
    name: 'Отклонено',
    is_terminal: true,
    allowed_transitions: [],
  },
]
test('new requests explain locked actions and emit an explicit allowed target', async () => {
  const wrapper = mount(ContactStatusActions, {
    props: {
      statuses,
      currentStatus: statuses[0],
      allowedStatuses: [statuses[1]!, statuses[4]!],
      ready: true,
      canManage: true,
      disabled: false,
    },
  })
  expect(wrapper.text()).toContain('Сначала начните обработку')
  for (const name of ['Ожидает клиента', 'Завершить']) {
    const button = wrapper
      .findAll('button')
      .find((item) => item.text().includes(name))!
    expect(button.attributes('disabled')).toBeDefined()
  }
  await wrapper
    .findAll('button')
    .find((button) => button.text() === 'Начать обработку')!
    .trigger('click')
  expect(wrapper.emitted('changeStatus')).toEqual([['processing']])
  expect(statuses[0]!.code).toBe('new')
})

test('terminal actions require confirmation and terminal requests cannot be changed', async () => {
  const wrapper = mount(ContactStatusActions, {
    props: {
      statuses,
      currentStatus: statuses[1],
      allowedStatuses: [statuses[2]!, statuses[3]!, statuses[4]!],
      ready: true,
      canManage: true,
      disabled: false,
    },
  })
  await wrapper
    .findAll('button')
    .find((button) => button.text() === 'Завершить')!
    .trigger('click')
  expect(wrapper.emitted('changeStatus')).toBeUndefined()
  expect(wrapper.text()).toContain('После подтверждения статус нельзя изменить')
  await wrapper
    .findAll('button')
    .find((button) => button.text() === 'Подтвердить завершение')!
    .trigger('click')
  expect(wrapper.emitted('changeStatus')).toEqual([['completed']])
  await wrapper.setProps({ currentStatus: statuses[3], allowedStatuses: [] })
  expect(wrapper.text()).toContain('Статус больше нельзя изменить')
  expect(wrapper.findAll('button')).toHaveLength(0)
})
