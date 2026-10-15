<template>
  <div class="space-y-6 animate-in fade-in duration-200">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-black text-white">Dasbor Administrasi XZY</h1>
        <p class="text-xs text-slate-400">Pantau pemakaian token global, kesehatan integrasi, dan aktivitas pengguna</p>
      </div>

      <div class="flex items-center gap-2">
        <a
          href="/api/admin/export-csv"
          target="_blank"
          class="px-3.5 py-2 rounded-xl bg-slate-900 border border-slate-800 hover:bg-slate-800 text-xs font-semibold text-slate-200 flex items-center gap-1.5"
        >
          <span>📥</span>
          <span>Unduh CSV Ledger</span>
        </a>
        <button
          @click="fetchDashboard"
          class="px-3.5 py-2 rounded-xl bg-violet-600 hover:bg-violet-500 text-white font-semibold text-xs transition-colors"
        >
          ↻ Refresh Data
        </button>
      </div>
    </div>

    <!-- Stats Counter Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
      <div class="p-5 rounded-2xl bg-slate-900 border border-slate-800 space-y-1">
        <span class="text-xs text-slate-400 font-medium">Pemakaian Hari Ini</span>
        <div class="text-2xl font-black text-cyan-400">
          {{ formatNumber(stats?.usage?.today_weighted ?? 0) }}
        </div>
        <p class="text-[11px] text-slate-500">Weighted tokens (hari ini)</p>
      </div>

      <div class="p-5 rounded-2xl bg-slate-900 border border-slate-800 space-y-1">
        <span class="text-xs text-slate-400 font-medium">Pemakaian Bulan Ini</span>
        <div class="text-2xl font-black text-violet-400">
          {{ formatNumber(stats?.usage?.month_weighted ?? 0) }}
        </div>
        <p class="text-[11px] text-slate-500">Weighted tokens (bulan berjalan)</p>
      </div>

      <div class="p-5 rounded-2xl bg-slate-900 border border-slate-800 space-y-1">
        <span class="text-xs text-slate-400 font-medium">Total Akumulasi Token</span>
        <div class="text-2xl font-black text-white">
          {{ formatNumber(stats?.usage?.total_weighted ?? 0) }}
        </div>
        <p class="text-[11px] text-slate-500">Sejak sistem aktif</p>
      </div>

      <div class="p-5 rounded-2xl bg-slate-900 border border-slate-800 space-y-1">
        <span class="text-xs text-slate-400 font-medium">Total Pengguna</span>
        <div class="text-2xl font-black text-emerald-400">
          {{ stats?.stats?.total_users ?? 0 }}
        </div>
        <p class="text-[11px] text-slate-500">
          {{ stats?.stats?.active_users ?? 0 }} akun aktif • {{ stats?.stats?.total_projects ?? 0 }} proyek
        </p>
      </div>
    </div>

    <!-- Data Quality Breakdown -->
    <div class="p-6 rounded-2xl bg-slate-900 border border-slate-800 space-y-4">
      <h3 class="font-bold text-sm text-white">Transparansi Kualitas Data Token (Actual vs Estimated)</h3>
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div
          v-for="item in stats?.usage?.quality_breakdown || []"
          :key="item.data_source"
          class="p-4 rounded-xl bg-slate-950 border border-slate-800 space-y-1 text-xs"
        >
          <div class="flex items-center justify-between">
            <span class="font-mono uppercase text-[10px] font-bold text-slate-400">{{ item.data_source }}</span>
            <span class="text-slate-500">{{ item.count }} request</span>
          </div>
          <div class="text-lg font-bold text-slate-200">
            {{ formatNumber(item.total_weighted) }}
          </div>
          <p class="text-[10px] text-slate-500">
            {{ item.data_source === 'actual' ? 'Dilaporkan resmi oleh upstream gateway' : 'Estimasi karakter internal' }}
          </p>
        </div>
      </div>
    </div>

    <!-- Top Users Leaderboard -->
    <div class="rounded-2xl border border-slate-800 bg-slate-900 overflow-hidden">
      <div class="p-4 border-b border-slate-800 flex items-center justify-between">
        <h3 class="font-bold text-sm text-white">Pengguna Teratas Bulan Ini</h3>
        <router-link to="/admin/users" class="text-xs text-cyan-400 hover:underline">Kelola Semua Pengguna →</router-link>
      </div>
      <div class="overflow-x-auto">
        <table class="w-full text-left text-xs text-slate-300">
          <thead class="bg-slate-950/60 border-b border-slate-800 text-[11px] text-slate-400 uppercase font-mono">
            <tr>
              <th class="p-3">Nama</th>
              <th class="p-3">Email</th>
              <th class="p-3">Pemakaian Bulan Ini</th>
              <th class="p-3">Kuota Bulanan</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-800/80">
            <tr v-for="u in stats?.top_users || []" :key="u.id" class="hover:bg-slate-800/40">
              <td class="p-3 font-semibold text-white">{{ u.name }}</td>
              <td class="p-3 text-slate-400">{{ u.email }}</td>
              <td class="p-3 font-mono text-cyan-400 font-bold">{{ formatNumber(u.month_usage) }}</td>
              <td class="p-3 font-mono text-slate-400">{{ formatNumber(u.monthly_token_quota) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { api } from '@/api/client';
import { useToast } from '@/composables/useToast';

const toast = useToast();
const stats = ref<any>(null);

const formatNumber = (num: number) => {
    return new Intl.NumberFormat('id-ID').format(Math.round(num || 0));
};

const fetchDashboard = async () => {
    try {
        stats.value = await api.get('/api/admin/dashboard');
    } catch (e: any) {
        toast.error('Gagal memuat dasbor admin: ' + e.message);
    }
};

onMounted(() => {
    fetchDashboard();
});
</script>
