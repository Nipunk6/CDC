"use client";

import { useCallback, useEffect, useState } from "react";
import Link from "next/link";
import {
  Alert,
  Box,
  Button,
  Card,
  Chip,
  Dialog,
  DialogActions,
  DialogContent,
  DialogTitle,
  Grid2 as Grid,
  LinearProgress,
  List,
  ListItemButton,
  ListItemText,
  Pagination,
  Paper,
  Stack,
  Tab,
  Tabs,
  TextField,
  Typography,
} from "@mui/material";
import DescriptionIcon from "@mui/icons-material/Description";
import CheckIcon from "@mui/icons-material/Check";
import CloseIcon from "@mui/icons-material/Close";
import OpenInNewIcon from "@mui/icons-material/OpenInNew";

import PageHeader from "@/components/shared/pageheader";
import { adminApi } from "@/lib/adminapi";
import { formatDateTime, statusColor, titleCase } from "@/lib/format";

const statuses = ["pending", "approved", "rejected"];

// The signed preview URL goes through the session-gated Next.js PDF proxy (M0.3d).
const proxied = (url) => `/api/proxy-pdf?url=${encodeURIComponent(url)}`;

export default function AdminResumesPage() {
  const [status, setStatus] = useState("pending");
  const [search, setSearch] = useState("");
  const [appliedSearch, setAppliedSearch] = useState("");
  const [page, setPage] = useState(1);
  const [resumes, setResumes] = useState([]);
  const [meta, setMeta] = useState({ last_page: 1, total: 0 });
  const [counts, setCounts] = useState({});
  const [selectedId, setSelectedId] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [success, setSuccess] = useState(null);
  const [rejectOpen, setRejectOpen] = useState(false);
  const [remark, setRemark] = useState("");
  const [busy, setBusy] = useState(false);
  const [dialogError, setDialogError] = useState(null);
  const [loadedAt, setLoadedAt] = useState(0);

  const load = useCallback(async (currentStatus, targetPage, term) => {
    setLoading(true);
    try {
      const query = new URLSearchParams({ status: currentStatus, page: String(targetPage) });
      if (term.trim()) query.set("search", term.trim());
      const response = await adminApi(`/admin/resumes?${query.toString()}`);
      const list = response.resumes ?? [];
      setResumes(list);
      setMeta(response.meta ?? { last_page: 1, total: 0 });
      setCounts(response.counts ?? {});
      setLoadedAt(Date.now());
      // Deciding the last item of the last page leaves an empty page behind; step back.
      const lastPage = response.meta?.last_page ?? 1;
      if (targetPage > lastPage) {
        setPage(lastPage);
      }
      setSelectedId((prev) => (list.some((r) => r.id === prev) ? prev : list[0]?.id ?? null));
      setError(null);
    } catch (e) {
      setError(e instanceof Error ? e.message : "Failed to load resumes.");
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    void load(status, page, appliedSearch);
  }, [load, status, page, appliedSearch]);

  const selected = resumes.find((r) => r.id === selectedId) ?? null;

  // Preview links are signed for 30 minutes; refresh the list before they go stale.
  const select = (id) => {
    setSelectedId(id);
    if (Date.now() - loadedAt > 20 * 60 * 1000) {
      void load(status, page, appliedSearch);
    }
  };

  const decide = async (decision, adminRemark = null) => {
    if (!selected) return;
    setBusy(true);
    setDialogError(null);
    try {
      const response = await adminApi(`/admin/resumes/${selected.id}`, {
        method: "PATCH",
        body: JSON.stringify({ status: decision, admin_remark: adminRemark, expected_updated_at: selected.updated_at }),
      });
      setSuccess(`${response.message} (${selected.student_profile?.roll_no} · ${selected.label})`);
      setRejectOpen(false);
      await load(status, page, appliedSearch);
    } catch (e) {
      const message = e instanceof Error ? e.message : "Failed to update the resume.";
      if (rejectOpen) setDialogError(message);
      else setError(message);
      // A 409 means the student uploaded a new file: reload so the preview shows it.
      await load(status, page, appliedSearch);
    } finally {
      setBusy(false);
    }
  };

  return (
    <>
      <PageHeader
        icon={<DescriptionIcon />}
        title="Resume Verification"
        subtitle={`${counts.pending ?? 0} pending · ${counts.approved ?? 0} verified · ${counts.rejected ?? 0} rejected`}
        backHref="/admin/students"
        backLabel="Students"
      />

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

      <Stack direction={{ xs: "column", md: "row" }} spacing={2} justifyContent="space-between" sx={{ mb: 2 }}>
        <Tabs
          value={status}
          onChange={(_e, value) => {
            setStatus(value);
            setPage(1);
          }}
        >
          {statuses.map((value) => (
            <Tab key={value} value={value} label={`${value === "approved" ? "Verified" : titleCase(value)} (${counts[value] ?? 0})`} />
          ))}
        </Tabs>
        <TextField
          size="small"
          label="Search Roll Number or name"
          value={search}
          onChange={(event) => setSearch(event.target.value)}
          onKeyDown={(event) => {
            if (event.key === "Enter") {
              setPage(1);
              setAppliedSearch(search);
            }
          }}
        />
      </Stack>

      {loading && <LinearProgress sx={{ mb: 2 }} />}

      <Grid container spacing={2}>
        <Grid size={{ xs: 12, md: 4 }}>
          <Card>
            {resumes.length === 0 && !loading ? (
              <Typography color="text.secondary" sx={{ p: 3 }}>
                No {status === "approved" ? "verified" : status} resumes.
              </Typography>
            ) : (
              <List dense sx={{ maxHeight: { md: 640 }, overflowY: "auto" }}>
                {resumes.map((resume) => (
                  <ListItemButton key={resume.id} selected={resume.id === selectedId} onClick={() => select(resume.id)}>
                    <ListItemText
                      primary={`${resume.student_profile?.roll_no} · ${resume.student_profile?.full_name}`}
                      secondary={`Slot ${resume.slot} · ${resume.label} · ${formatDateTime(resume.updated_at)}`}
                    />
                  </ListItemButton>
                ))}
              </List>
            )}
            {meta.last_page > 1 && (
              <Stack alignItems="center" sx={{ py: 1 }}>
                <Pagination size="small" count={meta.last_page} page={page} onChange={(_e, value) => setPage(value)} />
              </Stack>
            )}
          </Card>
        </Grid>

        <Grid size={{ xs: 12, md: 8 }}>
          {selected ? (
            <Paper sx={{ p: 2 }}>
              <Stack direction={{ xs: "column", sm: "row" }} justifyContent="space-between" spacing={1.5} sx={{ mb: 2 }}>
                <Box sx={{ minWidth: 0 }}>
                  <Typography variant="h6" fontWeight={700}>
                    <Link href={`/admin/students/${selected.student_profile?.id}`}>
                      {selected.student_profile?.full_name}
                    </Link>
                  </Typography>
                  <Typography variant="body2" color="text.secondary">
                    {selected.student_profile?.roll_no} · {selected.student_profile?.branch} · {selected.student_profile?.graduating_batch}
                  </Typography>
                  <Stack direction="row" spacing={1} alignItems="center" sx={{ mt: 0.5 }}>
                    <Typography variant="body2">
                      Slot {selected.slot}: <strong>{selected.label}</strong>
                    </Typography>
                    <Chip size="small" variant="outlined" color={statusColor(selected.status)} label={selected.status === "approved" ? "Verified" : titleCase(selected.status)} />
                  </Stack>
                  {selected.admin_remark && (
                    <Typography variant="body2" color="text.secondary" sx={{ mt: 0.5 }}>
                      Remark: {selected.admin_remark} {selected.reviewed_by ? `— ${selected.reviewed_by.name}` : ""}
                    </Typography>
                  )}
                </Box>
                <Stack direction="row" spacing={1} alignItems="flex-start">
                  <Button
                    variant="contained"
                    color="success"
                    startIcon={<CheckIcon />}
                    disabled={busy || selected.status === "approved"}
                    onClick={() => decide("approved")}
                  >
                    Mark as verified
                  </Button>
                  <Button
                    variant="outlined"
                    color="error"
                    startIcon={<CloseIcon />}
                    disabled={busy}
                    onClick={() => {
                      setRemark("");
                      setDialogError(null);
                      setRejectOpen(true);
                    }}
                  >
                    Reject
                  </Button>
                  <Button
                    component="a"
                    href={proxied(selected.preview_url)}
                    target="_blank"
                    rel="noopener"
                    startIcon={<OpenInNewIcon />}
                  >
                    Open
                  </Button>
                </Stack>
              </Stack>
              <Box
                component="iframe"
                key={selected.id}
                title="Resume preview"
                src={proxied(selected.preview_url)}
                sx={{ width: "100%", height: { xs: 480, md: 720 }, border: 1, borderColor: "divider", borderRadius: 1 }}
              />
            </Paper>
          ) : (
            <Paper sx={{ p: 4, textAlign: "center" }}>
              <Typography color="text.secondary">Select a resume to preview it.</Typography>
            </Paper>
          )}
        </Grid>
      </Grid>

      <Dialog open={rejectOpen} onClose={() => !busy && setRejectOpen(false)} maxWidth="sm" fullWidth>
        <DialogTitle>Reject resume</DialogTitle>
        <DialogContent>
          {dialogError && (
            <Alert severity="error" sx={{ mt: 1 }}>
              {dialogError}
            </Alert>
          )}
          <TextField
            autoFocus
            fullWidth
            multiline
            minRows={3}
            label="What should the student fix?"
            value={remark}
            onChange={(event) => setRemark(event.target.value)}
            sx={{ mt: 1 }}
          />
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setRejectOpen(false)} disabled={busy}>
            Cancel
          </Button>
          <Button color="error" variant="contained" disabled={busy || !remark.trim()} onClick={() => decide("rejected", remark.trim())}>
            Reject
          </Button>
        </DialogActions>
      </Dialog>
    </>
  );
}
