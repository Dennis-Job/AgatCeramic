<script setup lang="ts">
import { computed, ref } from 'vue'
import { Copy, FileSpreadsheet, Pencil, Trash2, X } from '@lucide/vue'
import AdminWorkspace from './AdminWorkspace.vue'
import AuthCard from './AuthCard.vue'
import ConfirmDialog from './ConfirmDialog.vue'
import PageHeader from './PageHeader.vue'
import UiAlert from '../ui/UiAlert.vue'
import UiNotification from '../ui/UiNotification.vue'
import UiBadge from '../ui/UiBadge.vue'
import UiButton from '../ui/UiButton.vue'
import UiDialogFooter from '../ui/UiDialogFooter.vue'
import UiCard from '../ui/UiCard.vue'
import UiCheckbox from '../ui/UiCheckbox.vue'
import UiDialog from '../ui/UiDialog.vue'
import UiDatePicker from '../ui/UiDatePicker.vue'
import UiEmptyState from '../ui/UiEmptyState.vue'
import UiField from '../ui/UiField.vue'
import UiFileDropzone from '../ui/UiFileDropzone.vue'
import UiImagePreview from '../ui/UiImagePreview.vue'
import UiInput from '../ui/UiInput.vue'
import UiLoadingState from '../ui/UiLoadingState.vue'
import UiPagination from '../ui/UiPagination.vue'
import UiPopover from '../ui/UiPopover.vue'
import UiRadio from '../ui/UiRadio.vue'
import UiSegmentedControl from '../ui/UiSegmentedControl.vue'
import UiSegmentedTabs from '../ui/UiSegmentedTabs.vue'
import UiSelect from '../ui/UiSelect.vue'
import UiTable from '../ui/UiTable.vue'
import UiTextarea from '../ui/UiTextarea.vue'

type ConfirmMode = 'default' | 'busy' | 'error'

const input = ref('')
const demoFileInput = ref<HTMLInputElement | null>(null)
const demoFile = ref<File | null>(null)
function selectDemoFile(files: FileList | null) {
  demoFile.value = files?.[0] ?? null
}
const populatedInput = ref('Керамогранит')
const searchInput = ref('')
const passwordInput = ref('надёжный-пароль')
const selectedOption = ref('standard')
const emptyOption = ref('')
const clearableOption = ref('extended')
const teleportedOption = ref('standard')
const date = ref('2026-09-13')
const emptyDate = ref('')
const message = ref('')
const checked = ref(false)
const checkedByDefault = ref(true)
const checkboxValues = ref<Array<number | string>>(['porcelain'])
const segmented = ref('')
const importTabDemo = ref('tab-1')
const importTabDemoOptions = [
  {
    id: 'tab-1',
    label: 'Вкладка 1',
    panelId: 'ui-kit-tab-panel-1',
  },
  {
    id: 'tab-2',
    label: 'Вкладка 2',
    panelId: 'ui-kit-tab-panel-2',
  },
  {
    id: 'tab-3',
    label: 'Вкладка 3',
    panelId: 'ui-kit-tab-panel-3',
  },
  {
    id: 'tab-4',
    label: 'Вкладка 4',
    panelId: 'ui-kit-tab-panel-4',
  },
]
const segmentOptions = [
  { value: '', label: 'Все' },
  { value: '1', label: 'Активные', count: 124 },
  { value: '0', label: 'Скрытые', count: 8 },
]
const radio = ref('standard')
const disabledRadio = ref('disabled-selected')
const isDialogOpen = ref(false)
const isPopoverOpen = ref(false)
const confirmMode = ref<ConfirmMode | null>(null)
const confirmBusy = ref(false)
const notice = ref('')
type NotificationTone = 'success' | 'error' | 'warning' | 'info'
const demoNotifications = ref<
  Array<{ id: number; tone: NotificationTone; message: string }>
>([])
let notificationId = 0
function showNotification(tone: NotificationTone): void {
  const messages = {
    success: 'Изменения сохранены.',
    error:
      'Не удалось сохранить изменения. Проверьте подключение и повторите действие. Сообщение с длинным названием: керамогранит для облицовки внутренних стен и пола.',
    warning: 'Проверьте данные перед публикацией.',
    info: 'Обработка файла продолжается в фоне.',
  }
  demoNotifications.value.push({
    id: ++notificationId,
    tone,
    message: messages[tone],
  })
}
function removeNotification(id: number): void {
  demoNotifications.value = demoNotifications.value.filter(
    (item) => item.id !== id,
  )
}
const currentPage = ref(2)
const isButtonLoading = ref(false)

const options = [
  { label: 'Стандартный вариант', value: 'standard' },
  { label: 'Расширенный вариант', value: 'extended' },
  { label: 'Расширенный', value: 'extended-short' },
]

const uiComponents = [
  'UiAlert',
  'UiBadge',
  'UiButton',
  'UiCard',
  'UiCheckbox',
  'UiDatePicker',
  'UiDialog',
  'UiDialogFooter',
  'UiEmptyState',
  'UiField',
  'UiFileDropzone',
  'UiImagePreview',
  'UiInput',
  'UiLoadingState',
  'UiNotification',
  'UiNotificationHost',
  'UiPagination',
  'UiPopover',
  'UiRadio',
  'UiSegmentedControl',
  'UiSegmentedTabs',
  'UiSelect',
  'UiTable',
  'UiTextarea',
]

const sharedComponents = ['AuthCard', 'ConfirmDialog', 'PageHeader']

const semanticColorLevels = [
  '25',
  '50',
  '100',
  '200',
  '300',
  '400',
  '500',
  '600',
  '700',
  '800',
  '900',
  '950',
]

