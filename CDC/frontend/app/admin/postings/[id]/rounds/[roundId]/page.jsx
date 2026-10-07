"use client";

import { use, useCallback, useEffect, useState } from "react";
import Link from "next/link";
import {
  Alert,
  AlertTitle,
  Box,
  Button,
  ButtonGroup,
  Card,
  CardContent,
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
  MenuItem,
  Pagination,
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
import { alpha } from "@mui/material/styles";
import ArrowBackIcon from "@mui/icons-material/ArrowBack";
import ArrowForwardIcon from "@mui/icons-material/ArrowForward";
import ChecklistIcon from "@mui/icons-material/Checklist";
import EmojiEventsIcon from "@mui/icons-material/EmojiEvents";
import CloseIcon from "@mui/icons-material/Close";

import PageHeader from "@/components/shared/pageheader";
import StudentQuickView from "@/components/admin/studentquickview";
import BulkShortlistDialog from "@/components/admin/posting/bulkshortlistdialog";
import TemplateDownloadButton from "@/components/admin/templatedownloadbutton";
import NoticeComposer from "@/components/admin/engagement/noticecomposer";
import StageEmailDialog from "@/components/admin/engagement/stageemaildialog";
import ReconcileDialog from "@/components/admin/posting/reconciledialog";
import { adminApi } from "@/lib/adminapi";

const COLORS = { selected: "success", waitlisted: "info", rejected: "error" };

const decisionLabel = (result, isFinal) =>
  ({ selected: isFinal ? "Selected" : "Shortlisted", waitlisted: "On Hold", rejected: "Rejected" })[result] ?? "Undecided";

function DecisionChip({ row, isFinal }) {
  if (!row.result || row.result === "pending") {
    return <Chip size="small" variant="outlined" label="Undecided" />;
  }
  return (
    <Tooltip title={row.published ? "Published — visible to the student" : "Draft — not visible to the student or company yet"}>
      <Chip
        size="small"
        color={COLORS[row.result]}
        variant={row.published ? "filled" : "outlined"}
        sx={row.published ? undefined : { borderStyle: "dashed" }}
        label={`${decisionLabel(row.result, isFinal)}${row.is_addendum ? " +" : ""}${row.published ? "" : " (draft)"}`}
      />
    </Tooltip>
  );
}

const rowSx = (row) => (theme) => {
  if (!row.result || row.result === "pending") return {};
  const color = theme.palette[COLORS[row.result]].main;
  return row.published
    ? { bgcolor: alpha(color, 0.1) }
    : { bgcolor: alpha(color, 0.03), boxShadow: `inset 4px 0 0 ${alpha(color, 0.6)}` };
};

/**
 * "Shortlist for <Stage>" (Superset parity S1): one stage's candidates, decided one by one or in bulk. Every decision
 * is a draft until "Publish Shortlist to Students"; published rows are protected (D70e — Re-add / Addendum on the
 * Progress Grid are the only late paths).
 */
export default function StageShortlistPage({ params }) {
  const { id, roundId } = use(params);
  // Keyed by stage so the ← / → arrows start each stage with a clean selection, filters and page.
  return <StageShortlist key={roundId} id={id} roundId={roundId} />;
}

function StageShortlist({ id, roundId }) {
  const [data, setData] = useState(null);
  const [error, setError] = useState(null);
  const [success, setSuccess] = useState(null);
  const [report, setReport] = useState(null);
  const [search, setSearch] = useState("");
  const [query, setQuery] = useState({ search: "", sort: "roll_no", direction: "asc", decision: "all", page: 1 });
  const [selected, setSelected] = useState([]);
  const [busy, setBusy] = useState(false);
  const [bulkOpen, setBulkOpen] = useState(false);
  const [publish, setPublish] = useState(null);
  const [quickView, setQuickView] = useState(null);
  const [comms, setComms] = useState(null); // "notice" | "email" (Superset parity S7.2)
  const [reconcileOpen, setReconcileOpen] = useState(false);

  const base = `/admin/postings/${id}/rounds/${roundId}`;

  const load = useCallback(async () => {
    try {
      const params = new URLSearchParams({ ...query, page: String(query.page) });
      setData(await adminApi(`${base}/shortlist?${params}`));
      setError(null);
    } catch (e) {
      setError(e instanceof Error ? e.message : "Failed to load this stage.");
    }
  }, [base, query]);

  useEffect(() => {
    void load();
  }, [load]);

  // Debounced search box → query.
  useEffect(() => {
    const timer = setTimeout(() => setQuery((q) => (q.search === search ? q : { ...q, search, page: 1 })), 300);
    return () => clearTimeout(timer);
  }, [search]);

  const showReport = (response) => {
    setSuccess(response.message);
    setReport(response.errors?.length || response.warnings?.length ? response : null);
  };

  const decide = async (rows, result) => {
    const editable = rows.filter((r) => !r.published);
    if (editable.length === 0) {
      setError("Published decisions cannot be changed here. Use Re-add or Addendum on the Progress Grid.");
      return;
    }
    setBusy(true);
    setError(null);
    try {
      const response = await adminApi(`${base}/results`, {
        method: "POST",
        body: JSON.stringify({ entries: editable.map((r) => ({ roll_no: r.student.roll_no, result })) }),
      });
      showReport(response);
      setSelected([]);
      await load();
    } catch (e) {
      setError(e instanceof Error ? e.message : "Request failed.");
    } finally {
      setBusy(false);
    }
  };

  const clearDraft = async (row) => {
    setBusy(true);
    setError(null);
    try {
      const response = await adminApi(`${base}/results/${row.application_id}`, { method: "DELETE" });
      setSuccess(response.message);
      await load();
    } catch (e) {
      setError(e instanceof Error ? e.message : "Request failed.");
    } finally {
      setBusy(false);
    }
  };

  const runPublish = async () => {
    setBusy(true);
    setError(null);
    try {
      const response = await adminApi(`${base}/publish`, { method: "POST", body: JSON.stringify({ reject_remaining: publish.rejectRemaining }) });
      setSuccess(response.message);
      setPublish(null);
      await load();
    } catch (e) {
      setError(e instanceof Error ? e.message : "Request failed.");
    } finally {
      setBusy(false);
    }
  };

  if (!data) {
    return error ? <Alert severity="error">{error}</Alert> : <LinearProgress />;
  }

  const { posting, round, counts, candidates, meta } = data;
  const isFinal = round.is_final;
  const locked = posting.accepts_applications || posting.status === "cancelled";
  const pageRows = candidates;
  const selectedRows = pageRows.filter((r) => selected.includes(r.application_id));
  const selectableIds = pageRows.filter((r) => !r.published).map((r) => r.application_id);
  const allSelected = selectableIds.length > 0 && selectableIds.every((rid) => selected.includes(rid));

  const toggle = (rid) => setSelected((s) => (s.includes(rid) ? s.filter((x) => x !== rid) : [...s, rid]));

  return (
    <>
      <PageHeader
        icon={<ChecklistIcon />}
        title={`Shortlist for ${round.name}`}
        subtitle={`${posting.company_name ?? ""} · ${posting.title} · Stage ${round.position} of ${data.rounds.length}`}
        backHref={`/admin/postings/${posting.id}`}
        backLabel="Back to Job Profile"
        actions={
          <>
            <TemplateDownloadButton
              label="Download Current Shortlist"
              path={`${base}/shortlist/export`}
              fileName={`stage-${round.id}-shortlist.xlsx`}
              onError={setError}
            />
            {isFinal && (
              <Button component={Link} href={`/admin/postings/${posting.id}/results`} variant="contained" color="secondary" startIcon={<EmojiEventsIcon />}>
                Shortlist for Offer
              </Button>
            )}
          </>
        }
      />

      {locked && (
        <Alert severity="info" sx={{ mb: 2 }}>
          {posting.status === "cancelled"
            ? "This job profile is cancelled."
            : "Applications are still open. Close applications (Overview tab) or wait for the deadline before deciding this stage."}
        </Alert>
      )}
      {error && (
        <Alert severity="error" sx={{ mb: 2 }} onClose={() => setError(null)}>
          {error}
        </Alert>
      )}
      {success && (
        <Alert severity="success" sx={{ mb: 2 }} onClose={() => setSuccess(null)}>
          {success}
        </Alert>
      )}
      {report && (
        <Alert severity="warning" sx={{ mb: 2 }} onClose={() => setReport(null)}>
          <AlertTitle>Check these students</AlertTitle>
          {(report.errors ?? []).map((e, i) => (
            <div key={`e-${e.roll_no}-${i}`}>
              {e.roll_no}: {e.reason}
            </div>
          ))}
          {(report.warnings ?? []).map((w, i) => (
            <div key={`w-${w.roll_no}-${i}`}>
              {w.roll_no}: {w.reason} (saved anyway)
            </div>
          ))}
        </Alert>
      )}

      <Card sx={{ mb: 2 }}>
        <CardContent>
          <Stack direction={{ xs: "column", md: "row" }} spacing={2} alignItems={{ md: "center" }} justifyContent="space-between">
            <Stack direction="row" spacing={1} alignItems="center">
              <Tooltip title={data.previous_round ? `Previous stage: ${data.previous_round.name}` : "This is the first stage"}>
                <span>
                  <IconButton
                    component={data.previous_round ? Link : "button"}
                    href={data.previous_round ? `/admin/postings/${posting.id}/rounds/${data.previous_round.id}` : undefined}
                    disabled={!data.previous_round}
                    aria-label="Previous stage"
                  >
                    <ArrowBackIcon />
                  </IconButton>
                </span>
              </Tooltip>
              <Box>
                <Typography variant="overline" color="text.secondary" sx={{ lineHeight: 1.2 }}>
                  {round.name} → {data.next_label}
                </Typography>
                <Typography variant="h6" fontWeight={700}>
                  {counts.selected} selected out of {counts.pool ?? counts.candidates} candidates
                  {counts.outside_pool > 0 ? ` (+${counts.outside_pool} outside pool)` : ""}
                </Typography>
                <Typography variant="caption" color="text.secondary">
                  {counts.waitlisted} on hold · {counts.rejected} rejected · {counts.undecided} undecided · {counts.drafts} draft · {counts.published} published
                </Typography>
              </Box>
              <Tooltip title={data.next_round ? `Next stage: ${data.next_round.name}` : "This is the last stage"}>
                <span>
                  <IconButton
                    component={data.next_round ? Link : "button"}
                    href={data.next_round ? `/admin/postings/${posting.id}/rounds/${data.next_round.id}` : undefined}
                    disabled={!data.next_round}
                    aria-label="Next stage"
                  >
                    <ArrowForwardIcon />
                  </IconButton>
                </span>
              </Tooltip>
            </Stack>
            <Stack direction={{ xs: "column", sm: "row" }} spacing={1} flexWrap="wrap" useFlexGap justifyContent={{ md: "flex-end" }}>
              <Button variant="outlined" disabled={locked} onClick={() => setBulkOpen(true)}>
                Bulk Shortlist / Reject / Hold
              </Button>
              <Button variant="outlined" color="error" onClick={() => setReconcileOpen(true)}>
                Reconcile Ineligible Students
              </Button>
              <Button variant="outlined" onClick={() => setComms("notice")}>
                Send Notice
              </Button>
              <Button variant="outlined" onClick={() => setComms("email")}>
                Send Email to Shortlisted / On Hold
              </Button>
              {isFinal ? (
                <Button component={Link} href={`/admin/postings/${posting.id}/results`} variant="contained">
                  Announce on Shortlist for Offer
                </Button>
              ) : (
                <Button variant="contained" disabled={locked || busy} onClick={() => setPublish({ rejectRemaining: round.status !== "completed" })}>
                  Publish Shortlist to Students
                </Button>
              )}
            </Stack>
          </Stack>
        </CardContent>
      </Card>

      <Card>
        <CardContent>
          <Stack direction={{ xs: "column", md: "row" }} spacing={1.5} sx={{ mb: 2 }}>
            <TextField size="small" label="Search by name or Roll" value={search} onChange={(e) => setSearch(e.target.value)} sx={{ minWidth: { md: 260 } }} />
            <FormControl size="small" sx={{ minWidth: 170 }}>
              <InputLabel id="decision">Decision</InputLabel>
              <Select labelId="decision" label="Decision" value={query.decision} onChange={(e) => setQuery((q) => ({ ...q, decision: e.target.value, page: 1 }))}>
                <MenuItem value="all">All</MenuItem>
                <MenuItem value="selected">{isFinal ? "Selected" : "Shortlisted"}</MenuItem>
                <MenuItem value="waitlisted">On Hold</MenuItem>
                <MenuItem value="rejected">Rejected</MenuItem>
                <MenuItem value="undecided">Undecided</MenuItem>
              </Select>
            </FormControl>
            <FormControl size="small" sx={{ minWidth: 170 }}>
              <InputLabel id="sort">Sort by</InputLabel>
              <Select
                labelId="sort"
                label="Sort by"
                value={`${query.sort}:${query.direction}`}
                onChange={(e) => {
                  const [sort, direction] = e.target.value.split(":");
                  setQuery((q) => ({ ...q, sort, direction, page: 1 }));
                }}
              >
                <MenuItem value="roll_no:asc">Roll Number</MenuItem>
                <MenuItem value="name:asc">Name (A–Z)</MenuItem>
                <MenuItem value="cgpa:desc">CGPA (high to low)</MenuItem>
                <MenuItem value="decision:asc">Decision</MenuItem>
              </Select>
            </FormControl>
          </Stack>

          {selectedRows.length > 0 && (
            <Stack direction={{ xs: "column", sm: "row" }} spacing={1} alignItems={{ sm: "center" }} sx={{ mb: 2, p: 1.5, borderRadius: 1, bgcolor: "action.hover" }}>
              <Typography variant="body2" fontWeight={600} sx={{ flexGrow: 1 }}>
                {selectedRows.length} selected
              </Typography>
              <Button size="small" color="success" variant="contained" disabled={busy || locked} onClick={() => decide(selectedRows, "selected")}>
                Shortlist
              </Button>
              <Button size="small" color="error" variant="contained" disabled={busy || locked} onClick={() => decide(selectedRows, "rejected")}>
                Reject
              </Button>
              <Button size="small" color="info" variant="contained" disabled={busy || locked} onClick={() => decide(selectedRows, "waitlisted")}>
                Hold
              </Button>
              <Button size="small" onClick={() => setSelected([])}>
                Clear selection
              </Button>
            </Stack>
          )}

          {busy && <LinearProgress sx={{ mb: 1 }} />}
          <TableContainer sx={{ border: 1, borderColor: "divider", borderRadius: 1 }}>
            <Table size="small">
              <TableHead>
                <TableRow>
                  <TableCell padding="checkbox">
                    <Checkbox
                      checked={allSelected}
                      indeterminate={!allSelected && selected.length > 0}
                      disabled={selectableIds.length === 0 || locked}
                      onChange={() => setSelected(allSelected ? [] : selectableIds)}
                      inputProps={{ "aria-label": "Select all on this page" }}
                    />
                  </TableCell>
                  <TableCell>Student</TableCell>
                  <TableCell sx={{ display: { xs: "none", md: "table-cell" } }}>Branch</TableCell>
                  <TableCell sx={{ display: { xs: "none", sm: "table-cell" } }}>CGPA</TableCell>
                  <TableCell>Decision</TableCell>
                  <TableCell align="right">Action</TableCell>
                </TableRow>
              </TableHead>
              <TableBody>
                {pageRows.length === 0 && (
                  <TableRow>
                    <TableCell colSpan={6}>
                      <Typography variant="body2" color="text.secondary" sx={{ py: 2 }}>
                        {(counts.pool ?? counts.candidates) + (counts.outside_pool ?? 0) === 0 ? "Nobody is in this stage yet. Publish the previous stage's shortlist first." : "No candidate matches."}
                      </Typography>
                    </TableCell>
                  </TableRow>
                )}
                {pageRows.map((row) => (
                  <TableRow key={row.application_id} hover sx={rowSx(row)}>
                    <TableCell padding="checkbox">
                      <Checkbox
                        checked={selected.includes(row.application_id)}
                        disabled={row.published || locked}
                        onChange={() => toggle(row.application_id)}
                        inputProps={{ "aria-label": `Select ${row.student.roll_no}` }}
                      />
                    </TableCell>
                    <TableCell>
                      <Stack direction="row" spacing={0.5} alignItems="center">
                        <Typography
                          component="button"
                          variant="body2"
                          fontWeight={600}
                          onClick={() => setQuickView(row.student.id)}
                          sx={{ border: 0, p: 0, bgcolor: "transparent", color: "primary.main", cursor: "pointer", textAlign: "left", font: "inherit", fontWeight: 600 }}
                        >
                          {row.student.full_name}
                        </Typography>
                        {row.offers?.length > 0 && (
                          <Tooltip title={row.offers.map((o) => `Placed in ${o.role ?? "a role"} at ${o.company ?? "a company"}`).join(" · ")}>
                            <EmojiEventsIcon fontSize="small" sx={{ color: "warning.main" }} />
                          </Tooltip>
                        )}
                      </Stack>
                      <Typography variant="caption" color="text.secondary">
                        {row.student.roll_no}
                        {!row.in_pool && " · outside this stage's pool"}
                        {row.attendance === "no" && " · absent"}
                      </Typography>
                    </TableCell>
                    <TableCell sx={{ display: { xs: "none", md: "table-cell" } }}>
                      <Typography variant="body2">{row.student.branch}</Typography>
                    </TableCell>
                    <TableCell sx={{ display: { xs: "none", sm: "table-cell" } }}>{row.student.current_cgpa ?? "—"}</TableCell>
                    <TableCell>
                      <DecisionChip row={row} isFinal={isFinal} />
                    </TableCell>
                    <TableCell align="right">
                      {row.published ? (
                        <Typography variant="caption" color="text.secondary">
                          Published
                        </Typography>
                      ) : (
                        <Stack direction="row" spacing={0.5} justifyContent="flex-end" alignItems="center">
                          <ButtonGroup size="small" disabled={busy || locked} sx={{ flexWrap: "nowrap" }}>
                            <Button color="success" variant={row.result === "selected" ? "contained" : "outlined"} onClick={() => decide([row], "selected")}>
                              Shortlist
                            </Button>
                            <Button color="error" variant={row.result === "rejected" ? "contained" : "outlined"} onClick={() => decide([row], "rejected")}>
                              Reject
                            </Button>
                            <Button color="info" variant={row.result === "waitlisted" ? "contained" : "outlined"} onClick={() => decide([row], "waitlisted")}>
                              Hold
                            </Button>
                          </ButtonGroup>
                          {row.result && row.result !== "pending" && (
                            <Tooltip title="Clear this draft decision">
                              <span>
                                <IconButton size="small" disabled={busy || locked} onClick={() => clearDraft(row)} aria-label="Clear draft">
                                  <CloseIcon fontSize="inherit" />
                                </IconButton>
                              </span>
                            </Tooltip>
                          )}
                        </Stack>
                      )}
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </TableContainer>

          <Stack direction={{ xs: "column", sm: "row" }} justifyContent="space-between" alignItems="center" spacing={1} sx={{ mt: 2 }}>
            <Typography variant="body2" color="text.secondary">
              Showing page {meta.current_page} of {meta.last_page} ({meta.total} records) · dashed = draft, filled = published
            </Typography>
            {meta.last_page > 1 && (
              <Pagination count={meta.last_page} page={meta.current_page} onChange={(_e, page) => setQuery((q) => ({ ...q, page }))} color="primary" />
            )}
          </Stack>
        </CardContent>
      </Card>

      {bulkOpen && (
        <BulkShortlistDialog
          postingId={posting.id}
          round={round}
          open={bulkOpen}
          onClose={() => setBulkOpen(false)}
          onDone={(response) => {
            setBulkOpen(false);
            showReport(response);
            void load();
          }}
        />
      )}

      <Dialog open={Boolean(publish)} onClose={() => !busy && setPublish(null)} maxWidth="sm" fullWidth>
        <DialogTitle>Publish the {round.name} shortlist to students?</DialogTitle>
        <DialogContent>
          <Stack spacing={2} sx={{ pt: 1 }}>
            <Typography>
              Are you sure you want to publish the shortlist for {data.next_label} with {counts.selected} candidate(s) shortlisted? {counts.drafts} draft
              decision(s) become visible and every affected student is emailed.
            </Typography>
            {publish && (
              <FormControlLabel
                control={<Checkbox checked={publish.rejectRemaining} onChange={(e) => setPublish((p) => ({ ...p, rejectRemaining: e.target.checked }))} />}
                label={`Mark everyone else in this stage's pool (${counts.undecided} undecided) as not selected`}
              />
            )}
          </Stack>
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setPublish(null)} disabled={busy}>
            Cancel
          </Button>
          <Button variant="contained" onClick={runPublish} disabled={busy}>
            Continue
          </Button>
        </DialogActions>
      </Dialog>

      {comms === "notice" && (
        <NoticeComposer
          open
          banner={`Prefilled for the published Shortlisted / On Hold students of “${round.name}”. Draft decisions are never included.`}
          initialAudiences={[
            { audience_type: "round_results", audience_filter: { job_posting_id: String(posting.id), posting_round_id: String(round.id), results: ["selected", "waitlisted"] } },
          ]}
          onClose={() => setComms(null)}
          onDone={(message) => {
            setComms(null);
            setSuccess(message);
          }}
        />
      )}
      {comms === "email" && (
        <StageEmailDialog
          open
          postingId={posting.id}
          round={round}
          onClose={() => setComms(null)}
          onDone={(message) => {
            setComms(null);
            setSuccess(message);
          }}
        />
      )}

      {reconcileOpen && (
        <ReconcileDialog
          postingId={posting.id}
          round={round}
          onClose={() => setReconcileOpen(false)}
          onDone={(message) => {
            setReconcileOpen(false);
            setSuccess(message);
            void load();
          }}
        />
      )}

      <StudentQuickView studentId={quickView} onClose={() => setQuickView(null)} />
    </>
  );
}
