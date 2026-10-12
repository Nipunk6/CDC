"use client";

import { signOut, useSession } from "next-auth/react";

import { mentorNav } from "@/lib/nav/mentor";
import { initials } from "@/lib/initials";
import { useBranding } from "@/lib/branding";
import AppShell from "@/components/shared/appshell";
import ShellFooter from "@/components/shared/shellfooter";

const apiBase = process.env.NEXT_PUBLIC_API_URL ?? "http://localhost:8000/api";
const navSections = mentorNav();

// The mentor portal's shell (PU.3). Mentor pages and their API helper arrive with the mentor milestone (P8).
export default function MentorShell({ children }) {
  const { data: session } = useSession();
  const branding = useBranding();
  const name = session?.user?.name ?? "Mentor";

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
    <AppShell
      portal="mentor"
      navSections={navSections}
      brand={{ title: "CDC Mentor", subtitle: branding?.display_name ?? "IIT (ISM) Dhanbad", homeHref: "/mentor", branding }}
      user={{ name, detail: session?.user?.email ?? "", initials: initials(name, "M") }}
      onSignOut={handleSignOut}
      footer={<ShellFooter />}
    >
      {children}
    </AppShell>
  );
}
