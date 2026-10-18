"use client";

import { useCallback, useEffect, useState } from "react";
import Link from "next/link";
import {
  Alert,
  Avatar,
  Box,
  Button,
  Card,
  CardActions,
  CardContent,
  Chip,
  Dialog,
  DialogActions,
  DialogContent,
  DialogTitle,
  Divider,
  FormControlLabel,
  Grid2,
  InputAdornment,
  LinearProgress,
  Paper,
  Radio,
  RadioGroup,
  Stack,
  TextField,
  Typography,
} from "@mui/material";
import SearchIcon from "@mui/icons-material/Search";
import ContentCopyIcon from "@mui/icons-material/ContentCopy";
import PlaceOutlinedIcon from "@mui/icons-material/PlaceOutlined";
import SchoolOutlinedIcon from "@mui/icons-material/SchoolOutlined";
import PaymentsOutlinedIcon from "@mui/icons-material/PaymentsOutlined";
import GroupsOutlinedIcon from "@mui/icons-material/GroupsOutlined";
import EventOutlinedIcon from "@mui/icons-material/EventOutlined";
import WorkOutlineIcon from "@mui/icons-material/WorkOutline";

import FloatDialog from "@/components/admin/floatdialog";
import { adminApi } from "@/lib/adminapi";
import { formatDate } from "@/lib/format";

const WORK_MODES = { onsite: "On-site", remote: "Remote", hybrid: "Hybrid" };

const money = (amount, currency) => {
  try {
    return new Intl.NumberFormat("en-IN", { style: "currency", currency: currency || "INR", maximumFractionDigits: 0 }).format(amount);
  } catch {
    return `${currency ?? ""} ${amount}`;
  }
};

const payText = (pay) => (pay ? `${pay.label} ${money(pay.amount, pay.currency)} / ${pay.interval}` : null);

function InfoLine({ icon, children }) {
  if (!children) return null;
  return (
    <Stack direction="row" spacing={1} alignItems="flex-start" sx={{ color: "text.secondary" }}>
      <Box sx={{ display: "flex", mt: "1px", "& svg": { fontSize: 18 } }}>{icon}</Box>
      <Typography variant="body2" color="text.primary" sx={{ minWidth: 0, overflowWrap: "anywhere" }}>
        {children}
      </Typography>
    </Stack>
  );
}

function FormCard({ form, onDuplicate, onPublished }) {
  const isInf = form.type === "inf";
  const company = form.company ?? {};
  const details = [form.location, WORK_MODES[form.work_mode], isInf && form.duration_weeks ? `${form.duration_weeks} weeks` : null].filter(Boolean).join(" · ");
  const eligibility = form.programmes_count
    ? `${form.programmes_count} programme${form.programmes_count === 1 ? "" : "s"} · ${form.branches_count} branch${form.branches_count === 1 ? "" : "es"}`
    : null;

  return (
    <Card variant="outlined" sx={{ height: "100%", display: "flex", flexDirection: "column" }}>
      <CardContent sx={{ flex: 1 }}>
        <Stack direction="row" spacing={1.5} alignItems="center" sx={{ mb: 1.5 }}>
          <Avatar
            src={company.logo_url ?? undefined}
            alt={company.name}
            variant="rounded"
            sx={{ width: 48, height: 48, bgcolor: "background.default", color: "primary.main", border: 1, borderColor: "divider", "& img": { objectFit: "contain", p: 0.5 } }}
          >
            {(company.name ?? "?").charAt(0)}
          </Avatar>
          <Box sx={{ minWidth: 0, flex: 1 }}>
            <Typography fontWeight={700} noWrap title={company.name}>
              {company.name ?? "Unknown company"}
            </Typography>
            <Typography variant="caption" color="text.secondary" noWrap component="div">
              {company.hr_email}
            </Typography>
          </Box>
          <Chip size="small" color={isInf ? "secondary" : "primary"} label={isInf ? "INF" : "JNF"} />
        </Stack>

        <Typography variant="subtitle1" fontWeight={600} sx={{ lineHeight: 1.3, overflowWrap: "anywhere" }}>
          {form.title}
        </Typography>
        <Typography variant="body2" color="text.secondary" sx={{ mb: 1.5 }}>
          {isInf ? "Internship" : "Full-time"}
          {form.batches?.length ? ` for batch ${form.batches.join(", ")}` : ""}
        </Typography>

        <Stack spacing={0.75}>
          <InfoLine icon={<PlaceOutlinedIcon />}>{details || null}</InfoLine>
          <InfoLine icon={<PaymentsOutlinedIcon />}>
            {[payText(form.pay), form.ppo_ctc ? `PPO ${money(form.ppo_ctc, form.pay?.currency)} / year` : null].filter(Boolean).join(" · ") || null}
          </InfoLine>
          <InfoLine icon={<SchoolOutlinedIcon />}>{eligibility}</InfoLine>
          <InfoLine icon={<GroupsOutlinedIcon />}>{form.expected_hires ? `${form.expected_hires} expected hires` : null}</InfoLine>
          <InfoLine icon={<EventOutlinedIcon />}>Accepted form · updated {formatDate(form.updated_at)}</InfoLine>
        </Stack>

        <Box sx={{ mt: 1.5 }}>
          {form.posting ? (
            <Chip
              size="small"
              color="success"
              variant="outlined"
              icon={<WorkOutlineIcon />}
              label={`Published in ${form.posting.cycle ?? "a cycle"}${form.posting.offer_label ? ` · ${form.posting.offer_label}` : ""}`}
              sx={{ maxWidth: "100%" }}
            />
          ) : (
            <Chip size="small" variant="outlined" label="Not published yet" />
          )}
        </Box>
      </CardContent>

      <Divider />
      <CardActions sx={{ px: 2, py: 1.25, justifyContent: "space-between", flexWrap: "wrap", gap: 1 }}>
        <Button component={Link} href={`/admin/${form.type}s/${form.id}`} size="small">
          View form
        </Button>
        <Stack direction="row" spacing={1}>
          <Button size="small" variant="outlined" startIcon={<ContentCopyIcon />} onClick={() => onDuplicate(form)}>
            Duplicate
          </Button>
          <FloatDialog formType={form.type} formId={form.id} formStatus="accepted" compact onPublished={onPublished} />
        </Stack>
      </CardActions>
    </Card>
  );
}

