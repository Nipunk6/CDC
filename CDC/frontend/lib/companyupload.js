import { getSession } from "next-auth/react";
import { signOutOnUnauthorized } from "@/lib/signoutonunauthorized";

const apiBase = process.env.NEXT_PUBLIC_API_URL ?? "http://localhost:8000/api";

// Company twin of adminUpload (lib/adminupload.js): multipart POST with the bearer token. Errors carry the server's
// message and `payload`, so callers can show the per-row problems of a sheet upload (P3-D56).
export async function companyUpload(path, formData) {
  const session = await getSession();
  const token = session?.accessToken;
  if (!token) {
    throw new Error("Not authenticated.");
  }

  const response = await fetch(`${apiBase}${path}`, {
    method: "POST",
    headers: { Accept: "application/json", Authorization: `Bearer ${token}` },
    body: formData,
  });

  const payload = await response.json().catch(() => ({}));
  if (!response.ok) {
    signOutOnUnauthorized(response.status, "/auth/login/recruiter");
    const error = new Error(payload.message ?? "Upload failed.");
    error.payload = payload;
    throw error;
  }
  return payload;
}
