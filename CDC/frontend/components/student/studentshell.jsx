"use client";

import { useEffect, useState } from "react";
import { usePathname } from "next/navigation";
import { signOut, useSession } from "next-auth/react";
import { Box, Button, Paper, Typography } from "@mui/material";
import BlockIcon from "@mui/icons-material/Block";

import { SESSION_EXPIRED_EVENT, SUSPENDED_EVENT, studentApi } from "@/lib/studentapi";
import { studentNav } from "@/lib/nav/student";
import { initials } from "@/lib/initials";
import { useBranding } from "@/lib/branding";
import AppShell from "@/components/shared/appshell";
import ShellFooter from "@/components/shared/shellfooter";

const navSections = studentNav();

const handleSignOut = async () => {
  try {
    await studentApi("/auth/logout", { method: "POST" }); // revoke the Sanctum token server-side
  } catch {
    // ignore - the client session is cleared regardless
  }
  await signOut({ callbackUrl: "/auth/login/student" });
};

// PU.3: a thin wrapper. Layout and navigation are the shared AppShell; the student's pages are in lib/nav/student.js.
export default function StudentShell({ children }) {
  const pathname = usePathname();
  const { data: session } = useSession();
  const [unread, setUnread] = useState(0);
  const [suspended, setSuspended] = useState(false);
  const branding = useBranding();

  // Any student API call that meets a suspended account or a dead token lands here (QA F-020).
  useEffect(() => {
    const onSuspended = () => setSuspended(true);
    const onExpired = () => void signOut({ callbackUrl: "/auth/login/student" });
    window.addEventListener(SUSPENDED_EVENT, onSuspended);
    window.addEventListener(SESSION_EXPIRED_EVENT, onExpired);
    return () => {
      window.removeEventListener(SUSPENDED_EVENT, onSuspended);
      window.removeEventListener(SESSION_EXPIRED_EVENT, onExpired);
    };
  }, []);

  useEffect(() => {
    const run = async () => {
      try {
        const response = await studentApi(`/auth/notifications?ts=${Date.now()}`);
        setUnread(response.unread_count ?? 0);
      } catch {
        setUnread(0);
      }
    };

    const handleUpdated = (event) => {
      if (typeof event.detail === "number") {
        setUnread(event.detail);
        return;
      }
      void run();
    };

    window.addEventListener("student-notifications-updated", handleUpdated);
    void run();
    return () => window.removeEventListener("student-notifications-updated", handleUpdated);
  }, [pathname]);

  if (suspended) {
    return (
      <Box sx={{ minHeight: "100vh", display: "grid", placeItems: "center", bgcolor: "background.default", px: 2 }}>
        <Paper variant="outlined" sx={{ p: { xs: 3, sm: 4 }, maxWidth: 440, textAlign: "center" }} role="alert">
          <BlockIcon color="error" sx={{ fontSize: 48, mb: 1 }} />
          <Typography variant="h5" fontWeight={700} gutterBottom>
            Account suspended
          </Typography>
          <Typography color="text.secondary" sx={{ mb: 3 }}>
            Your account has been suspended by the CDC. Contact the CDC office to restore access.
          </Typography>
          <Button variant="contained" onClick={() => void signOut({ callbackUrl: "/auth/login/student" })}>
            Logout
          </Button>
        </Paper>
      </Box>
    );
  }

  const name = session?.user?.name ?? "Student";

  return (
    <AppShell
      portal="student"
      navSections={navSections}
      brand={{ title: "CDC Placement Portal", subtitle: branding?.display_name ?? "IIT (ISM) Dhanbad", homeHref: "/student", branding }}
      user={{ name, detail: session?.user?.rollNo ?? "", initials: initials(name, "S"), profileHref: "/student/profile" }}
      onSignOut={handleSignOut}
      notificationsCount={unread}
      notificationsHref="/student/notifications"
      footer={<ShellFooter />}
    >
      {children}
    </AppShell>
  );
}
