"use client";

import { useEffect, useState } from "react";
import {
  Alert,
  Box,
  Button,
  Checkbox,
  Dialog,
  DialogActions,
  DialogContent,
  DialogTitle,
  FormControlLabel,
  Grid2 as Grid,
  LinearProgress,
  List,
  ListItem,
  ListItemText,
  Paper,
  Radio,
  RadioGroup,
  Stack,
  TextField,
  Typography,
  useMediaQuery,
} from "@mui/material";
import { useTheme } from "@mui/material/styles";
import TuneIcon from "@mui/icons-material/Tune";

import {
  EligibilityGrid,
  defaultProgrammes,
  eligibilityNumbersValid,
  isValidPercent,
  mergeCustomBranchesIntoProgrammes,
} from "@/components/forms/shared";
import { adminApi } from "@/lib/adminapi";
import { fromLocalInput, toLocalInput } from "@/lib/format";
import { shortProgramme } from "@/lib/usecatalogue";

const isValidCgpa = (value) => !value || (/^\d{1,2}(\.\d{1,2})?$/.test(String(value).trim()) && Number(value) <= 10);
// 23:59 IST three days from now: the default window when applications are reopened with a change.
const defaultReopenUntil = () => `${toLocalInput(Date.now() + 3 * 24 * 60 * 60 * 1000).slice(0, 10)}T23:59`;

const isValidBatch = (value) => !value || /^\d{4}$/.test(String(value).trim());

const BUILT_IN_GROUPS = defaultProgrammes.map((p) => ({ programme: p.programme, branches: p.branches.map((b) => b.branch) }));

// The editable criteria, prefilled from the drive's snapshot (the keys JobPosting::SNAPSHOT_KEYS holds).
const fromSnapshot = (snapshot) => {
  const s = snapshot ?? {};
  return {
    // Older or seeded snapshots may list only the selected programmes; offer every built-in programme and branch.
    eligibility:
      Array.isArray(s.eligibility) && s.eligibility.length > 0
        ? mergeCustomBranchesIntoProgrammes(s.eligibility, BUILT_IN_GROUPS)
        : defaultProgrammes,
    globalCgpa: s.globalCgpa ?? "7.0",
    globalBacklogs: Boolean(s.globalBacklogs),
    genderFilter: s.genderFilter || "all",
    graduatingBatch: s.graduatingBatch ?? "",
    minTenthPercent: s.minTenthPercent ?? "",
    minTwelfthPercent: s.minTwelfthPercent ?? "",
  };
};

// Only what the rules read, so the preview's query string stays short: selected branches, no UI flags.
const compact = (criteria) => ({
  ...criteria,
  eligibility: criteria.eligibility
    .map((p) => ({
      programme: p.programme,
      graduatingBatch: p.graduatingBatch ?? "",
      graduatingBatches: p.graduatingBatches ?? [],
      branches: p.branches
        .filter((b) => b.selected)
        .map((b) => ({
          branch: b.branch,
          selected: true,
          cgpa: b.cgpa ?? "",
          backlogsAllowed: Boolean(b.backlogsAllowed),
          maxOngoingBacklogs: b.maxOngoingBacklogs ?? "",
          maxTotalBacklogs: b.maxTotalBacklogs ?? "",
        })),
    }))
    .filter((p) => p.branches.length > 0),
});

const problemWith = (c) => {
  const selected = c.eligibility.reduce((n, p) => n + p.branches.filter((b) => b.selected).length, 0);
  if (selected === 0) return "Select at least one programme and branch.";
  if (!isValidBatch(c.graduatingBatch)) return "The graduating batch must be a four-digit year, or blank for any batch.";
  if (!isValidPercent(c.minTenthPercent) || !isValidPercent(c.minTwelfthPercent)) return "Min 10th % and Min 12th % must be blank or between 0 and 100 with at most 2 decimals.";
  if (!c.eligibility.every((p) => p.branches.every((b) => !b.selected || isValidCgpa(b.cgpa)))) return "Branch CGPA cut-offs must be blank or between 0 and 10 with at most 2 decimals.";
  if (!eligibilityNumbersValid(c.eligibility, c.minTenthPercent, c.minTwelfthPercent)) return "Backlog limits must be blank or a whole number from 0 to 999.";
  return null;
};