const colorGroups = [
  {
    label: 'Primary',
    tokens: [
      '25',
      '50',
      '100',
      '200',
      '300',
      '400',
      '500',
      '600',
      '700',
      '800',
      '900',
      '950',
    ],
    prefix: '--admin-color-primary-',
  },
  {
    label: 'Gray',
    tokens: [
      '25',
      '50',
      '100',
      '200',
      '300',
      '400',
      '500',
      '600',
      '700',
      '800',
      '900',
      '950',
    ],
    prefix: '--admin-color-gray-',
  },
  {
    label: 'Success',
    tokens: semanticColorLevels,
    prefix: '--admin-color-success-',
  },
  {
    label: 'Warning',
    tokens: semanticColorLevels,
    prefix: '--admin-color-warning-',
  },
  {
    label: 'Error',
    tokens: semanticColorLevels,
    prefix: '--admin-color-error-',
  },
  {
    label: 'Orange',
    tokens: [
      '25',
      '50',
      '100',
      '200',
      '300',
      '400',
      '500',
      '600',
      '700',
      '800',
      '900',
      '950',
    ],
    prefix: '--admin-color-orange-',
  },
  {
    label: 'Blue light',
    tokens: [
      '25',
      '50',
      '100',
      '200',
      '300',
      '400',
      '500',
      '600',
      '700',
      '800',
      '900',
      '950',
    ],
    prefix: '--admin-color-blue-light-',
  },
]

const standaloneColors = [
  { label: 'Page', token: '--admin-color-page' },
  { label: 'White', token: '--admin-color-white' },
  { label: 'Card surface', token: '--admin-color-card-surface' },
  { label: 'Card border', token: '--admin-color-card-border' },
  { label: 'Card inset surface', token: '--admin-color-card-inset-surface' },
  { label: 'Card inset border', token: '--admin-color-card-inset-border' },
  { label: 'Gray dark', token: '--admin-color-gray-dark' },
  { label: 'Text', token: '--admin-color-text' },
  { label: 'Blue 50', token: '--admin-color-blue-50' },
  { label: 'Blue 500', token: '--admin-color-blue-500' },
  { label: 'Blue 700', token: '--admin-color-blue-700' },
  { label: 'Green 500', token: '--admin-color-green-500' },
]

const spacingTokens = ['1', '2', '3', '4', '5', '6']
const radiusTokens = ['md', 'lg', 'xl', '2xl', 'card', 'card-inset']
const controlHeightTokens = ['sm', 'md', 'lg']
const shadowTokens = ['card', 'input', 'dialog', 'dropdown', 'sm', 'xl']
const foundationMetaTokens = [
  '--admin-container-max-width',
  '--admin-shell-max-width',
  '--admin-workspace-overview-max-width',
  '--admin-workspace-form-max-width',
  '--admin-editor-navigation-width',
  '--admin-editor-preview-min-width',
  '--admin-workspace-gutter',
  '--admin-workspace-gutter-compact',
  '--admin-workspace-inline-gutter',
  '--admin-popover-width',
  '--admin-popover-column-width',
  '--admin-shell-top-height',
  '--admin-shell-navigation-height',
  '--admin-shell-height',
  '--admin-logo-filter',
  '--admin-spacing-unit',
  '--admin-border-width',
  '--admin-focus-outline-width',
  '--admin-focus-outline-offset',
  '--admin-focus-ring',
  '--admin-focus-color',
  '--admin-focus-halo-color',
  '--admin-focus-halo-width',
  '--admin-notification-width',
  '--admin-notification-offset',
  '--admin-notification-gap',
  '--admin-notification-z-index',
  '--admin-transition-duration',
  '--admin-transition-timing',
]

const pagination = computed(() => ({
  current_page: currentPage.value,
  last_page: 3,
  per_page: 10,
  total: 24,
  from: (currentPage.value - 1) * 10 + 1,
  to: currentPage.value === 3 ? 24 : currentPage.value * 10,
}))

const firstPage = {
  current_page: 1,
  last_page: 3,
  per_page: 10,
  total: 24,
  from: 1,
  to: 10,
}

const lastPage = {
  current_page: 3,
  last_page: 3,
  per_page: 10,
  total: 24,
  from: 21,
  to: 24,
}

const emptyPage = {
  current_page: 1,
  last_page: 1,
  per_page: 10,
  total: 0,
  from: null,
  to: null,
}

function showLoadingState(): void {
  isButtonLoading.value = true
  window.setTimeout(() => {
    isButtonLoading.value = false
  }, 900)
}

function openConfirm(mode: ConfirmMode): void {
  confirmMode.value = mode
  confirmBusy.value = mode === 'busy'
  if (mode === 'busy') {
    window.setTimeout(() => {
      confirmBusy.value = false
    }, 1200)
  }
}

function closeConfirm(): void {
  confirmMode.value = null
  confirmBusy.value = false
}

function confirmAction(): void {
  closeConfirm()
  notice.value = 'Демонстрационное действие подтверждено.'
}

function submitAuthPreview(): void {
  notice.value = 'Тестовая auth-форма отправлена без API-запроса.'
}
</script>

