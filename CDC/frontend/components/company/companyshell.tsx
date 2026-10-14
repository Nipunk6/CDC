"use client";

import { usePathname } from "next/navigation";
import { signOut, useSession } from "next-auth/react";
import { Box, Container } from "@mui/material";
import { companyApi } from "@/lib/companyapi";
import { companyNav } from "@/lib/nav/company";
import { initials } from "@/lib/initials";
import { useBranding } from "@/lib/branding";
import AppShell from "@/components/shared/appshell";
import ShellFooter from "@/components/shared/shellfooter";

const handleSignOut = async () => {
  try {
    await companyApi("/auth/logout", { method: "POST" }); // revoke the Sanctum token server-side
  } catch {
    // ignore - the client session is cleared regardless
  }
  await signOut({ callbackUrl: "/auth/login/recruiter" });
};

const navSections = companyNav();

// PU.3: a thin wrapper. Layout and navigation are the shared AppShell; the recruiter's pages are in lib/nav/company.js.
export default function CompanyShell({
  children,
}: {
  children: React.ReactNode;
}) {
  const pathname = usePathname();
  const { data: session } = useSession();
  const branding = useBranding();

  // Registration is a public page: no sidebar, because nobody is signed in yet.
  if (pathname === "/company/register") {
    return (
      <Box sx={{ minHeight: "100vh", display: "flex", flexDirection: "column", bgcolor: "background.default" }}>
        <Container maxWidth="xl" sx={{ py: 4, flex: 1 }}>
          {children}
        </Container>
        <Container maxWidth="xl" component="footer" sx={{ py: 2 }}>
          <ShellFooter />
        </Container>
      </Box>
    );
  }

  const name = session?.user?.name ?? "Recruiter";

  return (
    <AppShell
      portal="company"
      navSections={navSections}
      brand={{ title: "CDC Recruiter", subtitle: branding?.display_name ?? "IIT (ISM) Dhanbad", homeHref: "/company", branding }}
      user={{ name, detail: session?.user?.email ?? "", initials: initials(name, "R"), profileHref: "/company/profile" }}
      onSignOut={handleSignOut}
      notificationsHref="/company/notifications"
      footer={<ShellFooter />}
    >
      {children}
    </AppShell>
  );
}
