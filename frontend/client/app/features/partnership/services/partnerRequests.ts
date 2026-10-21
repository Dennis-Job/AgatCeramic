export interface PartnerRequestPayload {
  name: string
  phone: string
  email: string
  message: string
}

export async function submitPartnerRequest(
  base: string,
  payload: PartnerRequestPayload,
): Promise<void> {
  const url = new URL(
    'partner-requests',
    base.endsWith('/') ? base : `${base}/`,
  )
  try {
    await $fetch(url.toString(), { method: 'POST', body: payload, retry: 0 })
  } catch (error) {
    const failure = error as {
      statusCode?: number
      data?: {
        error?: {
          details?: Partial<Record<keyof PartnerRequestPayload, unknown>>
        }
      }
    } | null
    const fields = failure?.data?.error?.details
    const fieldMessage = fields?.email
      ? 'Проверьте email: укажите корректный адрес до 255 символов.'
      : fields?.phone
        ? 'Проверьте номер телефона: введите 10 цифр после +7.'
        : fields?.name
          ? 'Проверьте имя: обязательное поле, не более 255 символов.'
          : fields?.message
            ? 'Проверьте предложение: обязательное поле, не более 2000 символов.'
            : 'Проверьте имя, телефон, email и предложение.'
    throw new Error(
      failure?.statusCode === 429
        ? 'Слишком много заявок. Попробуйте отправить предложение позже.'
        : failure?.statusCode === 422
          ? fieldMessage
          : 'Не удалось отправить предложение. Попробуйте ещё раз или напишите нам на email для сотрудничества.',
    )
  }
}
