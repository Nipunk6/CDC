"use client";

import { useEffect, useState } from "react";
import {
  Alert,
  Box,
  Button,
  Checkbox,
  Chip,
  Dialog,
  DialogActions,
  DialogContent,
  DialogTitle,
  FormControlLabel,
  FormGroup,
  Stack,
  TextField,
  Typography,
} from "@mui/material";
import AttachFileIcon from "@mui/icons-material/AttachFile";

import ConfirmDialog from "@/components/admin/engagement/confirmdialog";
import { RichTextEditor, stripHtml } from "@/components/forms/shared";
import { adminApi } from "@/lib/adminapi";
import { adminUpload } from "@/lib/adminupload";

const MAX_BYTES = 5 * 1024 * 1024;

/**
 * "Send Email to Shortlisted / On Hold" (Superset parity S7.2): a free-text mail to the stage's PUBLISHED shortlisted
 * and/or on-hold students, sent in BCC batches. Draft decisions are never mailed (enforced on the server too).
 * Mount it with a `key` so each opening starts fresh.
 */
export default function StageEmailDialog({ open, postingId, round, onClose, onDone }) {
  const [counts, setCounts] = useState(null);
  const [results, setResults] = useState(["selected", "waitlisted"]);
  const [subject, setSubject] = useState("");
  const [message, setMessage] = useState("");
  const [file, setFile] = useState(null);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState(null);
  const [confirming, setConfirming] = useState(false);

  useEffect(() => {
    let cancelled = false;
    adminApi(`/admin/postings/${postingId}/rounds/${round.id}/message-audience`)
      .then((r) => !cancelled && setCounts(r))
      .catch(() => !cancelled && setCounts({ selected: 0, waitlisted: 0 }));
    return () => {
      cancelled = true;
    };
  }, [postingId, round.id]);

  const shortlistedLabel = round.is_final ? "Selected" : "Shortlisted";
  const total = counts ? results.reduce((sum, r) => sum + (counts[r] ?? 0), 0) : 0;
  const toggle = (value) => setResults((rs) => (rs.includes(value) ? rs.filter((r) => r !== value) : [...rs, value]));

  const pickFile = (event) => {
    const picked = event.target.files?.[0] ?? null;
    event.target.value = "";
    if (!picked) return;
    if (!/\.(pdf|jpe?g|png)$/i.test(picked.name)) {
      setError("Attach a PDF or an image (JPG/PNG).");
      return;
    }
    if (picked.size > MAX_BYTES) {
      setError("The attachment must be at most 5 MB.");
      return;
    }
    setError(null);
    setFile(picked);
  };

  const review = () => {
    if (!subject.trim() || !stripHtml(message).trim()) {
      setError("Subject and message are required.");
      return;
    }
    setConfirming(true);
  };

  const send = async () => {
    setConfirming(false);
    setBusy(true);
    setError(null);
    try {
      const form = new FormData();
      form.append("subject", subject.trim());
      form.append("message", message);
      results.forEach((r) => form.append("results[]", r));
      if (file) form.append("attachment", file);
      const response = await adminUpload(`/admin/postings/${postingId}/rounds/${round.id}/email`, form);
      onDone(response.message);
    } catch (e) {
      setError(e instanceof Error ? e.message : "Could not send the email.");
    } finally {
      setBusy(false);
    }
  };

  return (
    <Dialog open={open} onClose={() => !busy && onClose()} maxWidth="md" fullWidth>
      <DialogTitle>Send Email</DialogTitle>
      <DialogContent dividers>
        <Stack spacing={2}>
          <Alert severity="info">
            This message will be sent to the students whose decision in “{round.name}” is published as {results.map((r) => (r === "selected" ? shortlistedLabel : "On Hold")).join(" or ") || "—"}{" "}
            ({counts ? total : "…"} student(s)), in BCC batches to their institute addresses.
          </Alert>
          {error && <Alert severity="error">{error}</Alert>}
          <FormGroup row>
            <FormControlLabel control={<Checkbox checked={results.includes("selected")} onChange={() => toggle("selected")} />} label={`${shortlistedLabel} (${counts?.selected ?? "…"})`} />
            <FormControlLabel control={<Checkbox checked={results.includes("waitlisted")} onChange={() => toggle("waitlisted")} />} label={`On Hold (${counts?.waitlisted ?? "…"})`} />
          </FormGroup>
          <TextField label="Subject" required value={subject} onChange={(e) => setSubject(e.target.value)} inputProps={{ maxLength: 200 }} />
          <Box>
            <Typography variant="subtitle2" gutterBottom>
              Message
            </Typography>
            <RichTextEditor value={message} onChange={setMessage} />
          </Box>
          <Stack direction="row" spacing={1} alignItems="center" flexWrap="wrap" useFlexGap>
            <Button component="label" variant="outlined" size="small" startIcon={<AttachFileIcon />} disabled={busy}>
              Add Attachment
              <input hidden type="file" accept="application/pdf,.pdf,image/jpeg,image/png" onChange={pickFile} />
            </Button>
            {file && <Chip label={file.name} onDelete={() => setFile(null)} />}
            <Typography variant="caption" color="text.secondary">
              PDF or image, at most 5 MB
            </Typography>
          </Stack>
        </Stack>
      </DialogContent>
      <DialogActions>
        <Button onClick={onClose} disabled={busy}>
          Cancel
        </Button>
        <Button variant="contained" onClick={review} disabled={busy || results.length === 0 || total === 0}>
          Send Email
        </Button>
      </DialogActions>
      <ConfirmDialog
        open={confirming}
        title="Send this email?"
        message={`Email ${total} student(s) now, in BCC batches?`}
        confirmLabel="Send Email"
        onConfirm={send}
        onCancel={() => setConfirming(false)}
      />
    </Dialog>
  );
}
