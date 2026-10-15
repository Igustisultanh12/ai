<template>
  <div class="rounded-xl border border-line bg-surface overflow-hidden text-xs font-mono shadow-lg my-3">
    <!-- Header -->
    <div class="px-4 py-2 bg-card border-b border-line flex items-center justify-between text-ink/80">
      <div class="flex items-center gap-2">
        <span class="text-violet-600 dark:text-violet-400 font-semibold">{{ patch.path }}</span>
        <span v-if="patch.is_new" class="px-1.5 py-0.5 rounded bg-emerald-500/20 text-emerald-600 dark:text-emerald-400 text-[10px]">Berkas Baru</span>
        <span v-else class="px-1.5 py-0.5 rounded bg-amber-500/20 text-amber-700 dark:text-amber-400 text-[10px]">Dimodifikasi</span>
      </div>
      <div class="text-[11px] text-muted">
        {{ lineDiffs.length }} baris perubahan
      </div>
    </div>

    <!-- Diff lines list -->
    <div class="max-h-72 overflow-y-auto divide-y divide-line select-text">
      <div
        v-for="(line, idx) in lineDiffs"
        :key="idx"
        class="flex items-start px-3 py-0.5 leading-5 font-mono text-[12px]"
        :class="{
          'bg-emerald-500/10 text-emerald-600 dark:text-emerald-300': line.type === 'add',
          'bg-rose-500/10 text-rose-600 dark:text-rose-300 line-through opacity-80': line.type === 'del',
          'text-muted': line.type === 'same',
        }"
      >
        <span class="w-6 shrink-0 text-muted/70 select-none text-right pr-2">
          {{ line.type === 'add' ? '+' : (line.type === 'del' ? '-' : ' ') }}
        </span>
        <pre class="flex-1 whitespace-pre-wrap break-all font-mono">{{ line.text }}</pre>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';

export interface PatchItem {
    path: string;
    is_new?: boolean;
    old_content?: string;
    new_content?: string;
}

const props = defineProps<{
    patch: PatchItem;
}>();

const lineDiffs = computed(() => {
    const oldLines = (props.patch.old_content || '').split('\n');
    const newLines = (props.patch.new_content || '').split('\n');

    const result: Array<{ type: 'same' | 'add' | 'del'; text: string }> = [];

    if (props.patch.is_new || !props.patch.old_content) {
        newLines.forEach(line => {
            result.push({ type: 'add', text: line });
        });
        return result;
    }

    // Simple line by line comparator
    let i = 0;
    let j = 0;
    while (i < oldLines.length || j < newLines.length) {
        if (i < oldLines.length && j < newLines.length) {
            if (oldLines[i] === newLines[j]) {
                result.push({ type: 'same', text: oldLines[i] });
                i++;
                j++;
            } else {
                result.push({ type: 'del', text: oldLines[i] });
                result.push({ type: 'add', text: newLines[j] });
                i++;
                j++;
            }
        } else if (i < oldLines.length) {
            result.push({ type: 'del', text: oldLines[i] });
            i++;
        } else if (j < newLines.length) {
            result.push({ type: 'add', text: newLines[j] });
            j++;
        }
    }

    return result;
});
</script>
