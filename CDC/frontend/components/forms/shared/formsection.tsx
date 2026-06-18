"use client";

import { ReactNode } from "react";
import {
  Box,
  Card,
  CardContent,
  Divider,
  Stack,
  Typography,
  alpha,
} from "@mui/material";

interface FormSectionProps {
  title: string;
  subtitle?: string;
  icon?: ReactNode;
  children: ReactNode;
  required?: boolean;
}

export default function FormSection({
  title,
  subtitle,
  icon,
  children,
  required,
}: FormSectionProps) {
  return (
    <Card
      sx={{
        mb: 3,
        border: "1px solid",
        borderColor: "divider",
        boxShadow: (theme) => `0 2px 8px ${alpha(theme.palette.primary.main, 0.08)}`,
      }}
    >
      <Box
        sx={{
          px: 3,
          py: 2,
          background: (theme) =>
            `linear-gradient(135deg, ${alpha(theme.palette.primary.main, 0.05)} 0%, ${alpha(theme.palette.primary.main, 0.02)} 100%)`,
          borderBottom: "1px solid",
          borderColor: "divider",
        }}
      >
        <Stack direction="row" alignItems="center" spacing={1.5}>
          {icon && (
            <Box
              sx={{
                color: "primary.main",
                display: "flex",
                alignItems: "center",
              }}
            >
              {icon}
            </Box>
          )}
          <Box>
            <Stack direction="row" alignItems="center" spacing={1}>
              <Typography variant="h6" fontWeight={600}>
                {title}
              </Typography>
              {required && (
                <Typography color="error" component="span" fontWeight={500}>
                  *
                </Typography>
              )}
            </Stack>
            {subtitle && (
              <Typography variant="body2" color="text.secondary">
                {subtitle}
              </Typography>
            )}
          </Box>
        </Stack>
      </Box>
      <CardContent sx={{ p: 3 }}>{children}</CardContent>
    </Card>
  );
}
