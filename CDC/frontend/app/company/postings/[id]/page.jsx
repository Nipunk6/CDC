"use client";

import { use, useCallback, useEffect, useMemo, useState } from "react";
import {
  Alert,
  Autocomplete,
  Box,
  Button,
  Card,
  CardContent,
  Chip,
  Dialog,
  DialogActions,
  DialogContent,
  DialogTitle,
  FormControl,
  InputLabel,
  LinearProgress,
  MenuItem,
  Select,
  Stack,
  Tab,
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TableRow,
  Tabs,
  TextField,
  Typography,
} from "@mui/material";
import WorkIcon from "@mui/icons-material/Work";
import SendIcon from "@mui/icons-material/Send";
import DownloadIcon from "@mui/icons-material/Download";

import PageHeader from "@/components/shared/pageheader";
import { companyApi } from "@/lib/companyapi";
import { companyDownload } from "@/lib/companydownload";
import { formatDateTime, postingStatusLabel, statusColor, titleCase } from "@/lib/format";

const kindLabels = { shortlist: "Shortlist", waitlist: "On Hold", addendum: "Addendum", replacement_request: "Replacement request" };
// A completed drive still takes these (QA F-010).
const COMPLETED_KINDS = ["replacement_request", "addendum"];
const resultColors = { selected: "success", rejected: "error", waitlisted: "info", pending: "default" };
const companyResultLabel = (result, isFinal) =>
  ({ selected: isFinal ? "Selected" : "Shortlisted", rejected: "Not selected", waitlisted: "On Hold", pending: "Pending" })[result] ?? titleCase(result);