/**
 * "Add New Job" landing: recently accepted JNFs/INFs as company cards, with a search bar. Each card can be
 * published (Open for Applications) or duplicated as a new INF or JNF draft, which opens in the wizard.
 */
export default function AcceptedFormsGrid({ onDuplicated }) {
  const [search, setSearch] = useState("");
  const [state, setState] = useState({ key: null, forms: [], error: null });
  const [reloadTick, setReloadTick] = useState(0);
  const [duplicating, setDuplicating] = useState(null);
  const [duplicateAs, setDuplicateAs] = useState("inf");
  const [busy, setBusy] = useState(false);
  const [duplicateError, setDuplicateError] = useState(null);

  const term = search.trim();
  const requestKey = `${term}#${reloadTick}`;
  const loading = state.key !== requestKey;

  useEffect(() => {
    let cancelled = false;
    const timer = setTimeout(() => {
      adminApi(`/admin/form-builder/accepted-forms${term ? `?search=${encodeURIComponent(term)}` : ""}`)
        .then((response) => !cancelled && setState({ key: requestKey, forms: response.forms ?? [], error: null }))
        .catch((e) => !cancelled && setState({ key: requestKey, forms: [], error: e instanceof Error ? e.message : "Could not load the forms." }));
    }, 250);
    return () => {
      cancelled = true;
      clearTimeout(timer);
    };
  }, [term, requestKey]);

  const reload = useCallback(() => setReloadTick((tick) => tick + 1), []);

  const openDuplicate = (form) => {
    setDuplicating(form);
    setDuplicateAs(form.type);
    setDuplicateError(null);
  };

  const duplicate = async () => {
    setBusy(true);
    setDuplicateError(null);
    try {
      const response = await adminApi("/admin/form-builder/duplicate", {
        method: "POST",
        body: JSON.stringify({ form_type: duplicating.type, form_id: duplicating.id, as: duplicateAs }),
      });
      setDuplicating(null);
      onDuplicated(response);
    } catch (e) {
      setDuplicateError(e instanceof Error ? e.message : "Could not duplicate the form.");
    } finally {
      setBusy(false);
    }
  };

  return (
    <Box>
      <TextField
        fullWidth
        placeholder="Search by company, job title or HR email"
        value={search}
        onChange={(e) => setSearch(e.target.value)}
        sx={{ mb: 3, bgcolor: "background.paper" }}
        slotProps={{ input: { startAdornment: <InputAdornment position="start"><SearchIcon /></InputAdornment> } }}
      />

      <Typography variant="subtitle1" fontWeight={700} sx={{ mb: 1.5 }}>
        Recently accepted forms
      </Typography>

      {loading && <LinearProgress sx={{ mb: 2 }} />}
      {state.error && <Alert severity="error" sx={{ mb: 2 }}>{state.error}</Alert>}

      {!loading && !state.error && state.forms.length === 0 && (
        <Paper variant="outlined" sx={{ p: 4, textAlign: "center" }}>
          <Typography color="text.secondary">
            {term ? `No accepted forms match "${term}".` : "No accepted JNFs or INFs yet."}
          </Typography>
        </Paper>
      )}

      <Grid2 container spacing={2}>
        {state.forms.map((form) => (
          <Grid2 key={`${form.type}-${form.id}`} size={{ xs: 12, md: 6, xl: 4 }}>
            <FormCard form={form} onDuplicate={openDuplicate} onPublished={reload} />
          </Grid2>
        ))}
      </Grid2>

      <Dialog open={Boolean(duplicating)} onClose={() => !busy && setDuplicating(null)} maxWidth="xs" fullWidth>
        <DialogTitle>Duplicate form</DialogTitle>
        <DialogContent dividers>
          <Stack spacing={2}>
            {duplicateError && <Alert severity="error">{duplicateError}</Alert>}
            <Typography variant="body2" color="text.secondary">
              A copy of <strong>{duplicating?.title}</strong> ({duplicating?.company?.name}) opens as a new draft for you to edit.
              The batch, declarations and signatory are filled in again.
            </Typography>
            <Box>
              <Typography variant="subtitle2" fontWeight={700}>
                Duplicate as
              </Typography>
              <RadioGroup value={duplicateAs} onChange={(e) => setDuplicateAs(e.target.value)}>
                <FormControlLabel value="inf" control={<Radio />} label="INF (internship)" />
                <FormControlLabel value="jnf" control={<Radio />} label="JNF (full-time)" />
              </RadioGroup>
              {duplicating && duplicateAs !== duplicating.type && (
                <Typography variant="caption" color="text.secondary">
                  {duplicateAs === "jnf" ? "The stipend and duration" : "The salary details"} do not carry over; fill them in the form.
                </Typography>
              )}
            </Box>
          </Stack>
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setDuplicating(null)} disabled={busy}>
            Cancel
          </Button>
          <Button variant="contained" startIcon={<ContentCopyIcon />} onClick={duplicate} disabled={busy}>
            {busy ? "Duplicating..." : "Duplicate & edit"}
          </Button>
        </DialogActions>
      </Dialog>
    </Box>
  );
}
