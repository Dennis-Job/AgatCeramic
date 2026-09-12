import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../../../stores/auth'
import { deleteEmployee, getEmployeeRoles, getEmployees, saveEmployee } from '../services/employees'
import type { Employee, EmployeePayload, EmployeeRole } from '../types/employee.types'

const emptyForm = (): EmployeePayload => ({ name: '', email: '', password: '', password_confirmation: '', status: 'active', role_ids: [] })

export function useEmployeesWorkspace() {
  const auth = useAuthStore()
  const router = useRouter()
  const employees = ref<Employee[]>([])
  const roles = ref<EmployeeRole[]>([])
  const search = ref('')
  const status = ref('')
  const loading = ref(true)
  const rolesLoading = ref(false)
  const rolesReady = ref(false)
  const error = ref('')
  const rolesError = ref('')
  const formError = ref('')
  const deleteError = ref('')
  const isFormOpen = ref(false)
  const editing = ref<Employee | null>(null)
  const deleting = ref<Employee | null>(null)
  const saving = ref(false)
  const isDeleting = ref(false)
  const form = ref<EmployeePayload>(emptyForm())
  const title = computed(() => editing.value ? 'Редактировать сотрудника' : 'Новый сотрудник')
  const canManage = computed(() => auth.hasPermission('admin-users.manage'))
  const canEdit = computed(() => canManage.value && rolesReady.value && !rolesLoading.value && !rolesError.value)

  function openForm(employee: Employee | null = null): void {
    if (!canEdit.value) return
    editing.value = employee
    formError.value = ''
    form.value = employee
      ? { name: employee.name, email: employee.email, status: employee.status, role_ids: employee.roles.map(role => role.id) }
      : emptyForm()
    isFormOpen.value = true
  }

  function closeForm(): void { if (!saving.value) isFormOpen.value = false }

  async function load(): Promise<void> {
    loading.value = true
    error.value = ''
    try { employees.value = (await getEmployees(search.value, status.value)).data }
    catch (reason) { error.value = reason instanceof Error ? reason.message : 'Не удалось загрузить сотрудников.' }
    finally { loading.value = false }
  }

  async function initialize(): Promise<void> {
    loading.value = true
    rolesError.value = ''
    rolesReady.value = false
    if (canManage.value) {
      rolesLoading.value = true
      try { roles.value = await getEmployeeRoles(); rolesReady.value = true }
      catch (reason) { rolesError.value = reason instanceof Error ? reason.message : 'Не удалось загрузить роли.' }
      finally { rolesLoading.value = false }
    }
    await load()
  }

  async function submit(): Promise<void> {
    if (!canEdit.value) return
    formError.value = ''
    saving.value = true
    const changedOwnPassword = Boolean(form.value.password) && editing.value?.id === auth.user?.id
    try {
      await saveEmployee(editing.value?.id ?? null, form.value)
      if (changedOwnPassword) {
        await auth.logout()
        await router.replace({ name: 'login', query: { password_changed: '1' } })
        return
      }
      isFormOpen.value = false
      await load()
    } catch (reason) { formError.value = reason instanceof Error ? reason.message : 'Не удалось сохранить сотрудника.' }
    finally { saving.value = false }
  }

  async function remove(): Promise<void> {
    if (!deleting.value) return
    isDeleting.value = true
    deleteError.value = ''
    try { await deleteEmployee(deleting.value.id); deleting.value = null; await load() }
    catch (reason) { deleteError.value = reason instanceof Error ? reason.message : 'Не удалось удалить сотрудника.' }
    finally { isDeleting.value = false }
  }

  function cancelDelete(): void {
    if (isDeleting.value) return
    deleting.value = null
    deleteError.value = ''
  }

  return { employees, roles, search, status, loading, rolesLoading, rolesReady, error, rolesError, formError, deleteError, isFormOpen, editing, deleting, saving, isDeleting, form, title, canManage, canEdit, openForm, closeForm, load, initialize, submit, remove, cancelDelete }
}
