<script setup lang="ts">
import { computed, onMounted, ref } from "vue";
import { FolderTree, Pencil, Plus, Trash2, X } from "@lucide/vue";
import BaseCheckbox from "../components/BaseCheckbox.vue";
import BaseInput from "../components/BaseInput.vue";
import BaseSelect from "../components/BaseSelect.vue";
import {
  deleteCategory,
  getCategories,
  saveCategory,
  type Category,
  type CategoryPayload,
} from "../services/categories";
import { useAuthStore } from "../stores/auth";
const auth = useAuthStore();
const categories = ref<Category[]>([]);
const error = ref("");
const opened = ref(false);
const editing = ref<Category | null>(null);
const deleting = ref<Category | null>(null);
const isDeleting = ref(false);
const form = ref<CategoryPayload>({
  parent_id: null,
  name: "",
  slug: "",
  description: "",
  is_parent: false,
  is_active: true,
  sort_order: 0,
});
const isSlugManuallyEdited = ref(false);
const canManage = computed(() => auth.hasPermission("catalog.manage"));
const title = computed(() =>
  editing.value ? `Категория: ${editing.value.name}` : "Новая категория",
);
const transliterationMap: Record<string, string> = {
  а: "a",
  б: "b",
  в: "v",
  г: "g",
  д: "d",
  е: "e",
  ё: "yo",
  ж: "zh",
  з: "z",
  и: "i",
  й: "y",
  к: "k",
  л: "l",
  м: "m",
  н: "n",
  о: "o",
  п: "p",
  р: "r",
  с: "s",
  т: "t",
  у: "u",
  ф: "f",
  х: "kh",
  ц: "ts",
  ч: "ch",
  ш: "sh",
  щ: "shch",
  ъ: "",
  ы: "y",
  ь: "",
  э: "e",
  ю: "yu",
  я: "ya",
};
function toSlug(value: string): string {
  return Array.from(
    value.toLowerCase(),
    (character) => transliterationMap[character] ?? character,
  )
    .join("")
    .normalize("NFKD")
    .replace(/[\u0300-\u036f]/g, "")
    .replace(/[^a-z0-9]+/g, "-")
    .replace(/^-+|-+$/g, "");
}
function updateName(name: string): void {
  form.value.name = name;
  if (!isSlugManuallyEdited.value) form.value.slug = toSlug(name);
}
function updateSlug(slug: string): void {
  form.value.slug = slug;
  isSlugManuallyEdited.value = true;
}
function open(category: Category | null = null): void {
  opened.value = false;
  editing.value = category;
  isSlugManuallyEdited.value = category !== null;
  form.value = category
    ? {
        parent_id: category.parent_id ?? null,
        name: category.name,
        slug: category.slug,
        description: category.description ?? "",
        is_parent: category.is_parent,
        is_active: category.is_active,
        sort_order: category.sort_order,
      }
    : {
        parent_id: null,
        name: "",
        slug: "",
        description: "",
        is_parent: false,
        is_active: true,
        sort_order: 0,
      };
  queueMicrotask(() => {
    opened.value = true;
  });
}
function flattenTree(items: Category[], depth = 0): Category[] {
  return items.flatMap((item) => [
    { ...item, name: `${"— ".repeat(depth)}${item.name}` },
    ...flattenTree(item.children ?? [], depth + 1),
  ]);
}
function descendantIds(category: Category): Set<number> {
  return new Set(
    (category.children ?? []).flatMap((child) => [
      child.id,
      ...descendantIds(child),
    ]),
  );
}
const parentOptions = computed(() => [
  { label: "Без родителя", value: "" },
  ...categories.value
    .filter(
      (category) =>
        category.is_parent &&
        !(
          editing.value
            ? new Set([editing.value.id, ...descendantIds(editing.value)])
            : new Set<number>()
        ).has(category.id),
    )
    .map((category) => ({ label: category.name, value: String(category.id) })),
]);
const selectedParentId = computed({
  get: () =>
    form.value.parent_id === null ? "" : String(form.value.parent_id),
  set: (value: string) => {
    form.value.parent_id = value === "" ? null : Number(value);
  },
});
function categoryName(id: number | null | undefined): string | null {
  return id === null || id === undefined
    ? null
    : (categories.value
        .find((category) => category.id === id)
        ?.name.replace(/^(— )+/, "") ?? null);
}
async function load(): Promise<void> {
  try {
    categories.value = flattenTree(await getCategories());
  } catch (reason) {
    error.value =
      reason instanceof Error
        ? reason.message
        : "Не удалось загрузить категории.";
  }
}
async function save(): Promise<void> {
  try {
    await saveCategory(editing.value?.id ?? null, form.value);
    opened.value = false;
    await load();
  } catch (reason) {
    error.value =
      reason instanceof Error
        ? reason.message
        : "Не удалось сохранить категорию.";
  }
}
function remove(category: Category): void {
  deleting.value = category;
}
async function confirmRemoval(): Promise<void> {
  if (!deleting.value) return;
  isDeleting.value = true;
  try {
    await deleteCategory(deleting.value.id);
    deleting.value = null;
    await load();
  } catch (reason) {
    error.value =
      reason instanceof Error
        ? reason.message
        : "Не удалось удалить категорию.";
  } finally {
    isDeleting.value = false;
  }
}
onMounted(load);
</script>
<template>
  <section class="mx-auto admin-page">
    <div class="mb-7 flex flex-wrap items-end justify-between gap-4">
      <div>
        <p class="text-sm font-medium text-gray-500">Каталог</p>
        <h1
          class="mt-1 text-2xl font-bold tracking-tight text-gray-900 sm:text-3xl"
        >
          Категории
        </h1>
      </div>
      <button
        v-if="canManage"
        class="inline-flex items-center gap-2 rounded-lg bg-primary-500 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-primary-600"
        @click="open()"
      >
        <Plus :size="18" />Добавить категорию
      </button>
    </div>
    <p
      v-if="error"
      class="mb-4 rounded-lg border border-error-200 bg-error-50 px-4 py-3 text-sm text-error-500"
    >
      {{ error }}
    </p>
    <div
      class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-card"
    >
      <div v-if="categories.length" class="divide-y divide-gray-100">
        <article
          v-for="category in categories"
          :key="category.id"
          class="flex items-center gap-4 p-4 sm:p-5"
        >
          <span
            class="grid h-10 w-10 shrink-0 place-items-center rounded-lg bg-primary-50 text-primary-600"
            ><FolderTree :size="20"
          /></span>
          <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-2">
              <h2 class="truncate font-semibold text-gray-800">
                {{ category.name }}
              </h2>
              <span
                class="admin-badge rounded-full px-2 py-1 text-xs font-medium"
                :class="
                  category.is_active
                    ? 'bg-success-50 text-success-600'
                    : 'bg-gray-100 text-gray-500'
                "
                >{{ category.is_active ? "Активна" : "Скрыта" }}</span
              ><span
                v-if="category.is_parent"
                class="admin-badge rounded-full bg-primary-50 px-2 py-1 text-xs font-medium text-primary-600"
                >Родительская</span
              >
            </div>
            <p class="mt-1 truncate text-sm text-gray-500">
              /{{ category.slug }} · Порядок: {{ category.sort_order }}
            </p>
            <p
              v-if="category.parent_id"
              class="mt-1 text-xs font-medium text-gray-500"
            >
              Родитель:
              <span class="text-primary-600">{{ categoryName(category.parent_id) }}</span>
            </p>
            <p
              v-if="category.is_parent"
              class="mt-1 text-xs font-medium text-primary-600"
            >
              Подкатегорий: {{ category.children?.length ?? 0 }}
            </p>
          </div>
          <div v-if="canManage" class="flex gap-1">
            <button
              class="rounded-lg p-2 text-gray-500 hover:bg-primary-50 hover:text-primary-600"
              @click="open(category)"
            >
              <Pencil :size="17" /></button
            ><button
              class="rounded-lg p-2 text-gray-500 hover:bg-error-50 hover:text-error-500"
              @click="remove(category)"
            >
              <Trash2 :size="17" />
            </button>
          </div>
        </article>
      </div>
      <div v-else class="px-5 py-14 text-center text-sm text-gray-500">
        Категорий пока нет.
      </div>
    </div>
    <div
      v-if="opened"
      class="fixed inset-0 z-50 grid place-items-center bg-gray-900/50 p-4"
      @click.self="opened = false"
    >
      <form
        class="admin-dialog-content w-full max-w-2xl overflow-y-auto rounded-xl bg-white p-6 shadow-xl"
        @submit.prevent="save"
      >
        <div class="flex items-start justify-between">
          <div>
            <h2 class="text-lg font-bold text-gray-900">{{ title }}</h2>
            <p class="mt-1 text-sm text-gray-500">
              Настройте отображаемое название и адрес страницы категории.
            </p>
          </div>
          <button
            type="button"
            class="rounded-lg p-1 text-gray-500 hover:bg-gray-50"
            @click="opened = false"
          >
            <X :size="20" />
          </button>
        </div>
        <div class="mt-6 grid gap-4">
          <div class="grid gap-4 sm:grid-cols-2">
            <label class="text-sm font-medium text-gray-700"
              >Название<BaseInput
                :model-value="form.name"
                class="mt-1.5"
                required
                @update:model-value="updateName" /></label
            ><label class="text-sm font-medium text-gray-700"
              >Технический код (slug)<BaseInput
                :model-value="form.slug"
                class="mt-1.5"
                pattern="[a-z0-9]+(-[a-z0-9]+)*"
                required
                @update:model-value="updateSlug"
            /></label>
          </div>
          <label class="text-sm font-medium text-gray-700"
            >Описание<textarea
              v-model="form.description"
              class="mt-1.5 min-h-24 w-full rounded-lg border border-gray-300 p-3 font-normal outline-none focus:border-primary-500"
            /></label
          ><label class="text-sm font-medium text-gray-700"
            >Родительская категория<BaseSelect
              v-model="selectedParentId"
              class="mt-1.5 w-full font-normal"
              accessible-name="Родительская категория"
              :options="parentOptions"
          /></label>
          <div class="grid gap-4 sm:grid-cols-2">
            <BaseCheckbox
              class="mt-7"
              mode="boolean"
              :checked="form.is_parent"
              @update:checked="form.is_parent = $event"
              >Родительская категория</BaseCheckbox
            ><BaseCheckbox
              class="mt-7"
              mode="boolean"
              :checked="form.is_active"
              @update:checked="form.is_active = $event"
              >Категория активна</BaseCheckbox
            >
          </div>
          <label class="text-sm font-medium text-gray-700"
            >Порядок сортировки<input
              :value="form.sort_order"
              class="mt-1.5 w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm outline-none focus:border-primary-500"
              min="0"
              type="number"
              required
              @input="
                form.sort_order = Number(
                  ($event.target as HTMLInputElement).value,
                )
              "
          /></label>
        </div>
        <div class="mt-6 flex justify-end gap-3">
          <button
            type="button"
            class="rounded-lg px-4 py-2.5 text-sm font-semibold text-gray-600 hover:bg-gray-50"
            @click="opened = false"
          >
            Отмена</button
          ><button
            class="rounded-lg bg-primary-500 px-4 py-2.5 text-sm font-semibold text-white hover:bg-primary-600"
          >
            Сохранить
          </button>
        </div>
      </form>
    </div>
  </section>
  <div
    v-if="deleting"
    class="fixed inset-0 z-[60] grid place-items-center bg-gray-900/50 p-4"
    @click.self="!isDeleting && (deleting = null)"
  >
    <section
      class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl"
      role="dialog"
      aria-modal="true"
      aria-labelledby="delete-category-title"
    >
      <h2 id="delete-category-title" class="text-lg font-bold text-gray-900">
        Удалить категорию?
      </h2>
      <p class="mt-3 text-sm leading-6 text-gray-500">
        Категория «{{ deleting.name }}» будет удалена. Это действие нельзя
        отменить.
      </p>
      <div class="mt-6 flex justify-end gap-3">
        <button
          type="button"
          class="rounded-lg px-4 py-2.5 text-sm font-semibold text-gray-600 disabled:cursor-not-allowed disabled:opacity-60"
          :disabled="isDeleting"
          @click="deleting = null"
        >
          Отмена</button
        ><button
          type="button"
          class="rounded-lg bg-error-500 px-4 py-2.5 text-sm font-semibold text-white hover:bg-error-700 disabled:cursor-not-allowed disabled:opacity-60"
          :disabled="isDeleting"
          @click="confirmRemoval"
        >
          {{ isDeleting ? "Удаление…" : "Удалить" }}
        </button>
      </div>
    </section>
  </div>
</template>
