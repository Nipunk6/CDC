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
  FormControlLabel,
  IconButton,
  LinearProgress,
  Menu,
  MenuItem,
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
import EmojiEventsIcon from "@mui/icons-material/EmojiEvents";
import DownloadIcon from "@mui/icons-material/Download";
import OpenInNewIcon from "@mui/icons-material/OpenInNew";

import { adminApi, adminDownload } from "@/lib/adminapi";
import BulkShortlistDialog from "@/components/admin/posting/bulkshortlistdialog";
import StudentQuickView from "@/components/admin/studentquickview";
import { statusColor, titleCase } from "@/lib/format";

const splitRolls = (text) =>
  text
    .split(/[\s,;]+/)
    .map((r) => r.trim().toUpperCase())
    .filter(Boolean);

const resultColors = { selected: "success", rejected: "error", waitlisted: "info", pending: "default" };

// A student's name opens the quick-view drawer (Superset parity S4.3).
const nameButtonSx = { border: 0, p: 0, bgcolor: "transparent", color: "primary.main", cursor: "pointer", textAlign: "left", font: "inherit", fontWeight: 600 };

// Admin all-stages grid wording (display only; enum values unchanged).
const resultLabels = { selected: "Passed", rejected: "Failed", pending: "In Process", waitlisted: "On Hold" };

// Overall status across every stage (Superset parity S1.6). Published results only; drafts never change it.
const OVERALL = {
  offered: { label: "Offered", color: "success" },
  not_selected: { label: "Not selected", color: "error" },
  on_hold: { label: "On hold", color: "info" },
  in_process: { label: "In process", color: "default" },
};

export function overallStatus(application, rounds) {
  if (application.offer_here) return "offered";
  let latest = null;
  for (const round of rounds) {
    const cell = application.results?.[round.id];
    if (cell?.published && cell.result !== "pending") {
      if (cell.result === "rejected") return "not_selected";
      latest = cell.result;
    }
  }
  return latest === "waitlisted" ? "on_hold" : "in_process";
}

