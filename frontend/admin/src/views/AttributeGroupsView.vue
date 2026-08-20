<script setup lang="ts">
import { computed, onMounted, ref } from "vue";
import { Layers3, Pencil, Plus, Trash2, X } from "@lucide/vue";
import BaseInput from "../components/BaseInput.vue";
import BaseDialog from "../components/BaseDialog.vue";
import PaginationControls from "../components/PaginationControls.vue";
import { usePaginatedCollection } from "../composables/usePaginatedCollection";
import {
  deleteAttributeGroup,
  getAttributeGroups,
  saveAttributeGroup,
  type AttributeGroup,
  type AttributeGroupPayload,
} from "../services/attributeGroups";
import { useAuthStore } from "../stores/auth";
const auth = useAuthStore();
const groupList = usePaginatedCollection<AttributeGroup>("Не удалось загрузить группы.");
const { items: groups, pagination, error, loading } = groupList;
const opened = ref(false);
const editing = ref<AttributeGroup | null>(null);
const deleting = ref<AttributeGroup | null>(null);
const isDeleting = ref(false);
const isSaving = ref(false);
const form = ref<AttributeGroupPayload>({
  name: "",
  slug: "",
  description: "",
  sort_order: 0,
});
const manuallyEditedSlug = ref(false);
const canManage = computed(() => auth.hasPermission("catalog.manage"));
const title = computed(() =>
  editing.value
    ? `Группа: ${editing.value.name}`
    : "Новая группа характеристик",
);
const map: Record<string, string> = {
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
function slug(value: string): string {
  return Array.from(value.toLowerCase(), (char) => map[char] ?? char)
    .join("")
    .normalize("NFKD")
    .replace(/[\u0300-\u036f]/g, "")
    .replace(/[^a-z0-9]+/g, "-")
    .replace(/^-+|-+$/g, "");
}
function updateName(value: string): void {
  form.value.name = value;
  if (!manuallyEditedSlug.value) form.value.slug = slug(value);
}
function open(group: AttributeGroup | null = null): void {
  opened.value = false;
  editing.value = group;
  manuallyEditedSlug.value = group !== null;
  form.value = group
    ? {
        name: group.name,
        slug: group.slug,
        description: group.description ?? "",
        sort_order: group.sort_order,
      }
    : { name: "", slug: "", description: "", sort_order: 0 };
  queueMicrotask(() => {
    opened.value = true;
  });
}
async function load(page = pagination.value?.current_page ?? 1): Promise<void> {
  await groupList.load(page, (requestedPage) =>
    getAttributeGroups({ page: requestedPage }),
  );
}
async function save(): Promise<void> {
  isSaving.value = true;
  try {
    await saveAttributeGroup(editing.value?.id ?? null, form.value);
    opened.value = false;
    await load();
  } catch (reason) {
    error.value =
      reason instanceof Error ? reason.message : "Не удалось сохранить группу.";
  } finally {
    isSaving.value = false;
  }
}
async function remove(): Promise<void> {
  if (!deleting.value) return;
  isDeleting.value = true;
  try {
    await deleteAttributeGroup(deleting.value.id);
    deleting.value = null;
    await groupList.reloadAfterDeletion((page) => getAttributeGroups({ page }));
  } catch (reason) {
    error.value =
      reason instanceof Error ? reason.message : "Не удалось удалить группу.";
  } finally {
    isDeleting.value = false;
  }
}
onMounted(load);
</script>
<template>
  <section class="mx-auto admin-page" :aria-busy="loading">
    <div class="mb-7 flex flex-wrap items-end justify-between gap-4">
      <div>
        <p class="text-sm font-medium text-gray-500">Каталог</p>
        <h1
          class="mt-1 text-2xl font-bold tracking-tight text-gray-900 sm:text-3xl"
        >
          Группы характеристик
        </h1>
      </div>
      <button
        v-if="canManage"
        class="inline-flex items-center gap-2 rounded-lg bg-primary-500 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-primary-600"
        @click="open()"
      >
        <Plus :size="18" />Добавить группу
      </button>
    </div>
    <p
      v-if="error"
      class="mb-4 rounded-lg border border-error-200 bg-error-50 px-4 py-3 text-sm text-error-500"
      role="alert"
    >
      {{ error }}
    </p>
    <p v-if="loading" class="sr-only" role="status">
      Загрузка групп характеристик…
    </p>
    <div
      class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-card"
    >
      <div v-if="groups.length" class="divide-y divide-gray-100">
        <article
          v-for="group in groups"
          :key="group.id"
          class="flex items-center gap-4 p-4 sm:p-5"
        >
          <span
            class="grid h-10 w-10 shrink-0 place-items-center rounded-lg bg-primary-50 text-primary-600"
            ><Layers3 :size="20"
          /></span>
          <div class="min-w-0 flex-1">
            <h2 class="truncate font-semibold text-gray-800">
              {{ group.name }}
            </h2>
            <p class="mt-1 truncate text-sm text-gray-500">
              /{{ group.slug }} · Порядок: {{ group.sort_order }}
            </p>
            <p
              v-if="group.description"
              class="mt-1 truncate text-sm text-gray-500"
            >
              {{ group.description }}
            </p>
          </div>
          <div v-if="canManage" class="flex gap-1">
            <button
              class="rounded-lg p-2 text-gray-500 hover:bg-primary-50 hover:text-primary-600"
              :aria-label="`Редактировать группу ${group.name}`"
              @click="open(group)"
            >
              <Pencil :size="17" /></button
            ><button
              class="rounded-lg p-2 text-gray-500 hover:bg-error-50 hover:text-error-500"
              :aria-label="`Удалить группу ${group.name}`"
              @click="deleting = group"
            >
              <Trash2 :size="17" />
            </button>
          </div>
        </article>
      </div>
      <div v-else-if="!loading" class="px-5 py-14 text-center text-sm text-gray-500">
        Групп пока нет.
      </div>
    </div>
    <PaginationControls
      v-if="pagination"
      :meta="pagination"
      :loading="loading"
      @change="load"
    />
    <BaseDialog
      :open="opened"
      labelledby="attribute-group-dialog-title"
      describedby="attribute-group-dialog-description"
      :close-disabled="isSaving"
      panel-class="w-full max-w-xl"
      @close="opened = false"
    >
      <form
        class="max-h-[90vh] w-full overflow-y-auto rounded-xl bg-white p-6 shadow-xl"
        @submit.prevent="save"
      >
        <div class="flex items-start justify-between">
          <div>
            <h2 id="attribute-group-dialog-title" class="text-lg font-bold text-gray-900">{{ title }}</h2>
            <p id="attribute-group-dialog-description" class="mt-1 text-sm text-gray-500">
              Сгруппируйте характеристики для карточек товаров.
            </p>
          </div>
          <button
            type="button"
            class="rounded-lg p-1 text-gray-500 hover:bg-gray-50"
            aria-label="Закрыть окно группы характеристик"
            :disabled="isSaving"
            @click="opened = false"
          >
            <X :size="20" />
          </button>
        </div>
        <div class="mt-6 grid gap-4">
          <label class="text-sm font-medium text-gray-700"
            >Название<BaseInput
              :model-value="form.name"
              class="mt-1.5"
              data-autofocus
              required
              @update:model-value="updateName" /></label
          ><label class="text-sm font-medium text-gray-700"
            >Технический код (slug)<BaseInput
              :model-value="form.slug"
              class="mt-1.5"
              pattern="[a-z0-9]+(-[a-z0-9]+)*"
              required
              @update:model-value="
                (value) => {
                  form.slug = value;
                  manuallyEditedSlug = true;
                }
              " /></label
          ><label class="text-sm font-medium text-gray-700"
            >Описание<textarea
              v-model="form.description"
              class="mt-1.5 min-h-24 w-full rounded-lg border border-gray-300 p-3 font-normal outline-none focus:border-primary-500"
            /></label
          ><label class="text-sm font-medium text-gray-700"
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
            :disabled="isSaving"
            @click="opened = false"
          >
            Отмена</button
          ><button
            class="rounded-lg bg-primary-500 px-4 py-2.5 text-sm font-semibold text-white hover:bg-primary-600"
            :disabled="isSaving"
          >
            {{ isSaving ? "Сохранение…" : "Сохранить" }}
          </button>
        </div>
      </form>
    </BaseDialog>
    <BaseDialog
      :open="Boolean(deleting)"
      labelledby="delete-attribute-group-title"
      describedby="delete-attribute-group-description"
      :close-disabled="isDeleting"
      overlay-class="z-[60] grid place-items-center p-4"
      panel-class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl"
      @close="deleting = null"
    >
      <template v-if="deleting">
        <h2 id="delete-attribute-group-title" class="text-lg font-bold text-gray-900">Удалить группу?</h2>
        <p id="delete-attribute-group-description" class="mt-3 text-sm text-gray-500">
          Группа «{{ deleting.name }}» будет удалена. Это действие нельзя отменить.
        </p>
        <div class="mt-6 flex justify-end gap-3">
          <button
            class="rounded-lg px-4 py-2.5 text-sm font-semibold text-gray-600 hover:bg-gray-50"
            type="button"
            :disabled="isDeleting"
            @click="deleting = null"
          >
            Отмена</button
          ><button
            class="rounded-lg bg-error-500 px-4 py-2.5 text-sm font-semibold text-white hover:bg-error-700"
            type="button"
            :disabled="isDeleting"
            @click="remove"
          >
            {{ isDeleting ? "Удаление…" : "Удалить" }}
          </button>
        </div>
      </template>
    </BaseDialog>
  </section>
</template>
