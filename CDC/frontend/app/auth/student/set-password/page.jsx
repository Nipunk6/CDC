"use client";

import { Suspense } from "react";

import SetPasswordForm from "@/components/auth/setpasswordform";

// Student variant of /auth/reset-password: identical API call, but lands on the roll-number login (D53).
export default function StudentSetPasswordPage() {
  return (
    <Suspense fallback={null}>
      <SetPasswordForm
        loginHref="/auth/login/student"
        accountLabel="student account"
        successText="Password set. Sign in with your roll number."
        incompleteText="This link is incomplete. Use Forgot password on the student login page to get a new one."
        backLabel="Back to student sign in"
      />
    </Suspense>
  );
}
