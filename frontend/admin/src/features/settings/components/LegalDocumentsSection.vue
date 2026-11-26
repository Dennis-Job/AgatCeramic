<script setup lang="ts">
import { nextTick, ref } from 'vue'
import { X } from '@lucide/vue'
import UiBadge from '../../../components/ui/UiBadge.vue'
import UiButton from '../../../components/ui/UiButton.vue'
import UiCard from '../../../components/ui/UiCard.vue'
import UiEmptyState from '../../../components/ui/UiEmptyState.vue'
import UiField from '../../../components/ui/UiField.vue'
import UiInput from '../../../components/ui/UiInput.vue'
import UiSelect from '../../../components/ui/UiSelect.vue'
import UiRichTextEditor from '../../../components/ui/UiRichTextEditor.vue'
import UiDialog from '../../../components/ui/UiDialog.vue'
import UiDialogFooter from '../../../components/ui/UiDialogFooter.vue'
import type { useLegalDocuments } from '../composables/useLegalDocuments'
import { documentOptions, optionLabel } from '../settingsOptions'
import type { LegalDocument, SettingsAction } from '../types/settings.types'

const props = defineProps<{
  state: ReturnType<typeof useLegalDocuments>
  busyAction: SettingsAction | null
}>()
const {
  documents,
  documentForm,
  editingDocumentId,
  editDocument,
  cancelDocumentEdit,
  addDocument,
  preview,
  openPreview,
  closePreview,
  publishPreview,
} = props.state
const documentEditorForm = ref<HTMLFormElement | null>(null)
const documentList = ref<HTMLDivElement | null>(null)

async function beginDocumentEdit(document: LegalDocument): Promise<void> {
  if (!editDocument(document)) return
  // Let the preview restore its opener before moving focus into the editor.
  await nextTick()
  await nextTick()
  documentEditorForm.value?.focus({ preventScroll: true })
  documentEditorForm.value?.scrollIntoView({ block: 'start' })
}

async function focusDocumentAction(id: number): Promise<void> {
  await nextTick()
  documentList.value
    ?.querySelector<HTMLButtonElement>(`[data-edit-document="${id}"]`)
    ?.focus()
}

async function submitDocument(): Promise<void> {
  const id = editingDocumentId.value
  const saved = await addDocument()
  if (id !== null && saved) await focusDocumentAction(id)
}

async function cancelEdit(): Promise<void> {
  const id = editingDocumentId.value
  cancelDocumentEdit()
  if (id !== null && editingDocumentId.value === null)
    await focusDocumentAction(id)
}

function updateDocumentType(value: string): void {
  documentForm.value.type = value as LegalDocument['type']
}
</script>

