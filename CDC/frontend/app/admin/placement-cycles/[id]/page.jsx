"use client";

import { Suspense, use, useCallback, useEffect, useMemo, useState } from "react";
import Link from "next/link";
import { useRouter, useSearchParams } from "next/navigation";
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
  Link as MuiLink,
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
import PauseCircleOutlineIcon from "@mui/icons-material/PauseCircleOutline";
import PlayCircleOutlineIcon from "@mui/icons-material/PlayCircleOutline";
import UploadFileIcon from "@mui/icons-material/UploadFile";
import WorkOutlineIcon from "@mui/icons-material/WorkOutline";
import EmojiEventsIcon from "@mui/icons-material/EmojiEvents";
import PlaylistAddIcon from "@mui/icons-material/PlaylistAdd";
import PublishIcon from "@mui/icons-material/Publish";

import { adminApi } from "@/lib/adminapi";
import { rememberPlacement } from "@/lib/recentplacements";
import CyclePostings from "@/components/admin/cyclepostings";
import TemplateDownloadButton from "@/components/admin/templatedownloadbutton";
import StudentBlocksPanel from "@/components/admin/studentblockspanel";
import StudentFilterPanel from "@/components/admin/studentfilterpanel";
import useCatalogue from "@/lib/usecatalogue";
import { filtersFromParams, filtersToQuery, hasFilters } from "@/lib/studentfilters";

const apiBase = process.env.NEXT_PUBLIC_API_URL ?? "http://localhost:8000/api";

const formatDate = (value) => {
  if (!value) return "—";
  const date = new Date(value);
  return Number.isNaN(date.getTime())
    ? value
    : date.toLocaleDateString("en-IN", { day: "2-digit", month: "short", year: "numeric" });
};

const dash = (value) => (value === null || value === undefined || value === "" ? "—" : value);

const emptyMeta = { current_page: 1, last_page: 1, per_page: 50, total: 0 };

export default function AdminPlacementCycleDetailPage({ params }) {
  const { id } = use(params);

  return (
    <Suspense fallback={<LinearProgress />}>
      <PlacementCycleDetail id={id} />
    </Suspense>
  );
}

