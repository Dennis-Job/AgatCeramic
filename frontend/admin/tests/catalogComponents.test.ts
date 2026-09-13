import { readFileSync } from 'node:fs'
import { mount } from '@vue/test-utils'
import { describe, expect, test } from 'vitest'
import UiSelect from '../src/components/ui/UiSelect.vue'
import UiDialog from '../src/components/ui/UiDialog.vue'
import UiLoadingState from '../src/components/ui/UiLoadingState.vue'
import UiPagination from '../src/components/ui/UiPagination.vue'
import AttributeValueField from '../src/features/products/components/AttributeValueField.vue'
import UiAlert from '../src/components/ui/UiAlert.vue'
import ConfirmDialog from '../src/components/shared/ConfirmDialog.vue'
import PageHeader from '../src/components/shared/PageHeader.vue'
import UiTable from '../src/components/ui/UiTable.vue'
import { sortByLabel } from '../src/utils/alphabetical'
import { attributeTypeLabel, attributeTypeOptions } from '../src/utils/attributeTypes'

const readSource = (relativePath: string) => readFileSync(new URL(relativePath, import.meta.url), 'utf8')

describe('products feature boundaries', () => {
  test('keeps product domain types in the feature types module', () => {
    const editorComposable = readSource('../src/features/products/composables/useProductEditor.ts')
    const contextComposable = readSource('../src/features/products/composables/useProductEditorContext.ts')
    const productService = readSource('../src/features/products/services/products.ts')
    const productTypes = readSource('../src/features/products/types/product.types.ts')

    expect(editorComposable).not.toMatch(/from\s+['"][^'"]+\.vue['"]/) // composables must not import components
    expect(contextComposable).not.toMatch(/from\s+['"][^'"]+\.vue['"]/) // context stays feature-layer only
    expect(productService).not.toMatch(/export\s+(?:interface|type)\s+Product/)
    expect(productTypes).toMatch(/export\s+type\s+AttributeDraftValue/)
    expect(productTypes).toMatch(/export\s+type\s+Product\s*=/)
  })

  test('uses UI-kit controls throughout product components', () => {
    const componentNames = [
      'ProductEditor.vue',
      'ProductEditorSteps.vue',
      'ProductMainSection.vue',
      'ProductAttributesSection.vue',
      'ProductImagesSection.vue',
      'ProductVariantsSection.vue',
      'ProductRelationsSection.vue',
      'ProductReviewSection.vue',
      'ProductImportDialog.vue',
      'ProductGroupImportDialog.vue',
      'ProductPriceStatusImportDialog.vue',
    ]

    for (const componentName of componentNames) {
      const source = readSource(`../src/features/products/components/${componentName}`)
      expect(source, componentName).not.toMatch(/<(?:button|select|textarea|table)\b/)
      const inputs = source.match(/<input\b[^>]*>/g) ?? []
      expect(inputs.every(input => /type="file"/.test(input)), componentName).toBe(true)
    }
  })
})

describe('attribute type labels', () => {
  test('provides Russian labels for every supported attribute type', () => {
    expect(attributeTypeOptions.map(option => option.label)).toEqual([
      'Строка',
      'Многострочный текст',
      'Целое число',
      'Десятичное число',
      'Да / нет',
      'Список',
      'Множественный список',
      'Дата',
    ])
    expect(attributeTypeLabel('decimal')).toBe('Десятичное число')
  })
})

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

describe('UiLoadingState', () => {
  test('shows a visible, accessible loading message', () => {
    const wrapper = mount(UiLoadingState, {
      props: { label: 'Загрузка товаров…' },
    })

    const status = wrapper.get('[role="status"]')
    expect(status.text()).toBe('Загрузка товаров…')
    expect(status.classes()).not.toContain('sr-only')
    expect(status.attributes('aria-live')).toBe('polite')
    expect(status.get('svg').attributes('aria-hidden')).toBe('true')
  })
})

describe('shared page patterns', () => {
  test('keeps an eyebrow and actions together in the reusable page header', () => {
    const wrapper = mount(PageHeader, {
      props: { eyebrow: 'Каталог', title: 'Длинное название раздела', description: 'Описание раздела.' },
      slots: { actions: '<button type="button">Добавить</button>' },
    })

    expect(wrapper.get('h1').text()).toBe('Длинное название раздела')
    expect(wrapper.text()).toContain('Каталог')
    expect(wrapper.get('button').text()).toBe('Добавить')
  })

  test('provides a labelled, scrollable table shell with an optional minimum width', () => {
    const wrapper = mount(UiTable, {
      props: { label: 'Список сотрудников', minWidth: 'min-w-[680px]', tableClass: 'admin-table-employees' },
      slots: { default: '<tbody><tr><td>Иван</td></tr></tbody>' },
    })

    expect(wrapper.get('table').attributes('aria-label')).toBe('Список сотрудников')
    expect(wrapper.get('[role="region"]').attributes('aria-label')).toBe('Список сотрудников')
    expect(wrapper.get('[role="region"]').classes()).toContain('[contain:paint]')
    expect(wrapper.get('[role="region"]').classes()).toContain('focus-visible:ring-2')
    expect(wrapper.get('[role="region"]').classes()).toContain('focus-visible:ring-inset')
    expect(wrapper.get('table').classes()).toContain('min-w-[680px]')
    expect(wrapper.get('table').classes()).toContain('admin-table-employees')
    expect(wrapper.text()).toContain('Иван')
  })
})