<template>
  <UiCard aria-labelledby="legal-documents-title">
    <template #header>
      <div>
        <h2
          id="legal-documents-title"
          class="text-lg font-semibold text-gray-500"
        >
          Юридические документы
        </h2>
        <p class="mt-1 text-sm text-gray-500">
          Новая версия сохраняется черновиком. Публикация делает текст доступным
          публично.
        </p>
      </div>
    </template>
    <form
      id="legal-document-editor"
      ref="documentEditorForm"
      class="space-y-4"
      tabindex="-1"
      :aria-label="
        editingDocumentId !== null
          ? 'Редактирование черновика'
          : 'Новая версия документа'
      "
      @submit.prevent="submitDocument"
    >
      <p
        v-if="editingDocumentId !== null"
        role="status"
        class="text-sm text-gray-500"
      >
        Редактирование черновика · ID {{ editingDocumentId }}. Сохранение
        обновит эту версию без публикации.
      </p>
      <div
        class="grid grid-cols-[minmax(0,1fr)] gap-4 md:grid-cols-2 [&>*]:min-w-0"
      >
        <UiField label="Документ" required
          ><UiSelect
            :model-value="documentForm.type"
            :options="documentOptions"
            :disabled="Boolean(busyAction) || editingDocumentId !== null"
            accessible-name="Тип документа"
            @update:model-value="updateDocumentType"
        /></UiField>
        <UiField label="Версия" required
          ><UiInput
            v-model="documentForm.version"
            :disabled="Boolean(busyAction)"
            required
            maxlength="64"
            placeholder="2026-09-25"
        /></UiField>
      </div>
      <div class="space-y-2">
        <p class="text-sm font-medium text-gray-500">
          Текст документа <span aria-hidden="true">*</span>
        </p>
        <UiRichTextEditor
          v-model="documentForm.content"
          v-model:body="documentForm.body"
          required
          :disabled="Boolean(busyAction)"
        />
        <p class="text-xs text-gray-500">
          Выделите текст или поставьте курсор в абзац, затем выберите
          форматирование. Enter — новый абзац, Shift+Enter — перенос строки.
        </p>
      </div>
      <div class="flex flex-wrap gap-3">
        <UiButton
          type="submit"
          :loading="busyAction === 'document'"
          :disabled="Boolean(busyAction)"
          >{{
            editingDocumentId !== null
              ? 'Сохранить изменения'
              : 'Сохранить версию'
          }}</UiButton
        >
        <UiButton
          v-if="editingDocumentId !== null"
          type="button"
          variant="secondary"
          :disabled="Boolean(busyAction)"
          @click="cancelEdit"
        >
          Отменить редактирование
        </UiButton>
      </div>
    </form>
    <div
      v-if="documents.length"
      ref="documentList"
      class="mt-6 divide-y divide-gray-100 border-t border-gray-100"
    >
      <article
        v-for="document in documents"
        :key="document.id"
        class="legal-document-row grid grid-cols-[minmax(0,1fr)_auto] items-center gap-3 py-4 lg:grid-cols-[minmax(0,1fr)_8rem_16rem]"
      >
        <div class="col-span-2 min-w-0 lg:col-span-1">
          <p class="break-words font-medium text-gray-500">
            {{ optionLabel(documentOptions, document.type) }} ·
            {{ document.version }}
          </p>
          <p class="text-xs text-gray-500">
            ID {{ document.id }} ·
            {{
              document.published_at
                ? `Опубликован ${new Date(document.published_at).toLocaleDateString('ru-RU')}`
                : 'Черновик'
            }}
          </p>
        </div>
        <UiBadge
          class="col-span-2 justify-self-start sm:col-span-1"
          :tone="document.published_at ? 'success' : 'neutral'"
          >{{ document.published_at ? 'Опубликован' : 'Черновик' }}</UiBadge
        >
        <div
          class="col-span-2 flex flex-wrap gap-2 sm:col-span-1 sm:justify-end"
        >
          <UiButton
            v-if="!document.published_at"
            variant="secondary"
            size="sm"
            :data-edit-document="document.id"
            :disabled="Boolean(busyAction) || editingDocumentId !== null"
            :aria-label="`Редактировать ${optionLabel(documentOptions, document.type)} версии ${document.version}`"
            @click="beginDocumentEdit(document)"
            >Редактировать</UiButton
          >
          <UiButton
            variant="secondary"
            size="sm"
            class="justify-self-end"
            :disabled="Boolean(busyAction)"
            :aria-label="`Просмотреть ${optionLabel(documentOptions, document.type)} версии ${document.version}`"
            @click="openPreview(document.id)"
            >Просмотреть</UiButton
          >
        </div>
      </article>
    </div>
    <UiEmptyState v-else class="mt-5" label="Версий документов пока нет." />
  </UiCard>
  <UiDialog
    :open="Boolean(preview)"
    labelledby="legal-preview-title"
    panel-class="w-full max-w-5xl rounded-xl bg-white p-6 shadow-xl"
    :close-disabled="Boolean(busyAction)"
    @close="closePreview"
  >
    <div class="flex items-start justify-between gap-4">
      <h2 id="legal-preview-title" class="text-lg font-semibold text-gray-500">
        {{ preview ? optionLabel(documentOptions, preview.type) : '' }} ·
        {{ preview?.version }}
      </h2>
      <UiButton
        variant="surface"
        aria-label="Закрыть предпросмотр документа"
        :disabled="Boolean(busyAction)"
        @click="closePreview"
      >
        <X :size="20" />
      </UiButton>
    </div>
    <div
      class="mt-4 max-h-[50vh] overflow-y-auto rounded-lg border border-gray-200 bg-gray-25 p-4"
    >
      <UiRichTextEditor
        v-if="preview"
        :key="preview.id"
        :model-value="preview.content"
        :body="preview.body"
        readonly
        label="Предпросмотр документа"
      />
    </div>
    <UiDialogFooter>
      <template #note
        ><span class="block lg:max-w-sm">{{
          preview?.published_at
            ? 'Опубликованная версия'
            : editingDocumentId !== null
              ? 'Сначала сохраните или отмените редактирование черновика.'
              : 'Черновик. Проверьте текст перед публикацией. Если нужны исправления, нажмите «Редактировать».'
        }}</span></template
      >
      <UiButton
        variant="secondary"
        :disabled="Boolean(busyAction)"
        @click="closePreview"
        >Закрыть</UiButton
      >
      <UiButton
        v-if="preview && !preview.published_at"
        variant="secondary"
        :disabled="Boolean(busyAction) || editingDocumentId !== null"
        @click="beginDocumentEdit(preview)"
        >Редактировать</UiButton
      >
      <UiButton
        v-if="preview && !preview.published_at"
        :loading="busyAction === `publish:${preview.id}`"
        :disabled="Boolean(busyAction) || editingDocumentId !== null"
        @click="publishPreview"
        >Опубликовать эту версию</UiButton
      >
    </UiDialogFooter>
  </UiDialog>
</template>
