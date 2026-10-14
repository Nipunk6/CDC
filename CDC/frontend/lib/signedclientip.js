// Server-only (SEC-008 / QA N-1). Logins (NextAuth authorize) and the PDF proxy call Laravel from the Next.js
// server, so Laravel would otherwise see this server's IP for everyone. These headers carry the real client IP,
// signed with INTERNAL_PROXY_SECRET (never a NEXT_PUBLIC_ variable); Laravel trusts the IP only if the HMAC verifies.
//
// The client IP is read from X-Forwarded-For. Next.js only fills that header when it is missing, so in production
// Next must sit behind a reverse proxy that appends the real address. CLIENT_IP_PROXY_HOPS (default 1) says how many
// trusted proxies append entries: the IP used is that many entries from the right.

const IP_PATTERN = /^[0-9a-fA-F:.]{2,45}$/;

/**
 * @param {Headers | undefined | null} headers
 * @returns {string | null}
 */
export function clientIpFromHeaders(headers) {
  const hops = Number.parseInt(process.env.CLIENT_IP_PROXY_HOPS ?? "1", 10);
  const forwarded = (headers?.get?.("x-forwarded-for") ?? "")
    .split(",")
    .map((part) => part.trim())
    .filter(Boolean);
  let ip = null;
  if (Number.isFinite(hops) && hops > 0 && forwarded.length >= hops) {
    ip = forwarded[forwarded.length - hops];
  } else {
    ip = headers?.get?.("x-real-ip")?.trim() || null;
  }
  if (!ip) return null;
  // IPv4-mapped IPv6 from Node sockets ("::ffff:127.0.0.1") → plain IPv4.
  if (ip.startsWith("::ffff:") && ip.includes(".")) ip = ip.slice(7);
  return IP_PATTERN.test(ip) ? ip : null;
}

function toHex(buffer) {
  return Array.from(new Uint8Array(buffer), (byte) => byte.toString(16).padStart(2, "0")).join("");
}

/**
 * Headers proving the client IP to Laravel, or {} when there is no secret or no IP.
 * @param {Headers | undefined | null} headers
 * @returns {Promise<Record<string, string>>}
 */
export async function signedClientIpHeaders(headers) {
  const secret = process.env.INTERNAL_PROXY_SECRET;
  const ip = clientIpFromHeaders(headers);
  if (!secret || !ip) return {};

  const timestamp = String(Math.floor(Date.now() / 1000));
  const encoder = new TextEncoder();
  const key = await crypto.subtle.importKey("raw", encoder.encode(secret), { name: "HMAC", hash: "SHA-256" }, false, ["sign"]);
  const signature = await crypto.subtle.sign("HMAC", key, encoder.encode(`${ip}|${timestamp}`));

  return {
    "X-CDC-Client-IP": ip,
    "X-CDC-Client-IP-Ts": timestamp,
    "X-CDC-Client-IP-Sig": toHex(signature),
  };
}
