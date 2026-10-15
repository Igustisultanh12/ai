<template>
  <div class="space-y-8 animate-in fade-in duration-200">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="page-title">Konfigurasi Gateway AI & Model</h1>
        <p class="page-subtitle">Kelola gateway TokenomixAI, API key terenkripsi, multiplier token, dan koneksi upstream</p>
      </div>

      <div class="flex items-center gap-2">
        <button
          @click="testConnection"
          :disabled="isTesting"
          class="btn btn-secondary btn-sm flex items-center gap-1.5 disabled:opacity-50"
        >
          <span v-if="isTesting" class="w-3 h-3 border-2 border-cyan-400 border-t-transparent rounded-full animate-spin"></span>
          <Icon name="zap" class="h-4 w-4" /><span>Uji Koneksi</span>
        </button>
        <button
          @click="refreshModels"
          :disabled="isRefreshing"
          class="btn btn-primary shine btn-sm flex items-center gap-1.5 disabled:opacity-50"
        >
          <span v-if="isRefreshing" class="w-3 h-3 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
          <Icon name="refresh" class="h-4 w-4" /><span>Sinkronkan Katalog Model</span>
        </button>
      </div>
    </div>

    <!-- Live Test Status Box -->
    <div v-if="testResult" class="p-4 rounded-2xl border transition-all text-xs flex items-start gap-3"
      :class="testResult.success ? 'bg-emerald-500/10 border-emerald-500/40 text-emerald-600 dark:text-emerald-300' : 'bg-rose-500/10 border-rose-500/40 text-rose-600 dark:text-rose-300'"
    >
      <Icon :name="testResult.success ? 'check-circle' : 'x-circle'" class="h-5 w-5" />
      <div class="space-y-1">
        <h4 class="font-bold text-ink">{{ testResult.success ? 'Koneksi Berhasil' : 'Uji Koneksi Gagal' }}</h4>
        <p class="leading-relaxed">{{ testResult.message }}</p>
        <div v-if="testResult.latency_ms" class="text-[10px] font-mono text-muted pt-0.5">
          Latensi Gateway: {{ testResult.latency_ms }} ms
        </div>
      </div>
    </div>

    <!-- Agent Gateway Config Form -->
    <div class="rounded-3xl border-2 border-line bg-card p-6 space-y-5">
      <div class="flex items-center justify-between border-b border-line pb-3">
        <div class="space-y-0.5">
          <h3 class="font-bold text-base text-ink">Gateway Provider: TokenomixAI</h3>
          <p class="text-xs text-muted">Endpoint OpenAI-Compatible resmi TokenomixAI: https://api.tokenomixai.com/v1</p>
        </div>
        <span
          class="px-2.5 py-1 rounded-full text-xs font-semibold"
          :class="form.is_active ? 'bg-emerald-500/20 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30' : 'bg-surface-2 text-muted'"
        >
          {{ form.is_active ? 'Aktif' : 'Nonaktif' }}
        </span>
      </div>

      <form @submit.prevent="saveAgentConfig" class="space-y-5 text-xs">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="label">Nama Konfigurasi</label>
            <input
              v-model="form.name"
              type="text"
              required
              class="input"
            />
          </div>

          <div>
            <label class="label">Base URL Upstream</label>
            <input
              v-model="form.base_url"
              type="url"
              required
              class="input font-mono"
            />
          </div>
        </div>

        <div>
          <label class="label">
            API Key TokenomixAI (Enkripsi Server-Side)
          </label>
          <input
            v-model="form.api_key"
            type="password"
            :placeholder="hasSavedKey ? (form.masked_api_key || 'sk-tkx-•••••••••••••••• (Kosongkan jika tidak diubah)') : 'sk-tkx-xxxxxxxxxxxxxxxx'"
            class="input text-violet-600 dark:text-violet-300 font-mono"
          />
          <p class="text-[10px] text-muted mt-1">
            Kunci rahasia disimpan terenkripsi di server dan tidak pernah dikirim dalam bentuk plaintext ke browser.
          </p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
          <div>
            <label class="label">Model Default</label>
            <select
              v-model="form.default_model"
              class="input text-violet-600 dark:text-violet-300 font-mono"
            >
              <option v-for="m in multipliers" :key="m.model_id" :value="m.model_id">
                {{ m.model_id }} ({{ m.multiplier }}x)
              </option>
            </select>
          </div>

          <div>
            <label class="label">Batas Output Token (Max Tokens)</label>
            <input
              v-model.number="form.max_output_tokens"
              type="number"
              min="256"
              max="128000"
              class="input font-mono"
            />
          </div>

          <div>
            <label class="label">Temperature</label>
            <input
              v-model.number="form.temperature"
              type="number"
              step="0.05"
              min="0"
              max="2"
              class="input font-mono"
            />
          </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
          <div>
            <label class="label">Timeout Request (detik)</label>
            <input
              v-model.number="form.timeout_seconds"
              type="number"
              min="10"
              max="300"
              class="input font-mono"
            />
          </div>

          <div>
            <label class="label">Batas Konkurensi Agent</label>
            <input
              v-model.number="form.concurrency_limit"
              type="number"
              min="1"
              max="50"
              class="input font-mono"
            />
          </div>

          <div>
            <label class="label">Maks Iterasi Vibecoding</label>
            <input
              v-model.number="form.max_iterations"
              type="number"
              min="1"
              max="20"
              class="input font-mono"
            />
          </div>
        </div>

        <div class="flex items-center gap-3 pt-2">
          <label class="flex items-center gap-2 cursor-pointer text-ink">
            <input type="checkbox" v-model="form.is_active" class="rounded bg-surface border-line text-violet-600 dark:text-violet-400" />
            <span class="font-semibold">Aktifkan Gateway TokenomixAI untuk Chat & Vibecoding</span>
          </label>
        </div>

        <div class="flex justify-end pt-3 border-t border-line">
          <button
            type="submit"
            :disabled="isSaving"
            class="btn btn-primary shine btn-sm disabled:opacity-50"
          >
            {{ isSaving ? 'Menyimpan...' : 'Simpan Konfigurasi Agent' }}
          </button>
        </div>
      </form>
    </div>

    <!-- Model Multipliers Catalog Table -->
    <div class="rounded-3xl border-2 border-ink bg-card overflow-hidden space-y-0 shadow-brutal">
      <div class="p-4 border-b border-line flex items-center justify-between">
        <div>
          <h3 class="font-bold text-sm text-ink">Katalog Multiplier Model (TokenomixAI)</h3>
          <p class="text-xs text-muted">Bobot multiplier untuk perhitungan: weighted_tokens = raw_tokens × multiplier</p>
        </div>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-left text-xs text-ink/80">
          <thead class="bg-surface/60 border-b border-line text-[11px] text-muted uppercase font-mono">
            <tr>
              <th class="p-3.5">Model ID</th>
              <th class="p-3.5">Multiplier</th>
              <th class="p-3.5">Kemampuan</th>
              <th class="p-3.5">Sumber & Keterangan</th>
              <th class="p-3.5 text-right">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-line font-mono text-[11px]">
            <tr v-for="m in multipliers" :key="m.id" class="hover:bg-surface-2/40">
              <td class="p-3.5 font-bold text-ink font-mono">{{ m.model_id }}</td>
              <td class="p-3.5 text-cyan-600 dark:text-cyan-300 font-bold text-sm">{{ m.multiplier }}x</td>
              <td class="p-3.5 space-x-1">
                <span v-if="m.supports_tools" class="px-1.5 py-0.5 rounded bg-violet-600/20 text-violet-600 dark:text-violet-300 text-[10px]">Tools</span>
                <span v-if="m.supports_vision" class="px-1.5 py-0.5 rounded bg-cyan-500/20 text-cyan-600 dark:text-cyan-300 text-[10px]">Vision</span>
              </td>
              <td class="p-3.5 text-muted font-sans text-xs">
                <div>{{ m.description || '-' }}</div>
                <div class="text-[10px] text-muted font-mono">{{ m.source }}</div>
              </td>
              <td class="p-3.5 text-right font-sans">
                <button
                  @click="openMultiplierEdit(m)"
                  class="btn btn-secondary btn-sm"
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
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-zinc-950/60 backdrop-blur-sm"
    >
      <div class="max-w-sm w-full rounded-[2rem] bg-card border-2 border-ink p-5 space-y-4 shadow-brutal-lg">
        <h3 class="font-bold text-sm text-ink">Edit Multiplier: {{ editingMultiplier.model_id }}</h3>

        <div class="space-y-3 text-xs">
          <div>
            <label class="label">Nilai Multiplier (x)</label>
            <input
              v-model.number="multiplierForm.multiplier"
              type="number"
              step="0.01"
              min="0.1"
              max="500"
              class="input text-violet-600 dark:text-violet-300 font-mono"
            />
          </div>

          <label class="flex items-center gap-2 cursor-pointer text-ink/80">
            <input type="checkbox" v-model="multiplierForm.supports_tools" class="rounded bg-surface border-line" />
            <span>Mendukung Function / Tool Calling</span>
          </label>

          <label class="flex items-center gap-2 cursor-pointer text-ink/80">
            <input type="checkbox" v-model="multiplierForm.supports_vision" class="rounded bg-surface border-line" />
            <span>Mendukung Vision</span>
          </label>
        </div>

        <div class="flex justify-end gap-2 text-xs pt-2">
          <button @click="editingMultiplier = null" class="px-3 py-1.5 text-muted">Batal</button>
          <button @click="saveMultiplier" class="btn btn-primary shine">Simpan</button>
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
    allowed_models: ['glm-5.1', 'glm-5.2', 'glm-5.3-flash', 'kimi-k3', 'deepseek-v4-flash-0731'],
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
