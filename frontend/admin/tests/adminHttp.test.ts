import { afterEach, expect, test, vi } from 'vitest'
import { apiFetch } from '../src/services/api'
import { login, logout } from '../src/services/auth'

const csrfPath = '/sanctum/csrf-cookie'

function mockFetch() {
  const fetch = vi.fn<typeof globalThis.fetch>()
  vi.stubGlobal('fetch', fetch)
  return fetch
}

afterEach(() => {
  document.cookie = 'XSRF-TOKEN=; Max-Age=0; Path=/'
  vi.unstubAllGlobals()
})

test.each([
  [401, 'unauthenticated'],
  [403, 'forbidden'],
  [419, 'http_error'],
  [422, 'validation_failed'],
  [429, 'too_many_requests'],
])(
  'preserves the backend error status %i and code %s',
  async (status, code) => {
    const details =
      status === 422
        ? { name: ['Укажите название.', 'Слишком длинное название.'] }
        : {}
    mockFetch().mockResolvedValueOnce(
      Response.json(
        {
          error: {
            code,
            message: 'Сообщение сервера.',
            details: status === 422 ? details : [],
          },
        },
        { status },
      ),
    )

    await expect(apiFetch('/admin/resource')).rejects.toMatchObject({
      name: 'ApiError',
      status,
      code,
      details,
      message: status === 422 ? 'Укажите название.' : 'Сообщение сервера.',
    })
  },
)

test.each([
  '<html>Bad Gateway</html>',
  'null',
  '{"error":{"message":42,"details":{"name":false}}}',
])(
  'returns a typed fallback when an error response is malformed (%s)',
  async (body) => {
    mockFetch().mockResolvedValueOnce(new Response(body, { status: 502 }))
    await expect(apiFetch('/admin/resource')).rejects.toMatchObject({
      name: 'ApiError',
      status: 502,
      code: 'http_error',
      details: {},
      message: 'Не удалось выполнить запрос.',
    })
  },
)

test('normalizes a network failure without an HTTP status', async () => {
  mockFetch().mockRejectedValueOnce(new TypeError('Failed to fetch'))
  await expect(apiFetch('/admin/resource')).rejects.toMatchObject({
    name: 'ApiError',
    status: null,
    code: 'network_error',
    details: {},
    message: 'Не удалось подключиться к серверу. Попробуйте ещё раз.',
  })
})

test('preserves a fetch cancellation and forwards its signal', async () => {
  const abort = new AbortController()
  const fetch = mockFetch().mockImplementationOnce(async (_url, init) => {
    expect(init?.signal).toBe(abort.signal)
    abort.abort()
    throw abort.signal.reason
  })
  await expect(
    apiFetch('/admin/resource', { signal: abort.signal }),
  ).rejects.toBe(abort.signal.reason)
  expect(fetch).toHaveBeenCalledOnce()
})

test('preserves cancellation while reading the HTTP error body', async () => {
  const abort = new AbortController()
  const response = new Response(null, { status: 403 })
  vi.spyOn(response, 'json').mockImplementationOnce(async () => {
    abort.abort()
    throw abort.signal.reason
  })
  mockFetch().mockResolvedValueOnce(response)

  const result = apiFetch('/admin/resource', { signal: abort.signal })
  await expect(result).rejects.toMatchObject({ name: 'AbortError' })
})

test('shares an in-flight CSRF request across writes and reads the decoded token after it completes', async () => {
  const csrf = Promise.withResolvers<Response>()
  const fetch = mockFetch()
    .mockReturnValueOnce(csrf.promise)
    .mockImplementation(async () => new Response(null, { status: 204 }))
  const requests = Promise.all([logout(), logout()])

  expect(fetch).toHaveBeenCalledOnce()
  expect(String(fetch.mock.calls[0]?.[0])).toMatch(/\/sanctum\/csrf-cookie$/)
  expect(fetch.mock.calls[0]?.[1]?.credentials).toBe('include')
  document.cookie = 'XSRF-TOKEN=secret%3D; Path=/'
  csrf.resolve(new Response(null, { status: 204 }))

  await requests
  expect(fetch).toHaveBeenCalledTimes(3)
  for (const [, init] of fetch.mock.calls.slice(1)) {
    expect(init?.credentials).toBe('include')
    expect(new Headers(init?.headers).get('X-XSRF-TOKEN')).toBe('secret=')
  }
})

test('blocks the write after a CSRF HTTP failure and releases initialization for the next request', async () => {
  const urls: string[] = []
  mockFetch().mockImplementation(async (url) => {
    const path = new URL(String(url), window.location.origin).pathname
    urls.push(path)
    if (urls.length === 1) return new Response(null, { status: 503 })
    return new Response(null, { status: 204 })
  })

  await expect(logout()).rejects.toMatchObject({
    status: 503,
    code: 'http_error',
  })
  expect(urls).toEqual([csrfPath])
  await expect(logout()).resolves.toBeUndefined()
  expect(urls).toEqual([csrfPath, csrfPath, '/api/v1/admin/auth/logout'])
})

test('reinitializes CSRF on the next write after 419 without replaying the failed mutation', async () => {
  const fetch = mockFetch()
    .mockResolvedValueOnce(new Response(null, { status: 204 }))
    .mockResolvedValueOnce(
      Response.json({ error: { code: 'http_error' } }, { status: 419 }),
    )
    .mockResolvedValueOnce(new Response(null, { status: 204 }))
    .mockResolvedValueOnce(new Response(null, { status: 204 }))

  await expect(logout()).rejects.toMatchObject({
    status: 419,
    code: 'http_error',
  })
  expect(fetch).toHaveBeenCalledTimes(2)
  await expect(logout()).resolves.toBeUndefined()
  expect(
    fetch.mock.calls.map(
      ([url]) => new URL(String(url), window.location.origin).pathname,
    ),
  ).toEqual([
    csrfPath,
    '/api/v1/admin/auth/logout',
    csrfPath,
    '/api/v1/admin/auth/logout',
  ])
})

test('aborting a write waiting on CSRF leaves the other write active', async () => {
  const csrf = Promise.withResolvers<Response>()
  const fetch = mockFetch()
    .mockReturnValueOnce(csrf.promise)
    .mockResolvedValue(new Response(null, { status: 204 }))
  const abort = new AbortController()
  const cancelled = apiFetch('/admin/resource', {
    method: 'POST',
    signal: abort.signal,
  })
  const active = logout()
  abort.abort(new Error('Отменено пользователем'))

  await expect(cancelled).rejects.toBe(abort.signal.reason)
  expect(fetch).toHaveBeenCalledOnce()
  csrf.resolve(new Response(null, { status: 204 }))
  await active
  expect(fetch).toHaveBeenCalledTimes(2)
})

test('login keeps the server message while retaining validation details', async () => {
  const details = { email: ['Укажите адрес.'] }
  mockFetch()
    .mockResolvedValueOnce(new Response(null, { status: 204 }))
    .mockResolvedValueOnce(
      Response.json(
        {
          error: {
            code: 'validation_failed',
            message: 'Ошибка входа.',
            details,
          },
        },
        { status: 422 },
      ),
    )
  await expect(login('', '')).rejects.toMatchObject({
    message: 'Ошибка входа.',
    details,
  })
})
