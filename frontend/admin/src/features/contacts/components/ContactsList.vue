<script setup lang="ts">
import UiBadge from '../../../components/ui/UiBadge.vue'
import UiEmptyState from '../../../components/ui/UiEmptyState.vue'
import UiLoadingState from '../../../components/ui/UiLoadingState.vue'
import UiTable from '../../../components/ui/UiTable.vue'
import type { Contact } from '../types/contact.types'

defineProps<{
  contacts: Contact[]
  selectedId?: number
  loading: boolean
  date: (value: string | null) => string
  statusName: (code: string) => string
  typeName: (value: string) => string
}>()
defineEmits<{ select: [contact: Contact] }>()
</script>

<template>
  <UiLoadingState
    v-if="loading"
    class="admin-container"
    label="Загрузка обращений…"
  />
  <UiEmptyState
    v-else-if="!contacts.length"
    class="admin-container"
    label="Обращения не найдены."
  />
  <template v-else>
    <div class="admin-container divide-y divide-gray-100 md:hidden">
      <article
        v-for="contact in contacts"
        :key="contact.id"
        :class="selectedId === contact.id ? 'bg-primary-50' : ''"
        class="p-4"
      >
        <button
          type="button"
          class="w-full rounded-lg text-left admin-focus"
          :aria-label="`Открыть обращение ${contact.id}`"
          :aria-current="selectedId === contact.id ? 'true' : undefined"
          @click="$emit('select', contact)"
        >
          <div class="flex flex-wrap items-start justify-between gap-2">
            <div>
              <span class="block font-semibold text-gray-500">{{
                typeName(contact.type)
              }}</span
              ><time
                class="mt-1 block text-xs text-gray-500"
                :datetime="contact.created_at"
                >{{ date(contact.created_at) }}</time
              >
            </div>
            <UiBadge tone="primary">{{ statusName(contact.status) }}</UiBadge>
          </div>
          <p class="mt-3 break-words text-sm font-medium text-gray-500">
            {{ contact.contact.name ?? 'Без имени' }}
          </p>
          <p class="break-all text-xs text-gray-500">
            {{ contact.contact.phone ?? contact.contact.email ?? '—' }}
          </p>
          <p class="mt-3 text-xs text-gray-500">
            Ответственный: {{ contact.assignee?.name ?? 'Не назначен' }}
          </p>
        </button>
      </article>
    </div>
    <div class="min-w-0">
      <UiTable
        full-bleed
        class="hidden md:block"
        min-width="min-w-[760px]"
        table-class="seller-table"
        sticky-header
        label="Список обращений"
      >
        <thead class="bg-gray-25 text-xs text-gray-500">
          <tr>
            <th scope="col" class="w-48 px-5 py-3">Обращение</th>
            <th scope="col" class="px-5 py-3">Контакт</th>
            <th scope="col" class="w-40 px-5 py-3">Статус</th>
            <th scope="col" class="w-44 px-5 py-3">Ответственный</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="contact in contacts"
            :key="contact.id"
            class="border-t border-gray-100"
            :class="selectedId === contact.id ? 'bg-primary-50' : ''"
          >
            <td class="px-5 py-4">
              <button
                type="button"
                class="-m-2 rounded-lg p-2 text-left admin-focus"
                :aria-label="`Открыть обращение ${contact.id}`"
                :aria-current="selectedId === contact.id ? 'true' : undefined"
                @click="$emit('select', contact)"
              >
                <span class="block font-semibold text-gray-500">{{
                  typeName(contact.type)
                }}</span
                ><time
                  class="mt-1 block text-xs text-gray-500"
                  :datetime="contact.created_at"
                  >{{ date(contact.created_at) }}</time
                >
              </button>
            </td>
            <td class="px-5 py-4">
              <p class="font-medium text-gray-500">
                {{ contact.contact.name ?? 'Без имени' }}
              </p>
              <p class="mt-1 text-xs text-gray-500">
                {{ contact.contact.phone ?? contact.contact.email ?? '—' }}
              </p>
            </td>
            <td class="px-5 py-4">
              <UiBadge tone="primary">{{ statusName(contact.status) }}</UiBadge>
            </td>
            <td class="px-5 py-4 text-gray-500">
              {{ contact.assignee?.name ?? 'Не назначен' }}
            </td>
          </tr>
        </tbody>
      </UiTable>
    </div>
  </template>
</template>
