<script setup lang="ts">
import { UserRound, Phone, Mail, MessageSquare } from '@lucide/vue'
import UiNotification from '../../../components/ui/UiNotification.vue'
import UiButton from '../../../components/ui/UiButton.vue'
import UiBadge from '../../../components/ui/UiBadge.vue'
import ContactStatusActions from './ContactStatusActions.vue'
import UiLoadingState from '../../../components/ui/UiLoadingState.vue'
import UiSelect from '../../../components/ui/UiSelect.vue'
import UiTextarea from '../../../components/ui/UiTextarea.vue'
import { formatPhone } from '../../../utils/phone'
import { contactStatusTone } from '../utils/contactStatusTone'
import type {
  Contact,
  ContactAssignee,
  ContactComment,
  ContactHistory,
  ContactStatus,
} from '../types/contact.types'

defineProps<{
  selected: Contact | null
  statuses: ContactStatus[]
  currentStatus?: ContactStatus
  allowedStatuses: ContactStatus[]
  transitionReady: boolean
  assignees: ContactAssignee[]
  history: ContactHistory[]
  comments: ContactComment[]
  detailLoading: boolean
  saving: boolean
  actionError: string
  canManage: boolean
  canManageStatus: boolean
  canAssign: boolean
  date: (value: string | null) => string
  statusName: (code: string) => string
  typeName: (value: string) => string
  assigneeOptions: { label: string; value: string }[]
}>()
const commentBody = defineModel<string>('commentBody', { required: true })
defineEmits<{
  changeStatus: [status: string]
  retry: []
  retryDetail: []
  changeAssignee: [value: string]
  submitComment: []
}>()
</script>

<template>
  <div
    class="contact-details min-w-0"
    role="region"
    aria-label="Детали выбранной записи"
  >
    <UiNotification v-if="actionError && !selected">{{
      actionError
    }}</UiNotification>
    <UiLoadingState v-if="detailLoading" label="Загрузка деталей обращения…" />
    <div v-else-if="!selected" class="p-8 text-sm text-gray-500" role="status">
      <template v-if="actionError"
        ><p>Не удалось открыть обращение.</p>
        <UiButton class="mt-3" variant="secondary" @click="$emit('retryDetail')"
          >Повторить загрузку обращения</UiButton
        ></template
      >
      <template v-else>Выберите обращение в списке.</template>
    </div>
    <div v-else class="space-y-6">
      <UiNotification v-if="actionError">{{ actionError }}</UiNotification>
      <div class="flex flex-wrap items-center gap-2 text-sm text-gray-500">
        <UiBadge
          class="shrink-0 whitespace-nowrap"
          :tone="contactStatusTone(selected.status)"
          >{{ statusName(selected.status) }}</UiBadge
        >
        <span>{{ typeName(selected.type) }}</span>
        <time :datetime="selected.created_at">{{
          date(selected.created_at)
        }}</time>
      </div>
      <section class="space-y-3">
        <h3 class="sr-only">Контакт</h3>
        <p class="flex items-start gap-3 text-base font-semibold text-gray-500">
          <UserRound :size="18" class="mt-0.5 shrink-0" aria-hidden="true" />
          <span class="min-w-0 break-words">{{
            selected.contact.name ?? 'Без имени'
          }}</span>
        </p>
        <p
          v-if="selected.contact.phone"
          class="flex items-start gap-3 text-sm text-gray-500"
        >
          <Phone :size="18" class="shrink-0" aria-hidden="true" />
          <span class="min-w-0 break-all">{{
            formatPhone(selected.contact.phone)
          }}</span>
        </p>
        <p
          v-if="selected.contact.email"
          class="flex items-start gap-3 text-sm text-gray-500"
        >
          <Mail :size="18" class="shrink-0" aria-hidden="true" />
          <span class="min-w-0 break-all">{{ selected.contact.email }}</span>
        </p>
        <p
          v-if="selected.message"
          class="contact-message flex items-start gap-3 rounded-xl bg-gray-50 p-4 text-sm text-gray-500"
        >
          <MessageSquare :size="18" class="shrink-0" aria-hidden="true" />
          <span class="min-w-0 whitespace-pre-line break-words">{{
            selected.message
          }}</span>
        </p>
      </section>
      <section v-if="canManageStatus || canAssign" class="grid gap-3">
        <h3 class="text-sm font-semibold text-gray-500">Ответственный</h3>
        <UiSelect
          v-if="canAssign"
          :model-value="selected.assignee ? String(selected.assignee.id) : ''"
          :options="assigneeOptions"
          accessible-name="Ответственный"
          :disabled="saving"
          @update:model-value="$emit('changeAssignee', $event)"
        />
      </section>
      <ContactStatusActions
        :current-status="currentStatus"
        :allowed-statuses="allowedStatuses"
        :statuses="statuses"
        :ready="transitionReady"
        :can-manage="canManage"
        :disabled="saving || detailLoading"
        @change-status="$emit('changeStatus', $event)"
        @retry="$emit('retry')"
      />
      <section>
        <h3 class="text-sm font-semibold text-gray-500">История статусов</h3>
        <ol class="mt-3 space-y-2 border-l border-gray-200 pl-4 text-sm">
          <li v-for="entry in history" :key="entry.id">
            <p class="break-words">
              {{ statusName(entry.from_status) }} →
              <b>{{ statusName(entry.to_status) }}</b>
            </p>
            <p class="text-xs text-gray-500">
              {{ entry.actor?.name ?? 'Система' }} ·
              {{ date(entry.occurred_at) }}
            </p>
          </li>
          <li v-if="!history.length" class="text-gray-500">
            Изменений статуса пока нет.
          </li>
        </ol>
      </section>
      <section>
        <h3 class="text-sm font-semibold text-gray-500">
          Внутренние комментарии
        </h3>
        <div class="mt-3 space-y-3">
          <article
            v-for="comment in comments"
            :key="comment.id"
            class="admin-panel--inset p-3 text-sm"
          >
            <p class="whitespace-pre-line break-words">{{ comment.body }}</p>
            <p class="mt-2 text-xs text-gray-500">
              {{ comment.author?.name ?? 'Система' }} ·
              {{ date(comment.created_at) }}
            </p>
          </article>
          <p v-if="!comments.length" class="text-sm text-gray-500">
            Комментариев пока нет.
          </p>
        </div>
        <form
          v-if="canManage"
          class="mt-3"
          @submit.prevent="$emit('submitComment')"
        >
          <label class="sr-only" for="contact-comment"
            >Новый внутренний комментарий</label
          ><UiTextarea
            id="contact-comment"
            v-model="commentBody"
            rows="3"
            placeholder="Добавить внутренний комментарий"
            :disabled="saving"
          /><UiButton
            class="mt-2"
            type="submit"
            variant="secondary"
            :disabled="saving || !commentBody.trim()"
            >Добавить</UiButton
          >
        </form>
      </section>
    </div>
  </div>
</template>
