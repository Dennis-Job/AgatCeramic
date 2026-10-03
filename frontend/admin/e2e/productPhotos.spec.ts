import AxeBuilder from '@axe-core/playwright'
import { expect, test } from './fixtures'
import { mockCatalogApi } from './catalogApi'

const addName = 'Добавить фото товара Монте Тиберио'
const editName = 'Редактировать фото товара Монте Тиберио'
const imagesRoute = /\/api\/v1\/admin\/products\/1\/images(?:\?.*)?$/
const uploadFile = {
  name: 'tile.jpg',
  mimeType: 'image/jpeg',
  buffer: Buffer.from('test image'),
}

test('table photo action uploads, deletes and updates the thumbnail without opening other editor steps', async ({
  page,
}) => {
  await mockCatalogApi(page)
  await page.goto('/products')
  const add = page.getByRole('button', { name: addName, exact: true })
  await expect(add).toBeVisible()
  await page.screenshot({ path: '.tmp/product-photo-review/table-empty.png' })
  const requests: string[] = []
  page.on('request', (request) =>
    requests.push(new URL(request.url()).pathname),
  )
  await add.focus()
  await page.keyboard.press('Enter')
  const dialog = page.getByRole('dialog')
  await expect(dialog).toHaveAccessibleName('Добавление фото')
  await expect(
    dialog.getByRole('heading', { name: 'Добавление фото' }),
  ).toBeFocused()
  await expect(
    dialog.getByText('Фото этого товара ещё не добавлены.'),
  ).toBeVisible()
  await expect(
    dialog.getByText('Фото этого товара ещё не добавлены.').locator('..'),
  ).not.toHaveClass(/border/)
  await expect(dialog.locator('.admin-tooltip')).toHaveCount(0)
  await page.screenshot({
    path: '.tmp/product-photo-empty-review/modal-1280.png',
  })
  await page.setViewportSize({ width: 320, height: 900 })
  await page.screenshot({
    path: '.tmp/product-photo-empty-review/modal-320.png',
  })
  await page.setViewportSize({ width: 1280, height: 900 })
  await expect(
    dialog.getByRole('button', { name: 'Основное', exact: false }),
  ).toHaveCount(0)
  expect(
    requests.filter(
      (path) => path.includes('/admin/') && !path.endsWith('/images'),
    ),
  ).toEqual([])
  await dialog.getByLabel('Файл изображения').setInputFiles(uploadFile)
  await dialog.getByRole('button', { name: 'Загрузить', exact: true }).click()
  await expect(dialog.locator('article img')).toHaveCount(1)
  await expect(dialog).toHaveAccessibleName('Редактирование фото')
  await dialog.getByRole('button', { name: 'Готово' }).click()
  const edit = page.getByRole('button', { name: editName, exact: true })
  await expect(edit).toBeFocused()
  await expect(edit.locator('img')).toHaveAttribute('src', '/uploaded-1-1.jpg')
  await edit.click()
  await dialog
    .getByRole('button', { name: 'Удалить изображение 1', exact: true })
    .click()
  const confirm = page.getByRole('dialog', { name: 'Удалить фотографию?' })
  await confirm.getByRole('button', { name: 'Удалить', exact: true }).click()
  await expect(
    dialog.getByText('Фото этого товара ещё не добавлены.'),
  ).toBeVisible()
  await dialog.getByRole('button', { name: 'Готово' }).click()
  await expect(add).toBeFocused()
  await expect(add.locator('img')).toHaveCount(0)
})

