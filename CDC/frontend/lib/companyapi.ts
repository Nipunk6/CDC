import { getSession } from "next-auth/react";

const apiBase = process.env.NEXT_PUBLIC_API_URL ?? "http://localhost:8000/api";

export async function companyApi<T>(path: string, init?: RequestInit): Promise<T> {
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
    const payload = (await response.json().catch(() => ({}))) as { message?: string };
    throw new Error(payload.message ?? "Request failed.");
  }

  return (await response.json()) as T;
}

export async function companyFileUpload(file: File): Promise<{ file: { path: string; url: string; name: string; size: number; mime: string } }> {
  const session = await getSession();
  const token = session?.accessToken;

  if (!token) {
    throw new Error("Not authenticated.");
  }

  const formData = new FormData();
  formData.append("file", file);

  const response = await fetch(`${apiBase}/company/uploads`, {
    method: "POST",
    headers: {
      Accept: "application/json",
      Authorization: `Bearer ${token}`,
    },
    body: formData,
  });

  if (!response.ok) {
    const payload = (await response.json().catch(() => ({}))) as { message?: string };
    throw new Error(payload.message ?? "Upload failed.");
  }

  return (await response.json()) as { file: { path: string; url: string; name: string; size: number; mime: string } };
}

export async function companyLogoUpload(
  file: File
): Promise<{ message: string; company: { logo_url?: string | null } }> {
  const session = await getSession();
  const token = session?.accessToken;

  if (!token) {
    throw new Error("Not authenticated.");
  }

  const formData = new FormData();
  formData.append("company_logo", file);

  const response = await fetch(`${apiBase}/company/profile/logo`, {
    method: "POST",
    headers: {
      Accept: "application/json",
      Authorization: `Bearer ${token}`,
    },
    body: formData,
  });

  if (!response.ok) {
    const payload = (await response.json().catch(() => ({}))) as { message?: string };
    throw new Error(payload.message ?? "Logo upload failed.");
  }

  return (await response.json()) as { message: string; company: { logo_url?: string | null } };
}
