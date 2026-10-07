import AxeBuilder from '@axe-core/playwright'
import { expect, test, type Page } from './fixtures'
import { mockCategoryOverview, overviewCategories } from './categoryOverviewApi'
import { createDeferredApiRequests } from './deferredApi'

const pixel =
  'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScL/nwAAAABJRU5ErkJggg=='
const file = {
  name: 'category.png',
  mimeType: 'image/png',
  buffer: Buffer.from(pixel.split(',')[1]!, 'base64'),
}
const media = {
  id: 77,
  kind: 'image',
  url: pixel,
  thumbnail_url: pixel,
  title: 'category',
  alt: 'Белая керамическая плитка',
  mime_type: 'image/png',
  size: file.buffer.length,
  width: 1,
  height: 1,
  created_at: '2026-10-07T12:00:00Z',
  updated_at: '2026-10-07T12:00:00Z',
}

async function mockImages(page: Page) {
  await mockCategoryOverview(page)
  const deferred = createDeferredApiRequests()
  let failUpload = false
  let uploads = 0
  const saves: Record<string, unknown>[] = []
  await page.route('**/api/v1/admin/media?*', async (route) => {
    await deferred.wait('mediaRead')
    return route.fulfill({
      json: {
        data: [],
        meta: { current_page: 1, last_page: 1, per_page: 100, total: 0 },
      },
    })
  })
  await page.route('**/api/v1/admin/media', async (route) => {
    if (route.request().method() !== 'POST') return route.fallback()
    uploads++
    await deferred.wait('upload')
    if (failUpload) {
      failUpload = false
      return route.fulfill({
        status: 422,
        json: {
          error: {
            message: 'Не удалось загрузить изображение. Повторите попытку.',
          },
        },
      })
    }
    const multipart = route.request().postDataBuffer()!.toString()
    expect(multipart).toContain('name="file"; filename="category.png"')
    expect(multipart).toContain('Белая керамическая плитка')
    return route.fulfill({ status: 201, json: { data: media } })
  })
  await page.route(/\/api\/v1\/admin\/categories(?:\/1)?$/, (route) => {
    if (!['POST', 'PATCH'].includes(route.request().method()))
      return route.fallback()
    const payload = route.request().postDataJSON() as Record<string, unknown>
    saves.push(payload)
    return route.fulfill({
      status: 201,
      json: {
        data: {
          ...overviewCategories[0],
          ...payload,
          image: media,
          image_id: 77,
        },
      },
    })
  })
  return {
    deferred,
    saves,
    uploads: () => uploads,
    failNextUpload: () => {
      failUpload = true
    },
  }
}

for (const mode of ['create', 'edit'] as const) {
  test(`category ${mode} uploads and selects an image in the dialog before saving`, async ({
    page,
  }) => {
    const api = await mockImages(page)
    await page.goto('/categories')
    await page
      .getByRole('button', {
        name:
          mode === 'create'
            ? 'Добавить категорию'
            : 'Редактировать категорию Керамогранит',
        exact: true,
      })
      .click()
    const dialog = page.getByRole('dialog')
    await dialog
      .getByRole('textbox', { name: /^Название(?: Очистить поле)?$/ })
      .fill('Настенная плитка')
    if (mode === 'create') {
      await dialog
        .getByRole('button', {
          name: 'Выберите или перетащите фото в эту область',
          exact: true,
        })
        .evaluate(
          (el, image) => {
            const transfer = new DataTransfer()
            transfer.items.add(
              new File([new Uint8Array(image.bytes)], image.name, {
                type: image.mimeType,
              }),
            )
            el.dispatchEvent(
              new DragEvent('drop', { bubbles: true, dataTransfer: transfer }),
            )
          },
          { name: file.name, mimeType: file.mimeType, bytes: [...file.buffer] },
        )
    } else {
      await dialog
        .getByLabel('Загрузить файл — Изображение категории')
        .setInputFiles(file)
    }
    const save = dialog.getByRole('button', { name: 'Сохранить', exact: true })
    await expect(save).toBeDisabled()
    await dialog
      .getByRole('textbox', { name: /^Название(?: Очистить поле)?$/ })
      .press('Enter')
    expect(api.saves).toEqual([])
    await dialog.getByLabel('Описание загружаемого изображения').fill(media.alt)
    const upload = api.deferred.defer('upload')
    await dialog
      .getByRole('button', { name: 'Загрузить и выбрать', exact: true })
      .click()
    await upload.requested
    await expect(
      dialog.getByRole('button', { name: 'Отмена', exact: true }),
    ).toBeDisabled()
    await expect(
      dialog.getByRole('button', { name: 'Закрыть окно категории' }),
    ).toBeDisabled()
    await page.keyboard.press('Escape')
    await expect(dialog).toBeVisible()
    await expect(save).toBeDisabled()
    upload.release()
    await expect(
      dialog.getByRole('button', {
        name: 'Увеличить изображение категории',
        exact: true,
      }),
    ).toBeVisible()
    await expect(dialog.getByRole('img', { name: media.alt })).toBeVisible()
    await expect(
      dialog.getByRole('status').filter({ hasText: 'Сохраните категорию' }),
    ).toBeVisible()
    await expect(save).toBeEnabled()
    await expect(
      dialog.getByRole('button', { name: 'Отмена', exact: true }),
    ).toBeEnabled()
    await expect(
      dialog.getByRole('button', { name: 'Отмена', exact: true }),
    ).toBeInViewport({ ratio: 1 })
    await page.screenshot({ path: `.tmp/category-image-${mode}-uploaded.png` })
    await save.click()
    await expect(dialog).toHaveCount(0)
    expect(api.saves).toHaveLength(1)
    expect(api.saves[0]).toMatchObject({
      image_id: 77,
      name: 'Настенная плитка',
    })
    await expect(page).toHaveURL(/\/categories$/)
  })
}