test('photo editor keeps hover, keyboard, cover order and responsive accessibility', async ({
  page,
}) => {
  await mockCatalogApi(page, {
    sourceProduct: {
      name: 'Монте Тиберио — керамогранит коллекционный полированный с декоративной фактурой белого мрамора 60×120 см',
    },
    initialProductImages: [
      {
        id: 1,
        url: '/first.jpg',
        alt: 'Первое фото',
        is_primary: true,
        sort_order: 0,
      },
      {
        id: 2,
        url: '/second.jpg',
        alt: 'Второе фото',
        is_primary: false,
        sort_order: 1,
      },
      {
        id: 3,
        url: '/third.jpg',
        alt: 'Третье фото',
        is_primary: false,
        sort_order: 2,
      },
      {
        id: 4,
        url: '/fourth.jpg',
        alt: 'Четвёртое фото',
        is_primary: false,
        sort_order: 3,
      },
    ],
  })
  await page.goto('/products')
  const edit = page.getByRole('button', {
    name: /Редактировать фото товара Монте Тиберио/,
  })
  const pencil = edit.locator('.product-photo-edit')
  await expect(pencil).toHaveCSS('opacity', '0')
  await edit.hover()
  await expect(pencil).toHaveCSS('opacity', '1')
  await edit.focus()
  await page.keyboard.press('Enter')
  const dialog = page.getByRole('dialog', {
    name: 'Редактирование фото',
    exact: true,
  })
  await expect(dialog.locator('article img')).toHaveCount(4)
  for (const width of [320, 640, 768, 1024, 1280]) {
    await page.setViewportSize({ width, height: 900 })
    expect(
      await page.evaluate(
        () => document.documentElement.scrollWidth <= innerWidth,
      ),
    ).toBe(true)
    expect(
      await dialog.evaluate(
        (element) => element.scrollWidth <= element.clientWidth,
      ),
    ).toBe(true)
    const top = await dialog
      .locator('article')
      .evaluateAll((articles) =>
        articles.map((article) => article.getBoundingClientRect().top),
      )
    expect(top.filter((value) => Math.abs(value - top[0]!) < 1)).toHaveLength(
      width >= 1024 ? 4 : width >= 640 ? 2 : 1,
    )
    await page.screenshot({
      path: `.tmp/product-photo-upload-review/modal-${width}.png`,
    })
    if (width === 1280)
      await dialog.locator('.product-photo-dropzone').screenshot({
        path: '.tmp/product-photo-upload-review/upload-field.png',
      })
    expect((await new AxeBuilder({ page }).analyze()).violations).toEqual([])
  }
  await dialog
    .getByRole('button', {
      name: 'Переместить изображение 1 ниже',
      exact: true,
    })
    .click()
  await expect(dialog.locator('article img').first()).toHaveAttribute(
    'src',
    '/second.jpg',
  )
  await page.keyboard.press('Escape')
  await expect(dialog).toHaveCount(0)
  await expect(edit).toBeFocused()
  await expect(edit.locator('img')).toHaveAttribute('src', '/second.jpg')
  await page.mouse.move(0, 0)
  await page.keyboard.press('Tab')
  await edit.focus()
  await expect(pencil).toHaveCSS('opacity', '1')
  await page.screenshot({ path: '.tmp/product-photo-review/table-focus.png' })
})

test('photo load and upload failures offer recovery and keep the selected file', async ({
  page,
  browserIssueGuard,
}) => {
  await mockCatalogApi(page)
  browserIssueGuard.allowApiError(500, '/api/v1/admin/products/1/images')
  await page.route(imagesRoute, async (route) => {
    await route.fulfill({
      status: 500,
      json: { error: { message: 'Ошибка фото' } },
    })
  })
  await page.goto('/products')
  await page.getByRole('button', { name: addName }).click()
  const dialog = page.getByRole('dialog')
  await expect(
    dialog.getByRole('button', { name: 'Повторить загрузку' }),
  ).toBeVisible()
  await expect(
    dialog.getByRole('button', {
      name: /Выберите или перетащите фото|Отпустите фото/,
    }),
  ).toHaveCount(0)
  await page.unroute(imagesRoute)
  await dialog.getByRole('button', { name: 'Повторить загрузку' }).click()
  await dialog.getByLabel('Файл изображения').setInputFiles(uploadFile)
  await page.route(imagesRoute, async (route) => {
    if (route.request().method() === 'POST')
      await route.fulfill({
        status: 500,
        json: { error: { message: 'Загрузка не удалась' } },
      })
    else await route.fallback()
  })
  await dialog.getByRole('button', { name: 'Загрузить', exact: true }).click()
  await expect(
    page.getByText('Загрузка не удалась', { exact: true }),
  ).toBeVisible()
  await expect(dialog.locator('output')).toHaveText('tile.jpg')
  await page.unroute(imagesRoute)
  await dialog.getByRole('button', { name: 'Загрузить', exact: true }).click()
  await expect(dialog.locator('article img')).toHaveCount(1)
})

test('closing a loading photo dialog ignores the late response and clears pending file on reopen', async ({
  page,
}) => {
  await mockCatalogApi(page)
  let release: (() => void) | undefined
  await page.route(imagesRoute, async (route) => {
    await new Promise<void>((resolve) => {
      release = resolve
    })
    await route.fallback()
  })
  await page.goto('/products')
  const add = page.getByRole('button', { name: addName })
  await add.click()
  await expect(
    page.getByText('Загрузка фотографий…', { exact: true }),
  ).toBeVisible()
  await expect(
    page.getByRole('heading', { name: 'Добавление фото' }),
  ).toBeFocused()
  await expect(page.getByRole('tooltip')).toHaveCount(0)
  await page.getByRole('button', { name: 'Закрыть фотографии товара' }).click()
  await expect(add).toBeFocused()
  await expect.poll(() => typeof release).toBe('function')
  release?.()
  await page.unroute(imagesRoute)
  await add.click()
  const dialog = page.getByRole('dialog', { name: 'Добавление фото' })
  await dialog.getByLabel('Файл изображения').setInputFiles(uploadFile)
  await dialog.getByRole('button', { name: 'Готово' }).click()
  await add.click()
  await expect(dialog.locator('output')).toHaveText('Файл не выбран')
  await expect(
    dialog.getByRole('button', { name: 'Загрузить', exact: true }),
  ).toHaveCount(0)
})

