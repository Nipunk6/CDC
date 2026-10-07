"use client";

import { useEffect, useState } from "react";
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
  LinearProgress,
  MenuItem,
  Select,
  Stack,
  TextField,
  Typography,
} from "@mui/material";

import { adminApi } from "@/lib/adminapi";
import { adminUpload } from "@/lib/adminupload";

const CURRENCIES = ["INR", "USD", "EUR", "GBP", "SGD", "AED", "JPY"];

/**
 * Edit an announced offer (Superset parity S2): CTC Offered + CTC Interval, currency and offer type. A type change
 * shows what happens to blocks before saving. A block the CDC lifted by hand stays lifted unless the admin ticks
 * "Bring back …", which is the only case that sends `reapply_blocking: true` (D91).
 */
export function EditOfferDialog({ offer, student, offerTypes, onClose, onSaved }) {
  // Both amounts set (e.g. Intern + Full-Time): edit them separately; otherwise one amount with an interval.
  const both = offer.ctc_annual != null && offer.stipend_monthly != null;
  const [type, setType] = useState(offer.offer_type);
  const [interval, setCtcInterval] = useState(offer.ctc_annual == null && offer.stipend_monthly != null ? "MONTH" : "YEAR");
  const [amount, setAmount] = useState(String((offer.ctc_annual == null && offer.stipend_monthly != null ? offer.stipend_monthly : offer.ctc_annual) ?? ""));
  const [stipend, setStipend] = useState(String(offer.stipend_monthly ?? ""));
  const [currency, setCurrency] = useState(offer.currency ?? "INR");
  const [preview, setPreview] = useState(null);
  const [applyBlocking, setApplyBlocking] = useState(true);
  const [restoreLifted, setRestoreLifted] = useState(false);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState(null);

  useEffect(() => {
    if (type === offer.offer_type) return undefined;
    let cancelled = false;
    adminApi(`/admin/offers/${offer.id}/preview?offer_type=${type}`)
      .then((p) => !cancelled && setPreview({ ...p, requested: type }))
      .catch((e) => !cancelled && setError(e instanceof Error ? e.message : "Could not preview the change."));
    return () => {
      cancelled = true;
    };
  }, [type, offer.id, offer.offer_type]);

  const typeChanged = type !== offer.offer_type;
  // A preview of an earlier type choice is never shown for the current one.
  const shownPreview = typeChanged && preview?.requested === type ? preview : null;
  const restorable = shownPreview?.restorable ?? [];
  const restoring = applyBlocking && restoreLifted && restorable.length > 0;
  const restoreText = restoring ? `Brings back the block lifted by hand in ${restorable.map((r) => r.cycle).join(", ")}.` : "";
  const effectText = !applyBlocking
    ? "Blocks stay as they are."
    : [shownPreview?.changes_blocks || !restoreText ? shownPreview?.summary : null, restoreText || null].filter(Boolean).join(" ");

  const save = async () => {
    const toInt = (v) => (v === "" ? null : Math.round(Number(v)));
    if ([amount, stipend].some((v) => v !== "" && (Number.isNaN(Number(v)) || Number(v) < 0))) {
      setError("Amounts must be positive numbers.");
      return;
    }
    const body = { offer_type: type, currency };
    // No flag = the blocking rules apply but hand-lifted blocks stay lifted; true only when the admin asked for them back.
    if (typeChanged && !applyBlocking) body.reapply_blocking = false;
    else if (typeChanged && restoring) body.reapply_blocking = true;
    if (both) {
      body.ctc_annual = toInt(amount);
      body.stipend_monthly = toInt(stipend);
    } else if (interval === "YEAR") {
      body.ctc_annual = toInt(amount);
      body.stipend_monthly = null;
    } else {
      body.stipend_monthly = toInt(amount);
      body.ctc_annual = null;
    }
    setBusy(true);
    setError(null);
    try {
      const response = await adminApi(`/admin/offers/${offer.id}`, { method: "PATCH", body: JSON.stringify(body) });
      onSaved(response.message);
    } catch (e) {
      setError(e instanceof Error ? e.message : "Saving failed.");
    } finally {
      setBusy(false);
    }
  };

  return (
    <Dialog open onClose={() => !busy && onClose()} maxWidth="sm" fullWidth>
      <DialogTitle>
        Edit offer — {student.roll_no} {student.full_name}
      </DialogTitle>
      <DialogContent>
        <Stack spacing={2} sx={{ pt: 1 }}>
          {error && <Alert severity="error">{error}</Alert>}
          <FormControl size="small" fullWidth>
            <InputLabel id="offer-type">Offer type</InputLabel>
            <Select
              labelId="offer-type"
              label="Offer type"
              value={type}
              onChange={(e) => {
                setType(e.target.value);
                setRestoreLifted(false);
              }}
            >
              {offerTypes.map((t) => (
                <MenuItem key={t.value} value={t.value}>
                  {t.label}
                </MenuItem>
              ))}
            </Select>
          </FormControl>
          <Stack direction={{ xs: "column", sm: "row" }} spacing={2}>
            <TextField
              size="small"
              type="number"
              label={both ? "CTC Offered (per year)" : "CTC Offered"}
              value={amount}
              onChange={(e) => setAmount(e.target.value)}
              fullWidth
            />
            {!both && (
              <FormControl size="small" sx={{ minWidth: 140 }}>
                <InputLabel id="ctc-interval">CTC Interval</InputLabel>
                <Select labelId="ctc-interval" label="CTC Interval" value={interval} onChange={(e) => setCtcInterval(e.target.value)}>
                  <MenuItem value="YEAR">YEAR</MenuItem>
                  <MenuItem value="MONTH">MONTH</MenuItem>
                </Select>
              </FormControl>
            )}
            <FormControl size="small" sx={{ minWidth: 110 }}>
              <InputLabel id="ctc-currency">Currency</InputLabel>
              <Select labelId="ctc-currency" label="Currency" value={currency} onChange={(e) => setCurrency(e.target.value)}>
                {CURRENCIES.map((c) => (
                  <MenuItem key={c} value={c}>
                    {c}
                  </MenuItem>
                ))}
              </Select>
            </FormControl>
          </Stack>
          {both && <TextField size="small" type="number" label="CTC Offered (per month, internship)" value={stipend} onChange={(e) => setStipend(e.target.value)} />}

          {typeChanged && !shownPreview && <LinearProgress />}
          {shownPreview && (
            <Alert severity={shownPreview.changes_blocks || shownPreview.had_lifted_blocks ? "warning" : "info"}>
              <Typography variant="body2" fontWeight={600} gutterBottom>
                Effect on placement blocks
              </Typography>
              <Typography variant="body2">{effectText}</Typography>
              {shownPreview.had_lifted_blocks && applyBlocking && (
                <Typography variant="body2" sx={{ mt: 1 }}>
                  This offer&apos;s block was lifted by hand earlier. It stays lifted{restorable.length > 0 ? " unless you tick the box below" : ""}.
                </Typography>
              )}
              <Stack sx={{ mt: 1 }}>
                <FormControlLabel
                  control={<Checkbox checked={applyBlocking} onChange={(e) => setApplyBlocking(e.target.checked)} />}
                  label="Apply the blocking rules for the new offer type"
                />
                {applyBlocking && restorable.length > 0 && (
                  <FormControlLabel
                    control={<Checkbox checked={restoreLifted} onChange={(e) => setRestoreLifted(e.target.checked)} />}
                    label={`Bring back the block lifted by hand (${restorable.map((r) => r.cycle).join(", ")})`}
                  />
                )}
              </Stack>
            </Alert>
          )}
          <Typography variant="caption" color="text.secondary">
            The student is told about a change of offer type or CTC by email and in the portal. The change is recorded in the audit log.
          </Typography>
        </Stack>
      </DialogContent>
      <DialogActions>
        <Button onClick={onClose} disabled={busy}>
          Cancel
        </Button>
        <Button variant="contained" onClick={save} disabled={busy || (typeChanged && !shownPreview)}>
          {busy ? "Saving..." : "Save changes"}
        </Button>
      </DialogActions>
    </Dialog>
  );
}

