"use client";

import { useState } from "react";
import Link from "next/link";
import { yupResolver } from "@hookform/resolvers/yup";
import {
  Alert,
  Box,
  Button,
  Paper,
  Stack,
  TextField,
  Typography,
} from "@mui/material";
import { useForm } from "react-hook-form";
import * as yup from "yup";

const apiBase = process.env.NEXT_PUBLIC_API_URL?.replace(/\/$/, "") ?? "http://127.0.0.1:8000/api";

type ForgotPasswordFormValues = {
  email: string;
};

const schema = yup.object({
  email: yup.string().email("Enter a valid email").required("Email is required"),
});

export default function ForgotPasswordPage() {
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);

  const {
    register,
    handleSubmit,
    formState: { errors, isSubmitting },
  } = useForm<ForgotPasswordFormValues>({
    resolver: yupResolver(schema),
  });

  const onSubmit = async (values: ForgotPasswordFormValues) => {
    setError(null);
    setSuccess(null);

    const response = await fetch(`${apiBase}/auth/forgot-password`, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
      },
      body: JSON.stringify(values),
    });

    const data = await response.json().catch(() => ({}));

    if (!response.ok) {
      setError(data.message ?? "Could not send reset link. Please try again.");
      return;
    }

    setSuccess(
      data.message ??
        "If an account exists for this email, a reset link has been sent."
    );
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
          maxWidth: 480,
          p: { xs: 3, sm: 5 },
          borderRadius: 3,
          border: "1px solid",
          borderColor: "divider",
        }}
      >
        <Stack spacing={3}>
          <Box>
            <Typography variant="h4" fontWeight={700} gutterBottom>
              Forgot Password
            </Typography>
            <Typography color="text.secondary">
              Enter your registered email and we will send a secure, time-limited reset link.
            </Typography>
          </Box>

          {error && <Alert severity="error">{error}</Alert>}
          {success && <Alert severity="success">{success}</Alert>}

          <Box component="form" noValidate onSubmit={handleSubmit(onSubmit)}>
            <Stack spacing={3}>
              <TextField
                label="Email Address"
                type="email"
                fullWidth
                placeholder="company@example.com"
                {...register("email")}
                error={Boolean(errors.email)}
                helperText={errors.email?.message}
                InputProps={{ sx: { borderRadius: 2 } }}
              />

              <Button
                type="submit"
                variant="contained"
                size="large"
                disabled={isSubmitting}
                sx={{ py: 1.4, borderRadius: 2, fontWeight: 600 }}
              >
                {isSubmitting ? "Sending link..." : "Send Reset Link"}
              </Button>
            </Stack>
          </Box>

          <Typography variant="body2" textAlign="center">
            Remembered your password?{" "}
            <Link href="/auth/login">Back to Sign In</Link>
          </Typography>
        </Stack>
      </Paper>
    </Box>
  );
}