const Count = ({ label, value, color }) => (
  <Paper variant="outlined" sx={{ p: 1.5, height: "100%" }}>
    <Typography variant="h5" fontWeight={700} color={color ?? "text.primary"}>
      {value ?? 0}
    </Typography>
    <Typography variant="body2" color="text.secondary">
      {label}
    </Typography>
  </Paper>
);

/**
 * "Edit eligibility" on a floated drive (D103). Edit the criteria in the wizard's eligibility grid, review what the
 * change does (who becomes eligible, who stops, which applicants are affected), then save. Mount it only while open.
 */
export default function EditEligibilityDialog({ posting, onClose, onSaved }) {
  const theme = useTheme();
  const fullScreen = useMediaQuery(theme.breakpoints.down("sm"));
  const [criteria, setCriteria] = useState(() => fromSnapshot(posting.eligibility_snapshot));
  const [step, setStep] = useState("edit");
  const [preview, setPreview] = useState(null);
  const [notify, setNotify] = useState(true);
  const [reopen, setReopen] = useState(false);
  const [openUntil, setOpenUntil] = useState(defaultReopenUntil);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState(null);

  // The parent mounts this dialog only while it is open, so every opening starts from the drive's current snapshot.
  // Admin-added custom branches are offered too.
  useEffect(() => {
    let cancelled = false;
    adminApi("/programme-branches")
      .then((response) => {
        if (cancelled) return;
        // Branch states are not applied: a retired branch the drive already selects must stay visible, not vanish.
        setCriteria((prev) => ({ ...prev, eligibility: mergeCustomBranchesIntoProgrammes(prev.eligibility, response.programme_branches ?? []) }));
      })
      .catch(() => {});
    return () => {
      cancelled = true;
    };
  }, []);

  const set = (key, value) => setCriteria((prev) => ({ ...prev, [key]: value }));

  const review = async () => {
    const problem = problemWith(criteria);
    if (problem) {
      setError(problem);
      return;
    }
    setBusy(true);
    setError(null);
    try {
      const query = encodeURIComponent(JSON.stringify(compact(criteria)));
      const response = await adminApi(`/admin/postings/${posting.id}/eligibility/preview?criteria=${query}`);
      setPreview(response.preview);
      // Closed drive: reopen for everyone eligible by default (owner request); an open drive keeps its deadline.
      setReopen(Boolean(response.preview.can_reopen && !response.preview.applications_open));
      setStep("review");
    } catch (e) {
      setError(e instanceof Error ? e.message : "Could not check the change.");
    } finally {
      setBusy(false);
    }
  };

  const save = async () => {
    if (reopen && (!openUntil || Number.isNaN(new Date(openUntil).getTime()) || new Date(fromLocalInput(openUntil)) <= new Date())) {
      setError("Choose a date and time in the future for applications to stay open until.");
      return;
    }
    setBusy(true);
    setError(null);
    try {
      const body = { ...criteria, notify_newly_eligible: Boolean(mailPossible && notify) };
      if (reopen) body.applications_open_until = fromLocalInput(openUntil);
      const response = await adminApi(`/admin/postings/${posting.id}/eligibility`, { method: "PATCH", body: JSON.stringify(body) });
      onSaved?.(response.message);
      onClose();
    } catch (e) {
      setError(e instanceof Error ? e.message : "Could not save the change.");
    } finally {
      setBusy(false);
    }
  };

  const affected = preview?.affected_applicants ?? [];
  const mailPossible = Boolean(preview?.applications_open || reopen);
  const mailCount = reopen ? preview?.notified_on_reopen ?? 0 : preview?.will_be_notified ?? 0;

  return (
    <Dialog open onClose={() => !busy && onClose()} maxWidth="lg" fullWidth fullScreen={fullScreen}>
      <DialogTitle sx={{ display: "flex", alignItems: "center", gap: 1 }}>
        <TuneIcon color="primary" />
        {step === "edit" ? "Edit eligibility" : "Review the change"}
      </DialogTitle>
      {busy && <LinearProgress />}
      <DialogContent dividers>
        <Stack spacing={2.5}>
          {error && (
            <Alert severity="error" onClose={() => setError(null)}>
              {error}
            </Alert>
          )}

          {step === "edit" && (
            <>
              <Alert severity="info">
                Students who already applied keep their applications and can still view, edit or withdraw them. New
                applications follow the new criteria. The JNF/INF is updated to match.
              </Alert>
              <Paper variant="outlined" sx={{ p: 2 }}>
                <Stack spacing={2}>
                  <Stack direction={{ xs: "column", md: "row" }} spacing={{ xs: 1, md: 3 }} alignItems={{ md: "center" }}>
                    <Typography variant="subtitle2" fontWeight={600}>
                      Gender preference
                    </Typography>
                    <RadioGroup row value={criteria.genderFilter} onChange={(e) => set("genderFilter", e.target.value)}>
                      <FormControlLabel value="all" control={<Radio />} label="All genders" />
                      <FormControlLabel value="male" control={<Radio />} label="Male only" />
                      <FormControlLabel value="female" control={<Radio />} label="Female only" />
                    </RadioGroup>
                  </Stack>
                  <Stack direction={{ xs: "column", sm: "row" }} spacing={2}>
                    <TextField
                      fullWidth
                      label="Graduating batch"
                      value={criteria.graduatingBatch}
                      onChange={(e) => set("graduatingBatch", e.target.value.trim())}
                      error={!isValidBatch(criteria.graduatingBatch)}
                      helperText={isValidBatch(criteria.graduatingBatch) ? "Blank = any batch (PhD is always exempt)" : "Enter a four-digit year"}
                      inputProps={{ inputMode: "numeric", maxLength: 4 }}
                    />
                    <TextField
                      fullWidth
                      type="number"
                      label="Minimum 10th %"
                      value={criteria.minTenthPercent}
                      onChange={(e) => set("minTenthPercent", e.target.value)}
                      error={!isValidPercent(criteria.minTenthPercent)}
                      helperText={isValidPercent(criteria.minTenthPercent) ? "Leave blank for no cutoff" : "Enter a value between 0 and 100 (up to 2 decimals)"}
                      inputProps={{ min: 0, max: 100, step: 0.01 }}
                    />
                    <TextField
                      fullWidth
                      type="number"
                      label="Minimum 12th %"
                      value={criteria.minTwelfthPercent}
                      onChange={(e) => set("minTwelfthPercent", e.target.value)}
                      error={!isValidPercent(criteria.minTwelfthPercent)}
                      helperText={isValidPercent(criteria.minTwelfthPercent) ? "Leave blank for no cutoff" : "Enter a value between 0 and 100 (up to 2 decimals)"}
                      inputProps={{ min: 0, max: 100, step: 0.01 }}
                    />
                  </Stack>
                </Stack>
              </Paper>
              {/* The shared wizard grid; on phones its programme header wraps so long programme names get a full row. */}
              <Box sx={{ "& .MuiStack-root > .MuiPaper-root > .MuiBox-root": { flexWrap: "wrap", rowGap: 1 } }}>
                <EligibilityGrid
                  value={criteria.eligibility}
                  onChange={(value) => set("eligibility", value)}
                  globalCgpa={criteria.globalCgpa}
                  onGlobalCgpaChange={(value) => set("globalCgpa", value)}
                  globalBacklogs={criteria.globalBacklogs}
                  onGlobalBacklogsChange={(value) => set("globalBacklogs", value)}
                  graduatingBatch={criteria.graduatingBatch}
                  batchReadOnly={Boolean(criteria.graduatingBatch)}
                />
              </Box>
            </>
          )}

          {step === "review" && preview && (
            <>
              {!preview.changed && <Alert severity="warning">These are already the drive&apos;s criteria. Nothing would change.</Alert>}
              <Grid container spacing={1.5}>
                <Grid size={{ xs: 6, md: 3 }}>
                  <Count label="Eligible now" value={preview.currently_eligible} />
                </Grid>
                <Grid size={{ xs: 6, md: 3 }}>
                  <Count label="Eligible after the change" value={preview.eligible_after} color="primary.main" />
                </Grid>
                <Grid size={{ xs: 6, md: 3 }}>
                  <Count label="Newly eligible" value={preview.newly_eligible} color="success.main" />
                </Grid>
                <Grid size={{ xs: 6, md: 3 }}>
                  <Count label="No longer eligible" value={preview.no_longer_eligible} color="error.main" />
                </Grid>
              </Grid>

              <Box>
                <Typography variant="subtitle1" fontWeight={700}>
                  Applicants who would no longer be eligible ({affected.length})
                </Typography>
                <Typography variant="body2" color="text.secondary" sx={{ mb: 1 }}>
                  They keep their applications and stay in the selection process.
                </Typography>
                {affected.length === 0 ? (
                  <Typography variant="body2">None of the current applicants is affected.</Typography>
                ) : (
                  <Paper variant="outlined" sx={{ maxHeight: 280, overflowY: "auto" }}>
                    <List dense disablePadding>
                      {affected.map((a) => (
                        <ListItem key={a.application_id} divider>
                          <ListItemText
                            primary={`${a.roll_no} · ${a.full_name}`}
                            secondary={`${shortProgramme(a.programme)} · ${a.branch} · CGPA ${a.current_cgpa ?? "—"} · ${(a.reasons ?? []).join(" ")}`}
                            slotProps={{ secondary: { sx: { overflowWrap: "anywhere" } } }}
                          />
                        </ListItem>
                      ))}
                    </List>
                  </Paper>
                )}
              </Box>

              <Box>
                <FormControlLabel
                  control={<Checkbox checked={reopen} disabled={!preview.can_reopen} onChange={(e) => setReopen(e.target.checked)} />}
                  label={preview.applications_open ? "Also change the application deadline" : "Reopen applications for everyone eligible"}
                />
                <Typography variant="body2" color="text.secondary" sx={{ ml: 4, mb: reopen ? 1.5 : 0 }}>
                  {!preview.can_reopen
                    ? preview.reopen_refusal
                    : preview.applications_open
                      ? "Applications are open. Tick this to give students more time under the new criteria."
                      : "Applications are closed. Reopening lets every student eligible under the new criteria apply until the time below."}
                </Typography>
                {reopen && (
                  <TextField
                    type="datetime-local"
                    label="Applications open until (IST)"
                    value={openUntil}
                    onChange={(e) => setOpenUntil(e.target.value)}
                    slotProps={{ inputLabel: { shrink: true } }}
                    sx={{ ml: { sm: 4 }, width: { xs: "100%", sm: 280 } }}
                  />
                )}
              </Box>

              <Box>
                <FormControlLabel
                  control={<Checkbox checked={mailPossible && notify} disabled={!mailPossible} onChange={(e) => setNotify(e.target.checked)} />}
                  label={reopen ? "Email eligible students who were never told about this drive" : "Email newly eligible students"}
                />
                <Typography variant="body2" color="text.secondary" sx={{ ml: 4 }}>
                  {mailPossible
                    ? `Sends the usual "new opening" email to ${mailCount} student${mailCount === 1 ? "" : "s"}. Applicants and anyone already told about this drive are not emailed again.`
                    : "Applications are closed for this drive, so nobody is emailed unless you reopen them."}
                </Typography>
              </Box>
            </>
          )}
        </Stack>
      </DialogContent>
      <DialogActions sx={{ flexWrap: "wrap", gap: 1 }}>
        {step === "edit" ? (
          <>
            <Button onClick={onClose} disabled={busy}>
              Cancel
            </Button>
            <Button variant="contained" onClick={review} disabled={busy}>
              Review change
            </Button>
          </>
        ) : (
          <>
            <Button onClick={() => setStep("edit")} disabled={busy}>
              Back to edit
            </Button>
            <Button variant="contained" onClick={save} disabled={busy || !(preview?.changed || reopen)}>
              Save eligibility
            </Button>
          </>
        )}
      </DialogActions>
    </Dialog>
  );
}
