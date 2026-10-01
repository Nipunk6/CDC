"use client";

import { Suspense, useState } from "react";
import Link from "next/link";
import { useRouter, useSearchParams } from "next/navigation";
import { Alert, Box, Button, CircularProgress, Paper, Stack, TextField, Typography } from "@mui/material";

const apiBase = process.env.NEXT_PUBLIC_API_URL?.replace(/\/$/, "") ?? "http://127.0.0.1:8000/api";

// Same rules as the Phase 1 reset page and AuthController@resetPassword.
const passwordProblem = (value) => {
  if (value.length < 8) return "Password must be at least 8 characters.";
  if (!/[a-z]/.test(value)) return "Password must contain at least one lowercase letter.";
  if (!/[A-Z]/.test(value)) return "Password must contain at least one uppercase letter.";
  if (!/[0-9]/.test(value)) return "Password must contain at least one number.";
  return null;
};

// Student variant of /auth/reset-password: identical API call, but lands on the roll-number login (D53).
function SetPasswordForm() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const token = searchParams.get("token") ?? "";
  const email = searchParams.get("email") ?? "";

  const [password, setPassword] = useState("");
  const [confirm, setConfirm] = useState("");
  const [error, setError] = useState(null);
  const [success, setSuccess] = useState(null);
  const [busy, setBusy] = useState(false);

  const submit = async (event) => {
    event.preventDefault();
    setError(null);

    if (!token || !email) {
      setError("This link is incomplete. Use Forgot password on the student login page to get a new one.");
      return;
    }
    const problem = passwordProblem(password);
    if (problem) {
      setError(problem);
      return;
    }
    if (password !== confirm) {
      setError("Passwords do not match.");
      return;
    }

    setBusy(true);
    try {
      const response = await fetch(`${apiBase}/auth/reset-password`, {
        method: "POST",
        headers: { "Content-Type": "application/json", Accept: "application/json" },
        body: JSON.stringify({ token, email, password, password_confirmation: confirm }),
      });
      const data = await response.json().catch(() => ({}));
      if (!response.ok) {
        setError(data.message ?? "Unable to set the password. The link may have expired.");
        return;
      }
      setSuccess("Password set. Sign in with your roll number.");
      setTimeout(() => router.replace("/auth/login/student"), 1200);
    } finally {
      setBusy(false);
    }
  };

  return (
    <Box sx={{ minHeight: "100vh", display: "flex", alignItems: "center", justifyContent: "center", p: 2, bgcolor: "grey.50" }}>
      <Paper component="form" onSubmit={submit} elevation={0} sx={{ width: "100%", maxWidth: 460, p: { xs: 3, sm: 5 }, borderRadius: 3, border: "1px solid", borderColor: "divider" }}>
        <Stack spacing={2.5}>
          <Box>
            <Typography variant="h5" fontWeight={700} color="primary.main">
              Set your password
            </Typography>
            <Typography variant="body2" color="text.secondary">
              IIT (ISM) CDC Placement Portal — student account {email ? `(${email})` : ""}
            </Typography>
          </Box>
          {error && <Alert severity="error">{error}</Alert>}
          {success && <Alert severity="success">{success}</Alert>}
          <TextField label="New password" type="password" fullWidth value={password} onChange={(e) => setPassword(e.target.value)} helperText="At least 8 characters with upper-case, lower-case and a number." />
          <TextField label="Confirm password" type="password" fullWidth value={confirm} onChange={(e) => setConfirm(e.target.value)} />
          <Button type="submit" variant="contained" size="large" disabled={busy || Boolean(success)}>
            {busy ? <CircularProgress size={22} color="inherit" /> : "Set Password"}
          </Button>
          <Typography variant="body2" textAlign="center">
            <Link href="/auth/login/student">Back to student sign in</Link>
          </Typography>
        </Stack>
      </Paper>
    </Box>
  );
}

export default function StudentSetPasswordPage() {
  return (
    <Suspense fallback={null}>
      <SetPasswordForm />
    </Suspense>
  );
}
