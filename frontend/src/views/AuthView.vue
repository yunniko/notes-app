<script setup lang="ts">
import { computed, nextTick, ref, useTemplateRef, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import { authApi } from "@/api/auth";
import { ApiError } from "@/api/http";
import { useSession } from "@/composables/useSession";
import {
  PASSWORD_MIN_LENGTH,
  safeRedirect,
  validateLogin,
  validateRegistration,
  type CredentialErrors,
  type Credentials,
} from "@/domain/user";

/** One page for both forms: they share fields and flow, and differ in rules and wording. */
const props = defineProps<{ mode: "login" | "register" }>();
const route = useRoute();
const router = useRouter();
const session = useSession();

const isRegister = computed(() => props.mode === "register");
const form = ref<Credentials>({ username: "", password: "" });
const showPassword = ref(false);
const attempted = ref(false);
const submitting = ref(false);
const serverErrors = ref<CredentialErrors>({});
const formError = ref<string | null>(null);

const usernameInput = useTemplateRef<HTMLInputElement>("username");
const passwordInput = useTemplateRef<HTMLInputElement>("password");

/** Surrounding spaces in the username are dropped, as a paste often brings them along. */
const credentials = computed<Credentials>(() => ({ username: form.value.username.trim(), password: form.value.password }));
const clientErrors = computed(() => (isRegister.value ? validateRegistration(credentials.value) : validateLogin(credentials.value)));
const errors = computed<CredentialErrors>(() => ({ ...serverErrors.value, ...(attempted.value ? clientErrors.value : {}) }));

// Switching between the two forms keeps what was typed but drops the other form's errors.
watch(
  () => props.mode,
  () => {
    attempted.value = false;
    serverErrors.value = {};
    formError.value = null;
  }
);

async function onSubmit(): Promise<void> {
  attempted.value = true;
  serverErrors.value = {};
  formError.value = null;
  if (Object.keys(clientErrors.value).length > 0) {
    await nextTick();
    (clientErrors.value.username ? usernameInput.value : passwordInput.value)?.focus();
    return;
  }

  submitting.value = true;
  try {
    const user = isRegister.value ? await authApi.register(credentials.value) : await authApi.login(credentials.value);
    session.signedIn(user);
    await router.replace(safeRedirect(route.query.redirect));
  } catch (error) {
    if (error instanceof ApiError && error.status === 422) {
      serverErrors.value = error.fieldErrors;
      formError.value = "Please fix the highlighted fields.";
    } else if (error instanceof ApiError && error.status === 429) {
      const minutes = Math.ceil((error.retryAfterSeconds ?? 900) / 60);
      formError.value = `Too many failed attempts. Try again in ${minutes} minutes.`;
    } else {
      formError.value = error instanceof ApiError ? error.message : "Something went wrong. Please try again.";
    }
    if (error instanceof ApiError && error.status === 401) {
      // Start the password over; the emptied field is not a mistake to flag yet.
      form.value.password = "";
      attempted.value = false;
      await nextTick();
      passwordInput.value?.focus();
    }
  } finally {
    submitting.value = false;
  }
}
</script>

<template>
  <section class="auth" aria-labelledby="auth-heading">
    <h1 id="auth-heading">{{ isRegister ? "Create account" : "Log in" }}</h1>
    <p class="auth__lead">
      {{ isRegister ? "Your notes are private to your account." : "Log in to see your notes." }}
    </p>

    <p v-if="formError" class="notice notice--error" role="alert">{{ formError }}</p>

    <form class="form" novalidate @submit.prevent="onSubmit">
      <div class="field">
        <label for="auth-username">Username</label>
        <input
          id="auth-username"
          ref="username"
          v-model="form.username"
          type="text"
          autocomplete="username"
          autocapitalize="none"
          spellcheck="false"
          required
          :aria-invalid="!!errors.username"
          :aria-describedby="errors.username ? 'auth-username-error' : isRegister ? 'auth-username-hint' : undefined"
        />
        <p v-if="errors.username" id="auth-username-error" class="field__error" role="alert">{{ errors.username }}</p>
        <p v-else-if="isRegister" id="auth-username-hint" class="field__hint">3–32 letters, digits, dots, dashes or underscores.</p>
      </div>

      <div class="field">
        <label for="auth-password">Password</label>
        <input
          id="auth-password"
          ref="password"
          v-model="form.password"
          :type="showPassword ? 'text' : 'password'"
          :autocomplete="isRegister ? 'new-password' : 'current-password'"
          required
          :aria-invalid="!!errors.password"
          :aria-describedby="errors.password ? 'auth-password-error' : isRegister ? 'auth-password-hint' : undefined"
        />
        <p v-if="errors.password" id="auth-password-error" class="field__error" role="alert">{{ errors.password }}</p>
        <p v-else-if="isRegister" id="auth-password-hint" class="field__hint">At least {{ PASSWORD_MIN_LENGTH }} characters.</p>
        <label class="checkbox"><input v-model="showPassword" type="checkbox" /> Show password</label>
      </div>

      <button type="submit" class="button button--primary" :disabled="submitting">
        {{ submitting ? "Please wait…" : isRegister ? "Create account" : "Log in" }}
      </button>
    </form>

    <p class="auth__switch">
      <template v-if="isRegister">
        Already have an account?
        <RouterLink :to="{ name: 'login', query: route.query }">Log in</RouterLink>
      </template>
      <template v-else>
        New here?
        <RouterLink :to="{ name: 'register', query: route.query }">Create an account</RouterLink>
      </template>
    </p>
  </section>
</template>

<style scoped>
.auth {
  max-width: 24rem;
  margin: 1rem auto 0;
  display: flex;
  flex-direction: column;
  gap: 1rem;
}
.auth h1 {
  margin: 0;
}
.auth__lead,
.auth__switch {
  margin: 0;
  color: var(--text-muted);
}
.form {
  display: flex;
  flex-direction: column;
  gap: 1.1rem;
}
.field {
  display: flex;
  flex-direction: column;
  gap: 0.4rem;
}
.field > label {
  font-weight: 600;
}
.field input[type="text"],
.field input[type="password"] {
  width: 100%;
  padding: 0.6rem 0.75rem;
  font: inherit;
  color: var(--text);
  background: var(--surface);
  border: 1px solid var(--border-strong);
  border-radius: var(--radius-sm);
}
.field [aria-invalid="true"] {
  border-color: var(--danger);
}
.field__error,
.field__hint {
  margin: 0;
  font-size: 0.85rem;
}
.field__error {
  color: var(--danger);
}
.field__hint {
  color: var(--text-muted);
}
.checkbox {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  font-size: 0.9rem;
  font-weight: 400;
  color: var(--text-muted);
}
.notice {
  margin: 0;
  padding: 0.9rem 1rem;
  border-radius: var(--radius-sm);
  border: 1px solid;
}
.notice--error {
  color: var(--danger);
  border-color: var(--danger);
  background: var(--danger-bg);
}
</style>
