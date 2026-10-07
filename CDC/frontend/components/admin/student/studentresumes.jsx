"use client";

import { useState } from "react";
import {
  Alert,
  Box,
  Button,
  Chip,
  Dialog,
  DialogActions,
  DialogContent,
  DialogTitle,
  Paper,
  Stack,
  TextField,
  Typography,
} from "@mui/material";
import CheckIcon from "@mui/icons-material/Check";
import CloseIcon from "@mui/icons-material/Close";
import DoneAllIcon from "@mui/icons-material/DoneAll";
import OpenInNewIcon from "@mui/icons-material/OpenInNew";
import VisibilityIcon from "@mui/icons-material/Visibility";

import { adminApi } from "@/lib/adminapi";
import { formatDateTime, statusColor, titleCase } from "@/lib/format";

// The signed preview URL goes through the session-gated Next.js PDF proxy (M0.3d), as on /admin/resumes.
const proxied = (url) => `/api/proxy-pdf?url=${encodeURIComponent(url)}`;
const statusLabel = (status) => (status === "approved" ? "Verified" : titleCase(status));

// "Resumes & Documents" (Superset parity S4.5). Decisions use the same endpoint and race guard as the queue.
export default function StudentResumes({ student, onChanged }) {
  const resumes = student.resumes ?? [];
  const pending = resumes.filter((r) => r.status === "pending");
  const [previewId, setPreviewId] = useState(null);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState(null);
  const [success, setSuccess] = useState(null);
  const [rejecting, setRejecting] = useState(null);
  const [remark, setRemark] = useState("");
  const [dialogError, setDialogError] = useState(null);

  const decide = async (resume, status, adminRemark = null) => {
    setBusy(true);
    setError(null);
    setDialogError(null);
    try {
      const response = await adminApi(`/admin/resumes/${resume.id}`, {
        method: "PATCH",
        body: JSON.stringify({ status, admin_remark: adminRemark, expected_updated_at: resume.updated_at }),
      });
      setSuccess(`${response.message} (${resume.label})`);
      setRejecting(null);
      await onChanged?.();
    } catch (e) {
      const message = e instanceof Error ? e.message : "Failed to update the resume.";
      if (rejecting) setDialogError(message);
      else setError(message);
      // A 409 means the student uploaded a new file: reload so the preview shows it.
      await onChanged?.();
    } finally {
      setBusy(false);
    }
  };

  const verifyAll = async () => {
    if (!window.confirm(`Mark ${pending.length} pending resume(s) as verified?`)) return;
    setBusy(true);
    setError(null);
    try {
      const response = await adminApi(`/admin/students/${student.id}/resumes/verify-all`, {
        method: "POST",
        body: JSON.stringify({ resumes: pending.map((r) => ({ id: r.id, expected_updated_at: r.updated_at })) }),
      });
      setSuccess(response.message);
      await onChanged?.();
    } catch (e) {
      setError(e instanceof Error ? e.message : "Failed to verify the resumes.");
    } finally {
      setBusy(false);
    }
  };

  return (
    <Stack spacing={2}>
      <Stack direction={{ xs: "column", sm: "row" }} spacing={1} justifyContent="space-between" alignItems={{ sm: "center" }}>
        <Typography variant="subtitle1" fontWeight={700}>
          Resume
        </Typography>
        <Button variant="contained" color="success" size="small" startIcon={<DoneAllIcon />} disabled={busy || pending.length === 0} onClick={verifyAll}>
          Mark all as verified
        </Button>
      </Stack>
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
      {resumes.length === 0 && <Typography color="text.secondary">The student has not uploaded a resume yet.</Typography>}

      {resumes.map((resume) => (
        <Paper key={resume.id} variant="outlined" sx={{ p: 2 }}>
          <Stack direction={{ xs: "column", md: "row" }} spacing={1.5} justifyContent="space-between">
            <Box sx={{ minWidth: 0 }}>
              <Stack direction="row" spacing={1} alignItems="center" flexWrap="wrap" useFlexGap>
                <Typography fontWeight={600} sx={{ wordBreak: "break-word" }}>
                  Slot {resume.slot}: {resume.label}
                </Typography>
                <Chip size="small" variant="outlined" color={statusColor(resume.status)} label={statusLabel(resume.status)} />
              </Stack>
              <Typography variant="body2" color="text.secondary">
                Uploaded {formatDateTime(resume.updated_at)}
              </Typography>
              {resume.admin_remark && (
                <Typography variant="body2" color="text.secondary" sx={{ wordBreak: "break-word" }}>
                  Remark: {resume.admin_remark} {resume.reviewed_by ? `— ${resume.reviewed_by.name}` : ""}
                </Typography>
              )}
            </Box>
            <Stack direction="row" spacing={1} alignItems="flex-start" flexWrap="wrap" useFlexGap>
              <Button
                size="small"
                variant="contained"
                color="success"
                startIcon={<CheckIcon />}
                disabled={busy || resume.status === "approved"}
                onClick={() => decide(resume, "approved")}
              >
                Mark as verified
              </Button>
              <Button
                size="small"
                variant="outlined"
                color="error"
                startIcon={<CloseIcon />}
                disabled={busy}
                onClick={() => {
                  setRemark("");
                  setDialogError(null);
                  setRejecting(resume);
                }}
              >
                Reject
              </Button>
              <Button size="small" startIcon={<VisibilityIcon />} onClick={() => setPreviewId((id) => (id === resume.id ? null : resume.id))}>
                {previewId === resume.id ? "Hide" : "Preview"}
              </Button>
              <Button size="small" component="a" href={proxied(resume.preview_url)} target="_blank" rel="noopener" startIcon={<OpenInNewIcon />}>
                Open
              </Button>
            </Stack>
          </Stack>
          {previewId === resume.id && (
            <Box
              component="iframe"
              title={`Resume preview: ${resume.label}`}
              src={proxied(resume.preview_url)}
              sx={{ mt: 2, width: "100%", height: { xs: 480, md: 720 }, border: 1, borderColor: "divider", borderRadius: 1 }}
            />
          )}
        </Paper>
      ))}

      <Dialog open={rejecting !== null} onClose={() => !busy && setRejecting(null)} maxWidth="sm" fullWidth>
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
          <Button onClick={() => setRejecting(null)} disabled={busy}>
            Cancel
          </Button>
          <Button color="error" variant="contained" disabled={busy || !remark.trim()} onClick={() => decide(rejecting, "rejected", remark.trim())}>
            Reject
          </Button>
        </DialogActions>
      </Dialog>
    </Stack>
  );
}
