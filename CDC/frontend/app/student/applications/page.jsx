"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import {
  Alert,
  Avatar,
  Box,
  Button,
  Card,
  CardContent,
  Chip,
  LinearProgress,
  Paper,
  Stack,
  Tab,
  Tabs,
  Typography,
} from "@mui/material";

import RoundTrail from "@/components/student/roundtrail";
import { studentApi } from "@/lib/studentapi";
import { formatDateTime, formatMoney, statusColor, titleCase } from "@/lib/format";

export default function StudentApplicationsPage() {
  const [applications, setApplications] = useState(null);
  const [error, setError] = useState(null);
  const [view, setView] = useState("active");

  useEffect(() => {
    studentApi("/student/applications")
      .then((response) => setApplications(response.applications ?? []))
      .catch((e) => setError(e.message));
  }, []);

  if (error) return <Alert severity="error">{error}</Alert>;
  if (!applications) return <LinearProgress />;

  const rows = applications.filter((a) => (view === "active" ? a.status === "applied" : a.status === "withdrawn"));
  const unverified = applications.some((a) => a.status === "applied" && a.used_unverified_resume);

  return (
    <Stack spacing={3}>
      <Box>
        <Typography variant="h4" color="primary.main" fontWeight={700}>
          My Applications
        </Typography>
        <Typography color="text.secondary">Results appear here once the CDC publishes each round.</Typography>
      </Box>

      {unverified && (
        <Alert severity="warning">
          Some applications use a resume that is not verified yet. <Link href="/student/resumes">Get your resume verified ASAP.</Link>
        </Alert>
      )}

      <Tabs value={view} onChange={(_e, value) => setView(value)}>
        <Tab value="active" label={`Active (${applications.filter((a) => a.status === "applied").length})`} />
        <Tab value="withdrawn" label={`Withdrawn (${applications.filter((a) => a.status === "withdrawn").length})`} />
      </Tabs>

      {rows.length === 0 && (
        <Paper variant="outlined" sx={{ p: 5, textAlign: "center" }}>
          <Typography color="text.secondary" gutterBottom>
            {view === "active" ? "You have not applied anywhere yet." : "No withdrawn applications."}
          </Typography>
          {view === "active" && (
            <Button component={Link} href="/student/postings" variant="contained">
              Browse Job Profiles
            </Button>
          )}
        </Paper>
      )}

      <Stack spacing={2}>
        {rows.map((application) => (
          <Card key={application.id} variant="outlined">
            <CardContent>
              <Stack direction={{ xs: "column", sm: "row" }} spacing={2} justifyContent="space-between">
                <Stack direction="row" spacing={1.5} alignItems="center" sx={{ minWidth: 0 }}>
                  <Avatar src={application.posting.company?.logo_url ?? undefined} variant="rounded" sx={{ bgcolor: "primary.main" }}>
                    {application.posting.company?.name?.[0]}
                  </Avatar>
                  <Box sx={{ minWidth: 0 }}>
                    {application.posting.status === "cancelled" ? (
                      <Typography fontWeight={700} sx={{ wordBreak: "break-word" }}>
                        {application.posting.title}
                      </Typography>
                    ) : (
                      <Typography fontWeight={700} component={Link} href={`/student/postings/${application.posting.id}`} sx={{ color: "text.primary", wordBreak: "break-word" }}>
                        {application.posting.title}
                      </Typography>
                    )}
                    <Typography variant="body2" color="text.secondary">
                      {application.posting.company?.name} · applied {formatDateTime(application.applied_at)}
                    </Typography>
                  </Box>
                </Stack>
                <Stack direction="row" spacing={1} alignItems="flex-start" flexWrap="wrap" useFlexGap>
                  <Chip size="small" variant="outlined" color={statusColor(application.posting.status)} label={`Drive: ${titleCase(application.posting.status)}`} />
                  <Chip size="small" variant="outlined" label={`Resume: ${application.resume?.label ?? "—"}`} color={application.used_unverified_resume ? "warning" : "default"} />
                </Stack>
              </Stack>
              {application.offer && (
                <Alert severity="success" sx={{ mt: 2 }}>
                  🎉 Offer: {application.offer.label ?? titleCase(application.offer.offer_type)}
                  {application.offer.ctc_annual ? ` · ${formatMoney(application.offer.ctc_annual, application.offer.currency)} p.a.` : ""}
                  {application.offer.stipend_monthly ? ` · ${formatMoney(application.offer.stipend_monthly, application.offer.currency)}/month` : ""}
                </Alert>
              )}
              <Box sx={{ mt: 2 }}>
                <RoundTrail trail={application.trail} status={application.status} offer={application.offer} />
              </Box>
            </CardContent>
          </Card>
        ))}
      </Stack>
    </Stack>
  );
}