test('category upload waits for the media list to preserve the new image selection', async ({
  page,
}) => {
  const api = await mockImages(page)
  const read = api.deferred.defer('mediaRead')
  await page.goto('/categories')
  await page
    .getByRole('button', { name: 'Добавить категорию', exact: true })
    .click()
  await read.requested
  const dialog = page.getByRole('dialog')
  await dialog
    .getByLabel('Загрузить файл — Изображение категории')
    .setInputFiles(file)
  await dialog.getByLabel('Описание загружаемого изображения').fill(media.alt)
  const upload = dialog.getByRole('button', {
    name: 'Загрузить и выбрать',
    exact: true,
  })
  await expect(upload).toBeDisabled()
  expect(api.uploads()).toBe(0)
  read.release()
  await expect(upload).toBeEnabled()
  await upload.click()
  await expect(
    dialog.getByRole('button', {
      name: 'Увеличить изображение категории',
      exact: true,
    }),
  ).toBeVisible()
  await expect(dialog.getByRole('img', { name: media.alt })).toBeVisible()
  await expect(
    dialog.getByRole('button', { name: 'Сохранить', exact: true }),
  ).toBeEnabled()
})

test('category upload validates description, retries errors and allows cancelling a pending file', async ({
  page,
  browserIssueGuard,
}) => {
  const api = await mockImages(page)
  browserIssueGuard.allowApiError(422, '/api/v1/admin/media')
  await page.goto('/categories')
  await page
    .getByRole('button', { name: 'Добавить категорию', exact: true })
    .click()
  const dialog = page.getByRole('dialog')
  const save = dialog.getByRole('button', { name: 'Сохранить', exact: true })
  await dialog
    .getByRole('textbox', { name: /^Название(?: Очистить поле)?$/ })
    .fill('Категория с изображением')
  await dialog
    .getByLabel('Загрузить файл — Изображение категории')
    .setInputFiles(file)
  await dialog
    .getByRole('button', { name: 'Загрузить и выбрать', exact: true })
    .click()
  await expect(dialog.getByRole('alert')).toContainText(
    'Укажите название и описание изображения.',
  )
  expect(api.uploads()).toBe(0)
  await dialog.getByLabel('Описание загружаемого изображения').fill(media.alt)
  api.failNextUpload()
  await dialog
    .getByRole('button', { name: 'Загрузить и выбрать', exact: true })
    .click()
  await expect(dialog.getByRole('alert')).toContainText(
    'Не удалось загрузить изображение.',
  )
  await expect(dialog.getByLabel('Название файла')).toHaveValue('category')
  await expect(save).toBeDisabled()
  await page.screenshot({ path: '.tmp/category-image-error.png' })
  await dialog
    .getByRole('button', { name: 'Загрузить и выбрать', exact: true })
    .click()
  await expect(save).toBeEnabled()
  expect(api.uploads()).toBe(2)
  await dialog
    .getByLabel('Загрузить файл — Изображение категории')
    .setInputFiles(file)
  await expect(save).toBeDisabled()
  await dialog
    .getByRole('button', { name: 'Отменить выбор файла', exact: true })
    .click()
  expect(api.saves).toEqual([])
  await expect(save).toBeEnabled()
  await expect(
    dialog.getByRole('button', {
      name: 'Увеличить изображение категории',
      exact: true,
    }),
  ).toBeVisible()
})

