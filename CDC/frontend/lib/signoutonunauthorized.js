import { signOut } from "next-auth/react";

// SEC-015: a 401 from the API means the token is dead (expired or revoked), so end the login session as well; the
// admin and company portals would otherwise look signed in while every call fails. The student portal does the same
// through its shell (QA F-020). One sign-out at a time, however many calls fail together.
let signingOut = false;

/**
 * @param {number} status the API response status
 * @param {string} loginPath where to land after signing out, e.g. "/auth/login/admin"
 */
export function signOutOnUnauthorized(status, loginPath) {
  if (status !== 401 || typeof window === "undefined" || signingOut) return;
  signingOut = true;
  void signOut({ callbackUrl: loginPath });
}
