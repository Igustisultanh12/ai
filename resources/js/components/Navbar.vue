<template>
  <header class="h-16 border-b border-slate-800 bg-slate-950/80 backdrop-blur-md sticky top-0 z-30 flex items-center justify-between px-4 sm:px-6">
    <div class="flex items-center gap-4">
      <button
        @click="$emit('toggleSidebar')"
        class="text-slate-400 hover:text-white p-1.5 rounded-lg hover:bg-slate-800/60 transition-colors"
        aria-label="Menu"
      >
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
        </svg>
      </button>

      <router-link to="/dashboard" class="flex items-center gap-2">
        <span class="text-2xl font-black tracking-wider bg-gradient-to-r from-violet-400 via-fuchsia-400 to-cyan-400 bg-clip-text text-transparent">
          XZY
        </span>
        <span class="text-xs px-2 py-0.5 rounded-full bg-violet-500/10 border border-violet-500/20 text-violet-300 font-medium hidden sm:inline-block">
          Vibecoding
        </span>
      </router-link>
    </div>

    <div class="flex items-center gap-3">
      <!-- Quota Meter Widget -->
      <div v-if="quotaSummary" class="hidden md:flex items-center gap-2 px-3 py-1.5 rounded-lg bg-slate-900 border border-slate-800 text-xs">
        <span class="text-slate-400">Kuota Harian:</span>
        <span class="font-semibold text-cyan-400">{{ formatNumber(quotaSummary.remaining_daily) }}</span>
        <span class="text-slate-500">/ {{ formatNumber(quotaSummary.daily_quota) }}</span>
        <div class="w-16 h-1.5 bg-slate-800 rounded-full overflow-hidden ml-1">
          <div
            class="h-full rounded-full transition-all duration-500"
            :class="quotaSummary.daily_percent > 85 ? 'bg-rose-500' : 'bg-cyan-400'"
            :style="{ width: `${quotaSummary.daily_percent}%` }"
          ></div>
        </div>
      </div>

      <!-- Admin Panel Shortcut -->
      <router-link
        v-if="isAdmin"
        to="/admin"
        class="hidden sm:flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-lg bg-violet-600/20 border border-violet-500/30 text-violet-300 hover:bg-violet-600/30 transition-colors"
      >
        <span>🛡️</span>
        <span>Admin Panel</span>
      </router-link>

      <!-- User Dropdown Menu -->
      <div class="relative" ref="dropdownRef">
        <button
          @click="isDropdownOpen = !isDropdownOpen"
          class="flex items-center gap-2 p-1.5 rounded-lg hover:bg-slate-900 border border-transparent hover:border-slate-800 transition-colors"
        >
          <div class="w-8 h-8 rounded-full bg-gradient-to-tr from-violet-600 to-cyan-500 flex items-center justify-center text-xs font-bold text-white shadow-sm">
            {{ userInitials }}
          </div>
          <span class="text-sm font-medium text-slate-200 hidden lg:inline-block max-w-[120px] truncate">
            {{ user?.name }}
          </span>
          <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
          </svg>
        </button>

        <div
          v-if="isDropdownOpen"
          class="absolute right-0 mt-2 w-56 rounded-xl bg-slate-900 border border-slate-800 shadow-2xl py-2 z-50 text-sm animate-in fade-in slide-in-from-top-2 duration-150"
        >
          <div class="px-4 py-2 border-b border-slate-800/80">
            <p class="font-semibold text-white truncate">{{ user?.name }}</p>
            <p class="text-xs text-slate-400 truncate">{{ user?.email }}</p>
            <span class="inline-block mt-1 px-1.5 py-0.5 text-[10px] font-semibold rounded bg-slate-800 text-slate-300">
              Role: {{ user?.role === 'admin' ? 'Administrator' : 'Pengguna' }}
            </span>
          </div>

          <router-link
            to="/settings"
            @click="isDropdownOpen = false"
            class="flex items-center gap-2 px-4 py-2 text-slate-300 hover:bg-slate-800 hover:text-white transition-colors"
          >
            <span>⚙️</span>
            <span>Pengaturan Akun & GitHub</span>
          </router-link>

          <router-link
            to="/usage"
            @click="isDropdownOpen = false"
            class="flex items-center gap-2 px-4 py-2 text-slate-300 hover:bg-slate-800 hover:text-white transition-colors"
          >
            <span>📊</span>
            <span>Penggunaan Token</span>
          </router-link>

          <div v-if="isAdmin" class="border-t border-slate-800/80 my-1">
            <router-link
              to="/admin"
              @click="isDropdownOpen = false"
              class="flex items-center gap-2 px-4 py-2 text-violet-300 hover:bg-violet-950/40 transition-colors"
            >
              <span>🛡️</span>
              <span>Dasbor Administrator</span>
            </router-link>
          </div>

          <div class="border-t border-slate-800/80 my-1">
            <button
              @click="handleLogout"
              class="w-full flex items-center gap-2 px-4 py-2 text-rose-400 hover:bg-rose-950/30 transition-colors text-left"
            >
              <span>🚪</span>
              <span>Keluar (Logout)</span>
            </button>
          </div>
        </div>
      </div>
    </div>
  </header>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { useAuth } from '@/stores/auth';
import { api } from '@/api/client';

defineEmits(['toggleSidebar']);

const { user, isAdmin, logout } = useAuth();
const isDropdownOpen = ref(false);
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

onMounted(async () => {
    try {
        const res = await api.get('/api/usage/summary');
        quotaSummary.value = res;
    } catch (e) {
        // Ignore quota meter failure
    }
});
</script>
