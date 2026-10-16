"use client";

import { Suspense } from "react";

import SetPasswordForm from "@/components/auth/setpasswordform";

// Staff invitation (staff roles): set the first password, then sign in on the admin login and complete the profile.
export default function StaffSetPasswordPage() {
  return (
    <Suspense fallback={null}>
      <SetPasswordForm
        loginHref="/auth/login/admin"
        accountLabel="CDC staff account"
        successText="Password set. Sign in with your email; the portal will then ask for your details."
        incompleteText="This link is incomplete. Ask a super admin to resend your invitation."
        backLabel="Back to admin sign in"
      />
    </Suspense>
  );
}
