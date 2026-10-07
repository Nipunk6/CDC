"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import { Alert, Button, Stack } from "@mui/material";

import { adminApi } from "@/lib/adminapi";

/**
 * "You have N profile update requests pending for approval" (Superset parity S4.7): pending branch changes plus
 * pending resumes, with links to both queues. Renders nothing when there is nothing pending.
 */
export default function PendingRequestsBanner({ sx }) {
  const [counts, setCounts] = useState(null);

  useEffect(() => {
    let cancelled = false;
    adminApi("/admin/students/pending-requests")
      .then((response) => {
        if (!cancelled) setCounts(response);
      })
      .catch(() => {
        // the banner is a hint; the queues stay reachable from the menu
      });
    return () => {
      cancelled = true;
    };
  }, []);

  if (!counts || !counts.total) return null;

  return (
    <Alert
      severity="info"
      sx={{ mb: 2, "& .MuiAlert-message": { width: "100%" }, ...sx }}
    >
      <Stack direction={{ xs: "column", sm: "row" }} spacing={1} alignItems={{ sm: "center" }} justifyContent="space-between">
        <span>
          You have <strong>{counts.total}</strong> profile update {counts.total === 1 ? "request" : "requests"} pending for approval.
        </span>
        <Stack direction="row" spacing={1} flexWrap="wrap" useFlexGap>
          {counts.branch_changes > 0 && (
            <Button component={Link} href="/admin/branch-changes" size="small" variant="outlined">
              Branch Changes ({counts.branch_changes})
            </Button>
          )}
          {counts.resumes > 0 && (
            <Button component={Link} href="/admin/resumes" size="small" variant="outlined">
              Resumes ({counts.resumes})
            </Button>
          )}
        </Stack>
      </Stack>
    </Alert>
  );
}
