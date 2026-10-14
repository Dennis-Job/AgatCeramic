import AxeBuilder from './approvedAccessibility'
import { expect, test, type Page } from './fixtures'
import { mockAdminBaseline } from './adminBaselineApi'
import { brand } from './catalogApi'
import { createDeferredApiRequests } from './deferredApi'

const document = {
  id: 78,
  kind: 'document',
  title: 'Каталог плитки',
  url: '/catalog.pdf',
  thumbnail_url: null,
  alt: null,
  mime_type: 'application/pdf',
  size: 100,
  width: null,
  height: null,
  created_at: brand.created_at,
  updated_at: brand.updated_at,
}
const uploaded = { ...document, id: 79, title: 'Новый каталог бренда' }
const pdf = {
  name: 'catalog.pdf',
  mimeType: 'application/pdf',
  buffer: Buffer.from('%PDF-1.7\n%%EOF'),
}

async function mockDocuments(page: Page) {
  await mockAdminBaseline(page)
  const deferred = createDeferredApiRequests()
  const saves: Record<string, unknown>[] = []
  const uploads: string[] = []
  let failNext = false
  await page.route('**/api/v1/admin/brands**', (route) => {
    if (['POST', 'PATCH'].includes(route.request().method())) {
      const payload = route.request().postDataJSON()
      saves.push(payload)
      return route.fulfill({ json: { data: { ...brand, ...payload } } })
    }
    return route.fulfill({
      json: {
        data: [{ ...brand, documents: [document] }],
        meta: { current_page: 1, last_page: 1, per_page: 25, total: 1 },
      },
    })
  })
  await page.route('**/api/v1/admin/media**', async (route) => {
    if (route.request().method() === 'POST') {
      uploads.push(route.request().postData() ?? '')
      await deferred.wait('upload')
      if (failNext) {
        failNext = false
        return route.fulfill({
          status: 422,
          json: { error: { message: 'Не удалось загрузить документ.' } },
        })
      }
      return route.fulfill({ status: 201, json: { data: uploaded } })
    }
    const docs =
      new URL(route.request().url()).searchParams.get('kind') === 'document'
    return route.fulfill({
      json: {
        data: docs ? [document] : [],
        meta: {
          current_page: 1,
          last_page: 1,
          per_page: 100,
          total: docs ? 1 : 0,
        },
      },
    })
  })
  return {
    saves,
    uploads,
    deferred,
    failUpload: () => {
      failNext = true
    },
  }
}

async function openForm(page: Page, edit = false) {
  await page.goto('/brands')
  await page
    .getByRole('button', {
      name: edit ? `Редактировать бренд ${brand.name}` : 'Добавить бренд',
      exact: true,
    })
    .click()
  const dialog = page.getByRole('dialog', {
    name: edit ? 'Редактирование бренда' : 'Новый бренд',
    exact: true,
  })
  const file = dialog.getByLabel(/^Загрузить файл — Документы бренда/)
  await expect(file).toBeEnabled()
  return {
    dialog,
    file,
    save: dialog.getByRole('button', { name: 'Сохранить', exact: true }),
  }
}

