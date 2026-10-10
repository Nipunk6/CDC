// SEC-015: the Laravel API token expires 7 days after login (backend config/sanctum.php `expiration`), so the NextAuth
// session must not live longer. SessionTokenTest::test_T4_5_frontend_session_does_not_outlive_api_token keeps the
// number below equal to Sanctum's. Used by auth.ts (server side), so it imports nothing.

export const API_TOKEN_LIFETIME_SECONDS = 604800;

/**
 * True once the API token issued at `issuedAtMs` has expired, a minute early so a call never meets a token that dies
 * mid-request. A session without an issue time (signed in before this check existed) counts as expired.
 *
 * @param {unknown} issuedAtMs
 * @param {number} [nowMs]
 * @returns {boolean}
 */
export function apiTokenExpired(issuedAtMs, nowMs = Date.now()) {
  if (typeof issuedAtMs !== "number" || !Number.isFinite(issuedAtMs)) return true;
  return nowMs >= issuedAtMs + API_TOKEN_LIFETIME_SECONDS * 1000 - 60 * 1000;
}