/**
 * Revoke an announced offer: admin only, confirmation and a reason (B2-8).
 */
export function RevokeOfferDialog({ offer, student, onClose, onDone }) {
  const [remark, setRemark] = useState("");
  const [confirm, setConfirm] = useState(false);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState(null);

  const revoke = async () => {
    setBusy(true);
    setError(null);
    try {
      const response = await adminApi(`/admin/offers/${offer.id}/revoke`, { method: "POST", body: JSON.stringify({ confirm, remark }) });
      onDone(response.message);
    } catch (e) {
      setError(e instanceof Error ? e.message : "Revoking failed.");
    } finally {
      setBusy(false);
    }
  };

  return (
    <Dialog open onClose={() => !busy && onClose()} maxWidth="sm" fullWidth>
      <DialogTitle>
        Revoke offer — {student.roll_no} {student.full_name}
      </DialogTitle>
      <DialogContent>
        <Stack spacing={2} sx={{ pt: 1 }}>
          {error && <Alert severity="error">{error}</Alert>}
          <Alert severity="warning">
            The offer is removed, the blocks it created are lifted and placed-elsewhere flags that no longer apply are cleared. The student is told by email
            with your reason. The audit log keeps the offer&apos;s details.
          </Alert>
          <TextField label="Reason (sent to the student)" value={remark} onChange={(e) => setRemark(e.target.value)} multiline minRows={2} />
          <FormControlLabel control={<Checkbox checked={confirm} onChange={(e) => setConfirm(e.target.checked)} />} label="I want to revoke this offer" />
        </Stack>
      </DialogContent>
      <DialogActions>
        <Button onClick={onClose} disabled={busy}>
          Cancel
        </Button>
        <Button variant="contained" color="error" onClick={revoke} disabled={busy || !confirm || !remark.trim()}>
          {busy ? "Revoking..." : "Revoke offer"}
        </Button>
      </DialogActions>
    </Dialog>
  );
}

