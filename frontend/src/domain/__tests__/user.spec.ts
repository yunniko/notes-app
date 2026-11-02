import { describe, expect, it } from "vitest";
import { safeRedirect, validateLogin, validateRegistration } from "../user";

const USERNAME_RULE = "Username must be 3 to 32 characters: letters, digits, dot, dash or underscore.";

describe("validateRegistration", () => {
  it("accepts a well-formed username and a long enough password", () => {
    expect(validateRegistration({ username: "jan.novak-2", password: "12345678" })).toEqual({});
  });

  it.each([
    ["", "Username is required."],
    ["ab", USERNAME_RULE],
    ["a".repeat(33), USERNAME_RULE],
    ["jan novák", USERNAME_RULE],
  ])("rejects the username %j", (username, message) => {
    expect(validateRegistration({ username, password: "12345678" }).username).toBe(message);
  });

  it("counts password length in characters, like the server", () => {
    expect(validateRegistration({ username: "alice", password: "" }).password).toBe("Password is required.");
    expect(validateRegistration({ username: "alice", password: "1234567" }).password).toBe("Password must be at least 8 characters.");
    expect(validateRegistration({ username: "alice", password: "🐎".repeat(8) })).toEqual({}); // 8 characters, 32 bytes
    expect(validateRegistration({ username: "alice", password: "x".repeat(129) }).password).toBe(
      "Password must be at most 128 characters."
    );
  });
});

describe("validateLogin", () => {
  it("only needs both fields filled", () => {
    expect(validateLogin({ username: "x", password: "y" })).toEqual({});
    expect(validateLogin({ username: "", password: "" })).toEqual({ username: "Username is required.", password: "Password is required." });
  });
});

describe("safeRedirect", () => {
  it.each([
    ["/notes/7", "/notes/7"],
    ["/archive?priority=high", "/archive?priority=high"],
    ["//evil.example", "/"],
    ["/\\evil.example", "/"],
    ["https://evil.example", "/"],
    [undefined, "/"],
    [["/notes/7"], "/"],
  ])("maps %j to %j", (target, expected) => {
    expect(safeRedirect(target)).toBe(expected);
  });
});
