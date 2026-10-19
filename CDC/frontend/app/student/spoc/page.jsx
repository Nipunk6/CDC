"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import {
  Alert,
  Avatar,
  Box,
  Card,
  CardContent,
  Chip,
  Grid2 as Grid,
  LinearProgress,
  Link as MuiLink,
  Stack,
  Typography,
} from "@mui/material";

import PageHeader from "@/components/shared/pageheader";
import { studentApi } from "@/lib/studentapi";
import { formatDateTime, postingStatusLabel, statusColor } from "@/lib/format";

/**
 * Student → SPOC (P10.2): for an SCPT member, every company and role they are SPOC for, with the current HR details.
 * A student who is not on any SCPT sees only a short note.
 */
export default function StudentSpocPage() {
  const [data, setData] = useState(null);
  const [error, setError] = useState(null);

  useEffect(() => {
    studentApi("/student/spoc")
      .then(setData)
      .catch((e) => setError(e instanceof Error ? e.message : "Could not load your SPOC companies."));
  }, []);

  return (
    <>
      <PageHeader title="SPOC" subtitle="The companies and roles you are the student point of contact for." />
      {error && (
        <Alert severity="error" sx={{ mb: 2 }}>
          {error}
        </Alert>
      )}
      {!data && !error && <LinearProgress />}
      {data && !data.is_scpt && (
        <Card>
          <CardContent>
            <Typography color="text.secondary">You are not an SCPT member, so there is nothing here for you.</Typography>
          </CardContent>
        </Card>
      )}
      {data?.is_scpt && (
        <Stack spacing={2}>
          <Stack direction="row" spacing={1} flexWrap="wrap" useFlexGap>
            {data.memberships.map((m) => (
              <Chip
                key={m.placement_cycle?.id}
                color="primary"
                variant="outlined"
                label={`SCPT · ${m.placement_cycle?.name ?? ""}${m.designation ? ` · ${m.designation}` : ""}`}
              />
            ))}
          </Stack>
          {data.assignments.length === 0 && (
            <Card>
              <CardContent>
                <Typography color="text.secondary">The CDC has not made you SPOC for any company yet. You will get a mail when it does.</Typography>
              </CardContent>
            </Card>
          )}
          <Grid container spacing={2}>
            {data.assignments.map((a) => (
              <Grid key={a.id} size={{ xs: 12, md: 6 }}>
                <Card sx={{ height: "100%" }}>
                  <CardContent>
                    <Stack direction="row" spacing={2} alignItems="center" sx={{ mb: 2 }}>
                      <Avatar
                        src={a.company?.logo_url ?? undefined}
                        variant="rounded"
                        sx={{ width: 48, height: 48, bgcolor: "background.paper", color: "primary.main", border: 1, borderColor: "divider" }}
                      >
                        {a.company?.name?.[0]}
                      </Avatar>
                      <Box sx={{ minWidth: 0 }}>
                        <Typography variant="subtitle1" fontWeight={700} sx={{ wordBreak: "break-word" }}>
                          {a.company?.name}
                        </Typography>
                        <MuiLink component={Link} href={`/student/postings/${a.job_posting_id}`} underline="hover">
                          {a.role}
                        </MuiLink>
                      </Box>
                    </Stack>
                    <Stack direction="row" spacing={1} flexWrap="wrap" useFlexGap sx={{ mb: 2 }}>
                      <Chip size="small" color={statusColor(a.status)} variant="outlined" label={postingStatusLabel(a.status)} />
                      <Chip size="small" variant="outlined" label={a.type === "internship" ? "Internship" : "Full Time"} />
                      {a.placement_cycle?.name && <Chip size="small" variant="outlined" label={a.placement_cycle.name} />}
                    </Stack>
                    {a.application_deadline && (
                      <Typography variant="body2" color="text.secondary" sx={{ mb: 2 }}>
                        Apply by {formatDateTime(a.application_deadline)}
                      </Typography>
                    )}
                    <Typography variant="subtitle2" fontWeight={700} gutterBottom>
                      HR details
                    </Typography>
                    <Detail label="Name" value={a.hr?.name} />
                    <Detail label="Designation" value={a.hr?.designation} />
                    <Detail label="Email" value={a.hr?.email} href={a.hr?.email ? `mailto:${a.hr.email}` : null} />
                    <Detail label="Phone" value={a.hr?.phone} href={a.hr?.phone ? `tel:${a.hr.phone}` : null} />
                    {a.hr?.alt_phone && <Detail label="Alternate phone" value={a.hr.alt_phone} href={`tel:${a.hr.alt_phone}`} />}
                    {a.co_spocs.length > 0 && (
                      <Typography variant="body2" color="text.secondary" sx={{ mt: 2 }}>
                        Other SPOCs: {a.co_spocs.map((c) => `${c.name} (${c.phone || c.email || c.roll_no})`).join(", ")}
                      </Typography>
                    )}
                  </CardContent>
                </Card>
              </Grid>
            ))}
          </Grid>
        </Stack>
      )}
    </>
  );
}

function Detail({ label, value, href }) {
  return (
    <Stack direction="row" spacing={1} sx={{ py: 0.25 }}>
      <Typography variant="body2" color="text.secondary" sx={{ width: 120, flexShrink: 0 }}>
        {label}
      </Typography>
      <Typography variant="body2" sx={{ wordBreak: "break-word", minWidth: 0 }}>
        {value ? href ? <MuiLink href={href} underline="hover">{value}</MuiLink> : value : "—"}
      </Typography>
    </Stack>
  );
}
