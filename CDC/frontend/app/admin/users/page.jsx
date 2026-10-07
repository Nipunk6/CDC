"use client";

import GroupIcon from "@mui/icons-material/Group";

import PageHeader from "@/components/shared/pageheader";
import UsersDirectory from "@/components/admin/usersdirectory";

export default function AdminUsersPage() {
  return (
    <>
      <PageHeader icon={<GroupIcon />} title="Users" subtitle="CDC staff who can sign in to the admin portal." backHref="/admin/settings" backLabel="Back to Admin" />
      <UsersDirectory />
    </>
  );
}
