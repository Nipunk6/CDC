"use client";

import { useEffect, useState } from "react";
import {
  Alert,
  Box,
  Button,
  Dialog,
  DialogActions,
  DialogContent,
  DialogTitle,
  Stack,
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TableRow,
  Typography,
} from "@mui/material";
import DownloadIcon from "@mui/icons-material/Download";
import UploadFileIcon from "@mui/icons-material/UploadFile";

import { adminDownload } from "@/lib/adminapi";
import { adminUpload } from "@/lib/adminupload";

// Upload an .xlsx/.csv, optionally preview with ?dry_run=1, then run for real and show the per-row report.
export default function SpreadsheetImportDialog({
  open,
  onClose,
  onDone,
  title,
  description,
  endpoint,
  templatePath,
  templateName,
  dryRun = false,
}) {
  const [file, setFile] = useState(null);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState(null);
  const [report, setReport] = useState(null);
  const [previewed, setPreviewed] = useState(false);

  useEffect(() => {
    if (open) {
      setFile(null);
      setError(null);
      setReport(null);
      setPreviewed(false);
    }
  }, [open]);

  const run = async (isDryRun) => {
    if (!file) {
      setError("Choose a file first.");
      return;
    }
    setBusy(true);
    setError(null);
    const body = new FormData();
    body.append("file", file);
    if (isDryRun) body.append("dry_run", "1");
    try {
      const response = await adminUpload(endpoint, body);
      setReport(response);
      if (isDryRun) {
        setPreviewed(true);
      } else {
        onDone?.(response);
      }
    } catch (e) {
      setError(e instanceof Error ? e.message : "Upload failed.");
    } finally {
      setBusy(false);
    }
  };

  const errors = report?.errors ?? [];
  const finished = report && report.dry_run === false;

  return (
    <Dialog open={open} onClose={() => !busy && onClose()} maxWidth="md" fullWidth>
      <DialogTitle>{title}</DialogTitle>
      <DialogContent dividers>
        <Stack spacing={2}>
          {description && (
            <Typography variant="body2" color="text.secondary">
              {description}
            </Typography>
          )}
          {templatePath && (
            <Box>
              <Button
                size="small"
                startIcon={<DownloadIcon />}
                onClick={() => adminDownload(templatePath, templateName).catch((e) => setError(e.message))}
              >
                Download template
              </Button>
            </Box>
          )}
          <Stack direction={{ xs: "column", sm: "row" }} spacing={1.5} alignItems={{ sm: "center" }}>
            <Button variant="outlined" component="label" startIcon={<UploadFileIcon />} disabled={busy}>
              Choose file
              <input
                hidden
                type="file"
                accept=".xlsx,.xls,.csv"
                onChange={(event) => {
                  setFile(event.target.files?.[0] ?? null);
                  setReport(null);
                  setPreviewed(false);
                }}
              />
            </Button>
            <Typography variant="body2" color="text.secondary" sx={{ wordBreak: "break-all" }}>
              {file ? file.name : "No file chosen (.xlsx, .xls or .csv, max 5 MB)"}
            </Typography>
          </Stack>

          {error && <Alert severity="error">{error}</Alert>}
          {report && (
            <Alert severity={errors.length ? "warning" : "success"}>{report.message}</Alert>
          )}

          {errors.length > 0 && (
            <TableContainer sx={{ maxHeight: 320, border: 1, borderColor: "divider", borderRadius: 1 }}>
              <Table size="small" stickyHeader>
                <TableHead>
                  <TableRow>
                    <TableCell>Row</TableCell>
                    <TableCell>Roll no</TableCell>
                    <TableCell>Field</TableCell>
                    <TableCell>Problem</TableCell>
                  </TableRow>
                </TableHead>
                <TableBody>
                  {errors.map((item, index) => (
                    <TableRow key={`${item.row}-${item.field}-${index}`}>
                      <TableCell>{item.row ?? "—"}</TableCell>
                      <TableCell>{item.roll_no || "—"}</TableCell>
                      <TableCell>{item.field ?? "—"}</TableCell>
                      <TableCell>{item.reason}</TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </TableContainer>
          )}
        </Stack>
      </DialogContent>
      <DialogActions>
        <Button onClick={onClose} disabled={busy}>
          {finished ? "Close" : "Cancel"}
        </Button>
        {dryRun && !finished && (
          <Button onClick={() => run(true)} disabled={busy || !file}>
            {busy && !previewed ? "Checking..." : "Check file"}
          </Button>
        )}
        {!finished && (
          <Button
            variant="contained"
            onClick={() => run(false)}
            disabled={busy || !file || (dryRun && (!previewed || (report?.valid_rows ?? 0) === 0))}
          >
            {dryRun && previewed ? `Import ${report?.valid_rows ?? 0} row(s)` : "Upload"}
          </Button>
        )}
      </DialogActions>
    </Dialog>
  );
}
