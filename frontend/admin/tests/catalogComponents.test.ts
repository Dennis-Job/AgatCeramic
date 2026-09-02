import { mount } from '@vue/test-utils'
import { describe, expect, test } from 'vitest'
import BaseSelect from '../src/components/BaseSelect.vue'
import BaseDialog from '../src/components/BaseDialog.vue'
import CollectionLoadingState from '../src/components/CollectionLoadingState.vue'
import PaginationControls from '../src/components/PaginationControls.vue'
import AttributeValueField from '../src/components/AttributeValueField.vue'
import { sortByLabel } from '../src/utils/alphabetical'

describe('alphabetical option ordering', () => {
  test('sorts Russian labels naturally without mutating the source collection', () => {
    const source = [
      { value: '3', label: 'Плитка 10' },
      { value: '1', label: 'ёж' },
      { value: '2', label: 'Ель' },
      { value: '4', label: 'Плитка 2' },
    ]

    expect(sortByLabel(source).map(option => option.label)).toEqual(['ёж', 'Ель', 'Плитка 2', 'Плитка 10'])
    expect(source.map(option => option.label)).toEqual(['Плитка 10', 'ёж', 'Ель', 'Плитка 2'])
  })

  test('shows product attribute choices alphabetically', async () => {
    const wrapper = mount(AttributeValueField, {
      props: {
        modelValue: '',
        accessibleName: 'Цвет',
        attribute: {
          id: 1,
          attribute_group_id: null,
          name: 'Цвет',
          slug: 'color',
          type: 'select',
          unit: null,
          is_filterable: true,
          is_required: false,
          is_visible_on_product_page: true,
          sort_order: 0,
          options: [
            { value: 'white', label: 'Белый', sort_order: 2 },
            { value: 'azure', label: 'Лазурный', sort_order: 0 },
            { value: 'beige', label: 'Бежевый', sort_order: 1 },
          ],
          created_at: '',
          updated_at: '',
        },
      },
    })

    await wrapper.get('button[aria-label="Цвет"]').trigger('click')
    expect(Array.from(document.body.querySelectorAll('[data-floating-select-menu] [data-select-option]')).map(button => button.textContent?.trim())).toEqual(['Бежевый', 'Белый', 'Лазурный'])
    wrapper.unmount()
  })
})

describe('CollectionLoadingState', () => {
  test('shows a visible, accessible loading message', () => {
    const wrapper = mount(CollectionLoadingState, {
      props: { label: 'Загрузка товаров…' },
    })

    const status = wrapper.get('[role="status"]')
    expect(status.text()).toBe('Загрузка товаров…')
    expect(status.classes()).not.toContain('sr-only')
    expect(status.attributes('aria-live')).toBe('polite')
    expect(status.get('svg').attributes('aria-hidden')).toBe('true')
  })
})

describe('BaseDialog', () => {
  test('does not close when a text-selection drag starts inside the panel and ends on the backdrop', async () => {
    const wrapper = mount(BaseDialog, {
      props: { open: true, labelledby: 'dialog-title' },
      slots: { default: '<h2 id="dialog-title">Редактирование</h2><input value="Текст для выделения">' },
    })

    await wrapper.get('input').trigger('pointerdown')
    await wrapper.get('.fixed.inset-0').trigger('pointerup')

    expect(wrapper.emitted('close')).toBeUndefined()
  })

  test('closes only after a complete primary-pointer action on the backdrop', async () => {
    const wrapper = mount(BaseDialog, {
      props: { open: true, labelledby: 'dialog-title' },
      slots: { default: '<h2 id="dialog-title">Подтверждение</h2>' },
    })
    const backdrop = wrapper.get('.fixed.inset-0')

    await backdrop.trigger('pointerdown')
    await backdrop.trigger('pointerup')

    expect(wrapper.emitted('close')).toEqual([[]])
  })
})

