"use client";

import { useCallback, useEffect, useState } from "react";
import { useRouter } from "next/navigation";
import {
  Alert,
  Box,
  Button,
  Card,
  CardContent,
  Chip,
  Dialog,
  DialogActions,
  DialogContent,
  DialogTitle,
  Divider,
  FormControl,
  IconButton,
  InputLabel,
  MenuItem,
  Pagination,
  Select,
  Skeleton,
  Stack,
  TextField,
  Typography,
} from "@mui/material";
import PollIcon from "@mui/icons-material/Poll";
import AddIcon from "@mui/icons-material/Add";
import VisibilityIcon from "@mui/icons-material/VisibilityOutlined";
import DeleteIcon from "@mui/icons-material/DeleteOutline";
import DashboardIcon from "@mui/icons-material/DashboardOutlined";
import ContentCopyIcon from "@mui/icons-material/ContentCopy";
import CheckCircleIcon from "@mui/icons-material/CheckCircle";
import ScheduleIcon from "@mui/icons-material/Schedule";
import ArchiveIcon from "@mui/icons-material/Inventory2Outlined";
import AssignmentIcon from "@mui/icons-material/AssignmentOutlined";
import CloseIcon from "@mui/icons-material/Close";

import PageHeader from "@/components/shared/pageheader";
import { RichTextEditor, stripHtml } from "@/components/forms/shared";
import ConfirmDialog from "@/components/admin/engagement/confirmdialog";
import { SURVEY_STATUS, SURVEY_TYPES } from "@/components/admin/engagement/surveylabels";
import { adminApi } from "@/lib/adminapi";
import { formatDateTime } from "@/lib/format";

function StatusMark({ status }) {
  const icon = { published: <CheckCircleIcon color="success" fontSize="small" />, draft: <ScheduleIcon color="warning" fontSize="small" />, archived: <ArchiveIcon color="disabled" fontSize="small" /> }[status];
  return (
    <Stack direction="row" spacing={0.5} alignItems="center">
      {icon}
      <Typography variant="body2" fontWeight={600}>
        {SURVEY_STATUS[status] ?? status}
      </Typography>
    </Stack>
  );
}

function CreateDialog({ onClose, onCreated }) {
  const [name, setName] = useState("");
  const [touched, setTouched] = useState(false);
  const [welcome, setWelcome] = useState("");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState(null);
  const missing = !name.trim();

  const create = async () => {
    setBusy(true);
    setError(null);
    try {
      const response = await adminApi("/admin/surveys", { method: "POST", body: JSON.stringify({ title: name.trim(), welcome_text: stripHtml(welcome) ? welcome : null }) });
      onCreated(response);
    } catch (e) {
      setError(e instanceof Error ? e.message : "Could not create the survey.");
      setBusy(false);
    }
  };

  return (
    <Dialog open onClose={() => !busy && onClose()} maxWidth="sm" fullWidth>
      <Box sx={{ bgcolor: "warning.light", px: 3, py: 2, display: "flex", alignItems: "center", gap: 1.5 }}>
        <AssignmentIcon />
        <Box sx={{ flex: 1 }}>
          <DialogTitle sx={{ p: 0 }}>Create a new form</DialogTitle>
          <Typography variant="body2">Create deceivingly simple, yet insanely powerful surveys</Typography>
        </Box>
        <IconButton onClick={onClose} aria-label="Close" disabled={busy}>
          <CloseIcon />
        </IconButton>
      </Box>
      <DialogContent>
        <Stack spacing={2} sx={{ pt: 1 }}>
          {error && <Alert severity="error">{error}</Alert>}
          <TextField
            label="Name of the form"
            placeholder="e.g Recruiter feedback form"
            required
            autoFocus
            value={name}
            onChange={(e) => setName(e.target.value)}
            onBlur={() => setTouched(true)}
            error={touched && missing}
            helperText={touched && missing ? "Required field" : " "}
            inputProps={{ maxLength: 255 }}
          />
          <Box>
            <Typography variant="subtitle2" gutterBottom>
              Objective of the survey / Welcome text
            </Typography>
            <RichTextEditor value={welcome} onChange={setWelcome} />
          </Box>
        </Stack>
      </DialogContent>
      <DialogActions>
        <Button variant="contained" onClick={create} disabled={missing || busy}>
          Create
        </Button>
        <Button onClick={onClose} disabled={busy}>
          Cancel
        </Button>
      </DialogActions>
    </Dialog>
  );
}

