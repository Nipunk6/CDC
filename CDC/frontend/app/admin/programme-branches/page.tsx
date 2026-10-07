import { redirect } from "next/navigation";

// Branch Manager lives in the Admin hub since fix L14 (components/admin/branchmanager.jsx); old links keep working.
export default function AdminProgrammeBranchesPage() {
  redirect("/admin/settings?tab=branch-manager");
}
