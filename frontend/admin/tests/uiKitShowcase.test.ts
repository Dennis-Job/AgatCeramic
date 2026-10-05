import { readdirSync, readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { mount } from '@vue/test-utils'
import { describe, expect, test } from 'vitest'
import UiKitShowcase from '../src/components/shared/UiKitShowcase.vue'

describe('UiKitShowcase', () => {
  test('renders every reusable UI and shared component in its inventory', () => {
    const uiDirectory = resolve(process.cwd(), 'src/components/ui')
    const uiComponents = readdirSync(uiDirectory)
      .filter((file) => file.startsWith('Ui') && file.endsWith('.vue'))
      .map((file) => file.replace(/\.vue$/, ''))
      .sort()
    const sharedComponents = ['AuthCard', 'ConfirmDialog', 'PageHeader']
    const wrapper = mount(UiKitShowcase)
    const inventory = wrapper.get('[data-ui-kit-section="inventory"]').text()

    for (const component of [...uiComponents, ...sharedComponents]) {
      expect(inventory, component).toContain(component)
    }
    expect(uiComponents).toHaveLength(23)
    expect(wrapper.findAll('[data-ui-kit-section]')).toHaveLength(13)
    expect(
      wrapper.get('[data-ui-kit-section="tabs"] [role="tablist"]').exists(),
    ).toBe(true)
    const demoTabs = wrapper.findAll(
      '[data-ui-kit-section="tabs"] [role="tab"]',
    )
    expect(demoTabs.map((tab) => tab.text())).toEqual([
      'Вкладка 1',
      'Вкладка 2',
      'Вкладка 3',
      'Вкладка 4',
    ])
    expect(
      wrapper.findAll('[data-ui-kit-section="tabs"] [role="tabpanel"]'),
    ).toHaveLength(4)
    expect(demoTabs[0].attributes('aria-selected')).toBe('true')
    expect(wrapper.findAll('input[required]')).toHaveLength(2)
    expect(
      wrapper.get('[data-ui-kit-section="collection-states"] button').classes(),
    ).toContain('bg-blue-light-100')
    wrapper.unmount()
  })

  test('renders every design token declared by tokens.css', () => {
    const tokenSource = readFileSync(
      resolve(process.cwd(), 'src/styles/tokens.css'),
      'utf8',
    )
    const tokens = Array.from(
      new Set(tokenSource.match(/--admin-[a-z0-9-]+(?=\s*:)/g) ?? []),
    ).sort()
    const wrapper = mount(UiKitShowcase)
    const tokenCatalogue = wrapper
      .get('[data-ui-kit-section="design-tokens"]')
      .text()

    expect(tokens.length).toBeGreaterThan(0)
    for (const token of tokens) {
      expect(tokenCatalogue, token).toContain(token)
    }
    wrapper.unmount()
  })
})
