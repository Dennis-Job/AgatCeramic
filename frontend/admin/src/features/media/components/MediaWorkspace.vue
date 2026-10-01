<script setup lang="ts">
import { FileText, Pencil, Trash2 } from '@lucide/vue'
import ConfirmDialog from '../../../components/shared/ConfirmDialog.vue'
import AdminWorkspace from '../../../components/shared/AdminWorkspace.vue'
import PageHeader from '../../../components/shared/PageHeader.vue'
import UiAlert from '../../../components/ui/UiAlert.vue'
import UiButton from '../../../components/ui/UiButton.vue'
import UiCard from '../../../components/ui/UiCard.vue'
import UiEmptyState from '../../../components/ui/UiEmptyState.vue'
import UiField from '../../../components/ui/UiField.vue'
import UiInput from '../../../components/ui/UiInput.vue'
import UiLoadingState from '../../../components/ui/UiLoadingState.vue'
import UiPagination from '../../../components/ui/UiPagination.vue'
import UiTable from '../../../components/ui/UiTable.vue'
import UiSelect from '../../../components/ui/UiSelect.vue'
import { useMediaWorkspace } from '../composables/useMediaWorkspace'
import MediaImagePreview from './MediaImagePreview.vue'

const {
  list,
  kind,
  title,
  alt,
  editing,
  deleting,
  busy,
  formError,
  deleteError,
  success,
  load,
  chooseFile,
  upload,
  startEdit,
  cancelEdit,
  confirmDelete,
  saveEdit,
  remove,
} = useMediaWorkspace()
</script>

<template>
  <AdminWorkspace mode="list">
    <template #intro>
      <PageHeader class="mb-7" eyebrow="Контент" title="Медиатека" />
    </template>

    <UiAlert v-if="success" class="mb-4" tone="success" role="status">{{
      success
    }}</UiAlert>
    <UiCard class="mb-6 media-upload">
      <form
        class="grid gap-4 p-5 sm:grid-cols-2"
        @submit.prevent="editing ? saveEdit() : upload()"
      >
        <h2 class="text-base font-semibold sm:col-span-2">
          {{ editing ? 'Редактировать файл' : 'Загрузить файл' }}
        </h2>
        <UiField label="Название" required
          ><UiInput v-model="title" required maxlength="255" :disabled="busy"
        /></UiField>
        <UiField v-if="!editing" label="Тип файла" required>
          <UiSelect
            v-model="kind"
            :options="[
              { label: 'Изображение', value: 'image' },
              { label: 'PDF документ', value: 'document' },
            ]"
            accessible-name="Тип файла"
            :disabled="busy"
          />
        </UiField>
        <UiField
          v-if="!editing"
          label="Файл"
          required
          help="JPEG, PNG или WebP до 10 МБ; PDF до 20 МБ."
        >
          <input
            type="file"
            :accept="
              kind === 'image'
                ? 'image/jpeg,image/png,image/webp'
                : 'application/pdf'
            "
            required
            :disabled="busy"
            class="mt-1.5 block w-full min-w-0 text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-primary-50 file:px-3 file:py-2 file:text-primary-700 focus-visible:outline-2 focus-visible:outline-primary-500"
            @change="chooseFile"
          />
        </UiField>
        <UiField
          v-if="editing ? editing.kind === 'image' : kind === 'image'"
          label="Alt текст"
          help="Кратко опишите изображение для доступности."
          ><UiInput v-model="alt" maxlength="255" :disabled="busy"
        /></UiField>
        <UiAlert v-if="formError" class="sm:col-span-2">{{
          formError
        }}</UiAlert>
        <div class="flex flex-wrap gap-2 sm:col-span-2">
          <UiButton :loading="busy">{{
            editing ? 'Сохранить' : 'Загрузить'
          }}</UiButton>
          <UiButton
            v-if="editing"
            type="button"
            variant="secondary"
            :disabled="busy"
            @click="cancelEdit"
            >Отмена</UiButton
          >
        </div>
      </form>
    </UiCard>
    <UiAlert v-if="list.error.value" class="mb-4">{{
      list.error.value
    }}</UiAlert>
    <UiButton
      v-if="list.error.value"
      class="mb-4"
      variant="secondary"
      @click="load()"
      >Повторить загрузку</UiButton
    >
    <div v-if="!list.error.value || list.items.value.length">
      <UiLoadingState v-if="list.loading.value" label="Загрузка файлов…" />
      <UiTable
        v-else-if="list.items.value.length"
        label="Список файлов"
        min-width="min-w-[880px]"
        table-class="seller-table"
        sticky-header
        sticky-edges
      >
        <thead>
          <tr>
            <th scope="col">Файл</th>
            <th scope="col" class="w-40">Тип / размер</th>
            <th scope="col" class="w-72">Alt текст</th>
            <th scope="col" class="w-28">
              <span class="sr-only">Действия</span>
            </th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="item in list.items.value" :key="item.id">
            <td>
              <div class="flex items-start gap-3">
                <MediaImagePreview
                  v-if="item.kind === 'image'"
                  :url="item.thumbnail_url || item.url"
                  :alt="item.alt || item.title"
                  class="h-16 w-16 shrink-0"
                /><FileText
                  v-else
                  :size="32"
                  class="shrink-0 text-primary-600"
                  aria-hidden="true"
                />
                <div class="min-w-0">
                  <a
                    :href="item.url"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="font-medium text-primary-700 underline focus-visible:outline-2 focus-visible:outline-primary-500"
                    >{{ item.title }}</a
                  >
                  <p class="mt-1 text-xs text-gray-500">#{{ item.id }}</p>
                </div>
              </div>
            </td>
            <td>
              {{ item.kind === 'image' ? 'Изображение' : 'PDF документ' }}
              <p class="mt-1 text-xs text-gray-500">
                {{ Math.ceil(item.size / 1024) }} КБ
              </p>
            </td>
            <td>{{ item.alt || '—' }}</td>
            <td>
              <div class="flex justify-end gap-1">
                <UiButton
                  variant="ghost"
                  size="sm"
                  :aria-label="`Изменить файл ${item.title}`"
                  @click="startEdit(item)"
                  ><Pencil :size="17"
                /></UiButton>
                <UiButton
                  variant="danger-ghost"
                  size="sm"
                  :aria-label="`Удалить файл ${item.title}`"
                  @click="confirmDelete(item)"
                  ><Trash2 :size="17"
                /></UiButton>
              </div>
            </td>
          </tr></tbody
      ></UiTable>
      <UiEmptyState v-else label="Файлов пока нет." />
    </div>
    <UiPagination
      v-if="list.pagination.value"
      :meta="list.pagination.value"
      :loading="list.loading.value"
      @change="load"
    />
    <ConfirmDialog
      :open="Boolean(deleting)"
      title="Удалить файл?"
      :description="`Файл «${deleting?.title ?? ''}» будет удалён. Сначала снимите все ссылки на него.`"
      :busy="busy"
      :error="deleteError"
      @close="deleting = null"
      @confirm="remove"
    />
  </AdminWorkspace>
</template>

<style scoped>
.media-upload {
  max-width: var(--admin-workspace-form-max-width);
}
</style>