describe('BaseSelect', () => {
  test('teleports an opted-in menu outside clipping containers and keeps keyboard and outside-click handling', async () => {
    const wrapper = mount(BaseSelect, {
      attachTo: document.body,
      props: {
        modelValue: '',
        accessibleName: 'Текстура',
        teleportMenu: true,
        options: [
          { value: 'glossy', label: 'Глянцевая' },
          { value: 'matte', label: 'Матовая' },
        ],
      },
    })

    await wrapper.get('button[aria-label="Текстура"]').trigger('click')
    const menu = document.body.querySelector('.fixed.z-\\[70\\]')
    expect(menu?.textContent).toContain('Матовая')
    expect(wrapper.element.contains(menu)).toBe(false)
    expect(document.activeElement?.textContent?.trim()).toBe('Глянцевая')

    await (document.activeElement as HTMLElement).dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowDown', bubbles: true }))
    expect(document.activeElement?.textContent?.trim()).toBe('Матовая')
    await (document.activeElement as HTMLElement).dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }))
    await wrapper.vm.$nextTick()
    expect(document.body.querySelector('.fixed.z-\\[70\\]')).toBeNull()
    expect(document.activeElement?.getAttribute('aria-label')).toBe('Текстура')

    await wrapper.get('button[aria-label="Текстура"]').trigger('click')
    document.body.dispatchEvent(new MouseEvent('click', { bubbles: true }))
    await wrapper.vm.$nextTick()
    expect(document.body.querySelector('.fixed.z-\\[70\\]')).toBeNull()
    wrapper.unmount()
  })

  test('clears a selected value only when explicitly enabled', async () => {
    const wrapper = mount(BaseSelect, {
      attachTo: document.body,
      props: {
        modelValue: 'matte',
        accessibleName: 'Поверхность',
        clearable: true,
        options: [{ value: 'matte', label: 'Матовая' }],
      },
    })

    await wrapper.get('button[aria-label="Очистить выбор: Поверхность"]').trigger('click')
    expect(wrapper.emitted('update:modelValue')).toEqual([['']])
    expect(wrapper.emitted('change')).toEqual([['']])
    expect(document.activeElement?.getAttribute('aria-label')).toBe('Поверхность')

    await wrapper.setProps({ clearable: false })
    expect(wrapper.find('button[aria-label="Очистить выбор: Поверхность"]').exists()).toBe(false)
    wrapper.unmount()
  })

  test('filters options and emits the selected value with accessible names', async () => {
    const wrapper = mount(BaseSelect, {
      attachTo: document.body,
      props: {
        modelValue: '',
        accessibleName: 'Категория',
        searchable: true,
        searchPlaceholder: 'Начните вводить название категории',
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
    expect(search.attributes('placeholder')).toBe('Начните вводить название категории')
    await search.setValue('моз')
    expect(wrapper.text()).toContain('Мозаика')
    expect(wrapper.text()).not.toContain('Керамогранит')

    const option = wrapper.findAll('button').find((button) => button.text().includes('Мозаика'))
    expect(option).toBeDefined()
    await option!.trigger('click')
    expect(wrapper.emitted('update:modelValue')).toEqual([['2']])
    expect(wrapper.emitted('change')).toEqual([['2']])
    expect(trigger.attributes('aria-expanded')).toBe('false')
    expect(document.activeElement).toBe(trigger.element)
    wrapper.unmount()
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
    expect(document.activeElement?.getAttribute('aria-label')).toBe('Бренд')
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

  test('can render the visible range without a second live announcement', () => {
    const wrapper = mount(PaginationControls, {
      props: {
        meta: { current_page: 1, last_page: 1, per_page: 25, total: 3, from: 1, to: 3 },
        announce: false,
      },
    })

    expect(wrapper.text()).toContain('Показано 1–3 из 3')
    expect(wrapper.find('[role="status"]').exists()).toBe(false)
  })

  test('does not render for an empty collection', () => {
    const wrapper = mount(PaginationControls, {
      props: { meta: { current_page: 1, last_page: 1, per_page: 15, total: 0 } },
    })
    expect(wrapper.find('nav').exists()).toBe(false)
  })
})
