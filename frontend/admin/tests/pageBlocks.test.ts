import { describe, expect, it, vi, beforeEach } from 'vitest'
import { effectScope, nextTick, ref } from 'vue'
import { usePageBlocks } from '../src/features/pages/composables/usePageBlocks'
import { savePage, publishPage } from '../src/features/pages/services/pages'
import type { ContentPage } from '../src/features/pages/types/page.types'

vi.mock('../src/features/pages/services/pages', () => ({
  savePage: vi.fn(),
  publishPage: vi.fn(),
}))
const page = (): ContentPage => ({
  id: 7,
  title: 'Страница',
  slug: 'page',
  body: '',
  blocks: [
    {
      id: 'text',
      type: 'text',
      enabled: true,
      data: { title: 'Текст', body: 'Исходный' },
    },
  ],
  is_published: true,
  has_unpublished_changes: false,
  published_at: '',
  created_at: '',
  updated_at: '',
})

describe('page block draft workflow', () => {
  beforeEach(() => vi.clearAllMocks())
  it('isolates local edits, retains errors for retry, and publishes only saved content', async () => {
    const source = ref(page())
    const scope = effectScope()
    const editor = scope.run(() =>
      usePageBlocks(source, (saved) => {
        source.value = saved
      }),
    )!
    editor.form.value.blocks![0]!.enabled = false
    expect(source.value.blocks![0]!.enabled).toBe(true)
    await editor.publish()
    expect(publishPage).not.toHaveBeenCalled()
    vi.mocked(savePage).mockRejectedValueOnce(new Error('Ошибка сохранения'))
    await editor.save()
    expect(editor.error.value).toBe('Ошибка сохранения')
    expect(editor.dirty.value).toBe(true)
    vi.mocked(savePage).mockResolvedValue({
      ...page(),
      ...editor.form.value,
      has_unpublished_changes: true,
    })
    await editor.save()
    await nextTick()
    expect(editor.dirty.value).toBe(false)
    expect(editor.success.value).toContain('Черновик блоков сохранён')
    vi.mocked(publishPage).mockResolvedValue({
      ...source.value,
      has_unpublished_changes: false,
    })
    await editor.publish()
    await nextTick()
    expect(publishPage).toHaveBeenCalledWith(7)
    expect(editor.success.value).toBe('Сохранённый черновик опубликован.')
    scope.stop()
  })
  it('preserves edits made during a save and blocks concurrent saves', async () => {
    const scope = effectScope()
    const editor = scope.run(() => usePageBlocks(page(), vi.fn()))!
    editor.form.value.title = 'Первое изменение'
    let resolve!: (page: ContentPage) => void
    vi.mocked(savePage).mockImplementation(
      () =>
        new Promise((done) => {
          resolve = done
        }),
    )
    const saving = editor.save()
    await editor.save()
    expect(savePage).toHaveBeenCalledTimes(1)
    editor.form.value.title = 'Изменение во время запроса'
    resolve({ ...page(), title: 'Первое изменение' })
    await saving
    expect(editor.form.value.title).toBe('Изменение во время запроса')
    expect(editor.dirty.value).toBe(true)
    scope.stop()
  })
  it('refreshes saved metadata without replacing a locally edited draft', async () => {
    const source = ref(page())
    const scope = effectScope()
    const editor = scope.run(() => usePageBlocks(source, vi.fn()))!
    source.value = { ...source.value, title: 'Новые метаданные' }
    await nextTick()
    expect(editor.form.value.title).toBe('Новые метаданные')
    editor.form.value.blocks![0]!.enabled = false
    source.value = { ...source.value, title: 'Другой ответ' }
    await nextTick()
    expect(editor.form.value.title).toBe('Новые метаданные')
    expect(editor.form.value.blocks![0]!.enabled).toBe(false)
    scope.stop()
  })
  it('does not save/publish while a resource dialog or upload has unsaved input', async () => {
    const scope = effectScope()
    const editor = scope.run(() => usePageBlocks(page(), vi.fn()))!
    editor.resourceDirty.value = true
    await editor.save()
    await editor.publish()
    expect(savePage).not.toHaveBeenCalled()
    expect(publishPage).not.toHaveBeenCalled()
    scope.stop()
  })
})
