<template>
  <div class="space-y-6 animate-in fade-in duration-200">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-black text-white">Audit Log Sistem</h1>
        <p class="text-xs text-slate-400">Jejak audit seluruh perubahan peran, kuota, konfigurasi agent, dan akses integrasi</p>
      </div>

      <button @click="fetchLogs" class="px-3.5 py-2 rounded-xl bg-slate-900 border border-slate-800 text-xs text-slate-300 hover:text-white">
        ↻ Refresh Log
      </button>
    </div>

    <!-- Filter -->
    <div class="flex items-center gap-3 p-4 rounded-2xl bg-slate-900 border border-slate-800">
      <input
        v-model="actionFilter"
        @input="handleSearch"
        type="text"
        placeholder="Filter berdasarkan nama aksi (misal: user.update, agent.config)..."
        class="w-full sm:w-96 px-3.5 py-2 rounded-xl bg-slate-950 border border-slate-800 text-xs text-white outline-none focus:border-cyan-400"
      />
    </div>

    <!-- Audit Table -->
    <div class="rounded-2xl border border-slate-800 bg-slate-900 overflow-hidden shadow-xl">
      <div class="overflow-x-auto">
        <table class="w-full text-left text-xs text-slate-300">
          <thead class="bg-slate-950/60 border-b border-slate-800 text-[11px] text-slate-400 uppercase font-mono">
            <tr>
              <th class="p-3.5">Waktu</th>
              <th class="p-3.5">Aktor</th>
              <th class="p-3.5">Aksi</th>
              <th class="p-3.5">Target</th>
              <th class="p-3.5">IP & Correlation ID</th>
              <th class="p-3.5">Metadata (Tersanitasi)</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-800/80 font-mono text-[11px]">
            <tr v-if="logs.length === 0">
              <td colspan="6" class="p-8 text-center text-slate-500 font-sans">
                Tidak ada rekaman jejak audit yang ditemukan.
              </td>
            </tr>
            <tr v-for="l in logs" :key="l.id" class="hover:bg-slate-800/40">
              <td class="p-3.5 text-slate-400 whitespace-nowrap">{{ formatDate(l.created_at) }}</td>
              <td class="p-3.5 text-white font-sans">
                <p class="font-semibold">{{ l.user?.name || 'Sistem' }}</p>
                <p class="text-[10px] text-slate-500 font-mono">{{ l.user?.email || '-' }}</p>
              </td>
              <td class="p-3.5">
                <span class="px-2 py-0.5 rounded bg-violet-600/20 border border-violet-500/30 text-violet-300 font-semibold">
                  {{ l.action }}
                </span>
              </td>
              <td class="p-3.5 text-slate-400">
                {{ l.target_type }} {{ l.target_id ? '#' + l.target_id : '' }}
              </td>
              <td class="p-3.5 text-slate-400">
                <p>{{ l.ip_address }}</p>
                <p class="text-[9px] text-slate-600 truncate max-w-[120px]">{{ l.correlation_id }}</p>
              </td>
              <td class="p-3.5 text-slate-300 font-mono text-[10px] max-w-xs truncate">
                {{ JSON.stringify(l.metadata || {}) }}
              </td>
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
const logs = ref<any[]>([]);
const actionFilter = ref('');

let debounce: any = null;

const formatDate = (dateStr: string) => {
    if (!dateStr) return '';
    return new Date(dateStr).toLocaleString('id-ID', {
        dateStyle: 'short',
        timeStyle: 'medium',
    });
};

const fetchLogs = async () => {
    try {
        const queryParams = new URLSearchParams({
            action: actionFilter.value,
        });
        const res = await api.get(`/api/admin/audit-logs?${queryParams.toString()}`);
        logs.value = res.data || [];
    } catch (e: any) {
        toast.error('Gagal memuat audit log: ' + e.message);
    }
};

const handleSearch = () => {
    clearTimeout(debounce);
    debounce = setTimeout(() => {
        fetchLogs();
    }, 300);
};

onMounted(() => {
    fetchLogs();
});
</script>
