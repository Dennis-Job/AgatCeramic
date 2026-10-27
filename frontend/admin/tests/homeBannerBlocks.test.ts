import { mount } from '@vue/test-utils'
import { createPinia } from 'pinia'
import { describe, expect, it } from 'vitest'
import { reactive } from 'vue'
import UiField from '../src/components/ui/UiField.vue'
import HomePageBodyEditor from '../src/features/homepage/components/HomePageBodyEditor.vue'
import { blankBlockData } from '../src/features/pages/validation/blocks'

describe('independent homepage banner collections', () => {
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
