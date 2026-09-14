import { effectScope } from 'vue'
import { afterEach, describe, expect, test, vi } from 'vitest'
import { useFileImport } from '../src/features/products/composables/useFileImport'
import {
  PRODUCT_IMAGE_IMPORT_MAX_BYTES,
  PRODUCT_IMPORT_MAX_BYTES,
  validateXlsxImport,
  validateZipImport,
} from '../src/features/products/validation/importFiles'

type TestJob = {
  id: number
  status: 'pending' | 'processing' | 'completed' | 'failed'
}

afterEach(() => {
  vi.useRealTimers()
})

describe('product import file validation', () => {
  test('accepts XLSX up to the configured limit and rejects other or oversized files', () => {
    expect(validateXlsxImport(new File(['content'], 'catalog.XLSX'))).toBe('')
    expect(validateXlsxImport(new File(['content'], 'catalog.csv'))).toBe(
      'Прикрепите XLSX-файл размером не более 10 МБ.',
    )
    expect(
      validateXlsxImport(new File(['content'], 'catalog.xlsx'), 'Своя ошибка'),
    ).toBe('')

    const oversized = new File(['content'], 'catalog.xlsx')
    Object.defineProperty(oversized, 'size', {
      value: PRODUCT_IMPORT_MAX_BYTES + 1,
    })
    expect(validateXlsxImport(oversized, 'Своя ошибка')).toBe('Своя ошибка')
  })

  test('accepts ZIP by extension or MIME type and enforces the image archive limit', () => {
    expect(validateZipImport(new File(['content'], 'images.ZIP'))).toBe('')
    expect(
      validateZipImport(
        new File(['content'], 'images.bin', { type: 'application/zip' }),
      ),
    ).toBe('')
    expect(validateZipImport(new File(['content'], 'images.tar'))).toBe(
      'Прикрепите ZIP-архив размером не более 500 МБ.',
    )

    const oversized = new File(['content'], 'images.zip')
    Object.defineProperty(oversized, 'size', {
      value: PRODUCT_IMAGE_IMPORT_MAX_BYTES + 1,
    })
    expect(validateZipImport(oversized)).toBe(
      'Прикрепите ZIP-архив размером не более 500 МБ.',
    )
  })
})

describe('useFileImport', () => {
  test('uploads, polls to completion and emits completion outside the component', async () => {
    const upload = vi.fn(async (): Promise<TestJob> => ({
      id: 7,
      status: 'pending',
    }))
    const getStatus = vi.fn(async (): Promise<TestJob> => ({
      id: 7,
      status: 'completed',
    }))
    const completed = vi.fn()
    const scope = effectScope()
    const workflow = scope.run(() =>
      useFileImport({
        upload,
        getStatus,
        validate: () => '',
        uploadError: 'Ошибка загрузки.',
        onCompleted: completed,
        pollInterval: 1,
      }),
    )!

    const file = new File(['content'], 'products.xlsx')
    workflow.selectFile(file)
    await workflow.upload()

    await vi.waitFor(() => expect(workflow.finished.value).toBe(true))
    expect(upload).toHaveBeenCalledWith(file)
    expect(getStatus).toHaveBeenCalledWith(7)
    expect(completed).toHaveBeenCalledOnce()
    expect(workflow.busy.value).toBe(false)
    scope.stop()
  })

  test('keeps a pending job retryable after a polling error', async () => {
    const getStatus = vi
      .fn()
      .mockRejectedValueOnce(new Error('temporary'))
      .mockResolvedValueOnce({ id: 3, status: 'completed' } satisfies TestJob)
    const scope = effectScope()
    const workflow = scope.run(() =>
      useFileImport<TestJob>({
        upload: async () => ({ id: 3, status: 'pending' }),
        getStatus,
        validate: () => '',
        uploadError: 'Ошибка загрузки.',
        onCompleted: vi.fn(),
      }),
    )!

    workflow.selectFile(new File(['content'], 'products.xlsx'))
    await workflow.upload()
    await vi.waitFor(() => expect(workflow.pollingError.value).toBe(true))
    expect(workflow.result.value?.status).toBe('pending')
    expect(workflow.busy.value).toBe(true)

    await workflow.poll(3)
    expect(workflow.pollingError.value).toBe(false)
    expect(workflow.result.value?.status).toBe('completed')
    scope.stop()
  })

  test('stops before transport when validation fails', async () => {
    const upload = vi.fn()
    const scope = effectScope()
    const workflow = scope.run(() =>
      useFileImport<TestJob>({
        upload,
        getStatus: vi.fn(),
        validate: () => 'Некорректный файл.',
        uploadError: 'Ошибка загрузки.',
        onCompleted: vi.fn(),
      }),
    )!

    workflow.selectFile(new File(['content'], 'products.csv'))
    await workflow.upload()
    expect(workflow.error.value).toBe('Некорректный файл.')
    expect(upload).not.toHaveBeenCalled()
    scope.stop()
  })

  test('owns download state, browser delivery and notices', async () => {
    const createObjectURL = vi.fn(() => 'blob:test')
    const revokeObjectURL = vi.fn()
    vi.stubGlobal('URL', { ...URL, createObjectURL, revokeObjectURL })
    const click = vi
      .spyOn(HTMLAnchorElement.prototype, 'click')
      .mockImplementation(() => undefined)
    const scope = effectScope()
    const workflow = scope.run(() =>
      useFileImport<TestJob>({
        upload: vi.fn(),
        getStatus: vi.fn(),
        validate: () => '',
        uploadError: 'Ошибка загрузки.',
        onCompleted: vi.fn(),
      }),
    )!

    await workflow.download(
      async () => ({ blob: new Blob(['report']), filename: 'errors.xlsx' }),
      'Файл скачан.',
      'Не удалось скачать файл.',
    )

    expect(createObjectURL).toHaveBeenCalledOnce()
    expect(click).toHaveBeenCalledOnce()
    expect(workflow.notice.value).toBe('Файл скачан.')
    expect(workflow.downloading.value).toBe(false)
    scope.stop()
    vi.unstubAllGlobals()
  })
})
