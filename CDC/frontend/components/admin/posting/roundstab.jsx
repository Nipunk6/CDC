"use client";

import { useState } from "react";
import {
  Alert,
  Button,
  Checkbox,
  Chip,
  Dialog,
  DialogActions,
  DialogContent,
  DialogTitle,
  FormControl,
  FormControlLabel,
  IconButton,
  InputLabel,
  MenuItem,
  Select,
  Stack,
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TableRow,
  TextField,
  Tooltip,
  Typography,
} from "@mui/material";
import AddIcon from "@mui/icons-material/Add";
import EditIcon from "@mui/icons-material/Edit";
import DeleteOutlineIcon from "@mui/icons-material/DeleteOutline";
import ArrowUpwardIcon from "@mui/icons-material/ArrowUpward";
import ArrowDownwardIcon from "@mui/icons-material/ArrowDownward";

import { adminApi } from "@/lib/adminapi";
import { formatDateTime, fromLocalInput, statusColor, titleCase, toLocalInput } from "@/lib/format";

export const ROUND_TYPES = {
  ppt: "Pre-Placement Talk",
  resume: "Resume Shortlisting",
  written_test: "Written Test",
  aptitude_test: "Aptitude Test",
  technical_test: "Technical Test",
  group_discussion: "Group Discussion",
  hr_interview: "HR Interview",
  technical_interview: "Technical Interview",
  psychometric: "Psychometric Test",
  medical: "Medical Test",
  other: "Other",
};

const blank = { name: "", round_type: "technical_interview", scheduled_at: "", status: "pending", is_final: false };

