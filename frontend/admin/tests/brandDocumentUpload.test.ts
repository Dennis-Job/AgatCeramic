import { mount } from '@vue/test-utils'
import { describe, expect, test, vi } from 'vitest'
import BrandFormDialog from '../src/features/brands/components/BrandFormDialog.vue'
import { emptyBrand } from '../src/features/brands/validation/brand.schema'
import { useInlineMediaUpload } from '../src/features/media/composables/useInlineMediaUpload'

const api = vi.hoisted(() => ({ uploadMedia: vi.fn() }))
vi.mock('../src/features/media/services/media', () => api)

describe('PDF upload validation', () => {
  test.each([
    new File(['image'], 'photo.png', { type: 'image/png' }),
    new File([new Uint8Array(20 * 1024 * 1024 + 1)], 'large.pdf', {
      type: 'application/pdf',
    }),
  ])(
    'rejects an unsupported or oversized document before uploading',
    async (file) => {
      api.uploadMedia.mockClear()
      const upload = useInlineMediaUpload('document', vi.fn())
      upload.chooseFile(file)
      expect(upload.file.value).toBeNull()
      expect(upload.error.value).toBe('Выберите PDF размером не больше 20 МБ.')
      await upload.upload()
      expect(api.uploadMedia).not.toHaveBeenCalled()
    },
  )

  test('requires a document title without requesting an image description', async () => {
    const upload = useInlineMediaUpload('document', vi.fn())
    upload.chooseFile(
      new File(['%PDF'], 'catalog.pdf', { type: 'application/pdf' }),
    )
    upload.title.value = '   '
    await upload.upload()
    expect(upload.error.value).toBe('Укажите название документа.')
  })
})

test('brand media guards stay active until both logo and PDF finish', async () => {
  const wrapper = mount(BrandFormDialog, {
    props: {
      open: true,
      title: 'Новый бренд',
      busy: false,
      mediaPending: false,
      mediaUploading: false,
      error: '',
      form: emptyBrand(),
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
  const logo = wrapper.findComponent({ name: 'MediaImageEditor' })
  const pdf = wrapper.findComponent({ name: 'MediaReferenceField' })
  logo.vm.$emit('pending', true)
  pdf.vm.$emit('pending', true)
  logo.vm.$emit('pending', false)
  await wrapper.vm.$nextTick()
  expect(wrapper.emitted('mediaPending')?.at(-1)).toEqual([true])
  pdf.vm.$emit('pending', false)
  await wrapper.vm.$nextTick()
  expect(wrapper.emitted('mediaPending')?.at(-1)).toEqual([false])
  logo.vm.$emit('uploading', true)
  pdf.vm.$emit('uploading', true)
  logo.vm.$emit('uploading', false)
  await wrapper.vm.$nextTick()
  expect(wrapper.emitted('mediaUploading')?.at(-1)).toEqual([true])
  pdf.vm.$emit('uploading', false)
  await wrapper.vm.$nextTick()
  expect(wrapper.emitted('mediaUploading')?.at(-1)).toEqual([false])
  wrapper.unmount()
})
