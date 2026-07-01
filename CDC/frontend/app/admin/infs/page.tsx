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
import SchoolIcon from "@mui/icons-material/School";
import VisibilityIcon from "@mui/icons-material/Visibility";
import CheckCircleIcon from "@mui/icons-material/CheckCircle";
import PendingIcon from "@mui/icons-material/Pending";
import CancelIcon from "@mui/icons-material/Cancel";
import HourglassEmptyIcon from "@mui/icons-material/HourglassEmpty";
import FilterListIcon from "@mui/icons-material/FilterList";
import DownloadIcon from "@mui/icons-material/Download";
import { adminApi, adminDownload } from "@/lib/adminapi";

type InfItem = {
  id: number;
  internship_title: string;
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

function InfQueueContent() {
  const searchParams = useSearchParams();
  const initialStatus = searchParams.get("status") || "all";
  const [infs, setInfs] = useState<InfItem[]>([]);
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
        const response = await adminApi<{ infs: InfItem[] }>(`/admin/infs${query}`);
        setInfs(response.infs);
      } catch (e) {
        setError(e instanceof Error ? e.message : "Failed to load INF queue.");
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
  const existingYears = infs.map((inf) => inf.graduating_batch).filter((b): b is string => !!b && b !== "Unknown Batch");
  const availableYears = Array.from(new Set([...staticYears, ...existingYears])).sort((a, b) => b.localeCompare(a));

  const filteredInfs = infs.filter(inf => {
    if (selectedYear === "all") return true;
    return (inf.graduating_batch || "Unknown Batch") === selectedYear;
  });

  const groupedInfs = filteredInfs.reduce((acc, inf) => {
    const batch = inf.graduating_batch || "Unknown Batch";
    if (!acc[batch]) {
      acc[batch] = [];
    }
    acc[batch].push(inf);
    return acc;
  }, {} as Record<string, InfItem[]>);

  // Sort batches descending
  const sortedBatches = Object.keys(groupedInfs).sort((a, b) => b.localeCompare(a));

  return (
    <Box>
      {/* Header */}
      <Paper
        sx={{
          p: 2,
          mb: 3,
          background: (theme) =>
            `linear-gradient(135deg, ${theme.palette.secondary.main} 0%, ${theme.palette.secondary.dark} 100%)`,
          color: "white",
          borderRadius: 2,
        }}
      >
        <Stack direction={{ xs: "column", md: "row" }} justifyContent="space-between" alignItems={{ md: "center" }} spacing={2}>
          <Stack direction="row" spacing={2} alignItems="center">
            <Avatar sx={{ width: 48, height: 48, bgcolor: "white", color: "secondary.main" }}>
              <SchoolIcon />
            </Avatar>
            <Box>
              <Typography variant="h5" fontWeight={700}>
                INF Review Queue
              </Typography>
              <Typography variant="body2" sx={{ opacity: 0.9 }}>
                Review and manage Internship Notification Forms
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
            <FilterListIcon color="secondary" />
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
            <FilterListIcon color="secondary" />
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
          <Chip label={`${filteredInfs.length} results`} color="secondary" variant="outlined" sx={{ ml: 'auto !important' }} />
        </Stack>
      </Paper>

      {error && <Alert severity="error" sx={{ mb: 2 }}>{error}</Alert>}

      {loading ? (
        <Box sx={{ py: 4 }}>
          <LinearProgress color="secondary" />
          <Typography textAlign="center" mt={2}>Loading INFs...</Typography>
        </Box>
      ) : infs.length === 0 ? (
        <Card>
          <CardContent>
            <Box textAlign="center" py={4}>
              <Typography color="text.secondary" mb={2}>
                No INF submissions found for the selected filter.
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
                  <Chip label={groupedInfs[batch].length} size="small" color="secondary" sx={{ height: 20 }} />
                </Stack>
              </AccordionSummary>
              <AccordionDetails sx={{ p: 0 }}>
                <TableContainer sx={{ overflowX: "auto" }}>
                  <Table>
                    <TableHead>
                      <TableRow>
                        <TableCell sx={{ width: "25%" }}>Internship Title</TableCell>
                        <TableCell sx={{ width: "25%" }}>Company</TableCell>
                        <TableCell sx={{ width: "15%" }}>Status</TableCell>
                        <TableCell sx={{ width: "12%" }}>Submitted</TableCell>
                        <TableCell sx={{ width: "13%" }}>Last Updated</TableCell>
                        <TableCell align="right" sx={{ width: "10%" }}>Action</TableCell>
                      </TableRow>
                    </TableHead>
                    <TableBody>
                      {groupedInfs[batch].map((inf) => (
                        <TableRow key={inf.id} hover>
                          <TableCell>
                            <Typography variant="body2" fontWeight={500}>
                              {inf.internship_title}
                            </Typography>
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
                              <Typography variant="body2" color="text.secondary">
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
                                color={getStatusColor(inf.status) as "success" | "warning" | "info" | "error" | "default"}
                                variant="outlined"
                              />
                              {inf.review_marked && (
                                <Chip label="Marked for review" size="small" color="warning" variant="outlined" />
                              )}
                              {inf.status === "submitted" && inf.edit_access_requested_at && (
                                <Chip label="EDIT ACCESS REQUIRED" size="small" color="error" variant="filled" sx={{ fontWeight: 700, fontSize: '0.75rem' }} />
                              )}
                            </Stack>
                          </TableCell>
                          <TableCell>
                            <Typography variant="caption" color="text.secondary">
                              {inf.created_at ? new Date(inf.created_at).toLocaleDateString() : "-"}
                            </Typography>
                          </TableCell>
                          <TableCell>
                            <Typography variant="caption" color="text.secondary">
                              {new Date(inf.updated_at).toLocaleDateString()}
                            </Typography>
                          </TableCell>
                          <TableCell align="right">
                            <Stack direction="row" spacing={1} justifyContent="flex-end">
                              {inf.status === "accepted" && (
                                <Tooltip title="Download CSV">
                                  <Button
                                    size="small"
                                    variant="outlined"
                                    color="secondary"
                                    startIcon={<DownloadIcon />}
                                    onClick={() => void adminDownload(`/admin/infs/${inf.id}/csv`, `accepted-inf-${inf.id}.csv`)}
                                  >
                                    CSV
                                  </Button>
                                </Tooltip>
                              )}
                              <Tooltip title="Review INF">
                                <Button
                                  component={Link}
                                  href={`/admin/infs/${inf.id}`}
                                  size="small"
                                  variant="contained"
                                  color="secondary"
                                  startIcon={<VisibilityIcon />}
                                  onClick={(event) => {
                                    if (inf.status === "draft") {
                                      return;
                                    }
                                    const confirmed = window.confirm("Open this INF for review?");
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

export default function AdminInfQueuePage() {
  return (
    <Suspense fallback={
      <Box sx={{ py: 4 }}>
        <LinearProgress color="secondary" />
        <Typography textAlign="center" mt={2}>Loading...</Typography>
      </Box>
    }>
      <InfQueueContent />
    </Suspense>
  );
}
