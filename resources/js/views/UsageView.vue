<template>
  <div class="space-y-6 animate-in fade-in duration-200">
    <div>
      <h1 class="text-2xl font-black text-white">Penggunaan Token & Kuota</h1>
      <p class="text-xs text-slate-400">Transparansi pemotongan token sesuai rumus: raw_tokens × multiplier_model</p>
    </div>

    <!-- Quota Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
      <div class="p-5 rounded-2xl bg-slate-900 border border-slate-800 space-y-2">
        <span class="text-xs text-slate-400 font-medium">Sisa Kuota Harian</span>
        <div class="text-2xl font-black text-cyan-400">
          {{ formatNumber(summary?.remaining_daily ?? 0) }}
        </div>
        <p class="text-[11px] text-slate-500">
          Batas per hari: {{ formatNumber(summary?.daily_quota ?? 0) }} token
        </p>
      </div>

      <div class="p-5 rounded-2xl bg-slate-900 border border-slate-800 space-y-2">
        <span class="text-xs text-slate-400 font-medium">Sisa Kuota Bulanan</span>
        <div class="text-2xl font-black text-violet-400">
          {{ formatNumber(summary?.remaining_monthly ?? 0) }}
        </div>
        <p class="text-[11px] text-slate-500">
          Batas per bulan: {{ formatNumber(summary?.monthly_quota ?? 0) }} token
        </p>
      </div>

      <div class="p-5 rounded-2xl bg-slate-900 border border-slate-800 space-y-2">
        <span class="text-xs text-slate-400 font-medium">Total Pemakaian</span>
        <div class="text-2xl font-black text-slate-100">
          {{ formatNumber(summary?.total_weighted ?? 0) }}
        </div>
        <p class="text-[11px] text-slate-500">
          Weighted token terakumulasi
        </p>
      </div>
    </div>

    <!-- Ledger Table -->
    <div class="rounded-2xl border border-slate-800 bg-slate-900 overflow-hidden shadow-xl">
      <div class="p-4 border-b border-slate-800 flex items-center justify-between">
        <h3 class="font-bold text-sm text-white">Buku Besar Transaksi Token (Ledger)</h3>
        <button @click="fetchLedger" class="text-xs text-cyan-400 hover:underline">↻ Refresh</button>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-left text-xs text-slate-300">
          <thead class="bg-slate-950/60 border-b border-slate-800 text-[11px] text-slate-400 uppercase font-mono">
            <tr>
              <th class="p-3">Waktu</th>
              <th class="p-3">Model</th>
              <th class="p-3">Prompt</th>
              <th class="p-3">Completion</th>
              <th class="p-3">Raw</th>
              <th class="p-3">Multiplier</th>
              <th class="p-3">Weighted</th>
              <th class="p-3">Kualitas</th>
              <th class="p-3">Status</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-800/80 font-mono text-[11px]">
            <tr v-if="ledger.length === 0">
              <td colspan="9" class="p-8 text-center text-slate-500">
                Belum ada catatan transaksi token.
              </td>
            </tr>
            <tr v-for="item in ledger" :key="item.id" class="hover:bg-slate-800/40 transition-colors">
              <td class="p-3 text-slate-400 whitespace-nowrap">{{ formatDate(item.created_at) }}</td>
              <td class="p-3 text-white font-semibold">{{ item.model }}</td>
              <td class="p-3">{{ formatNumber(item.prompt_tokens) }}</td>
              <td class="p-3">{{ formatNumber(item.completion_tokens) }}</td>
              <td class="p-3 text-slate-200">{{ formatNumber(item.raw_tokens) }}</td>
              <td class="p-3 text-cyan-300">{{ item.multiplier_snapshot }}x</td>
              <td class="p-3 text-violet-300 font-bold">{{ formatNumber(item.weighted_tokens) }}</td>
              <td class="p-3">
                <span
                  class="px-1.5 py-0.5 rounded text-[10px]"
                  :class="item.data_source === 'actual' ? 'bg-emerald-500/20 text-emerald-400' : 'bg-amber-500/20 text-amber-400'"
                >
                  {{ item.data_source }}
                </span>
              </td>
              <td class="p-3">
                <span
                  class="px-1.5 py-0.5 rounded text-[10px]"
                  :class="item.status === 'success' ? 'bg-emerald-500/20 text-emerald-400' : 'bg-rose-500/20 text-rose-400'"
                >
                  {{ item.status }}
                </span>
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
const summary = ref<any>(null);
const ledger = ref<any[]>([]);

const formatNumber = (num: number) => {
    return new Intl.NumberFormat('id-ID').format(Math.round(num || 0));
};

const formatDate = (dateStr: string) => {
    if (!dateStr) return '';
    return new Date(dateStr).toLocaleString('id-ID', {
        dateStyle: 'short',
        timeStyle: 'medium',
    });
};

const fetchSummary = async () => {
    try {
        summary.value = await api.get('/api/usage/summary');
    } catch (e: any) {
        toast.error('Gagal memuat kuota: ' + e.message);
    }
};

const fetchLedger = async () => {
    try {
        const res = await api.get('/api/usage/ledger');
        ledger.value = res.data || [];
    } catch (e: any) {
        toast.error('Gagal memuat buku besar: ' + e.message);
    }
};

onMounted(() => {
    fetchSummary();
    fetchLedger();
});
</script>
