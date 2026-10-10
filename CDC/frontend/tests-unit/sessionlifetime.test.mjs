// SEC-015 (P-1.12): the login session ends when the 7-day API token does. Run: npm run test:unit
import { test } from "node:test";
import assert from "node:assert/strict";
import { API_TOKEN_LIFETIME_SECONDS, apiTokenExpired } from "../lib/sessionlifetime.js";

const DAY = 24 * 60 * 60 * 1000;

test("the lifetime is Sanctum's 7 days", () => {
  assert.equal(API_TOKEN_LIFETIME_SECONDS, 7 * 24 * 60 * 60);
});

test("a token is live until just before 7 days, then expired", () => {
  const issued = Date.UTC(2026, 9, 1, 9, 0, 0);
  assert.equal(apiTokenExpired(issued, issued), false);
  assert.equal(apiTokenExpired(issued, issued + 6 * DAY), false);
  assert.equal(apiTokenExpired(issued, issued + 7 * DAY - 2 * 60 * 1000), false);
  assert.equal(apiTokenExpired(issued, issued + 7 * DAY - 30 * 1000), true, "a minute's margin before the API refuses it");
  assert.equal(apiTokenExpired(issued, issued + 8 * DAY), true);
});

test("a session without an issue time (signed in before this change) counts as expired", () => {
  assert.equal(apiTokenExpired(undefined), true);
  assert.equal(apiTokenExpired(null), true);
  assert.equal(apiTokenExpired("2026-10-01"), true);
});
