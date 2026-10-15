<template>
  <div class="space-y-8 animate-in fade-in duration-200">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-black text-white">Konfigurasi Gateway AI & Model</h1>
        <p class="text-xs text-slate-400">Kelola gateway TokenomixAI, API key terenkripsi, multiplier token, dan koneksi upstream</p>
      </div>

      <div class="flex items-center gap-2">
        <button
          @click="testConnection"
          :disabled="isTesting"
          class="px-4 py-2 rounded-xl bg-cyan-500/10 border border-cyan-500/30 hover:bg-cyan-500/20 text-cyan-300 font-semibold text-xs transition-colors flex items-center gap-1.5 disabled:opacity-50"
        >
          <span v-if="isTesting" class="w-3 h-3 border-2 border-cyan-400 border-t-transparent rounded-full animate-spin"></span>
          <span>⚡ Uji Koneksi</span>
        </button>
        <button
          @click="refreshModels"
          :disabled="isRefreshing"
          class="px-4 py-2 rounded-xl bg-violet-600 hover:bg-violet-500 text-white font-semibold text-xs transition-colors flex items-center gap-1.5 disabled:opacity-50"
        >
          <span v-if="isRefreshing" class="w-3 h-3 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
          <span>↻ Sinkronkan Katalog Model</span>
        </button>
      </div>
    </div>

    <!-- Live Test Status Box -->
    <div v-if="testResult" class="p-4 rounded-2xl border transition-all text-xs flex items-start gap-3"
      :class="testResult.success ? 'bg-emerald-950/30 border-emerald-500/40 text-emerald-300' : 'bg-rose-950/30 border-rose-500/40 text-rose-300'"
    >
      <span class="text-lg">{{ testResult.success ? '✓' : '✕' }}</span>
      <div class="space-y-1">
        <h4 class="font-bold text-white">{{ testResult.success ? 'Koneksi Berhasil' : 'Uji Koneksi Gagal' }}</h4>
        <p class="leading-relaxed">{{ testResult.message }}</p>
        <div v-if="testResult.latency_ms" class="text-[10px] font-mono text-slate-400 pt-0.5">
          Latensi Gateway: {{ testResult.latency_ms }} ms
        </div>
      </div>
    </div>

    <!-- Agent Gateway Config Form -->
    <div class="rounded-2xl border border-slate-800 bg-slate-900 p-6 space-y-5 shadow-xl">
      <div class="flex items-center justify-between border-b border-slate-800 pb-3">
        <div class="space-y-0.5">
          <h3 class="font-bold text-base text-white">Gateway Provider: TokenomixAI</h3>
          <p class="text-xs text-slate-400">Endpoint OpenAI-Compatible resmi TokenomixAI: https://api.tokenomixai.com/v1</p>
        </div>
        <span
          class="px-2.5 py-1 rounded-full text-xs font-semibold"
          :class="form.is_active ? 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30' : 'bg-slate-800 text-slate-400'"
        >
          {{ form.is_active ? 'Aktif' : 'Nonaktif' }}
        </span>
      </div>

      <form @submit.prevent="saveAgentConfig" class="space-y-5 text-xs">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="block font-semibold text-slate-300 mb-1">Nama Konfigurasi</label>
            <input
              v-model="form.name"
              type="text"
              required
              class="w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-800 text-white outline-none focus:border-cyan-400"
            />
          </div>

          <div>
            <label class="block font-semibold text-slate-300 mb-1">Base URL Upstream</label>
            <input
              v-model="form.base_url"
              type="url"
              required
              class="w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-800 text-white font-mono outline-none focus:border-cyan-400"
            />
          </div>
        </div>

        <div>
          <label class="block font-semibold text-slate-300 mb-1">
            API Key TokenomixAI (Enkripsi Server-Side)
          </label>
          <input
            v-model="form.api_key"
            type="password"
            :placeholder="hasSavedKey ? (form.masked_api_key || 'sk-tkx-•••••••••••••••• (Kosongkan jika tidak diubah)') : 'sk-tkx-xxxxxxxxxxxxxxxx'"
            class="w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-800 text-cyan-300 font-mono outline-none focus:border-cyan-400"
          />
          <p class="text-[10px] text-slate-500 mt-1">
            Kunci rahasia disimpan terenkripsi di server dan tidak pernah dikirim dalam bentuk plaintext ke browser.
          </p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
          <div>
            <label class="block font-semibold text-slate-300 mb-1">Model Default</label>
            <select
              v-model="form.default_model"
              class="w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-800 text-cyan-300 font-mono outline-none"
            >
              <option v-for="m in multipliers" :key="m.model_id" :value="m.model_id">
                {{ m.model_id }} ({{ m.multiplier }}x)
              </option>
            </select>
          </div>

          <div>
            <label class="block font-semibold text-slate-300 mb-1">Batas Output Token (Max Tokens)</label>
            <input
              v-model.number="form.max_output_tokens"
              type="number"
              min="256"
              max="128000"
              class="w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-800 text-white font-mono outline-none"
            />
          </div>

          <div>
            <label class="block font-semibold text-slate-300 mb-1">Temperature</label>
            <input
              v-model.number="form.temperature"
              type="number"
              step="0.05"
              min="0"
              max="2"
              class="w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-800 text-white font-mono outline-none"
            />
          </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
          <div>
            <label class="block font-semibold text-slate-300 mb-1">Timeout Request (detik)</label>
            <input
              v-model.number="form.timeout_seconds"
              type="number"
              min="10"
              max="300"
              class="w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-800 text-white font-mono outline-none"
            />
          </div>

          <div>
            <label class="block font-semibold text-slate-300 mb-1">Batas Konkurensi Agent</label>
            <input
              v-model.number="form.concurrency_limit"
              type="number"
              min="1"
              max="50"
              class="w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-800 text-white font-mono outline-none"
            />
          </div>

          <div>
            <label class="block font-semibold text-slate-300 mb-1">Maks Iterasi Vibecoding</label>
            <input
              v-model.number="form.max_iterations"
              type="number"
              min="1"
              max="20"
              class="w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-800 text-white font-mono outline-none"
            />
          </div>
        </div>

        <div class="flex items-center gap-3 pt-2">
          <label class="flex items-center gap-2 cursor-pointer text-white">
            <input type="checkbox" v-model="form.is_active" class="rounded bg-slate-950 border-slate-800 text-cyan-400" />
            <span class="font-semibold">Aktifkan Gateway TokenomixAI untuk Chat & Vibecoding</span>
          </label>
        </div>

        <div class="flex justify-end pt-3 border-t border-slate-800">
          <button
            type="submit"
            :disabled="isSaving"
            class="px-5 py-2.5 rounded-xl bg-cyan-500 hover:bg-cyan-400 text-slate-950 font-bold text-xs shadow-lg shadow-cyan-500/20 transition-all disabled:opacity-50"
          >
            {{ isSaving ? 'Menyimpan...' : 'Simpan Konfigurasi Agent' }}
          </button>
        </div>
      </form>
    </div>

    <!-- Model Multipliers Catalog Table -->
    <div class="rounded-2xl border border-slate-800 bg-slate-900 overflow-hidden shadow-xl space-y-0">
      <div class="p-4 border-b border-slate-800 flex items-center justify-between">
        <div>
          <h3 class="font-bold text-sm text-white">Katalog Multiplier Model (TokenomixAI)</h3>
          <p class="text-xs text-slate-400">Bobot multiplier untuk perhitungan: weighted_tokens = raw_tokens × multiplier</p>
        </div>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-left text-xs text-slate-300">
          <thead class="bg-slate-950/60 border-b border-slate-800 text-[11px] text-slate-400 uppercase font-mono">
            <tr>
              <th class="p-3.5">Model ID</th>
              <th class="p-3.5">Multiplier</th>
              <th class="p-3.5">Kemampuan</th>
              <th class="p-3.5">Sumber & Keterangan</th>
              <th class="p-3.5 text-right">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-800/80 font-mono text-[11px]">
            <tr v-for="m in multipliers" :key="m.id" class="hover:bg-slate-800/40">
              <td class="p-3.5 font-bold text-white font-mono">{{ m.model_id }}</td>
              <td class="p-3.5 text-cyan-300 font-bold text-sm">{{ m.multiplier }}x</td>
              <td class="p-3.5 space-x-1">
                <span v-if="m.supports_tools" class="px-1.5 py-0.5 rounded bg-violet-600/20 text-violet-300 text-[10px]">Tools</span>
                <span v-if="m.supports_vision" class="px-1.5 py-0.5 rounded bg-cyan-500/20 text-cyan-300 text-[10px]">Vision</span>
              </td>
              <td class="p-3.5 text-slate-400 font-sans text-xs">
                <div>{{ m.description || '-' }}</div>
                <div class="text-[10px] text-slate-500 font-mono">{{ m.source }}</div>
              </td>
              <td class="p-3.5 text-right font-sans">
                <button
                  @click="openMultiplierEdit(m)"
                  class="px-2.5 py-1 rounded-lg bg-slate-800 hover:bg-slate-700 text-cyan-300 font-semibold text-xs"
                >
                  Ubah
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Edit Multiplier Modal -->
    <div
      v-if="editingMultiplier"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-sm"
    >
      <div class="max-w-sm w-full rounded-2xl bg-slate-900 border border-slate-800 p-5 space-y-4">
        <h3 class="font-bold text-sm text-white">Edit Multiplier: {{ editingMultiplier.model_id }}</h3>

        <div class="space-y-3 text-xs">
          <div>
            <label class="block font-semibold text-slate-300 mb-1">Nilai Multiplier (x)</label>
            <input
              v-model.number="multiplierForm.multiplier"
              type="number"
              step="0.01"
              min="0.1"
              max="500"
              class="w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-800 text-cyan-300 font-mono outline-none"
            />
          </div>

          <label class="flex items-center gap-2 cursor-pointer text-slate-300">
            <input type="checkbox" v-model="multiplierForm.supports_tools" class="rounded bg-slate-950 border-slate-800" />
            <span>Mendukung Function / Tool Calling</span>
          </label>

          <label class="flex items-center gap-2 cursor-pointer text-slate-300">
            <input type="checkbox" v-model="multiplierForm.supports_vision" class="rounded bg-slate-950 border-slate-800" />
            <span>Mendukung Vision</span>
          </label>
        </div>

        <div class="flex justify-end gap-2 text-xs pt-2">
          <button @click="editingMultiplier = null" class="px-3 py-1.5 text-slate-400">Batal</button>
          <button @click="saveMultiplier" class="px-4 py-1.5 rounded-lg bg-cyan-500 text-slate-950 font-bold">Simpan</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue';
