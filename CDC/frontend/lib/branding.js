"use client";

import { useEffect, useState } from "react";

const apiBase = process.env.NEXT_PUBLIC_API_URL ?? "http://localhost:8000/api";

// Account tab branding (S8.1): the institute display name and account logo, public and the same for every
// portal. Fetched once per page load and shared by every shell; the Account tab refreshes it after a save.
const BRANDING_UPDATED_EVENT = "branding-updated";
export const DEFAULT_LOGO = "/images/centenary-badge.png";

let pending = null;
let cacheBust = "";

function loadBranding() {
  pending ??= fetch(`${apiBase}/branding`, { headers: { Accept: "application/json" } })
    .then((response) => (response.ok ? response.json() : null))
    .catch(() => null);
  return pending;
}

function withLogoUrl(branding) {
  if (!branding) return null;
  return {
    display_name: branding.display_name || null,
    logo_url: branding.has_logo ? `${apiBase}/branding/logo${cacheBust ? `?t=${cacheBust}` : ""}` : null,
  };
}

/** Call after the logo or name changes so every mounted shell picks it up at once. */
export function notifyBrandingUpdated() {
  pending = null;
  cacheBust = String(Date.now());
  window.dispatchEvent(new Event(BRANDING_UPDATED_EVENT));
}

/**
 * `{display_name, logo_url}` (either may be null) once loaded; null while loading or when unreachable.
 *
 * @returns {{ display_name: string | null, logo_url: string | null } | null}
 */
export function useBranding() {
  const [branding, setBranding] = useState(/** @type {{ display_name: string | null, logo_url: string | null } | null} */ (null));

  useEffect(() => {
    let alive = true;
    const run = () =>
      loadBranding().then((data) => {
        if (alive) setBranding(withLogoUrl(data));
      });
    run();
    window.addEventListener(BRANDING_UPDATED_EVENT, run);
    return () => {
      alive = false;
      window.removeEventListener(BRANDING_UPDATED_EVENT, run);
    };
  }, []);

  return branding;
}
