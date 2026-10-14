// SEC-009: baseline Content-Security-Policy, sent on every page by next.config.ts. No nonces (Next.js docs: a static
// policy in next.config needs 'unsafe-inline' for its inline bootstrap scripts and for MUI/Emotion style tags).
// 'unsafe-eval' is added only in development, where React's dev tooling needs it. Scripts and workers come only from
// this site — the pdf.js worker is bundled (SEC-018), not loaded from a CDN. The browser talks to the Laravel API
// directly, so its origin is allowed for fetch and for /storage images.

/**
 * @param {{ apiUrl?: string, dev?: boolean }} options
 * @returns {string}
 */
export function contentSecurityPolicy({ apiUrl, dev = false } = {}) {
  let apiOrigin = "";
  try {
    apiOrigin = new URL(apiUrl ?? "http://localhost:8000/api").origin;
  } catch {
    apiOrigin = "";
  }
  const api = apiOrigin ? [apiOrigin] : [];

  const directives = {
    "default-src": ["'self'"],
    "script-src": ["'self'", "'unsafe-inline'", ...(dev ? ["'unsafe-eval'"] : [])],
    "style-src": ["'self'", "'unsafe-inline'"],
    "img-src": ["'self'", "blob:", "data:", ...api],
    "font-src": ["'self'", "data:"],
    "connect-src": ["'self'", ...api],
    "frame-src": ["'self'", "blob:"],
    "worker-src": ["'self'", "blob:"],
    "object-src": ["'none'"],
    "base-uri": ["'self'"],
    "form-action": ["'self'"],
    "frame-ancestors": ["'self'"],
  };

  return Object.entries(directives)
    .map(([name, values]) => `${name} ${values.join(" ")}`)
    .join("; ");
}
