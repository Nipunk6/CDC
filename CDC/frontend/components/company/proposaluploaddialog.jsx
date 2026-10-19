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
  FormControl,
  InputLabel,
  MenuItem,
  Select,
  Stack,
  Typography,
} from "@mui/material";
import DownloadIcon from "@mui/icons-material/Download";
import UploadFileIcon from "@mui/icons-material/UploadFile";

import { companyDownload } from "@/lib/companydownload";
import { companyUpload } from "@/lib/companyupload";

const KIND_LABELS = { shortlist: "Shortlist", waitlist: "On Hold", addendum: "Addendum", replacement_request: "Replacement request" };

/**
 * Company → "Upload from Excel" (P3-D56). Two ways in:
 * - Stage decisions: download the stage's sheet (a Shortlisted / On Hold dropdown in the Decision column), fill it,
 *   upload it; Shortlisted rows become a Shortlist proposal and On Hold rows an On Hold proposal.
 * - A roll-number list for one proposal type (used for addenda and replacement requests).
 * Every upload is previewed first; rows that cannot be used are skipped and listed. The CDC still approves everything.
 */
export default function ProposalUploadDialog({ posting, completed, open, onClose, onSent }) {
  const rounds = posting.rounds ?? [];
  const defaultRound = completed ? rounds.at(-1) : rounds.find((r) => r.status !== "completed") ?? rounds[0];
  const [mode, setMode] = useState(completed ? "list" : "decisions");
  const [kind, setKind] = useState(completed ? "replacement_request" : "addendum");
  const [roundId, setRoundId] = useState(String(defaultRound?.id ?? ""));
  const [file, setFile] = useState(null);
  const [preview, setPreview] = useState(null);
  const [error, setError] = useState(null);
  const [busy, setBusy] = useState(false);

  const round = rounds.find((r) => String(r.id) === roundId);
  const reset = () => {
    setPreview(null);
    setError(null);
  };

  const send = async (dryRun) => {
    setBusy(true);
    setError(null);
    try {
      const body = new FormData();
      body.append("file", file);
      body.append("dry_run", dryRun ? "1" : "0");
      if (mode === "list") body.append("kind", kind);
      const path = `/company/postings/${posting.id}/rounds/${roundId}/${mode === "decisions" ? "decision-sheet" : "proposals/upload"}`;
      const response = await companyUpload(path, body);
      if (dryRun) setPreview(response);
      else onSent(response);
    } catch (e) {
      setError(e instanceof Error ? e.message : "Could not read the file.");
      setPreview(e?.payload?.errors ? e.payload : null);
    } finally {
      setBusy(false);
    }
  };

  const downloadSheet = async () => {
    setError(null);
    try {
      await companyDownload(`/company/postings/${posting.id}/rounds/${roundId}/decision-sheet`, `decisions-${round?.name ?? "stage"}.xlsx`);
    } catch (e) {
      setError(e instanceof Error ? e.message : "Could not download the sheet.");
    }
  };

  const usable = preview && !preview.message?.startsWith("Nothing to send") && (mode === "decisions" ? preview.shortlisted + preview.on_hold > 0 : preview.students > 0);

  return (
    <Dialog open={open} onClose={() => !busy && onClose()} maxWidth="sm" fullWidth>
      <DialogTitle>Upload from Excel</DialogTitle>
      <DialogContent>
        <Stack spacing={2} sx={{ pt: 1 }}>
          {error && <Alert severity="error">{error}</Alert>}
          <Alert severity="info">The CDC reviews every proposal. Students are informed only when the CDC publishes the stage.</Alert>
          <Stack direction={{ xs: "column", sm: "row" }} spacing={2}>
            <FormControl fullWidth size="small">
              <InputLabel id="u-mode">What are you sending?</InputLabel>
              <Select
                labelId="u-mode"
                label="What are you sending?"
                value={mode}
                onChange={(e) => {
                  setMode(e.target.value);
                  reset();
                }}
              >
                {!completed && <MenuItem value="decisions">Stage decisions (Shortlisted / On Hold)</MenuItem>}
                <MenuItem value="list">A list of roll numbers</MenuItem>
              </Select>
            </FormControl>
            <FormControl fullWidth size="small">
              <InputLabel id="u-round">Stage</InputLabel>
              <Select
                labelId="u-round"
                label="Stage"
                value={roundId}
                onChange={(e) => {
                  setRoundId(e.target.value);
                  reset();
                }}
              >
                {rounds.map((r) => (
                  <MenuItem key={r.id} value={String(r.id)}>
                    {r.name}
                  </MenuItem>
                ))}
              </Select>
            </FormControl>
          </Stack>

          {mode === "decisions" ? (
            <>
              <Typography variant="body2" color="text.secondary">
                1. Download the sheet for this stage. 2. In the <strong>Decision</strong> column pick <strong>Shortlisted</strong> or <strong>On Hold</strong> from
                the dropdown; leave it blank for students you are not shortlisting. 3. Upload it, check the preview, then send. Shortlisted and On Hold
                go to the CDC as two proposals.
              </Typography>
              <Box>
                <Button variant="outlined" startIcon={<DownloadIcon />} onClick={downloadSheet} disabled={!roundId}>
                  Download decision sheet
                </Button>
              </Box>
            </>
          ) : (
            <>
              <FormControl fullWidth size="small">
                <InputLabel id="u-kind">Type</InputLabel>
                <Select
                  labelId="u-kind"
                  label="Type"
                  value={kind}
                  onChange={(e) => {
                    setKind(e.target.value);
                    reset();
                  }}
                >
                  {Object.entries(KIND_LABELS)
                    .filter(([k]) => !completed || ["addendum", "replacement_request"].includes(k))
                    .map(([k, label]) => (
                      <MenuItem key={k} value={k}>
                        {label}
                      </MenuItem>
                    ))}
                </Select>
              </FormControl>
              <Typography variant="body2" color="text.secondary">
                An Excel or CSV file with roll numbers in the first column (a &quot;Roll No&quot; heading is fine).
                {kind === "waitlist" && " An On Hold list replaces the stage's current On Hold list."}
              </Typography>
            </>
          )}

          <Box>
            <Button variant="outlined" component="label" startIcon={<UploadFileIcon />}>
              {file ? file.name : "Choose file (.xlsx / .csv)"}
              <input
                hidden
                type="file"
                accept=".xlsx,.xls,.csv"
                onChange={(e) => {
                  setFile(e.target.files?.[0] ?? null);
                  reset();
                }}
              />
            </Button>
          </Box>

          {preview && (
            <Alert severity={usable ? (preview.errors?.length ? "warning" : "info") : "error"}>
              <AlertTitle>{usable ? 'Preview — check it, then click "Send to CDC"' : "Nothing can be sent from this file"}</AlertTitle>
              {mode === "decisions" ? (
                <Typography variant="body2">
                  {preview.shortlisted ?? 0} shortlisted · {preview.on_hold ?? 0} on hold · {preview.blank ?? 0} left blank
                </Typography>
              ) : (
                <Typography variant="body2">{preview.students ?? 0} student(s)</Typography>
              )}
              {preview.errors?.length > 0 && (
                <Box component="ul" sx={{ m: 0, mt: 1, pl: 2.5, maxHeight: 180, overflowY: "auto" }}>
                  {preview.errors.map((e, i) => (
                    <li key={i}>
                      <Typography variant="body2">
                        {e.roll_no}: {e.reason}
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
        {!usable ? (
          <Button variant="contained" onClick={() => send(true)} disabled={!file || !roundId || busy}>
            Preview
          </Button>
        ) : (
          <Button variant="contained" onClick={() => send(false)} disabled={busy}>
            Send to CDC
          </Button>
        )}
      </DialogActions>
    </Dialog>
  );
}
