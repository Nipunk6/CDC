"use client";

import { use, useCallback, useEffect, useState } from "react";
import Link from "next/link";
import { getSession } from "next-auth/react";
import {
  Alert,
  AlertTitle,
  Avatar,
  Box,
  Button,
  Card,
  CardContent,
  Chip,
  Divider,
  IconButton,
  LinearProgress,
  Pagination,
  Paper,
  Stack,
  Tab,
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TableRow,
  Tabs,
  TextField,
  Tooltip,
  Typography,
  alpha,
} from "@mui/material";
import ArrowBackIcon from "@mui/icons-material/ArrowBack";
import EventRepeatIcon from "@mui/icons-material/EventRepeat";
import GroupsIcon from "@mui/icons-material/Groups";
import LockIcon from "@mui/icons-material/Lock";
import PersonRemoveIcon from "@mui/icons-material/PersonRemove";
import SearchIcon from "@mui/icons-material/Search";
import UploadFileIcon from "@mui/icons-material/UploadFile";
import WorkOutlineIcon from "@mui/icons-material/WorkOutline";
import EmojiEventsIcon from "@mui/icons-material/EmojiEvents";
import PlaylistAddIcon from "@mui/icons-material/PlaylistAdd";

import { adminApi } from "@/lib/adminapi";

const apiBase = process.env.NEXT_PUBLIC_API_URL ?? "http://localhost:8000/api";

const formatDate = (value) => {
  if (!value) return "—";
  const date = new Date(value);
  return Number.isNaN(date.getTime())
    ? value
    : date.toLocaleDateString("en-IN", { day: "2-digit", month: "short", year: "numeric" });
};

const dash = (value) => (value === null || value === undefined || value === "" ? "—" : value);

