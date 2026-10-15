<template>
  <aside
    class="fixed inset-y-0 left-0 z-40 flex flex-col border-r border-line bg-card/90 backdrop-blur-xl transition-[width,transform] duration-300"
    :class="[
      isOpen ? 'translate-x-0' : '-translate-x-full md:translate-x-0',
      isSidebarCollapsed ? 'w-64 md:w-20' : 'w-64',
    ]"
  >
    <!-- Brand -->
    <div class="flex h-16 items-center justify-between gap-2 border-b border-line px-4">
      <router-link to="/dashboard" class="group flex items-center gap-2.5 overflow-hidden" @click="$emit('closeSidebar')">
        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl border-2 border-ink bg-brand-gradient font-display text-lg font-bold text-white shadow-brutal-sm transition-transform duration-300 group-hover:-rotate-6 group-hover:scale-105">
          X
        </span>
        <span v-if="!isSidebarCollapsed" class="font-display text-xl font-bold tracking-tight text-ink">XZY</span>
      </router-link>
      <button
        type="button"
        class="btn-ghost hidden h-8 w-8 place-items-center rounded-lg md:grid"
        :title="isSidebarCollapsed ? 'Perluas sidebar' : 'Ciutkan sidebar'"
        @click="isSidebarCollapsed = !isSidebarCollapsed"
      >
        <Icon :name="isSidebarCollapsed ? 'chevrons-right' : 'chevrons-left'" class="h-4 w-4" />
      </button>
      <button type="button" class="btn-ghost grid h-8 w-8 place-items-center rounded-lg md:hidden" title="Tutup menu" @click="$emit('closeSidebar')">
        <Icon name="x" class="h-4 w-4" />
      </button>
    </div>

    <!-- Navigation -->
    <nav class="flex-1 space-y-1 overflow-y-auto p-3">
      <p v-if="!isSidebarCollapsed" class="px-3 pt-1 pb-2 text-[11px] font-bold tracking-[0.18em] text-muted uppercase">Menu</p>

      <router-link
        v-for="item in navItems"
        :key="item.path"
        :to="item.path"
        :title="isSidebarCollapsed ? item.label : undefined"
        class="group relative flex items-center gap-3 rounded-2xl px-3 py-2.5 text-sm font-semibold transition-all duration-200"
        :class="isActive(item.path)
          ? 'border-2 border-ink bg-yellow-300 text-zinc-900 shadow-brutal-sm'
          : 'border-2 border-transparent text-muted hover:bg-surface-2 hover:text-ink'"
        @click="$emit('closeSidebar')"
      >
        <Icon :name="item.icon" class="h-5 w-5 transition-transform duration-300 group-hover:scale-110" :class="isActive(item.path) ? '' : 'group-hover:-rotate-6'" />
        <span v-if="!isSidebarCollapsed" class="truncate">{{ item.label }}</span>
      </router-link>

      <template v-if="isAdmin">
        <p v-if="!isSidebarCollapsed" class="px-3 pt-6 pb-2 text-[11px] font-bold tracking-[0.18em] text-violet-600 uppercase dark:text-violet-300">Administrasi</p>
        <div v-else class="mx-3 my-3 border-t border-line"></div>

        <router-link
          v-for="item in adminItems"
          :key="item.path"
          :to="item.path"
          :title="isSidebarCollapsed ? item.label : undefined"
          class="group relative flex items-center gap-3 rounded-2xl px-3 py-2.5 text-sm font-semibold transition-all duration-200"
          :class="isActive(item.path, true)
            ? 'border-2 border-ink bg-violet-500 text-white shadow-brutal-sm'
            : 'border-2 border-transparent text-muted hover:bg-surface-2 hover:text-ink'"
          @click="$emit('closeSidebar')"
        >
          <Icon :name="item.icon" class="h-5 w-5 transition-transform duration-300 group-hover:scale-110" />
          <span v-if="!isSidebarCollapsed" class="truncate">{{ item.label }}</span>
        </router-link>
      </template>
    </nav>

    <!-- Account -->
    <div class="border-t border-line p-3">
      <router-link
        to="/settings"
        class="group flex items-center gap-3 rounded-2xl p-2 transition-colors hover:bg-surface-2"
        :title="isSidebarCollapsed ? 'Pengaturan akun' : undefined"
        @click="$emit('closeSidebar')"
      >
        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl border-2 border-ink bg-lime-300 font-display text-sm font-bold text-zinc-900">
          {{ initials }}
        </span>
        <span v-if="!isSidebarCollapsed" class="min-w-0 flex-1">
          <span class="block truncate text-sm font-semibold text-ink">{{ user?.name }}</span>
          <span class="block truncate text-xs text-muted">Pengaturan akun</span>
        </span>
        <Icon v-if="!isSidebarCollapsed" name="settings" class="h-4 w-4 text-muted transition-transform duration-500 group-hover:rotate-90" />
      </router-link>
    </div>
  </aside>

  <!-- Mobile backdrop -->
  <transition name="page">
    <div v-if="isOpen" class="fixed inset-0 z-30 bg-zinc-950/50 backdrop-blur-sm md:hidden" @click="$emit('closeSidebar')"></div>
  </transition>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { useRoute } from 'vue-router';
import { useAuth } from '@/stores/auth';
import { useLayout } from '@/composables/useLayout';

defineProps<{
  isOpen: boolean;
}>();

defineEmits(['closeSidebar']);

const route = useRoute();
const { user, isAdmin } = useAuth();
const { isSidebarCollapsed } = useLayout();

const initials = computed(() =>
    (user.value?.name || 'X')
        .split(' ')
        .map((n) => n[0])
        .slice(0, 2)
        .join('')
        .toUpperCase()
);

const isActive = (path: string, exact = false) => {
    if (exact || path === '/dashboard') return route.path === path;
    // A project's workspace belongs to the "Proyek" section
    if (path === '/projects' && route.path.startsWith('/workspace')) return true;
    return route.path === path || route.path.startsWith(path + '/');
};

const navItems = [
    { label: 'Dasbor', path: '/dashboard', icon: 'layout-dashboard' },
    { label: 'Proyek', path: '/projects', icon: 'folder' },
    { label: 'AI Chat', path: '/chat', icon: 'message-square' },
    { label: 'Penggunaan Token', path: '/usage', icon: 'bar-chart' },
    { label: 'Pengaturan & GitHub', path: '/settings', icon: 'settings' },
];

const adminItems = [
    { label: 'Dasbor Admin', path: '/admin', icon: 'shield-check' },
    { label: 'Pengguna & Kuota', path: '/admin/users', icon: 'users' },
    { label: 'Konfigurasi Agent', path: '/admin/agent', icon: 'bot' },
    { label: 'Audit Log', path: '/admin/audit', icon: 'list' },
];
</script>
