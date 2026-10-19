"use client";

import { useState } from "react";
import {
  Alert,
  AlertTitle,
  Box,
  Button,
  Dialog,
  DialogActions,
  DialogContent,
  DialogTitle,
  Stack,
  Typography,
} from "@mui/material";
import DownloadIcon from "@mui/icons-material/Download";
import UploadFileIcon from "@mui/icons-material/UploadFile";

import { adminDownload } from "@/lib/adminapi";
import { adminUpload } from "@/lib/adminupload";

/**
 * P6.2 stage attendance by Excel: download the stage's sheet (its students, with attendance already marked), fill
 * Present / Absent, upload it for a preview, then save. A blank cell leaves the student as they were.
 */
export default function AttendanceUploadDialog({ postingId, round, open, onClose, onDone }) {
  const [file, setFile] = useState(null);
  const [preview, setPreview] = useState(null);
  const [error, setError] = useState(null);
  const [busy, setBusy] = useState(false);
  const path = `/admin/postings/${postingId}/rounds/${round.id}/attendance`;

  const send = async (dryRun) => {
    setBusy(true);
    setError(null);
    try {
      const body = new FormData();
      body.append("file", file);
      body.append("dry_run", dryRun ? "1" : "0");
      const response = await adminUpload(`${path}/upload`, body);
      if (dryRun) setPreview(response);
      else onDone(response);
    } catch (e) {
      setError(e instanceof Error ? e.message : "Could not read the sheet.");
    } finally {
      setBusy(false);
    }
  };

  const download = async () => {
    setError(null);
    try {
      await adminDownload(`${path}/template`, `attendance-${round.name}.xlsx`);
    } catch (e) {
      setError(e instanceof Error ? e.message : "Could not download the sheet.");
    }
  };

  return (
    <Dialog open={open} onClose={() => !busy && onClose()} maxWidth="sm" fullWidth>
      <DialogTitle>Upload attendance — {round.name}</DialogTitle>
      <DialogContent>
        <Stack spacing={2} sx={{ pt: 1 }}>
          {error && <Alert severity="error">{error}</Alert>}
          <Typography variant="body2" color="text.secondary">
            1. Download this stage&apos;s sheet. 2. Write <strong>Present</strong> or <strong>Absent</strong> in the Attendance column (a blank cell leaves the
            student as they are). 3. Upload it and check the preview before saving. The company sees attendance once the stage&apos;s results are
            published; you can still correct it afterwards.
          </Typography>
          <Box>
            <Button variant="outlined" startIcon={<DownloadIcon />} onClick={download}>
              Download attendance sheet
            </Button>
          </Box>
          <Box>
            <Button variant="outlined" component="label" startIcon={<UploadFileIcon />}>
              {file ? file.name : "Choose the filled sheet (.xlsx / .csv)"}
              <input
                hidden
                type="file"
                accept=".xlsx,.xls,.csv"
                onChange={(e) => {
                  setFile(e.target.files?.[0] ?? null);
                  setPreview(null);
                }}
              />
            </Button>
          </Box>
          {preview && preview.present + preview.absent === 0 && preview.errors.length === 0 && (
            <Alert severity="success">
              <AlertTitle>Nothing to change</AlertTitle>
              Every mark in this sheet ({preview.unchanged}) is already saved. To change attendance, edit Present / Absent in the sheet and upload it
              again.
            </Alert>
          )}
          {preview && (preview.present + preview.absent > 0 || preview.errors.length > 0) && (
            <Alert severity={preview.errors.length ? "warning" : "info"}>
              <AlertTitle>
                Preview — {preview.present + preview.absent > 0 ? 'check it, then click "Save attendance" below' : "nothing can be saved from this sheet"}
              </AlertTitle>
              <Typography variant="body2">
                {preview.present} present · {preview.absent} absent · {preview.unchanged} already as marked
              </Typography>
              {preview.corrected.length > 0 && (
                <Typography variant="body2" sx={{ mt: 0.5 }}>
                  Corrects {preview.corrected.length} attendance mark(s) the company already sees:{" "}
                  {preview.corrected.map((c) => `${c.roll_no} (${c.from === "yes" ? "Present" : "Absent"} → ${c.to === "yes" ? "Present" : "Absent"})`).join(", ")}
                </Typography>
              )}
              {[...preview.errors, ...preview.warnings].length > 0 && (
                <Box component="ul" sx={{ m: 0, mt: 1, pl: 2.5, maxHeight: 180, overflowY: "auto" }}>
                  {preview.errors.map((e, i) => (
                    <li key={`e${i}`}>
                      <Typography variant="body2">
                        {e.roll_no}: {e.reason} (not saved)
                      </Typography>
                    </li>
                  ))}
                  {preview.warnings.map((w, i) => (
                    <li key={`w${i}`}>
                      <Typography variant="body2">
                        {w.roll_no}: {w.reason}
                      </Typography>
                    </li>
                  ))}
                </Box>
              )}
            </Alert>
          )}
        </Stack>
      </DialogContent>
      <DialogActions>
        <Button onClick={onClose} disabled={busy}>
          Cancel
        </Button>
        {!preview ? (
          <Button variant="contained" onClick={() => send(true)} disabled={!file || busy}>
            Preview
          </Button>
        ) : (
          <Button variant="contained" onClick={() => send(false)} disabled={busy || preview.present + preview.absent === 0}>
            Save attendance
          </Button>
        )}
      </DialogActions>
    </Dialog>
  );
}
