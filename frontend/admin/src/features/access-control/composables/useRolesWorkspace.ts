import { computed, ref, watch } from 'vue'
import { useAuthStore } from '../../../stores/auth'
import { deleteRole, getPermissions, getRoles, saveRole } from '../services/roles'
import type { AccessRole, Permission, RolePayload } from '../types/access.types'
import { roleNameToSlug } from '../validation/role.schema'

const emptyForm = (): RolePayload => ({ name: '', slug: '', description: '', permission_ids: [] })

export function useRolesWorkspace() {
  const auth = useAuthStore()
  const roles = ref<AccessRole[]>([])
  const permissions = ref<Permission[]>([])
  const error = ref('')
  const permissionsError = ref('')
  const formError = ref('')
  const deleteError = ref('')
  const loading = ref(true)
  const permissionsLoading = ref(false)
  const permissionsReady = ref(false)
  const saving = ref(false)
  const deleting = ref<AccessRole | null>(null)
  const isDeleting = ref(false)
  const opened = ref(false)
  const editing = ref<AccessRole | null>(null)
  const form = ref<RolePayload>(emptyForm())
  const generatedSlug = ref('')
  const canManage = computed(() => auth.hasPermission('roles.manage'))
  const canEdit = computed(() => canManage.value && permissionsReady.value && !permissionsLoading.value && !permissionsError.value)
  const title = computed(() => editing.value ? `Роль: ${editing.value.name}` : 'Новая роль')

  watch(() => form.value.name, name => {
    if (!editing.value && (form.value.slug === '' || form.value.slug === generatedSlug.value)) {
      generatedSlug.value = roleNameToSlug(name)
      form.value.slug = generatedSlug.value
    }
  })

  function open(role: AccessRole | null = null): void {
    if (!canEdit.value) return
    editing.value = role
    formError.value = ''
    generatedSlug.value = ''
    form.value = role ? { name: role.name, slug: role.slug, description: role.description ?? '', permission_ids: role.permissions.map(item => item.id) } : emptyForm()
    opened.value = true
  }

  function close(): void { if (!saving.value) opened.value = false }

  async function load(): Promise<void> {
    loading.value = true
    error.value = ''
    permissionsError.value = ''
    permissionsReady.value = false
    try {
      try { roles.value = await getRoles() }
      catch (reason) { error.value = reason instanceof Error ? reason.message : 'Не удалось загрузить роли.' }
      if (canManage.value) {
        permissionsLoading.value = true
        try { permissions.value = await getPermissions(); permissionsReady.value = true }
        catch (reason) { permissionsError.value = reason instanceof Error ? reason.message : 'Не удалось загрузить права.' }
        finally { permissionsLoading.value = false }
      }
    } finally { loading.value = false }
  }

  async function save(): Promise<void> {
    if (!canEdit.value) return
    saving.value = true
    formError.value = ''
    try { await saveRole(editing.value?.id ?? null, form.value); opened.value = false; await load() }
    catch (reason) { formError.value = reason instanceof Error ? reason.message : 'Не удалось сохранить роль.' }
    finally { saving.value = false }
  }

  async function remove(): Promise<void> {
    if (!deleting.value) return
    isDeleting.value = true
    deleteError.value = ''
    try { await deleteRole(deleting.value.id); deleting.value = null; await load() }
    catch (reason) { deleteError.value = reason instanceof Error ? reason.message : 'Не удалось удалить роль.' }
    finally { isDeleting.value = false }
  }

  function cancelDelete(): void {
    if (isDeleting.value) return
    deleting.value = null
    deleteError.value = ''
  }

  return { roles, permissions, error, permissionsError, formError, deleteError, loading, permissionsLoading, permissionsReady, saving, deleting, isDeleting, opened, editing, form, canManage, canEdit, title, open, close, load, save, remove, cancelDelete }
}
