<template>
  <AuthShell
    eyebrow="Masuk"
    :title="mfaStep ? 'Satu langkah lagi' : 'Selamat datang lagi'"
    :subtitle="mfaStep ? 'Masukkan kode dari aplikasi authenticator kamu.' : 'Masuk dan lanjutkan ngoding bareng AI.'"
  >
    <!-- Step 1: email & password -->
    <form v-if="!mfaStep" class="space-y-5" @submit.prevent="handleLogin">
      <div v-if="errorMessage" class="flex items-start gap-2 rounded-2xl border-2 border-rose-400 bg-rose-500/10 p-3 text-sm text-rose-600 dark:text-rose-300">
        <Icon name="alert-triangle" class="mt-0.5 h-4 w-4" />
        <span>{{ errorMessage }}</span>
      </div>

      <div>
        <label class="label" for="login-email">Email</label>
        <input id="login-email" v-model="form.email" type="email" required autocomplete="email" class="input" placeholder="nama@domain.com" />
      </div>

      <div>
        <label class="label" for="login-password">Password</label>
        <div class="relative">
          <input
            id="login-password"
            v-model="form.password"
            :type="showPassword ? 'text' : 'password'"
            required
            autocomplete="current-password"
            class="input pr-12"
            placeholder="Password kamu"
          />
          <button
            type="button"
            class="absolute top-1/2 right-2 grid h-8 w-8 -translate-y-1/2 place-items-center rounded-lg text-muted hover:bg-surface-2 hover:text-ink"
            :title="showPassword ? 'Sembunyikan password' : 'Tampilkan password'"
            @click="showPassword = !showPassword"
          >
            <Icon :name="showPassword ? 'eye' : 'lock'" class="h-4 w-4" />
          </button>
        </div>
      </div>

      <button v-magnetic type="submit" :disabled="isLoading" class="btn btn-primary btn-lg shine w-full">
        <Icon v-if="isLoading" name="loader" class="h-5 w-5 animate-spin" />
        <Icon v-else name="log-in" class="h-5 w-5" />
        {{ isLoading ? 'Memverifikasi...' : 'Masuk ke XZY' }}
      </button>
    </form>

    <!-- Step 2: two-factor code -->
    <form v-else class="space-y-5" @submit.prevent="handleMfaVerify">
      <div class="flex items-center gap-3 rounded-2xl border-2 border-ink bg-yellow-300 p-3 text-sm font-semibold text-zinc-900">
        <Icon name="shield-check" class="h-5 w-5" />
        Verifikasi dua faktor aktif untuk akun ini.
      </div>

      <div v-if="errorMessage" class="flex items-start gap-2 rounded-2xl border-2 border-rose-400 bg-rose-500/10 p-3 text-sm text-rose-600 dark:text-rose-300">
        <Icon name="alert-triangle" class="mt-0.5 h-4 w-4" />
        <span>{{ errorMessage }}</span>
      </div>

      <div>
        <label class="label" for="mfa-code">Kode OTP atau kode pemulihan</label>
        <input
          id="mfa-code"
          v-model="mfaCode"
          type="text"
          inputmode="numeric"
          autocomplete="one-time-code"
          required
          autofocus
          class="input text-center font-mono text-xl tracking-[0.4em]"
          placeholder="123456"
        />
      </div>

      <button type="submit" :disabled="isLoading" class="btn btn-primary btn-lg shine w-full">
        <Icon v-if="isLoading" name="loader" class="h-5 w-5 animate-spin" />
        {{ isLoading ? 'Memverifikasi...' : 'Konfirmasi Kode' }}
      </button>

      <button type="button" class="btn btn-ghost w-full" @click="mfaStep = false">
        <Icon name="arrow-left" class="h-4 w-4" />
        Kembali ke form login
      </button>
    </form>

    <template #footer>
      Belum punya akun?
      <router-link to="/register" class="font-bold text-violet-600 underline decoration-2 underline-offset-4 hover:text-fuchsia-600 dark:text-violet-300">Daftar gratis</router-link>
    </template>
  </AuthShell>
</template>

<script setup lang="ts">
import { ref, reactive } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import AuthShell from '@/components/AuthShell.vue';
import { api } from '@/api/client';
import { useAuth } from '@/stores/auth';
import { useToast } from '@/composables/useToast';

const router = useRouter();
const route = useRoute();
const showPassword = ref(false);

// Back to the page that asked for a login (e.g. after the session expired)
const redirectTarget = () => {
    const r = route.query.redirect;
    return typeof r === 'string' && r.startsWith('/') && !r.startsWith('//') ? r : '/dashboard';
};
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
            router.push(redirectTarget());
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
        router.push(redirectTarget());
    } catch (e: any) {
        errorMessage.value = e.message || 'Kode 2FA tidak valid.';
    } finally {
        isLoading.value = false;
    }
};
</script>
