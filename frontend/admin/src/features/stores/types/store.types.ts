export type WorkingHour = {
  weekday: number
  is_closed: boolean
  opens_at: string | null
  closes_at: string | null
}

export type Store = {
  id: number
  name: string
  address: string
  phone: string | null
  is_published: boolean
  working_hours: WorkingHour[]
  created_at: string
  updated_at: string
}

export type StorePayload = Pick<
  Store,
  'name' | 'address' | 'phone' | 'is_published'
>