test('category users without media.manage cannot upload images', async ({
  page,
}) => {
  await mockImages(page)
  await page.route('**/api/v1/admin/auth/me', (route) =>
    route.fulfill({
      json: {
        data: {
          id: 1,
          name: 'Менеджер',
          email: 'manager@example.test',
          status: 'active',
          permissions: ['catalog.manage'],
        },
      },
    }),
  )
  await page.goto('/categories')
  await page
    .getByRole('button', { name: 'Добавить категорию', exact: true })
    .click()
  await expect(
    page.getByRole('dialog').locator('input[type="file"]'),
  ).toHaveCount(0)
})

for (const width of [320, 640, 768, 1024, 1280]) {
  test(`category pending upload fits ${width}px with accessible controls`, async ({
    page,
  }) => {
    await mockImages(page)
    await page.setViewportSize({ width, height: 700 })
    await page.goto('/categories')
    await page
      .getByRole('button', { name: 'Добавить категорию', exact: true })
      .click()
    const dialog = page.getByRole('dialog')
    await expect(
      dialog.getByRole('button', {
        name: 'Выбрать из загруженных',
        exact: true,
      }),
    ).toBeEnabled()
    await dialog
      .getByText('Изображение категории', { exact: true })
      .evaluate((el) => el.scrollIntoView({ block: 'start' }))
    await page.screenshot({ path: `.tmp/category-image-empty-${width}.png` })
    await dialog
      .getByLabel('Загрузить файл — Изображение категории')
      .setInputFiles(file)
    await dialog.getByLabel('Описание загружаемого изображения').fill(media.alt)
    expect(
      await dialog.evaluate((el) => el.scrollWidth <= el.clientWidth),
    ).toBe(true)
    expect(
      await dialog
        .locator('.overflow-y-auto')
        .first()
        .evaluate((el) => el.scrollWidth <= el.clientWidth),
    ).toBe(true)
    const cancel = (await dialog
      .getByRole('button', { name: 'Отмена', exact: true })
      .boundingBox())!
    const save = (await dialog
      .getByRole('button', { name: 'Сохранить', exact: true })
      .boundingBox())!
    expect(Math.abs(save.y - cancel.y)).toBeLessThanOrEqual(1)
    const accessibility = await new AxeBuilder({ page })
      .include('[role="dialog"]')
      .disableRules(['color-contrast'])
      .analyze()
    expect(
      accessibility.violations.filter((item) =>
        ['serious', 'critical'].includes(item.impact ?? ''),
      ),
    ).toEqual([])
    const upload = dialog.getByRole('button', {
      name: 'Загрузить и выбрать',
      exact: true,
    })
    await upload.scrollIntoViewIfNeeded()
    await expect(upload).toBeInViewport({ ratio: 1 })
    await expect(
      dialog.getByRole('button', { name: 'Отменить выбор файла', exact: true }),
    ).toBeInViewport({ ratio: 1 })
    await page.screenshot({ path: `.tmp/category-image-pending-${width}.png` })
    await upload.click()
    await dialog
      .getByRole('button', {
        name: 'Увеличить изображение категории',
        exact: true,
      })
      .click()
    const viewer = page.getByRole('dialog', {
      name: 'Просмотр изображения',
      exact: true,
    })
    const viewerBounds = (await viewer.boundingBox())!
    expect(viewerBounds.width).toBe(width)
    expect(viewerBounds.height).toBe(700)
    const closeViewer = viewer.getByRole('button', {
      name: 'Закрыть просмотр изображения',
      exact: true,
    })
    await expect
      .poll(() =>
        closeViewer.evaluate((el) => {
          const r = el.getBoundingClientRect()
          return el.contains(
            document.elementFromPoint(r.x + r.width / 2, r.y + r.height / 2),
          )
        }),
      )
      .toBe(true)
    expect(
      await viewer.evaluate((el) => el.scrollWidth <= el.clientWidth),
    ).toBe(true)
    await page.screenshot({
      path: `.tmp/category-image-fullscreen-${width}.png`,
    })
    const viewerAxe = await new AxeBuilder({ page })
      .include('[role="dialog"]')
      .disableRules(['color-contrast'])
      .analyze()
    expect(
      viewerAxe.violations.filter((item) =>
        ['serious', 'critical'].includes(item.impact ?? ''),
      ),
    ).toEqual([])
    await closeViewer.click()
    await expect(dialog).toBeVisible()
  })
}

