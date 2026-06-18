"use client";

import { useEffect, Suspense } from "react";
import { useRouter, useSearchParams } from "next/navigation";
import { Box, CircularProgress } from "@mui/material";

function LoginRedirectHandler() {
  const router = useRouter();
  const searchParams = useSearchParams();

  useEffect(() => {
    const callbackUrl = searchParams.get("callbackUrl") || "";
    const queryStr = searchParams.toString();
    const query = queryStr ? `?${queryStr}` : "";

    if (callbackUrl.toLowerCase().includes("/admin")) {
      router.replace(`/auth/login/admin${query}`);
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
