import { getSession } from "next-auth/react";
import { signOutOnUnauthorized } from "@/lib/signoutonunauthorized";

const apiBase = process.env.NEXT_PUBLIC_API_URL ?? "http://localhost:8000/api";

// Multipart and binary helpers for Phase 2 admin pages. `adminApi` always sends
// JSON, and lib/adminapi.ts is a frozen Phase 1 file, so these live here (D29).

async function bearer() {
  const session = await getSession();
  const token = session?.accessToken;
  if (!token) {
    throw new Error("Not authenticated.");
  }
  return token;
}

export async function adminUpload(path, formData, method = "POST") {
  const token = await bearer();
  const response = await fetch(`${apiBase}${path}`, {
    method,
    headers: { Accept: "application/json", Authorization: `Bearer ${token}` },
    body: formData,
  });

  const payload = await response.json().catch(() => ({}));
  if (!response.ok) {
    signOutOnUnauthorized(response.status, "/auth/login/admin");
    throw new Error(payload.message ?? "Upload failed.");
  }
  return payload;
}

// Fetch a protected file (photo, PDF) and return an object URL the caller must revoke.
export async function adminBlobUrl(path) {
  const token = await bearer();
  const response = await fetch(`${apiBase}${path}`, {
    headers: { Accept: "*/*", Authorization: `Bearer ${token}` },
  });
  if (!response.ok) {
    signOutOnUnauthorized(response.status, "/auth/login/admin");
    const payload = await response.json().catch(() => ({}));
    throw new Error(payload.message ?? "Could not load the file.");
  }
  return URL.createObjectURL(await response.blob());
}
