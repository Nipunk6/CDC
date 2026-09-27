import { NextResponse } from "next/server";
import { auth } from "@/auth";

export default auth((req) => {
  const pathname = req.nextUrl.pathname;
  const isAuthRoute = pathname.startsWith("/auth");
  const isPublicCompanyRegisterRoute = pathname.startsWith("/company/register");
  const isAdminRoute = pathname.startsWith("/admin");
  const isCompanyRoute = pathname.startsWith("/company") && !isPublicCompanyRegisterRoute;
  const isStudentRoute = pathname.startsWith("/student");
  // Fail closed: treat anything without a user as signed-out (Auth.js returns an error object on misconfiguration).
  const session = req.auth?.user ? req.auth : null;
  const role = session?.user?.role;

  if ((isAdminRoute || isCompanyRoute || isStudentRoute) && !session) {
    // The /auth/login redirector only knows admin vs recruiter, so students go straight to their page.
    const loginUrl = new URL(isStudentRoute ? "/auth/login/student" : "/auth/login", req.url);
    loginUrl.searchParams.set("callbackUrl", pathname);
    return NextResponse.redirect(loginUrl);
  }

  if (isAdminRoute && role !== "admin") {
    return NextResponse.redirect(new URL("/", req.url));
  }

  if (isCompanyRoute && role !== "company") {
    return NextResponse.redirect(new URL("/", req.url));
  }

  if (isStudentRoute && role !== "student") {
    return NextResponse.redirect(new URL("/", req.url));
  }

  if (isAuthRoute && session) {
    const destination = role === "admin" ? "/admin" : role === "student" ? "/student" : "/company";
    return NextResponse.redirect(new URL(destination, req.url));
  }

  return NextResponse.next();
});

export const config = {
  matcher: ["/auth/:path*", "/admin/:path*", "/company/:path*", "/student/:path*"],
};
