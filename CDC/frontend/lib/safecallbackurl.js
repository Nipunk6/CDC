// SEC-005: where to go after login. A callbackUrl from the query string is used only when it is a plain path on this
// site — one leading "/", not "//" or "/\", no control characters, nothing that decodes to another origin, and not a
// login page. Anything else falls back to the role's home page, so a crafted link cannot send a user to another site
// right after a genuine login. Shared by app/auth/login/page.tsx and app/auth/login/[type]/page.tsx.

const BASE = "http://callback.invalid";

/**
 * @param {unknown} raw the callbackUrl query value
 * @param {string} fallback the role home page, e.g. "/student"
 * @returns {string}
 */
export function safeCallbackUrl(raw, fallback) {
  if (typeof raw !== "string") return fallback;
  const value = raw.trim();

  if (!value.startsWith("/") || value.startsWith("//") || value.startsWith("/\\")) return fallback;
  if (/[\u0000-\u001f\u007f\\]/.test(value)) return fallback;

  let decoded = value;
  try {
    decoded = decodeURIComponent(value);
  } catch {
    return fallback;
  }
  if (decoded.startsWith("//") || decoded.startsWith("/\\")) return fallback;

  let url;
  try {
    url = new URL(value, BASE);
  } catch {
    return fallback;
  }
  if (url.origin !== BASE) return fallback;
  if (url.pathname === "/auth" || url.pathname.startsWith("/auth/")) return fallback;

  return `${url.pathname}${url.search}${url.hash}`;
}
