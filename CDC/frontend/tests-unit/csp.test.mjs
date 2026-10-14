// SEC-009 (P-1.6): baseline Content-Security-Policy. Run: npm run test:unit
import { test } from "node:test";
import assert from "node:assert/strict";
import { contentSecurityPolicy } from "../lib/csp.js";

const parse = (policy) =>
  Object.fromEntries(
    policy
      .split(";")
      .map((part) => part.trim())
      .filter(Boolean)
      .map((part) => {
        const [name, ...values] = part.split(/\s+/);
        return [name, values];
      }),
  );

test("production policy has no unsafe-eval and locks down objects, bases, forms and framing", () => {
  const csp = parse(contentSecurityPolicy({ apiUrl: "https://api.cdc.example/api", dev: false }));
  for (const [directive, values] of Object.entries(csp)) {
    assert.ok(!values.includes("'unsafe-eval'"), `${directive} must not allow unsafe-eval`);
  }
  assert.deepEqual(csp["default-src"], ["'self'"]);
  assert.deepEqual(csp["object-src"], ["'none'"]);
  assert.deepEqual(csp["base-uri"], ["'self'"]);
  assert.deepEqual(csp["form-action"], ["'self'"]);
  assert.deepEqual(csp["frame-ancestors"], ["'self'"]);
});

test("scripts and workers come only from this site (pdf.js worker is bundled, not from a CDN)", () => {
  const csp = parse(contentSecurityPolicy({ apiUrl: "https://api.cdc.example/api", dev: false }));
  assert.ok(csp["script-src"].every((v) => !v.includes("unpkg") && !v.startsWith("http")), "no third-party script hosts");
  assert.ok(csp["worker-src"].includes("'self'"));
  assert.ok(!csp["worker-src"].some((v) => v.startsWith("http")));
});

test("the API origin (not its path) is allowed for fetch and images", () => {
  const csp = parse(contentSecurityPolicy({ apiUrl: "https://api.cdc.example/api", dev: false }));
  assert.ok(csp["connect-src"].includes("https://api.cdc.example"));
  assert.ok(csp["img-src"].includes("https://api.cdc.example"));
});

test("development adds unsafe-eval only for scripts (React dev tooling)", () => {
  const csp = parse(contentSecurityPolicy({ apiUrl: "http://127.0.0.1:8000/api", dev: true }));
  assert.ok(csp["script-src"].includes("'unsafe-eval'"));
  assert.ok(!csp["style-src"].includes("'unsafe-eval'"));
});
