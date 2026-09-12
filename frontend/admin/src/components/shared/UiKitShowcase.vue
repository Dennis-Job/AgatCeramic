<script setup lang="ts">
import { computed, ref } from 'vue'
import ConfirmDialog from './ConfirmDialog.vue'
import PageHeader from './PageHeader.vue'
import UiAlert from '../ui/UiAlert.vue'
import UiBadge from '../ui/UiBadge.vue'
import UiButton from '../ui/UiButton.vue'
import UiCard from '../ui/UiCard.vue'
import UiCheckbox from '../ui/UiCheckbox.vue'
import UiDialog from '../ui/UiDialog.vue'
import UiEmptyState from '../ui/UiEmptyState.vue'
import UiField from '../ui/UiField.vue'
import UiInput from '../ui/UiInput.vue'
import UiLoadingState from '../ui/UiLoadingState.vue'
import UiPagination from '../ui/UiPagination.vue'
import UiRadio from '../ui/UiRadio.vue'
import UiSelect from '../ui/UiSelect.vue'
import UiTable from '../ui/UiTable.vue'
import UiTextarea from '../ui/UiTextarea.vue'

const input = ref('')
const selectedOption = ref('standard')
const message = ref('')
const checked = ref(false)
const radio = ref('standard')
const isDialogOpen = ref(false)
const isConfirmDialogOpen = ref(false)
const notice = ref('')
const currentPage = ref(2)
const isButtonLoading = ref(false)

const options = [
  { label: 'Стандартный вариант', value: 'standard' },
  { label: 'Расширенный вариант', value: 'extended' },
]

const pagination = computed(() => ({
  current_page: currentPage.value,
  last_page: 3,
  per_page: 10,
  total: 24,
  from: (currentPage.value - 1) * 10 + 1,
  to: currentPage.value === 3 ? 24 : currentPage.value * 10,
}))

function showLoadingState(): void {
  isButtonLoading.value = true
  window.setTimeout(() => { isButtonLoading.value = false }, 900)
}

function confirmAction(): void {
  isConfirmDialogOpen.value = false
  notice.value = 'Демонстрационное действие подтверждено.'
}
</script>

