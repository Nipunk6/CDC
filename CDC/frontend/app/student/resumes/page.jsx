"use client";

import { useCallback, useEffect, useState } from "react";
import {
  Alert,
  Box,
  Button,
  Card,
  CardActions,
  CardContent,
  Chip,
  Dialog,
  DialogActions,
  DialogContent,
  DialogTitle,
  Grid2 as Grid,
  IconButton,
  LinearProgress,
  Stack,
  TextField,
  Tooltip,
  Typography,
} from "@mui/material";
import UploadFileIcon from "@mui/icons-material/UploadFile";
import DeleteOutlineIcon from "@mui/icons-material/DeleteOutline";
import LockIcon from "@mui/icons-material/Lock";
import VisibilityIcon from "@mui/icons-material/Visibility";
import DescriptionIcon from "@mui/icons-material/Description";

import { studentApi, studentBlobUrl, studentUpload } from "@/lib/studentapi";
import { formatDateTime, statusColor, titleCase } from "@/lib/format";

const MAX_BYTES = 2 * 1024 * 1024;

export default function StudentResumesPage() {
  const [resumes, setResumes] = useState([]);
  const [maxSlots, setMaxSlots] = useState(8);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [success, setSuccess] = useState(null);

  const [dialog, setDialog] = useState(null); // { slot, label, file, existing }
  const [saving, setSaving] = useState(false);
  const [dialogError, setDialogError] = useState(null);

  const load = useCallback(async () => {
    try {
      const response = await studentApi("/student/resumes");
      setResumes(response.resumes ?? []);
      setMaxSlots(response.max_slots ?? 8);
      setError(null);
    } catch (e) {
      setError(e instanceof Error ? e.message : "Failed to load your resumes.");
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    void load();
  }, [load]);

  const bySlot = Object.fromEntries(resumes.map((r) => [r.slot, r]));

  const openUpload = (slot) => {
    const existing = bySlot[slot];
    setDialogError(null);
    setDialog({ slot, label: existing?.label ?? "", file: null, existing });
  };

  const handleUpload = async () => {
    if (!dialog?.file || !dialog.label.trim()) return;
    if (dialog.file.size > MAX_BYTES) {
      setDialogError("The resume must be 2 MB or smaller.");
      return;
    }
    setSaving(true);
    setDialogError(null);
    const body = new FormData();
    body.append("slot", String(dialog.slot));
    body.append("label", dialog.label.trim());
    body.append("file", dialog.file);
    try {
      const response = await studentUpload("/student/resumes", body);
      setSuccess(response.message);
      setDialog(null);
      await load();
    } catch (e) {
      setDialogError(e instanceof Error ? e.message : "Upload failed.");
    } finally {
      setSaving(false);
    }
  };

  const handleDelete = async (resume) => {
    if (!window.confirm(`Delete "${resume.label}"?`)) return;
    try {
      const response = await studentApi(`/student/resumes/${resume.id}`, { method: "DELETE" });
      setSuccess(response.message);
      await load();
    } catch (e) {
      setError(e instanceof Error ? e.message : "Could not delete the resume.");
    }
  };

  const handleView = async (resume) => {
    // Open the tab synchronously so pop-up blockers allow it, then point it at the blob.
    const tab = window.open("", "_blank");
    try {
      const url = await studentBlobUrl(`/student/resumes/${resume.id}/file`);
      if (tab) {
        tab.location.href = url;
      } else {
        window.location.href = url;
      }
      // The new tab has loaded the blob by then; release our copy.
      setTimeout(() => URL.revokeObjectURL(url), 60 * 1000);
    } catch (e) {
      tab?.close();
      setError(e instanceof Error ? e.message : "Could not open the resume.");
    }
  };

  return (
    <Stack spacing={3}>
      <Box>
        <Typography variant="h4" color="primary.main" fontWeight={700}>
          My Resumes
        </Typography>
        <Typography color="text.secondary">
          Up to {maxSlots} resumes · PDF only · max 2 MB each. Every upload is verified by the CDC before companies see it
          as verified.
        </Typography>
      </Box>

      {error && (
        <Alert severity="error" onClose={() => setError(null)}>
          {error}
        </Alert>
      )}
      {success && (
        <Alert severity="success" onClose={() => setSuccess(null)}>
          {success}
        </Alert>
      )}
      {loading && <LinearProgress />}

      <Grid container spacing={2}>
        {Array.from({ length: maxSlots }, (_, index) => index + 1).map((slot) => {
          const resume = bySlot[slot];
          return (
            <Grid key={slot} size={{ xs: 12, sm: 6, lg: 3 }}>
              <Card
                variant="outlined"
                sx={{
                  height: "100%",
                  display: "flex",
                  flexDirection: "column",
                  borderStyle: resume ? "solid" : "dashed",
                }}
              >
                <CardContent sx={{ flex: 1 }}>
                  <Stack direction="row" justifyContent="space-between" alignItems="center" sx={{ mb: 1 }}>
                    <Typography variant="overline" color="text.secondary">
                      Slot {slot}
                    </Typography>
                    {resume?.is_locked && (
                      <Tooltip title="Attached to an application that is still in progress. It cannot be replaced or deleted until that process ends.">
                        <LockIcon fontSize="small" color="action" />
                      </Tooltip>
                    )}
                  </Stack>
                  {resume ? (
                    <Stack spacing={1}>
                      <Stack direction="row" spacing={1} alignItems="center">
                        <DescriptionIcon color="primary" />
                        <Typography fontWeight={700} sx={{ wordBreak: "break-word" }}>
                          {resume.label}
                        </Typography>
                      </Stack>
                      <Box>
                        <Chip
                          size="small"
                          variant="outlined"
                          color={statusColor(resume.status)}
                          label={
                            resume.status === "pending" ? "Pending verification" : resume.status === "approved" ? "Verified" : titleCase(resume.status)
                          }
                        />
                      </Box>
                      {resume.status === "rejected" && resume.admin_remark && (
                        <Alert severity="warning" sx={{ py: 0 }}>
                          {resume.admin_remark}
                        </Alert>
                      )}
                      <Typography variant="caption" color="text.secondary">
                        Updated {formatDateTime(resume.updated_at)} · {Math.max(1, Math.round((resume.file_size ?? 0) / 1024))} KB
                      </Typography>
                    </Stack>
                  ) : (
                    <Typography color="text.secondary" variant="body2">
                      Empty slot
                    </Typography>
                  )}
                </CardContent>
                <CardActions sx={{ justifyContent: "space-between" }}>
                  <Button
                    size="small"
                    startIcon={<UploadFileIcon />}
                    disabled={resume?.is_locked}
                    onClick={() => openUpload(slot)}
                  >
                    {resume ? "Replace" : "Upload"}
                  </Button>
                  {resume && (
                    <Stack direction="row">
                      <Tooltip title="View">
                        <IconButton size="small" onClick={() => handleView(resume)}>
                          <VisibilityIcon fontSize="small" />
                        </IconButton>
                      </Tooltip>
                      <Tooltip title={resume.is_locked ? "Locked" : "Delete"}>
                        <span>
                          <IconButton size="small" color="error" disabled={resume.is_locked} onClick={() => handleDelete(resume)}>
                            <DeleteOutlineIcon fontSize="small" />
                          </IconButton>
                        </span>
                      </Tooltip>
                    </Stack>
                  )}
                </CardActions>
              </Card>
            </Grid>
          );
        })}
      </Grid>

      <Dialog open={Boolean(dialog)} onClose={() => !saving && setDialog(null)} maxWidth="xs" fullWidth>
        <DialogTitle>{dialog?.existing ? `Replace slot ${dialog?.slot}` : `Upload to slot ${dialog?.slot}`}</DialogTitle>
        <DialogContent>
          <Stack spacing={2} sx={{ pt: 1 }}>
            {dialogError && <Alert severity="error">{dialogError}</Alert>}
            {dialog?.existing && (
              <Alert severity="info">Replacing a resume resets it to pending verification.</Alert>
            )}
            <TextField
              label="Label"
              placeholder="e.g. Software, Data Science, Core"
              fullWidth
              inputProps={{ maxLength: 60 }}
              value={dialog?.label ?? ""}
              onChange={(event) => setDialog((prev) => ({ ...prev, label: event.target.value }))}
            />
            <Button variant="outlined" component="label" startIcon={<UploadFileIcon />}>
              {dialog?.file ? dialog.file.name : "Choose PDF"}
              <input
                hidden
                type="file"
                accept="application/pdf"
                onChange={(event) => setDialog((prev) => ({ ...prev, file: event.target.files?.[0] ?? null }))}
              />
            </Button>
            {dialog?.file && dialog.file.size > MAX_BYTES && (
              <Alert severity="error">This file is larger than 2 MB.</Alert>
            )}
          </Stack>
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setDialog(null)} disabled={saving}>
            Cancel
          </Button>
          <Button
            variant="contained"
            onClick={handleUpload}
            disabled={saving || !dialog?.file || !dialog?.label.trim() || dialog.file.size > MAX_BYTES}
          >
            {saving ? "Uploading..." : "Upload"}
          </Button>
        </DialogActions>
      </Dialog>
    </Stack>
  );
}
