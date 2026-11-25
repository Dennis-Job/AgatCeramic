import {
  ApiError,
  decodeApiError,
  rethrowAbort,
  type ApiErrorOptions,
} from './apiError'

const apiBaseUrl = (
  import.meta.env.VITE_API_BASE_URL ?? 'http://localhost:8000/api/v1'
).replace(/\/$/, '')
const backendBaseUrl = apiBaseUrl.replace(/\/api\/v1$/, '')

type ApiRequestInit = RequestInit & ApiErrorOptions

async function fetchWithCredentials(
  url: string,
  init: RequestInit,
): Promise<Response> {
  const headers = new Headers(init.headers)
  headers.set('Accept', 'application/json')

  try {
    return await fetch(url, { ...init, credentials: 'include', headers })
  } catch (error) {
    rethrowAbort(error, init.signal)
    throw new ApiError(
      'Не удалось подключиться к серверу. Попробуйте ещё раз.',
      null,
      'network_error',
    )
  }
}

async function initializeCsrf(): Promise<void> {
  const response = await fetchWithCredentials(
    `${backendBaseUrl}/sanctum/csrf-cookie`,
    {},
  )
  if (!response.ok)
    throw await decodeApiError(response, {
      errorMessage: 'Не удалось подготовить запрос. Попробуйте ещё раз.',
    })
}

let pendingCsrf: Promise<void> | undefined

function requestCsrfCookie(signal?: AbortSignal | null): Promise<void> {
  // Each later write refreshes CSRF, including after logout or an expired session.
  const request = (pendingCsrf ??= initializeCsrf().finally(() => {
    pendingCsrf = undefined
  }))
  if (!signal) return request

  // A caller may stop waiting without cancelling initialization for other writes.
  return new Promise<void>((resolve, reject) => {
    const onAbort = () => reject(signal.reason)
    if (signal.aborted) onAbort()
    else signal.addEventListener('abort', onAbort, { once: true })

    void request.then(resolve, reject).then(() => {
      signal.removeEventListener('abort', onAbort)
    })
  })
}

function csrfToken(): string {
  const prefix = 'XSRF-TOKEN='
  const cookie = document.cookie
    .split(/;\s*/)
    .find((entry) => entry.startsWith(prefix))
  try {
    return cookie ? decodeURIComponent(cookie.slice(prefix.length)) : ''
  } catch {
    return ''
  }
}

export async function apiFetch(
  path: string,
  { errorMessage, errorMessageSource, ...init }: ApiRequestInit = {},
): Promise<Response> {
  const { signal } = init
  signal?.throwIfAborted()
  const method = init.method?.toUpperCase() ?? 'GET'
  if (!['GET', 'HEAD', 'OPTIONS'].includes(method)) {
    await requestCsrfCookie(signal)
    signal?.throwIfAborted()
    const headers = new Headers(init.headers)
    headers.set('X-XSRF-TOKEN', csrfToken())
    init.headers = headers
  }

  const response = await fetchWithCredentials(`${apiBaseUrl}${path}`, init)
  signal?.throwIfAborted()
  if (!response.ok)
    throw await decodeApiError(
      response,
      { errorMessage, errorMessageSource },
      signal,
    )
  return response
}

export async function apiData<T>(
  path: string,
  init?: ApiRequestInit,
): Promise<T> {
  const response = await apiFetch(path, init)
  return ((await response.json()) as { data: T }).data
}
