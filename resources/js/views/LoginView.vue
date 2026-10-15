<template>
  <div class="min-h-screen bg-slate-950 flex flex-col justify-center items-center p-4 selection:bg-violet-500 selection:text-white">
    <div class="max-w-md w-full space-y-6">
      <!-- Brand Logo -->
      <div class="text-center space-y-2">
        <router-link to="/" class="inline-flex items-center gap-2">
          <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-violet-600 via-fuchsia-500 to-cyan-400 flex items-center justify-center font-black text-white text-xl shadow-lg shadow-violet-500/25">
            X
          </div>
          <span class="text-3xl font-black tracking-tight text-white">XZY</span>
        </router-link>
        <p class="text-sm text-slate-400">Masuk ke platform vibecoding dan AI chat Anda</p>
      </div>

      <!-- Main Card -->
      <div class="bg-slate-900/90 border border-slate-800 rounded-2xl p-6 sm:p-8 shadow-2xl backdrop-blur-xl">
        <!-- Step 1: Standard Password Form -->
        <form v-if="!mfaStep" @submit.prevent="handleLogin" class="space-y-4">
          <div v-if="errorMessage" class="p-3.5 rounded-xl bg-rose-950/40 border border-rose-500/30 text-rose-300 text-xs">
            {{ errorMessage }}
          </div>

          <div>
            <label class="block text-xs font-semibold text-slate-300 mb-1.5">Alamat Email</label>
            <input
              v-model="form.email"
              type="email"
              required
              class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950 border border-slate-800 focus:border-cyan-400 focus:ring-1 focus:ring-cyan-400 text-slate-100 text-sm outline-none transition-colors"
              placeholder="nama@domain.com"
            />
          </div>

          <div>
            <label class="block text-xs font-semibold text-slate-300 mb-1.5">Password</label>
            <input
              v-model="form.password"
              type="password"
              required
              class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950 border border-slate-800 focus:border-cyan-400 focus:ring-1 focus:ring-cyan-400 text-slate-100 text-sm outline-none transition-colors"
              placeholder="••••••••"
            />
          </div>

          <button
            type="submit"
            :disabled="isLoading"
            class="w-full py-3 rounded-xl bg-gradient-to-r from-violet-600 to-cyan-500 hover:from-violet-500 hover:to-cyan-400 text-white font-semibold text-sm shadow-lg shadow-violet-600/25 transition-all hover:scale-[1.01] active:scale-[0.99] disabled:opacity-50 disabled:pointer-events-none flex items-center justify-center gap-2"
          >
            <span v-if="isLoading" class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
            <span>{{ isLoading ? 'Memverifikasi...' : 'Masuk ke XZY' }}</span>
          </button>
        </form>

        <!-- Step 2: 2FA MFA Challenge -->
        <form v-else @submit.prevent="handleMfaVerify" class="space-y-4">
          <div class="text-center space-y-1 pb-2">
            <div class="w-10 h-10 rounded-full bg-cyan-950/60 border border-cyan-500/40 text-cyan-400 flex items-center justify-center text-lg mx-auto">
              🔐
            </div>
            <h3 class="font-bold text-white text-base">Verifikasi Dua Faktor</h3>
            <p class="text-xs text-slate-400">Masukkan 6 digit kode dari aplikasi Authenticator atau gunakan kode pemulihan.</p>
          </div>

          <div v-if="errorMessage" class="p-3 rounded-xl bg-rose-950/40 border border-rose-500/30 text-rose-300 text-xs">
            {{ errorMessage }}
          </div>

          <div>
            <label class="block text-xs font-semibold text-slate-300 mb-1.5">Kode OTP / Recovery Code</label>
            <input
              v-model="mfaCode"
              type="text"
              required
              autofocus
              class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950 border border-slate-800 focus:border-cyan-400 focus:ring-1 focus:ring-cyan-400 text-center font-mono text-lg tracking-widest text-cyan-300 outline-none"
              placeholder="123456"
            />
          </div>

          <button
            type="submit"
            :disabled="isLoading"
            class="w-full py-3 rounded-xl bg-gradient-to-r from-violet-600 to-cyan-500 hover:from-violet-500 hover:to-cyan-400 text-white font-semibold text-sm shadow-lg transition-all disabled:opacity-50"
          >
            {{ isLoading ? 'Memverifikasi...' : 'Konfirmasi Kode' }}
          </button>

          <button
            type="button"
            @click="mfaStep = false"
            class="w-full text-xs text-slate-400 hover:text-white transition-colors"
          >
            ← Kembali ke form login
          </button>
        </form>
      </div>

      <!-- Footer Links -->
      <div class="text-center text-xs text-slate-400">
        Belum memiliki akun?
        <router-link to="/register" class="text-cyan-400 hover:underline font-semibold ml-1">
          Daftar sekarang
        </router-link>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive } from 'vue';
import { useRouter } from 'vue-router';
import { api } from '@/api/client';
import { useAuth } from '@/stores/auth';
import { useToast } from '@/composables/useToast';

const router = useRouter();
const { checkAuth } = useAuth();
const toast = useToast();

const form = reactive({
    email: '',
    password: '',
});

const mfaStep = ref(false);
const mfaCode = ref('');
const isLoading = ref(false);
const errorMessage = ref('');

const handleLogin = async () => {
    isLoading.value = true;
    errorMessage.value = '';

    try {
        const res = await api.post('/api/auth/login', form);

        if (res.mfa_required) {
            mfaStep.value = true;
            toast.info('Autentikasi dua faktor diwajibkan untuk akun ini.');
        } else {
            await checkAuth();
            toast.success('Login berhasil.');
            router.push('/dashboard');
        }
    } catch (e: any) {
        errorMessage.value = e.message || 'Login gagal. Periksa kembali email dan password.';
    } finally {
        isLoading.value = false;
    }
};

const handleMfaVerify = async () => {
    isLoading.value = true;
    errorMessage.value = '';

    try {
        await api.post('/api/auth/mfa/verify', { code: mfaCode.value });
        await checkAuth();
        toast.success('Verifikasi dua faktor berhasil.');
        router.push('/dashboard');
    } catch (e: any) {
        errorMessage.value = e.message || 'Kode 2FA tidak valid.';
    } finally {
        isLoading.value = false;
    }
};
</script>