export default function CompanyPostingDetailPage({ params }) {
  const { id } = use(params);
  const [posting, setPosting] = useState(null);
  const [applicants, setApplicants] = useState([]);
  const [share, setShare] = useState(false);
  const [proposals, setProposals] = useState([]);
  const [tab, setTab] = useState("applicants");
  const [error, setError] = useState(null);
  const [success, setSuccess] = useState(null);
  const [dialog, setDialog] = useState(null);
  const [dialogError, setDialogError] = useState(null);
  const [busy, setBusy] = useState(false);

  const load = useCallback(async () => {
    try {
      const [p, a, pr] = await Promise.all([
        companyApi(`/company/postings/${id}`),
        companyApi(`/company/postings/${id}/applicants`),
        companyApi(`/company/postings/${id}/proposals`),
      ]);
      setPosting(p.posting);
      setApplicants(a.applicants ?? []);
      setShare(Boolean(a.share_contact_details));
      setProposals(pr.proposals ?? []);
      setError(null);
    } catch (e) {
      setError(e instanceof Error ? e.message : "Failed to load this job profile.");
    }
  }, [id]);

  useEffect(() => {
    void load();
  }, [load]);

  const questions = useMemo(() => Object.fromEntries((posting?.questions ?? []).map((q) => [q.id, q.question])), [posting]);

  if (!posting) return error ? <Alert severity="error">{error}</Alert> : <LinearProgress />;

  const completed = posting.status === "completed";

  const submitProposal = async () => {
    setBusy(true);
    setDialogError(null);
    try {
      const response = await companyApi(`/company/postings/${id}/rounds/${dialog.roundId}/proposals`, {
        method: "POST",
        body: JSON.stringify({
          kind: dialog.kind,
          entries: dialog.selected.map((a) => ({ roll_no: a.roll_no })),
        }),
      });
      setSuccess(response.message);
      setDialog(null);
      await load();
    } catch (e) {
      setDialogError(e instanceof Error ? e.message : "Could not send the proposal.");
    } finally {
      setBusy(false);
    }
  };

  const exportApplicants = () =>
    companyDownload(`/company/postings/${id}/export`, `applicants-${id}.xlsx`).catch((e) => setError(e.message));

  return (
    <>
      <PageHeader
        icon={<WorkIcon />}
        title={posting.title}
        subtitle={`${posting.placement_cycle?.name ?? ""} · applications close ${formatDateTime(posting.application_deadline)}`}
        backHref="/company/postings"
        backLabel="All Job Profiles"
        actions={
          <>
            <Button variant="contained" color="secondary" startIcon={<DownloadIcon />} onClick={exportApplicants}>
              Download Applicants
            </Button>
            <Button
              variant="contained"
              color="secondary"
              startIcon={<SendIcon />}
              disabled={posting.status === "cancelled"}
              onClick={() => {
                setDialogError(null);
                setDialog(
                  completed
                    ? { kind: "replacement_request", roundId: String(posting.rounds?.at(-1)?.id ?? ""), selected: [] }
                    : { kind: "shortlist", roundId: String(posting.rounds?.find((r) => r.status !== "completed")?.id ?? posting.rounds?.[0]?.id ?? ""), selected: [] }
                );
              }}
            >
              {completed ? "Request replacement" : "Propose shortlist"}
            </Button>
          </>
        }
      />
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

      <Card sx={{ mb: 2 }}>
        <CardContent>
          <Stack direction="row" spacing={1} flexWrap="wrap" useFlexGap>
            <Chip color={statusColor(posting.status)} label={postingStatusLabel(posting)} />
            <Chip variant="outlined" label={`${posting.applicant_count} applicant(s)`} />
            {(posting.rounds ?? []).map((r) => (
              <Chip
                key={r.id}
                variant="outlined"
                color={statusColor(r.status)}
                label={`${r.name}: ${r.status === "completed" ? `${r.published_selected} ${r.is_final ? "selected" : "shortlisted"}${r.published_waitlisted ? `, ${r.published_waitlisted} on hold` : ""}` : titleCase(r.status)}`}
              />
            ))}
          </Stack>
          <Typography variant="caption" color="text.secondary" sx={{ display: "block", mt: 1 }}>
            You see results only after the CDC publishes them. {share ? "Contact details are shared for this job profile." : "Contact details are not shared for this job profile."}
          </Typography>
        </CardContent>
      </Card>

      <Card>
        <Tabs value={tab} onChange={(_e, v) => setTab(v)}>
          <Tab value="applicants" label={`Applicants (${applicants.length})`} />
          <Tab value="proposals" label={`My proposals (${proposals.length})`} />
        </Tabs>
        <CardContent>
          {tab === "applicants" && (
            <TableContainer sx={{ maxHeight: 640 }}>
              <Table size="small" stickyHeader>
                <TableHead>
                  <TableRow>
                    <TableCell>Roll Number</TableCell>
                    <TableCell>Name</TableCell>
                    <TableCell>Branch</TableCell>
                    <TableCell>CGPA</TableCell>
                    <TableCell>Backlogs</TableCell>
                    <TableCell>Class X / XII Percentage</TableCell>
                    {share && <TableCell>Contact</TableCell>}
                    <TableCell>Resume</TableCell>
                    {(posting.rounds ?? []).map((r) => (
                      <TableCell key={r.id}>{r.name}</TableCell>
                    ))}
                    {(posting.questions ?? []).length > 0 && <TableCell>Answers</TableCell>}
                  </TableRow>
                </TableHead>
                <TableBody>
                  {applicants.length === 0 && (
                    <TableRow>
                      <TableCell colSpan={8 + (posting.rounds?.length ?? 0)}>No applicants yet.</TableCell>
                    </TableRow>
                  )}
                  {applicants.map((a) => (
                    <TableRow key={a.application_id} hover>
                      <TableCell sx={{ fontWeight: 600 }}>{a.roll_no}</TableCell>
                      <TableCell>{a.full_name}</TableCell>
                      <TableCell>{a.branch}</TableCell>
                      <TableCell>{a.current_cgpa ?? "—"}</TableCell>
                      <TableCell>
                        {a.ongoing_backlogs} / {a.total_backlogs}
                      </TableCell>
                      <TableCell>
                        {a.tenth_percent ?? "—"} / {a.twelfth_percent ?? "—"}
                      </TableCell>
                      {share && (
                        <TableCell>
                          {a.phone ?? "—"}
                          <br />
                          {a.personal_email ?? a.institute_email}
                        </TableCell>
                      )}
                      <TableCell>
                        {a.resume_url ? (
                          <a href={a.resume_url} target="_blank" rel="noopener">
                            View
                          </a>
                        ) : (
                          "—"
                        )}
                      </TableCell>
                      {(posting.rounds ?? []).map((r) => {
                        const cell = a.rounds?.[r.id];
                        return (
                          <TableCell key={r.id}>
                            {cell ? (
                              <Chip size="small" color={resultColors[cell.result]} label={companyResultLabel(cell.result, r.is_final)} />
                            ) : (
                              "—"
                            )}
                          </TableCell>
                        );
                      })}
                      {(posting.questions ?? []).length > 0 && (
                        <TableCell sx={{ minWidth: 220 }}>
                          {(a.answers ?? []).map((ans) => (
                            <Typography key={ans.question_id} variant="caption" display="block">
                              <strong>{questions[ans.question_id] ?? "Q"}:</strong> {Array.isArray(ans.answer) ? ans.answer.join(", ") : ans.answer}
                            </Typography>
                          ))}
                        </TableCell>
                      )}
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </TableContainer>
          )}

          {tab === "proposals" && (
            <Stack spacing={1.5}>
              {proposals.length === 0 && <Typography color="text.secondary">You have not proposed anything yet.</Typography>}
              {proposals.map((p) => (
                <Box key={p.id} sx={{ p: 1.5, border: 1, borderColor: "divider", borderRadius: 1 }}>
                  <Stack direction="row" spacing={1} alignItems="center" flexWrap="wrap" useFlexGap>
                    <Typography fontWeight={600}>
                      {kindLabels[p.kind]} · {p.posting_round?.name}
                    </Typography>
                    <Chip size="small" variant="outlined" color={statusColor(p.status)} label={titleCase(p.status)} />
                    <Typography variant="caption" color="text.secondary">
                      {formatDateTime(p.created_at)} · {(p.payload ?? []).length} candidate(s)
                    </Typography>
                  </Stack>
                  {p.admin_remark && <Typography variant="body2">CDC remark: {p.admin_remark}</Typography>}
                </Box>
              ))}
            </Stack>
          )}
        </CardContent>
      </Card>

      <Dialog open={Boolean(dialog)} onClose={() => !busy && setDialog(null)} maxWidth="sm" fullWidth>
        <DialogTitle>Propose candidates</DialogTitle>
        <DialogContent>
          <Stack spacing={2} sx={{ pt: 1 }}>
            {dialogError && <Alert severity="error">{dialogError}</Alert>}
            <Alert severity="info">The CDC reviews every proposal. Students are informed only when the CDC publishes the stage.</Alert>
            <Stack direction={{ xs: "column", sm: "row" }} spacing={2}>
              <FormControl fullWidth size="small">
                <InputLabel id="p-kind">Type</InputLabel>
                <Select labelId="p-kind" label="Type" value={dialog?.kind ?? "shortlist"} onChange={(e) => setDialog((d) => ({ ...d, kind: e.target.value }))}>
                  {Object.entries(kindLabels)
                    .filter(([k]) => !completed || COMPLETED_KINDS.includes(k))
                    .map(([k, label]) => (
                      <MenuItem key={k} value={k}>
                        {label}
                      </MenuItem>
                    ))}
                </Select>
              </FormControl>
              <FormControl fullWidth size="small">
                <InputLabel id="p-round">Stage</InputLabel>
                <Select labelId="p-round" label="Stage" value={dialog?.roundId ?? ""} onChange={(e) => setDialog((d) => ({ ...d, roundId: e.target.value }))}>
                  {(posting.rounds ?? []).map((r) => (
                    <MenuItem key={r.id} value={String(r.id)}>
                      {r.name}
                    </MenuItem>
                  ))}
                </Select>
              </FormControl>
            </Stack>
            <Autocomplete
              multiple
              options={applicants}
              value={dialog?.selected ?? []}
              onChange={(_e, value) => setDialog((d) => ({ ...d, selected: value }))}
              getOptionLabel={(a) => `${a.roll_no} — ${a.full_name}`}
              isOptionEqualToValue={(a, b) => a.application_id === b.application_id}
              filterSelectedOptions
              renderInput={(params) => <TextField {...params} label="Candidates (type roll no or name)" />}
            />
            {dialog?.kind === "waitlist" && (
              <Typography variant="caption" color="text.secondary">
                List everyone who should be on hold at this stage (there is no order). Anyone currently on hold but not listed here is taken off hold.
              </Typography>
            )}
          </Stack>
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setDialog(null)} disabled={busy}>
            Cancel
          </Button>
          <Button variant="contained" onClick={submitProposal} disabled={busy || !dialog?.roundId || (dialog?.selected ?? []).length === 0}>
            Send to CDC
          </Button>
        </DialogActions>
      </Dialog>
    </>
  );
}
