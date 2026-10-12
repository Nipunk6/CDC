"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import { useSession } from "next-auth/react";
import {
  Alert,
  Avatar,
  Box,
  Button,
  Card,
  CardContent,
  Chip,
  Grid2,
  IconButton,
  LinearProgress,
  Paper,
  Stack,
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TableRow,
  Tooltip,
  Typography,
} from "@mui/material";
import BusinessIcon from "@mui/icons-material/Business";
import WorkIcon from "@mui/icons-material/Work";
import SchoolIcon from "@mui/icons-material/School";
import CheckCircleIcon from "@mui/icons-material/CheckCircle";
import PendingIcon from "@mui/icons-material/Pending";
import CancelIcon from "@mui/icons-material/Cancel";
import HourglassEmptyIcon from "@mui/icons-material/HourglassEmpty";
import VisibilityIcon from "@mui/icons-material/Visibility";
import AssignmentIcon from "@mui/icons-material/Assignment";
import { adminApi } from "@/lib/adminapi";
import PendingRequestsBanner from "@/components/admin/pendingrequestsbanner";
import { statusColor } from "@/lib/statuscolors";
import { greeting, todayLabel } from "@/lib/greeting";
import PageHeader from "@/components/shared/pageheader";
import StatTile from "@/components/shared/stattile";

type DashboardData = {
  stats: {
    companies_total: number;
    companies_with_submissions: number;
    jnf_total: number;
    jnf_submitted: number;
    jnf_under_review: number;
    jnf_accepted: number;
    jnf_rejected: number;
    jnf_draft: number;
    inf_total: number;
    inf_submitted: number;
    inf_under_review: number;
    inf_accepted: number;
    inf_rejected: number;
    inf_draft: number;
    pending_reviews: number;
  };
  recent_submissions: {
    jnfs: Array<{
      id: number;
      job_title: string;
      status: string;
      updated_at: string;
      company?: { name: string; logo_url?: string | null };
      edit_access_requested_at?: string | null;
      graduating_batch?: string;
    }>;
    infs: Array<{
      id: number;
      internship_title: string;
      status: string;
      updated_at: string;
      company?: { name: string; logo_url?: string | null };
      edit_access_requested_at?: string | null;
      graduating_batch?: string;
    }>;
  };
};

const getStatusIcon = (status: string) => {
  switch (status) {
    case "accepted": return <CheckCircleIcon fontSize="small" />;
    case "submitted": return <HourglassEmptyIcon fontSize="small" />;
    case "under_review": return <PendingIcon fontSize="small" />;
    case "rejected": return <CancelIcon fontSize="small" />;
    default: return null;
  }
};

