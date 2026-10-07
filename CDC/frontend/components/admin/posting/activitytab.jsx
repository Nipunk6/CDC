"use client";

import { useEffect, useState } from "react";
import { Alert, Box, LinearProgress, Stack, Typography } from "@mui/material";

import { adminApi } from "@/lib/adminapi";
import { formatDateTime } from "@/lib/format";

/**
 * Activity (Superset parity S6.7): the job profile's timeline from the audit log — opened, deadline changed,
 * eligibility edited, closed, reopened, stages published, results announced, offers edited.
 */
export default function ActivityTab({ posting }) {
  const [activity, setActivity] = useState(null);
  const [error, setError] = useState(null);

  useEffect(() => {
    let cancelled = false;
    adminApi(`/admin/postings/${posting.id}/activity`)
      .then((r) => !cancelled && setActivity(r.activity))
      .catch((e) => !cancelled && setError(e.message));
    return () => {
      cancelled = true;
    };
  }, [posting.id]);

  if (error) return <Alert severity="error">{error}</Alert>;
  if (!activity) return <LinearProgress />;
  if (activity.length === 0) {
    return (
      <Typography variant="body2" color="text.secondary">
        No activity recorded yet.
      </Typography>
    );
  }

  return (
    <Stack spacing={0}>
      {activity.map((item, index) => (
        <Stack key={item.id} direction="row" spacing={2}>
          <Box sx={{ display: "flex", flexDirection: "column", alignItems: "center" }}>
            <Box sx={{ width: 10, height: 10, borderRadius: "50%", bgcolor: "primary.main", mt: 0.75 }} />
            {index < activity.length - 1 && <Box sx={{ flex: 1, width: 2, bgcolor: "divider" }} />}
          </Box>
          <Box sx={{ pb: 2, minWidth: 0 }}>
            <Typography variant="body2" fontWeight={600}>
              {item.label}
              {item.stage ? ` · ${item.stage}` : ""}
            </Typography>
            <Typography variant="caption" color="text.secondary">
              {formatDateTime(item.at)} · {item.by}
            </Typography>
          </Box>
        </Stack>
      ))}
    </Stack>
  );
}
