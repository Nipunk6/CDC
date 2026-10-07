"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import {
  Alert,
  AlertTitle,
  Box,
  Button,
  Card,
  CardContent,
  Chip,
  Grid2 as Grid,
  LinearProgress,
  List,
  ListItemButton,
  ListItemText,
  Stack,
  Typography,
} from "@mui/material";
import DescriptionIcon from "@mui/icons-material/Description";

import PostingCard from "@/components/student/postingcard";
import RoundTrail from "@/components/student/roundtrail";
import { studentApi } from "@/lib/studentapi";
import { formatDateTime, formatMoney } from "@/lib/format";

const typeColors = { event: "secondary", deadline: "primary", round: "warning" };
const typeLabels = { event: "Event", deadline: "Deadline", round: "Stage" };

const greeting = () => {
  const hour = new Date().getHours();
  return hour < 12 ? "Good morning" : hour < 17 ? "Good afternoon" : "Good evening";
};

export default function StudentDashboardPage() {
  const [data, setData] = useState(null);
  const [error, setError] = useState(null);
  const [hello] = useState(greeting);

  useEffect(() => {
    studentApi("/student/dashboard")
      .then(setData)
      .catch((e) => setError(e.message));
  }, []);

  if (error) return <Alert severity="error">{error}</Alert>;
  if (!data) return <LinearProgress />;

  const { student, resumes } = data;

  return (
    <Stack spacing={3}>
      <Box>
        <Typography variant="h4" color="primary.main" fontWeight={700}>
          {hello}, {student.full_name?.split(" ")[0]}
        </Typography>
        <Typography color="text.secondary">
          {student.roll_no} · {student.branch} · Batch {student.graduating_batch}
        </Typography>
      </Box>

      {data.offers.length > 0 && (
        <Alert severity="success" icon={false} sx={{ fontSize: 16 }}>
          <AlertTitle>🎉 Congratulations!</AlertTitle>
          {data.offers.map((o) => (
            <div key={`${o.company}-${o.offer_type}`}>
              {o.company} — {o.label}
              {o.ctc_annual ? ` · ${formatMoney(o.ctc_annual, o.currency)} p.a.` : ""}
              {o.stipend_monthly ? ` · ${formatMoney(o.stipend_monthly, o.currency)}/month` : ""}
            </div>
          ))}
        </Alert>
      )}
      {data.active_blocks.length > 0 && (
        <Alert severity="info">
          {data.active_blocks.map((m) => (
            <div key={m}>{m}</div>
          ))}
        </Alert>
      )}
      {data.unverified_applications > 0 && (
        <Alert severity="warning" action={<Button component={Link} href="/student/resumes" color="inherit" size="small">My Resumes</Button>}>
          {data.unverified_applications} application(s) use a resume that is not verified. Get your resume verified ASAP.
        </Alert>
      )}

      <Grid container spacing={2}>
        <Grid size={{ xs: 12, md: 4 }}>
          <Card sx={{ height: "100%" }}>
            <CardContent>
              <Stack direction="row" spacing={1} alignItems="center" sx={{ mb: 1 }}>
                <DescriptionIcon color="primary" />
                <Typography variant="subtitle1" fontWeight={700}>
                  Profile & resumes
                </Typography>
              </Stack>
              <Typography variant="body2">CGPA: {student.current_cgpa ?? "—"}</Typography>
              <Stack direction="row" spacing={1} sx={{ my: 1 }} flexWrap="wrap" useFlexGap>
                <Chip size="small" color="success" variant="outlined" label={`${resumes.approved} verified`} />
                <Chip size="small" color="warning" variant="outlined" label={`${resumes.pending} pending`} />
                {resumes.rejected > 0 && <Chip size="small" color="error" variant="outlined" label={`${resumes.rejected} needs changes`} />}
              </Stack>
              {resumes.total === 0 && (
                <Typography variant="body2" color="error.main" gutterBottom>
                  Upload a resume before applying anywhere.
                </Typography>
              )}
              <Stack direction="row" spacing={1}>
                <Button size="small" component={Link} href="/student/resumes" variant="outlined">
                  Resumes
                </Button>
                <Button size="small" component={Link} href="/student/profile">
                  Profile
                </Button>
              </Stack>
            </CardContent>
          </Card>
        </Grid>
        <Grid size={{ xs: 6, md: 4 }}>
          <Card sx={{ height: "100%" }}>
            <CardContent>
              <Typography variant="body2" color="text.secondary">
                In progress
              </Typography>
              <Typography variant="h3" fontWeight={800} color="primary.main">
                {data.active_applications}
              </Typography>
              <Button size="small" component={Link} href="/student/applications">
                My Applications
              </Button>
            </CardContent>
          </Card>
        </Grid>
        <Grid size={{ xs: 6, md: 4 }}>
          <Card sx={{ height: "100%" }}>
            <CardContent>
              <Typography variant="body2" color="text.secondary">
                Open for you
              </Typography>
              <Typography variant="h3" fontWeight={800} color="secondary.main">
                {data.nudges_total ?? data.nudges.length}
              </Typography>
              <Button size="small" component={Link} href="/student/postings?eligibility=eligible">
                Job Profiles
              </Button>
            </CardContent>
          </Card>
        </Grid>
      </Grid>

      {data.nudges.length > 0 && (
        <Box>
          <Typography variant="h6" fontWeight={700} gutterBottom>
            Eligible, closing soon — you haven&apos;t applied
          </Typography>
          <Grid container spacing={2}>
            {data.nudges.map((posting) => (
              <Grid key={posting.id} size={{ xs: 12, md: 6, xl: 4 }}>
                <PostingCard posting={{ ...posting, eligibility: { eligible: true, reasons: [] }, application: null }} />
              </Grid>
            ))}
          </Grid>
        </Box>
      )}

      <Grid container spacing={2}>
        <Grid size={{ xs: 12, lg: 7 }}>
          <Card sx={{ height: "100%" }}>
            <CardContent>
              <Typography variant="subtitle1" fontWeight={700} gutterBottom>
                Recent applications
              </Typography>
              {data.applications.length === 0 && <Typography color="text.secondary">No applications yet.</Typography>}
              <Stack spacing={2}>
                {data.applications.map((a) => (
                  <Box key={a.id}>
                    <Typography variant="body2" fontWeight={600} component={Link} href={`/student/postings/${a.posting.id}`} sx={{ color: "text.primary" }}>
                      {a.posting.company?.name} — {a.posting.title}
                    </Typography>
                    <RoundTrail trail={a.trail} status={a.status} offer={a.offer} />
                  </Box>
                ))}
              </Stack>
            </CardContent>
          </Card>
        </Grid>
        <Grid size={{ xs: 12, lg: 5 }}>
          <Card sx={{ height: "100%" }}>
            <CardContent>
              <Stack direction="row" justifyContent="space-between" alignItems="center">
                <Typography variant="subtitle1" fontWeight={700}>
                  Next 14 days
                </Typography>
                <Button size="small" component={Link} href="/student/calendar">
                  Calendar
                </Button>
              </Stack>
              {data.upcoming.length === 0 && <Typography color="text.secondary">Nothing scheduled.</Typography>}
              <List dense>
                {data.upcoming.map((item, index) => (
                  <ListItemButton key={index} component={Link} href={item.link}>
                    <Chip size="small" color={typeColors[item.type]} variant="outlined" label={typeLabels[item.type]} sx={{ mr: 1.5, minWidth: 76 }} />
                    <ListItemText primary={item.title} secondary={formatDateTime(item.at)} />
                  </ListItemButton>
                ))}
              </List>
            </CardContent>
          </Card>
        </Grid>
      </Grid>
    </Stack>
  );
}
