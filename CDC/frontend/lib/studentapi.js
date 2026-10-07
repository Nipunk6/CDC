import { getSession } from "next-auth/react";

const apiBase = process.env.NEXT_PUBLIC_API_URL ?? "http://localhost:8000/api";

export const SUSPENDED_EVENT = "student-account-suspended";
export const SESSION_EXPIRED_EVENT = "student-session-expired";

// A suspended account (403 "Account suspended…") or a dead token (401) is shown by the student shell, whichever
// page made the call (QA F-020).
function reportAuthFailure(status, payload) {
  if (typeof window === "undefined") return;
  if (status === 403 && /suspended/i.test(payload?.message ?? "")) {
    window.dispatchEvent(new Event(SUSPENDED_EVENT));
  } else if (status === 401) {
    window.dispatchEvent(new CustomEvent(SESSION_EXPIRED_EVENT));
  }
}

export async function studentApi(path, init) {
  const session = await getSession();
  const token = session?.accessToken;

  if (!token) {
    throw new Error("Not authenticated.");
  }

  const response = await fetch(`${apiBase}${path}`, {
    ...init,
    headers: {
      "Content-Type": "application/json",
      Accept: "application/json",
      Authorization: `Bearer ${token}`,
      ...(init?.headers ?? {}),
    },
  });

  if (!response.ok) {
    const payload = await response.json().catch(() => ({}));
    reportAuthFailure(response.status, payload);
    const error = new Error(payload.message ?? "Request failed.");
    error.status = response.status;
    error.payload = payload;
    throw error;
  }

  return await response.json();
}

export async function studentUpload(path, formData) {
  const session = await getSession();
  const token = session?.accessToken;

  if (!token) {
    throw new Error("Not authenticated.");
  }

  const response = await fetch(`${apiBase}${path}`, {
    method: "POST",
    headers: {
      Accept: "application/json",
      Authorization: `Bearer ${token}`,
    },
    body: formData,
  });

  const payload = await response.json().catch(() => ({}));
  if (!response.ok) {
    reportAuthFailure(response.status, payload);
    // Same error shape as studentApi, so callers can map per-field `errors` (e.g. survey questions, L20).
    const error = new Error(payload.message ?? "Upload failed.");
    error.status = response.status;
    error.payload = payload;
    error.errors = payload.errors ?? null;
    throw error;
  }
  return payload;
}

// Fetch a protected file (own photo, own resume) as an object URL. The caller revokes it.
export async function studentBlobUrl(path) {
  const session = await getSession();
  const token = session?.accessToken;

  if (!token) {
    throw new Error("Not authenticated.");
  }

  const response = await fetch(`${apiBase}${path}`, {
    headers: { Accept: "*/*", Authorization: `Bearer ${token}` },
  });

  if (!response.ok) {
    const payload = await response.json().catch(() => ({}));
    reportAuthFailure(response.status, payload);
    throw new Error(payload.message ?? "Could not load the file.");
  }

  return URL.createObjectURL(await response.blob());
}
