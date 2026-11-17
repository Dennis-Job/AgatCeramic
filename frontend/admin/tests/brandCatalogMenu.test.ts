import { mount } from '@vue/test-utils'
import { expect, test, vi } from 'vitest'
import BrandFormDialog from '../src/features/brands/components/BrandFormDialog.vue'
import { useBrandForm } from '../src/features/brands/composables/useBrandForm'
import type { Brand } from '../src/features/brands/types/brand.types'

const brand: Brand = {
  id: 1,
  name: 'Kerama Marazzi',
  slug: 'kerama-marazzi',
  description: null,
  country_code: null,
  logo_id: null,
  logo: null,
  documents: [],
  is_active: false,
  show_in_catalog_menu: true,
  created_at: '2026-11-17T10:00:00Z',
  updated_at: '2026-11-17T10:00:00Z',
}

test('new brands start outside the catalog menu and reopening create resets the selection', () => {
  const form = useBrandForm(vi.fn(), vi.fn())
  form.show()
  expect(form.form.value.show_in_catalog_menu).toBe(false)
  form.show(brand)
  expect(form.form.value.show_in_catalog_menu).toBe(true)
  form.show()
  expect(form.form.value.show_in_catalog_menu).toBe(false)
})

test('editing retains the catalog menu selection independently of activity and saves turning it off', async () => {
  const save = vi.fn().mockResolvedValue(brand)
  const reload = vi.fn().mockResolvedValue(undefined)
  const form = useBrandForm(save, reload)
  form.show(brand)
  expect(form.form.value.show_in_catalog_menu).toBe(true)
  expect(form.form.value.is_active).toBe(false)
  form.form.value.show_in_catalog_menu = false
  await form.submit()
  expect(save).toHaveBeenCalledWith(
    brand.id,
    expect.objectContaining({ show_in_catalog_menu: false, is_active: false }),
  )
  expect(reload).toHaveBeenCalledOnce()
  expect(form.open.value).toBe(false)
})

test('catalog menu switch updates the create payload and is disabled while saving or uploading', async () => {
  const save = vi.fn().mockResolvedValue(brand)
  const form = useBrandForm(save, vi.fn())
  form.show()
  form.updateName(brand.name)
  const wrapper = mount(BrandFormDialog, {
    props: {
      open: true,
      title: 'Новый бренд',
      busy: false,
      mediaPending: false,
      mediaUploading: false,
      error: '',
      form: form.form.value,
    },
    global: {
      stubs: {
        UiDialog: { template: '<div><slot /></div>' },
        MediaImageEditor: true,
        MediaReferenceField: true,
        UiSelect: true,
      },
    },
  })
  const toggle = wrapper.get('[role="switch"]')
  expect(toggle.attributes('aria-checked')).toBe('false')
  await toggle.trigger('click')
  expect(toggle.attributes('aria-checked')).toBe('true')
  await form.submit()
  expect(save).toHaveBeenCalledWith(
    null,
    expect.objectContaining({ show_in_catalog_menu: true }),
  )
  await wrapper.setProps({ busy: true })
  expect(toggle.attributes('disabled')).toBeDefined()
  await wrapper.setProps({ busy: false, mediaUploading: true })
  expect(toggle.attributes('disabled')).toBeDefined()
  wrapper.unmount()
})
