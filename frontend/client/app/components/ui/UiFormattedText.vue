<script setup lang="ts">
import {
  renderFormattedText,
  type FormattedTextNode,
} from '~/utils/formattedText'

const props = withDefaults(
  defineProps<{ content?: FormattedTextNode | null; body?: string }>(),
  { content: null, body: '' },
)
const FormattedNodes = () => renderFormattedText(props.content ?? {})
</script>

<template>
  <div class="ui-formatted-text">
    <FormattedNodes v-if="content?.type === 'doc'" />
    <p v-else class="ui-formatted-text__plain">{{ body }}</p>
  </div>
</template>

<style scoped>
.ui-formatted-text {
  overflow-wrap: anywhere;
  font-size: 15px;
  line-height: 1.8;
}
.ui-formatted-text :deep(p) {
  margin: 0 0 1em;
  white-space: pre-wrap;
}
.ui-formatted-text :deep(h2) {
  margin: 1.8em 0 0.7em;
  font-size: clamp(24px, 3vw, 32px);
  font-weight: 500;
  line-height: 1.3;
  letter-spacing: -0.02em;
}
.ui-formatted-text :deep(h3) {
  margin: 1.6em 0 0.6em;
  font-size: clamp(20px, 2.5vw, 24px);
  font-weight: 500;
  line-height: 1.4;
}
.ui-formatted-text :deep(ul),
.ui-formatted-text :deep(ol) {
  padding-inline-start: 1.5em;
  margin-block: 1em;
}
.ui-formatted-text :deep(ul) {
  list-style: disc;
}
.ui-formatted-text :deep(ol) {
  list-style: decimal;
}
.ui-formatted-text :deep(a) {
  color: inherit;
  text-decoration: underline;
  text-underline-offset: 0.2em;
}
.ui-formatted-text :deep(a:focus-visible) {
  outline: 1px solid var(--color-ink);
  outline-offset: 4px;
}
.ui-formatted-text :deep(> :first-child) {
  margin-top: 0;
}
.ui-formatted-text :deep(> :last-child) {
  margin-bottom: 0;
}
</style>
