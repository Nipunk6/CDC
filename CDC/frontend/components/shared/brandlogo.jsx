"use client";

import { DEFAULT_LOGO } from "@/lib/branding";

// The account logo when one is uploaded, else the built-in centenary badge (also if the logo fails to load).
export default function BrandLogo({ branding, size = 32, alt = "Institute logo" }) {
  return (
    // eslint-disable-next-line @next/next/no-img-element
    <img
      key={branding?.logo_url ?? DEFAULT_LOGO}
      src={branding?.logo_url ?? DEFAULT_LOGO}
      alt={alt}
      width={size}
      height={size}
      style={{ objectFit: "contain", width: size, height: size }}
      onError={(event) => {
        if (!event.currentTarget.src.endsWith(DEFAULT_LOGO)) event.currentTarget.src = DEFAULT_LOGO;
      }}
    />
  );
}
