<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { EditorContent, useEditor, type JSONContent } from '@tiptap/vue-3'
import StarterKit from '@tiptap/starter-kit'
import TextAlign from '@tiptap/extension-text-align'
import {
  AlignLeft,
  AlignRight,
  AlignJustify,
  Bold,
  Italic,
  List,
  ListOrdered,
  Link,
  Unlink,
  Undo2,
  Redo2,
} from '@lucide/vue'
import UiButton from './UiButton.vue'
import UiInput from './UiInput.vue'
import { documentContent, plainDocument, safeRichTextLink } from './richText'

const props = withDefaults(
  defineProps<{
    modelValue?: JSONContent | null
    body?: string
    disabled?: boolean
    readonly?: boolean
    required?: boolean
    label?: string
  }>(),
  {
    modelValue: null,
    body: '',
    disabled: false,
    readonly: false,
    required: false,
    label: 'Текст документа',
  },
)
const emit = defineEmits<{
  'update:modelValue': [value: JSONContent]
  'update:body': [value: string]
}>()
const linkOpen = ref(false)
const linkUrl = ref('')
const linkError = ref('')
const editor = useEditor({
  content: props.modelValue ?? plainDocument(props.body),
  editable: !props.disabled && !props.readonly,
  extensions: [
    StarterKit.configure({
      heading: { levels: [2, 3] },
      blockquote: false,
      code: false,
      codeBlock: false,
      horizontalRule: false,
      strike: false,
      underline: false,
      trailingNode: false,
      link: {
        openOnClick: false,
        autolink: false,
        linkOnPaste: false,
        isAllowedUri: safeRichTextLink,
      },
    }),
    TextAlign.configure({
      types: ['heading', 'paragraph'],
      alignments: ['left', 'right', 'justify'],
      defaultAlignment: 'left',
    }),
  ],
  editorProps: {
    attributes: {
      class: 'ui-rich-text__content',
      role: props.readonly ? 'document' : 'textbox',
      'aria-label': props.label,
      ...(!props.readonly
        ? { 'aria-multiline': 'true', 'aria-required': String(props.required) }
        : {}),
      ...(props.readonly ? { tabindex: '-1' } : {}),
    },
  },
  onUpdate: ({ editor }) => {
    emit('update:modelValue', documentContent(editor.getJSON()))
    emit('update:body', editor.getText({ blockSeparator: '\n' }))
  },
})
watch(
  () => props.disabled,
  (value) => editor.value?.setEditable(!value && !props.readonly),
)
watch(
  () => [props.modelValue, props.body] as const,
  () => {
    const content = props.modelValue ?? plainDocument(props.body)
    if (
      JSON.stringify(content) !==
      JSON.stringify(documentContent(editor.value?.getJSON() ?? {}))
    )
      editor.value?.commands.setContent(content, { emitUpdate: false })
  },
)
const controls = computed(() => [
  {
    label: 'Обычный текст',
    text: 'Текст',
    active: editor.value?.isActive('paragraph'),
    run: () => editor.value?.chain().focus().setParagraph().run(),
  },
  {
    label: 'Заголовок 2',
    text: 'H2',
    active: editor.value?.isActive('heading', { level: 2 }),
    run: () => editor.value?.chain().focus().toggleHeading({ level: 2 }).run(),
  },
  {
    label: 'Заголовок 3',
    text: 'H3',
    active: editor.value?.isActive('heading', { level: 3 }),
    run: () => editor.value?.chain().focus().toggleHeading({ level: 3 }).run(),
  },
  {
    label: 'Жирный',
    icon: Bold,
    active: editor.value?.isActive('bold'),
    run: () => editor.value?.chain().focus().toggleBold().run(),
  },
  {
    label: 'Курсив',
    icon: Italic,
    active: editor.value?.isActive('italic'),
    run: () => editor.value?.chain().focus().toggleItalic().run(),
  },
  {
    label: 'Маркированный список',
    icon: List,
    active: editor.value?.isActive('bulletList'),
    run: () => editor.value?.chain().focus().toggleBulletList().run(),
  },
  {
    label: 'Нумерованный список',
    icon: ListOrdered,
    active: editor.value?.isActive('orderedList'),
    run: () => editor.value?.chain().focus().toggleOrderedList().run(),
  },
  ...[
    { value: 'left', label: 'По левому краю', icon: AlignLeft },
    { value: 'right', label: 'По правому краю', icon: AlignRight },
    { value: 'justify', label: 'По ширине', icon: AlignJustify },
  ].map((item) => ({
    label: item.label,
    icon: item.icon,
    active: editor.value?.isActive({ textAlign: item.value }),
    run: () => editor.value?.chain().focus().setTextAlign(item.value).run(),
  })),
])
function openLink() {
  linkUrl.value = editor.value?.getAttributes('link').href ?? ''
  linkError.value = ''
  linkOpen.value = !linkOpen.value
}
function closeLink() {
  linkOpen.value = false
  editor.value?.commands.focus()
}
function applyLink() {
  const href = linkUrl.value.trim()
  if (!safeRichTextLink(href)) {
    linkError.value = 'Введите ссылку https://, http://, mailto: или путь от /.'
    return
  }
  editor.value?.chain().focus().extendMarkRange('link').setLink({ href }).run()
  linkOpen.value = false
}
</script>

