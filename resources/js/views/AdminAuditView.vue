<template>
  <div class="space-y-6 animate-in fade-in duration-200">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="page-title">Audit Log Sistem</h1>
        <p class="page-subtitle">Jejak audit seluruh perubahan peran, kuota, konfigurasi agent, dan akses integrasi</p>
      </div>

      <button @click="fetchLogs" class="btn btn-secondary btn-sm">
        <Icon name="refresh" class="h-4 w-4" /> Refresh Log
      </button>
    </div>

    <!-- Filter -->
    <div class="flex items-center gap-3 p-4 rounded-3xl bg-card border-2 border-line">
      <input
        v-model="actionFilter"
        @input="handleSearch"
        type="text"
        placeholder="Filter berdasarkan nama aksi (misal: user.update, agent.config)..."
        class="input sm:w-96 text-xs"
      />
    </div>

    <!-- Audit Table -->
    <div class="rounded-3xl border-2 border-ink bg-card overflow-hidden shadow-brutal">
      <div class="overflow-x-auto">
        <table class="w-full text-left text-xs text-ink/80">
          <thead class="bg-surface/60 border-b border-line text-[11px] text-muted uppercase font-mono">
            <tr>
              <th class="p-3.5">Waktu</th>
              <th class="p-3.5">Aktor</th>
              <th class="p-3.5">Aksi</th>
              <th class="p-3.5">Target</th>
              <th class="p-3.5">IP & Correlation ID</th>
              <th class="p-3.5">Metadata (Tersanitasi)</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-line font-mono text-[11px]">
            <tr v-if="logs.length === 0">
              <td colspan="6" class="p-8 text-center text-muted font-sans">
                Tidak ada rekaman jejak audit yang ditemukan.
              </td>
            </tr>
            <tr v-for="l in logs" :key="l.id" class="hover:bg-surface-2/40">
              <td class="p-3.5 text-muted whitespace-nowrap">{{ formatDate(l.created_at) }}</td>
              <td class="p-3.5 text-ink font-sans">
                <p class="font-semibold">{{ l.user?.name || 'Sistem' }}</p>
                <p class="text-[10px] text-muted font-mono">{{ l.user?.email || '-' }}</p>
              </td>
              <td class="p-3.5">
                <span class="px-2 py-0.5 rounded bg-violet-600/20 border border-violet-500/30 text-violet-600 dark:text-violet-300 font-semibold">
                  {{ l.action }}
                </span>
              </td>
              <td class="p-3.5 text-muted">
                {{ l.target_type }} {{ l.target_id ? '#' + l.target_id : '' }}
              </td>
              <td class="p-3.5 text-muted">
                <p>{{ l.ip_address }}</p>
                <p class="text-[9px] text-muted/70 truncate max-w-[120px]">{{ l.correlation_id }}</p>
              </td>
              <td class="p-3.5 text-ink/80 font-mono text-[10px] max-w-xs truncate">
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