<template>
  <section class="mx-auto admin-page space-y-6">
    <PageHeader
      eyebrow="Разработка"
      title="UI-kit"
      description="Временная живая витрина компонентов Admin. Используйте эти компоненты в новых экранах вместо локальных копий UI primitives."
    />

    <UiAlert tone="info" live="polite">
      Исходники компонентов находятся в <code>src/components/ui/</code> и <code>src/components/shared/</code>.
    </UiAlert>
    <UiAlert v-if="notice" tone="success" live="polite">{{ notice }}</UiAlert>

    <div class="grid gap-6 xl:grid-cols-2">
      <UiCard>
        <h2 class="text-lg font-semibold text-gray-900">Кнопки</h2>
        <p class="mt-1 text-sm text-gray-500"><code>ui/UiButton.vue</code></p>
        <div class="mt-4 flex flex-wrap gap-3">
          <UiButton>Основная</UiButton>
          <UiButton variant="secondary">Вторичная</UiButton>
          <UiButton variant="danger">Опасное действие</UiButton>
          <UiButton variant="ghost">Прозрачная</UiButton>
          <UiButton variant="danger-ghost">Удалить</UiButton>
          <UiButton :loading="isButtonLoading" @click="showLoadingState">Загрузка</UiButton>
          <UiButton disabled>Недоступна</UiButton>
        </div>
      </UiCard>

      <UiCard>
        <h2 class="text-lg font-semibold text-gray-900">Статусы</h2>
        <p class="mt-1 text-sm text-gray-500"><code>ui/UiAlert.vue</code>, <code>ui/UiBadge.vue</code></p>
        <div class="mt-4 flex flex-wrap gap-2">
          <UiBadge>Нейтральный</UiBadge>
          <UiBadge tone="primary">Основной</UiBadge>
          <UiBadge tone="success">Успешно</UiBadge>
          <UiBadge tone="warning">Внимание</UiBadge>
          <UiBadge tone="danger">Ошибка</UiBadge>
        </div>
        <div class="mt-4 space-y-2">
          <UiAlert tone="success" live="polite">Операция выполнена успешно.</UiAlert>
          <UiAlert tone="warning" live="polite">Проверьте данные перед сохранением.</UiAlert>
          <UiAlert>Не удалось сохранить изменения.</UiAlert>
        </div>
      </UiCard>

      <UiCard>
        <h2 class="text-lg font-semibold text-gray-900">Поля формы</h2>
        <p class="mt-1 text-sm text-gray-500"><code>ui/UiField.vue</code>, <code>ui/UiInput.vue</code>, <code>ui/UiSelect.vue</code>, <code>ui/UiTextarea.vue</code></p>
        <div class="mt-4 grid gap-4">
          <UiField label="Название" help="Подсказка отображается под полем." required>
            <UiInput v-model="input" class="mt-1.5" placeholder="Например, Керамогранит" />
          </UiField>
          <UiField label="Категория" error="Выберите один вариант.">
            <UiSelect v-model="selectedOption" class="mt-1.5" :options="options" accessible-name="Демонстрационная категория" searchable />
          </UiField>
          <UiField label="Комментарий">
            <UiTextarea v-model="message" class="mt-1.5" placeholder="Текст комментария" />
          </UiField>
        </div>
      </UiCard>

      <UiCard>
        <h2 class="text-lg font-semibold text-gray-900">Выбор</h2>
        <p class="mt-1 text-sm text-gray-500"><code>ui/UiCheckbox.vue</code>, <code>ui/UiRadio.vue</code></p>
        <div class="mt-4 grid gap-3">
          <UiCheckbox v-model:checked="checked" mode="boolean" accessible-name="Публиковать товар">Публиковать товар</UiCheckbox>
          <UiCheckbox :checked="true" mode="boolean" disabled accessible-name="Недоступный выбранный флажок">Недоступный выбранный вариант</UiCheckbox>
          <div class="grid gap-2 sm:grid-cols-2">
            <UiRadio v-model="radio" name="ui-kit-radio" value="standard">Стандартный</UiRadio>
            <UiRadio v-model="radio" name="ui-kit-radio" value="extended">Расширенный</UiRadio>
          </div>
        </div>
      </UiCard>
    </div>

    <UiCard>
      <h2 class="text-lg font-semibold text-gray-900">Таблица и пагинация</h2>
      <p class="mt-1 text-sm text-gray-500"><code>ui/UiTable.vue</code>, <code>ui/UiPagination.vue</code></p>
      <div class="mt-4">
        <UiTable min-width="min-w-[560px]" label="Пример компонентов UI-kit">
          <thead class="bg-gray-25 text-xs font-medium text-gray-500">
            <tr><th class="px-5 py-3">Компонент</th><th class="px-5 py-3">Расположение</th><th class="px-5 py-3">Назначение</th></tr>
          </thead>
          <tbody>
            <tr class="border-t border-gray-100 text-gray-700"><td class="px-5 py-4 font-medium">UiButton</td><td class="px-5 py-4"><code>components/ui</code></td><td class="px-5 py-4">Действия и отправка форм</td></tr>
            <tr class="border-t border-gray-100 text-gray-700"><td class="px-5 py-4 font-medium">PageHeader</td><td class="px-5 py-4"><code>components/shared</code></td><td class="px-5 py-4">Заголовок route-level страницы</td></tr>
          </tbody>
        </UiTable>
        <UiPagination :meta="pagination" @change="currentPage = $event" />
      </div>
    </UiCard>

    <div class="grid gap-6 xl:grid-cols-2">
      <UiCard>
        <h2 class="text-lg font-semibold text-gray-900">Состояния коллекций</h2>
        <p class="mt-1 text-sm text-gray-500"><code>ui/UiLoadingState.vue</code>, <code>ui/UiEmptyState.vue</code></p>
        <UiLoadingState label="Загрузка данных для примера…" />
        <UiEmptyState label="В этой коллекции пока нет данных." />
      </UiCard>

      <UiCard>
        <h2 class="text-lg font-semibold text-gray-900">Диалоги</h2>
        <p class="mt-1 text-sm text-gray-500"><code>ui/UiDialog.vue</code>, <code>shared/ConfirmDialog.vue</code></p>
        <div class="mt-4 flex flex-wrap gap-3">
          <UiButton variant="secondary" @click="isDialogOpen = true">Открыть диалог</UiButton>
          <UiButton variant="danger" @click="isConfirmDialogOpen = true">Подтверждение удаления</UiButton>
        </div>
      </UiCard>
    </div>

    <UiDialog :open="isDialogOpen" labelledby="ui-kit-dialog-title" describedby="ui-kit-dialog-description" panel-class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl" @close="isDialogOpen = false">
      <h2 id="ui-kit-dialog-title" class="text-lg font-bold text-gray-900">Обычный диалог</h2>
      <p id="ui-kit-dialog-description" class="mt-2 text-sm text-gray-500">Проверьте закрытие через Escape, клик по фону и возврат фокуса.</p>
      <div class="mt-6 flex justify-end"><UiButton @click="isDialogOpen = false">Закрыть</UiButton></div>
    </UiDialog>
    <ConfirmDialog
      :open="isConfirmDialogOpen"
      title="Удалить демонстрационный элемент?"
      description="Это безопасный пример: реальные данные не изменятся."
      confirm-label="Подтвердить"
      @close="isConfirmDialogOpen = false"
      @confirm="confirmAction"
    />
  </section>
</template>
