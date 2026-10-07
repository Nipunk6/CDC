"use client";

import { useEffect, useState } from "react";
import { Alert, Button, IconButton, LinearProgress, List, ListItem, ListItemIcon, ListItemText, Stack, TextField, Tooltip, Typography } from "@mui/material";
import PictureAsPdfIcon from "@mui/icons-material/PictureAsPdf";
import DeleteOutlineIcon from "@mui/icons-material/DeleteOutline";
import UploadFileIcon from "@mui/icons-material/UploadFile";
import OpenInNewIcon from "@mui/icons-material/OpenInNew";

import { adminApi } from "@/lib/adminapi";
import { adminBlobUrl, adminUpload } from "@/lib/adminupload";
import { formatDateTime } from "@/lib/format";

/**
 * Attached Documents (Superset parity S6.4): PDFs (JD, company deck, at most 5 MB) that students who can see the job
 * profile may download.
 */
export default function DocumentsTab({ posting, onMessage }) {
  const [documents, setDocuments] = useState(null);
  const [version, setVersion] = useState(0);
  const [title, setTitle] = useState("");
  const [file, setFile] = useState(null);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState(null);

  useEffect(() => {
    let cancelled = false;
    adminApi(`/admin/postings/${posting.id}/documents`)
      .then((r) => !cancelled && setDocuments(r.documents))
      .catch((e) => !cancelled && setError(e.message));
    return () => {
      cancelled = true;
    };
  }, [posting.id, version]);

  const upload = async () => {
    setBusy(true);
    setError(null);
    try {
      const body = new FormData();
      body.append("title", title.trim());
      body.append("file", file);
      const response = await adminUpload(`/admin/postings/${posting.id}/documents`, body);
      onMessage?.(response.message);
      setTitle("");
      setFile(null);
      setVersion((v) => v + 1);
    } catch (e) {
      setError(e instanceof Error ? e.message : "Upload failed.");
    } finally {
      setBusy(false);
    }
  };

  const remove = async (doc) => {
    if (!window.confirm(`Remove "${doc.title}"? Students will no longer see it.`)) return;
    try {
      const response = await adminApi(`/admin/postings/${posting.id}/documents/${doc.id}`, { method: "DELETE" });
      onMessage?.(response.message);
      setVersion((v) => v + 1);
    } catch (e) {
      setError(e instanceof Error ? e.message : "Could not remove the document.");
    }
  };

  const view = async (doc) => {
    try {
      const url = await adminBlobUrl(`/admin/postings/${posting.id}/documents/${doc.id}`);
      window.open(url, "_blank", "noopener");
      setTimeout(() => URL.revokeObjectURL(url), 60000);
    } catch (e) {
      setError(e instanceof Error ? e.message : "Could not open the document.");
    }
  };

  return (
    <Stack spacing={2}>
      <Typography variant="body2" color="text.secondary">
        PDFs such as the job description or the company deck. Students who can see this job profile can download them.
      </Typography>
      {error && <Alert severity="error">{error}</Alert>}
      <Stack direction={{ xs: "column", sm: "row" }} spacing={1} alignItems={{ sm: "center" }}>
        <TextField size="small" label="Title" value={title} onChange={(e) => setTitle(e.target.value)} inputProps={{ maxLength: 150 }} sx={{ flex: 1 }} />
        <Button variant="outlined" component="label" startIcon={<UploadFileIcon />}>
          {file ? file.name : "Choose PDF (max 5 MB)"}
          <input hidden type="file" accept="application/pdf,.pdf" onChange={(e) => setFile(e.target.files?.[0] ?? null)} />
        </Button>
        <Button variant="contained" onClick={upload} disabled={busy || !file || !title.trim()}>
          Attach
        </Button>
      </Stack>
      {!documents && !error && <LinearProgress />}
      {documents?.length === 0 && (
        <Typography variant="body2" color="text.secondary">
          No documents attached.
        </Typography>
      )}
      <List disablePadding>
        {(documents ?? []).map((doc) => (
          <ListItem
            key={doc.id}
            divider
            secondaryAction={
              <Stack direction="row">
                <Tooltip title="Open">
                  <IconButton onClick={() => view(doc)} aria-label={`Open ${doc.title}`}>
                    <OpenInNewIcon fontSize="small" />
                  </IconButton>
                </Tooltip>
                <Tooltip title="Remove">
                  <IconButton color="error" onClick={() => remove(doc)} aria-label={`Remove ${doc.title}`}>
                    <DeleteOutlineIcon fontSize="small" />
                  </IconButton>
                </Tooltip>
              </Stack>
            }
            sx={{ pr: 12 }}
          >
            <ListItemIcon sx={{ minWidth: 40 }}>
              <PictureAsPdfIcon color="error" />
            </ListItemIcon>
            <ListItemText
              primary={doc.title}
              primaryTypographyProps={{ noWrap: true, fontWeight: 600 }}
              secondary={`${Math.max(1, Math.round(doc.file_size / 1024))} KB · ${formatDateTime(doc.created_at)}${doc.uploaded_by ? ` · ${doc.uploaded_by}` : ""}`}
            />
          </ListItem>
        ))}
      </List>
    </Stack>
  );
}