export default function AdminPlacementCycleDetailPage({ params }) {
  const { id } = use(params);

  const [cycle, setCycle] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [success, setSuccess] = useState(null);
  const [tab, setTab] = useState(0);

  const [enrollments, setEnrollments] = useState([]);
  const [meta, setMeta] = useState({ current_page: 1, last_page: 1, per_page: 50, total: 0 });
  const [search, setSearch] = useState("");
  const [page, setPage] = useState(1);
  const [enrollmentsLoading, setEnrollmentsLoading] = useState(false);

  const [rollInput, setRollInput] = useState("");
  const [file, setFile] = useState(null);
  const [enrolling, setEnrolling] = useState(false);
  const [report, setReport] = useState(null);

  const loadCycle = useCallback(async () => {
    try {
      const response = await adminApi(`/admin/placement-cycles/${id}`);
      setCycle(response.placement_cycle ?? null);
      setError(null);
    } catch (e) {
      setError(e instanceof Error ? e.message : "Failed to load this placement cycle.");
    } finally {
      setLoading(false);
    }
  }, [id]);

  const loadEnrollments = useCallback(
    async (targetPage = 1, term = "") => {
      setEnrollmentsLoading(true);
      try {
        const query = new URLSearchParams({ page: String(targetPage) });
        if (term.trim()) query.set("search", term.trim());

        const response = await adminApi(`/admin/placement-cycles/${id}/enrollments?${query.toString()}`);
        setEnrollments(response.enrollments ?? []);
        setMeta(response.meta ?? { current_page: 1, last_page: 1, per_page: 50, total: 0 });
      } catch (e) {
        setError(e instanceof Error ? e.message : "Failed to load enrolled students.");
      } finally {
        setEnrollmentsLoading(false);
      }
    },
    [id]
  );

  useEffect(() => {
    void loadCycle();
    void loadEnrollments(1, "");
  }, [loadCycle, loadEnrollments]);

  const handleSearch = async () => {
    setPage(1);
    await loadEnrollments(1, search);
  };

  const handlePageChange = async (_event, nextPage) => {
    setPage(nextPage);
    await loadEnrollments(nextPage, search);
  };

  const handleCloseCycle = async () => {
    if (!window.confirm(`Close "${cycle?.name}"?`)) return;

    try {
      await adminApi(`/admin/placement-cycles/${id}/close`, { method: "PATCH" });
      setSuccess("Placement cycle closed.");
      await loadCycle();
    } catch (e) {
      setError(e instanceof Error ? e.message : "Failed to close the cycle.");
    }
  };

  const handleEnroll = async () => {
    const rollNumbers = rollInput
      .split(/[\s,;]+/)
      .map((value) => value.trim())
      .filter(Boolean);

    if (!file && rollNumbers.length === 0) {
      setError("Paste at least one roll number or choose a file to upload.");
      return;
    }

    setEnrolling(true);
    setError(null);
    setSuccess(null);
    setReport(null);

    try {
      let payload;

      if (file) {
        // Multipart needs a raw fetch: adminApi always sends JSON.
        const session = await getSession();
        const formData = new FormData();
        formData.append("file", file);

        const response = await fetch(`${apiBase}/admin/placement-cycles/${id}/enroll`, {
          method: "POST",
          headers: {
            Accept: "application/json",
            Authorization: `Bearer ${session?.accessToken}`,
          },
          body: formData,
        });

        payload = await response.json().catch(() => ({}));

        if (!response.ok) {
          throw new Error(payload.message ?? "Enrolment failed.");
        }
      } else {
        payload = await adminApi(`/admin/placement-cycles/${id}/enroll`, {
          method: "POST",
          body: JSON.stringify({ roll_nos: rollNumbers }),
        });
      }

      setReport(payload);
      setRollInput("");
      setFile(null);
      await Promise.all([loadCycle(), loadEnrollments(1, search)]);
      setPage(1);
    } catch (e) {
      setError(e instanceof Error ? e.message : "Enrolment failed.");
    } finally {
      setEnrolling(false);
    }
  };

  const handleRemove = async (enrollment) => {
    const label = enrollment.student_profile?.roll_no ?? `student #${enrollment.student_profile_id}`;
    if (!window.confirm(`Remove ${label} from this cycle?`)) return;

    try {
      await adminApi(`/admin/placement-cycles/${id}/enroll/${enrollment.student_profile_id}`, { method: "DELETE" });
      setSuccess("Student removed from this cycle.");
      await Promise.all([loadCycle(), loadEnrollments(page, search)]);
    } catch (e) {
      setError(e instanceof Error ? e.message : "Failed to remove the student.");
    }
  };

  if (loading) {
    return <LinearProgress />;
  }

  if (!cycle) {
    return (
      <Box>
        <Alert severity="error" sx={{ mb: 2 }}>
          {error ?? "Placement cycle not found."}
        </Alert>
        <Button component={Link} href="/admin/placement-cycles" startIcon={<ArrowBackIcon />}>
          Back to Placement Cycles
        </Button>
      </Box>
    );
  }

  const stats = [
    { icon: <GroupsIcon />, label: "Enrolled students", value: cycle.enrolled_students_count ?? 0 },
    { icon: <WorkOutlineIcon />, label: "Postings floated", value: cycle.postings_count ?? 0 },
    { icon: <EmojiEventsIcon />, label: "Offers made", value: cycle.offers_count ?? 0 },
  ];

  return (
    <Box>
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
          <Stack direction="row" spacing={2} alignItems="center">
            <Avatar sx={{ width: 48, height: 48, bgcolor: "white", color: "primary.main" }}>
              <EventRepeatIcon />
            </Avatar>
            <Box>
              <Typography variant="h5" fontWeight={700} sx={{ textAlign: "left" }}>
                {cycle.name}
              </Typography>
              <Stack direction="row" spacing={1} alignItems="center" flexWrap="wrap" useFlexGap sx={{ mt: 0.5 }}>
                <Chip
                  size="small"
                  label={cycle.type === "fulltime" ? "Full Time" : "Internship"}
                  sx={{ bgcolor: alpha("#fff", 0.2), color: "white" }}
                />
                <Chip
                  size="small"
                  label={cycle.status === "open" ? "Open" : "Closed"}
                  sx={{ bgcolor: alpha("#fff", 0.2), color: "white" }}
                />
                <Typography variant="body2" sx={{ opacity: 0.9 }}>
                  {formatDate(cycle.starts_on)} → {formatDate(cycle.ends_on)}
                </Typography>
              </Stack>
            </Box>
          </Stack>
          <Stack direction="row" spacing={1}>
            {cycle.status === "open" && (
              <Button variant="contained" color="secondary" startIcon={<LockIcon />} onClick={handleCloseCycle}>
                Close Cycle
              </Button>
            )}
            <Button
              component={Link}
              href="/admin/placement-cycles"
              variant="outlined"
              startIcon={<ArrowBackIcon />}
              sx={{
                color: "white",
                borderColor: "white",
                "&:hover": { borderColor: "white", bgcolor: alpha("#fff", 0.1) },
              }}
            >
              All Cycles
            </Button>
          </Stack>
        </Stack>
      </Paper>

      {error && (
        <Alert severity="error" sx={{ mb: 2 }} onClose={() => setError(null)}>
          {error}
        </Alert>
      )}
      {success && (
        <Alert severity="success" sx={{ mb: 2 }} onClose={() => setSuccess(null)}>
          {success}
        </Alert>
      )}

      <Paper sx={{ mb: 3 }}>
        <Tabs value={tab} onChange={(_event, next) => setTab(next)} variant="scrollable" allowScrollButtonsMobile>
          <Tab label="Overview" />
          <Tab label={`Enrolled Students (${cycle.enrolled_students_count ?? 0})`} />
          <Tab label="Postings" />
        </Tabs>
      </Paper>

      {tab === 0 && (
        <Stack spacing={3}>
          <Stack direction={{ xs: "column", sm: "row" }} spacing={2}>
            {stats.map((stat) => (
              <Card key={stat.label} sx={{ flex: 1 }}>
                <CardContent>
                  <Stack direction="row" spacing={1.5} alignItems="center">
                    <Avatar sx={{ bgcolor: (theme) => alpha(theme.palette.primary.main, 0.1), color: "primary.main" }}>
                      {stat.icon}
                    </Avatar>
                    <Box>
                      <Typography variant="h5" fontWeight={700}>
                        {stat.value}
                      </Typography>
                      <Typography variant="caption" color="text.secondary">
                        {stat.label}
                      </Typography>
                    </Box>
                  </Stack>
                </CardContent>
              </Card>
            ))}
          </Stack>

          <Card>
            <CardContent>
              <Typography variant="h6" fontWeight={700} gutterBottom sx={{ textAlign: "left" }}>
                Allowed Programmes &amp; Batches
              </Typography>
              <Divider sx={{ mb: 2 }} />
              {(cycle.allowed_programmes ?? []).length === 0 ? (
                <Typography color="text.secondary">No programmes recorded for this cycle.</Typography>
              ) : (
                <Stack spacing={1.5}>
                  {(cycle.allowed_programmes ?? []).map((row, index) => (
                    <Stack
                      key={`${row.programme}-${index}`}
                      direction={{ xs: "column", md: "row" }}
                      spacing={1}
                      alignItems={{ md: "center" }}
                      justifyContent="space-between"
                    >
                      <Typography variant="body2" sx={{ textAlign: "left" }}>
                        {row.programme}
                      </Typography>
                      <Stack direction="row" spacing={0.5} flexWrap="wrap" useFlexGap>
                        {(row.batches ?? []).map((batch) => (
                          <Chip key={batch} size="small" variant="outlined" label={batch} />
                        ))}
                      </Stack>
                    </Stack>
                  ))}
                </Stack>
              )}
            </CardContent>
          </Card>

          <Card>
            <CardContent>
              <Typography variant="h6" fontWeight={700} gutterBottom sx={{ textAlign: "left" }}>
                Details
              </Typography>
              <Divider sx={{ mb: 2 }} />
              <Typography variant="body2" sx={{ mb: 2 }}>
                {dash(cycle.description)}
              </Typography>
              <Typography variant="caption" color="text.secondary">
                Created by {cycle.created_by?.name ?? "—"} ({cycle.created_by?.email ?? "—"}) on{" "}
                {formatDate(cycle.created_at)}
              </Typography>
            </CardContent>
          </Card>
        </Stack>
      )}

      {tab === 1 && (
        <Stack spacing={3}>
          <Card>
            <CardContent>
              <Typography variant="h6" fontWeight={700} gutterBottom sx={{ textAlign: "left" }}>
                Enrol Students
              </Typography>
              <Divider sx={{ mb: 2 }} />
              <Stack spacing={2}>
                <TextField
                  label="Paste roll numbers"
                  placeholder={"22JE0459\n22JE0460\n22JE0461"}
                  multiline
                  minRows={3}
                  fullWidth
                  value={rollInput}
                  onChange={(event) => setRollInput(event.target.value)}
                  helperText="Separate with new lines, commas or spaces."
                  disabled={enrolling || Boolean(file)}
                />

                <Stack direction={{ xs: "column", sm: "row" }} spacing={2} alignItems={{ sm: "center" }}>
                  <Button component="label" variant="outlined" startIcon={<UploadFileIcon />} disabled={enrolling}>
                    {file ? file.name : "Upload .xlsx / .csv"}
                    <input
                      type="file"
                      hidden
                      accept=".csv,.txt,.xlsx,.xls"
                      onChange={(event) => setFile(event.target.files?.[0] ?? null)}
                    />
                  </Button>
                  {file && (
                    <Button size="small" color="inherit" onClick={() => setFile(null)} disabled={enrolling}>
                      Clear file
                    </Button>
                  )}
                  <Box sx={{ flexGrow: 1 }} />
                  <Button
                    variant="contained"
                    startIcon={<PlaylistAddIcon />}
                    onClick={handleEnroll}
                    disabled={enrolling}
                  >
                    {enrolling ? "Enrolling..." : "Enrol Students"}
                  </Button>
                </Stack>

                <Typography variant="caption" color="text.secondary">
                  A single column of roll numbers; a &quot;roll_no&quot; header row is ignored.
                </Typography>
              </Stack>

              {report && (
                <Alert severity={report.errors?.length ? "warning" : "success"} sx={{ mt: 2 }}>
                  <AlertTitle>{report.message}</AlertTitle>
                  {report.errors?.length > 0 && (
                    <TableContainer sx={{ maxHeight: 260, mt: 1 }}>
                      <Table size="small" stickyHeader>
                        <TableHead>
                          <TableRow>
                            <TableCell>Row</TableCell>
                            <TableCell>Roll No</TableCell>
                            <TableCell>Reason</TableCell>
                          </TableRow>
                        </TableHead>
                        <TableBody>
                          {report.errors.map((row, index) => (
                            <TableRow key={`${row.row}-${row.roll_no}-${index}`}>
                              <TableCell>{row.row}</TableCell>
                              <TableCell>{row.roll_no}</TableCell>
                              <TableCell>{row.reason}</TableCell>
                            </TableRow>
                          ))}
                        </TableBody>
                      </Table>
                    </TableContainer>
                  )}
                </Alert>
              )}
            </CardContent>
          </Card>

          <Card>
            <CardContent>
              <Stack
                direction={{ xs: "column", sm: "row" }}
                spacing={2}
                alignItems={{ sm: "center" }}
                justifyContent="space-between"
                sx={{ mb: 2 }}
              >
                <Typography variant="h6" fontWeight={700} sx={{ textAlign: "left" }}>
                  Enrolled Students ({meta.total})
                </Typography>
                <Stack direction="row" spacing={1}>
                  <TextField
                    size="small"
                    placeholder="Roll no, name, branch..."
                    value={search}
                    onChange={(event) => setSearch(event.target.value)}
                    onKeyDown={(event) => {
                      if (event.key === "Enter") void handleSearch();
                    }}
                  />
                  <Button variant="outlined" startIcon={<SearchIcon />} onClick={handleSearch}>
                    Search
                  </Button>
                </Stack>
              </Stack>
              <Divider sx={{ mb: 2 }} />

              {enrollmentsLoading ? (
                <LinearProgress />
              ) : enrollments.length === 0 ? (
                <Typography color="text.secondary">
                  No students enrolled yet. Paste roll numbers above to enrol them.
                </Typography>
              ) : (
                <>
                  <TableContainer>
                    <Table size="small">
                      <TableHead>
                        <TableRow>
                          <TableCell>Roll No</TableCell>
                          <TableCell>Name</TableCell>
                          <TableCell>Programme</TableCell>
                          <TableCell>Branch</TableCell>
                          <TableCell>Batch</TableCell>
                          <TableCell>Status</TableCell>
                          <TableCell align="right">Actions</TableCell>
                        </TableRow>
                      </TableHead>
                      <TableBody>
                        {enrollments.map((enrollment) => (
                          <TableRow key={enrollment.id} hover>
                            <TableCell>{dash(enrollment.student_profile?.roll_no)}</TableCell>
                            <TableCell>{dash(enrollment.student_profile?.full_name)}</TableCell>
                            <TableCell>{dash(enrollment.student_profile?.programme)}</TableCell>
                            <TableCell>{dash(enrollment.student_profile?.branch)}</TableCell>
                            <TableCell>{dash(enrollment.student_profile?.graduating_batch)}</TableCell>
                            <TableCell>
                              <Chip
                                size="small"
                                variant="outlined"
                                color={enrollment.status === "active" ? "success" : "warning"}
                                label={enrollment.status}
                              />
                            </TableCell>
                            <TableCell align="right">
                              <Tooltip title="Remove from cycle">
                                <IconButton size="small" color="error" onClick={() => handleRemove(enrollment)}>
                                  <PersonRemoveIcon fontSize="small" />
                                </IconButton>
                              </Tooltip>
                            </TableCell>
                          </TableRow>
                        ))}
                      </TableBody>
                    </Table>
                  </TableContainer>

                  {meta.last_page > 1 && (
                    <Stack alignItems="center" sx={{ mt: 2 }}>
                      <Pagination count={meta.last_page} page={page} onChange={handlePageChange} color="primary" />
                    </Stack>
                  )}
                </>
              )}
            </CardContent>
          </Card>
        </Stack>
      )}

      {tab === 2 && (
        <Card>
          <CardContent sx={{ textAlign: "center", py: 6 }}>
            <WorkOutlineIcon sx={{ fontSize: 56, color: "text.disabled", mb: 1 }} />
            <Typography variant="h6" gutterBottom>
              No postings in this cycle yet
            </Typography>
            <Typography color="text.secondary" sx={{ textAlign: "center" }}>
              Accepted JNFs and INFs appear here once an admin floats them into this cycle.
            </Typography>
          </CardContent>
        </Card>
      )}
    </Box>
  );
}
