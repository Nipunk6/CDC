"use client";

import { Suspense, useEffect, useState } from "react";
import Link from "next/link";
import { useSearchParams } from "next/navigation";
import {
  Alert,
  Avatar,
  Box,
  Button,
  Card,
  CardContent,
  Chip,
  FormControl,
  LinearProgress,
  MenuItem,
  Paper,
  Select,
  SelectChangeEvent,
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
  Accordion,
  AccordionSummary,
  AccordionDetails,
} from "@mui/material";
import ExpandMoreIcon from "@mui/icons-material/ExpandMore";
import ArrowBackIcon from "@mui/icons-material/ArrowBack";
import WorkIcon from "@mui/icons-material/Work";
import VisibilityIcon from "@mui/icons-material/Visibility";
import CheckCircleIcon from "@mui/icons-material/CheckCircle";
import PendingIcon from "@mui/icons-material/Pending";
import CancelIcon from "@mui/icons-material/Cancel";
import HourglassEmptyIcon from "@mui/icons-material/HourglassEmpty";
import FilterListIcon from "@mui/icons-material/FilterList";
import DownloadIcon from "@mui/icons-material/Download";
import { adminApi, adminDownload } from "@/lib/adminapi";

type JnfItem = {
  id: number;
  job_title: string;
  status: string;
  review_marked?: boolean;
  updated_at: string;
  created_at: string;
  company?: { name: string; logo_url?: string | null };
  edit_access_requested_at?: string | null;
  graduating_batch?: string;
};

