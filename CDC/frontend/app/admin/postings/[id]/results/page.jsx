"use client";

import { use, useCallback, useEffect, useMemo, useState } from "react";
import Link from "next/link";
import {
  Alert,
  AlertTitle,
  Box,
  Button,
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
  LinearProgress,
  MenuItem,
  Select,
  Stack,
  Switch,
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TableRow,
  TextField,
  Typography,
} from "@mui/material";
import EmojiEventsIcon from "@mui/icons-material/EmojiEvents";
import UploadFileIcon from "@mui/icons-material/UploadFile";

import BlockingRules from "@/components/shared/blockingrules";
import { EditOfferDialog, RevokeOfferDialog, UploadCtcsDialog } from "@/components/admin/offerdialogs";
import PageHeader from "@/components/shared/pageheader";
import StudentQuickView from "@/components/admin/studentquickview";
import { adminApi } from "@/lib/adminapi";
import { formatMoney } from "@/lib/format";
import { BLOCK_SCOPE_LABEL } from "@/lib/offerpolicy";

// A student's name opens the quick-view drawer (Superset parity S4.3).
const nameButtonSx = { border: 0, p: 0, bgcolor: "transparent", color: "primary.main", cursor: "pointer", textAlign: "left", font: "inherit", fontWeight: 600 };

const scopeLabel = (scope) => (scope ? `Blocks ${BLOCK_SCOPE_LABEL[scope].toLowerCase()}` : "No block");

const rowFrom = (item, offerTypes) => {
  const type = item.suggested.offer_type;
  const block = offerTypes.find((t) => t.value === type)?.block;
  return {
    application_id: item.application_id,
    include: true,
    offer_type: type,
    ctc_annual: item.suggested.ctc_annual ?? "",
    stipend_monthly: item.suggested.stipend_monthly ?? "",
    block: Boolean(block),
    block_scope: block?.scope ?? "all",
    overridden: false,
  };
};

/**
 * The announcement console (spec M7.5): final-round selections → offer type, CTC/stipend, block → publish.
 */
