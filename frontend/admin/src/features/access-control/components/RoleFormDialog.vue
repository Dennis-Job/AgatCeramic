<script setup lang="ts">
import { X } from '@lucide/vue'
import { computed, ref, watch } from 'vue'
import UiNotification from '../../../components/ui/UiNotification.vue'
import UiButton from '../../../components/ui/UiButton.vue'
import UiDialogFooter from '../../../components/ui/UiDialogFooter.vue'
import UiCheckbox from '../../../components/ui/UiCheckbox.vue'
import UiDialog from '../../../components/ui/UiDialog.vue'
import UiField from '../../../components/ui/UiField.vue'
import UiInput from '../../../components/ui/UiInput.vue'
import UiTextarea from '../../../components/ui/UiTextarea.vue'
import type { AccessRole, Permission, RolePayload } from '../types/access.types'
import { groupPermissions } from '../utils/permissions'

const props = defineProps<{
  open: boolean
  title: string
  editing: AccessRole | null
  permissions: Permission[]
  busy: boolean
  error: string
}>()
const form = defineModel<RolePayload>('form', { required: true })
const emit = defineEmits<{ close: []; submit: [] }>()
const permissionSearch = ref('')
const permissionGroups = computed(() => {
  const needle = permissionSearch.value.trim().toLocaleLowerCase('ru-RU')
  return groupPermissions(
    props.permissions.filter((permission) =>
      [permission.name, permission.code, permission.description ?? ''].some(
        (value) => value.toLocaleLowerCase('ru-RU').includes(needle),
      ),
    ),
  )
})
watch(
  () => props.open,
  () => {
    permissionSearch.value = ''
  },
)
</script>

<template>
  <UiDialog
    :open="open"
    labelledby="role-dialog-title"
    describedby="role-dialog-description"
    :close-disabled="busy"
    panel-class="w-full max-w-3xl"
    @close="emit('close')"
  >
    <form
      class="flex max-h-[90dvh] w-full flex-col"
      @submit.prevent="emit('submit')"
    >
      <div class="flex shrink-0 items-start justify-between gap-3">
        <div class="min-w-0">
          <h2
            id="role-dialog-title"
            class="break-words text-lg font-bold text-gray-500 [overflow-wrap:anywhere]"
          >
            {{ title }}
          </h2>
        </div>
        <UiButton
          type="button"
          variant="surface"
          size="sm"
          aria-label="Закрыть окно роли"
          :disabled="busy"
          @click="emit('close')"
          ><X :size="20"
        /></UiButton>
      </div>
      <div
        class="-m-[var(--admin-focus-halo-width)] min-h-0 overflow-y-auto overscroll-contain p-[var(--admin-focus-halo-width)]"
      >
        <UiNotification v-if="error">{{ error }}</UiNotification>
        <div class="my-6 grid gap-4">
          <div class="grid gap-4 sm:grid-cols-2">
            <UiField label="Название" required
              ><UiInput
                v-model="form.name"
                class="mt-1.5"
                :disabled="editing?.is_system || busy"
                required /></UiField
            ><UiField label="Технический код" required
              ><UiInput
                v-model="form.slug"
                class="mt-1.5"
                :disabled="editing?.is_system || busy"
                required
            /></UiField>
          </div>
          <UiField label="Описание"
            ><UiTextarea
              v-model="form.description"
              class="mt-1.5 min-h-20 font-normal"
              :disabled="busy"
          /></UiField>
          <fieldset :disabled="busy">
            <legend class="text-sm font-semibold text-gray-500">Права</legend>
            <p class="mb-3 mt-1 text-xs text-gray-500">
              Выбрано прав: {{ form.permission_ids.length }}. Изменения
              применятся после сохранения роли.
            </p>
            <UiInput
              v-model="permissionSearch"
              searchable
              aria-label="Поиск прав роли"
              placeholder="Найти право по названию или коду"
              :disabled="busy"
              class="mb-4"
            />
            <div class="space-y-4">
              <fieldset
                v-for="group in permissionGroups"
                :key="group.module"
                class="min-w-0"
              >
                <legend
                  class="mb-3 w-full rounded-lg bg-gray-50 px-3 py-2 text-sm font-semibold text-gray-500 [overflow-wrap:anywhere]"
                >
                  {{ group.name }}
                </legend>
                <div class="grid gap-3 sm:grid-cols-2">
                  <UiCheckbox
                    v-for="permission in group.permissions"
                    :key="permission.id"
                    v-model="form.permission_ids"
                    :value="permission.id"
                  >
                    <span class="break-words [overflow-wrap:anywhere]"
                      >{{ permission.name }}
                      <span class="text-xs text-gray-500"
                        >({{ permission.code }})</span
                      ></span
                    >
                  </UiCheckbox>
                </div>
              </fieldset>
              <p
                v-if="!permissionGroups.length"
                role="status"
                class="text-sm text-gray-500"
              >
                Права не найдены.
              </p>
            </div>
          </fieldset>
        </div>
      </div>
      <UiDialogFooter>
        <template #note
          ><span id="role-dialog-description"
            >Настройте роль и доступные ей права.</span
          ></template
        >
        <UiButton
          type="button"
          variant="surface"
          :disabled="busy"
          @click="emit('close')"
          >Отмена</UiButton
        ><UiButton type="submit" :loading="busy" :disabled="busy">{{
          busy ? 'Сохранение…' : 'Сохранить'
        }}</UiButton>
      </UiDialogFooter>
    </form>
  </UiDialog>
</template>
