<script setup lang="ts">
import { computed } from 'vue'
import { LogOut } from '@lucide/vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../../stores/auth'

const auth = useAuthStore()
const router = useRouter()
const initial = computed(() => auth.user?.name.slice(0, 1).toUpperCase() ?? 'А')

async function signOut(): Promise<void> {
  await auth.logout()
  await router.replace('/login')
}
</script>

<template>
  <div class="flex items-center gap-3" aria-label="Меню пользователя">
    <div class="hidden min-w-0 text-right sm:block">
      <p class="truncate text-sm font-semibold text-gray-700">{{ auth.user?.name }}</p>
      <p class="truncate text-xs text-gray-400">{{ auth.user?.email }}</p>
    </div>
    <button
      type="button"
      class="grid h-10 w-10 shrink-0 cursor-pointer place-items-center rounded-full bg-primary-500 text-sm font-bold text-white transition hover:bg-primary-600 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary-50"
      aria-label="Мой профиль"
      @click="router.push({ name: 'profile' })"
    >{{ initial }}</button>
    <button
      type="button"
      class="grid h-10 w-10 shrink-0 place-items-center rounded-full border border-gray-200 text-gray-500 transition hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary-50"
      aria-label="Выйти"
      @click="signOut"
    ><LogOut :size="19" aria-hidden="true" /></button>
  </div>
</template>
