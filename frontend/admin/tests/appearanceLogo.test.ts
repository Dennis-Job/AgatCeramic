import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, expect, test, vi } from 'vitest'
import MediaImageEditor from '../src/features/media/components/MediaImageEditor.vue'

const state = vi.hoisted(() => ({
  permissions: [] as string[],
  getAllMedia: vi.fn(),
  uploadMedia: vi.fn(),
}))
vi.mock('../src/stores/auth', () => ({
  useAuthStore: () => ({
    hasPermission: (permission: string) =>
      state.permissions.includes(permission),
  }),
}))
vi.mock('../src/features/media/services/media', () => ({
  getAllMedia: state.getAllMedia,
  uploadMedia: state.uploadMedia,
}))

beforeEach(() => {
  state.permissions = ['content.manage']
  state.getAllMedia.mockReset().mockResolvedValue([])
  state.uploadMedia.mockReset()
})

function editor(modelValue: number | null = null, restricted = true) {
  return mount(MediaImageEditor, {
    props: {
      label: 'Логотип сайта',
      modelValue,
      requireManagePermission: restricted,
    },
    global: { stubs: { UiDialog: true } },
  })
}

test('content editors cannot select, clear or upload a restricted logo and an empty value does not request media', async () => {
  const wrapper = editor()
  await flushPromises()
  expect(state.getAllMedia).not.toHaveBeenCalled()
  expect(wrapper.find('input[type="file"]').exists()).toBe(false)
  expect(wrapper.find('input[type="checkbox"]').exists()).toBe(false)
  expect(wrapper.text()).not.toContain('Выбрать из загруженных')
  expect(wrapper.text()).toContain(
    'Выбор и загрузка доступны сотруднику с правом управления медиа.',
  )
  wrapper.unmount()
})

test('content editors can still see the selected logo without acquiring media controls', async () => {
  state.getAllMedia.mockResolvedValue([
    {
      id: 7,
      title: 'Логотип',
      alt: 'AgatCeramic',
      url: '/logo.png',
      thumbnail_url: '/logo-small.png',
    },
  ])
  const wrapper = editor(7)
  await flushPromises()
  expect(state.getAllMedia).toHaveBeenCalledWith('image')
  expect(wrapper.find('img').attributes('src')).toBe('/logo-small.png')
  expect(wrapper.text()).not.toContain('Выбрать из загруженных')
  expect(wrapper.find('input[type="checkbox"]').exists()).toBe(false)
  wrapper.unmount()
})

test('the existing unrestricted image editor keeps content-editor library access', async () => {
  const wrapper = editor(null, false)
  await flushPromises()
  expect(state.getAllMedia).toHaveBeenCalledWith('image')
  expect(wrapper.text()).toContain('Выбрать из загруженных')
  expect(wrapper.find('input[type="file"]').exists()).toBe(false)
  wrapper.unmount()
})

test('a page source image has one preview and an explicit source choice; unavailable media never falls back to a different photo', async () => {
  state.permissions = ['content.manage', 'media.manage']
  const wrapper = editor()
  await wrapper.setProps({
    fallbackUrl: '/images/about.webp',
    fallbackAlt: 'Образцы материалов',
    compact: true,
  })
  await flushPromises()
  expect(wrapper.findAll('img')).toHaveLength(1)
  expect(wrapper.find('img').attributes('src')).toBe(
    'http://localhost:3000/images/about.webp',
  )
  expect(wrapper.find('img').attributes('alt')).toBe('Образцы материалов')
  expect(wrapper.text()).toContain('Исходное изображение')
  expect(wrapper.find('input[type="checkbox"]').exists()).toBe(false)
  expect(wrapper.text()).not.toContain('Без изображения')
  await wrapper.setProps({ modelValue: 99 })
  expect(wrapper.text()).toContain('Использовать исходное изображение')
  expect(wrapper.find('img').exists()).toBe(false)
  expect(wrapper.text()).toContain('Выбран файл #99. Предпросмотр недоступен.')
  await wrapper.setProps({ modelValue: null })
  expect(wrapper.find('img').attributes('src')).toBe(
    'http://localhost:3000/images/about.webp',
  )
  wrapper.unmount()
})

test('a disabled image editor ignores dropped files without entering a pending state', async () => {
  state.permissions = ['content.manage', 'media.manage']
  const wrapper = editor()
  await wrapper.setProps({ disabled: true })
  await flushPromises()
  wrapper
    .findComponent({ name: 'UiFileDropzone' })
    .vm.$emit('files', [
      new File(['photo'], 'photo.webp', { type: 'image/webp' }),
    ])
  await flushPromises()
  expect(wrapper.text()).not.toContain('Название файла')
  expect(wrapper.emitted('pending')).toBeUndefined()
  expect(state.uploadMedia).not.toHaveBeenCalled()
  wrapper.unmount()
})