<template>
  <div
    class="ui-rich-text"
    :class="{ 'ui-rich-text--readonly': readonly, 'opacity-50': disabled }"
  >
    <template v-if="!readonly">
      <div
        role="toolbar"
        aria-label="Форматирование текста"
        class="flex flex-wrap gap-1 border-b border-gray-200 p-2"
      >
        <UiButton
          v-for="control in controls"
          :key="control.label"
          type="button"
          size="sm"
          :variant="control.active ? 'soft-blue' : 'surface'"
          :aria-label="control.label"
          :aria-pressed="Boolean(control.active)"
          :disabled="disabled || !editor"
          :tooltip="control.label"
          @mousedown.prevent
          @click="control.run()"
        >
          <component
            :is="control.icon"
            v-if="control.icon"
            :size="16"
            aria-hidden="true"
          />
          <span v-else>{{ control.text }}</span>
        </UiButton>
        <UiButton
          type="button"
          size="sm"
          variant="surface"
          aria-label="Добавить или изменить ссылку"
          :aria-expanded="linkOpen"
          :disabled="disabled || !editor"
          tooltip="Ссылка"
          @mousedown.prevent
          @click="openLink"
          ><Link :size="16" aria-hidden="true"
        /></UiButton>
        <UiButton
          type="button"
          size="sm"
          variant="surface"
          aria-label="Убрать ссылку"
          :disabled="disabled || !editor?.isActive('link')"
          tooltip="Убрать ссылку"
          @mousedown.prevent
          @click="editor?.chain().focus().unsetLink().run()"
          ><Unlink :size="16" aria-hidden="true"
        /></UiButton>
        <UiButton
          type="button"
          size="sm"
          variant="surface"
          aria-label="Отменить"
          :disabled="disabled || !editor?.can().undo()"
          tooltip="Отменить"
          @mousedown.prevent
          @click="editor?.chain().focus().undo().run()"
          ><Undo2 :size="16" aria-hidden="true"
        /></UiButton>
        <UiButton
          type="button"
          size="sm"
          variant="surface"
          aria-label="Повторить"
          :disabled="disabled || !editor?.can().redo()"
          tooltip="Повторить"
          @mousedown.prevent
          @click="editor?.chain().focus().redo().run()"
          ><Redo2 :size="16" aria-hidden="true"
        /></UiButton>
      </div>
      <div
        v-if="linkOpen"
        class="space-y-2 border-b border-gray-200 p-3"
        @keydown.esc.stop.prevent="closeLink"
      >
        <label class="block text-sm text-gray-500"
          >Адрес ссылки<UiInput
            v-model="linkUrl"
            :disabled="disabled"
            placeholder="https://example.ru"
            @keydown.enter.prevent="applyLink"
        /></label>
        <p v-if="linkError" role="alert" class="text-sm text-error-500">
          {{ linkError }}
        </p>
        <div class="flex flex-wrap gap-2">
          <UiButton
            type="button"
            size="sm"
            :disabled="disabled"
            @click="applyLink"
            >Применить ссылку</UiButton
          ><UiButton
            type="button"
            size="sm"
            variant="surface"
            @click="closeLink"
            >Отмена</UiButton
          >
        </div>
      </div>
    </template>
    <EditorContent :editor="editor" />
  </div>
</template>

<style scoped>
.ui-rich-text {
  min-width: 0;
  border: 1px solid var(--admin-color-gray-300);
  border-radius: var(--admin-radius-lg);
  background: var(--admin-color-white);
  color: var(--admin-color-text);
}
.ui-rich-text :deep(.ui-rich-text__content) {
  min-height: 14rem;
  padding: var(--admin-spacing-4);
  outline: none;
  overflow-wrap: anywhere;
  white-space: pre-wrap;
  font-size: 0.875rem;
  line-height: 1.8;
}
.ui-rich-text :deep(.ui-rich-text__content:focus-visible) {
  outline: 2px solid var(--admin-color-primary-500);
  outline-offset: 2px;
}
.ui-rich-text :deep(p) {
  margin: 0 0 0.8em;
}
.ui-rich-text :deep(h2) {
  font-size: 1.5em;
  font-weight: 600;
  margin: 1.2em 0 0.6em;
}
.ui-rich-text :deep(h3) {
  font-size: 1.2em;
  font-weight: 600;
  margin: 1.2em 0 0.6em;
}
.ui-rich-text :deep(ul) {
  list-style: disc;
  padding-left: 1.5em;
}
.ui-rich-text :deep(ol) {
  list-style: decimal;
  padding-left: 1.5em;
}
.ui-rich-text :deep(a) {
  color: var(--admin-color-primary-500);
  text-decoration: underline;
}
.ui-rich-text--readonly {
  border: 0;
  background: transparent;
}
.ui-rich-text--readonly :deep(.ui-rich-text__content) {
  min-height: 0;
  padding: 0;
}
</style>
