import { mount } from '@vue/test-utils'
import { describe, expect, test } from 'vitest'
import BaseSelect from '../src/components/BaseSelect.vue'
import PaginationControls from '../src/components/PaginationControls.vue'

describe('BaseSelect', () => {
  test('filters options and emits the selected value with accessible names', async () => {
    const wrapper = mount(BaseSelect, {
      props: {
        modelValue: '',
        accessibleName: 'Категория',
        searchable: true,
        options: [
          { value: '1', label: 'Керамогранит' },
          { value: '2', label: 'Мозаика' },
        ],
      },
    })

    const trigger = wrapper.get('button[aria-label="Категория"]')
    expect(trigger.attributes('aria-expanded')).toBe('false')
    await trigger.trigger('click')
    expect(trigger.attributes('aria-expanded')).toBe('true')

    const search = wrapper.get('input[aria-label="Поиск: Категория"]')
    await search.setValue('моз')
    expect(wrapper.text()).toContain('Мозаика')
    expect(wrapper.text()).not.toContain('Керамогранит')

    const option = wrapper.findAll('button').find((button) => button.text().includes('Мозаика'))
    expect(option).toBeDefined()
    await option!.trigger('click')
    expect(wrapper.emitted('update:modelValue')).toEqual([['2']])
    expect(wrapper.emitted('change')).toEqual([['2']])
    expect(trigger.attributes('aria-expanded')).toBe('false')
  })

  test('closes with Escape and reports an empty search result', async () => {
    const wrapper = mount(BaseSelect, {
      attachTo: document.body,
      props: {
        modelValue: '',
        accessibleName: 'Бренд',
        searchable: true,
        options: [{ value: '1', label: 'Kerama Marazzi' }],
      },
    })

    await wrapper.get('button[aria-label="Бренд"]').trigger('click')
    await wrapper.get('input[type="search"]').setValue('нет такого')
    expect(wrapper.text()).toContain('Ничего не найдено')
    await wrapper.get('input[type="search"]').trigger('keydown', { key: 'Escape' })
    expect(wrapper.find('input[type="search"]').exists()).toBe(false)
    wrapper.unmount()
  })
})

describe('PaginationControls', () => {
  test('announces the range, changes pages, and guards unavailable navigation', async () => {
    const wrapper = mount(PaginationControls, {
      props: {
        meta: { current_page: 2, last_page: 3, per_page: 15, total: 31, from: 16, to: 30 },
      },
    })

    expect(wrapper.get('nav').attributes('aria-label')).toBe('Пагинация: страница 2 из 3')
    expect(wrapper.get('[role="status"]').text()).toBe('Показано 16–30 из 31')
    await wrapper.get('button[aria-label="Предыдущая страница"]').trigger('click')
    await wrapper.get('button[aria-label="Следующая страница"]').trigger('click')
    expect(wrapper.emitted('change')).toEqual([[1], [3]])

    await wrapper.setProps({ loading: true })
    expect(wrapper.get('button[aria-label="Предыдущая страница"]').attributes()).toHaveProperty('disabled')
    expect(wrapper.get('button[aria-label="Следующая страница"]').attributes()).toHaveProperty('disabled')
  })

  test('does not render for an empty collection', () => {
    const wrapper = mount(PaginationControls, {
      props: { meta: { current_page: 1, last_page: 1, per_page: 15, total: 0 } },
    })
    expect(wrapper.find('nav').exists()).toBe(false)
  })
})
