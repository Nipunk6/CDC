"use client";

import { Suspense } from "react";
import { usePathname, useRouter, useSearchParams } from "next/navigation";
import { Box, Tab, Tabs } from "@mui/material";
import GroupIcon from "@mui/icons-material/Group";

import PageHeader from "@/components/shared/pageheader";
import UsersDirectory from "@/components/admin/usersdirectory";
import RolesTab from "@/components/admin/access/rolestab";

// Users & Roles (super admins): staff accounts with their role and access, and the roles themselves.
function UsersAndRoles() {
  const router = useRouter();
  const pathname = usePathname();
  const searchParams = useSearchParams();
  const tab = searchParams.get("tab") === "roles" ? "roles" : "users";

  return (
    <>
      <PageHeader icon={<GroupIcon />} title="Users & Roles" subtitle="CDC staff who can sign in to the admin portal, and what each role lets them do." backHref="/admin/settings" backLabel="Back to Admin" />
      <Tabs value={tab} onChange={(_e, value) => router.replace(value === "roles" ? `${pathname}?tab=roles` : pathname)} sx={{ mb: 2, borderBottom: 1, borderColor: "divider" }}>
        <Tab value="users" label="Users" />
        <Tab value="roles" label="Roles" />
      </Tabs>
      <Box>{tab === "roles" ? <RolesTab /> : <UsersDirectory />}</Box>
    </>
  );
}

export default function AdminUsersPage() {
  return (
    <Suspense>
      <UsersAndRoles />
    </Suspense>
  );
}