for (const edit of [false, true]) {
  test(`brand ${edit ? 'edit' : 'create'} uploads PDF and saves its attachment`, async ({
    page,
  }) => {
    const api = await mockDocuments(page)
    const { dialog, file, save } = await openForm(page, edit)
    await dialog
      .getByRole('textbox', { name: /^Название/ })
      .fill('Бренд с каталогом')
    await file.setInputFiles(pdf)
    await expect(save).toBeDisabled()
    await dialog
      .getByRole('textbox', { name: /^Название файла/ })
      .fill(uploaded.title)
    const request = api.deferred.defer('upload')
    await dialog
      .getByRole('button', { name: 'Загрузить и выбрать', exact: true })
      .click()
    await request.requested
    await expect(
      dialog.getByRole('button', {
        name: 'Перетащите PDF или нажмите для добавления',
        exact: true,
      }),
    ).toBeDisabled()
    await page.keyboard.press('Escape')
    await expect(dialog).toBeVisible()
    await expect(save).toBeDisabled()
    request.release()
    await expect(
      dialog.getByRole('checkbox', {
        name: `${uploaded.title}, файл 79`,
        exact: true,
      }),
    ).toBeChecked()
    await expect(
      dialog
        .getByRole('status')
        .filter({ hasText: 'Документ загружен и выбран.' }),
    ).toBeVisible()
    expect(api.uploads[0]).toContain('name="kind"\r\n\r\ndocument')
    expect(api.uploads[0]).toContain('filename="catalog.pdf"')
    expect(api.uploads[0]).toContain(uploaded.title)
    await save.click()
    await expect(dialog).toBeHidden()
    expect(api.saves[0]).toMatchObject({
      name: 'Бренд с каталогом',
      document_ids: edit ? [78, 79] : [79],
    })
  })
}

test('PDF upload failure retains selection for retry and cancel clears pending state', async ({
  page,
  browserIssueGuard,
}) => {
  browserIssueGuard.allowApiError(422, '/api/v1/admin/media')
  const api = await mockDocuments(page)
  const { dialog, file, save } = await openForm(page)
  await file.setInputFiles(pdf)
  api.failUpload()
  const upload = dialog.getByRole('button', {
    name: 'Загрузить и выбрать',
    exact: true,
  })
  await upload.click()
  await expect(dialog.getByRole('alert')).toContainText(
    'Не удалось загрузить документ.',
  )
  await expect(save).toBeDisabled()
  await page.screenshot({ path: '../../.tmp/brand-documents/error.png' })
  await upload.click()
  await expect(save).toBeEnabled()
  await expect(
    dialog.getByRole('checkbox', {
      name: `${uploaded.title}, файл 79`,
      exact: true,
    }),
  ).toBeChecked()
  await page.screenshot({
    path: '../../.tmp/brand-documents/success.png',
    animations: 'disabled',
  })
  await file.setInputFiles(pdf)
  await dialog
    .getByRole('button', { name: 'Отменить выбор файла', exact: true })
    .click()
  await expect(save).toBeEnabled()
  await expect(
    dialog.getByRole('button', {
      name: 'Перетащите PDF или нажмите для добавления',
      exact: true,
    }),
  ).toBeFocused()
  await file.setInputFiles(pdf)
  await page.keyboard.press('Escape')
  await expect(dialog).toBeHidden()
  await page
    .getByRole('button', { name: 'Добавить бренд', exact: true })
    .click()
  await expect(save).toBeEnabled()
  await expect(
    dialog.getByRole('textbox', { name: /^Название файла/ }),
  ).toBeHidden()
  expect(api.uploads).toHaveLength(2)
})

test('rejects unsupported files and leaves existing documents selectable without upload permission', async ({
  page,
}) => {
  const api = await mockDocuments(page)
  const { dialog, file, save } = await openForm(page)
  await file.setInputFiles({
    name: 'photo.png',
    mimeType: 'image/png',
    buffer: Buffer.from('image'),
  })
  await expect(dialog.getByRole('alert')).toContainText(
    'Выберите PDF размером не больше 20 МБ.',
  )
  await expect(save).toBeEnabled()
  expect(api.uploads).toHaveLength(0)
  await page.route('**/api/v1/admin/auth/me', (route) =>
    route.fulfill({
      json: {
        data: {
          id: 1,
          name: 'Каталог',
          email: 'catalog@example.test',
          status: 'active',
          permissions: ['catalog.manage'],
        },
      },
    }),
  )
  await page.reload()
  await page
    .getByRole('button', { name: 'Добавить бренд', exact: true })
    .click()
  await expect(file).toHaveCount(0)
  const existing = dialog.getByRole('checkbox', {
    name: `${document.title}, файл 78`,
    exact: true,
  })
  await expect(existing).toBeEnabled()
  await existing.focus()
  await page.keyboard.press('Space')
  await expect(existing).toBeChecked()
})

