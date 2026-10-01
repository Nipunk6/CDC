"use client";

import { useCallback, useEffect, useMemo, useState } from "react";
import Link from "next/link";
import {
  Alert,
  AlertTitle,
  Box,
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
  LinearProgress,
  Menu,
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
import MoreVertIcon from "@mui/icons-material/MoreVert";
import CheckIcon from "@mui/icons-material/Check";
import CloseIcon from "@mui/icons-material/Close";
import UndoIcon from "@mui/icons-material/Undo";

import { adminApi } from "@/lib/adminapi";
import { adminUpload } from "@/lib/adminupload";
import { statusColor, titleCase } from "@/lib/format";

const splitRolls = (text) =>
  text
    .split(/[\s,;]+/)
    .map((r) => r.trim().toUpperCase())
    .filter(Boolean);

const resultColors = { selected: "success", rejected: "error", waitlisted: "info", pending: "default" };

function ResultCell({ cell, onReadd, onRemoveDraft }) {
  if (!cell) return <Typography variant="caption" color="text.disabled">—</Typography>;

  const label = cell.result === "waitlisted" ? "Waitlist" : titleCase(cell.result);

  return (
    <Stack direction="row" spacing={0.5} alignItems="center" flexWrap="wrap" useFlexGap>
      {cell.attendance && (
        <Tooltip title={cell.attendance === "yes" ? "Appeared" : "Absent"}>
          {cell.attendance === "yes" ? <CheckIcon fontSize="small" color="success" /> : <CloseIcon fontSize="small" color="error" />}
        </Tooltip>
      )}
      {cell.result !== "pending" && (
        <Tooltip title={cell.published ? "Published — visible to the student" : "Draft — not visible yet"}>
          <Chip
            size="small"
            color={resultColors[cell.result]}
            variant={cell.published ? "filled" : "outlined"}
            sx={cell.published ? undefined : { borderStyle: "dashed" }}
            label={`${label}${cell.is_addendum ? " +" : ""}${cell.published ? "" : " (draft)"}`}
          />
        </Tooltip>
      )}
      {cell.published && cell.result === "rejected" && (
        <Tooltip title="Re-add previously rejected candidate">
          <IconButton size="small" onClick={onReadd}>
            <UndoIcon fontSize="inherit" />
          </IconButton>
        </Tooltip>
      )}
      {!cell.published && cell.result !== "pending" && (
        <Tooltip title="Remove this draft">
          <IconButton size="small" onClick={onRemoveDraft}>
            <CloseIcon fontSize="inherit" />
          </IconButton>
        </Tooltip>
      )}
    </Stack>
  );
}

/**
 * Rounds × applicants grid (req 20) with per-round bulk actions. Drafts are dashed; published chips are filled.
 */
export default function PipelineTab({ posting, onMessage, onChanged }) {
  const [data, setData] = useState(null);
  const [error, setError] = useState(null);
  const [menu, setMenu] = useState(null); // { anchor, round }
  const [dialog, setDialog] = useState(null); // { kind, round, ... }
  const [busy, setBusy] = useState(false);
  const [report, setReport] = useState(null);
  const [search, setSearch] = useState("");

  const load = useCallback(async () => {
    try {
      setData(await adminApi(`/admin/postings/${posting.id}/pipeline`));
      setError(null);
    } catch (e) {
      setError(e instanceof Error ? e.message : "Failed to load the pipeline.");
    }
  }, [posting.id]);

  useEffect(() => {
    void load();
  }, [load]);

  const base = `/admin/postings/${posting.id}/rounds`;

  const run = async (fn) => {
    setBusy(true);
    setError(null);
    try {
      const response = await fn();
      onMessage?.(response.message);
      setReport(response.errors?.length || response.warnings?.length ? response : null);
      setDialog(null);
      await load();
      onChanged?.();
    } catch (e) {
      setError(e instanceof Error ? e.message : "Request failed.");
    } finally {
      setBusy(false);
    }
  };

  const submitDialog = () => {
    const { kind, round } = dialog;
    if (kind === "results") {
      if (dialog.file) {
        const body = new FormData();
        body.append("file", dialog.file);
        body.append("result", dialog.result);
        return run(() => adminUpload(`${base}/${round.id}/results`, body));
      }
      return run(() =>
        adminApi(`${base}/${round.id}/results`, { method: "POST", body: JSON.stringify({ roll_nos: splitRolls(dialog.text), result: dialog.result }) })
      );
    }
    if (kind === "attendance") {
      return run(() =>
        adminApi(`${base}/${round.id}/attendance`, {
          method: "POST",
          body: JSON.stringify({ roll_nos_present: splitRolls(dialog.present), roll_nos_absent: splitRolls(dialog.absent) }),
        })
      );
    }
    if (kind === "addendum") {
      return run(() =>
        adminApi(`${base}/${round.id}/addendum`, { method: "POST", body: JSON.stringify({ roll_nos: splitRolls(dialog.text), remark: dialog.remark || null }) })
      );
    }
    if (kind === "publish") {
      return run(() => adminApi(`${base}/${round.id}/publish`, { method: "POST", body: JSON.stringify({ reject_remaining: dialog.rejectRemaining }) }));
    }
    if (kind === "readd") {
      return run(() =>
        adminApi(`${base}/${round.id}/readd/${dialog.application.id}`, {
          method: "POST",
          body: JSON.stringify({ confirm: dialog.confirm, remark: dialog.remark }),
        })
      );
    }
    return undefined;
  };

  const rows = useMemo(() => {
    const term = search.trim().toLowerCase();
    return (data?.applications ?? []).filter((a) =>
      !term ? true : `${a.student?.roll_no} ${a.student?.full_name} ${a.student?.branch}`.toLowerCase().includes(term)
    );
  }, [data, search]);

  if (!data) return error ? <Alert severity="error">{error}</Alert> : <LinearProgress />;

  return (
    <Stack spacing={2}>
      {data.accepts_applications && (
        <Alert severity="info">Applications are still open. Close applications (Overview tab) or wait for the deadline before entering results.</Alert>
      )}
      {error && <Alert severity="error">{error}</Alert>}
      {report && (
        <Alert severity="warning" onClose={() => setReport(null)}>
          <AlertTitle>Check these roll numbers</AlertTitle>
          {(report.errors ?? []).map((e) => (
            <div key={`e-${e.roll_no}`}>
              {e.roll_no}: {e.reason}
            </div>
          ))}
          {(report.warnings ?? []).map((w) => (
            <div key={`w-${w.roll_no}`}>
              {w.roll_no}: {w.reason} (saved anyway)
            </div>
          ))}
        </Alert>
      )}

      <Stack direction={{ xs: "column", sm: "row" }} justifyContent="space-between" spacing={1}>
        <TextField size="small" label="Search roll no, name, branch" value={search} onChange={(e) => setSearch(e.target.value)} />
        <Typography variant="body2" color="text.secondary" sx={{ alignSelf: "center" }}>
          {rows.length} active applicant(s) · dashed = draft, filled = published
        </Typography>
      </Stack>

      <TableContainer sx={{ maxHeight: 640, border: 1, borderColor: "divider", borderRadius: 1 }}>
        <Table size="small" stickyHeader>
          <TableHead>
            <TableRow>
              <TableCell sx={{ position: "sticky", left: 0, zIndex: 3, bgcolor: "background.paper", minWidth: 200 }}>Student</TableCell>
              {data.rounds.map((round) => (
                <TableCell key={round.id} sx={{ minWidth: 190 }}>
                  <Stack direction="row" alignItems="center" justifyContent="space-between">
                    <Box>
                      <Typography variant="body2" fontWeight={700} component="div">
                        {round.name} {round.is_final && <Chip size="small" label="Final" color="primary" sx={{ ml: 0.5 }} />}
                      </Typography>
                      <Stack direction="row" spacing={0.5} alignItems="center">
                        <Chip size="small" variant="outlined" color={statusColor(round.status)} label={titleCase(round.status)} />
                        <Typography variant="caption" color="text.secondary">
                          {round.draft_count} draft · pool {round.pool_count}
                        </Typography>
                      </Stack>
                    </Box>
                    <IconButton size="small" onClick={(e) => setMenu({ anchor: e.currentTarget, round })}>
                      <MoreVertIcon fontSize="small" />
                    </IconButton>
                  </Stack>
                </TableCell>
              ))}
            </TableRow>
          </TableHead>
          <TableBody>
            {rows.length === 0 && (
              <TableRow>
                <TableCell colSpan={data.rounds.length + 1}>No active applicants.</TableCell>
              </TableRow>
            )}
            {rows.map((a) => (
              <TableRow key={a.id} hover>
                <TableCell sx={{ position: "sticky", left: 0, zIndex: 1, bgcolor: "background.paper" }}>
                  <Typography variant="body2" fontWeight={600}>
                    <Link href={`/admin/students/${a.student?.id}`}>{a.student?.roll_no}</Link> {a.student?.full_name}
                  </Typography>
                  <Typography variant="caption" color="text.secondary">
                    {a.student?.branch} · {a.student?.current_cgpa ?? "—"}
                  </Typography>
                  <Stack direction="row" spacing={0.5}>
                    {a.used_unverified_resume && <Chip size="small" color="warning" label="⚠ Unverified" />}
                    {a.placed_elsewhere_flag && <Chip size="small" color="error" label="🚩 Placed" />}
                  </Stack>
                </TableCell>
                {data.rounds.map((round) => (
                  <TableCell key={round.id}>
                    <ResultCell
                      cell={a.results?.[round.id]}
                      onReadd={() => setDialog({ kind: "readd", round, application: a, remark: "", confirm: false })}
                      onRemoveDraft={() =>
                        window.confirm(`Remove the draft result for ${a.student?.roll_no}?`) &&
                        run(() => adminApi(`${base}/${round.id}/results/${a.id}`, { method: "DELETE" }))
                      }
                    />
                  </TableCell>
                ))}
              </TableRow>
            ))}
          </TableBody>
        </Table>
      </TableContainer>

      <Menu anchorEl={menu?.anchor} open={Boolean(menu)} onClose={() => setMenu(null)}>
        <MenuItem
          onClick={() => {
            setDialog({ kind: "results", round: menu.round, text: "", result: "selected", file: null });
            setMenu(null);
          }}
        >
          Enter results (paste / upload)
        </MenuItem>
        <MenuItem
          onClick={() => {
            setDialog({ kind: "attendance", round: menu.round, present: "", absent: "" });
            setMenu(null);
          }}
        >
          Mark attendance
        </MenuItem>
        <MenuItem
          disabled={menu?.round?.status !== "completed"}
          onClick={() => {
            setDialog({ kind: "addendum", round: menu.round, text: "", remark: "" });
            setMenu(null);
          }}
        >
          Addendum (after publishing)
        </MenuItem>
        <MenuItem
          disabled={menu?.round?.is_final}
          onClick={() => {
            setDialog({ kind: "publish", round: menu.round, rejectRemaining: menu.round.status !== "completed" });
            setMenu(null);
          }}
        >
          {menu?.round?.is_final ? "Publish from the Results page" : "Publish round"}
        </MenuItem>
      </Menu>

      <Dialog open={Boolean(dialog)} onClose={() => !busy && setDialog(null)} maxWidth="sm" fullWidth>
        <DialogTitle>
          {dialog?.kind === "results" && `Enter results — ${dialog.round.name}`}
          {dialog?.kind === "attendance" && `Attendance — ${dialog.round.name}`}
          {dialog?.kind === "addendum" && `Addendum — ${dialog.round.name}`}
          {dialog?.kind === "publish" && `Publish ${dialog.round.name}?`}
          {dialog?.kind === "readd" && "Re-add previously rejected candidate"}
        </DialogTitle>
        <DialogContent>
          <Stack spacing={2} sx={{ pt: 1 }}>
            {error && <Alert severity="error">{error}</Alert>}
            {dialog?.kind === "results" && (
              <>
                <FormControl size="small">
                  <InputLabel id="res-kind">Mark pasted roll numbers as</InputLabel>
                  <Select labelId="res-kind" label="Mark pasted roll numbers as" value={dialog.result} onChange={(e) => setDialog((d) => ({ ...d, result: e.target.value }))}>
                    <MenuItem value="selected">Selected</MenuItem>
                    <MenuItem value="waitlisted">Waitlisted</MenuItem>
                    <MenuItem value="rejected">Rejected</MenuItem>
                  </Select>
                </FormControl>
                <TextField
                  multiline
                  minRows={5}
                  label="Roll numbers (one per line, or comma separated)"
                  value={dialog.text}
                  disabled={Boolean(dialog.file)}
                  onChange={(e) => setDialog((d) => ({ ...d, text: e.target.value }))}
                />
                <Button variant="outlined" component="label">
                  {dialog.file ? dialog.file.name : "…or upload .xlsx/.csv (roll_no, result)"}
                  <input hidden type="file" accept=".xlsx,.xls,.csv" onChange={(e) => setDialog((d) => ({ ...d, file: e.target.files?.[0] ?? null }))} />
                </Button>
                <Typography variant="caption" color="text.secondary">
                  Results are saved as drafts. Nobody sees them until you publish the round.
                </Typography>
              </>
            )}
            {dialog?.kind === "attendance" && (
              <>
                <TextField multiline minRows={4} label="Present (roll numbers)" value={dialog.present} onChange={(e) => setDialog((d) => ({ ...d, present: e.target.value }))} />
                <TextField multiline minRows={3} label="Absent (roll numbers)" value={dialog.absent} onChange={(e) => setDialog((d) => ({ ...d, absent: e.target.value }))} />
              </>
            )}
            {dialog?.kind === "addendum" && (
              <>
                <Alert severity="info">Adds candidates to a round whose results are already published. They are flagged as addendum and notified when you publish again.</Alert>
                <TextField multiline minRows={4} label="Roll numbers" value={dialog.text} onChange={(e) => setDialog((d) => ({ ...d, text: e.target.value }))} />
                <TextField label="Remark (optional)" value={dialog.remark} onChange={(e) => setDialog((d) => ({ ...d, remark: e.target.value }))} />
              </>
            )}
            {dialog?.kind === "publish" && (
              <>
                <Typography>
                  {dialog.round.draft_count} draft result(s) will become visible and every affected student is emailed (selected/waitlisted: result mail;
                  rejected: regret mail).
                </Typography>
                <FormControlLabel
                  control={<Checkbox checked={dialog.rejectRemaining} onChange={(e) => setDialog((d) => ({ ...d, rejectRemaining: e.target.checked }))} />}
                  label={`Mark everyone else in this round's pool (${dialog.round.pool_count}) as not selected`}
                />
              </>
            )}
            {dialog?.kind === "readd" && (
              <>
                <Alert severity="warning">
                  Re-add {dialog.application.student?.roll_no} {dialog.application.student?.full_name} to {dialog.round.name}. The company will be notified.
                </Alert>
                <TextField label="Reason (sent to the company)" value={dialog.remark} onChange={(e) => setDialog((d) => ({ ...d, remark: e.target.value }))} />
                <FormControlLabel
                  control={<Checkbox checked={dialog.confirm} onChange={(e) => setDialog((d) => ({ ...d, confirm: e.target.checked }))} />}
                  label="Re-add previously rejected candidate — the company will be notified"
                />
              </>
            )}
          </Stack>
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setDialog(null)} disabled={busy}>
            Cancel
          </Button>
          <Button
            variant="contained"
            onClick={submitDialog}
            disabled={busy || (dialog?.kind === "readd" && (!dialog.confirm || !dialog.remark.trim()))}
          >
            {dialog?.kind === "publish" ? "Publish & notify" : "Save"}
          </Button>
        </DialogActions>
      </Dialog>
    </Stack>
  );
}
