"use client";

import { useMemo, useState, Suspense } from "react";
import Link from "next/link";
import { useRouter, useSearchParams } from "next/navigation";
import { yupResolver } from "@hookform/resolvers/yup";
import {
  Alert,
  Box,
  Button,
  CircularProgress,
  IconButton,
  InputAdornment,
  Paper,
  Stack,
  TextField,
  Typography,
} from "@mui/material";
import { useForm } from "react-hook-form";
import * as yup from "yup";
import VisibilityIcon from "@mui/icons-material/Visibility";
import VisibilityOffIcon from "@mui/icons-material/VisibilityOff";

const apiBase = process.env.NEXT_PUBLIC_API_URL?.replace(/\/$/, "") ?? "http://127.0.0.1:8000/api";

type ResetPasswordFormValues = {
  password: string;
  password_confirmation: string;
};

const schema = yup.object({
  password: yup
    .string()
    .min(8, "Password must be at least 8 characters")
    .matches(/[a-z]/, "Password must contain at least one lowercase letter")
    .matches(/[A-Z]/, "Password must contain at least one uppercase letter")
    .matches(/[0-9]/, "Password must contain at least one number")
    .required("Password is required"),
  password_confirmation: yup
    .string()
    .oneOf([yup.ref("password")], "Passwords do not match")
    .required("Please confirm your password"),
});

function ResetPasswordHandler() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const token = searchParams.get("token") ?? "";
  const email = searchParams.get("email") ?? "";

  const hasValidQuery = useMemo(() => token.length > 0 && email.length > 0, [token, email]);

  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);
  const [showPassword, setShowPassword] = useState(false);
  const [showConfirmPassword, setShowConfirmPassword] = useState(false);

  const {
    register,
    handleSubmit,
    formState: { errors, isSubmitting },
  } = useForm<ResetPasswordFormValues>({
    resolver: yupResolver(schema),
  });

  const onSubmit = async (values: ResetPasswordFormValues) => {
    setError(null);
    setSuccess(null);

    if (!hasValidQuery) {
      setError("Invalid or missing reset token. Please request a new reset link.");
      return;
    }

    const response = await fetch(`${apiBase}/auth/reset-password`, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
      },
      body: JSON.stringify({
        token,
        email,
        password: values.password,
        password_confirmation: values.password_confirmation,
      }),
    });

    const data = await response.json().catch(() => ({}));

    if (!response.ok) {
      setError(data.message ?? "Unable to reset password. The link may have expired.");
      return;
    }

    setSuccess(data.message ?? "Password reset successful.");
    setTimeout(() => {
      router.replace("/auth/login");
    }, 1200);
  };

  return (
    <Box
      sx={{
        minHeight: "100vh",
        display: "flex",
        alignItems: "center",
        justifyContent: "center",
        p: 3,
        bgcolor: "grey.50",
      }}
    >
      <Paper
        elevation={0}
        sx={{
          width: "100%",
          maxWidth: 520,
          p: { xs: 3, sm: 5 },
          borderRadius: 3,
          border: "1px solid",
          borderColor: "divider",
        }}
      >
        <Stack spacing={3}>
          <Box>
            <Typography variant="h4" fontWeight={700} gutterBottom>
              Set New Password
            </Typography>
            <Typography color="text.secondary">
              Choose a new password for <strong>{email || "your account"}</strong>.
            </Typography>
          </Box>

          {!hasValidQuery && (
            <Alert severity="warning">
              Invalid or expired reset link. Request a new link to continue.
            </Alert>
          )}

          {error && <Alert severity="error">{error}</Alert>}
          {success && <Alert severity="success">{success}</Alert>}

          <Box component="form" noValidate onSubmit={handleSubmit(onSubmit)}>
            <Stack spacing={3}>
              <TextField
                label="New Password"
                type={showPassword ? "text" : "password"}
                fullWidth
                placeholder="Enter a strong password"
                {...register("password")}
                error={Boolean(errors.password)}
                helperText={errors.password?.message}
                InputProps={{
                  sx: { borderRadius: 2 },
                  endAdornment: (
                    <InputAdornment position="end">
                      <IconButton
                        onClick={() => setShowPassword(!showPassword)}
                        edge="end"
                      >
                        {showPassword ? <VisibilityOffIcon /> : <VisibilityIcon />}
                      </IconButton>
                    </InputAdornment>
                  ),
                }}
              />

              <TextField
                label="Confirm New Password"
                type={showConfirmPassword ? "text" : "password"}
                fullWidth
                placeholder="Re-enter your new password"
                {...register("password_confirmation")}
                error={Boolean(errors.password_confirmation)}
                helperText={errors.password_confirmation?.message}
                InputProps={{
                  sx: { borderRadius: 2 },
                  endAdornment: (
                    <InputAdornment position="end">
                      <IconButton
                        onClick={() => setShowConfirmPassword(!showConfirmPassword)}
                        edge="end"
                      >
                        {showConfirmPassword ? <VisibilityOffIcon /> : <VisibilityIcon />}
                      </IconButton>
                    </InputAdornment>
                  ),
                }}
              />

              <Button
                type="submit"
                variant="contained"
                size="large"
                disabled={isSubmitting || !hasValidQuery}
                sx={{ py: 1.4, borderRadius: 2, fontWeight: 600 }}
              >
                {isSubmitting ? "Updating password..." : "Reset Password"}
              </Button>
            </Stack>
          </Box>

          <Typography variant="body2" textAlign="center">
            <Link href="/auth/login">Back to Sign In</Link>
          </Typography>
        </Stack>
      </Paper>
    </Box>
  );
}

export default function ResetPasswordPage() {
  return (
    <Suspense fallback={
      <Box sx={{ minHeight: "100vh", display: "flex", alignItems: "center", justifyContent: "center", bgcolor: "grey.50" }}>
        <CircularProgress />
      </Box>
    }>
      <ResetPasswordHandler />
    </Suspense>
  );
}
