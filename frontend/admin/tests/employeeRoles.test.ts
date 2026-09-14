import { mount } from '@vue/test-utils'
import { describe, expect, test } from 'vitest'
import EmployeesList from '../src/features/employees/components/EmployeesList.vue'
import * as employeeServices from '../src/features/employees/services/employees'

describe('employee roles', () => {
  test('renders the backend role name for a known system slug', () => {
    const wrapper = mount(EmployeesList, {
      props: {
        employees: [
          {
            id: 1,
            name: 'Иван Петров',
            email: 'ivan@example.test',
            status: 'active',
            last_login_at: null,
            roles: [
              { id: 7, name: 'Руководитель доступа', slug: 'administrator' },
            ],
          },
        ],
        loading: false,
        canManage: false,
        canEdit: false,
      },
    })

    expect(wrapper.text()).toContain('Руководитель доступа')
    expect(wrapper.text()).not.toMatch(/(^|\s)Администратор($|\s)/)
  })

  test('exports only the employee-specific roles loader', () => {
    expect(employeeServices.getEmployeeRoles).toBeTypeOf('function')
    expect('getRoles' in employeeServices).toBe(false)
  })
})