test('compact image controls clear and restore selection and open a full screen preview', async ({
  page,
}) => {
  const api = await mockImages(page)
  await page.route('**/api/v1/admin/media?*', (route) =>
    route.fulfill({
      json: {
        data: [media],
        meta: { current_page: 1, last_page: 1, per_page: 100, total: 1 },
      },
    }),
  )
  await page.goto('/categories')
  await page
    .getByRole('button', { name: 'Добавить категорию', exact: true })
    .click()
  const dialog = page.getByRole('dialog', {
    name: 'Новая категория',
    exact: true,
  })
  const none = dialog.getByRole('checkbox', {
    name: 'Без изображения',
    exact: true,
  })
  await expect(none).toBeChecked()
  await expect(
    dialog.getByRole('button', { name: 'Изображение категории', exact: true }),
  ).toHaveCount(0)
  const dropzone = dialog.getByRole('button', {
    name: 'Выберите или перетащите фото в эту область',
    exact: true,
  })
  await expect(dropzone).toBeVisible()
  await dialog
    .getByRole('button', { name: 'Выбрать из загруженных', exact: true })
    .click()
  await dialog
    .getByRole('button', { name: 'Изображение категории', exact: true })
    .click()
  await page
    .getByRole('button', { name: 'category (#77)', exact: true })
    .click()
  await expect(
    dialog.getByRole('button', { name: 'Выбрать из загруженных', exact: true }),
  ).toBeFocused()
  await expect(none).not.toBeChecked()
  const preview = dialog.getByRole('button', {
    name: 'Увеличить изображение категории',
    exact: true,
  })
  const bounds = (await preview.boundingBox())!
  expect(bounds.width).toBeLessThanOrEqual(128)
  const dropBounds = (await dropzone.boundingBox())!
  expect(dropBounds.x).toBeGreaterThanOrEqual(bounds.x + bounds.width)
  expect(Math.abs(bounds.y - dropBounds.y)).toBeLessThanOrEqual(1)
  await preview.click()
  const viewer = page.getByRole('dialog', {
    name: 'Просмотр изображения',
    exact: true,
  })
  await expect(viewer).toBeVisible()
  await expect(viewer.getByRole('img', { name: media.alt })).toHaveAttribute(
    'src',
    media.url,
  )
  const full = (await viewer.boundingBox())!
  const size = page.viewportSize()!
  expect(full.width).toBe(size.width)
  expect(full.height).toBe(size.height)
  await page.screenshot({ path: '.tmp/category-image-fullscreen.png' })
  await page.keyboard.press('Escape')
  await expect(viewer).toHaveCount(0)
  await expect(dialog).toBeVisible()
  await expect(preview).toBeFocused()
  await dialog
    .getByLabel('Загрузить файл — Изображение категории')
    .setInputFiles(file)
  await expect(
    dialog.getByRole('button', { name: 'Сохранить', exact: true }),
  ).toBeDisabled()
  await none.press('Space')
  await expect(none).toBeChecked()
  await expect(
    dialog.getByRole('button', { name: 'Сохранить', exact: true }),
  ).toBeEnabled()
  await expect(preview).toHaveCount(0)
  await expect(dialog.getByText('category (#77)', { exact: true })).toHaveCount(
    0,
  )
  await none.press('Space')
  await expect(none).not.toBeChecked()
  await expect(preview).toBeVisible()
  await preview.click()
  await page
    .getByRole('button', { name: 'Закрыть просмотр изображения', exact: true })
    .click()
  await expect(preview).toBeFocused()
  await none.press('Space')
  await dialog
    .getByRole('textbox', { name: /^Название(?: Очистить поле)?$/ })
    .fill('Категория без изображения')
  await dialog.getByRole('button', { name: 'Сохранить', exact: true }).click()
  await expect(dialog).toHaveCount(0)
  expect(api.saves[0]).toMatchObject({ image_id: null })
})

