"use client";

import Link from "next/link";
import { Avatar, Box, Button, Paper, Stack, Typography, alpha } from "@mui/material";
import ArrowBackIcon from "@mui/icons-material/ArrowBack";

// The Phase 1 maroon gradient page header, shared by the Phase 2 pages.
export default function PageHeader({ icon, title, subtitle, backHref, backLabel = "Back", actions }) {
  return (
    <Paper
      sx={{
        p: 2,
        mb: 3,
        background: (theme) =>
          `linear-gradient(135deg, ${theme.palette.primary.main} 0%, ${theme.palette.primary.dark} 100%)`,
        color: "white",
        borderRadius: 2,
      }}
    >
      <Stack
        direction={{ xs: "column", md: "row" }}
        justifyContent="space-between"
        alignItems={{ md: "center" }}
        spacing={2}
      >
        <Stack direction="row" spacing={2} alignItems="center" sx={{ minWidth: 0 }}>
          {icon && <Avatar sx={{ width: 48, height: 48, bgcolor: "white", color: "primary.main" }}>{icon}</Avatar>}
          <Box sx={{ minWidth: 0 }}>
            <Typography variant="h5" fontWeight={700} sx={{ wordBreak: "break-word" }}>
              {title}
            </Typography>
            {subtitle && (
              <Typography variant="body2" sx={{ opacity: 0.9 }}>
                {subtitle}
              </Typography>
            )}
          </Box>
        </Stack>
        <Stack direction="row" spacing={1} flexWrap="wrap" useFlexGap>
          {actions}
          {backHref && (
            <Button
              component={Link}
              href={backHref}
              variant="outlined"
              startIcon={<ArrowBackIcon />}
              sx={{
                color: "white",
                borderColor: "white",
                "&:hover": { borderColor: "white", bgcolor: alpha("#fff", 0.1) },
              }}
            >
              {backLabel}
            </Button>
          )}
        </Stack>
      </Stack>
    </Paper>
  );
}
