"use client";

import { useEffect, useMemo, useState } from "react";
import { usePathname } from "next/navigation";
import { signOut, useSession } from "next-auth/react";
import { adminApi } from "@/lib/adminapi";
import { adminNav } from "@/lib/nav/admin";
import { initials } from "@/lib/initials";
import { useBranding } from "@/lib/branding";
import AppShell from "@/components/shared/appshell";
import ShellFooter from "@/components/shared/shellfooter";
import StudentSearch from "@/components/admin/studentsearch";

const handleSignOut = async () => {
  try {
    await adminApi("/auth/logout", { method: "POST" }); // revoke the Sanctum token server-side
  } catch {
    // ignore - the client session is cleared regardless
  }
  await signOut({ callbackUrl: "/auth/login/admin" });
};

// PU.3: a thin wrapper. Layout and navigation are the shared AppShell; the admin's pages are listed in lib/nav/admin.js.
export default function AdminShell({
  children,
}: {
  children: React.ReactNode;
}) {
  const pathname = usePathname();
  const { data: session } = useSession();
  const [unreadNotifications, setUnreadNotifications] = useState(0);
  const branding = useBranding();

  const navSections = useMemo(() => adminNav({ isSuperAdmin: Boolean(session?.user?.isSuperAdmin) }), [session?.user?.isSuperAdmin]);

  useEffect(() => {
    const run = async () => {
      try {
        const response = await adminApi<{ unread_count: number }>(`/auth/notifications?ts=${Date.now()}`);
        setUnreadNotifications(response.unread_count ?? 0);
      } catch {
        // Keep navigation resilient even if notification fetch fails.
        setUnreadNotifications(0);
      }
    };

    const handleNotificationsUpdated = (event: Event) => {
      const customEvent = event as CustomEvent<number>;
      if (typeof customEvent.detail === "number") {
        setUnreadNotifications(customEvent.detail);
        return;
      }

      void run();
    };

    window.addEventListener("admin-notifications-updated", handleNotificationsUpdated);

    void run();

    return () => {
      window.removeEventListener("admin-notifications-updated", handleNotificationsUpdated);
    };
  }, [pathname]);

  const name = session?.user?.name ?? "Admin";

  return (
    <AppShell
      portal="admin"
      navSections={navSections}
      brand={{ title: "CDC Admin", subtitle: branding?.display_name ?? "IIT (ISM) Dhanbad", homeHref: "/admin", branding }}
      user={{ name, detail: session?.user?.email ?? "", initials: initials(name, "A"), profileHref: "/admin/settings?tab=account" }}
      onSignOut={handleSignOut}
      notificationsCount={unreadNotifications}
      notificationsHref="/admin/notifications"
      utilities={<StudentSearch />}
      footer={<ShellFooter />}
    >
      {children}
    </AppShell>
  );
}
