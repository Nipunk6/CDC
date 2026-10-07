"use client";

import { useState } from "react";
import {
  Alert,
  Button,
  Checkbox,
  Dialog,
  DialogActions,
  DialogContent,
  DialogTitle,
  FormControl,
  FormControlLabel,
  InputLabel,
  MenuItem,
  Select,
  Stack,
  TextField,
  Typography,
} from "@mui/material";

import { adminApi } from "@/lib/adminapi";
import { adminUpload } from "@/lib/adminupload";

// Roll numbers are upper-cased; email addresses are sent as typed (the API matches them case-insensitively).
export const splitIdentifiers = (text) =>
  text
    .split(/[\s,;]+/)
    .map((r) => r.trim())
    .filter(Boolean)
    .map((r) => (r.includes("@") ? r.toLowerCase() : r.toUpperCase()));

const SUBMIT_LABELS = { selected: "Add students to Shortlist", waitlisted: "Hold at current stage", rejected: "Remove from shortlist" };

/**
 * "Bulk Shortlist / Reject / Hold" for one stage (Superset parity S1.3). Writes DRAFT results through
 * POST …/rounds/{r}/results; unknown and withdrawn entries come back in `errors`, outside-the-pool entries in `warnings`
 * (or `errors` with the strict check on).
 */
export default function BulkShortlistDialog({ postingId, round, open, onClose, onDone }) {
  const [result, setResult] = useState("selected");
  const [text, setText] = useState("");
  const [file, setFile] = useState(null);
  const [strict, setStrict] = useState(false);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState(null);

  const entries = splitIdentifiers(text);

  const close = () => {
    if (busy) return;
    setText("");
    setFile(null);
    setError(null);
    onClose();
  };

  const submit = async () => {
    setBusy(true);
    setError(null);
    try {
      const path = `/admin/postings/${postingId}/rounds/${round.id}/results`;
      let response;
      if (file) {
        const body = new FormData();
        body.append("file", file);
        body.append("result", result);
        body.append("strict", strict ? "1" : "0");
        response = await adminUpload(path, body);
      } else {
        response = await adminApi(path, { method: "POST", body: JSON.stringify({ roll_nos: entries, result, strict }) });
      }
      setText("");
      setFile(null);
      onDone(response);
    } catch (e) {
      setError(e instanceof Error ? e.message : "Request failed.");
    } finally {
      setBusy(false);
    }
  };

  return (
    <Dialog open={open} onClose={close} maxWidth="sm" fullWidth>
      <DialogTitle>Bulk Shortlist / Reject / Hold — {round?.name}</DialogTitle>
      <DialogContent>
        <Stack spacing={2} sx={{ pt: 1 }}>
          {error && <Alert severity="error">{error}</Alert>}
          <FormControl size="small">
            <InputLabel id="bulk-kind">Mark students as</InputLabel>
            <Select labelId="bulk-kind" label="Mark students as" value={result} onChange={(e) => setResult(e.target.value)}>
              <MenuItem value="selected">Add to shortlist</MenuItem>
              <MenuItem value="waitlisted">Hold at current stage</MenuItem>
              <MenuItem value="rejected">Remove from shortlist</MenuItem>
            </Select>
          </FormControl>
          <TextField
            multiline
            minRows={5}
            label="Enter Roll nos or Email IDs"
            helperText="Separated by a new line, a comma or a space."
            value={text}
            disabled={Boolean(file)}
            onChange={(e) => setText(e.target.value)}
          />
          {!file && (
            <Typography variant="body2" color={entries.length ? "primary.main" : "text.secondary"} fontWeight={600}>
              You have entered {entries.length} roll no{entries.length === 1 ? "" : "s"}
            </Typography>
          )}
          <Button variant="outlined" component="label">
            {file ? file.name : "…or upload .xlsx/.csv (roll number or email, result)"}
            <input hidden type="file" accept=".xlsx,.xls,.csv" onChange={(e) => setFile(e.target.files?.[0] ?? null)} />
          </Button>
          <FormControlLabel
            control={<Checkbox checked={strict} onChange={(e) => setStrict(e.target.checked)} />}
            label="Strict check on current stage (refuse students who are not in this stage's pool)"
          />
          <Typography variant="caption" color="text.secondary">
            Decisions are saved as drafts. Nobody sees them until you publish the stage. Unknown or withdrawn students are listed afterwards,
            never skipped silently.
          </Typography>
        </Stack>
      </DialogContent>
      <DialogActions>
        <Button onClick={close} disabled={busy}>
          Cancel
        </Button>
        <Button variant="contained" onClick={submit} disabled={busy || (!file && entries.length === 0)}>
          {SUBMIT_LABELS[result]}
        </Button>
      </DialogActions>
    </Dialog>
  );
}
