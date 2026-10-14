import { getSession } from "next-auth/react";
import { signOutOnUnauthorized } from "@/lib/signoutonunauthorized";

const apiBase = process.env.NEXT_PUBLIC_API_URL ?? "http://localhost:8000/api";

// Company twin of adminDownload (lib/adminapi.ts): bearer fetch + Content-Disposition filename parsing.
export async function companyDownload(path, fallbackFileName) {
  const session = await getSession();
  const token = session?.accessToken;

  if (!token) {
    throw new Error("Not authenticated.");
  }

  const response = await fetch(`${apiBase}${path}`, {
    method: "GET",
    headers: {
      Accept: "application/octet-stream,*/*",
      Authorization: `Bearer ${token}`,
    },
  });

  if (!response.ok) {
    signOutOnUnauthorized(response.status, "/auth/login/recruiter");
    const payload = await response.json().catch(() => ({}));
    throw new Error(payload.message ?? "Download failed.");
  }

  const blob = await response.blob();
  const contentDisposition = response.headers.get("content-disposition") ?? "";
  const fileNameMatch = /filename\*=UTF-8''([^;]+)|filename="?([^";]+)"?/i.exec(contentDisposition);
  const fileName = decodeURIComponent(fileNameMatch?.[1] ?? fileNameMatch?.[2] ?? "") || fallbackFileName;

  const downloadUrl = window.URL.createObjectURL(blob);
  const anchor = document.createElement("a");
  anchor.href = downloadUrl;
  anchor.download = fileName;
  document.body.appendChild(anchor);
  anchor.click();
  anchor.remove();
  window.URL.revokeObjectURL(downloadUrl);
}