<template>
  <UiNotification
    v-for="item in demoNotifications"
    :key="item.id"
    :tone="item.tone"
    @dismiss="removeNotification(item.id)"
  >
    {{ item.message }}
  </UiNotification>
  <AdminWorkspace mode="overview" class="space-y-6">
    <PageHeader
      eyebrow="Разработка"
      title="UI-kit"
      description="Визуальная система Seller: белые поверхности, синий акцент и компактные controls. Общие компоненты, их состояния и design tokens для всех экранов Admin."
    >
      <template #actions>
        <UiButton
          size="sm"
          variant="secondary"
          type="button"
          @click="notice = 'Действие PageHeader выполнено.'"
          >Действие страницы</UiButton
        >
      </template>
    </PageHeader>

    <UiAlert tone="info" live="polite">
      Исходники компонентов находятся в <code>src/components/ui/</code> и
      <code>src/components/shared/</code>. Витрина использует сами компоненты,
      поэтому их вид и поведение не дублируются.
    </UiAlert>
    <UiNotification v-if="notice" tone="success" live="polite">{{
      notice
    }}</UiNotification>

    <UiCard
      data-ui-kit-section="inventory"
      aria-labelledby="ui-kit-inventory-title"
    >
      <template #header>
        <div>
          <h2
            id="ui-kit-inventory-title"
            class="text-lg font-semibold text-gray-500"
          >
            Состав UI-kit
          </h2>
          <p class="mt-1 text-sm text-gray-500">
            23 UI primitives и 3 shared-компонента, доступных для повторного
            использования. Layout- и feature-компоненты в этот каталог не
            входят.
          </p>
        </div>
      </template>
      <div class="grid gap-4 lg:grid-cols-2">
        <div>
          <h3 class="text-sm font-semibold text-gray-500">components/ui</h3>
          <div class="mt-2 flex flex-wrap gap-2">
            <UiBadge v-for="component in uiComponents" :key="component">
              {{ component }}
            </UiBadge>
          </div>
        </div>
        <div>
          <h3 class="text-sm font-semibold text-gray-500">components/shared</h3>
          <div class="mt-2 flex flex-wrap gap-2">
            <UiBadge
              v-for="component in sharedComponents"
              :key="component"
              tone="primary"
            >
              {{ component }}
            </UiBadge>
          </div>
        </div>
      </div>
    </UiCard>

    <div class="grid gap-6 xl:grid-cols-2">
      <UiCard
        data-ui-kit-section="buttons"
        aria-labelledby="ui-kit-buttons-title"
      >
        <template #header>
          <div>
            <h2
              id="ui-kit-buttons-title"
              class="text-lg font-semibold text-gray-500"
            >
              Кнопки
            </h2>
            <p class="mt-1 text-sm text-gray-500">
              <code>ui/UiButton.vue</code>
            </p>
          </div>
        </template>

        <h3 class="text-sm font-semibold text-gray-500">Варианты</h3>
        <div class="mt-2 flex flex-wrap items-center gap-3">
          <UiButton>Основная</UiButton>
          <UiButton variant="secondary">Вторичная</UiButton>
          <UiButton variant="surface">На белой поверхности</UiButton>
          <UiButton variant="surface" disabled>Поверхность недоступна</UiButton>
          <UiButton variant="neutral-ghost">Нейтральная прозрачная</UiButton>
          <UiButton variant="neutral-ghost" disabled
            >Нейтральная недоступна</UiButton
          >
          <UiButton variant="danger">Опасное действие</UiButton>
          <UiButton variant="primary-ghost">Синяя прозрачная</UiButton>
          <UiButton variant="danger-ghost">Удалить</UiButton>
        </div>

        <h3 class="mt-5 text-sm font-semibold text-gray-500">Размеры</h3>
        <div class="mt-2 flex flex-wrap items-center gap-3">
          <UiButton size="sm">Small</UiButton>
          <UiButton size="md">Medium</UiButton>
          <UiButton size="lg">Large</UiButton>
        </div>

        <h3 class="mt-5 text-sm font-semibold text-gray-500">Состояния</h3>
        <div
          class="mt-2 flex flex-wrap gap-2"
          aria-label="Кнопки управления с подсказками"
        >
          <UiButton
            variant="neutral-ghost"
            size="sm"
            aria-label="Копировать"
            tooltip="Копировать товар — создать похожий"
            ><Copy :size="17" aria-hidden="true"
          /></UiButton>
          <UiButton variant="primary-ghost" size="sm" aria-label="Редактировать"
            ><Pencil :size="17" aria-hidden="true"
          /></UiButton>
          <UiButton variant="danger-ghost" size="sm" aria-label="Удалить"
            ><Trash2 :size="17" aria-hidden="true"
          /></UiButton>
        </div>
        <div class="mt-2 flex flex-wrap items-center gap-3">
          <UiButton :loading="isButtonLoading" @click="showLoadingState">
            Загрузка
          </UiButton>
          <UiButton loading>Постоянная загрузка</UiButton>
          <UiButton disabled>Недоступна</UiButton>
          <UiButton variant="secondary" disabled>Вторичная недоступна</UiButton>
          <UiButton variant="danger" disabled>Опасная недоступна</UiButton>
          <UiButton variant="primary-ghost" disabled
            >Редактирование недоступно</UiButton
          >
          <UiButton variant="danger-ghost" disabled
            >Удаление недоступно</UiButton
          >
        </div>
      </UiCard>

      <UiCard
        data-ui-kit-section="feedback"
        aria-labelledby="ui-kit-feedback-title"
      >
        <template #header>
          <div>
            <h2
              id="ui-kit-feedback-title"
              class="text-lg font-semibold text-gray-500"
            >
              Статусы и оповещения
            </h2>
            <p class="mt-1 text-sm text-gray-500">
              <code>ui/UiAlert.vue</code>, <code>ui/UiBadge.vue</code>
            </p>
          </div>
        </template>
        <div class="flex flex-wrap gap-2">
          <UiBadge>Нейтральный</UiBadge>
          <UiBadge tone="primary">Основной</UiBadge>
          <UiBadge tone="additional">Дополнительный</UiBadge>
          <UiBadge tone="success">Успешно</UiBadge>
          <UiBadge tone="warning">Внимание</UiBadge>
          <UiBadge tone="danger">Ошибка</UiBadge>
        </div>
        <div class="mt-4 space-y-2">
          <UiButton
            variant="secondary"
            @click="notice = 'Демонстрационное уведомление.'"
            >Показать уведомление</UiButton
          >
          <div class="flex flex-wrap gap-2">
            <UiButton variant="secondary" @click="showNotification('success')"
              >Уведомление об успехе</UiButton
            >
            <UiButton variant="secondary" @click="showNotification('error')"
              >Уведомление об ошибке</UiButton
            >
            <UiButton variant="secondary" @click="showNotification('warning')"
              >Уведомление с предупреждением</UiButton
            >
            <UiButton variant="secondary" @click="showNotification('info')"
              >Информационное уведомление</UiButton
            >
          </div>
          <UiAlert tone="primary" live="polite"
            >Информационное сообщение.</UiAlert
          >
          <UiAlert tone="additional" live="polite"
            >Дополнительное информационное сообщение.</UiAlert
          >
          <UiAlert tone="success" live="polite"
            >Операция выполнена успешно.</UiAlert
          >
          <UiAlert tone="warning" live="polite"
            >Проверьте данные перед сохранением.</UiAlert
          >
          <UiAlert>Не удалось сохранить изменения.</UiAlert>
        </div>
      </UiCard>
    </div>

    <UiCard
      data-ui-kit-section="cards-and-header"
      aria-labelledby="ui-kit-cards-title"
    >
      <template #header>
        <div>
          <h2
            id="ui-kit-cards-title"
            class="text-lg font-semibold text-gray-500"
          >
            Карточки и заголовок страницы
          </h2>
          <p class="mt-1 text-sm text-gray-500">
            <code>ui/UiCard.vue</code>, <code>shared/PageHeader.vue</code>
          </p>
        </div>
      </template>
      <p class="text-sm text-gray-500">
        Текущий заголовок UI-kit выше показывает <code>PageHeader</code> с
        eyebrow, description и actions slot.
      </p>
      <div class="mt-4 grid gap-4 md:grid-cols-2">
        <UiCard>
          <template #header>
            <h3 class="font-semibold text-gray-500">Секционная панель</h3>
          </template>
          <p>
            Заголовок находится в серой шапке, а содержимое — в отдельном белом
            блоке со скруглёнными углами.
          </p>
        </UiCard>
        <UiCard surface="white">
          <template #header>
            <h3 class="font-semibold text-gray-500">Белая поверхность</h3>
          </template>
          <p>Внешний фон, шапка и белое тело используют общие card tokens.</p>
        </UiCard>
        <UiCard class="md:col-span-2">
          <template #header>
            <h3 class="font-semibold text-gray-500">Общий паттерн</h3>
          </template>
          <p>
            <code>UiCard</code> с именованным слотом <code>#header</code>
            автоматически разделяет заголовок и основное содержимое.
          </p>
        </UiCard>
      </div>
    </UiCard>

    <UiCard
      data-ui-kit-section="form-controls"
      aria-labelledby="ui-kit-fields-title"
    >
      <template #header>
        <div>
          <h2
            id="ui-kit-fields-title"
            class="text-lg font-semibold text-gray-500"
          >
            Поля формы
          </h2>
          <p class="break-words text-sm text-gray-500 [overflow-wrap:anywhere]">
            <code>ui/UiField.vue</code>, <code>ui/UiInput.vue</code>,
            <code>ui/UiSelect.vue</code>, <code>ui/UiDatePicker.vue</code>,
            <code>ui/UiTextarea.vue</code>
          </p>
        </div>
      </template>

      <div class="grid min-w-0 gap-6 xl:grid-cols-2">
        <div class="grid min-w-0 grid-cols-[minmax(0,1fr)] content-start gap-4">
          <h3 class="text-sm font-semibold text-gray-500">Input и Textarea</h3>
          <UiField
            label="Название"
            help="Подсказка отображается под полем."
            required
          >
            <UiInput
              v-model="input"
              class="mt-1.5"
              placeholder="Например, Керамогранит"
              required
            />
          </UiField>
          <UiField
            label="Заполненное поле"
            help="Кнопка очистки появляется автоматически."
          >
            <UiInput
              v-model="populatedInput"
              class="mt-1.5"
              aria-label="Заполненное поле"
            />
          </UiField>
          <UiField label="Поиск">
            <UiInput
              v-model="searchInput"
              class="mt-1.5"
              searchable
              aria-label="Поиск по каталогу"
              placeholder="Найти товар"
            />
          </UiField>
          <UiField label="Пароль">
            <UiInput
              v-model="passwordInput"
              class="mt-1.5"
              type="password"
              autocomplete="off"
            />
          </UiField>
          <UiField label="Поле с ошибкой" error="Заполните обязательное поле.">
            <UiInput
              model-value=""
              class="mt-1.5 border-error-500"
              aria-invalid="true"
            />
          </UiField>
          <UiField label="Комментарий">
            <UiTextarea
              v-model="message"
              class="mt-1.5"
              placeholder="Текст комментария"
            />
          </UiField>
          <UiField label="Недоступный комментарий">
            <UiTextarea
              model-value="Текст доступен только для чтения."
              class="mt-1.5"
              disabled
            />
          </UiField>
        </div>

        <div class="grid min-w-0 grid-cols-[minmax(0,1fr)] content-start gap-4">
          <h3 class="text-sm font-semibold text-gray-500">
            Select и DatePicker
          </h3>
          <UiField label="Категория" error="Выберите один вариант.">
            <UiSelect
              v-model="selectedOption"
              class="mt-1.5"
              :options="options"
              accessible-name="Демонстрационная категория"
              searchable
            />
          </UiField>
          <UiField label="Select с placeholder">
            <UiSelect
              v-model="emptyOption"
              class="mt-1.5"
              :options="options"
              accessible-name="Пустой select"
              placeholder="Выберите вариант"
            />
          </UiField>
          <UiField label="Select с очисткой">
            <UiSelect
              v-model="clearableOption"
              class="mt-1.5"
              :options="options"
              accessible-name="Очищаемый select"
              clearable
            />
          </UiField>
          <UiField
            label="Select с teleport menu"
            help="Меню рендерится в body и не обрезается dialog/table overflow."
          >
            <UiSelect
              v-model="teleportedOption"
              class="mt-1.5"
              :options="options"
              accessible-name="Select с teleport menu"
              searchable
              clearable
              teleport-menu
            />
          </UiField>
          <UiField label="Дата публикации">
            <UiDatePicker
              v-model="date"
              class="mt-1.5"
              data-ui-kit-enabled-date
              accessible-name="Дата публикации"
            />
          </UiField>
          <UiField label="Пустая дата">
            <UiDatePicker
              v-model="emptyDate"
              class="mt-1.5"
              accessible-name="Пустая дата"
            />
          </UiField>
        </div>
      </div>

      <div
        data-ui-kit-disabled-fields
        class="mt-6 grid min-w-0 grid-cols-[minmax(0,1fr)] gap-4 rounded-lg border border-gray-200 bg-gray-25 p-4 md:grid-cols-2 xl:grid-cols-4"
      >
        <div class="md:col-span-2 xl:col-span-4">
          <h3 class="text-sm font-semibold text-gray-500">Недоступные поля</h3>
          <p class="mt-1 text-xs text-gray-500">
            Значения видимы, но ввод и вспомогательные действия заблокированы.
          </p>
        </div>
        <UiField label="Поиск товара">
          <UiInput
            model-value="Керамогранит"
            class="mt-1.5"
            searchable
            aria-label="Недоступный поиск товара"
            disabled
          />
        </UiField>
        <UiField label="Категория">
          <UiSelect
            model-value="extended-short"
            class="mt-1.5"
            :options="options"
            accessible-name="Недоступная категория"
            clearable
            searchable
            disabled
          />
        </UiField>
        <UiField label="Дата публикации">
          <UiDatePicker
            model-value="2026-09-13"
            class="mt-1.5"
            accessible-name="Недоступная дата публикации"
            disabled
          />
        </UiField>
        <UiField label="Комментарий">
          <UiTextarea
            model-value="Недоступно для редактирования"
            class="mt-1.5"
            disabled
          />
        </UiField>
      </div>
    </UiCard>

    <UiCard
      data-ui-kit-section="selection"
      aria-labelledby="ui-kit-selection-title"
    >
      <template #header>
        <div>
          <h2
            id="ui-kit-selection-title"
            class="text-lg font-semibold text-gray-500"
          >
            Флажки и переключатели
          </h2>
          <p class="mt-1 text-sm text-gray-500">
            <code>ui/UiCheckbox.vue</code>, <code>ui/UiRadio.vue</code>
          </p>
        </div>
      </template>
      <div class="grid gap-6 lg:grid-cols-2">
        <div class="grid content-start gap-3">
          <h3 class="text-sm font-semibold text-gray-500">Checkbox</h3>
          <UiCheckbox
            v-model:checked="checked"
            mode="boolean"
            accessible-name="Публиковать товар"
            >Невыбранный boolean</UiCheckbox
          >
          <UiCheckbox
            v-model:checked="checkedByDefault"
            mode="boolean"
            accessible-name="Видимый в каталоге"
            >Выбранный boolean</UiCheckbox
          >
          <UiCheckbox
            v-model="checkboxValues"
            value="porcelain"
            accessible-name="Керамогранит в наборе"
            >Значение в массиве</UiCheckbox
          >
          <UiCheckbox
            :checked="true"
            mode="boolean"
            disabled
            accessible-name="Недоступный выбранный флажок"
            >Недоступный выбранный вариант</UiCheckbox
          >
          <UiCheckbox
            :checked="false"
            mode="boolean"
            disabled
            accessible-name="Недоступный невыбранный флажок"
            >Недоступный невыбранный вариант</UiCheckbox
          >
        </div>
        <div class="grid content-start gap-3">
          <h3 class="text-sm font-semibold text-gray-500">Radio</h3>
          <UiRadio v-model="radio" name="ui-kit-radio" value="standard">
            Выбранный
          </UiRadio>
          <UiRadio v-model="radio" name="ui-kit-radio" value="extended">
            Невыбранный
          </UiRadio>
          <UiRadio
            v-model="disabledRadio"
            name="ui-kit-disabled-radio"
            value="disabled-selected"
            disabled
          >
            Недоступный выбранный
          </UiRadio>
          <UiRadio
            v-model="disabledRadio"
            name="ui-kit-disabled-radio"
            value="disabled"
            disabled
          >
            Недоступный
          </UiRadio>
        </div>
      </div>
    </UiCard>

    <UiCard data-ui-kit-section="tabs" aria-labelledby="ui-kit-tabs-title">
      <template #header>
        <div>
          <h2
            id="ui-kit-tabs-title"
            class="text-lg font-semibold text-gray-500"
          >
            Вкладки
          </h2>
          <p class="mt-1 text-sm text-gray-500">
            <code>ui/UiSegmentedTabs.vue</code>
          </p>
        </div>
      </template>
      <div class="space-y-4">
        <UiSegmentedTabs
          v-model="importTabDemo"
          id-prefix="ui-kit-import-tab"
          label="Тип загрузки"
          :options="importTabDemoOptions"
        />
        <div
          v-for="option in importTabDemoOptions"
          v-show="importTabDemo === option.id"
          :id="option.panelId"
          :key="option.id"
          role="tabpanel"
          tabindex="0"
          :aria-labelledby="`ui-kit-import-tab-${option.id}`"
          class="rounded-xl bg-gray-25 p-4 text-sm text-gray-500"
        >
          {{ `Пример содержимого для вкладки ${option.id.slice(-1)}.` }}
        </div>
      </div>
    </UiCard>

    <UiCard
      data-ui-kit-section="segmented"
      aria-labelledby="ui-kit-segmented-title"
    >
      <template #header>
        <div>
          <h2
            id="ui-kit-segmented-title"
            class="text-lg font-semibold text-gray-500"
          >
            Сегментированные переключатели
          </h2>
          <p class="mt-1 text-sm text-gray-500">
            <code>ui/UiSegmentedControl.vue</code>
          </p>
        </div>
      </template>
      <div class="grid gap-6 lg:grid-cols-2">
        <UiSegmentedControl
          v-model="segmented"
          name="ui-kit-segmented"
          label="Состояние"
          :options="segmentOptions"
        />
        <UiSegmentedControl
          model-value="1"
          name="ui-kit-segmented-disabled"
          label="Недоступный переключатель"
          :options="segmentOptions"
          disabled
        />
      </div>
    </UiCard>

    <UiCard
      data-ui-kit-section="table-and-pagination"
      aria-labelledby="ui-kit-table-title"
    >
      <template #header>
        <div>
          <h2
            id="ui-kit-table-title"
            class="text-lg font-semibold text-gray-500"
          >
            Таблица и пагинация
          </h2>
          <p class="mt-1 text-sm text-gray-500">
            <code>ui/UiTable.vue</code>, <code>ui/UiPagination.vue</code>
          </p>
        </div>
      </template>
      <div>
        <UiTable min-width="min-w-[680px]" label="Пример компонентов UI-kit">
          <thead class="bg-gray-25 text-xs font-medium text-gray-500">
            <tr>
              <th class="px-5 py-3">Компонент</th>
              <th class="px-5 py-3">Расположение</th>
              <th class="px-5 py-3">Назначение</th>
            </tr>
          </thead>
          <tbody>
            <tr class="border-t border-gray-100 text-gray-500">
              <td class="px-5 py-4 font-medium">UiButton</td>
              <td class="px-5 py-4"><code>components/ui</code></td>
              <td class="px-5 py-4">Действия и отправка форм</td>
            </tr>
            <tr class="border-t border-gray-100 text-gray-500">
              <td class="px-5 py-4 font-medium">PageHeader</td>
              <td class="px-5 py-4"><code>components/shared</code></td>
              <td class="px-5 py-4">Заголовок route-level страницы</td>
            </tr>
          </tbody>
        </UiTable>
        <UiPagination
          :meta="pagination"
          aria-label="Пагинация: интерактивный пример"
          @change="currentPage = $event"
        />
      </div>

      <div class="mt-6 grid gap-4 xl:grid-cols-2">
        <div class="rounded-lg border border-gray-200 bg-gray-25 p-4">
          <h3 class="text-sm font-semibold text-gray-500">Первая страница</h3>
          <UiPagination
            :meta="firstPage"
            aria-label="Пагинация: первая страница"
            :announce="false"
          />
        </div>
        <div class="rounded-lg border border-gray-200 bg-gray-25 p-4">
          <h3 class="text-sm font-semibold text-gray-500">
            Последняя страница
          </h3>
          <UiPagination
            :meta="lastPage"
            aria-label="Пагинация: последняя страница"
            :announce="false"
          />
        </div>
        <div class="rounded-lg border border-gray-200 bg-gray-25 p-4">
          <h3 class="text-sm font-semibold text-gray-500">Загрузка</h3>
          <UiPagination
            :meta="pagination"
            aria-label="Пагинация: загрузка"
            loading
            :announce="false"
          />
        </div>
        <div class="rounded-lg border border-gray-200 bg-gray-25 p-4">
          <h3 class="text-sm font-semibold text-gray-500">Нулевой total</h3>
          <p class="mt-2 text-sm text-gray-500">
            При <code>total=0</code> компонент не рендерит navigation.
          </p>
          <UiPagination
            :meta="emptyPage"
            aria-label="Пагинация: пустой список"
            :announce="false"
          />
        </div>
      </div>
    </UiCard>

    <div class="grid gap-6 xl:grid-cols-2">
      <UiCard
        data-ui-kit-section="collection-states"
        aria-labelledby="ui-kit-collections-title"
      >
        <template #header>
          <div>
            <h2
              id="ui-kit-collections-title"
              class="text-lg font-semibold text-gray-500"
            >
              Состояния коллекций
            </h2>
            <p class="mt-1 text-sm text-gray-500">
              <code>ui/UiLoadingState.vue</code>,
              <code>ui/UiEmptyState.vue</code>
            </p>
          </div>
        </template>
        <UiLoadingState label="Загрузка данных для примера…" />
        <UiEmptyState label="В этой коллекции пока нет данных.">
          <UiButton size="sm" variant="soft-blue" type="button"
            >Добавить элемент</UiButton
          >
        </UiEmptyState>
        <div class="mt-4 max-w-xs">
          <p class="mb-2 text-sm text-gray-500">
            <code>ui/UiImagePreview.vue</code>: пустое изображение
          </p>
          <UiImagePreview :url="null" alt="Пример изображения" />
        </div>
      </UiCard>

      <UiCard data-ui-kit-section="file-upload">
        <template #header>
          <h2 class="text-lg font-semibold text-gray-500">
            Выбор и перетаскивание файла
          </h2>
          <p class="mt-1 text-sm text-gray-500">
            <code>ui/UiFileDropzone.vue</code>
          </p>
        </template>
        <input
          ref="demoFileInput"
          type="file"
          accept=".xlsx"
          aria-label="Файл Excel для примера"
          tabindex="-1"
          class="hidden"
          @change="selectDemoFile(($event.target as HTMLInputElement).files)"
        />
        <UiFileDropzone
          label="Выберите или перетащите Excel в эту область"
          description="Формат — XLSX. Размер — не больше 10 МБ."
          format-label="XLSX"
          icon-tone="green"
          @choose="demoFileInput?.click()"
          @files="selectDemoFile"
        >
          <template #icon
            ><FileSpreadsheet :size="44" :stroke-width="1.4"
          /></template>
        </UiFileDropzone>
        <output
          v-if="demoFile"
          class="mt-3 block text-sm text-gray-500 [overflow-wrap:anywhere]"
          aria-live="polite"
          >{{ demoFile.name }}</output
        >
        <UiFileDropzone
          class="mt-4"
          label="Выбор файла недоступен"
          description="Действие заблокировано на время загрузки."
          format-label="IMG"
          disabled
        />
      </UiCard>

      <UiCard
        data-ui-kit-section="dialogs"
        aria-labelledby="ui-kit-dialogs-title"
      >
        <template #header>
          <div>
            <h2
              id="ui-kit-dialogs-title"
              class="text-lg font-semibold text-gray-500"
            >
              Диалоги
            </h2>
            <p class="mt-1 text-sm text-gray-500">
              <code>ui/UiDialog.vue</code>,
              <code>shared/ConfirmDialog.vue</code>
            </p>
          </div>
        </template>
        <div class="flex flex-wrap gap-3">
          <UiButton variant="secondary" @click="isDialogOpen = true">
            Открыть диалог
          </UiButton>
          <UiButton variant="danger" @click="openConfirm('default')">
            Подтверждение удаления
          </UiButton>
          <UiButton variant="secondary" @click="openConfirm('busy')">
            Confirm: busy
          </UiButton>
          <UiButton variant="secondary" @click="openConfirm('error')">
            Confirm: error
          </UiButton>
        </div>
        <UiPopover
          v-model:open="isPopoverOpen"
          class="mt-4"
          label="Пример всплывающего окна"
        >
          <template #trigger="{ trigger }"
            ><UiButton v-bind="trigger" variant="secondary"
              >Всплывающее окно</UiButton
            ></template
          >
          <p class="text-sm text-gray-500">
            Наведите курсор или откройте окно с клавиатуры. Escape закроет его и
            вернёт фокус.
          </p>
        </UiPopover>
        <p class="mt-3 text-xs text-gray-500">
          Busy-пример разблокируется через 1,2 секунды, чтобы из него можно было
          выйти.
        </p>
      </UiCard>
    </div>

    <UiCard data-ui-kit-section="auth-card" aria-labelledby="ui-kit-auth-title">
      <template #header>
        <div>
          <h2
            id="ui-kit-auth-title"
            class="text-lg font-semibold text-gray-500"
          >
            Auth shell
          </h2>
          <p class="mt-1 text-sm text-gray-500">
            <code>shared/AuthCard.vue</code>
          </p>
        </div>
      </template>
      <div class="rounded-xl bg-gray-50 p-4 sm:p-6">
        <AuthCard
          class="mx-auto"
          heading-tag="h2"
          title="Пример auth-формы"
          description="Так выглядит общая оболочка login, forgot-password и reset-password."
          @submit="submitAuthPreview"
        >
          <UiField class="mt-6" label="Email" required>
            <UiInput
              model-value="admin@example.test"
              type="email"
              autocomplete="off"
              required
            />
          </UiField>
          <UiButton class="mt-5 w-full" type="submit">Продолжить</UiButton>
        </AuthCard>
      </div>
    </UiCard>

    <UiCard
      data-ui-kit-section="design-tokens"
      aria-labelledby="ui-kit-tokens-title"
    >
      <template #header>
        <div>
          <h2
            id="ui-kit-tokens-title"
            class="text-lg font-semibold text-gray-500"
          >
            Design tokens
          </h2>
          <p class="mt-1 text-sm text-gray-500">
            <code>styles/tokens.css</code> — единственный source of truth для
            визуальных констант Admin.
          </p>
        </div>
      </template>

      <section data-ui-kit-tokens="colors">
        <h3 class="text-base font-semibold text-gray-500">Цвета</h3>
        <div class="mt-4 space-y-5">
          <div v-for="group in colorGroups" :key="group.label">
            <h4 class="text-sm font-semibold text-gray-500">
              {{ group.label }}
            </h4>
            <div
              class="mt-2 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6"
            >
              <div v-for="token in group.tokens" :key="token" class="min-w-0">
                <div
                  class="h-12 rounded-lg border border-gray-200"
                  :style="{ backgroundColor: `var(${group.prefix}${token})` }"
                  aria-hidden="true"
                />
                <p class="mt-1 text-xs font-semibold text-gray-500">
                  {{ token }}
                </p>
                <code class="block break-all text-xs leading-4 text-gray-500">
                  {{ group.prefix }}{{ token }}
                </code>
              </div>
            </div>
          </div>
          <div>
            <h4 class="text-sm font-semibold text-gray-500">Отдельные цвета</h4>
            <div
              class="mt-2 grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-8"
            >
              <div
                v-for="color in standaloneColors"
                :key="color.token"
                class="min-w-0"
              >
                <div
                  class="h-12 rounded-lg border border-gray-200"
                  :style="{ backgroundColor: `var(${color.token})` }"
                  aria-hidden="true"
                />
                <p class="mt-1 text-xs font-semibold text-gray-500">
                  {{ color.label }}
                </p>
                <code class="block break-all text-xs leading-4 text-gray-500">
                  {{ color.token }}
                </code>
              </div>
            </div>
          </div>
        </div>
      </section>

      <div
        class="mt-8 grid gap-6 xl:grid-cols-2"
        data-ui-kit-tokens="foundations"
      >
        <section>
          <h3 class="text-base font-semibold text-gray-500">Типографика</h3>
          <div class="mt-3 space-y-4 rounded-lg border border-gray-200 p-4">
            <div>
              <p class="font-sans text-lg font-normal text-gray-500">
                Nunito 400 — основной текст
              </p>
              <p class="font-sans text-lg font-medium text-gray-500">
                Nunito 500 — medium
              </p>
              <p class="font-sans text-lg font-semibold text-gray-500">
                Nunito 600 — semibold
              </p>
              <p class="font-sans text-lg font-bold text-gray-500">
                Nunito 700 — bold
              </p>
            </div>
            <div class="border-t border-gray-200 pt-4 font-poppins">
              <p class="text-lg font-normal text-gray-500">
                Poppins 400 — display
              </p>
              <p class="text-lg font-medium text-gray-500">
                Poppins 500 — medium
              </p>
              <p class="text-lg font-semibold text-gray-500">
                Poppins 600 — semibold
              </p>
              <p class="text-lg font-bold text-gray-500">Poppins 700 — bold</p>
            </div>
            <code class="text-xs text-gray-500"
              >--admin-font-sans / --admin-font-display</code
            >
          </div>
        </section>

        <section>
          <h3 class="text-base font-semibold text-gray-500">Spacing</h3>
          <div class="mt-3 space-y-3 rounded-lg border border-gray-200 p-4">
            <div
              v-for="token in spacingTokens"
              :key="token"
              class="flex items-center gap-3"
            >
              <code class="w-40 shrink-0 text-xs text-gray-500"
                >--admin-spacing-{{ token }}</code
              >
              <span
                class="h-3 rounded-sm bg-primary-500"
                :style="{ width: `var(--admin-spacing-${token})` }"
                aria-hidden="true"
              />
            </div>
          </div>
        </section>

        <section>
          <h3 class="text-base font-semibold text-gray-500">
            Радиусы и border
          </h3>
          <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div v-for="token in radiusTokens" :key="token" class="text-center">
              <div
                class="h-20 border-2 border-primary-500 bg-primary-50"
                :style="{ borderRadius: `var(--admin-radius-${token})` }"
                aria-hidden="true"
              />
              <code class="mt-2 block break-all text-xs text-gray-500"
                >--admin-radius-{{ token }}</code
              >
            </div>
          </div>
          <div
            class="mt-3 rounded-lg p-3 text-sm text-gray-500"
            style="border: var(--admin-border-default)"
          >
            <code>--admin-border-default</code>
          </div>
        </section>

        <section>
          <h3 class="text-base font-semibold text-gray-500">Высота controls</h3>
          <div
            class="mt-3 flex flex-wrap items-end gap-4 rounded-lg border border-gray-200 p-4"
          >
            <div
              v-for="token in controlHeightTokens"
              :key="token"
              class="text-center"
            >
              <div
                class="grid w-24 place-items-center rounded-lg border border-gray-300 bg-white text-xs text-gray-500"
                :style="{ height: `var(--admin-control-height-${token})` }"
              >
                {{ token }}
              </div>
              <code class="mt-2 block break-all text-xs text-gray-500"
                >--admin-control-height-{{ token }}</code
              >
            </div>
          </div>
        </section>

        <section class="xl:col-span-2">
          <h3 class="text-base font-semibold text-gray-500">Тени</h3>
          <div
            class="mt-3 grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6"
          >
            <div
              v-for="token in shadowTokens"
              :key="token"
              class="grid h-24 place-items-center rounded-lg border border-gray-100 bg-white p-3 text-center"
              :style="{ boxShadow: `var(--admin-shadow-${token})` }"
            >
              <code class="break-all text-xs text-gray-500"
                >--admin-shadow-{{ token }}</code
              >
            </div>
          </div>
        </section>

        <section class="xl:col-span-2">
          <h3 class="text-base font-semibold text-gray-500">
            Focus и transition
          </h3>
          <div
            class="mt-3 flex flex-wrap items-center gap-4 rounded-lg border border-gray-200 p-4"
          >
            <UiButton type="button" variant="secondary">
              Перейдите Tab для focus ring
            </UiButton>
            <code
              v-for="token in foundationMetaTokens"
              :key="token"
              class="text-xs text-gray-500"
              >{{ token }}</code
            >
          </div>
        </section>
      </div>
    </UiCard>

    <UiDialog
      :open="isDialogOpen"
      labelledby="ui-kit-dialog-title"
      describedby="ui-kit-dialog-description"
      panel-class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl"
      @close="isDialogOpen = false"
    >
      <div class="flex items-start justify-between gap-4">
        <h2 id="ui-kit-dialog-title" class="text-lg font-bold text-gray-500">
          Обычный диалог
        </h2>
        <UiButton
          type="button"
          variant="surface"
          aria-label="Закрыть обычный диалог"
          @click="isDialogOpen = false"
        >
          <X :size="20" />
        </UiButton>
      </div>
      <UiDialogFooter>
        <template #note
          ><span id="ui-kit-dialog-description"
            >Проверьте закрытие через Escape, клик по фону и возврат
            фокуса.</span
          ></template
        >
        <UiButton @click="isDialogOpen = false">Закрыть</UiButton>
      </UiDialogFooter>
    </UiDialog>
    <ConfirmDialog
      :open="confirmMode !== null"
      title="Удалить демонстрационный элемент?"
      description="Это безопасный пример: реальные данные не изменятся."
      confirm-label="Подтвердить"
      :busy="confirmBusy"
      :error="
        confirmMode === 'error'
          ? 'Не удалось выполнить демонстрационное действие.'
          : ''
      "
      @close="closeConfirm"
      @confirm="confirmAction"
    />
  </AdminWorkspace>
</template>
