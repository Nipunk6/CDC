import type { NextConfig } from "next";
import path from "path";
import { contentSecurityPolicy } from "./lib/csp.js";

const securityHeaders = [
  {
    key: "X-Frame-Options",
    value: "SAMEORIGIN",
  },
  {
    key: "X-Content-Type-Options",
    value: "nosniff",
  },
  {
    key: "Referrer-Policy",
    value: "strict-origin-when-cross-origin",
  },
  {
    key: "Permissions-Policy",
    value: "camera=(), microphone=(), geolocation=()",
  },
  {
    key: "Strict-Transport-Security",
    value: "max-age=31536000; includeSubDomains",
  },
];

// SEC-009: every page gets the CSP. PDF responses (static .pdf files and /api/proxy-pdf) are left out: when the PDF
// response itself carries this policy, Chrome's built-in viewer shows a blank frame in the resume and policy-document
// iframes (checked in Chrome 152). A PDF has no page script for the policy to protect.
const contentSecurityPolicyHeader = {
  key: "Content-Security-Policy",
  value: contentSecurityPolicy({
    apiUrl: process.env.NEXT_PUBLIC_API_URL,
    dev: process.env.NODE_ENV === "development",
  }),
};

const nextConfig: NextConfig = {
  reactStrictMode: true,
  poweredByHeader: false,
  turbopack: {
    root: path.resolve(__dirname),
  },
  images: {
    remotePatterns: [
      {
        protocol: "http",
        hostname: "127.0.0.1",
        pathname: "/storage/**",
      },
      {
        protocol: "http",
        hostname: "localhost",
        pathname: "/storage/**",
      },
    ],
  },
  allowedDevOrigins: ["127.0.0.1", "localhost", "172.22.78.215"],
  async headers() {
    return [
      {
        source: "/(.*)",
        headers: securityHeaders,
      },
      {
        source: "/((?!api/proxy-pdf|.*\\.pdf).*)",
        headers: [contentSecurityPolicyHeader],
      },
    ];
  },
};
export default nextConfig;
