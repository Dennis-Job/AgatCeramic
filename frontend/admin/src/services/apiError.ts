type ApiErrorDetails = Record<string, string[]>

export class ApiError extends Error {
  readonly status: number | null
  readonly code: string
  readonly details: ApiErrorDetails

  constructor(
    message: string,
    status: number | null,
    code: string,
    details: ApiErrorDetails = {},
  ) {
    super(message)
    this.name = 'ApiError'
    this.status = status
    this.code = code
    this.details = details
  }
}

export type ApiErrorOptions = {
  errorMessage?: string
  errorMessageSource?: 'server' | 'fallback' | 'all-details'
}

export function rethrowAbort(
  error: unknown,
  signal?: AbortSignal | null,
): void {
  signal?.throwIfAborted()
  if (error instanceof Error && error.name === 'AbortError') throw error
}

export async function decodeApiError(
  response: Response,
  options: ApiErrorOptions = {},
  signal?: AbortSignal | null,
): Promise<ApiError> {
  const body = (await response.json().catch((error: unknown) => {
    rethrowAbort(error, signal)
    return null
  })) as {
    error?: { code?: unknown; message?: unknown; details?: unknown }
  } | null
  signal?.throwIfAborted()

  const error = body?.error
  const fields = error?.details
  const details = Object.fromEntries(
    Object.entries(
      fields && typeof fields === 'object' && !Array.isArray(fields)
        ? fields
        : {},
    ).filter((entry): entry is [string, string[]] => {
      const messages: unknown = entry[1]
      return (
        Array.isArray(messages) &&
        messages.every((message) => typeof message === 'string')
      )
    }),
  )
  const messages = Object.values(details).flat()
  const fallback = options.errorMessage ?? 'Не удалось выполнить запрос.'
  const serverMessage =
    typeof error?.message === 'string' ? error.message : fallback

  let message = serverMessage
  switch (options.errorMessageSource) {
    case 'fallback':
      message = fallback
      break
    case 'all-details':
      message = messages.join(' ') || serverMessage
      break
    case undefined:
      message = messages[0] ?? serverMessage
  }

  return new ApiError(
    message,
    response.status,
    typeof error?.code === 'string' ? error.code : 'http_error',
    details,
  )
}