describe('shared feedback and destructive confirmation', () => {
  test('announces errors as alerts', () => {
    const wrapper = mount(UiAlert, { slots: { default: 'Не удалось сохранить изменения.' } })

    expect(wrapper.get('[role="alert"]').text()).toBe('Не удалось сохранить изменения.')
    expect(wrapper.get('[role="alert"]').attributes('aria-live')).toBe('assertive')
  })

  test('uses the shared dialog controls for destructive confirmation', async () => {
    const wrapper = mount(ConfirmDialog, {
      props: { open: true, title: 'Удалить запись?', description: 'Отменить нельзя.' },
    })

    await wrapper.get('button.bg-error-500').trigger('click')
    expect(wrapper.emitted('confirm')).toEqual([[]])
    await wrapper.get('button.text-gray-600').trigger('click')
    expect(wrapper.emitted('close')).toEqual([[]])
  })
})

describe('UiDialog', () => {
  test('does not close when a text-selection drag starts inside the panel and ends on the backdrop', async () => {
    const wrapper = mount(UiDialog, {
      props: { open: true, labelledby: 'dialog-title' },
      slots: { default: '<h2 id="dialog-title">Редактирование</h2><input value="Текст для выделения">' },
    })

    await wrapper.get('input').trigger('pointerdown')
    await wrapper.get('.fixed.inset-0').trigger('pointerup')

    expect(wrapper.emitted('close')).toBeUndefined()
  })

  test('closes only after a complete primary-pointer action on the backdrop', async () => {
    const wrapper = mount(UiDialog, {
      props: { open: true, labelledby: 'dialog-title' },
      slots: { default: '<h2 id="dialog-title">Подтверждение</h2>' },
    })
    const backdrop = wrapper.get('.fixed.inset-0')

    await backdrop.trigger('pointerdown')
    await backdrop.trigger('pointerup')

    expect(wrapper.emitted('close')).toEqual([[]])
  })
})

describe('UiSelect', () => {
  test('exposes the current selection to assistive technology while closed', async () => {
    const wrapper = mount(UiSelect, {
      props: {
        modelValue: 'new',
        accessibleName: 'Статус заказа',
        options: [
          { value: 'new', label: 'Новый' },
          { value: 'processing', label: 'В обработке' },
        ],
      },
    })

    const trigger = wrapper.get('button[aria-label="Статус заказа"]')
    const descriptionId = trigger.attributes('aria-describedby')
    expect(descriptionId).toBeTruthy()
    expect(wrapper.get(`#${descriptionId}`).text()).toBe('Текущее значение: Новый')

    await wrapper.setProps({ modelValue: 'processing' })
    expect(wrapper.get(`#${descriptionId}`).text()).toBe('Текущее значение: В обработке')
  })

  test('teleports an opted-in menu outside clipping containers and keeps keyboard and outside-click handling', async () => {
    const wrapper = mount(UiSelect, {
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
    const wrapper = mount(UiSelect, {
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
    const wrapper = mount(UiSelect, {
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
    const wrapper = mount(UiSelect, {
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

describe('UiPagination', () => {
  test('announces the range, changes pages, and guards unavailable navigation', async () => {
    const wrapper = mount(UiPagination, {
      props: {
        meta: { current_page: 2, last_page: 3, per_page: 15, total: 31, from: 16, to: 30 },
      },
    })

    expect(wrapper.get('nav').attributes('aria-label')).toBe('Пагинация: страница 2 из 3')
    expect(wrapper.get('[aria-label="Текущая страница 2 из 3"]').text()).toBe('2 / 3')
    expect(wrapper.get('[role="status"]').text()).toBe('Показано 16–30 из 31')
    await wrapper.get('button[aria-label="Предыдущая страница"]').trigger('click')
    await wrapper.get('button[aria-label="Следующая страница"]').trigger('click')
    expect(wrapper.emitted('change')).toEqual([[1], [3]])

    await wrapper.setProps({ loading: true })
    expect(wrapper.get('button[aria-label="Предыдущая страница"]').attributes()).toHaveProperty('disabled')
    expect(wrapper.get('button[aria-label="Следующая страница"]').attributes()).toHaveProperty('disabled')
  })

  test('can render the visible range without a second live announcement', () => {
    const wrapper = mount(UiPagination, {
      props: {
        meta: { current_page: 1, last_page: 1, per_page: 25, total: 3, from: 1, to: 3 },
        announce: false,
      },
    })

    expect(wrapper.text()).toContain('Показано 1–3 из 3')
    expect(wrapper.find('[role="status"]').exists()).toBe(false)
  })

  test('does not render for an empty collection', () => {
    const wrapper = mount(UiPagination, {
      props: { meta: { current_page: 1, last_page: 1, per_page: 15, total: 0 } },
    })
    expect(wrapper.find('nav').exists()).toBe(false)
  })
})
