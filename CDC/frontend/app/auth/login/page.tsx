"use client";

import { useEffect, Suspense } from "react";
import { useRouter, useSearchParams } from "next/navigation";
import { Box, CircularProgress } from "@mui/material";
import { safeCallbackUrl } from "@/lib/safecallbackurl";

function LoginRedirectHandler() {
  const router = useRouter();
  const searchParams = useSearchParams();

  useEffect(() => {
    // SEC-005: forward only a safe same-site callbackUrl, never the raw query string.
    const callbackUrl = safeCallbackUrl(searchParams.get("callbackUrl"), "");
    const query = callbackUrl ? `?${new URLSearchParams({ callbackUrl }).toString()}` : "";

    if (callbackUrl.startsWith("/admin")) {
      router.replace(`/auth/login/admin${query}`);
    } else if (callbackUrl.startsWith("/student")) {
      router.replace(`/auth/login/student${query}`);
    } else {
      router.replace(`/auth/login/recruiter${query}`);
    }
  }, [router, searchParams]);

  return (
    <Box sx={{ minHeight: "100vh", display: "flex", alignItems: "center", justifyContent: "center", bgcolor: "grey.50" }}>
      <CircularProgress />
    </Box>
  );
}

export default function LoginRedirectPage() {
  return (
    <Suspense fallback={
      <Box sx={{ minHeight: "100vh", display: "flex", alignItems: "center", justifyContent: "center", bgcolor: "grey.50" }}>
        <CircularProgress />
      </Box>
    }>
      <LoginRedirectHandler />
    </Suspense>
  );
}
