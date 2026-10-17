import { expect, test } from './fixtures'
import AxeBuilder from './approvedAccessibility'
import { mockContentWorkspace } from './contentWorkspaceApi'
import type { ContentPage } from '../src/features/pages/types/page.types'

const timestamp = '2026-09-25T10:00:00Z'

test('new page dialog keeps actions reachable while its fields scroll', async ({
  page,
}) => {
  await mockContentWorkspace(page)
  await page.goto('/content')
  const opener = page.getByRole('button', { name: 'Добавить страницу' })
  await opener.click()
  const dialog = page.getByRole('dialog', { name: 'Новая страница' })
  const cancel = dialog.getByRole('button', { name: 'Отмена', exact: true })
  const save = dialog.getByRole('button', {
    name: 'Сохранить черновик',
    exact: true,
  })
  for (const width of [320, 640, 768, 1024, 1280]) {
    await page.setViewportSize({ width, height: 600 })
    await expect(cancel).toBeInViewport()
    await expect(save).toBeInViewport()
    const cancelBox = (await cancel.boundingBox())!
    const saveBox = (await save.boundingBox())!
    expect(Math.abs(cancelBox.y - saveBox.y)).toBeLessThan(1)
    expect(
      await dialog.evaluate((el) => el.scrollWidth <= el.clientWidth),
    ).toBe(true)
    const bounds = (await dialog.boundingBox())!
    expect(bounds.y).toBeGreaterThanOrEqual(0)
    expect(bounds.y + bounds.height).toBeLessThanOrEqual(600)
    await page.screenshot({
      path: test.info().outputPath(`page-dialog-${width}.png`),
    })
  }
  await page.setViewportSize({ width: 1280, height: 400 })
  const scrollArea = dialog
    .locator('div')
    .filter({ has: page.getByLabel('Заголовок') })
    .last()
  // Scroll the form area beside the controls, leaving the action row fixed.
  const area = (await scrollArea.boundingBox())!
  const before = (await save.boundingBox())!
  await page.mouse.move(area.x + 2, area.y + area.height / 2)
  await page.mouse.wheel(0, 800)
  await expect
    .poll(() =>
      dialog.evaluate((el) =>
        Array.from(el.querySelectorAll('div')).some((n) => n.scrollTop > 0),
      ),
    )
    .toBe(true)
  await expect(save).toBeInViewport()
  expect((await save.boundingBox())!.y).toBe(before.y)
  await page.screenshot({
    path: test.info().outputPath('page-dialog-short.png'),
  })
  await page.keyboard.press('Escape')
  await expect(dialog).toHaveCount(0)
  await expect(opener).toBeFocused()
  await opener.click()
  await cancel.click()
  await expect(opener).toBeFocused()
})

