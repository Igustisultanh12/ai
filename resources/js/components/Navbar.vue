<template>
  <header class="sticky top-0 z-30 flex h-16 items-center justify-between gap-3 border-b border-line bg-surface/75 px-4 backdrop-blur-xl sm:px-6">
    <div class="flex min-w-0 items-center gap-3">
      <button
        type="button"
        class="btn-ghost grid h-10 w-10 place-items-center rounded-xl md:hidden"
        aria-label="Buka menu"
        @click="$emit('toggleSidebar')"
      >
        <Icon name="menu" class="h-5 w-5" />
      </button>
      <div class="hidden min-w-0 sm:block">
        <p class="truncate font-display text-lg font-bold text-ink">{{ greeting }}, {{ firstName }}</p>
        <p class="truncate text-xs text-muted">{{ todayLabel }}</p>
      </div>
    </div>

    <div class="flex items-center gap-2 sm:gap-3">
      <!-- Daily quota meter -->
      <router-link
        v-if="quotaSummary"
        to="/usage"
        class="group hidden items-center gap-3 rounded-2xl border-2 border-line bg-card px-3.5 py-1.5 transition-colors hover:border-violet-400 md:flex"
        :title="'Diperbarui ' + lastUpdatedLabel"
      >
        <Icon name="coins" class="h-4 w-4 text-violet-600 transition-transform duration-500 group-hover:rotate-12 dark:text-violet-300" />
        <span class="text-xs leading-tight">
          <span class="block font-semibold text-muted">Kuota harian</span>
          <span class="block font-display text-sm font-bold text-ink tabular-nums">{{ formatNumber(displayRemaining) }}</span>
        </span>
        <span class="h-1.5 w-16 overflow-hidden rounded-full bg-surface-2">
          <span
            class="block h-full rounded-full transition-all duration-700"
            :class="quotaSummary.daily_percent > 85 ? 'bg-rose-500' : 'bg-brand-gradient'"
            :style="{ width: `${Math.max(2, 100 - quotaSummary.daily_percent)}%` }"
          ></span>
        </span>
      </router-link>

      <!-- Theme toggle -->
      <button
        type="button"
        class="grid h-10 w-10 place-items-center rounded-xl border-2 border-line bg-card text-ink transition-all hover:-translate-y-0.5 hover:border-violet-400"
        :title="theme === 'dark' ? 'Ganti ke tema terang' : 'Ganti ke tema gelap'"
        @click="toggleTheme"
      >
        <transition name="page" mode="out-in">
          <Icon v-if="theme === 'dark'" key="sun" name="sun" class="h-5 w-5 text-amber-400" />
          <Icon v-else key="moon" name="moon" class="h-5 w-5 text-violet-600" />
        </transition>
      </button>

      <router-link v-if="isAdmin" to="/admin" class="btn btn-sm btn-brutal hidden sm:inline-flex">
        <Icon name="shield-check" class="h-4 w-4" />
        Admin
      </router-link>

      <!-- Account menu -->
      <div ref="dropdownRef" class="relative">
        <button
          type="button"
          class="flex items-center gap-2 rounded-2xl border-2 border-transparent p-1 pr-2 transition-colors hover:border-line hover:bg-card"
          :aria-expanded="isDropdownOpen"
          @click="isDropdownOpen = !isDropdownOpen"
        >
          <span class="grid h-9 w-9 place-items-center rounded-xl border-2 border-ink bg-brand-gradient font-display text-sm font-bold text-white">
            {{ userInitials }}
          </span>
          <span class="hidden max-w-[120px] truncate text-sm font-semibold text-ink lg:inline">{{ user?.name }}</span>
          <Icon name="chevron-down" class="h-4 w-4 text-muted transition-transform duration-200" :class="isDropdownOpen ? 'rotate-180' : ''" />
        </button>

        <transition name="page">
          <div
            v-if="isDropdownOpen"
            class="absolute right-0 z-50 mt-2 w-64 overflow-hidden rounded-3xl border-2 border-ink bg-card text-sm shadow-brutal"
          >
            <div class="border-b border-line px-4 py-3">
              <p class="truncate font-semibold text-ink">{{ user?.name }}</p>
              <p class="truncate text-xs text-muted">{{ user?.email }}</p>
              <span class="chip mt-2">{{ user?.role === 'admin' ? 'Administrator' : 'Pengguna' }}</span>
            </div>
            <div class="p-2">
              <router-link
                v-for="item in menuItems"
                :key="item.to"
                :to="item.to"
                class="group flex items-center gap-3 rounded-2xl px-3 py-2 text-ink/80 transition-colors hover:bg-surface-2 hover:text-ink"
                @click="isDropdownOpen = false"
              >
                <Icon :name="item.icon" class="h-4 w-4 text-muted transition-transform group-hover:scale-110" />
                {{ item.label }}
              </router-link>
              <button
                type="button"
                class="group flex w-full items-center gap-3 rounded-2xl px-3 py-2 text-left text-rose-600 transition-colors hover:bg-rose-500/10 dark:text-rose-400"
                @click="handleLogout"
              >
                <Icon name="log-out" class="h-4 w-4 transition-transform group-hover:translate-x-0.5" />
                Keluar
              </button>
            </div>
          </div>
        </transition>
      </div>
    </div>
  </header>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import { useAuth } from '@/stores/auth';
