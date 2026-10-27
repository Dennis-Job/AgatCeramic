import { mount } from '@vue/test-utils'
import { createPinia } from 'pinia'
import { describe, expect, it } from 'vitest'
import { reactive } from 'vue'
import UiField from '../src/components/ui/UiField.vue'
import HomePageBodyEditor from '../src/features/homepage/components/HomePageBodyEditor.vue'
import { blankBlockData } from '../src/features/pages/validation/blocks'

describe('independent homepage banner collections', () => {
  it('edits an optional grid banner link without changing the menu link', async () => {
    const content = reactive(blankBlockData())
    content.materials.items = [{ ...content.categories.items[0]! }]
    const menuBefore = JSON.stringify(content.materials)
    const wrapper = mount(HomePageBodyEditor, {
      props: { section: 'categories', content },
      global: { plugins: [createPinia()] },
    })
    const links = () =>
      wrapper
        .findAllComponents(UiField)
        .filter((field) => field.props('label') === 'Ссылка баннера')
    expect(links()).toHaveLength(1)
    expect(links()[0]!.get('input').element.value).toBe('')
    expect(content.categories.items[0]).not.toHaveProperty('link_url')
    await links()[0]!.get('input').setValue('/catalog?category=tile')
    expect(content.categories.items[0]).toHaveProperty(
      'link_url',
      '/catalog?category=tile',
    )
    await wrapper.setProps({ section: 'materials' })
    expect(links()).toHaveLength(1)
    expect(links()[0]!.get('input').element.value).toBe('')
    expect(JSON.stringify(content.materials)).toBe(menuBefore)
    await wrapper.setProps({ section: 'categories' })
    expect(links()[0]!.get('input').element.value).toBe(
      '/catalog?category=tile',
    )
    wrapper.unmount()
  })

  it('edits each menu banner note and destination separately while retaining legacy text', async () => {
    const content = reactive(blankBlockData())
    content.materials.note = 'Прежнее общее примечание'
    const base = content.categories.items[0]!
    content.materials.items = [
      {
        ...base,
        id: 'first',
        note: 'Первое примечание',
        link_url: '/catalog/first',
      },
      {
        ...base,
        id: 'second',
        note: 'Второе примечание',
        link_url: '/catalog/second',
      },
    ]
    const wrapper = mount(HomePageBodyEditor, {
      props: { section: 'materials', content },
      global: { plugins: [createPinia()] },
    })
    const notes = () =>
      wrapper
        .findAllComponents(UiField)
        .filter((field) => field.props('label') === 'Примечание под баннером')
    const links = () =>
      wrapper
        .findAllComponents(UiField)
        .filter((field) => field.props('label') === 'Ссылка баннера')
    expect(notes()).toHaveLength(2)
    expect(notes()[0]!.get('textarea').element.value).toBe('Первое примечание')
    expect(notes()[1]!.get('textarea').element.value).toBe('Второе примечание')
    await notes()[0]!.get('textarea').setValue('Новое первое примечание')
    await links()[1]!.get('input').setValue('https://example.test/second')
    expect(content.materials.items[0]).toMatchObject({
      note: 'Новое первое примечание',
      link_url: '/catalog/first',
    })
    expect(content.materials.items[1]).toMatchObject({
      note: 'Второе примечание',
      link_url: 'https://example.test/second',
    })
    expect(content.materials.note).toBe('Прежнее общее примечание')
    await notes()[0]!.get('textarea').setValue('')
    expect(content.materials.items[0]!.note).toBe('')
    expect(content.materials.items[1]!.note).toBe('Второе примечание')
    wrapper.unmount()
  })

  it('edits menu items without changing the banner grid and retains edits across sections', async () => {
    const content = reactive(blankBlockData())
    content.categories.items[0]!.name = 'Баннер сетки'
    content.materials = {
      ...content.materials,
      items: [{ ...content.categories.items[0]!, name: 'Баннер меню' }],
    }
    const wrapper = mount(HomePageBodyEditor, {
      props: { section: 'materials', content },
      global: { plugins: [createPinia()] },
    })
    const names = () =>
      wrapper
        .findAllComponents(UiField)
        .filter((field) => field.props('label') === 'Название')
    expect(names()).toHaveLength(1)
    expect(names()[0]!.get('input').element.value).toBe('Баннер меню')
    await names()[0]!.get('input').setValue('Новый баннер меню')
    expect(content.materials.items[0]!.name).toBe('Новый баннер меню')
    expect(content.categories.items[0]!.name).toBe('Баннер сетки')

    await wrapper.setProps({ section: 'categories' })
    expect(names()[0]!.get('input').element.value).toBe('Баннер сетки')
    await names()[0]!.get('input').setValue('Новый баннер сетки')
    await wrapper.setProps({ section: 'materials' })
    expect(names()[0]!.get('input').element.value).toBe('Новый баннер меню')
    expect(content.categories.items[0]!.name).toBe('Новый баннер сетки')
    wrapper.unmount()
  })

  it('creates a menu without reusing the grid list', () => {
    const first = blankBlockData()
    const second = blankBlockData()
    expect(first.materials.items).toEqual([])
    expect(first.materials.items).not.toBe(first.categories.items)
    expect(first.materials.items).not.toBe(second.materials.items)
  })
})
