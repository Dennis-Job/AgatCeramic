<script setup lang="ts">
import { vTooltip } from '../../components/ui/tooltip'
import { ref } from 'vue'
import { LogOut, Settings, UserRound } from '@lucide/vue'
import { useRouter } from 'vue-router'
import UiPopover from '../../components/ui/UiPopover.vue'
import UiNotification from '../../components/ui/UiNotification.vue'
import UiButton from '../../components/ui/UiButton.vue'
import { useAuthStore } from '../../stores/auth'
const open = defineModel<boolean>('open', { default: false })
const auth = useAuthStore()
const router = useRouter()
const signingOut = ref(false)
const error = ref('')
async function signOut(): Promise<void> {
  signingOut.value = true
  error.value = ''
  try {
    await auth.logout()
    await router.replace('/login')
  } catch (cause) {
    error.value =
      cause instanceof Error
        ? cause.message
        : 'Не удалось выйти. Попробуйте ещё раз.'
  } finally {
    signingOut.value = false
  }
}
</script>

<template>
  <UiNotification v-if="error">{{ error }}</UiNotification>
  <UiPopover
    id="admin-user-panel"
    v-model:open="open"
    align="end"
    label="Меню пользователя"
  >
    <template #trigger="{ trigger }">
      <button
        v-tooltip="open ? false : undefined"
        v-bind="trigger"
        type="button"
        class="admin-header-icon"
        aria-label="Меню пользователя"
      >
        <span class="admin-user-symbol"
          ><UserRound :size="17" :stroke-width="2.4" aria-hidden="true"
        /></span>
      </button>
    </template>
    <template #default="{ close }">
      <div class="border-b border-gray-200 pb-4">
        <p class="break-words text-base font-bold text-gray-900">
          {{ auth.user?.name }}
        </p>
        <p class="mt-1 break-all text-sm text-gray-500">
          {{ auth.user?.email }}
        </p>
      </div>
      <div class="mt-3 grid gap-1">
        <RouterLink to="/profile" class="admin-account-action" @click="close()"
          ><UserRound :size="20" aria-hidden="true" />Мой профиль</RouterLink
        >
        <RouterLink
          v-if="auth.hasPermission('settings.manage')"
          to="/settings"
          class="admin-account-action"
          @click="close()"
          ><Settings :size="20" aria-hidden="true" />Настройки</RouterLink
        >
        <UiButton
          class="admin-account-action"
          variant="ghost"
          :loading="signingOut"
          :disabled="signingOut"
          @click="signOut"
          ><LogOut :size="20" aria-hidden="true" />Выйти</UiButton
        >
      </div>
    </template>
  </UiPopover>
</template>