test('photo upload blocks close while busy and restores controls afterwards', async ({
  page,
}) => {
  await mockCatalogApi(page)
  let release: (() => void) | undefined
  await page.route(imagesRoute, async (route) => {
    if (route.request().method() === 'POST')
      await new Promise<void>((resolve) => {
        release = resolve
      })
    await route.fallback()
  })
  await page.goto('/products')
  await page.getByRole('button', { name: addName }).click()
  const dialog = page.getByRole('dialog')
  await dialog.getByLabel('Файл изображения').setInputFiles(uploadFile)
  await dialog.getByRole('button', { name: 'Загрузить', exact: true }).click()
  await expect(
    dialog.getByRole('button', { name: 'Загрузка…', exact: true }),
  ).toBeDisabled()
  await expect(dialog.getByRole('button', { name: 'Готово' })).toBeDisabled()
  await expect(
    dialog.getByRole('button', { name: 'Закрыть фотографии товара' }),
  ).toBeDisabled()
  await page.keyboard.press('Escape')
  await expect(dialog).toBeVisible()
  await expect.poll(() => typeof release).toBe('function')
  release?.()
  await expect(dialog.locator('article img')).toHaveCount(1)
  await expect(dialog.getByRole('button', { name: 'Готово' })).toBeEnabled()
})

test('photo editor shows a fallback for unavailable existing photos', async ({
  page,
  browserIssueGuard,
}) => {
  await mockCatalogApi(page, {
    initialProductImages: [
      {
        id: 1,
        url: '/unavailable-photo.png',
        alt: 'Фото плитки',
        is_primary: true,
        sort_order: 0,
      },
    ],
  })
  browserIssueGuard.allowApiError(404, '/unavailable-photo.png')
  await page.route('**/unavailable-photo.png', (route) =>
    route.fulfill({ status: 404 }),
  )
  await page.goto('/products')
  await page.getByRole('button', { name: editName }).click()
  const dialog = page.getByRole('dialog')
  await expect(dialog.locator('article').getByRole('status')).toHaveText(
    'Не удалось загрузить изображение',
  )
  await page.screenshot({
    path: '.tmp/product-photo-review/modal-fallback.png',
  })
  expect((await new AxeBuilder({ page }).analyze()).violations).toEqual([])
})

test('photo dropzone accepts file drops, shows selection and rejects unsupported or oversized files', async ({
  page,
}) => {
  await mockCatalogApi(page)
  await page.goto('/products')
  await page.getByRole('button', { name: addName }).click()
  const dialog = page.getByRole('dialog')
  const zone = dialog.getByRole('button', {
    name: /Выберите или перетащите фото|Отпустите фото/,
    exact: true,
  })
  await expect(zone).toBeVisible()
  const transfer = await page.evaluateHandle(() => {
    const data = new DataTransfer()
    data.items.add(
      new File(['photo'], 'dropped-tile.jpg', { type: 'image/jpeg' }),
    )
    return data
  })
  await zone.dispatchEvent('dragenter', { dataTransfer: transfer })
  await zone.dispatchEvent('dragover', { dataTransfer: transfer })
  await expect(zone).toContainText('Отпустите фото для добавления')
  await page.screenshot({
    path: '.tmp/product-photo-upload-review/drop-active.png',
  })
  await zone.dispatchEvent('drop', { dataTransfer: transfer })
  await expect(dialog.locator('output')).toHaveText('dropped-tile.jpg')
  await expect(dialog.locator('article')).toHaveCount(0)
  await page.screenshot({
    path: '.tmp/product-photo-upload-review/file-selected.png',
  })
  await dialog.getByRole('button', { name: 'Загрузить', exact: true }).click()
  await expect(dialog.locator('article')).toHaveCount(1)
  for (const file of [
    {
      name: 'notes.txt',
      type: 'text/plain',
      size: 1,
      message: 'Выберите фото в формате JPG, PNG или WebP.',
    },
    {
      name: 'large.jpg',
      type: 'image/jpeg',
      size: 10 * 1024 * 1024 + 1,
      message: 'Размер фото не должен превышать 10 МБ.',
    },
  ]) {
    const invalid = await page.evaluateHandle(({ name, type, size }) => {
      const data = new DataTransfer()
      data.items.add(new File([new Uint8Array(size)], name, { type }))
      return data
    }, file)
    await zone.dispatchEvent('drop', { dataTransfer: invalid })
    await expect(page.getByText(file.message, { exact: true })).toBeVisible()
    await expect(
      dialog.getByRole('button', { name: 'Загрузить', exact: true }),
    ).toHaveCount(0)
    await expect(dialog.locator('article')).toHaveCount(1)
    await invalid.dispose()
  }
  const multiple = await page.evaluateHandle(() => {
    const data = new DataTransfer()
    for (const name of ['one.jpg', 'two.jpg'])
      data.items.add(new File(['photo'], name, { type: 'image/jpeg' }))
    return data
  })
  await zone.dispatchEvent('drop', { dataTransfer: multiple })
  await expect(
    page.getByText('Выберите одно фото за раз.', { exact: true }),
  ).toBeVisible()
  await transfer.dispose()
  await multiple.dispose()
})
