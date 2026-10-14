import { Inter } from "next/font/google";

// PU (owner approved, P3-D17): Inter is fetched once at build time and served from this site, so the browser makes no
// request to a font host and the CSP's `font-src 'self'` holds. Applied through lib/theme.ts.
export const inter = Inter({ subsets: ["latin"], display: "swap" });
