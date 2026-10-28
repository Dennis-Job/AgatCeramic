import { mount } from '@vue/test-utils'
import { reactive } from 'vue'
import { expect, test } from 'vitest'
import SiteChromeEditor from '../src/features/appearance/components/SiteChromeEditor.vue'
import type { SiteAppearance } from '../src/features/appearance/types/appearance.types'

function editor(navigation = [{ label: 'Каталог', to: '/catalog' }]) {
  const content = reactive({
    header: {
      navigation,
      topbar_left: '',
      topbar_right: '',
      logo_media_id: null,
      logo_alt: '',
      logo_url: null,
    },
    footer: { explore_links: [], message: {} },
  } as unknown as SiteAppearance)
  const wrapper = mount(SiteChromeEditor, {
    props: { content, section: 'header' },
    global: { stubs: { MediaImageEditor: true } },
  })
  return { wrapper, content }
}

test('reversing submenu mode restores unsaved parent URL and children and serializes only active fields', async () => {
  const { wrapper, content } = editor()
  const parent = () => wrapper.get('[aria-label="Пункт меню 1"]')
  await parent().findAll('input')[1]!.setValue('/unsaved-catalog')
  const toggle = () => parent().get('[role="switch"]')
  expect(toggle().attributes('aria-checked')).toBe('false')
  await toggle().trigger('click')
  expect(content.header.navigation).toEqual([
    { label: 'Каталог', children: [{ label: '', to: '' }] },
  ])
  const child = () => wrapper.get('[aria-label="Подпункт 1 пункта меню 1"]')
  await child().findAll('input')[0]!.setValue('Весь каталог')
  await child().findAll('input')[1]!.setValue('/all')
  await toggle().trigger('click')
  expect(content.header.navigation).toEqual([
    { label: 'Каталог', to: '/unsaved-catalog' },
  ])
  await toggle().trigger('click')
  expect(child().findAll('input')[0]!.element.value).toBe('Весь каталог')
  expect(content.header.navigation).toEqual([
    { label: 'Каталог', children: [{ label: 'Весь каталог', to: '/all' }] },
  ])
  await wrapper.setProps({ section: 'footer' })
  await wrapper.setProps({ section: 'header' })
  await toggle().trigger('click')
  expect(content.header.navigation).toEqual([
    { label: 'Каталог', to: '/unsaved-catalog' },
  ])
  wrapper.unmount()
})

test('submenu children can be added, reordered and removed with the group moving as one parent', async () => {
  const { wrapper, content } = editor([
    { label: 'Каталог', to: '/catalog' },
    { label: 'Контакты', to: '/contacts' },
  ])
  await wrapper.get('[role="switch"]').trigger('click')
  const first = wrapper.get('[aria-label="Подпункт 1 пункта меню 1"]')
  await first.findAll('input')[0]!.setValue('Керамогранит')
  await first.findAll('input')[1]!.setValue('/tiles')
  await wrapper
    .get('[aria-label="Добавить подпункт в пункт меню 1"]')
    .trigger('click')
  const second = wrapper.get('[aria-label="Подпункт 2 пункта меню 1"]')
  await second.findAll('input')[0]!.setValue('Мозаика')
  await second.findAll('input')[1]!.setValue('/mosaic')
  await wrapper
    .get('[aria-label="Поднять подпункт 2 пункта меню 1"]')
    .trigger('click')
  await wrapper.get('[aria-label="Опустить пункт меню 1"]').trigger('click')
  expect(content.header.navigation).toEqual([
    { label: 'Контакты', to: '/contacts' },
    {
      label: 'Каталог',
      children: [
        { label: 'Мозаика', to: '/mosaic' },
        { label: 'Керамогранит', to: '/tiles' },
      ],
    },
  ])
  await wrapper
    .get('[aria-label="Удалить подпункт 1 пункта меню 2"]')
    .trigger('click')
  expect(content.header.navigation[1]).toEqual({
    label: 'Каталог',
    children: [{ label: 'Керамогранит', to: '/tiles' }],
  })
  expect(
    wrapper
      .get('[aria-label="Удалить подпункт 1 пункта меню 2"]')
      .attributes('disabled'),
  ).toBeDefined()
  wrapper.unmount()
})

test('navigation controls respect disabled state including switches, input and structural actions', async () => {
  const { wrapper, content } = editor()
  await wrapper.get('[role="switch"]').trigger('click')
  const before = JSON.stringify(content.header.navigation)
  await wrapper.setProps({ disabled: true })
  const navigation = wrapper.get(
    '[aria-labelledby="appearance-navigation-title"]',
  )
  for (const control of navigation.findAll('button, input')) {
    if (control.attributes('aria-label')?.startsWith('Подсказка:')) continue
    expect(control.attributes('disabled')).toBeDefined()
  }
  await wrapper
    .get('[aria-label="Добавить подпункт в пункт меню 1"]')
    .trigger('click')
  await wrapper.get('[role="switch"]').trigger('click')
  expect(JSON.stringify(content.header.navigation)).toBe(before)
  wrapper.unmount()
})

test('new parents start as links and both parent and child lists stop at sixteen', async () => {
  const { wrapper, content } = editor()
  for (let index = 1; index < 16; index++) {
    await wrapper.get('[aria-label="Добавить пункт меню"]').trigger('click')
  }
  expect(content.header.navigation[15]).toEqual({ label: '', to: '' })
  expect(
    wrapper.get('[aria-label="Добавить пункт меню"]').attributes('disabled'),
  ).toBeDefined()
  await wrapper.get('[role="switch"]').trigger('click')
  for (let index = 1; index < 16; index++) {
    await wrapper
      .get('[aria-label="Добавить подпункт в пункт меню 1"]')
      .trigger('click')
  }
  expect(wrapper.findAll('[aria-label^="Подпункт "]')).toHaveLength(16)
  expect(
    wrapper
      .get('[aria-label="Добавить подпункт в пункт меню 1"]')
      .attributes('disabled'),
  ).toBeDefined()
  wrapper.unmount()
})