import { api } from '@/api/client';
import { useToast } from '@/composables/useToast';

const toast = useToast();

const form = reactive({
    name: 'TokenomixAI Primary',
    base_url: 'https://api.tokenomixai.com/v1',
    api_key: '',
    masked_api_key: '',
    default_model: 'glm-5.1',
    allowed_models: ['glm-5.1', 'deepseek-v4.1-flash', 'kimi-k2.8', 'auto'],
    max_output_tokens: 4096,
    temperature: 0.70,
    timeout_seconds: 60,
    concurrency_limit: 5,
    max_iterations: 5,
    is_active: false,
});

const hasSavedKey = ref(false);
const multipliers = ref<any[]>([]);
const isSaving = ref(false);
const isTesting = ref(false);
const isRefreshing = ref(false);
const testResult = ref<any>(null);

const editingMultiplier = ref<any>(null);
const multiplierForm = reactive({
    multiplier: 1.0,
    supports_tools: false,
    supports_vision: false,
});

const fetchAgentConfig = async () => {
    try {
        const res = await api.get('/api/admin/agent');
        const c = res.config;
        form.name = c.name;
        form.base_url = c.base_url;
        form.masked_api_key = c.masked_api_key;
        form.default_model = c.default_model;
        form.allowed_models = c.allowed_models || [];
        form.max_output_tokens = c.max_output_tokens;
        form.temperature = c.temperature;
        form.timeout_seconds = c.timeout_seconds;
        form.concurrency_limit = c.concurrency_limit;
        form.max_iterations = c.max_iterations;
        form.is_active = c.is_active;
        hasSavedKey.value = c.has_api_key;

        multipliers.value = res.multipliers || [];
    } catch (e: any) {
        toast.error('Gagal memuat konfigurasi Agent: ' + e.message);
    }
};

