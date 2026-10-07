import { redirect } from "next/navigation";

// The Users directory moved to /admin/users (Superset parity S8.2); this keeps old links working.
export default function ManageAdminsPage() {
  redirect("/admin/users");
}
