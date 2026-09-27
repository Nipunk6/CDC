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

  const allowedOrigins = new Set([request.nextUrl.origin, apiOrigin].filter(Boolean));
  if (!allowedOrigins.has(target.origin)) {
    return new NextResponse("URL origin not allowed", { status: 400 });
  }

  try {
    const response = await fetch(target.toString());
    if (!response.ok) {
      return new NextResponse(`Failed to fetch PDF: ${response.statusText}`, { status: response.status });
    }

    const data = await response.arrayBuffer();
    const contentType = response.headers.get("content-type") || "application/pdf";

    return new NextResponse(data, {
      headers: {
        "Content-Type": contentType,
        "Content-Disposition": "inline",
      },
    });
  } catch (error) {
    console.error("Error proxying PDF:", error);
    return new NextResponse("Error fetching PDF", { status: 500 });
  }
}
