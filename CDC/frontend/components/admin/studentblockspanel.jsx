"use client";

import { useCallback, useEffect, useState } from "react";
import {
  Alert,
  Box,
  Button,
  Chip,
  Dialog,
  DialogActions,
  DialogContent,
  DialogTitle,
  FormControl,
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
  Typography,
} from "@mui/material";
import BlockIcon from "@mui/icons-material/Block";

import { adminApi } from "@/lib/adminapi";
import { formatDateTime } from "@/lib/format";

/**
 * Placement blocks for one student (or one cycle): list, add manual/debarred, lift (spec M7.3).
 */
export default function StudentBlocksPanel({ student, cycleId, onChanged }) {
  const [blocks, setBlocks] = useState(null);
  const [error, setError] = useState(null);
  const [dialog, setDialog] = useState(null);
  const [busy, setBusy] = useState(false);

  const query = student ? `student_profile_id=${student.id}` : `cycle_id=${cycleId}`;

  const load = useCallback(async () => {
    try {
      const response = await adminApi(`/admin/blocks?${query}`);
      setBlocks(response.blocks ?? []);
    } catch (e) {
      setError(e instanceof Error ? e.message : "Failed to load blocks.");
    }
  }, [query]);

  useEffect(() => {
    void load();
  }, [load]);

  const lift = async (block) => {
    if (!window.confirm(`Lift this block for ${block.student_profile?.roll_no}? They become eligible again in ${block.placement_cycle?.name}.`)) return;
    try {
      await adminApi(`/admin/blocks/${block.id}`, { method: "DELETE" });
      await load();
      onChanged?.();
    } catch (e) {
      setError(e instanceof Error ? e.message : "Failed to lift the block.");
    }
  };

  const save = async () => {
    setBusy(true);
    setError(null);
    try {
      await adminApi("/admin/blocks", {
        method: "POST",
        body: JSON.stringify({
          student_profile_id: student.id,
          placement_cycle_id: Number(dialog.cycle),
          scope: dialog.scope,
          reason: dialog.reason,
          remark: dialog.remark.trim(),
        }),
      });
      setDialog(null);
      await load();
      onChanged?.();
    } catch (e) {
      setError(e instanceof Error ? e.message : "Failed to add the block.");
    } finally {
      setBusy(false);
    }
  };

  const cycles = (student?.cycle_enrollments ?? []).map((e) => e.placement_cycle).filter(Boolean);

  return (
    <Box>
      <Stack direction="row" justifyContent="space-between" alignItems="center" sx={{ mb: 1 }}>
        <Typography variant="subtitle1" fontWeight={700}>
          Placement blocks
        </Typography>
        {student && (
          <Button
            size="small"
            startIcon={<BlockIcon />}
            disabled={cycles.length === 0}
            onClick={() => {
              setError(null);
              setDialog({ cycle: String(cycles[0]?.id ?? ""), scope: "all", reason: "manual", remark: "" });
            }}
          >
            Add block / debar
          </Button>
        )}
      </Stack>
      {error && (
        <Alert severity="error" sx={{ mb: 1 }}>
          {error}
        </Alert>
      )}
      {blocks && blocks.length === 0 && <Typography color="text.secondary">No blocks.</Typography>}
      {blocks && blocks.length > 0 && (
        <TableContainer>
          <Table size="small">
            <TableHead>
              <TableRow>
                {!student && <TableCell>Student</TableCell>}
                <TableCell>Placement</TableCell>
                <TableCell>Block</TableCell>
                <TableCell>By</TableCell>
                <TableCell>Status</TableCell>
                <TableCell align="right" />
              </TableRow>
            </TableHead>
            <TableBody>
              {blocks.map((b) => (
                <TableRow key={b.id}>
                  {!student && (
                    <TableCell>
                      {b.student_profile?.roll_no} {b.student_profile?.full_name}
                    </TableCell>
                  )}
                  <TableCell>{b.placement_cycle?.name}</TableCell>
                  <TableCell>{b.message}</TableCell>
                  <TableCell sx={{ whiteSpace: "nowrap" }}>
                    {b.blocked_by?.name ?? "System"}
                    <Typography variant="caption" display="block" color="text.secondary">
                      {formatDateTime(b.created_at)}
                    </Typography>
                  </TableCell>
                  <TableCell>
                    {b.active ? (
                      <Chip size="small" color="error" label="Active" />
                    ) : (
                      <Chip size="small" variant="outlined" label={`Lifted ${formatDateTime(b.unblocked_at)} by ${b.unblocked_by?.name ?? "—"}`} />
                    )}
                  </TableCell>
                  <TableCell align="right">
                    {b.active && (
                      <Button size="small" onClick={() => lift(b)}>
                        Lift
                      </Button>
                    )}
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </TableContainer>
      )}

      <Dialog open={Boolean(dialog)} onClose={() => !busy && setDialog(null)} maxWidth="xs" fullWidth>
        <DialogTitle>Add block</DialogTitle>
        <DialogContent>
          <Stack spacing={2} sx={{ pt: 1 }}>
            {error && <Alert severity="error">{error}</Alert>}
            <FormControl fullWidth size="small">
              <InputLabel id="blk-cycle">Placement</InputLabel>
              <Select labelId="blk-cycle" label="Placement" value={dialog?.cycle ?? ""} onChange={(e) => setDialog((d) => ({ ...d, cycle: e.target.value }))}>
                {cycles.map((c) => (
                  <MenuItem key={c.id} value={String(c.id)}>
                    {c.name}
                  </MenuItem>
                ))}
              </Select>
            </FormControl>
            <FormControl fullWidth size="small">
              <InputLabel id="blk-reason">Reason</InputLabel>
              <Select labelId="blk-reason" label="Reason" value={dialog?.reason ?? "manual"} onChange={(e) => setDialog((d) => ({ ...d, reason: e.target.value }))}>
                <MenuItem value="manual">Manual block</MenuItem>
                <MenuItem value="debarred">Debarred</MenuItem>
              </Select>
            </FormControl>
            {dialog?.reason !== "debarred" && (
            <FormControl fullWidth size="small">
              <InputLabel id="blk-scope">Scope</InputLabel>
              <Select labelId="blk-scope" label="Scope" value={dialog?.scope ?? "all"} onChange={(e) => setDialog((d) => ({ ...d, scope: e.target.value }))}>
                <MenuItem value="all">Everything in the placement</MenuItem>
                <MenuItem value="internships_only">Internships only</MenuItem>
              </Select>
            </FormControl>
            )}
            {dialog?.reason === "debarred" && (
              <Typography variant="caption" color="text.secondary">
                A debarment blocks every posting in the cycle.
              </Typography>
            )}
            <TextField label="Remark (shown to the student)" value={dialog?.remark ?? ""} onChange={(e) => setDialog((d) => ({ ...d, remark: e.target.value }))} />
          </Stack>
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setDialog(null)} disabled={busy}>
            Cancel
          </Button>
          <Button variant="contained" color="error" onClick={save} disabled={busy || !dialog?.cycle || !dialog?.remark?.trim()}>
            Add block
          </Button>
        </DialogActions>
      </Dialog>
    </Box>
  );
}