export default function RoundsTab({ posting, onChanged, onMessage }) {
  const rounds = posting.rounds ?? [];
  const [dialog, setDialog] = useState(null);
  const [error, setError] = useState(null);
  const [busy, setBusy] = useState(false);

  const call = async (path, init, message) => {
    setBusy(true);
    setError(null);
    try {
      const response = await adminApi(path, init);
      onMessage?.(message ?? response.message);
      await onChanged();
      return true;
    } catch (e) {
      setError(e instanceof Error ? e.message : "Request failed.");
      return false;
    } finally {
      setBusy(false);
    }
  };

  const save = async () => {
    const body = {
      name: dialog.name.trim(),
      round_type: dialog.round_type,
      scheduled_at: fromLocalInput(dialog.scheduled_at),
      is_final: dialog.is_final,
      // Only send a status the admin actually changed, so a stale list cannot undo what publishing set.
      ...(dialog.id && dialog.status !== rounds.find((r) => r.id === dialog.id)?.status ? { status: dialog.status } : {}),
    };
    const ok = dialog.id
      ? await call(`/admin/postings/${posting.id}/rounds/${dialog.id}`, { method: "PATCH", body: JSON.stringify(body) })
      : await call(`/admin/postings/${posting.id}/rounds`, { method: "POST", body: JSON.stringify(body) });
    if (ok) setDialog(null);
  };

  const move = (index, delta) => {
    const ids = rounds.map((r) => r.id);
    const [id] = ids.splice(index, 1);
    ids.splice(index + delta, 0, id);
    void call(`/admin/postings/${posting.id}/rounds/reorder`, { method: "POST", body: JSON.stringify({ ordered_round_ids: ids }) });
  };

  return (
    <Stack spacing={2}>
      {error && <Alert severity="error">{error}</Alert>}
      <TableContainer>
        <Table size="small">
          <TableHead>
            <TableRow>
              <TableCell>#</TableCell>
              <TableCell>Round</TableCell>
              <TableCell sx={{ display: { xs: "none", sm: "table-cell" } }}>Type</TableCell>
              <TableCell>Scheduled</TableCell>
              <TableCell>Status</TableCell>
              <TableCell align="right">Actions</TableCell>
            </TableRow>
          </TableHead>
          <TableBody>
            {rounds.map((round, index) => (
              <TableRow key={round.id}>
                <TableCell>{index + 1}</TableCell>
                <TableCell>
                  {round.name} {round.is_final && <Chip size="small" color="primary" label="Final" sx={{ ml: 0.5 }} />}
                </TableCell>
                <TableCell sx={{ display: { xs: "none", sm: "table-cell" } }}>{ROUND_TYPES[round.round_type] ?? round.round_type}</TableCell>
                <TableCell>{round.scheduled_at ? formatDateTime(round.scheduled_at) : "—"}</TableCell>
                <TableCell>
                  <Chip size="small" variant="outlined" color={statusColor(round.status)} label={titleCase(round.status)} />
                </TableCell>
                <TableCell align="right" sx={{ whiteSpace: "nowrap" }}>
                  <IconButton size="small" disabled={busy || index === 0} onClick={() => move(index, -1)}>
                    <ArrowUpwardIcon fontSize="small" />
                  </IconButton>
                  <IconButton size="small" disabled={busy || index === rounds.length - 1} onClick={() => move(index, 1)}>
                    <ArrowDownwardIcon fontSize="small" />
                  </IconButton>
                  <IconButton
                    size="small"
                    disabled={busy}
                    onClick={() => setDialog({ ...round, scheduled_at: toLocalInput(round.scheduled_at) })}
                  >
                    <EditIcon fontSize="small" />
                  </IconButton>
                  <Tooltip title="Remove (only rounds without results)">
                    <span>
                      <IconButton
                        size="small"
                        color="error"
                        disabled={busy}
                        onClick={() =>
                          window.confirm(`Remove "${round.name}"?`) &&
                          call(`/admin/postings/${posting.id}/rounds/${round.id}`, { method: "DELETE" })
                        }
                      >
                        <DeleteOutlineIcon fontSize="small" />
                      </IconButton>
                    </span>
                  </Tooltip>
                </TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>
      </TableContainer>
      <Stack direction="row">
        <Button startIcon={<AddIcon />} onClick={() => setDialog({ ...blank })} disabled={busy}>
          Add round
        </Button>
      </Stack>

      <Dialog open={Boolean(dialog)} onClose={() => !busy && setDialog(null)} maxWidth="sm" fullWidth>
        <DialogTitle>{dialog?.id ? "Edit round" : "Add round"}</DialogTitle>
        <DialogContent>
          <Stack spacing={2} sx={{ pt: 1 }}>
            {error && <Alert severity="error">{error}</Alert>}
            <TextField label="Round name" value={dialog?.name ?? ""} onChange={(e) => setDialog((d) => ({ ...d, name: e.target.value }))} />
            <FormControl fullWidth>
              <InputLabel id="round-type">Type</InputLabel>
              <Select
                labelId="round-type"
                label="Type"
                value={dialog?.round_type ?? "other"}
                onChange={(e) =>
                  setDialog((d) => ({ ...d, round_type: e.target.value, name: d.name || ROUND_TYPES[e.target.value] }))
                }
              >
                {Object.entries(ROUND_TYPES).map(([key, label]) => (
                  <MenuItem key={key} value={key}>
                    {label}
                  </MenuItem>
                ))}
              </Select>
            </FormControl>
            <TextField
              type="datetime-local"
              label="Scheduled at, IST (optional)"
              value={dialog?.scheduled_at ?? ""}
              onChange={(e) => setDialog((d) => ({ ...d, scheduled_at: e.target.value }))}
              slotProps={{ inputLabel: { shrink: true } }}
            />
            {dialog?.id && (
              <FormControl fullWidth>
                <InputLabel id="round-status">Status</InputLabel>
                <Select labelId="round-status" label="Status" value={dialog.status} onChange={(e) => setDialog((d) => ({ ...d, status: e.target.value }))}>
                  {["pending", "ongoing", "completed"].map((s) => (
                    <MenuItem key={s} value={s}>
                      {titleCase(s)}
                    </MenuItem>
                  ))}
                </Select>
              </FormControl>
            )}
            <FormControlLabel
              control={
                <Checkbox
                  checked={Boolean(dialog?.is_final)}
                  disabled={Boolean(dialog?.id && rounds.find((r) => r.id === dialog.id)?.is_final)}
                  onChange={(e) => setDialog((d) => ({ ...d, is_final: e.target.checked }))}
                />
              }
              label="This is the final round (offers are announced after it)"
            />
            {dialog?.id && rounds.find((r) => r.id === dialog.id)?.is_final && (
              <Typography variant="caption" color="text.secondary">
                Every posting needs one final round. To change it, mark another round as final.
              </Typography>
            )}
          </Stack>
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setDialog(null)} disabled={busy}>
            Cancel
          </Button>
          <Button variant="contained" onClick={save} disabled={busy || !dialog?.name?.trim()}>
            Save
          </Button>
        </DialogActions>
      </Dialog>
    </Stack>
  );
}
