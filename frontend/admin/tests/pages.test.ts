import { beforeEach, describe, expect, it, vi } from 'vitest'
import { usePages } from '../src/features/pages/composables/usePages'
import { savePage, getPages } from '../src/features/pages/services/pages'

vi.mock('../src/features/pages/services/pages', () => ({
  savePage: vi.fn(),
  getPages: vi.fn(),
  deletePage: vi.fn(),
  publishPage: vi.fn(),
  unpublishPage: vi.fn(),
}))

describe('new page indexing', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    vi.mocked(getPages).mockResolvedValue({
      data: [],
      meta: { current_page: 1, last_page: 1, per_page: 25, total: 0 },
    })
  })

  it('starts new pages excluded from indexing and submits the explicit choice', async () => {
    const pages = usePages()
    pages.openEditor()
    expect(pages.form.value.is_indexable).toBe(false)
    Object.assign(pages.form.value, {
      title: 'О нас',
      slug: 'about',
      body: 'История',
    })
    await pages.submit()
    expect(savePage).toHaveBeenCalledWith(
      null,
      expect.objectContaining({ is_indexable: false }),
    )
  })

  it('initializes the SEO title from the page title when indexing is enabled', async () => {
    const pages = usePages()
    pages.openEditor()
    Object.assign(pages.form.value, {
      title: 'Доставка',
      slug: 'delivery',
      body: 'Условия',
      is_indexable: true,
    })
    await pages.submit()
    expect(savePage).toHaveBeenCalledWith(
      null,
      expect.objectContaining({
        is_indexable: true,
        seo: expect.objectContaining({ title: 'Доставка', description: '' }),
      }),
    )
    pages.openEditor()
    expect(pages.form.value.is_indexable).toBe(false)
  })
})
