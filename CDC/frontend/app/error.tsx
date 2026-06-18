"use client";

import { useEffect } from "react";
import { Box, Button, Stack, Typography } from "@mui/material";

export default function GlobalRouteError({
  error,
  reset,
}: {
  error: Error & { digest?: string };
  reset: () => void;
}) {
  useEffect(() => {
    console.error("Route error boundary caught an error:", error);
  }, [error]);

  return (
    <Box
      sx={{
        minHeight: "100vh",
        display: "flex",
        alignItems: "center",
        justifyContent: "center",
        p: 3,
        background: "linear-gradient(135deg, #f2f6fb 0%, #fff4e8 100%)",
      }}
    >
      <Stack spacing={2.5} sx={{ maxWidth: 560, textAlign: "center" }}>
        <Typography variant="h4" color="primary.main">
          Something went wrong
        </Typography>
        <Typography color="text.secondary">
          We could not complete this request. Please try again, or return to the
          dashboard.
        </Typography>
        <Stack direction={{ xs: "column", sm: "row" }} spacing={1.5}>
          <Button variant="contained" fullWidth onClick={reset}>
            Try Again
          </Button>
          <Button variant="outlined" fullWidth href="/">
            Go Home
          </Button>
        </Stack>
      </Stack>
    </Box>
  );
}
