import AxeBuilder from '@axe-core/playwright'
import { expect, test, type Page } from './fixtures'

const user = { id: 1, name: 'Тестовый администратор', email: 'admin@example.test', status: 'active', last_login_at: null, permissions: [] }

async function mockAuthApi(page: Page, options: { loginGate?: Promise<void>; failLogin?: boolean } = {}): Promise<void> {
  let authenticated = false
  await page.route('**/sanctum/csrf-cookie', route => route.fulfill({ status: 204 }))
  await page.route('**/api/v1/admin/auth/**', async (route) => {
    const path = new URL(route.request().url()).pathname
    if (path === '/api/v1/admin/auth/me') {
      await route.fulfill(authenticated ? { json: { data: user } } : { status: 401, json: { error: { message: 'Unauthenticated' } } })
      return
    }
    if (path === '/api/v1/admin/auth/login') {
      if (options.loginGate) await options.loginGate
      if (options.failLogin) {
        await route.fulfill({ status: 422, json: { error: { message: 'Неверный email или пароль.' } } })
        return
      }
      authenticated = true
    }
    await route.fulfill({ status: 204 })
  })
}

test('login preserves credentials contract, autocomplete, keyboard flow and redirect', async ({ page, browserIssueGuard }) => {
  browserIssueGuard.allowApiError(401, '/api/v1/admin/auth/me')
  let releaseLogin!: () => void
  const loginGate = new Promise<void>(resolve => { releaseLogin = resolve })
  await mockAuthApi(page, { loginGate })
  await page.goto('/login')

  const email = page.getByLabel('Email')
  const password = page.getByLabel('Пароль')
  await expect(email).toHaveAttribute('autocomplete', 'username')
  await expect(password).toHaveAttribute('autocomplete', 'current-password')
  await expect(email).toBeFocused()
  await page.keyboard.press('Tab')
  await expect(password).toBeFocused()
  await email.fill('admin@example.test')
  await password.fill('secret-password')
  const loginRequest = page.waitForRequest(request => new URL(request.url()).pathname.endsWith('/admin/auth/login'))
  const submit = page.getByRole('button', { name: 'Войти' })
  await submit.click()
  expect((await loginRequest).postDataJSON()).toEqual({ email: 'admin@example.test', password: 'secret-password' })
  await expect(page.getByRole('button', { name: 'Выполняется вход…' })).toBeDisabled()
  releaseLogin()
  await expect(page).toHaveURL('/')
})

test('login exposes a retryable API error state', async ({ page, browserIssueGuard }) => {
  browserIssueGuard.allowApiError(401, '/api/v1/admin/auth/me')
  browserIssueGuard.allowApiError(422, '/api/v1/admin/auth/login')
  await mockAuthApi(page, { failLogin: true })
  await page.goto('/login')

  await page.getByLabel('Email').fill('employee@example.test')
  await page.getByLabel('Пароль').fill('wrong-password')
  await page.getByRole('button', { name: 'Войти' }).click()
  await expect(page.getByRole('alert')).toContainText('Неверный email или пароль.')
  await expect(page.getByRole('button', { name: 'Войти' })).toBeEnabled()
})

test('forgot-password preserves API contract and success state', async ({ page, browserIssueGuard }) => {
  browserIssueGuard.allowApiError(401, '/api/v1/admin/auth/me')
  await mockAuthApi(page)
  await page.goto('/forgot-password')

  const email = page.getByLabel('Email')
  await expect(email).toHaveAttribute('autocomplete', 'email')
  await expect(email).toBeFocused()
  await email.fill('employee@example.test')
  const resetRequest = page.waitForRequest(request => new URL(request.url()).pathname.endsWith('/admin/auth/forgot-password'))
  await page.getByRole('button', { name: 'Отправить ссылку' }).click()
  expect((await resetRequest).postDataJSON()).toEqual({ email: 'employee@example.test' })
  await expect(page.getByRole('status')).toContainText('ссылка для сброса пароля отправлена')
  await expect(page.getByRole('button', { name: 'Отправить ссылку' })).toBeDisabled()
})

test('reset-password preserves token contract, autocomplete and login success redirect', async ({ page, browserIssueGuard }) => {
  browserIssueGuard.allowApiError(401, '/api/v1/admin/auth/me')
  await mockAuthApi(page)
  await page.goto('/reset-password?email=employee%40example.test&token=reset-token')

  const email = page.getByLabel('Email')
  const password = page.getByLabel('Новый пароль')
  const confirmation = page.getByLabel('Подтверждение пароля')
  await expect(email).toHaveValue('employee@example.test')
  await expect(email).toHaveAttribute('autocomplete', 'email')
  await expect(password).toHaveAttribute('autocomplete', 'new-password')
  await expect(confirmation).toHaveAttribute('autocomplete', 'new-password')
  await expect(password).toBeFocused()
  await password.fill('new-password-123')
  await confirmation.fill('new-password-123')
  const resetRequest = page.waitForRequest(request => new URL(request.url()).pathname.endsWith('/admin/auth/reset-password'))
  await page.getByRole('button', { name: 'Сбросить пароль' }).click()
  expect((await resetRequest).postDataJSON()).toEqual({ email: 'employee@example.test', token: 'reset-token', password: 'new-password-123', password_confirmation: 'new-password-123' })
  await expect(page).toHaveURL('/login?password_reset=1')
  await expect(page.getByRole('status')).toContainText('Пароль изменён')

  const accessibility = await new AxeBuilder({ page }).analyze()
  expect(accessibility.violations.filter(item => ['serious', 'critical'].includes(item.impact ?? ''))).toEqual([])
})
