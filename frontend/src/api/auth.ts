import type { Credentials, User } from "@/domain/user";
import { ApiError, request } from "./http";

/** Account endpoints. The session itself is an HttpOnly cookie the browser handles. */
export const authApi = {
  /** Creates the account and logs it in. */
  register(credentials: Credentials): Promise<User> {
    return request<User>("POST", "/api/auth/register", credentials);
  },

  /** Rejects with a 401 ApiError for a wrong username or password, 429 after too many attempts. */
  login(credentials: Credentials): Promise<User> {
    return request<User>("POST", "/api/auth/login", credentials);
  },

  logout(): Promise<void> {
    return request<void>("POST", "/api/auth/logout");
  },

  /** The logged-in user, or null when there is no valid session. */
  async me(): Promise<User | null> {
    try {
      return await request<User>("GET", "/api/auth/me");
    } catch (error) {
      if (error instanceof ApiError && error.status === 401) return null;
      throw error;
    }
  },
};