function ResultCell({ cell, onReadd, onRemoveDraft }) {
  if (!cell) return <Typography variant="caption" color="text.disabled">—</Typography>;

  const label = resultLabels[cell.result] ?? titleCase(cell.result);

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
 * Stages × applicants grid (req 20) with per-stage bulk actions. Drafts are dashed; published chips are filled.
 */
export default function PipelineTab({ posting, onMessage, onChanged }) {
  const [data, setData] = useState(null);
  const [error, setError] = useState(null);
  const [menu, setMenu] = useState(null); // { anchor, round }
  const [dialog, setDialog] = useState(null); // { kind, round, ... }
  const [busy, setBusy] = useState(false);
  const [report, setReport] = useState(null);
  const [search, setSearch] = useState("");
  const [quickView, setQuickView] = useState(null);

  const load = useCallback(async () => {
    try {
      setData(await adminApi(`/admin/postings/${posting.id}/pipeline`));
      setError(null);
    } catch (e) {
      setError(e instanceof Error ? e.message : "Failed to load the progress grid.");
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

  const selectedIn = (round) => (data.applications ?? []).filter((a) => a.results?.[round.id]?.result === "selected").length;
  // "M candidates" is the stage's pool (pool_count); rows written for live applicants outside it (addenda, D70(b)
  // warnings) are counted separately, the same split as the stage page (L8). The pool of a later stage is everyone
  // published as shortlisted in the stage before it (PipelineService::pool).
  const outsidePool = (index) => {
    // The server's count is the definition (AdminPipelineController::show); the local fallback mirrors it.
    if (typeof data.rounds[index]?.outside_pool_count === "number") return data.rounds[index].outside_pool_count;
    if (index === 0) return 0;
    const round = data.rounds[index];
    const previous = data.rounds[index - 1];
    return (data.applications ?? []).filter((a) => {
      const prev = a.results?.[previous.id];
      return a.results?.[round.id] && !(prev?.published && prev.result === "selected");
    }).length;
  };
  const statusCounts = (data.applications ?? []).reduce((acc, a) => {
    const key = overallStatus(a, data.rounds);
    acc[key] = (acc[key] ?? 0) + 1;
    return acc;
  }, {});

  return (
    <Stack spacing={2}>
      {data.accepts_applications && (
        <Alert severity="info">Applications are still open. Close applications (Overview tab) or wait for the deadline before entering results.</Alert>
      )}
      {error && <Alert severity="error">{error}</Alert>}
      {report && (
        <Alert severity="warning" onClose={() => setReport(null)}>
          <AlertTitle>Check these roll numbers</AlertTitle>
          {(report.errors ?? []).map((e, i) => (
            <div key={`e-${i}-${e.roll_no}`}>
              {e.roll_no}: {e.reason}
            </div>
          ))}
          {(report.warnings ?? []).map((w, i) => (
            <div key={`w-${i}-${w.roll_no}`}>
              {w.roll_no}: {w.reason} (saved anyway)
            </div>
          ))}
        </Alert>
      )}

      <Stack direction={{ xs: "column", sm: "row" }} justifyContent="space-between" spacing={1}>
        <TextField size="small" label="Search Roll Number, name, branch" value={search} onChange={(e) => setSearch(e.target.value)} />
        <Stack direction="row" spacing={1} alignItems="center">
          <Typography variant="body2" color="text.secondary">
            {rows.length} active applicant(s) · dashed = draft, filled = published
          </Typography>
          <Button
            size="small"
            variant="outlined"
            startIcon={<DownloadIcon />}
            onClick={() => adminDownload(`/admin/postings/${posting.id}/export`, `posting-${posting.id}-applicants.xlsx`).catch((e) => setError(e.message))}
          >
            Excel
          </Button>
        </Stack>
      </Stack>
      <Stack direction="row" spacing={1} flexWrap="wrap" useFlexGap alignItems="center">
        <Typography variant="caption" color="text.secondary">
          Status:
        </Typography>
        {Object.entries(OVERALL).map(([key, s]) => (
          <Chip key={key} size="small" color={s.color} variant="outlined" label={`${s.label} (${statusCounts[key] ?? 0})`} />
        ))}
        <Typography variant="caption" color="text.secondary">
          Based on published results only.
        </Typography>
      </Stack>

      <TableContainer sx={{ maxHeight: 640, border: 1, borderColor: "divider", borderRadius: 1 }}>
        <Table size="small" stickyHeader>
          <TableHead>
            <TableRow>
              <TableCell sx={{ position: "sticky", left: 0, zIndex: 3, bgcolor: "background.paper", minWidth: 200 }}>Student</TableCell>
              <TableCell sx={{ minWidth: 120 }}>Status</TableCell>
              {data.rounds.map((round, index) => (
                <TableCell key={round.id} sx={{ minWidth: 190 }}>
                  <Stack direction="row" alignItems="center" justifyContent="space-between">
                    <Box>
                      <Typography variant="body2" fontWeight={700} component="div">
                        {round.name} {round.is_final && <Chip size="small" label="Final" color="primary" sx={{ ml: 0.5 }} />}
                      </Typography>
                      <Stack direction="row" spacing={0.5} alignItems="center">
                        <Chip size="small" variant="outlined" color={statusColor(round.status)} label={titleCase(round.status)} />
                        <Typography variant="caption" color="text.secondary">
                          {selectedIn(round)} selected out of {round.pool_count} candidates
                          {outsidePool(index) > 0 ? ` (+${outsidePool(index)} outside pool)` : ""} · {round.draft_count} draft
                        </Typography>
                      </Stack>
                      <Button
                        component={Link}
                        href={`/admin/postings/${posting.id}/rounds/${round.id}`}
                        size="small"
                        endIcon={<OpenInNewIcon fontSize="inherit" />}
                        sx={{ px: 0, minWidth: 0, textTransform: "none" }}
                      >
                        Shortlist for {round.name}
                      </Button>
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
                <TableCell colSpan={data.rounds.length + 2}>No active applicants.</TableCell>
              </TableRow>
            )}
            {rows.map((a) => (
              <TableRow key={a.id} hover>
                <TableCell sx={{ position: "sticky", left: 0, zIndex: 1, bgcolor: "background.paper" }}>
                  <Typography variant="body2" fontWeight={600}>
                    <Link href={`/admin/students/${a.student?.id}`}>{a.student?.roll_no}</Link>{" "}
                    <Typography component="button" type="button" variant="body2" onClick={() => setQuickView(a.student?.id)} sx={nameButtonSx}>
                      {a.student?.full_name}
                    </Typography>
                    {a.offers?.length > 0 && (
                      <Tooltip title={a.offers.map((o) => `Placed in ${o.role ?? "a role"} at ${o.company ?? "a company"}`).join(" · ")}>
                        <EmojiEventsIcon fontSize="small" sx={{ color: "warning.main", ml: 0.5, verticalAlign: "text-bottom" }} />
                      </Tooltip>
                    )}
                  </Typography>
                  <Typography variant="caption" color="text.secondary">
                    {a.student?.branch} · {a.student?.current_cgpa ?? "—"}
                  </Typography>
                  <Stack direction="row" spacing={0.5}>
                    {a.used_unverified_resume && <Chip size="small" color="warning" label="⚠ Unverified" />}
                    {a.placed_elsewhere_flag && <Chip size="small" color="error" label="🚩 Placed" />}
                  </Stack>
                </TableCell>
                <TableCell>
                  <Chip size="small" color={OVERALL[overallStatus(a, data.rounds)].color} label={OVERALL[overallStatus(a, data.rounds)].label} />
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
        <MenuItem component={Link} href={menu ? `/admin/postings/${posting.id}/rounds/${menu.round.id}` : "#"} onClick={() => setMenu(null)}>
          Open Shortlist for {menu?.round?.name}
        </MenuItem>
        <MenuItem
          onClick={() => {
            setDialog({ kind: "results", round: menu.round });
            setMenu(null);
          }}
        >
          Bulk Shortlist / Reject / Hold
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
          {menu?.round?.is_final ? "Publish from Shortlist for Offer" : "Publish Shortlist to Students"}
        </MenuItem>
      </Menu>

      <StudentQuickView studentId={quickView} onClose={() => setQuickView(null)} />

      {dialog?.kind === "results" && (
        <BulkShortlistDialog
          postingId={posting.id}
          round={dialog.round}
          open
          onClose={() => setDialog(null)}
          onDone={async (response) => {
            onMessage?.(response.message);
            setReport(response.errors?.length || response.warnings?.length ? response : null);
            setDialog(null);
            await load();
            onChanged?.();
          }}
        />
      )}

      <Dialog open={Boolean(dialog) && dialog.kind !== "results"} onClose={() => !busy && setDialog(null)} maxWidth="sm" fullWidth>
        <DialogTitle>
          {dialog?.kind === "attendance" && `Attendance — ${dialog.round.name}`}
          {dialog?.kind === "addendum" && `Addendum — ${dialog.round.name}`}
          {dialog?.kind === "publish" && `Publish ${dialog.round.name} shortlist to students?`}
          {dialog?.kind === "readd" && "Re-add previously rejected candidate"}
        </DialogTitle>
        <DialogContent>
          <Stack spacing={2} sx={{ pt: 1 }}>
            {error && <Alert severity="error">{error}</Alert>}
            {dialog?.kind === "attendance" && (
              <>
                <TextField multiline minRows={4} label="Present (roll numbers)" value={dialog.present} onChange={(e) => setDialog((d) => ({ ...d, present: e.target.value }))} />
                <TextField multiline minRows={3} label="Absent (roll numbers)" value={dialog.absent} onChange={(e) => setDialog((d) => ({ ...d, absent: e.target.value }))} />
              </>
            )}
            {dialog?.kind === "addendum" && (
              <>
                <Alert severity="info">Adds candidates to a stage whose results are already published. They are flagged as addendum and notified when you publish again.</Alert>
                <TextField multiline minRows={4} label="Roll numbers" value={dialog.text} onChange={(e) => setDialog((d) => ({ ...d, text: e.target.value }))} />
                <TextField label="Remark (optional)" value={dialog.remark} onChange={(e) => setDialog((d) => ({ ...d, remark: e.target.value }))} />
              </>
            )}
            {dialog?.kind === "publish" && (
              <>
                <Typography>
                  {dialog.round.draft_count} draft result(s) will become visible and every affected student is emailed (shortlisted/on hold: result mail;
                  rejected: regret mail).
                </Typography>
                <FormControlLabel
                  control={<Checkbox checked={dialog.rejectRemaining} onChange={(e) => setDialog((d) => ({ ...d, rejectRemaining: e.target.checked }))} />}
                  label={`Mark everyone else in this stage's pool (${dialog.round.pool_count}) as not selected`}
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
            {dialog?.kind === "publish" ? "Continue" : "Save"}
          </Button>
        </DialogActions>
      </Dialog>
    </Stack>
  );
}
