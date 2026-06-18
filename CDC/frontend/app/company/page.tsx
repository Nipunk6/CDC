"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import {
  Alert,
  Avatar,
  Box,
  Button,
  Card,
  CardContent,
  Chip,
  Dialog,
  DialogActions,
  DialogContent,
  DialogContentText,
  DialogTitle,
  Grid2,
  IconButton,
  LinearProgress,
  Menu,
  MenuItem,
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
  alpha,
} from "@mui/material";
import AddIcon from "@mui/icons-material/Add";
import ContentCopyIcon from "@mui/icons-material/ContentCopy";
import DeleteIcon from "@mui/icons-material/Delete";
import EditIcon from "@mui/icons-material/Edit";
import VisibilityIcon from "@mui/icons-material/Visibility";
import MoreVertIcon from "@mui/icons-material/MoreVert";
import WorkIcon from "@mui/icons-material/Work";
import SchoolIcon from "@mui/icons-material/School";
import CheckCircleIcon from "@mui/icons-material/CheckCircle";
import PendingIcon from "@mui/icons-material/Pending";
import CancelIcon from "@mui/icons-material/Cancel";
import DraftsIcon from "@mui/icons-material/Drafts";
import TrendingUpIcon from "@mui/icons-material/TrendingUp";
import BusinessIcon from "@mui/icons-material/Business";
import { companyApi } from "@/lib/companyapi";

type DashboardResponse = {
  stats: {
    jnf_total: number;
    jnf_submitted: number;
    jnf_accepted: number;
    jnf_rejected: number;
    jnf_draft: number;
    inf_total: number;
    inf_submitted: number;
    inf_accepted: number;
    inf_rejected: number;
    inf_draft: number;
  };
  company?: {
    name: string;
    logo_url?: string | null;
  };
  recent_jnfs?: Array<{
    id: number;
    job_title: string;
    status: string;
    updated_at: string;
    edit_access_requested_at?: string | null;
    edit_access_requested_reason?: string | null;
  }>;
  recent_infs?: Array<{
    id: number;
    internship_title: string;
    status: string;
    updated_at: string;
    edit_access_requested_at?: string | null;
    edit_access_requested_reason?: string | null;
  }>;
};

const getStatusColor = (status: string) => {
  switch (status) {
    case "accepted": return "success";
    case "submitted": case "under_review": return "info";
    case "rejected": return "error";
    case "draft": return "default";
    default: return "default";
  }
};

const getStatusIcon = (status: string) => {
  switch (status) {
    case "accepted": return <CheckCircleIcon fontSize="small" />;
    case "submitted": case "under_review": return <PendingIcon fontSize="small" />;
    case "rejected": return <CancelIcon fontSize="small" />;
    case "draft": return <DraftsIcon fontSize="small" />;
    default: return null;
  }
};

