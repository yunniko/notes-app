import { readonly, ref } from "vue";
import { authApi } from "@/api/auth";
import type { User } from "@/domain/user";

const user = ref<User | null>(null);
let loading: Promise<void> | undefined;

/**
 * Who is logged in. Module-level state: the router guard, the header and the auth pages share it.
 * The session cookie is HttpOnly, so the app learns about it only by asking the server (`/auth/me`).
 */
export function useSession() {
  /** Asks the server once per page load; later calls reuse the answer. */
  function ensureLoaded(): Promise<void> {
    loading ??= authApi
      .me()
      .then((current) => {
        user.value = current;
      })
      .catch(() => {
        // Server unreachable: treat as logged out; the login page reports the network error.
        user.value = null;
        loading = undefined;
      });
    return loading;
  }

  /** After a successful login or registration. */
  function signedIn(current: User): void {
    user.value = current;
    loading = Promise.resolve();
  }

  /** Logs out on the server; the local state is cleared even if that request fails. */
  async function logOut(): Promise<void> {
    try {
      await authApi.logout();
    } finally {
      user.value = null;
    }
  }

  /** The server answered 401: the session is gone (expired, or logged out elsewhere). */
  function expired(): void {
    user.value = null;
  }

  return { user: readonly(user), ensureLoaded, signedIn, logOut, expired };
}

/** Test hook: forget the cached answer so each test starts logged out and unloaded. */
export function resetSessionForTests(): void {
  user.value = null;
  loading = undefined;
}