/**
 * Upload CTCs for a job profile's offers (roll number, CTC, interval YEAR|MONTH, currency) with a dry run first.
 */
export function UploadCtcsDialog({ postingId, onClose, onDone }) {
  const [file, setFile] = useState(null);
  const [report, setReport] = useState(null);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState(null);

  const send = async (dryRun) => {
    const body = new FormData();
    body.append("file", file);
    body.append("dry_run", dryRun ? "1" : "0");
    setBusy(true);
    setError(null);
    try {
      const response = await adminUpload(`/admin/postings/${postingId}/offers/ctc-upload`, body);
      if (dryRun) setReport(response);
      else onDone(response.message);
    } catch (e) {
      setError(e instanceof Error ? e.message : "Upload failed.");
    } finally {
      setBusy(false);
    }
  };

  const money = (v) => (v == null ? "—" : Number(v).toLocaleString("en-IN"));

  return (
    <Dialog open onClose={() => !busy && onClose()} maxWidth="sm" fullWidth>
      <DialogTitle>Upload CTCs</DialogTitle>
      <DialogContent>
        <Stack spacing={2} sx={{ pt: 1 }}>
          {error && <Alert severity="error">{error}</Alert>}
          <Typography variant="body2" color="text.secondary">
            One row per offer: Roll Number, CTC, CTC Interval (YEAR or MONTH, default YEAR), Currency (optional). A header row is fine. Check the file first;
            nothing changes until you apply it.
          </Typography>
          <Button variant="outlined" component="label">
            {file ? file.name : "Choose .xlsx / .csv"}
            <input
              hidden
              type="file"
              accept=".xlsx,.xls,.csv"
              onChange={(e) => {
                setFile(e.target.files?.[0] ?? null);
                setReport(null);
              }}
            />
          </Button>
          {busy && <LinearProgress />}
          {report && (
            <Alert severity={report.errors.length ? "warning" : "success"}>
              <Typography variant="body2" fontWeight={600}>
                {report.message}
              </Typography>
              {report.changes.slice(0, 20).map((c) => (
                <div key={c.roll_no}>
                  {c.roll_no} {c.name}:{" "}
                  {Object.keys(c.after)
                    .map((k) => `${k === "ctc_annual" ? "per year" : k === "stipend_monthly" ? "per month" : k} ${money(c.before[k])} → ${k === "currency" ? c.after[k] : money(c.after[k])}`)
                    .join(", ")}
                </div>
              ))}
              {report.changes.length > 20 && <div>…and {report.changes.length - 20} more</div>}
              {report.errors.map((e) => (
                <div key={`${e.row}-${e.roll_no}`}>
                  Row {e.row} ({e.roll_no}): {e.reason}
                </div>
              ))}
            </Alert>
          )}
        </Stack>
      </DialogContent>
      <DialogActions>
        <Button onClick={onClose} disabled={busy}>
          Cancel
        </Button>
        <Button onClick={() => send(true)} disabled={busy || !file}>
          Check file
        </Button>
        <Button variant="contained" onClick={() => send(false)} disabled={busy || !report || report.changes.length === 0}>
          Apply {report?.changes.length ?? 0} change(s)
        </Button>
      </DialogActions>
    </Dialog>
  );
}