test('cancelling a logo does not unlock save while PDF is still pending', async ({
  page,
}) => {
  await mockDocuments(page)
  const { dialog, file, save } = await openForm(page)
  await file.setInputFiles(pdf)
  await dialog
    .getByLabel('Загрузить файл — Логотип бренда', { exact: true })
    .setInputFiles({
      name: 'logo.png',
      mimeType: 'image/png',
      buffer: Buffer.from(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScL/nwAAAABJRU5ErkJggg==',
        'base64',
      ),
    })
  await dialog
    .getByRole('button', { name: 'Отменить выбор файла', exact: true })
    .first()
    .click()
  await expect(save).toBeDisabled()
  await dialog
    .getByRole('button', { name: 'Отменить выбор файла', exact: true })
    .click()
  await expect(save).toBeEnabled()
})

test('PDF dropzone opens the file chooser from the keyboard, accepts drops and restores focus on cancel', async ({
  page,
}) => {
  const api = await mockDocuments(page)
  const { dialog, save } = await openForm(page)
  const dropzone = dialog.getByRole('button', {
    name: 'Перетащите PDF или нажмите для добавления',
    exact: true,
  })
  await dropzone.focus()
  const chooser = page.waitForEvent('filechooser')
  await page.keyboard.press('Enter')
  await (await chooser).setFiles(pdf)
  await expect(
    dialog.getByRole('status').filter({ hasText: 'catalog.pdf' }),
  ).toBeVisible()
  await expect(save).toBeDisabled()
  await dialog
    .getByRole('button', { name: 'Отменить выбор файла', exact: true })
    .click()
  await expect(dropzone).toBeFocused()
  const dataTransfer = await page.evaluateHandle(() => {
    const transfer = new DataTransfer()
    transfer.items.add(
      new File(['%PDF-1.7\n%%EOF'], 'dropped.pdf', { type: 'application/pdf' }),
    )
    return transfer
  })
  await dropzone.dispatchEvent('drop', { dataTransfer })
  await dataTransfer.dispose()
  await expect(
    dialog.getByRole('textbox', { name: /^Название файла/ }),
  ).toHaveValue('dropped')
  await expect(save).toBeDisabled()
  expect(api.uploads).toHaveLength(0)
})

test('PDF controls fit all required widths and remain accessible', async ({
  page,
}) => {
  await mockDocuments(page)
  for (const width of [320, 640, 768, 1024, 1280]) {
    await page.setViewportSize({ width, height: 720 })
    const { dialog, file, save } = await openForm(page)
    const dropzone = dialog.getByRole('button', {
      name: 'Перетащите PDF или нажмите для добавления',
      exact: true,
    })
    await dropzone.focus()
    if (width === 320 || width === 1280) {
      await page.screenshot({
        path: `../../.tmp/brand-documents/dropzone-${width}.png`,
        animations: 'disabled',
      })
    }
    await file.setInputFiles({
      ...pdf,
      name: `${'ДлинноеНазваниеКаталога'.repeat(8)}.pdf`,
    })
    const title = dialog.getByRole('textbox', { name: /^Название файла/ })
    await title.focus()
    expect(
      await dialog.evaluate((el) => el.scrollWidth <= el.clientWidth),
    ).toBe(true)
    expect(
      await page.evaluate(
        () => document.documentElement.scrollWidth <= innerWidth,
      ),
    ).toBe(true)
    await expect(save).toBeInViewport()
    if (width === 320 || width === 1280) {
      expect((await new AxeBuilder({ page }).analyze()).violations).toEqual([])
      await page.screenshot({
        path: `../../.tmp/brand-documents/pending-${width}.png`,
      })
    }
    await page.keyboard.press('Escape')
    await expect(dialog).toBeHidden()
  }
})
