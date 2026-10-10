"use client";

import { signOut, useSession } from "next-auth/react";
import { Box, Button, Paper, Stack, Typography } from "@mui/material";
import LogoutIcon from "@mui/icons-material/Logout";
import SupervisorAccountIcon from "@mui/icons-material/SupervisorAccount";

const apiBase = process.env.NEXT_PUBLIC_API_URL ?? "http://localhost:8000/api";

// Phase 3 P0.2: the mentor role exists and can sign in; the mentor pages (mentees, resume queue) are built in the
// mentor milestone (P8) and will replace this holding page.
export default function MentorHomePage() {
  const { data: session } = useSession();

  const handleSignOut = async () => {
    try {
      // Revoke the API token as the other portals do; the session is cleared either way.
      await fetch(`${apiBase}/auth/logout`, {
        method: "POST",
        headers: { Accept: "application/json", Authorization: `Bearer ${session?.accessToken ?? ""}` },
      });
    } catch {
      // ignore
    }
    await signOut({ callbackUrl: "/auth/login/mentor" });
  };

  return (
    <Box sx={{ minHeight: "100vh", display: "flex", alignItems: "center", justifyContent: "center", bgcolor: "grey.50", p: 2 }}>
      <Paper sx={{ p: { xs: 3, sm: 5 }, maxWidth: 520, width: "100%" }}>
        <Stack spacing={2} alignItems="flex-start">
          <SupervisorAccountIcon color="primary" sx={{ fontSize: 40 }} />
          <Typography variant="h5" fontWeight={700} sx={{ textAlign: "left" }}>
            Mentor portal
          </Typography>
          <Typography color="text.secondary" sx={{ textAlign: "left" }}>
            You are signed in{session?.user?.name ? ` as ${session.user.name}` : ""}. Your mentees and their resumes will appear here once the CDC
            opens the mentor pages.
          </Typography>
          <Button variant="outlined" startIcon={<LogoutIcon />} onClick={handleSignOut}>
            Sign out
          </Button>
        </Stack>
      </Paper>
    </Box>
  );
}