const getStatusColor = (status: string) => {
  switch (status) {
    case "accepted": return "success";
    case "submitted": return "warning";
    case "under_review": return "info";
    case "rejected": return "error";
    default: return "default";
  }
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

function JnfQueueContent() {
  const searchParams = useSearchParams();
  const initialStatus = searchParams.get("status") || "all";
  const [jnfs, setJnfs] = useState<JnfItem[]>([]);
  const [status, setStatus] = useState<string>(initialStatus);
  const [selectedYear, setSelectedYear] = useState<string>("all");
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    const run = async () => {
      setLoading(true);
      setError(null);

      const query = status === "all" ? "?status=all" : (status === "pending" ? "" : `?status=${encodeURIComponent(status)}`);

      try {
        const response = await adminApi<{ jnfs: JnfItem[] }>(`/admin/jnfs${query}`);
        setJnfs(response.jnfs);
      } catch (e) {
        setError(e instanceof Error ? e.message : "Failed to load JNF queue.");
      } finally {
        setLoading(false);
      }
    };

    void run();
  }, [status]);

  const handleStatusChange = (event: SelectChangeEvent<string>) => {
    setStatus(event.target.value);
  };

  const currentYear = new Date().getFullYear();
  const staticYears = Array.from({ length: 4 }, (_, i) => String(currentYear + i));
  const existingYears = jnfs.map((jnf) => jnf.graduating_batch).filter((b): b is string => !!b && b !== "Unknown Batch");
  const availableYears = Array.from(new Set([...staticYears, ...existingYears])).sort((a, b) => b.localeCompare(a));

  const filteredJnfs = jnfs.filter(jnf => {
    if (selectedYear === "all") return true;
    return (jnf.graduating_batch || "Unknown Batch") === selectedYear;
  });

  const groupedJnfs = filteredJnfs.reduce((acc, jnf) => {
    const batch = jnf.graduating_batch || "Unknown Batch";
    if (!acc[batch]) {
      acc[batch] = [];
    }
    acc[batch].push(jnf);
    return acc;
  }, {} as Record<string, JnfItem[]>);

  // Sort batches descending
  const sortedBatches = Object.keys(groupedJnfs).sort((a, b) => b.localeCompare(a));

  return (
    <Box>
      {/* Header */}
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
        <Stack direction={{ xs: "column", md: "row" }} justifyContent="space-between" alignItems={{ md: "center" }} spacing={2}>
          <Stack direction="row" spacing={2} alignItems="center">
            <Avatar sx={{ width: 48, height: 48, bgcolor: "white", color: "primary.main" }}>
              <WorkIcon />
            </Avatar>
            <Box>
              <Typography variant="h5" fontWeight={700}>
                JNF Review Queue
              </Typography>
              <Typography variant="body2" sx={{ opacity: 0.9 }}>
                Review and manage Job Notification Forms
              </Typography>
            </Box>
          </Stack>
          <Button
            component={Link}
            href="/admin"
            variant="outlined"
            startIcon={<ArrowBackIcon />}
            sx={{ color: "white", borderColor: "white", "&:hover": { borderColor: "white", bgcolor: alpha("#fff", 0.1) } }}
          >
            Back to Dashboard
          </Button>
        </Stack>
      </Paper>

      {/* Filter */}
      <Paper sx={{ p: 2, mb: 3 }}>
        <Stack direction={{ xs: "column", sm: "row" }} spacing={2} alignItems={{ sm: "center" }} flexWrap="wrap" useFlexGap>
          <Stack direction="row" spacing={1} alignItems="center">
            <FilterListIcon color="primary" />
            <Typography variant="subtitle2">Filter by Year:</Typography>
          </Stack>
          <FormControl size="small" sx={{ minWidth: 150 }}>
            <Select value={selectedYear} onChange={(e) => setSelectedYear(e.target.value)} displayEmpty>
              <MenuItem value="all">All Years</MenuItem>
              <MenuItem value="Unknown Batch">Unknown Batch</MenuItem>
              {availableYears.map((year) => (
                <MenuItem key={year} value={year}>{`Batch of ${year}`}</MenuItem>
              ))}
            </Select>
          </FormControl>

          <Stack direction="row" spacing={1} alignItems="center" sx={{ ml: { sm: 2 } }}>
            <FilterListIcon color="primary" />
            <Typography variant="subtitle2">Filter by Status:</Typography>
          </Stack>
          <FormControl size="small" sx={{ minWidth: 250 }}>
            <Select value={status} onChange={handleStatusChange} displayEmpty>
              <MenuItem value="all">All Statuses</MenuItem>
              <MenuItem value="pending">📋 Pending Queue (submitted + under_review)</MenuItem>
              <MenuItem value="submitted">⏳ Submitted</MenuItem>
              <MenuItem value="under_review">🔍 Under Review</MenuItem>
              <MenuItem value="accepted">✅ Accepted</MenuItem>
              <MenuItem value="rejected">❌ Rejected</MenuItem>
              <MenuItem value="draft">📝 Draft</MenuItem>
            </Select>
          </FormControl>
          <Chip label={`${filteredJnfs.length} results`} color="primary" variant="outlined" sx={{ ml: 'auto !important' }} />
        </Stack>
      </Paper>

      {error && <Alert severity="error" sx={{ mb: 2 }}>{error}</Alert>}

      {loading ? (
        <Box sx={{ py: 4 }}>
          <LinearProgress />
          <Typography textAlign="center" mt={2}>Loading JNFs...</Typography>
        </Box>
      ) : jnfs.length === 0 ? (
        <Card>
          <CardContent>
            <Box textAlign="center" py={4}>
              <Typography color="text.secondary" mb={2}>
                No JNF submissions found for the selected filter.
              </Typography>
            </Box>
          </CardContent>
        </Card>
      ) : (
        <Box>
          {sortedBatches.map((batch) => (
            <Accordion key={batch} defaultExpanded sx={{ mb: 2, '&:before': { display: 'none' }, boxShadow: 1, borderRadius: 1 }}>
              <AccordionSummary expandIcon={<ExpandMoreIcon />} sx={{ bgcolor: 'grey.50', borderRadius: 1 }}>
                <Stack direction="row" spacing={2} alignItems="center">
                  <Typography variant="subtitle1" fontWeight={600}>
                    {batch === "Unknown Batch" ? batch : `Batch of ${batch}`}
                  </Typography>
                  <Chip label={groupedJnfs[batch].length} size="small" color="primary" sx={{ height: 20 }} />
                </Stack>
              </AccordionSummary>
              <AccordionDetails sx={{ p: 0 }}>
                <TableContainer sx={{ overflowX: "auto" }}>
                  <Table>
                    <TableHead>
                      <TableRow>
                        <TableCell sx={{ width: "25%" }}>Job Title</TableCell>
                        <TableCell sx={{ width: "25%" }}>Company</TableCell>
                        <TableCell sx={{ width: "15%" }}>Status</TableCell>
                        <TableCell sx={{ width: "12%" }}>Submitted</TableCell>
                        <TableCell sx={{ width: "13%" }}>Last Updated</TableCell>
                        <TableCell align="right" sx={{ width: "10%" }}>Action</TableCell>
                      </TableRow>
                    </TableHead>
                    <TableBody>
                      {groupedJnfs[batch].map((jnf) => (
                        <TableRow key={jnf.id} hover>
                          <TableCell>
                            <Typography variant="body2" fontWeight={500}>
                              {jnf.job_title}
                            </Typography>
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
                              <Typography variant="body2" color="text.secondary">
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
                                color={getStatusColor(jnf.status) as "success" | "warning" | "info" | "error" | "default"}
                                variant="outlined"
                              />
                              {jnf.review_marked && (
                                <Chip label="Marked for review" size="small" color="warning" variant="outlined" />
                              )}
                              {jnf.status === "submitted" && jnf.edit_access_requested_at && (
                                <Chip label="EDIT ACCESS REQUIRED" size="small" color="error" variant="filled" sx={{ fontWeight: 700, fontSize: '0.75rem' }} />
                              )}
                            </Stack>
                          </TableCell>
                          <TableCell>
                            <Typography variant="caption" color="text.secondary">
                              {jnf.created_at ? new Date(jnf.created_at).toLocaleDateString() : "-"}
                            </Typography>
                          </TableCell>
                          <TableCell>
                            <Typography variant="caption" color="text.secondary">
                              {new Date(jnf.updated_at).toLocaleDateString()}
                            </Typography>
                          </TableCell>
                          <TableCell align="right">
                            <Stack direction="row" spacing={1} justifyContent="flex-end">
                              {jnf.status === "accepted" && (
                                <Tooltip title="Download CSV">
                                  <Button
                                    size="small"
                                    variant="outlined"
                                    startIcon={<DownloadIcon />}
                                    onClick={() => void adminDownload(`/admin/jnfs/${jnf.id}/csv`, `accepted-jnf-${jnf.id}.csv`)}
                                  >
                                    CSV
                                  </Button>
                                </Tooltip>
                              )}
                              <Tooltip title="Review JNF">
                                <Button
                                  component={Link}
                                  href={`/admin/jnfs/${jnf.id}`}
                                  size="small"
                                  variant="contained"
                                  startIcon={<VisibilityIcon />}
                                  onClick={(event) => {
                                    if (jnf.status === "draft") {
                                      return;
                                    }
                                    const confirmed = window.confirm("Open this JNF for review?");
                                    if (!confirmed) {
                                      event.preventDefault();
                                    }
                                  }}
                                >
                                  Review
                                </Button>
                              </Tooltip>
                            </Stack>
                          </TableCell>
                        </TableRow>
                      ))}
                    </TableBody>
                  </Table>
                </TableContainer>
              </AccordionDetails>
            </Accordion>
          ))}
        </Box>
      )}
    </Box>
  );
}

export default function AdminJnfQueuePage() {
  return (
    <Suspense fallback={
      <Box sx={{ py: 4 }}>
        <LinearProgress />
        <Typography textAlign="center" mt={2}>Loading...</Typography>
      </Box>
    }>
      <JnfQueueContent />
    </Suspense>
  );
}