export default function AdminPage() {
  const [data, setData] = useState<DashboardData | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);
  const { data: session } = useSession();
  const [hello] = useState(() => greeting());
  const [today] = useState(() => todayLabel());
  const firstName = (session?.user?.name ?? "").trim().split(/\s+/)[0] ?? "";

  useEffect(() => {
    const run = async () => {
      try {
        const response = await adminApi<DashboardData>("/admin/dashboard");
        setData(response);
      } catch (e) {
        setError(e instanceof Error ? e.message : "Failed to load admin dashboard.");
      } finally {
        setLoading(false);
      }
    };
    void run();
  }, []);

  if (loading) {
    return (
      <Box sx={{ py: 4 }}>
        <LinearProgress />
        <Typography textAlign="center" mt={2}>Loading dashboard...</Typography>
      </Box>
    );
  }

  const stats = data?.stats;

  return (
    <Box>
      {error && <Alert severity="error" sx={{ mb: 2 }}>{error}</Alert>}

      <PageHeader
        title={`${hello}${firstName ? `, ${firstName}` : ""}`}
        subtitle={`${today} · Career Development Centre, IIT (ISM) Dhanbad`}
        actions={
          <>
            <Button component={Link} href="/admin/jnfs" variant="contained" startIcon={<WorkIcon />}>
              Review JNFs
            </Button>
            <Button component={Link} href="/admin/infs" variant="outlined" startIcon={<SchoolIcon />}>
              Review INFs
            </Button>
          </>
        }
      />

      <PendingRequestsBanner sx={{ mb: 3 }} />

      {/* Pending Alert */}
      {(stats?.pending_reviews ?? 0) > 0 && (
        <Alert severity="warning" sx={{ mb: 3 }}>
          <strong>{stats?.pending_reviews} submissions</strong> are pending review. Please review them at your earliest.
        </Alert>
      )}

      {/* Quick Stats */}
      <Grid2 container spacing={2} sx={{ mb: 3 }}>
        {[
          { label: "Total companies", value: stats?.companies_total ?? 0 },
          { label: "Pending reviews", value: stats?.pending_reviews ?? 0, tone: (stats?.pending_reviews ?? 0) > 0 ? "warning.dark" : undefined },
          { label: "JNFs submitted", value: stats?.jnf_submitted ?? 0, href: "/admin/jnfs?status=submitted", linkLabel: "Review" },
          { label: "INFs submitted", value: stats?.inf_submitted ?? 0, href: "/admin/infs?status=submitted", linkLabel: "Review" },
          { label: "JNFs accepted", value: stats?.jnf_accepted ?? 0 },
          { label: "INFs accepted", value: stats?.inf_accepted ?? 0 },
        ].map((item) => (
          <Grid2 key={item.label} size={{ xs: 6, sm: 4, lg: 2 }}>
            <StatTile {...item} />
          </Grid2>
        ))}
      </Grid2>

      {/* Draft Submissions */}
      <Typography variant="h6" sx={{ fontSize: "1.125rem", fontWeight: 700 }}>
        Draft submissions
      </Typography>
      <Typography variant="body2" color="text.secondary" sx={{ mb: 1.5 }}>
        Drafts are tracked separately from pending reviews.
      </Typography>
      <Grid2 container spacing={2} sx={{ mb: 3 }}>
        <Grid2 size={{ xs: 6, sm: 4, lg: 2 }}>
          <StatTile label="Draft JNFs" value={stats?.jnf_draft ?? 0} href="/admin/jnfs?status=draft&origin=draft" linkLabel="View drafts" />
        </Grid2>
        <Grid2 size={{ xs: 6, sm: 4, lg: 2 }}>
          <StatTile label="Draft INFs" value={stats?.inf_draft ?? 0} href="/admin/infs?status=draft&origin=draft" linkLabel="View drafts" />
        </Grid2>
      </Grid2>

      {/* Quick Actions */}
      <Paper sx={{ px: 2, py: 1.5, mb: 3, borderRadius: 3 }}>
        <Stack direction={{ xs: "column", sm: "row" }} spacing={{ xs: 1, sm: 2 }} alignItems={{ sm: "center" }}>
          <Typography variant="subtitle2" sx={{ fontWeight: 700, flexShrink: 0 }}>
            Quick actions
          </Typography>
          <Stack direction="row" spacing={1} flexWrap="wrap" useFlexGap>
            <Button component={Link} href="/admin/jnfs?status=submitted" size="small" startIcon={<WorkIcon />}>
              Pending JNFs ({stats?.jnf_submitted ?? 0})
            </Button>
            <Button component={Link} href="/admin/infs?status=submitted" size="small" startIcon={<SchoolIcon />}>
              Pending INFs ({stats?.inf_submitted ?? 0})
            </Button>
            <Button component={Link} href="/admin/alumni-outreach" size="small" startIcon={<AssignmentIcon />}>
              Alumni Outreach
            </Button>
            <Button component={Link} href="/admin/companies" size="small" startIcon={<BusinessIcon />}>
              Manage Companies
            </Button>
          </Stack>
        </Stack>
      </Paper>

      <Grid2 container spacing={3}>
        {/* Recent JNF Submissions */}
        <Grid2 size={{ xs: 12, md: 6 }}>
          <Card>
            <CardContent>
              <Stack direction="row" justifyContent="space-between" alignItems="center" mb={2}>
                <Stack direction="row" spacing={1} alignItems="center">
                  <WorkIcon color="primary" />
                  <Typography variant="h6" fontWeight={600}>
                    Recent JNF Submissions
                  </Typography>
                </Stack>
                <Button component={Link} href="/admin/jnfs" size="small">
                  View All
                </Button>
              </Stack>

              {data?.recent_submissions.jnfs && data.recent_submissions.jnfs.length > 0 ? (
                <TableContainer sx={{ overflowX: "auto" }}>
                  <Table size="small">
                    <TableHead>
                      <TableRow>
                        <TableCell>Job Title</TableCell>
                        <TableCell>Company</TableCell>
                        <TableCell>Status</TableCell>
                        <TableCell align="right">Action</TableCell>
                      </TableRow>
                    </TableHead>
                    <TableBody>
                      {data.recent_submissions.jnfs.map((jnf) => (
                        <TableRow key={jnf.id} hover>
                          <TableCell>
                            <Typography variant="body2" fontWeight={500} noWrap sx={{ maxWidth: 150 }}>
                              {jnf.job_title}
                            </Typography>
                            {jnf.graduating_batch && (
                              <Chip label={`Batch of ${jnf.graduating_batch}`} size="small" variant="outlined" sx={{ mt: 0.5, fontSize: '0.65rem', height: 20 }} />
                            )}
                          </TableCell>
                          <TableCell>
                            <Stack direction="row" spacing={1} alignItems="center">
                              <Avatar
                                src={jnf.company?.logo_url || undefined}
                                alt={jnf.company?.name || "Logo"}
                                sx={{ width: 24, height: 24, bgcolor: "grey.200", fontSize: "0.75rem" }}
                              >
                                {jnf.company?.name ? jnf.company.name.charAt(0).toUpperCase() : "-"}
                              </Avatar>
                              <Typography variant="body2" color="text.secondary" noWrap sx={{ maxWidth: 100 }}>
                                {jnf.company?.name ?? "-"}
                              </Typography>
                            </Stack>
                          </TableCell>
                          <TableCell>
                          <Stack direction="row" spacing={0.5} alignItems="center" flexWrap="nowrap">
                              <Chip
                                icon={getStatusIcon(jnf.status) || undefined}
                                label={jnf.status.replace("_", " ")}
                                size="small"
                                color={statusColor(jnf.status) as "success" | "warning" | "info" | "error" | "default"}
                                variant="outlined"
                              />
                              {jnf.status === "submitted" && jnf.edit_access_requested_at && (
                                <Chip label="EDIT ACCESS REQUIRED" size="small" color="error" variant="filled" sx={{ fontWeight: 700, fontSize: '0.7rem' }} />
                              )}
                            </Stack>
                          </TableCell>
                          <TableCell align="right">
                            <Tooltip title="Review">
                              <IconButton component={Link} href={`/admin/jnfs/${jnf.id}`} size="small" color="primary">
                                <VisibilityIcon fontSize="small" />
                              </IconButton>
                            </Tooltip>
                          </TableCell>
                        </TableRow>
                      ))}
                    </TableBody>
                  </Table>
                </TableContainer>
              ) : (
                <Box textAlign="center" py={4}>
                  <Typography color="text.secondary">No recent JNF submissions</Typography>
                </Box>
              )}
            </CardContent>
          </Card>
        </Grid2>

        {/* Recent INF Submissions */}
        <Grid2 size={{ xs: 12, md: 6 }}>
          <Card>
            <CardContent>
              <Stack direction="row" justifyContent="space-between" alignItems="center" mb={2}>
                <Stack direction="row" spacing={1} alignItems="center">
                  <SchoolIcon color="secondary" />
                  <Typography variant="h6" fontWeight={600}>
                    Recent INF Submissions
                  </Typography>
                </Stack>
                <Button component={Link} href="/admin/infs" size="small">
                  View All
                </Button>
              </Stack>

              {data?.recent_submissions.infs && data.recent_submissions.infs.length > 0 ? (
                <TableContainer sx={{ overflowX: "auto" }}>
                  <Table size="small">
                    <TableHead>
                      <TableRow>
                        <TableCell>Internship Title</TableCell>
                        <TableCell>Company</TableCell>
                        <TableCell>Status</TableCell>
                        <TableCell align="right">Action</TableCell>
                      </TableRow>
                    </TableHead>
                    <TableBody>
                      {data.recent_submissions.infs.map((inf) => (
                        <TableRow key={inf.id} hover>
                          <TableCell>
                            <Typography variant="body2" fontWeight={500} noWrap sx={{ maxWidth: 150 }}>
                              {inf.internship_title}
                            </Typography>
                            {inf.graduating_batch && (
                              <Chip label={`Batch of ${inf.graduating_batch}`} size="small" variant="outlined" sx={{ mt: 0.5, fontSize: '0.65rem', height: 20 }} />
                            )}
                          </TableCell>
                          <TableCell>
                            <Stack direction="row" spacing={1} alignItems="center">
                              <Avatar
                                src={inf.company?.logo_url || undefined}
                                alt={inf.company?.name || "Logo"}
                                sx={{ width: 24, height: 24, bgcolor: "grey.200", fontSize: "0.75rem" }}
                              >
                                {inf.company?.name ? inf.company.name.charAt(0).toUpperCase() : "-"}
                              </Avatar>
                              <Typography variant="body2" color="text.secondary" noWrap sx={{ maxWidth: 100 }}>
                                {inf.company?.name ?? "-"}
                              </Typography>
                            </Stack>
                          </TableCell>
                          <TableCell>
                          <Stack direction="row" spacing={0.5} alignItems="center" flexWrap="nowrap">
                              <Chip
                                icon={getStatusIcon(inf.status) || undefined}
                                label={inf.status.replace("_", " ")}
                                size="small"
                                color={statusColor(inf.status) as "success" | "warning" | "info" | "error" | "default"}
                                variant="outlined"
                              />
                              {inf.status === "submitted" && inf.edit_access_requested_at && (
                                <Chip label="EDIT ACCESS REQUIRED" size="small" color="error" variant="filled" sx={{ fontWeight: 700, fontSize: '0.7rem' }} />
                              )}
                            </Stack>
                          </TableCell>
                          <TableCell align="right">
                            <Tooltip title="Review">
                              <IconButton component={Link} href={`/admin/infs/${inf.id}`} size="small" color="secondary">
                                <VisibilityIcon fontSize="small" />
                              </IconButton>
                            </Tooltip>
                          </TableCell>
                        </TableRow>
                      ))}
                    </TableBody>
                  </Table>
                </TableContainer>
              ) : (
                <Box textAlign="center" py={4}>
                  <Typography color="text.secondary">No recent INF submissions</Typography>
                </Box>
              )}
            </CardContent>
          </Card>
        </Grid2>
      </Grid2>
    </Box>
  );
}
