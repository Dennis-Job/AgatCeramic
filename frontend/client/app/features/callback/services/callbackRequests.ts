export async function submitCallbackRequest(
  base: string,
  payload: { name: string | null; phone: string },
): Promise<void> {
  const url = new URL(
    'callback-requests',
    base.endsWith('/') ? base : `${base}/`,
  )
  try {
    await $fetch(url.toString(), {
      method: 'POST',
      body: payload,
      retry: 0,
    })
  } catch (error) {
    const failure = error as {
      statusCode?: number
      data?: { error?: { details?: { name?: unknown; phone?: unknown } } }
    } | null
    const message =
      failure?.statusCode === 429
        ? 'Слишком много заявок. Попробуйте отправить заявку позже.'
        : failure?.statusCode === 422
          ? failure.data?.error?.details?.name &&
            !failure.data?.error?.details?.phone
            ? 'Проверьте имя: не более 255 символов.'
            : 'Проверьте номер телефона и попробуйте ещё раз.'
          : 'Не удалось отправить заявку. Попробуйте ещё раз или свяжитесь с магазином по телефону.'
    throw new Error(message)
  }
}