test('category dropzone rejects an unsupported image without starting an upload', async ({
  page,
}) => {
  const api = await mockImages(page)
  await page.goto('/categories')
  await page
    .getByRole('button', { name: 'Добавить категорию', exact: true })
    .click()
  const dialog = page.getByRole('dialog')
  await dialog
    .getByLabel('Загрузить файл — Изображение категории')
    .setInputFiles({
      name: 'document.pdf',
      mimeType: 'application/pdf',
      buffer: Buffer.from('pdf'),
    })
  await expect(dialog.getByRole('alert')).toContainText(
    'Выберите JPEG, PNG или WebP',
  )
  await expect(
    dialog.getByRole('checkbox', { name: 'Без изображения', exact: true }),
  ).toBeChecked()
  await expect(
    dialog.getByRole('button', { name: 'Сохранить', exact: true }),
  ).toBeEnabled()
  expect(api.uploads()).toBe(0)
})

test('category photo removal appears on hover and keyboard focus and saves only the category link', async ({
  page,
}) => {
  const api = await mockImages(page)
  await page.goto('/categories')
  await page
    .getByRole('button', { name: 'Добавить категорию', exact: true })
    .click()
  const dialog = page.getByRole('dialog')
  await dialog
    .getByRole('textbox', { name: /^Название(?: Очистить поле)?$/ })
    .fill('Категория с фото')
  await dialog
    .getByLabel('Загрузить файл — Изображение категории')
    .setInputFiles(file)
  await dialog.getByLabel('Описание загружаемого изображения').fill(media.alt)
  await dialog
    .getByRole('button', { name: 'Загрузить и выбрать', exact: true })
    .click()
  const preview = dialog.getByRole('button', {
    name: 'Увеличить изображение категории',
    exact: true,
  })
  await expect(preview).toBeVisible()
  await expect(dialog.getByText('category (#77)', { exact: true })).toHaveCount(
    0,
  )
  const remove = dialog.getByRole('button', {
    name: 'Убрать изображение категории',
    exact: true,
  })
  await page.mouse.move(0, 0)
  await expect(remove).toHaveCSS('opacity', '0')
  await preview.hover()
  await expect(remove).toHaveCSS('opacity', '1')
  await page.screenshot({ path: '.tmp/category-image-remove-hover.png' })
  await page.mouse.move(0, 0)
  await preview.focus()
  await page.keyboard.press('Tab')
  await expect(remove).toBeFocused()
  await expect(remove).toHaveCSS('opacity', '1')
  await page.keyboard.press('Enter')
  await expect(preview).toHaveCount(0)
  await expect(
    page.getByRole('dialog', { name: 'Просмотр изображения', exact: true }),
  ).toHaveCount(0)
  const none = dialog.getByRole('checkbox', {
    name: 'Без изображения',
    exact: true,
  })
  await expect(none).toBeChecked()
  await expect(none).toBeFocused()
  expect(api.saves).toHaveLength(0)
  await dialog.getByRole('button', { name: 'Сохранить', exact: true }).click()
  await expect(dialog).toHaveCount(0)
  expect(api.saves[0]).toMatchObject({ image_id: null })
})

test.describe('category image touch controls', () => {
  test.use({
    hasTouch: true,
    isMobile: true,
    viewport: { width: 320, height: 700 },
  })
  test('photo removal is available without hover on touch screens', async ({
    page,
  }) => {
    await mockImages(page)
    await page.goto('/categories')
    await page
      .getByRole('button', { name: 'Добавить категорию', exact: true })
      .click()
    const dialog = page.getByRole('dialog')
    await dialog
      .getByLabel('Загрузить файл — Изображение категории')
      .setInputFiles(file)
    await dialog.getByLabel('Описание загружаемого изображения').fill(media.alt)
    await dialog
      .getByRole('button', { name: 'Загрузить и выбрать', exact: true })
      .click()
    const remove = dialog.getByRole('button', {
      name: 'Убрать изображение категории',
      exact: true,
    })
    await expect(remove).toHaveCSS('opacity', '1')
    await remove.scrollIntoViewIfNeeded()
    await page.screenshot({ path: '.tmp/category-image-remove-touch.png' })
    await remove.tap()
    await expect(
      dialog.getByRole('checkbox', { name: 'Без изображения', exact: true }),
    ).toBeChecked()
    await expect(
      dialog.getByRole('button', {
        name: 'Увеличить изображение категории',
        exact: true,
      }),
    ).toHaveCount(0)
  })
})
