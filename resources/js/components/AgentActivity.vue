<template>
  <div class="agent-activity text-xs">
    <!-- Header: animated glyph + live status + elapsed timer -->
    <button
      type="button"
      class="flex items-center gap-2.5 max-w-full text-left group"
      :aria-expanded="expanded"
      @click="expanded = !expanded"
    >
      <span class="glyph shrink-0" :class="{ 'is-idle': !active }" aria-hidden="true">
        <i></i><i></i><i></i><i></i>
      </span>
      <span class="truncate font-medium" :class="active ? 'shimmer' : 'text-slate-400'">{{ status }}</span>
      <span class="shrink-0 tabular-nums text-slate-500">{{ elapsedLabel }}</span>
      <svg
        v-if="steps.length"
        class="w-3.5 h-3.5 shrink-0 text-slate-500 transition-transform duration-200 group-hover:text-slate-300"
        :class="expanded ? 'rotate-180' : ''"
        viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"
      >
        <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.06l3.71-3.83a.75.75 0 111.08 1.04l-4.25 4.39a.75.75 0 01-1.08 0L5.21 8.27a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
      </svg>
    </button>

    <!-- Steps -->
    <transition name="steps">
      <ol v-if="expanded && steps.length" class="mt-2.5 ml-1.5 pl-4 border-l border-slate-700/70 space-y-1.5">
        <li
          v-for="s in steps"
          :key="s.id"
          class="step relative flex items-start gap-2 rounded-lg px-2.5 py-1.5 bg-slate-950/50 border border-slate-800/80"
        >
          <span class="mt-[3px] shrink-0">
            <span v-if="s.status === 'running'" class="block w-2.5 h-2.5 rounded-full border-2 border-orange-400 border-t-transparent animate-spin"></span>
            <svg v-else-if="s.status === 'done'" class="w-3 h-3 text-emerald-400" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 010 1.4l-7.5 7.5a1 1 0 01-1.4 0L3.3 9.7a1 1 0 111.4-1.4l3.8 3.79 6.8-6.8a1 1 0 011.4 0z" clip-rule="evenodd" /></svg>
            <svg v-else class="w-3 h-3 text-rose-400" viewBox="0 0 20 20" fill="currentColor"><path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z" /></svg>
          </span>
          <span class="min-w-0 flex-1">
            <span class="block truncate" :class="s.status === 'error' ? 'text-rose-300' : s.status === 'running' ? 'text-slate-100' : 'text-slate-400'">
              {{ s.label }}
            </span>
            <span v-if="s.error" class="block text-[11px] text-rose-400/80 break-words">{{ s.error }}</span>
          </span>
          <a
            v-if="s.link"
            :href="s.link"
            target="_blank"
            rel="noopener"
            class="shrink-0 text-cyan-400 hover:text-cyan-300 underline underline-offset-2"
          >lihat</a>
          <span class="shrink-0 tabular-nums text-[11px] text-slate-500">{{ stepDuration(s) }}</span>
        </li>
      </ol>
    </transition>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onBeforeUnmount, watch } from 'vue';
import type { AgentStep } from '@/types/agent';

const props = withDefaults(defineProps<{
  status: string;
  steps: AgentStep[];
  startedAt: number;
  active?: boolean;
}>(), { active: true });

const expanded = ref(true);
const now = ref(Date.now());
let timer: ReturnType<typeof setInterval> | null = null;

const formatDuration = (ms: number) => {
  const total = Math.max(0, Math.floor(ms / 1000));
  const m = Math.floor(total / 60);
  const s = total % 60;
  return m > 0 ? `${m}m ${s}s` : `${s}s`;
};

const elapsedLabel = computed(() => formatDuration(now.value - props.startedAt));

const stepDuration = (s: AgentStep) =>
  s.startedAt ? formatDuration((s.endedAt ?? now.value) - s.startedAt) : '';

const start = () => {
  if (!timer) timer = setInterval(() => (now.value = Date.now()), 1000);
};
const stop = () => {
  if (timer) clearInterval(timer);
  timer = null;
  now.value = Date.now();
};

watch(() => props.active, (a) => (a ? start() : stop()));
onMounted(() => (props.active ? start() : stop()));
onBeforeUnmount(stop);
</script>

<style scoped>
/* Four dots that keep regrouping, like a small constellation */
.glyph {
  position: relative;
  width: 14px;
  height: 14px;
}
.glyph i {
  position: absolute;
  width: 3.5px;
  height: 3.5px;
  border-radius: 9999px;
  background: #fb923c; /* orange-400 */
  box-shadow: 0 0 6px rgba(251, 146, 60, 0.55);
  animation: regroup 2.4s cubic-bezier(0.65, 0, 0.35, 1) infinite;
}
.glyph i:nth-child(1) { animation-delay: 0s; }
.glyph i:nth-child(2) { animation-delay: -0.6s; }
.glyph i:nth-child(3) { animation-delay: -1.2s; }
.glyph i:nth-child(4) { animation-delay: -1.8s; opacity: 0.75; }
.glyph.is-idle i { animation: none; background: #64748b; box-shadow: none; }
.glyph.is-idle i:nth-child(1) { left: 0; top: 5px; }
.glyph.is-idle i:nth-child(2) { left: 5px; top: 5px; }
.glyph.is-idle i:nth-child(3) { left: 10px; top: 5px; }
.glyph.is-idle i:nth-child(4) { display: none; }

@keyframes regroup {
  0%   { left: 0;    top: 5px;  transform: scale(1); }
  20%  { left: 5px;  top: 0;    transform: scale(1.25); }
  40%  { left: 10px; top: 5px;  transform: scale(0.8); }
  60%  { left: 5px;  top: 10px; transform: scale(1.1); }
  80%  { left: 2px;  top: 2px;  transform: scale(0.7); }
  100% { left: 0;    top: 5px;  transform: scale(1); }
}

/* Light sweeping across the status text */
.shimmer {
  background: linear-gradient(90deg, #94a3b8 0%, #94a3b8 35%, #f8fafc 50%, #94a3b8 65%, #94a3b8 100%);
  background-size: 250% 100%;
  -webkit-background-clip: text;
  background-clip: text;
  color: transparent;
  animation: shimmer 2.2s linear infinite;
}
@keyframes shimmer {
  0%   { background-position: 100% 0; }
  100% { background-position: -150% 0; }
}

.step::before {
  content: '';
  position: absolute;
  left: -1.3rem;
  top: 0.75rem;
  width: 0.55rem;
  height: 1px;
  background: rgb(51 65 85 / 0.7);
}

.steps-enter-active, .steps-leave-active { transition: opacity 0.18s ease, transform 0.18s ease; }
.steps-enter-from, .steps-leave-to { opacity: 0; transform: translateY(-4px); }

@media (prefers-reduced-motion: reduce) {
  .glyph i, .shimmer { animation: none; }
  .shimmer { color: #e2e8f0; background: none; }
}
</style>
