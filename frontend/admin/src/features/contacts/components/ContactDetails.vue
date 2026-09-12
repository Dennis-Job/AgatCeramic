<script setup lang="ts">
import UiAlert from '../../../components/ui/UiAlert.vue'
import UiButton from '../../../components/ui/UiButton.vue'
import UiLoadingState from '../../../components/ui/UiLoadingState.vue'
import UiSelect from '../../../components/ui/UiSelect.vue'
import UiTextarea from '../../../components/ui/UiTextarea.vue'
import type { Contact, ContactAssignee, ContactComment, ContactHistory, ContactStatus } from '../types/contact.types'

defineProps<{ selected: Contact | null; statuses: ContactStatus[]; assignees: ContactAssignee[]; history: ContactHistory[]; comments: ContactComment[]; detailLoading: boolean; saving: boolean; actionError: string; canManage: boolean; canManageStatus: boolean; canAssign: boolean; date: (value: string | null) => string; statusName: (code: string) => string; typeName: (value: string) => string; assigneeOptions: { label: string; value: string }[] }>()
const commentBody = defineModel<string>('commentBody', { required: true })
defineEmits<{ changeStatus: []; changeAssignee: [value: string]; submitComment: [] }>()
</script>

<template>
  <aside class="min-w-0 rounded-xl border border-gray-200 bg-white shadow-card">
    <UiAlert v-if="actionError && !selected" class="m-5 mb-0">{{ actionError }}</UiAlert>
    <UiLoadingState v-if="detailLoading" label="Загрузка деталей обращения…" />
    <div v-else-if="!selected" class="p-8 text-sm text-gray-500" role="status">Выберите обращение в списке.</div>
    <div v-else class="space-y-6 p-5">
      <div><p class="text-xs text-gray-500">{{ typeName(selected.type) }}</p><h2 class="mt-1 text-xl font-bold text-gray-900">Обращение #{{ selected.id }}</h2></div>
      <UiAlert v-if="actionError">{{ actionError }}</UiAlert>
      <section><h3 class="text-sm font-semibold text-gray-900">Контакт</h3><p class="mt-2 break-words text-sm text-gray-700">{{ selected.contact.name ?? 'Без имени' }}</p><p v-if="selected.contact.phone" class="break-all text-sm text-gray-600">{{ selected.contact.phone }}</p><p v-if="selected.contact.email" class="break-all text-sm text-gray-600">{{ selected.contact.email }}</p><p v-if="selected.message" class="mt-3 whitespace-pre-line break-words rounded-lg bg-gray-25 p-3 text-sm text-gray-700">{{ selected.message }}</p></section>
      <section v-if="canManageStatus || canAssign" class="grid gap-3 rounded-lg border border-gray-200 p-4"><h3 class="text-sm font-semibold text-gray-900">Обработка</h3><template v-if="canManageStatus"><UiSelect v-model="selected.status" :options="statuses.map(item => ({ label: item.name, value: item.code }))" accessible-name="Новый статус обращения" :disabled="saving" /><UiButton :loading="saving" :disabled="saving" @click="$emit('changeStatus')">Сохранить статус</UiButton></template><UiSelect v-if="canAssign" :model-value="selected.assignee ? String(selected.assignee.id) : ''" :options="assigneeOptions" accessible-name="Ответственный" :disabled="saving" @update:model-value="$emit('changeAssignee', $event)" /></section>
      <section><h3 class="text-sm font-semibold text-gray-900">История статусов</h3><ol class="mt-3 space-y-2 border-l border-gray-200 pl-4 text-sm"><li v-for="entry in history" :key="entry.id"><p class="break-words">{{ statusName(entry.from_status) }} → <b>{{ statusName(entry.to_status) }}</b></p><p class="text-xs text-gray-500">{{ entry.actor?.name ?? 'Система' }} · {{ date(entry.occurred_at) }}</p></li><li v-if="!history.length" class="text-gray-500">Изменений статуса пока нет.</li></ol></section>
      <section><h3 class="text-sm font-semibold text-gray-900">Внутренние комментарии</h3><div class="mt-3 space-y-3"><article v-for="comment in comments" :key="comment.id" class="rounded-lg bg-gray-25 p-3 text-sm"><p class="whitespace-pre-line break-words">{{ comment.body }}</p><p class="mt-2 text-xs text-gray-500">{{ comment.author?.name ?? 'Система' }} · {{ date(comment.created_at) }}</p></article><p v-if="!comments.length" class="text-sm text-gray-500">Комментариев пока нет.</p></div><form v-if="canManage" class="mt-3" @submit.prevent="$emit('submitComment')"><label class="sr-only" for="contact-comment">Новый внутренний комментарий</label><UiTextarea id="contact-comment" v-model="commentBody" rows="3" placeholder="Добавить внутренний комментарий" :disabled="saving" /><UiButton class="mt-2" type="submit" variant="secondary" :disabled="saving || !commentBody.trim()">Добавить</UiButton></form></section>
    </div>
  </aside>
</template>
