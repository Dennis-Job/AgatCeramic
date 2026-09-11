<script setup lang="ts">import { Inbox } from '@lucide/vue'; defineProps<{ label: string }>()</script>
<template><div class="flex min-h-36 flex-col items-center justify-center gap-2 px-5 py-12 text-center text-sm text-gray-500" role="status" aria-live="polite"><Inbox :size="22" class="text-gray-400" aria-hidden="true"/><p>{{ label }}</p><slot/></div></template>
