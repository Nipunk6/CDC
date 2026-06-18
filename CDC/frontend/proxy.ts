import { NextResponse } from "next/server";
import { auth } from "@/auth";

export default auth((req) => {
  const pathname = req.nextUrl.pathname;
  const isAuthRoute = pathname.startsWith("/auth");
  const isPublicCompanyRegisterRoute = pathname.startsWith("/company/register");
  const isAdminRoute = pathname.startsWith("/admin");
  const isCompanyRoute = pathname.startsWith("/company") && !isPublicCompanyRegisterRoute;
  const session = req.auth;
  const role = session?.user?.role;

  if ((isAdminRoute || isCompanyRoute) && !session) {
    const loginUrl = new URL("/auth/login", req.url);
    loginUrl.searchParams.set("callbackUrl", pathname);
    return NextResponse.redirect(loginUrl);
  }

  if (isAdminRoute && role !== "admin") {
    return NextResponse.redirect(new URL("/", req.url));
  }

  if (isCompanyRoute && role !== "company") {
    return NextResponse.redirect(new URL("/", req.url));
  }

  if (isAuthRoute && session) {
    const destination = role === "admin" ? "/admin" : "/company";
    return NextResponse.redirect(new URL(destination, req.url));
  }

  return NextResponse.next();
});

export const config = {
  matcher: ["/auth/:path*", "/admin/:path*", "/company/:path*"],
};