// The enrolled list's filters and page live in the URL, like /admin/students, and "Download as Excel" exports the
// filtered list (M5).
function PlacementCycleDetail({ id }) {
  const router = useRouter();
  const params = useSearchParams();
  const queryString = params.toString();
  const filters = useMemo(() => filtersFromParams(new URLSearchParams(queryString)), [queryString]);
  const page = Math.max(1, Number(new URLSearchParams(queryString).get("page")) || 1);
  const apiFilters = filtersToQuery(filters, { api: true });

  const [cycle, setCycle] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [success, setSuccess] = useState(null);
  // A shared or reloaded filtered link opens on the enrolled list.
  const [tab, setTab] = useState(() => (queryString ? 1 : 0));

  const catalogue = useCatalogue(adminApi);
  const [reloadTick, setReloadTick] = useState(0);
  const [listing, setListing] = useState({ key: null, enrollments: [], meta: emptyMeta });
  const requestKey = `${queryString}#${reloadTick}`;
  const enrollmentsLoading = listing.key !== requestKey;
  const { enrollments, meta } = listing;

  const [rollInput, setRollInput] = useState("");
  const [file, setFile] = useState(null);
  const [enrolling, setEnrolling] = useState(false);
  const [report, setReport] = useState(null);

  const loadCycle = useCallback(async () => {
    try {
      const response = await adminApi(`/admin/placement-cycles/${id}`);
      setCycle(response.placement_cycle ?? null);
      setError(null);
      if (response.placement_cycle) rememberPlacement(response.placement_cycle.id); // "Recently Visited" (S8.3)
    } catch (e) {
      setError(e instanceof Error ? e.message : "Failed to load this placement.");
    } finally {
      setLoading(false);
    }
  }, [id]);

  useEffect(() => {
    void loadCycle();
  }, [loadCycle]);

  useEffect(() => {
    let cancelled = false;
    adminApi(`/admin/placement-cycles/${id}/enrollments?${filtersToQuery(filters, { api: true, extra: { page } })}`)
      .then((response) => {
        if (cancelled) return;
        setListing({ key: requestKey, enrollments: response.enrollments ?? [], meta: response.meta ?? emptyMeta });
      })
      .catch((e) => {
        if (cancelled) return;
        setListing((prev) => ({ ...prev, key: requestKey }));
        setError(e instanceof Error ? e.message : "Failed to load enrolled students.");
      });
    return () => {
      cancelled = true;
    };
  }, [id, filters, page, requestKey]);

  const navigate = useCallback(
    (next, targetPage = 1) => {
      const query = filtersToQuery(next, { extra: { page: targetPage > 1 ? targetPage : "" } });
      router.replace(query ? `/admin/placement-cycles/${id}?${query}` : `/admin/placement-cycles/${id}`, { scroll: false });
    },
    [router, id]
  );

  const reloadEnrollments = () => setReloadTick((tick) => tick + 1);

  const handleFilters = (next) => navigate(next, 1);

  const handlePageChange = (_event, nextPage) => navigate(filters, nextPage);

  // S4.8: suspend / reactivate one enrolment. A suspended student stays listed but cannot apply in this placement.
  const handleEnrolmentStatus = async (enrollment) => {
    const suspend = enrollment.status === "active";
    const label = enrollment.student_profile?.roll_no ?? `student #${enrollment.student_profile_id}`;
    if (suspend && !window.confirm(`Suspend ${label}'s enrolment? They will not be able to apply to this placement's job profiles until reactivated.`)) {
      return;
    }
    try {
      const response = await adminApi(`/admin/placement-cycles/${id}/enrollments/${enrollment.id}`, {
        method: "PATCH",
        body: JSON.stringify({ status: suspend ? "suspended" : "active" }),
      });
      setSuccess(response.message);
      setListing((prev) => ({
        ...prev,
        enrollments: prev.enrollments.map((e) => (e.id === enrollment.id ? { ...e, status: response.enrollment?.status ?? e.status } : e)),
      }));
    } catch (e) {
      setError(e instanceof Error ? e.message : "Failed to update the enrolment.");
    }
  };

  const handleCloseCycle = async () => {
    if (!window.confirm(`Close "${cycle?.name}"?`)) return;

    try {
      await adminApi(`/admin/placement-cycles/${id}/close`, { method: "PATCH" });
      setSuccess("Placement closed.");
      await loadCycle();
    } catch (e) {
      setError(e instanceof Error ? e.message : "Failed to close the placement.");
    }
  };

  const handlePublish = async () => {
    if (!window.confirm(`Publish "${cycle?.name}"? Its enrolled students will be able to see it, and job profiles can be opened for applications in it.`)) return;

    try {
      const response = await adminApi(`/admin/placement-cycles/${id}/publish`, { method: "PATCH" });
      setSuccess(response.message ?? "Placement published.");
      await loadCycle();
    } catch (e) {
      setError(e instanceof Error ? e.message : "Failed to publish the placement.");
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
      await loadCycle();
      if (page > 1) navigate(filters, 1);
      reloadEnrollments();
    } catch (e) {
      setError(e instanceof Error ? e.message : "Enrolment failed.");
    } finally {
      setEnrolling(false);
    }
  };

  const handleRemove = async (enrollment) => {
    const label = enrollment.student_profile?.roll_no ?? `student #${enrollment.student_profile_id}`;
    if (!window.confirm(`Remove ${label} from this placement?`)) return;

    try {
      await adminApi(`/admin/placement-cycles/${id}/enroll/${enrollment.student_profile_id}`, { method: "DELETE" });
      setSuccess("Student removed from this placement.");
      await loadCycle();
      reloadEnrollments();
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
          {error ?? "Placement not found."}
        </Alert>
        <Button component={Link} href="/admin/placement-cycles" startIcon={<ArrowBackIcon />}>
          Back to Placements
        </Button>
      </Box>
    );
  }

  const stats = [
    { icon: <GroupsIcon />, label: "Enrolled students", value: cycle.enrolled_students_count ?? 0 },
    { icon: <WorkOutlineIcon />, label: "Job profiles opened", value: cycle.postings_count ?? 0 },
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
                {cycle.is_draft && <Chip size="small" color="warning" label="Draft" />}
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
          <Stack direction="row" spacing={1} flexWrap="wrap" useFlexGap>
            <TemplateDownloadButton
              label="Download as Excel"
              path={`/admin/placement-cycles/${id}/students/export${apiFilters ? `?${apiFilters}` : ""}`}
              fileName={`cycle-${id}-students.xlsx`}
              onError={setError}
            />
            {cycle.is_draft && (
              <Button variant="contained" color="warning" startIcon={<PublishIcon />} onClick={handlePublish}>
                Publish placement
              </Button>
            )}
            {cycle.status === "open" && (
              <Button variant="contained" color="secondary" startIcon={<LockIcon />} onClick={handleCloseCycle}>
                Close Placement
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
              All Placements
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
          <Tab label="Job Profiles" />
          <Tab label="Blocks" />
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
                <Typography color="text.secondary">No programmes recorded for this placement.</Typography>
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
                            <TableCell>Roll Number</TableCell>
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
              <Typography variant="h6" fontWeight={700} sx={{ textAlign: "left", mb: 2 }}>
                {hasFilters(filters) ? `${meta.total} filtered students` : `Enrolled Students (${meta.total})`}
              </Typography>
              <StudentFilterPanel
                key={filtersToQuery(filters)}
                initial={filters}
                onApply={handleFilters}
                catalogue={catalogue}
                statusLabel="Enrolment"
                statusOptions={[
                  ["active", "Enrolled"],
                  ["suspended", "Suspended"],
                ]}
              />
              <Divider sx={{ mb: 2 }} />

              {enrollmentsLoading ? (
                <LinearProgress />
              ) : enrollments.length === 0 ? (
                <Typography color="text.secondary">
                  {hasFilters(filters) ? "Could not find any students" : "No students enrolled yet. Paste roll numbers above to enrol them."}
                </Typography>
              ) : (
                <>
                  <TableContainer>
                    <Table size="small">
                      <TableHead>
                        <TableRow>
                          <TableCell>Roll Number</TableCell>
                          <TableCell>Name</TableCell>
                          <TableCell sx={{ display: { xs: "none", md: "table-cell" } }}>Programme</TableCell>
                          <TableCell sx={{ display: { xs: "none", sm: "table-cell" } }}>Branch</TableCell>
                          <TableCell>Batch</TableCell>
                          <TableCell>Status</TableCell>
                          <TableCell align="right">Actions</TableCell>
                        </TableRow>
                      </TableHead>
                      <TableBody>
                        {enrollments.map((enrollment) => (
                          <TableRow key={enrollment.id} hover>
                            <TableCell sx={{ whiteSpace: "nowrap" }}>{dash(enrollment.student_profile?.roll_no)}</TableCell>
                            <TableCell>
                              <MuiLink component={Link} href={`/admin/students/${enrollment.student_profile_id}`} underline="hover" fontWeight={600}>
                                {dash(enrollment.student_profile?.full_name)}
                              </MuiLink>
                            </TableCell>
                            <TableCell sx={{ display: { xs: "none", md: "table-cell" } }}>{dash(enrollment.student_profile?.programme)}</TableCell>
                            <TableCell sx={{ display: { xs: "none", sm: "table-cell" } }}>{dash(enrollment.student_profile?.branch)}</TableCell>
                            <TableCell>{dash(enrollment.student_profile?.graduating_batch)}</TableCell>
                            <TableCell>
                              <Chip
                                size="small"
                                variant="outlined"
                                color={enrollment.status === "active" ? "success" : "warning"}
                                label={enrollment.status === "active" ? "Enrolled" : "Suspended"}
                              />
                            </TableCell>
                            <TableCell align="right" sx={{ whiteSpace: "nowrap" }}>
                              <Tooltip title={enrollment.status === "active" ? "Suspend enrolment" : "Reactivate enrolment"}>
                                <IconButton
                                  size="small"
                                  color={enrollment.status === "active" ? "warning" : "success"}
                                  onClick={() => handleEnrolmentStatus(enrollment)}
                                >
                                  {enrollment.status === "active" ? (
                                    <PauseCircleOutlineIcon fontSize="small" />
                                  ) : (
                                    <PlayCircleOutlineIcon fontSize="small" />
                                  )}
                                </IconButton>
                              </Tooltip>
                              <Tooltip title="Remove from placement">
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

                  <Box sx={{ mt: 2, display: "flex", flexDirection: { xs: "column", sm: "row" }, gap: 1, alignItems: "center", justifyContent: "space-between" }}>
                    <Typography variant="body2" color="text.secondary">
                      Showing Page {meta.current_page} of {meta.last_page} ({meta.total} records)
                    </Typography>
                    {meta.last_page > 1 && (
                      <Pagination count={meta.last_page} page={page} onChange={handlePageChange} color="primary" size="small" />
                    )}
                  </Box>
                </>
              )}
            </CardContent>
          </Card>
        </Stack>
      )}

      {tab === 2 && <CyclePostings cycleId={id} />}

      {tab === 3 && (
        <Card>
          <CardContent>
            <StudentBlocksPanel cycleId={id} />
          </CardContent>
        </Card>
      )}
    </Box>
  );
}
