<template>
  <div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-slate-50 via-indigo-50/40 to-violet-50/30 dark:from-slate-950 dark:via-indigo-950/40 dark:to-violet-950/30 px-4">
    <div class="fixed -top-32 -left-32 w-96 h-96 rounded-full bg-indigo-200/30 dark:bg-indigo-500/10 float blur-3xl"></div>
    <div class="fixed -bottom-32 -right-32 w-96 h-96 rounded-full bg-violet-200/30 dark:bg-violet-500/10 float blur-3xl" style="animation-delay:-4s"></div>

    <div class="w-full max-w-md anim-enter-scale">
      <div class="text-center mb-8">
        <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-gradient-to-br from-indigo-500 to-violet-600 shadow-xl shadow-indigo-200/50 dark:shadow-indigo-500/10 mb-4">
          <i class="ri-database-2-line text-white text-xl"></i>
        </div>
        <h1 class="text-2xl font-bold text-slate-800 dark:text-slate-100">Create Account</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Join the Global Asset Management System</p>
      </div>

      <form @submit.prevent="submit" class="glass rounded-3xl p-7 space-y-5">
        <div>
          <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1.5">Full Name</label>
          <input
            v-model="form.name"
            type="text"
            autocomplete="name"
            class="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white/70 dark:bg-slate-800/70 text-sm text-slate-700 dark:text-slate-200 placeholder:text-slate-300 dark:placeholder:text-slate-600 focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-400 transition-all"
            placeholder="John Doe"
          />
          <p v-if="form.errors.name" class="text-[11px] text-red-500 mt-1">{{ form.errors.name }}</p>
        </div>

        <div>
          <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1.5">Email</label>
          <input
            v-model="form.email"
            type="email"
            autocomplete="email"
            class="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white/70 dark:bg-slate-800/70 text-sm text-slate-700 dark:text-slate-200 placeholder:text-slate-300 dark:placeholder:text-slate-600 focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-400 transition-all"
            placeholder="john@company.com"
          />
          <p v-if="form.errors.email" class="text-[11px] text-red-500 mt-1">{{ form.errors.email }}</p>
        </div>

        <div>
          <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1.5">Password</label>
          <input
            v-model="form.password"
            type="password"
            autocomplete="new-password"
            class="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white/70 dark:bg-slate-800/70 text-sm text-slate-700 dark:text-slate-200 placeholder:text-slate-300 dark:placeholder:text-slate-600 focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-400 transition-all"
            placeholder="••••••••"
          />
          <p v-if="form.errors.password" class="text-[11px] text-red-500 mt-1">{{ form.errors.password }}</p>
        </div>

        <div>
          <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1.5">Confirm Password</label>
          <input
            v-model="form.password_confirmation"
            type="password"
            autocomplete="new-password"
            class="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white/70 dark:bg-slate-800/70 text-sm text-slate-700 dark:text-slate-200 placeholder:text-slate-300 dark:placeholder:text-slate-600 focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-400 transition-all"
            placeholder="••••••••"
          />
        </div>

        <button
          type="submit"
          :disabled="form.processing"
          class="w-full py-2.5 rounded-xl bg-gradient-to-r from-indigo-500 to-violet-600 text-white text-sm font-semibold shadow-lg shadow-indigo-200/50 dark:shadow-indigo-500/10 hover:shadow-xl hover:shadow-indigo-300/50 transition-all disabled:opacity-60"
        >
          <span v-if="form.processing" class="flex items-center justify-center gap-2">
            <i class="ri-loader-4-line animate-spin"></i> Creating account…
          </span>
          <span v-else>Create Account</span>
        </button>

        <p class="text-center text-xs text-slate-400 dark:text-slate-500 pt-1">
          Already have an account?
          <a href="/login" @click.prevent="$inertia.visit('/login')" class="text-indigo-500 font-semibold hover:underline">Sign in</a>
        </p>
      </form>
    </div>
  </div>
</template>

<script setup>
import { useForm } from '@inertiajs/vue3';
import { useScrollReveal } from '@/composables/useAnimations';

useScrollReveal();

const form = useForm({
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
});

const submit = () => {
  form.post('/register', {
    onFinish: () => form.reset('password', 'password_confirmation'),
  });
};
</script>
