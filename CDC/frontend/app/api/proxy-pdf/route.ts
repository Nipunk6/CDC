import { NextRequest, NextResponse } from "next/server";
import { auth } from "@/auth";

const apiOrigin = (() => {
  try {
    return new URL(process.env.NEXT_PUBLIC_API_URL ?? "http://127.0.0.1:8000/api").origin;
  } catch {
    return null;
  }
})();

export async function GET(request: NextRequest) {
  const session = await auth();
  // Fail closed: on an Auth.js config error `auth()` resolves to a truthy error object, never a user.
  if (!session?.user) {
    return new NextResponse("Unauthorized", { status: 401 });
  }

  const { searchParams } = new URL(request.url);
  const pdfUrl = searchParams.get("url");

  if (!pdfUrl) {
    return new NextResponse("Missing url parameter", { status: 400 });
  }

  let target: URL;
  try {
    target = new URL(pdfUrl);
  } catch {
    return new NextResponse("Invalid url parameter", { status: 400 });
  }

  // Only PDFs this app itself serves may be proxied: policy documents and signed resume links (D59).
  const allowedOnApi =
    target.origin === apiOrigin &&
    (target.pathname.startsWith("/storage/policy-documents/") || target.pathname.startsWith("/api/resumes/signed/"));
  const allowedOnApp = target.origin === request.nextUrl.origin && target.pathname.toLowerCase().endsWith(".pdf");
  if (!allowedOnApi && !allowedOnApp) {
    return new NextResponse("URL not allowed", { status: 400 });
  }

  try {
    const response = await fetch(target.toString(), { redirect: "error" });
    if (!response.ok) {
      return new NextResponse(`Failed to fetch PDF: ${response.statusText}`, { status: response.status });
    }

    const upstreamType = (response.headers.get("content-type") ?? "").toLowerCase();
    if (!upstreamType.startsWith("application/pdf")) {
      return new NextResponse("Not a PDF", { status: 415 });
    }

    const data = await response.arrayBuffer();

    // Always served as a PDF, never sniffed, so nothing proxied can run script on this origin.
    return new NextResponse(data, {
      headers: {
        "Content-Type": "application/pdf",
        "Content-Disposition": "inline",
        "X-Content-Type-Options": "nosniff",
        "Cache-Control": "private, no-store",
      },
    });
  } catch (error) {
    console.error("Error proxying PDF:", error);
    return new NextResponse("Error fetching PDF", { status: 500 });
  }
}