test('content editor creates a draft, publishes it, and deletes it', async ({
  page,
  browserIssueGuard,
}) => {
  let storedPage: ContentPage | null = null
  let publishedBody: string | null = null
  let publishFails = true
  const submittedPublicationStates: unknown[] = []
  browserIssueGuard.allowApiError(422, '/api/v1/admin/pages/7/publish')

  await page.route('**/sanctum/csrf-cookie', (route) =>
    route.fulfill({ status: 204 }),
  )
  await page.route('**/api/v1/**', (route) => {
    const path = new URL(route.request().url()).pathname.replace('/api/v1', '')
    if (path === '/admin/auth/me')
      return route.fulfill({
        json: {
          data: {
            id: 1,
            name: 'Редактор',
            email: 'editor@example.test',
            status: 'active',
            permissions: ['content.manage'],
          },
        },
      })
    if (path === '/admin/pages' && route.request().method() === 'GET')
      return route.fulfill({
        json: {
          data: storedPage ? [storedPage] : [],
          meta: {
            current_page: 1,
            last_page: 1,
            per_page: 25,
            total: storedPage ? 1 : 0,
          },
        },
      })
    if (path === '/admin/pages' && route.request().method() === 'POST') {
      const payload = route.request().postDataJSON()
      submittedPublicationStates.push(payload.is_published)
      storedPage = {
        id: 7,
        ...payload,
        is_published: false,
        has_unpublished_changes: true,
        published_at: null,
        created_at: timestamp,
        updated_at: timestamp,
      }
      return route.fulfill({ status: 201, json: { data: storedPage } })
    }
    if (path === '/admin/pages/7' && route.request().method() === 'PATCH') {
      const payload = route.request().postDataJSON()
      submittedPublicationStates.push(payload.is_published)
      storedPage = {
        ...storedPage!,
        ...payload,
        has_unpublished_changes: true,
      }
      return route.fulfill({ json: { data: storedPage } })
    }
    if (
      path === '/admin/pages/7/publish' &&
      route.request().method() === 'POST'
    ) {
      if (publishFails) {
        publishFails = false
        return route.fulfill({
          status: 422,
          json: { error: { message: 'Проверьте черновик перед публикацией.' } },
        })
      }
      publishedBody = storedPage!.body
      storedPage = {
        ...storedPage!,
        is_published: true,
        has_unpublished_changes: false,
        published_at: timestamp,
      }
      return route.fulfill({ json: { data: storedPage } })
    }
    if (path === '/admin/pages/7' && route.request().method() === 'DELETE') {
      storedPage = null
      return route.fulfill({ status: 204 })
    }
    return route.fulfill({
      status: 404,
      json: { error: { message: 'Unknown endpoint' } },
    })
  })

  await page.goto('/content')
  await expect(page.getByText('Страниц пока нет.')).toBeVisible()
  await page.getByRole('button', { name: 'Добавить страницу' }).click()
  await page.getByLabel('Заголовок').fill('О компании')
  await page.getByLabel('Адрес страницы (slug)').fill('company')
  await page.getByLabel('Текст страницы').fill('История компании.')
  await page
    .getByRole('button', { name: 'Сохранить черновик', exact: true })
    .click()
  await expect(page.getByText('Черновик', { exact: true })).toBeVisible()
  expect(publishedBody).toBeNull()

  await page
    .getByRole('button', { name: 'Опубликовать блоки страницы' })
    .click()
  await expect(page.getByRole('alert')).toHaveText(
    'Проверьте черновик перед публикацией.',
  )
  await page
    .getByRole('button', { name: 'Опубликовать блоки страницы' })
    .click()
  await expect(page.getByText('Опубликована', { exact: true })).toBeVisible()
  expect(publishedBody).toBe('История компании.')

  await page.getByRole('tab', { name: 'Настройки', exact: true }).click()
  await page.getByLabel('Текст страницы').fill('Новая история компании.')
  await page
    .getByRole('button', { name: 'Сохранить черновик блоков', exact: true })
    .click()
  await expect(
    page.getByText('В черновике есть неопубликованные изменения.'),
  ).toBeVisible()
  expect(publishedBody).toBe('История компании.')
  await page
    .getByRole('button', { name: 'Опубликовать блоки страницы' })
    .click()
  await expect(page.getByText('Опубликована', { exact: true })).toBeVisible()
  await expect(
    page.getByRole('button', { name: 'Опубликовать блоки страницы' }),
  ).toBeDisabled()
  // The published badge is already visible and busy also disables this button;
  // wait for the publication request to apply before asserting its snapshot.
  await expect.poll(() => publishedBody).toBe('Новая история компании.')
  expect(submittedPublicationStates).toEqual([undefined, undefined])
  await page.screenshot({
    path: test.info().outputPath('page-published.png'),
    fullPage: true,
    animations: 'disabled',
  })

  expect((await new AxeBuilder({ page }).analyze()).violations).toEqual([])

  for (const width of [320, 640, 768, 1024, 1280, 2560]) {
    await page.setViewportSize({ width, height: 900 })
    const overflow = await page.evaluate(
      () =>
        document.documentElement.scrollWidth >
        document.documentElement.clientWidth,
    )
    expect(overflow, `horizontal overflow at ${width}px`).toBe(false)
    await page.screenshot({
      path: test.info().outputPath(`page-published-${width}.png`),
      fullPage: true,
      animations: 'disabled',
    })
  }
  const navigation = await page
    .getByRole('navigation', { name: 'Выбор страницы сайта' })
    .boundingBox()
  const preview = await page
    .getByRole('region', { name: 'Предпросмотр', exact: true })
    .boundingBox()
  expect(navigation).not.toBeNull()
  expect(preview).not.toBeNull()
  expect(preview!.x).toBeGreaterThan(navigation!.x + navigation!.width)

  await page.getByRole('button', { name: 'Снять с публикации' }).click()
  await expect(page.getByText('Черновик', { exact: true })).toBeVisible()
  expect(submittedPublicationStates).toEqual([undefined, undefined, false])

  await page.getByRole('tab', { name: 'Настройки', exact: true }).click()
  await page
    .getByRole('button', { name: 'Удалить страницу О компании' })
    .click()
  await page
    .getByRole('dialog', { name: 'Удалить страницу?' })
    .getByRole('button', { name: 'Удалить' })
    .click()
  await expect(page.getByText('Страниц пока нет.')).toBeVisible()
})

test('content editor opens a page outside the current list from its URL', async ({
  page,
}) => {
  await page.route('**/api/v1/**', (route) => {
    const path = new URL(route.request().url()).pathname.replace('/api/v1', '')
    if (path === '/admin/auth/me')
      return route.fulfill({
        json: {
          data: {
            id: 1,
            name: 'Редактор',
            email: 'editor@example.test',
            status: 'active',
            permissions: ['content.manage'],
          },
        },
      })
    if (path === '/admin/pages')
      return route.fulfill({
        json: {
          data: [],
          meta: { current_page: 1, last_page: 2, per_page: 25, total: 26 },
        },
      })
    if (path === '/admin/pages/77')
      return route.fulfill({
        json: {
          data: {
            id: 77,
            title: 'Доставка',
            slug: 'delivery',
            body: 'Информация о доставке.',
            is_published: false,
            has_unpublished_changes: true,
            published_at: null,
            created_at: timestamp,
            updated_at: timestamp,
          },
        },
      })
    return route.fulfill({
      status: 404,
      json: { error: { message: 'Unknown endpoint' } },
    })
  })

  await page.goto('/content?page=77')
  await expect(page.getByRole('heading', { name: 'Доставка' })).toBeVisible()
  await page.getByRole('tab', { name: 'Настройки', exact: true }).click()
  await expect(
    page.getByRole('textbox', { name: /^Текст страницы/ }),
  ).toHaveValue('Информация о доставке.')
  await expect(page.getByText('Страниц пока нет.')).toHaveCount(0)
})
