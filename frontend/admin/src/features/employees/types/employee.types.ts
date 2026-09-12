export type EmployeeRole = { id: number; name: string; slug: string }

export type Employee = {
  id: number
  name: string
  email: string
  status: 'active' | 'blocked'
  last_login_at: string | null
  roles: EmployeeRole[]
}

export type EmployeePayload = {
  name: string
  email: string
  password?: string
  password_confirmation?: string
  status: 'active' | 'blocked'
  role_ids: number[]
}

export type EmployeePage = {
  data: Employee[]
  meta: { current_page: number; last_page: number; total: number }
}