export default function CompanyDashboard() {
  const router = useRouter();
  const [data, setData] = useState<DashboardResponse | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);
  const [menuAnchor, setMenuAnchor] = useState<{ el: HTMLElement | null; type: "jnf" | "inf"; id: number } | null>(null);
  const [duplicateDialog, setDuplicateDialog] = useState<{ open: boolean; type: "jnf" | "inf"; id: number; title: string } | null>(null);
  const [deleteDialog, setDeleteDialog] = useState<{ open: boolean; type: "jnf" | "inf"; id: number; title: string } | null>(null);
  const [requestingEditAccess, setRequestingEditAccess] = useState<{ type: "jnf" | "inf"; id: number } | null>(null);

  const fetchDashboard = async () => {
    try {
      const response = await companyApi<DashboardResponse>("/company/dashboard");
      setData(response);
    } catch (e) {
      setError(e instanceof Error ? e.message : "Failed to load dashboard.");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    void fetchDashboard();
  }, []);

  const handleDuplicate = async () => {
    if (!duplicateDialog) return;
    
    try {
      const endpoint = duplicateDialog.type === "jnf" 
        ? `/company/jnfs/${duplicateDialog.id}/duplicate`
        : `/company/infs/${duplicateDialog.id}/duplicate`;
      
      const response = await companyApi<{ id: number }>(endpoint, { method: "POST" });
      setDuplicateDialog(null);
      router.push(`/company/${duplicateDialog.type}/${response.id}/edit`);
    } catch (e) {
      setError(e instanceof Error ? e.message : "Failed to duplicate.");
    }
  };

  const handleDelete = async () => {
    if (!deleteDialog) return;
    
    try {
      const endpoint = deleteDialog.type === "jnf" 
        ? `/company/jnfs/${deleteDialog.id}`
        : `/company/infs/${deleteDialog.id}`;
      
      await companyApi(endpoint, { method: "DELETE" });
      setDeleteDialog(null);
      // Refresh the dashboard data
      await fetchDashboard();
    } catch (e) {
      setError(e instanceof Error ? e.message : "Failed to delete.");
    }
  };

  const handleRequestEditAccess = async (type: "jnf" | "inf", id: number) => {
    const reason = window.prompt("Briefly explain why you need edit access again:");
    if (reason === null) return;

    const trimmedReason = reason.trim();
    if (!trimmedReason) {
      setError("Please provide a brief reason.");
      return;
    }

    try {
      setError(null);
      setRequestingEditAccess({ type, id });

      const endpoint = type === "jnf"
        ? `/company/jnfs/${id}/request-edit-access`
        : `/company/infs/${id}/request-edit-access`;

      await companyApi(endpoint, {
        method: "POST",
        body: JSON.stringify({ reason: trimmedReason }),
      });

      await fetchDashboard();
    } catch (e) {
      setError(e instanceof Error ? e.message : "Failed to request edit access.");
    } finally {
      setRequestingEditAccess(null);
    }
  };

  if (loading) {
    return (
      <Box sx={{ py: 4 }}>
        <LinearProgress />
        <Typography textAlign="center" mt={2}>Loading dashboard...</Typography>
      </Box>
    );
  }

  const stats = data?.stats || {
    jnf_total: 0, jnf_submitted: 0, jnf_accepted: 0, jnf_rejected: 0, jnf_draft: 0,
    inf_total: 0, inf_submitted: 0, inf_accepted: 0, inf_rejected: 0, inf_draft: 0,
  };

  return (
    <Box>
      {error && <Alert severity="error" sx={{ mb: 2 }}>{error}</Alert>}

      {/* Welcome Header */}
      <Paper
        sx={{
          p: 3,
          mb: 3,
          background: (theme) =>
            `linear-gradient(135deg, ${theme.palette.primary.main} 0%, ${theme.palette.primary.dark} 100%)`,
          color: "white",
          borderRadius: 2,
        }}
      >
        <Stack direction={{ xs: "column", md: "row" }} justifyContent="space-between" alignItems={{ md: "center" }} spacing={2}>
          <Stack direction="row" spacing={2} alignItems="center">
            <Avatar 
              src={data?.company?.logo_url || undefined}
              sx={{ width: 56, height: 56, bgcolor: "white", color: "primary.main" }}
            >
              {!data?.company?.logo_url && <BusinessIcon fontSize="large" />}
            </Avatar>
            <Box>
              <Typography variant="h5" fontWeight={700}>
                Welcome back{data?.company?.name ? `, ${data.company.name}` : ""}!
              </Typography>
              <Typography variant="body2" sx={{ opacity: 0.9 }}>
                Manage your Job and Internship Notification Forms
              </Typography>
            </Box>
          </Stack>
          <Stack direction="row" spacing={2}>
            <Button
              component={Link}
              href="/company/jnf/new"
              variant="contained"
              startIcon={<AddIcon />}
              sx={{ bgcolor: "white", color: "primary.main", "&:hover": { bgcolor: "grey.100" } }}
            >
              New JNF
            </Button>
            <Button
              component={Link}
              href="/company/inf/new"
              variant="outlined"
              startIcon={<AddIcon />}
              sx={{ color: "white", borderColor: "white", "&:hover": { borderColor: "white", bgcolor: alpha("#fff", 0.1) } }}
            >
              New INF
            </Button>
          </Stack>
        </Stack>
      </Paper>

      {/* Quick Stats */}
      <Grid2 container spacing={2} sx={{ mb: 3 }}>
        {[
          { label: "Total JNFs", value: stats.jnf_total, icon: <WorkIcon />, color: "primary.main" },
          { label: "JNFs Accepted", value: stats.jnf_accepted, icon: <CheckCircleIcon />, color: "success.main" },
          { label: "Total INFs", value: stats.inf_total, icon: <SchoolIcon />, color: "secondary.main" },
          { label: "INFs Accepted", value: stats.inf_accepted, icon: <CheckCircleIcon />, color: "success.main" },
        ].map((item) => (
          <Grid2 key={item.label} size={{ xs: 6, md: 3 }}>
            <Card sx={{ height: "100%" }}>
              <CardContent>
                <Stack direction="row" justifyContent="space-between" alignItems="flex-start">
                  <Box>
                    <Typography variant="caption" color="text.secondary" textTransform="uppercase">
                      {item.label}
                    </Typography>
                    <Typography variant="h3" fontWeight={700} sx={{ color: item.color }}>
                      {item.value}
                    </Typography>
                  </Box>
                  <Box sx={{ color: item.color, opacity: 0.3 }}>{item.icon}</Box>
                </Stack>
              </CardContent>
            </Card>
          </Grid2>
        ))}
      </Grid2>

      {/* Quick Actions */}
      <Paper sx={{ p: 2, mb: 3, bgcolor: alpha("#1976d2", 0.05), border: "1px solid", borderColor: "primary.light" }}>
        <Stack direction={{ xs: "column", sm: "row" }} spacing={2} alignItems="center" justifyContent="space-between">
          <Typography variant="subtitle1" fontWeight={600}>
            🚀 Quick Actions
          </Typography>
          <Stack direction="row" spacing={1} flexWrap="wrap">
            <Button component={Link} href="/company/jnf/new" size="small" startIcon={<WorkIcon />}>
              Create JNF for New Role
            </Button>
            <Button component={Link} href="/company/inf/new" size="small" startIcon={<SchoolIcon />}>
              Create INF for Internship
            </Button>
            <Button component={Link} href="/company/submissions" size="small" startIcon={<VisibilityIcon />}>
              View All Submissions
            </Button>
          </Stack>
        </Stack>
      </Paper>

      {/* JNF Submissions Table */}
      <Card sx={{ mb: 3 }}>
        <CardContent>
          <Stack direction="row" justifyContent="space-between" alignItems="center" mb={2}>
            <Stack direction="row" spacing={1} alignItems="center">
              <WorkIcon color="primary" />
              <Typography variant="h6" fontWeight={600}>
                Job Notification Forms (JNF)
              </Typography>
              <Chip label={stats.jnf_total} size="small" color="primary" />
            </Stack>
            <Button component={Link} href="/company/jnf/new" size="small" startIcon={<AddIcon />} variant="outlined">
              Add New Profile
            </Button>
          </Stack>

          {data?.recent_jnfs && data.recent_jnfs.length > 0 ? (
            <TableContainer>
              <Table size="small">
                <TableHead>
                  <TableRow>
                    <TableCell>Job Title / Profile</TableCell>
                    <TableCell>Status</TableCell>
                    <TableCell>Last Updated</TableCell>
                    <TableCell align="right">Actions</TableCell>
                  </TableRow>
                </TableHead>
                <TableBody>
                  {data.recent_jnfs.map((jnf) => (
                    <TableRow key={jnf.id} hover>
                      <TableCell>
                        <Typography variant="body2" fontWeight={500}>{jnf.job_title}</Typography>
                      </TableCell>
                      <TableCell>
                        <Stack direction="row" spacing={1} alignItems="center">
                          <Chip
                            icon={getStatusIcon(jnf.status) || undefined}
                            label={jnf.status.replace("_", " ")}
                            size="small"
                            color={getStatusColor(jnf.status) as "success" | "info" | "error" | "default"}
                            variant="outlined"
                          />
                          {jnf.status === "under_review" && (
                            <Button
                              component={Link}
                              href={`/company/jnf/${jnf.id}`}
                              size="small"
                              variant="text"
                            >
                              Admin Message
                            </Button>
                          )}
                          {jnf.status === "submitted" && !jnf.edit_access_requested_at && (
                            <Button
                              size="small"
                              variant="text"
                              onClick={() => void handleRequestEditAccess("jnf", jnf.id)}
                              disabled={requestingEditAccess?.type === "jnf" && requestingEditAccess.id === jnf.id}
                            >
                              {requestingEditAccess?.type === "jnf" && requestingEditAccess.id === jnf.id ? "Requesting..." : "Request Edit Access"}
                            </Button>
                          )}
                          {jnf.status === "submitted" && jnf.edit_access_requested_at && (
                            <Chip label="Edit access request submitted" size="small" color="warning" variant="outlined" />
                          )}
                        </Stack>
                      </TableCell>
                      <TableCell>
                        <Typography variant="caption" color="text.secondary">
                          {new Date(jnf.updated_at).toLocaleDateString()}
                        </Typography>
                      </TableCell>
                      <TableCell align="right">
                        <Stack direction="row" spacing={0.5} justifyContent="flex-end">
                          <Tooltip title="View">
                            <IconButton component={Link} href={`/company/jnf/${jnf.id}`} size="small">
                              <VisibilityIcon fontSize="small" />
                            </IconButton>
                          </Tooltip>
                          <Tooltip title="Edit">
                            <span>
                              <IconButton
                                component={Link}
                                href={jnf.status === "draft" || jnf.status === "under_review" ? `/company/jnf/${jnf.id}/edit` : "#"}
                                size="small"
                                disabled={jnf.status !== "draft" && jnf.status !== "under_review"}
                              >
                                <EditIcon fontSize="small" />
                              </IconButton>
                            </span>
                          </Tooltip>
                          <Tooltip title="Duplicate for New Role">
                            <IconButton
                              size="small"
                              onClick={() => setDuplicateDialog({ open: true, type: "jnf", id: jnf.id, title: jnf.job_title })}
                            >
                              <ContentCopyIcon fontSize="small" />
                            </IconButton>
                          </Tooltip>
                          {jnf.status === "draft" && (
                            <Tooltip title="Delete Draft">
                              <IconButton
                                size="small"
                                color="error"
                                onClick={() => setDeleteDialog({ open: true, type: "jnf", id: jnf.id, title: jnf.job_title })}
                              >
                                <DeleteIcon fontSize="small" />
                              </IconButton>
                            </Tooltip>
                          )}
                        </Stack>
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </TableContainer>
          ) : (
            <Box textAlign="center" py={4}>
              <Typography color="text.secondary" mb={2}>No JNFs created yet</Typography>
              <Button component={Link} href="/company/jnf/new" variant="contained" startIcon={<AddIcon />}>
                Create Your First JNF
              </Button>
            </Box>
          )}
        </CardContent>
      </Card>

      {/* INF Submissions Table */}
      <Card>
        <CardContent>
          <Stack direction="row" justifyContent="space-between" alignItems="center" mb={2}>
            <Stack direction="row" spacing={1} alignItems="center">
              <SchoolIcon color="secondary" />
              <Typography variant="h6" fontWeight={600}>
                Internship Notification Forms (INF)
              </Typography>
              <Chip label={stats.inf_total} size="small" color="secondary" />
            </Stack>
            <Button component={Link} href="/company/inf/new" size="small" startIcon={<AddIcon />} variant="outlined" color="secondary">
              Add New Profile
            </Button>
          </Stack>

          {data?.recent_infs && data.recent_infs.length > 0 ? (
            <TableContainer>
              <Table size="small">
                <TableHead>
                  <TableRow>
                    <TableCell>Internship Title / Profile</TableCell>
                    <TableCell>Status</TableCell>
                    <TableCell>Last Updated</TableCell>
                    <TableCell align="right">Actions</TableCell>
                  </TableRow>
                </TableHead>
                <TableBody>
                  {data.recent_infs.map((inf) => (
                    <TableRow key={inf.id} hover>
                      <TableCell>
                        <Typography variant="body2" fontWeight={500}>{inf.internship_title}</Typography>
                      </TableCell>
                      <TableCell>
                        <Stack direction="row" spacing={1} alignItems="center">
                          <Chip
                            icon={getStatusIcon(inf.status) || undefined}
                            label={inf.status.replace("_", " ")}
                            size="small"
                            color={getStatusColor(inf.status) as "success" | "info" | "error" | "default"}
                            variant="outlined"
                          />
                          {inf.status === "under_review" && (
                            <Button
                              component={Link}
                              href={`/company/inf/${inf.id}`}
                              size="small"
                              variant="text"
                            >
                              Admin Message
                            </Button>
                          )}
                          {inf.status === "submitted" && !inf.edit_access_requested_at && (
                            <Button
                              size="small"
                              variant="text"
                              onClick={() => void handleRequestEditAccess("inf", inf.id)}
                              disabled={requestingEditAccess?.type === "inf" && requestingEditAccess.id === inf.id}
                            >
                              {requestingEditAccess?.type === "inf" && requestingEditAccess.id === inf.id ? "Requesting..." : "Request Edit Access"}
                            </Button>
                          )}
                          {inf.status === "submitted" && inf.edit_access_requested_at && (
                            <Chip label="Edit access request submitted" size="small" color="warning" variant="outlined" />
                          )}
                        </Stack>
                      </TableCell>
                      <TableCell>
                        <Typography variant="caption" color="text.secondary">
                          {new Date(inf.updated_at).toLocaleDateString()}
                        </Typography>
                      </TableCell>
                      <TableCell align="right">
                        <Stack direction="row" spacing={0.5} justifyContent="flex-end">
                          <Tooltip title="View">
                            <IconButton component={Link} href={`/company/inf/${inf.id}`} size="small">
                              <VisibilityIcon fontSize="small" />
                            </IconButton>
                          </Tooltip>
                          {(inf.status === "draft" || inf.status === "under_review") && (
                            <Tooltip title="Edit">
                              <IconButton component={Link} href={`/company/inf/${inf.id}/edit`} size="small">
                                <EditIcon fontSize="small" />
                              </IconButton>
                            </Tooltip>
                          )}
                          <Tooltip title="Duplicate for New Role">
                            <IconButton
                              size="small"
                              onClick={() => setDuplicateDialog({ open: true, type: "inf", id: inf.id, title: inf.internship_title })}
                            >
                              <ContentCopyIcon fontSize="small" />
                            </IconButton>
                          </Tooltip>
                          {inf.status === "draft" && (
                            <Tooltip title="Delete Draft">
                              <IconButton
                                size="small"
                                color="error"
                                onClick={() => setDeleteDialog({ open: true, type: "inf", id: inf.id, title: inf.internship_title })}
                              >
                                <DeleteIcon fontSize="small" />
                              </IconButton>
                            </Tooltip>
                          )}
                        </Stack>
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </TableContainer>
          ) : (
            <Box textAlign="center" py={4}>
              <Typography color="text.secondary" mb={2}>No INFs created yet</Typography>
              <Button component={Link} href="/company/inf/new" variant="contained" color="secondary" startIcon={<AddIcon />}>
                Create Your First INF
              </Button>
            </Box>
          )}
        </CardContent>
      </Card>

      {/* Duplicate Dialog */}
      <Dialog open={duplicateDialog?.open || false} onClose={() => setDuplicateDialog(null)}>
        <DialogTitle>Duplicate {duplicateDialog?.type?.toUpperCase()}?</DialogTitle>
        <DialogContent>
          <DialogContentText>
            This will create a copy of <strong>&quot;{duplicateDialog?.title}&quot;</strong> as a new draft.
            You can then edit it for a different role (e.g., SDE → Data Analyst).
          </DialogContentText>
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setDuplicateDialog(null)}>Cancel</Button>
          <Button onClick={handleDuplicate} variant="contained" startIcon={<ContentCopyIcon />}>
            Duplicate & Edit
          </Button>
        </DialogActions>
      </Dialog>

      {/* Delete Dialog */}
      <Dialog open={deleteDialog?.open || false} onClose={() => setDeleteDialog(null)}>
        <DialogTitle>Delete Draft {deleteDialog?.type?.toUpperCase()}?</DialogTitle>
        <DialogContent>
          <DialogContentText>
            Are you sure you want to delete <strong>&quot;{deleteDialog?.title}&quot;</strong>?
            This action cannot be undone.
          </DialogContentText>
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setDeleteDialog(null)}>Cancel</Button>
          <Button onClick={handleDelete} variant="contained" color="error" startIcon={<DeleteIcon />}>
            Delete
          </Button>
        </DialogActions>
      </Dialog>
    </Box>
  );
}