const saveAgentConfig = async () => {
    isSaving.value = true;
    try {
        const res = await api.put('/api/admin/agent', form);
        toast.success(res.message || 'Konfigurasi berhasil disimpan.');
        form.api_key = '';
        fetchAgentConfig();
    } catch (e: any) {
        toast.error('Gagal menyimpan konfigurasi: ' + e.message);
    } finally {
        isSaving.value = false;
    }
};

const testConnection = async () => {
    isTesting.value = true;
    testResult.value = null;
    try {
        const res = await api.post('/api/admin/agent/test');
        testResult.value = res;
        if (res.success) {
            toast.success('Koneksi ke TokenomixAI berhasil!');
        } else {
            toast.warning(res.message);
        }
    } catch (e: any) {
        testResult.value = { success: false, message: e.message };
        toast.error('Uji koneksi gagal: ' + e.message);
    } finally {
        isTesting.value = false;
    }
};

const refreshModels = async () => {
    isRefreshing.value = true;
    try {
        const res = await api.post('/api/admin/agent/refresh-models');
        toast.success(res.message);
        multipliers.value = res.models || [];
    } catch (e: any) {
        toast.error('Gagal sinkronisasi model: ' + e.message);
    } finally {
        isRefreshing.value = false;
    }
};

const openMultiplierEdit = (m: any) => {
    editingMultiplier.value = m;
    multiplierForm.multiplier = parseFloat(m.multiplier);
    multiplierForm.supports_tools = m.supports_tools;
    multiplierForm.supports_vision = m.supports_vision;
};

const saveMultiplier = async () => {
    if (!editingMultiplier.value) return;
    try {
        await api.put(`/api/admin/multipliers/${editingMultiplier.value.id}`, multiplierForm);
        toast.success('Multiplier diperbarui.');
        editingMultiplier.value = null;
        fetchAgentConfig();
    } catch (e: any) {
        toast.error('Gagal memperbarui multiplier: ' + e.message);
    }
};

onMounted(() => {
    fetchAgentConfig();
});
</script>
