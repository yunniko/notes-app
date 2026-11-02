/** The logged-in account as the API returns it. */
export interface User {
  id: number;
  username: string;
  createdAt: string;
}

export interface Credentials {
  username: string;
  password: string;
}

export type CredentialErrors = Partial<Record<keyof Credentials, string>>;

/** Same rules as the backend's CredentialsValidator; the server stays the authority. */
export const USERNAME_MIN_LENGTH = 3;
export const USERNAME_MAX_LENGTH = 32;
export const PASSWORD_MIN_LENGTH = 8;
export const PASSWORD_MAX_LENGTH = 128;

const USERNAME_PATTERN = /^[A-Za-z0-9._-]+$/;

/** The full rules, checked before creating an account. */
export function validateRegistration({ username, password }: Credentials): CredentialErrors {
  const errors: CredentialErrors = {};
  if (username === "") {
    errors.username = "Username is required.";
  } else if (username.length < USERNAME_MIN_LENGTH || username.length > USERNAME_MAX_LENGTH || !USERNAME_PATTERN.test(username)) {
    errors.username = `Username must be ${USERNAME_MIN_LENGTH} to ${USERNAME_MAX_LENGTH} characters: letters, digits, dot, dash or underscore.`;
  }
  const length = Array.from(password).length; // code points, like PHP's mb_strlen
  if (password === "") {
    errors.password = "Password is required.";
  } else if (length < PASSWORD_MIN_LENGTH) {
    errors.password = `Password must be at least ${PASSWORD_MIN_LENGTH} characters.`;
  } else if (length > PASSWORD_MAX_LENGTH) {
    errors.password = `Password must be at most ${PASSWORD_MAX_LENGTH} characters.`;
  }
  return errors;
}

/** Logging in only needs both fields filled; whether they are right is the server's answer. */
export function validateLogin({ username, password }: Credentials): CredentialErrors {
  const errors: CredentialErrors = {};
  if (username === "") errors.username = "Username is required.";
  if (password === "") errors.password = "Password is required.";
  return errors;
}

/** Where to go after logging in: only a same-app path, never another site (open-redirect guard). */
export function safeRedirect(target: unknown): string {
  return typeof target === "string" && target.startsWith("/") && !target.startsWith("//") && !target.startsWith("/\\") ? target : "/";
}
