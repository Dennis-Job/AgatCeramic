export interface OrderDetails {
  customer_name: string
  customer_phone: string
  customer_email: string
  delivery_address: string
  customer_comment: string | null
}

export interface OrderReceipt {
  order_number: string
  total_amount: string
  items: {
    product_id: number | null
    product_name: string
    quantity: number
    line_total: string
  }[]
}

export class CheckoutError extends Error {
  constructor(
    message: string,
    readonly step?: 1 | 2,
  ) {
    super(message)
  }
}

export async function createGuestOrder(
  base: string,
  token: string,
  key: string,
  body: OrderDetails,
): Promise<OrderReceipt> {
  const url = new URL('orders', base.endsWith('/') ? base : base + '/')
  try {
    const { data } = await $fetch<{ data: OrderReceipt }>(url.toString(), {
      method: 'POST',
      headers: { 'X-Cart-Token': token, 'Idempotency-Key': key },
      body,
      credentials: 'omit',
      retry: 0,
      timeout: 15000,
    })
    if (!data?.order_number || !Array.isArray(data.items))
      throw new Error('Invalid order response')
    return data
  } catch (failure) {
    const error = failure as {
      statusCode?: number
      data?: { error?: { details?: Record<string, unknown> } }
    }
    const details = error?.data?.error?.details
    if (error?.statusCode === 422) {
      if (details?.customer_name) throw new CheckoutError('Проверьте ФИО.', 1)
      if (details?.customer_phone)
        throw new CheckoutError('Проверьте номер телефона.', 1)
      if (details?.customer_email)
        throw new CheckoutError('Проверьте адрес электронной почты.', 1)
      if (details?.delivery_address || details?.customer_comment)
        throw new CheckoutError('Проверьте адрес доставки и комментарий.', 2)
      throw new CheckoutError(
        'Не удалось оформить заказ. Проверьте наличие и количество товаров в корзине.',
      )
    }
    if (error?.statusCode === 404)
      throw new CheckoutError(
        'Корзина больше недоступна. Откройте её и проверьте товары.',
      )
    if (error?.statusCode === 429)
      throw new CheckoutError('Слишком много запросов. Попробуйте чуть позже.')
    // Fetch errors can contain the token and the customer's contact information.
    throw new CheckoutError(
      'Не удалось получить подтверждение. Повторите отправку с теми же данными.',
    )
  }
}
