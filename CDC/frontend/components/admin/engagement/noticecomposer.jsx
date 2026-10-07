"use client";

import { useState } from "react";
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
  Stack,
  TextField,
  Typography,
} from "@mui/material";
import AttachFileIcon from "@mui/icons-material/AttachFile";

import { RichTextEditor, stripHtml } from "@/components/forms/shared";
import AudiencePicker, { NOTICE_AUDIENCES, cleanAudiences } from "@/components/admin/engagement/audiencepicker";
import { adminApi } from "@/lib/adminapi";
import { adminUpload } from "@/lib/adminupload";

const MAX_BYTES = 5 * 1024 * 1024;

/**
 * Create / edit a notice (Superset parity S7.1): title, rich text (shown to students as plain text, D76), an optional
 * PDF (≤5 MB) and the audience groups. "Publish" saves, then publishes, optionally emailing the audience (BCC batches).
 * Mount it with a `key` so each opening starts fresh.
 */
export default function NoticeComposer({ open, notice = null, initialAudiences = [], banner = null, onClose, onDone }) {
  const [title, setTitle] = useState(notice?.title ?? "");
  const [body, setBody] = useState(notice?.body ?? "");
  const [audiences, setAudiences] = useState(notice?.audiences?.map((a) => ({ audience_type: a.audience_type, audience_filter: a.audience_filter })) ?? initialAudiences);
  const [file, setFile] = useState(null);
  const [existing, setExisting] = useState(notice?.attachment ?? null);
  const [sendEmail, setSendEmail] = useState(false);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState(null);
  const published = Boolean(notice?.published_at);

  const pickFile = (event) => {
    const picked = event.target.files?.[0] ?? null;
    event.target.value = "";
    if (!picked) return;
    if (!/\.pdf$/i.test(picked.name)) {
      setError("The attachment must be a PDF.");
      return;
    }
    if (picked.size > MAX_BYTES) {
      setError("The attachment must be at most 5 MB.");
      return;
    }
    setError(null);
    setFile(picked);
  };

  const run = async (publish) => {
    if (!title.trim()) {
      setError("Title is required.");
      return;
    }
    setBusy(true);
    setError(null);
    try {
      const payload = { title: title.trim(), body: stripHtml(body).trim() ? body : null, audiences: cleanAudiences(audiences) };
      const saved = notice
        ? await adminApi(`/admin/notices/${notice.id}`, { method: "PUT", body: JSON.stringify(payload) })
        : await adminApi("/admin/notices", { method: "POST", body: JSON.stringify(payload) });
      const id = saved.notice.id;
      if (file) {
        const form = new FormData();
        form.append("file", file);
        await adminUpload(`/admin/notices/${id}/attachment`, form);
      } else if (notice?.attachment && !existing) {
        await adminApi(`/admin/notices/${id}/attachment`, { method: "DELETE" });
      }
      let message = saved.message;
      if (publish) {
        const response = await adminApi(`/admin/notices/${id}/publish`, { method: "POST", body: JSON.stringify({ send_email: sendEmail }) });
        message = response.message;
      }
      onDone(message);
    } catch (e) {
      setError(e instanceof Error ? e.message : "Could not save the notice.");
    } finally {
      setBusy(false);
    }
  };

  return (
    <Dialog open={open} onClose={() => !busy && onClose()} maxWidth="md" fullWidth>
      <DialogTitle>{notice ? "Edit Notice" : "Create Notice"}</DialogTitle>
      <DialogContent dividers>
        <Stack spacing={2}>
          {banner && <Alert severity="info">{banner}</Alert>}
          {error && <Alert severity="error">{error}</Alert>}
          {published && <Alert severity="info">This notice is already published; edits are not emailed again.</Alert>}
          <TextField label="Title" required value={title} onChange={(e) => setTitle(e.target.value)} inputProps={{ maxLength: 255 }} />
          <Box>
            <Typography variant="subtitle2" gutterBottom>
              Content
            </Typography>
            <RichTextEditor value={body} onChange={setBody} />
          </Box>
          <Stack direction="row" spacing={1} alignItems="center" flexWrap="wrap" useFlexGap>
            <Button component="label" variant="outlined" size="small" startIcon={<AttachFileIcon />} disabled={busy}>
              Add Attachment
              <input hidden type="file" accept="application/pdf,.pdf" onChange={pickFile} />
            </Button>
            {file && <Chip label={file.name} onDelete={() => setFile(null)} />}
            {!file && existing && <Chip label={existing.name} onDelete={() => setExisting(null)} />}
            <Typography variant="caption" color="text.secondary">
              PDF, at most 5 MB
            </Typography>
          </Stack>
          <AudiencePicker value={audiences} onChange={setAudiences} types={NOTICE_AUDIENCES} kind="notice" />
          {!published && (
            <FormControlLabel
              control={<Checkbox checked={sendEmail} onChange={(e) => setSendEmail(e.target.checked)} />}
              label="Also email this notice to the audience when publishing (BCC batches, institute addresses)"
            />
          )}
        </Stack>
      </DialogContent>
      <DialogActions sx={{ flexWrap: "wrap", gap: 1 }}>
        <Button onClick={onClose} disabled={busy}>
          Cancel
        </Button>
        <Button variant="outlined" onClick={() => run(false)} disabled={busy}>
          {published ? "Save" : "Save Draft"}
        </Button>
        {!published && (
          <Button variant="contained" onClick={() => run(true)} disabled={busy || audiences.length === 0}>
            Publish
          </Button>
        )}
      </DialogActions>
    </Dialog>
  );
}