export default function AdminResultsPage({ params }) {
  const { id } = use(params);
  const [data, setData] = useState(null);
  const [rows, setRows] = useState({});
  const [error, setError] = useState(null);
  const [success, setSuccess] = useState(null);
  const [confirmOpen, setConfirmOpen] = useState(false);
  const [dialogError, setDialogError] = useState(null);
  const [busy, setBusy] = useState(false);
  const [offerDialog, setOfferDialog] = useState(null); // { kind: "edit" | "revoke", item }
  const [uploadOpen, setUploadOpen] = useState(false);
  const [quickView, setQuickView] = useState(null);

  const load = useCallback(async () => {
    try {
      const response = await adminApi(`/admin/postings/${id}/results/prepare`);
      setData(response);
      const next = {};
      [...response.selected, ...response.waitlisted].forEach((item) => {
        if (!item.offer) {
          // A published selection without an offer (its offer was revoked, S2) is not re-offered unless ticked again.
          // A candidate the API would refuse (holds an offer in this cycle, or blocked: QA F-035) is never pre-ticked.
          next[item.application_id] = { ...rowFrom(item, response.offer_types), include: item.result === "selected" && !item.published && !item.offer_refusal };
        }
      });
      setRows(next);
      setError(null);
    } catch (e) {
      setError(e instanceof Error ? e.message : "Failed to load results.");
    }
  }, [id]);

  useEffect(() => {
    void load();
  }, [load]);

  const update = (applicationId, patch) =>
    setRows((prev) => {
      const current = { ...prev[applicationId], ...patch };
      if (patch.offer_type && !prev[applicationId].overridden) {
        const block = data.offer_types.find((t) => t.value === patch.offer_type)?.block;
        current.block = Boolean(block);
        current.block_scope = block?.scope ?? current.block_scope;
      }
      return { ...prev, [applicationId]: current };
    });

  const included = useMemo(() => Object.values(rows).filter((r) => r.include), [rows]);

  if (!data) return error ? <Alert severity="error">{error}</Alert> : <LinearProgress />;

  const isInf = data.posting.form_type === "inf";
  const blocks = included.filter((r) => r.block).length;
  const regrets = data.regret_estimate + [...data.selected, ...data.waitlisted].filter((i) => !i.offer && !rows[i.application_id]?.include && i.result === "selected").length;

  const publish = async () => {
    setBusy(true);
    setDialogError(null);
    try {
      const response = await adminApi(`/admin/postings/${id}/results/publish`, {
        method: "POST",
        body: JSON.stringify({
          reject_remaining: true,
          selections: included.map((r) => ({
            application_id: r.application_id,
            offer_type: r.offer_type,
            ctc_annual: r.ctc_annual === "" ? null : Number(r.ctc_annual),
            stipend_monthly: r.stipend_monthly === "" ? null : Number(r.stipend_monthly),
            block: r.block,
            block_scope: r.block ? r.block_scope : null,
          })),
        }),
      });
      setSuccess(response.message);
      setConfirmOpen(false);
      await load();
    } catch (e) {
      setDialogError(e instanceof Error ? e.message : "Publishing failed.");
    } finally {
      setBusy(false);
    }
  };

  const renderRow = (item) => {
    const row = rows[item.application_id];
    const s = item.student;
    return (
      <TableRow key={item.application_id} hover selected={Boolean(row?.include)}>
        <TableCell padding="checkbox">
          {item.offer ? (
            <Chip size="small" color="success" label="Offered" />
          ) : (
            <Checkbox
              checked={Boolean(row?.include)}
              disabled={Boolean(item.offer_refusal)}
              onChange={(e) => update(item.application_id, { include: e.target.checked })}
            />
          )}
        </TableCell>
        <TableCell>
          <Typography variant="body2" fontWeight={600}>
            <Link href={`/admin/students/${s.id}`}>{s.roll_no}</Link>{" "}
            <Typography component="button" type="button" variant="body2" onClick={() => setQuickView(s.id)} sx={nameButtonSx}>
              {s.full_name}
            </Typography>
          </Typography>
          <Typography variant="caption" color="text.secondary">
            {s.branch} · CGPA {s.current_cgpa ?? "—"}
            {item.result === "waitlisted" ? " · on hold" : ""}
          </Typography>
          <Stack direction="row" spacing={0.5} sx={{ mt: 0.5 }} flexWrap="wrap" useFlexGap>
            {item.placed_elsewhere_flag && <Chip size="small" color="error" label="🚩 Placed elsewhere" />}
            {item.used_unverified_resume && <Chip size="small" color="warning" label="⚠ Unverified resume" />}
            {(item.active_blocks ?? []).map((m) => (
              <Chip key={m} size="small" color="warning" label={m} />
            ))}
          </Stack>
          {item.offer_refusal && !item.offer && (
            <Typography variant="caption" color="error" display="block" sx={{ mt: 0.5 }}>
              Cannot be offered: {item.offer_refusal}
            </Typography>
          )}
        </TableCell>
        {item.offer ? (
          <TableCell colSpan={3}>
            <Stack direction={{ xs: "column", md: "row" }} spacing={2} alignItems={{ md: "center" }}>
              <Typography variant="body2">
                {data.offer_types.find((t) => t.value === item.offer.offer_type)?.label ?? item.offer.offer_type}
                {item.offer.ctc_annual ? ` · ${formatMoney(item.offer.ctc_annual, item.offer.currency)} (YEAR)` : ""}
                {item.offer.stipend_monthly ? ` · ${formatMoney(item.offer.stipend_monthly, item.offer.currency)} (MONTH)` : ""}
              </Typography>
              <Stack direction="row" spacing={1}>
                <Button size="small" variant="outlined" onClick={() => setOfferDialog({ kind: "edit", item })}>
                  Edit
                </Button>
                <Button size="small" variant="outlined" color="error" onClick={() => setOfferDialog({ kind: "revoke", item })}>
                  Revoke
                </Button>
              </Stack>
            </Stack>
          </TableCell>
        ) : (
          <>
            <TableCell sx={{ minWidth: 220 }}>
              <FormControl size="small" fullWidth disabled={!row?.include}>
                <Select value={row?.offer_type ?? ""} onChange={(e) => update(item.application_id, { offer_type: e.target.value })}>
                  {data.offer_types.map((t) => (
                    <MenuItem key={t.value} value={t.value}>
                      {t.label}
                    </MenuItem>
                  ))}
                </Select>
              </FormControl>
            </TableCell>
            <TableCell sx={{ minWidth: 180 }}>
              <Stack spacing={1}>
                <TextField
                  size="small"
                  type="number"
                  label={`CTC Offered (${data.currency ?? "INR"})`}
                  helperText="CTC Interval: YEAR"
                  disabled={!row?.include}
                  value={row?.ctc_annual ?? ""}
                  onChange={(e) => update(item.application_id, { ctc_annual: e.target.value })}
                />
                {(isInf || ["intern", "intern_performance_ppo", "intern_fulltime"].includes(row?.offer_type)) && (
                  <TextField
                    size="small"
                    type="number"
                    label={`CTC Offered (${data.currency ?? "INR"})`}
                    helperText="CTC Interval: MONTH"
                    disabled={!row?.include}
                    value={row?.stipend_monthly ?? ""}
                    onChange={(e) => update(item.application_id, { stipend_monthly: e.target.value })}
                  />
                )}
              </Stack>
            </TableCell>
            <TableCell sx={{ minWidth: 230 }}>
              <FormControlLabel
                control={
                  <Switch
                    checked={Boolean(row?.block)}
                    disabled={!row?.include}
                    onChange={(e) => update(item.application_id, { block: e.target.checked, overridden: true })}
                  />
                }
                label={row?.block ? scopeLabel(row.block_scope) : "No block"}
              />
              {row?.block && (
                <Select
                  size="small"
                  value={row.block_scope}
                  disabled={!row?.include}
                  onChange={(e) => update(item.application_id, { block_scope: e.target.value, overridden: true })}
                  sx={{ display: "block", mt: 0.5 }}
                >
                  <MenuItem value="all">{BLOCK_SCOPE_LABEL.all}</MenuItem>
                  <MenuItem value="internships_only">{BLOCK_SCOPE_LABEL.internships_only}</MenuItem>
                </Select>
              )}
            </TableCell>
          </>
        )}
      </TableRow>
    );
  };

  return (
    <>
      <PageHeader
        icon={<EmojiEventsIcon />}
        title={`Shortlist for Offer — ${data.posting.company} · ${data.posting.title}`}
        subtitle={`${data.posting.offer_label} · Final stage: ${data.final_round.name}${data.posting.vacancies ? ` · ${data.posting.vacancies} vacancies` : ""}`}
        backHref={`/admin/postings/${id}`}
        backLabel="Back to Job Profile"
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
      {data.blocked_reason && (
        <Alert severity="warning" sx={{ mb: 2 }}>
          {data.blocked_reason}
        </Alert>
      )}
      {data.posting.accepts_applications && (
        <Alert severity="info" sx={{ mb: 2 }}>
          Applications are still open. Close applications before announcing results.
        </Alert>
      )}
      {data.selected.length === 0 && data.waitlisted.length === 0 && (
        <Alert severity="info" sx={{ mb: 2 }}>
          No one is selected in the final stage yet. Enter final-stage selections in the job profile&apos;s Progress Grid tab (they stay drafts), then return here.
        </Alert>
      )}
      {data.open_places > 0 && (
        <Alert severity="info" sx={{ mb: 2 }}>
          <AlertTitle>On Hold</AlertTitle>
          {data.open_places} place(s) are still open against the vacancies. Tick any on-hold candidates below to give them an offer.
        </Alert>
      )}

      <BlockingRules sx={{ mb: 2 }} />

      <Card>
        <CardContent>
          <Stack direction={{ xs: "column", sm: "row" }} justifyContent="space-between" alignItems={{ sm: "center" }} spacing={1} sx={{ mb: 1 }}>
            <Typography variant="subtitle1" fontWeight={700}>
              Shortlist for Offer
            </Typography>
            {[...data.selected, ...data.waitlisted].some((i) => i.offer) && (
              <Button size="small" variant="outlined" startIcon={<UploadFileIcon />} onClick={() => setUploadOpen(true)}>
                Upload CTCs
              </Button>
            )}
          </Stack>
          <TableContainer>
            <Table size="small">
              <TableHead>
                <TableRow>
                  <TableCell padding="checkbox">Offer</TableCell>
                  <TableCell>Student</TableCell>
                  <TableCell>Offer type</TableCell>
                  <TableCell>CTC Offered</TableCell>
                  <TableCell>Block (suggested per policy)</TableCell>
                </TableRow>
              </TableHead>
              <TableBody>
                {data.selected.map(renderRow)}
                {data.waitlisted.length > 0 && (
                  <TableRow>
                    <TableCell colSpan={5} sx={{ bgcolor: "grey.50" }}>
                      <Typography variant="body2" fontWeight={700}>
                        On Hold (tick to promote)
                      </Typography>
                    </TableCell>
                  </TableRow>
                )}
                {data.waitlisted.map(renderRow)}
              </TableBody>
            </Table>
          </TableContainer>
          <Box sx={{ mt: 2, display: "flex", justifyContent: "flex-end" }}>
            <Button
              variant="contained"
              size="large"
              disabled={busy || data.posting.accepts_applications || Boolean(data.blocked_reason) || (included.length === 0 && data.regret_estimate === 0)}
              onClick={() => {
                setDialogError(null);
                setConfirmOpen(true);
              }}
            >
              Publish results
            </Button>
          </Box>
        </CardContent>
      </Card>

      <StudentQuickView studentId={quickView} onClose={() => setQuickView(null)} />

      {offerDialog?.kind === "edit" && (
        <EditOfferDialog
          offer={offerDialog.item.offer}
          student={offerDialog.item.student}
          offerTypes={data.offer_types}
          onClose={() => setOfferDialog(null)}
          onSaved={(message) => {
            setOfferDialog(null);
            setSuccess(message);
            void load();
          }}
        />
      )}
      {offerDialog?.kind === "revoke" && (
        <RevokeOfferDialog
          offer={offerDialog.item.offer}
          student={offerDialog.item.student}
          onClose={() => setOfferDialog(null)}
          onDone={(message) => {
            setOfferDialog(null);
            setSuccess(message);
            void load();
          }}
        />
      )}
      {uploadOpen && (
        <UploadCtcsDialog
          postingId={id}
          onClose={() => setUploadOpen(false)}
          onDone={(message) => {
            setUploadOpen(false);
            setSuccess(message);
            void load();
          }}
        />
      )}

      <Dialog open={confirmOpen} onClose={() => !busy && setConfirmOpen(false)} maxWidth="sm" fullWidth>
        <DialogTitle>Publish final results?</DialogTitle>
        <DialogContent>
          {dialogError && (
            <Alert severity="error" sx={{ mb: 2 }}>
              {dialogError}
            </Alert>
          )}
          <Typography gutterBottom>
            <strong>{included.length}</strong> offer(s) · <strong>{blocks}</strong> student(s) blocked · about <strong>{regrets}</strong> regret mail(s)
          </Typography>
          <Typography variant="body2" color="text.secondary">
            Offers are emailed to the selected students, everyone else in the final stage is told they were not selected, blocked students&apos; other
            live applications in the placements their block reaches are flagged as placed elsewhere, and the job profile is marked completed. This cannot be undone from here
            (blocks can be lifted later).
          </Typography>
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setConfirmOpen(false)} disabled={busy}>
            Cancel
          </Button>
          <Button variant="contained" onClick={publish} disabled={busy}>
            {busy ? "Publishing..." : "Continue"}
          </Button>
        </DialogActions>
      </Dialog>
    </>
  );
}
