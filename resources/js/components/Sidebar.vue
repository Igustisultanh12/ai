<template>
  <aside
    class="fixed inset-y-0 left-0 z-40 bg-slate-950 border-r border-slate-800 flex flex-col transition-all duration-200"
    :class="[
      isOpen ? 'translate-x-0' : '-translate-x-full md:translate-x-0',
      isCollapsed ? 'md:w-16' : 'w-64',
    ]"
  >
    <!-- Logo area -->
    <div class="h-16 flex items-center justify-between px-4 border-b border-slate-800">
      <router-link to="/dashboard" class="flex items-center gap-2 overflow-hidden">
        <div class="w-8 h-8 rounded-lg bg-gradient-to-tr from-violet-600 to-cyan-400 flex items-center justify-center font-black text-white shrink-0">
          X
        </div>
        <span v-if="!isCollapsed" class="font-extrabold text-lg text-white tracking-wider">XZY</span>
      </router-link>
      <button
        @click="isCollapsed = !isCollapsed"
        class="hidden md:flex text-slate-400 hover:text-white p-1 rounded-md hover:bg-slate-800/60 transition-colors"
        :title="isCollapsed ? 'Perluas sidebar' : 'Ciutkan sidebar'"
      >
        <span class="text-xs">{{ isCollapsed ? '»' : '«' }}</span>
      </button>
    </div>

    <!-- Navigation links -->
    <nav class="flex-1 overflow-y-auto p-3 space-y-1">
      <div v-if="!isCollapsed" class="px-3 py-1.5 text-[10px] font-bold text-slate-500 uppercase tracking-wider">
        Menu Utama
      </div>

      <router-link
        v-for="item in navItems"
        :key="item.path"
        :to="item.path"
        class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-colors"
        :class="[
          $route.path === item.path || (item.path !== '/dashboard' && $route.path.startsWith(item.path))
            ? 'bg-violet-600/15 border border-violet-500/30 text-violet-300'
            : 'text-slate-400 hover:text-slate-200 hover:bg-slate-900',
        ]"
      >
        <span class="text-base shrink-0">{{ item.icon }}</span>
        <span v-if="!isCollapsed" class="truncate">{{ item.label }}</span>
      </router-link>

      <div v-if="isAdmin">
        <div v-if="!isCollapsed" class="px-3 pt-5 pb-1.5 text-[10px] font-bold text-violet-400 uppercase tracking-wider">
          Administrasi
        </div>
        <div v-else class="my-2 border-t border-slate-800"></div>

        <router-link
          v-for="item in adminItems"
          :key="item.path"
          :to="item.path"
          class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-colors"
          :class="[
            $route.path === item.path
              ? 'bg-violet-600/20 border border-violet-500/30 text-violet-300'
              : 'text-slate-400 hover:text-slate-200 hover:bg-slate-900',
          ]"
        >
          <span class="text-base shrink-0">{{ item.icon }}</span>
          <span v-if="!isCollapsed" class="truncate">{{ item.label }}</span>
        </router-link>
      </div>
    </nav>

    <!-- Bottom user status -->
    <div class="p-3 border-t border-slate-800/80">
      <router-link
        to="/settings"
        class="flex items-center gap-3 p-2 rounded-xl text-xs text-slate-400 hover:bg-slate-900 transition-colors"
      >
        <span class="text-base shrink-0">⚙️</span>
        <div v-if="!isCollapsed" class="overflow-hidden">
          <p class="font-medium text-slate-300 truncate">{{ user?.name }}</p>
          <p class="text-[10px] text-slate-500 truncate">Pengaturan Akun</p>
        </div>
      </router-link>
    </div>
  </aside>

  <!-- Mobile backdrop -->
  <div
    v-if="isOpen"
    @click="$emit('closeSidebar')"
    class="fixed inset-0 z-30 bg-black/60 backdrop-blur-sm md:hidden"
  ></div>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import { useAuth } from '@/stores/auth';

defineProps<{
  isOpen: boolean;
}>();

defineEmits(['closeSidebar']);

const { user, isAdmin } = useAuth();
const isCollapsed = ref(false);

const navItems = [
    { label: 'Dasbor', path: '/dashboard', icon: '⚡' },
    { label: 'Daftar Proyek', path: '/projects', icon: '📁' },
    { label: 'AI Chat', path: '/chat', icon: '💬' },
    { label: 'Penggunaan Token', path: '/usage', icon: '📊' },
    { label: 'Pengaturan & GitHub', path: '/settings', icon: '⚙️' },
];

const adminItems = [
    { label: 'Dasbor Admin', path: '/admin', icon: '🛡️' },
    { label: 'Pengguna & Kuota', path: '/admin/users', icon: '👥' },
    { label: 'Konfigurasi Agent', path: '/admin/agent', icon: '🤖' },
    { label: 'Audit Log', path: '/admin/audit', icon: '📜' },
];
</script>
