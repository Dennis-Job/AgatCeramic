<script setup lang="ts" generic="T extends Permission">
import { useId } from 'vue'
import { CircleCheck } from '@lucide/vue'
import type { Permission } from '../types/access.types'
import type { PermissionGroup } from '../utils/permissions'

defineProps<{ groups: PermissionGroup<T>[]; assigned?: boolean }>()
const headingId = useId()
</script>

<template>
  <div class="space-y-5">
    <section
      v-for="(group, index) in groups"
      :key="group.module"
      :aria-labelledby="`${headingId}-${index}`"
    >
      <h3
        :id="`${headingId}-${index}`"
        class="rounded-lg bg-gray-50 px-4 py-3 text-sm font-semibold text-gray-500 [overflow-wrap:anywhere]"
      >
        {{ group.name }}
      </h3>
      <ul class="divide-y divide-gray-100">
        <li
          v-for="permission in group.permissions"
          :key="permission.id"
          class="flex min-w-0 items-start gap-3 py-4 sm:px-4"
        >
          <CircleCheck
            v-if="assigned"
            :size="20"
            class="mt-0.5 shrink-0 text-primary-500"
            aria-hidden="true"
          />
          <div class="min-w-0 flex-1">
            <h4
              class="break-words text-sm font-semibold text-gray-500 [overflow-wrap:anywhere]"
            >
              {{ permission.name }}
            </h4>
            <p
              class="mt-1 whitespace-pre-wrap break-words text-sm text-gray-500 [overflow-wrap:anywhere]"
            >
              {{ permission.description || 'Описание не добавлено.' }}
            </p>
            <code class="mt-1 block break-all text-xs text-gray-500">{{
              permission.code
            }}</code>
            <slot name="details" :permission="permission" />
          </div>
        </li>
      </ul>
    </section>
  </div>
</template>
