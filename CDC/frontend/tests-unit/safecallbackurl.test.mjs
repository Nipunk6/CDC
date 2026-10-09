// SEC-005 (P-1.4): only same-origin, single-slash paths are accepted as a post-login callbackUrl.
// Run: npm run test:unit (Node's built-in test runner, no extra dependency).
import { test } from "node:test";
import assert from "node:assert/strict";
import { safeCallbackUrl } from "../lib/safecallbackurl.js";

test("accepts same-origin paths, keeping query and hash", () => {
  assert.equal(safeCallbackUrl("/student/postings", "/student"), "/student/postings");
  assert.equal(safeCallbackUrl("/admin/postings/7?tab=grid#r2", "/admin"), "/admin/postings/7?tab=grid#r2");
  assert.equal(safeCallbackUrl("  /company  ", "/company"), "/company");
});

test("rejects other origins and protocol tricks", () => {
  for (const evil of [
    "https://evil.example",
    "http://evil.example/admin",
    "//evil.example",
    "///evil.example",
    "/\\evil.example",
    "\\\\evil.example",
    "javascript:alert(1)",
    "JavaScript:alert(1)",
    "data:text/html,<script>alert(1)</script>",
    "/%2F%2Fevil.example",
    "/\tevil",
    "evil.example/student",
    "",
    null,
    undefined,
  ]) {
    assert.equal(safeCallbackUrl(evil, "/student"), "/student", `should reject ${JSON.stringify(evil)}`);
  }
});

test("rejects paths that would loop back to a login page", () => {
  assert.equal(safeCallbackUrl("/auth/login/admin", "/admin"), "/admin");
});