import { api } from '@/api/client';
import { USAGE_CHANGED_EVENT } from '@/composables/useUsage';
import { useTheme } from '@/composables/useTheme';

defineEmits(['toggleSidebar']);

const { user, isAdmin, logout } = useAuth();
const isDropdownOpen = ref(false);
const dropdownRef = ref<HTMLElement | null>(null);
const { theme, toggleTheme } = useTheme();

const menuItems = computed(() => [
    { to: '/settings', icon: 'settings', label: 'Pengaturan akun & GitHub' },
    { to: '/usage', icon: 'bar-chart', label: 'Penggunaan token' },
    ...(isAdmin.value ? [{ to: '/admin', icon: 'shield-check', label: 'Dasbor administrator' }] : []),
]);

const firstName = computed(() => (user.value?.name || '').split(' ')[0]);
const greeting = computed(() => {
    const h = new Date().getHours();
    if (h < 11) return 'Selamat pagi';
    if (h < 15) return 'Selamat siang';
    if (h < 19) return 'Selamat sore';
    return 'Selamat malam';
});
const todayLabel = new Date().toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });

// Close the account menu on outside click or Escape
const onDocClick = (e: MouseEvent) => {
    if (isDropdownOpen.value && dropdownRef.value && !dropdownRef.value.contains(e.target as Node)) {
        isDropdownOpen.value = false;
    }
};
const onKey = (e: KeyboardEvent) => {
    if (e.key === 'Escape') isDropdownOpen.value = false;
};
const quotaSummary = ref<any>(null);

const userInitials = computed(() => {
    if (!user.value?.name) return 'X';
    return user.value.name
        .split(' ')
        .map(n => n[0])
        .slice(0, 2)
        .join('')
        .toUpperCase();
});

const formatNumber = (num: number) => {
    return new Intl.NumberFormat('id-ID').format(Math.round(num || 0));
};

const handleLogout = async () => {
    isDropdownOpen.value = false;
    await logout();
};

// Remaining quota counts smoothly to its new value after each refresh
const displayRemaining = ref(0);
const lastUpdated = ref<Date | null>(null);
const lastUpdatedLabel = computed(() =>
    lastUpdated.value ? lastUpdated.value.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' }) : '-'
);
let tweenFrame = 0;

const tweenRemaining = (target: number) => {
    cancelAnimationFrame(tweenFrame);
    const from = displayRemaining.value || target;
    const start = performance.now();
    const step = (now: number) => {
        const t = Math.min(1, (now - start) / 700);
        const eased = 1 - Math.pow(1 - t, 3);
        displayRemaining.value = from + (target - from) * eased;
        if (t < 1) tweenFrame = requestAnimationFrame(step);
    };
    tweenFrame = requestAnimationFrame(step);
};

const refreshQuota = async () => {
    try {
        const res = await api.get('/api/usage/summary');
        quotaSummary.value = res;
        lastUpdated.value = new Date();
        tweenRemaining(Number(res.remaining_daily) || 0);
    } catch (e) {
        // Ignore quota meter failure
    }
};

let pollTimer: ReturnType<typeof setInterval> | null = null;
const onVisible = () => {
    if (document.visibilityState === 'visible') refreshQuota();
};

onMounted(() => {
    document.addEventListener('click', onDocClick);
    document.addEventListener('keydown', onKey);
    refreshQuota();
    window.addEventListener(USAGE_CHANGED_EVENT, refreshQuota);
    document.addEventListener('visibilitychange', onVisible);
    pollTimer = setInterval(() => {
        if (document.visibilityState === 'visible') refreshQuota();
    }, 60000);
});

onBeforeUnmount(() => {
    document.removeEventListener('click', onDocClick);
    document.removeEventListener('keydown', onKey);
    window.removeEventListener(USAGE_CHANGED_EVENT, refreshQuota);
    document.removeEventListener('visibilitychange', onVisible);
    if (pollTimer) clearInterval(pollTimer);
    cancelAnimationFrame(tweenFrame);
});
</script>
