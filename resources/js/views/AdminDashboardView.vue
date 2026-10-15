<template>
  <div class="space-y-6 animate-in fade-in duration-200">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="page-title">Dasbor Administrasi XZY</h1>
        <p class="page-subtitle">Pantau pemakaian token global, kesehatan integrasi, dan aktivitas pengguna</p>
      </div>

      <div class="flex items-center gap-2">
        <a
          href="/api/admin/export-csv"
          target="_blank"
          class="btn btn-secondary btn-sm hover:bg-surface-2 flex items-center gap-1.5"
        >
          <Icon name="download" class="h-4 w-4" />
          <span>Unduh CSV Ledger</span>
        </a>
        <button
          @click="fetchDashboard"
          class="btn btn-primary shine btn-sm"
        >
          <Icon name="refresh" class="h-4 w-4" /> Refresh Data
        </button>
      </div>
    </div>

    <!-- Stats Counter Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
      <div class="p-5 rounded-3xl bg-card border-2 border-line space-y-1 transition-all duration-300 hover:-translate-y-1 hover:border-ink hover:shadow-brutal">
        <span class="text-xs text-muted font-medium">Pemakaian Hari Ini</span>
        <div class="text-2xl font-black text-violet-600 dark:text-violet-400">
          {{ formatNumber(stats?.usage?.today_weighted ?? 0) }}
        </div>
        <p class="text-[11px] text-muted">Weighted tokens (hari ini)</p>
      </div>

      <div class="p-5 rounded-3xl bg-card border-2 border-line space-y-1 transition-all duration-300 hover:-translate-y-1 hover:border-ink hover:shadow-brutal">
        <span class="text-xs text-muted font-medium">Pemakaian Bulan Ini</span>
        <div class="text-2xl font-black text-violet-600 dark:text-violet-400">
          {{ formatNumber(stats?.usage?.month_weighted ?? 0) }}
        </div>
        <p class="text-[11px] text-muted">Weighted tokens (bulan berjalan)</p>
      </div>

      <div class="p-5 rounded-3xl bg-card border-2 border-line space-y-1 transition-all duration-300 hover:-translate-y-1 hover:border-ink hover:shadow-brutal">
        <span class="text-xs text-muted font-medium">Total Akumulasi Token</span>
        <div class="text-2xl font-black text-ink">
          {{ formatNumber(stats?.usage?.total_weighted ?? 0) }}
        </div>
        <p class="text-[11px] text-muted">Sejak sistem aktif</p>
      </div>

      <div class="p-5 rounded-3xl bg-card border-2 border-line space-y-1 transition-all duration-300 hover:-translate-y-1 hover:border-ink hover:shadow-brutal">
        <span class="text-xs text-muted font-medium">Total Pengguna</span>
        <div class="text-2xl font-black text-emerald-600 dark:text-emerald-400">
          {{ stats?.stats?.total_users ?? 0 }}
        </div>
        <p class="text-[11px] text-muted">
          {{ stats?.stats?.active_users ?? 0 }} akun aktif • {{ stats?.stats?.total_projects ?? 0 }} proyek
        </p>
      </div>
    </div>

    <!-- Data Quality Breakdown -->
    <div class="p-6 rounded-3xl bg-card border-2 border-line space-y-4">
      <h3 class="font-bold text-sm text-ink">Transparansi Kualitas Data Token (Actual vs Estimated)</h3>
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div
          v-for="item in stats?.usage?.quality_breakdown || []"
          :key="item.data_source"
          class="p-4 rounded-xl bg-surface border border-line space-y-1 text-xs"
        >
          <div class="flex items-center justify-between">
            <span class="font-mono uppercase text-[10px] font-bold text-muted">{{ item.data_source }}</span>
            <span class="text-muted">{{ item.count }} request</span>
          </div>
          <div class="text-lg font-bold text-ink">
            {{ formatNumber(item.total_weighted) }}
          </div>
          <p class="text-[10px] text-muted">
            {{ item.data_source === 'actual' ? 'Dilaporkan resmi oleh upstream gateway' : 'Estimasi karakter internal' }}
          </p>
        </div>
      </div>
    </div>

    <!-- Top Users Leaderboard -->
    <div class="rounded-3xl border-2 border-ink bg-card overflow-hidden shadow-brutal">
      <div class="p-4 border-b border-line flex items-center justify-between">
        <h3 class="font-bold text-sm text-ink">Pengguna Teratas Bulan Ini</h3>
        <router-link to="/admin/users" class="text-xs text-violet-600 dark:text-violet-400 hover:underline">Kelola Semua Pengguna <Icon name="arrow-right" class="h-3.5 w-3.5" /></router-link>
      </div>
      <div class="overflow-x-auto">
        <table class="w-full text-left text-xs text-ink/80">
          <thead class="bg-surface/60 border-b border-line text-[11px] text-muted uppercase font-mono">
            <tr>
              <th class="p-3">Nama</th>
              <th class="p-3">Email</th>
              <th class="p-3">Pemakaian Bulan Ini</th>
              <th class="p-3">Kuota Bulanan</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-line">
            <tr v-for="u in stats?.top_users || []" :key="u.id" class="hover:bg-surface-2/40">
              <td class="p-3 font-semibold text-ink">{{ u.name }}</td>
              <td class="p-3 text-muted">{{ u.email }}</td>
              <td class="p-3 font-mono text-cyan-600 dark:text-cyan-400 font-bold">{{ formatNumber(u.month_usage) }}</td>
              <td class="p-3 font-mono text-muted">{{ formatNumber(u.monthly_token_quota) }}</td>
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