export default function AdminSurveysPage() {
  const router = useRouter();
  const [status, setStatus] = useState("all");
  const [type, setType] = useState("all");
  const [search, setSearch] = useState("");
  const [term, setTerm] = useState("");
  const [page, setPage] = useState(1);
  const [data, setData] = useState(null);
  const [error, setError] = useState(null);
  const [success, setSuccess] = useState(null);
  const [creating, setCreating] = useState(false);
  const [removing, setRemoving] = useState({ open: false, survey: null }); // Delete confirmation (the survey stays while it closes)
  const [removeBusy, setRemoveBusy] = useState(false);

  useEffect(() => {
    const timer = setTimeout(() => {
      setTerm(search.trim());
      setPage(1);
    }, 300);
    return () => clearTimeout(timer);
  }, [search]);

  const load = useCallback(() => {
    const params = new URLSearchParams({ status, type, page: String(page) });
    if (term) params.set("search", term);
    return adminApi(`/admin/surveys?${params}`)
      .then(setData)
      .catch((e) => setError(e instanceof Error ? e.message : "Failed to load surveys."));
  }, [status, type, term, page]);

  useEffect(() => {
    load();
  }, [load]);

  const closeRemove = () => setRemoving((r) => ({ ...r, open: false }));

  const remove = async () => {
    const { survey } = removing;
    setRemoveBusy(true);
    try {
      setSuccess((await adminApi(`/admin/surveys/${survey.id}`, { method: "DELETE" })).message);
      closeRemove();
      await load();
    } catch (e) {
      setError(e instanceof Error ? e.message : "Could not delete the survey.");
      closeRemove();
    } finally {
      setRemoveBusy(false);
    }
  };

  const clone = async (survey) => {
    try {
      const response = await adminApi(`/admin/surveys/${survey.id}/clone`, { method: "POST" });
      router.push(`/admin/surveys/${response.survey.id}`);
    } catch (e) {
      setError(e instanceof Error ? e.message : "Could not clone the survey.");
    }
  };

  const surveys = data?.surveys ?? [];

  return (
    <>
      <PageHeader
        icon={<PollIcon />}
        title="Survey Forms"
        subtitle="Build a form, choose its audience and publish it to students. Answers are information only."
        backHref="/admin"
        backLabel="Back to Dashboard"
        actions={
          <Button variant="contained" color="secondary" startIcon={<AddIcon />} onClick={() => setCreating(true)}>
            Create New Survey
          </Button>
        }
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

      <Stack direction={{ xs: "column", sm: "row" }} spacing={1.5} sx={{ mb: 2 }}>
        <FormControl size="small" sx={{ minWidth: 150 }}>
          <InputLabel id="survey-status">Status</InputLabel>
          <Select
            labelId="survey-status"
            label="Status"
            value={status}
            onChange={(e) => {
              setStatus(e.target.value);
              setPage(1);
            }}
          >
            <MenuItem value="all">All</MenuItem>
            {Object.entries(SURVEY_STATUS).map(([k, v]) => (
              <MenuItem key={k} value={k}>
                {v}
              </MenuItem>
            ))}
          </Select>
        </FormControl>
        <FormControl size="small" sx={{ minWidth: 150 }}>
          <InputLabel id="survey-type">Type</InputLabel>
          <Select
            labelId="survey-type"
            label="Type"
            value={type}
            onChange={(e) => {
              setType(e.target.value);
              setPage(1);
            }}
          >
            <MenuItem value="all">All</MenuItem>
            {Object.entries(SURVEY_TYPES).map(([k, v]) => (
              <MenuItem key={k} value={k}>
                {v}
              </MenuItem>
            ))}
          </Select>
        </FormControl>
        <TextField size="small" label="Search" placeholder="Start typing survey name..." value={search} onChange={(e) => setSearch(e.target.value)} sx={{ flex: 1, maxWidth: { sm: 420 } }} />
      </Stack>

      {!data && (
        <Stack spacing={1.5}>
          {[0, 1, 2, 3, 4].map((i) => (
            <Skeleton key={i} variant="rounded" height={84} />
          ))}
        </Stack>
      )}
      {data && surveys.length === 0 && <Typography color="text.secondary">No surveys.</Typography>}
      <Stack spacing={1.5}>
        {surveys.map((survey) => (
          <Card key={survey.id}>
            <CardContent>
              <Stack direction={{ xs: "column", sm: "row" }} spacing={2} justifyContent="space-between" alignItems={{ sm: "center" }}>
                <Stack direction="row" spacing={1.5} sx={{ minWidth: 0 }}>
                  <AssignmentIcon color="primary" sx={{ mt: 0.25 }} />
                  <Box sx={{ minWidth: 0 }}>
                    <Typography fontWeight={700} sx={{ wordBreak: "break-word" }}>
                      {survey.title}
                    </Typography>
                    <Stack direction="row" spacing={1} alignItems="center" flexWrap="wrap" useFlexGap sx={{ my: 0.5 }}>
                      <Chip size="small" variant="outlined" label={SURVEY_TYPES[survey.survey_type] ?? survey.survey_type} />
                      {survey.is_public && <Chip size="small" variant="outlined" label="Public" />}
                      <Typography variant="caption" color="text.secondary">
                        {survey.question_count} question(s) · {survey.response_count} response(s)
                        {survey.deadline_at ? ` · Deadline ${formatDateTime(survey.deadline_at)}` : ""}
                        {survey.job_posting ? ` · ${survey.job_posting.label}` : ""}
                      </Typography>
                    </Stack>
                    <Stack direction="row" alignItems="center" flexWrap="wrap" useFlexGap divider={<Divider orientation="vertical" flexItem />} sx={{ "& .MuiButton-root": { textTransform: "none" } }}>
                      <Button size="small" startIcon={<VisibilityIcon />} onClick={() => router.push(`/admin/surveys/${survey.id}`)}>
                        View form
                      </Button>
                      {survey.status !== "archived" && (
                        <Button size="small" color="error" startIcon={<DeleteIcon />} onClick={() => setRemoving({ open: true, survey })}>
                          Delete
                        </Button>
                      )}
                      <Button size="small" startIcon={<DashboardIcon />} onClick={() => router.push(`/admin/surveys/${survey.id}/report`)}>
                        View Report
                      </Button>
                      <Button size="small" color="secondary" startIcon={<ContentCopyIcon />} onClick={() => clone(survey)}>
                        Clone
                      </Button>
                    </Stack>
                  </Box>
                </Stack>
                <StatusMark status={survey.status} />
              </Stack>
            </CardContent>
          </Card>
        ))}
      </Stack>
      {data?.meta?.last_page > 1 && (
        <Stack alignItems="center" sx={{ mt: 2 }}>
          <Pagination count={data.meta.last_page} page={data.meta.current_page} onChange={(_e, value) => setPage(value)} />
        </Stack>
      )}

      <ConfirmDialog
        open={removing.open}
        title={removing.survey?.status === "draft" ? "Delete this draft?" : "Archive this survey?"}
        message={
          removing.survey?.status === "draft"
            ? `Delete the draft "${removing.survey?.title}"? This cannot be undone.`
            : `Archive "${removing.survey?.title}"? Students will no longer see it. Its responses and report are kept.`
        }
        confirmLabel={removing.survey?.status === "draft" ? "Delete" : "Archive"}
        color="error"
        busy={removeBusy}
        onConfirm={remove}
        onCancel={closeRemove}
      />

      {creating && (
        <CreateDialog
          onClose={() => setCreating(false)}
          onCreated={(response) => {
            setCreating(false);
            try {
              sessionStorage.setItem("cdc-survey-created", String(response.survey.id)); // the builder shows "Success! New survey added."
            } catch {
              // storage unavailable
            }
            router.push(`/admin/surveys/${response.survey.id}`);
          }}
        />
      )}
    </>
  );
}
