"use client";

import { useEffect, useRef, useState } from "react";
import Link from "next/link";
import {
  Alert,
  Autocomplete,
  Box,
  Button,
  Card,
  Checkbox,
  Chip,
  FormControl,
  IconButton,
  InputLabel,
  LinearProgress,
  MenuItem,
  Pagination,
  Paper,
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
import CloseIcon from "@mui/icons-material/Close";
import MailOutlineIcon from "@mui/icons-material/MailOutline";
import SearchIcon from "@mui/icons-material/Search";
import SendIcon from "@mui/icons-material/Send";
import VisibilityIcon from "@mui/icons-material/Visibility";

import PageHeader from "@/components/shared/pageheader";
import { adminApi } from "@/lib/adminapi";
import { shortProgramme } from "@/lib/usecatalogue";
import { dash, formatDate, titleCase } from "@/lib/format";

const emptyMeta = { current_page: 1, last_page: 1, per_page: 50, total: 0 };
const emptyCounts = { sent: 0, accepted: 0, revoked: 0, total: 0 };
const thisYear = new Date().getFullYear();
const BATCHES = Array.from({ length: 9 }, (_v, i) => String(thisYear - 3 + i));
const STATUSES = [
  ["sent", "Sent"],
  ["accepted", "Accepted"],
  ["revoked", "Revoked"],
];

function StatusChip({ status }) {
  if (status === "accepted") return <Chip size="small" color="primary" label="ACCEPTED" />;
  if (status === "revoked") return <Chip size="small" color="error" variant="outlined" label="REVOKED" />;
  return <Chip size="small" color="primary" variant="outlined" label="SENT" />;
}

function query(filters, page) {
  const params = new URLSearchParams();
  if (filters.status) params.set("invitation_status", filters.status);
  filters.batches.forEach((batch) => params.append("batches[]", batch));
  if (filters.search.trim()) params.set("search", filters.search.trim());
  if (page > 1) params.set("page", String(page));
  return params.toString();
}

/**
 * Send Invitations (Superset parity S5): invitation status per student (Sent / Accepted / Revoked), bulk
 * "Re - Send Invites" and "Revoke Invites". Accepted students (password set) cannot be selected.
 */
export default function StudentInvitationsPage() {
  const [draft, setDraft] = useState({ status: "", batches: [], search: "" });
  const [filters, setFilters] = useState({ status: "", batches: [], search: "" });
  const [page, setPage] = useState(1);
  const [reloadTick, setReloadTick] = useState(0);
  const [result, setResult] = useState({ key: null, students: [], meta: emptyMeta, counts: emptyCounts });
  const [selected, setSelected] = useState([]);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState(null);
  const [success, setSuccess] = useState(null);

  const requestKey = `${query(filters, page)}#${reloadTick}`;
  const loading = result.key !== requestKey;
  const { students, meta, counts } = result;
  // "Resend to all pending" covers Sent invitations only; revoked ones are re-sent only when selected (D126).
  const pending = counts.sent;
  const statusById = useRef(new Map());

  useEffect(() => {
    let cancelled = false;
    const qs = query(filters, page);
    adminApi(`/admin/students/invitations${qs ? `?${qs}` : ""}`)
      .then((response) => {
        if (cancelled) return;
        (response.students ?? []).forEach((s) => statusById.current.set(s.id, s.invitation_status));
        setResult({
          key: requestKey,
          students: response.students ?? [],
          meta: response.meta ?? emptyMeta,
          counts: response.counts ?? emptyCounts,
        });
      })
      .catch((e) => {
        if (cancelled) return;
        setResult((prev) => ({ ...prev, key: requestKey }));
        setError(e instanceof Error ? e.message : "Failed to load invitations.");
      });
    return () => {
      cancelled = true;
    };
  }, [filters, page, requestKey]);

  const apply = (next) => {
    setFilters(next);
    setDraft(next);
    setPage(1);
    setSelected([]);
  };
  const reload = () => {
    setSelected([]);
    setReloadTick((tick) => tick + 1);
  };

  const selectable = students.filter((s) => s.invitation_status !== "accepted");
  const allOnPage = selectable.length > 0 && selectable.every((s) => selected.includes(s.id));
  const toggle = (id) => setSelected((prev) => (prev.includes(id) ? prev.filter((x) => x !== id) : [...prev, id]));
  const toggleAll = () =>
    setSelected((prev) =>
      allOnPage ? prev.filter((id) => !selectable.some((s) => s.id === id)) : Array.from(new Set([...prev, ...selectable.map((s) => s.id)]))
    );

  const run = async (path, body, confirmText) => {
    if (!window.confirm(confirmText)) return;
    setBusy(true);
    setError(null);
    try {
      const response = await adminApi(path, { method: "POST", body: JSON.stringify(body) });
      setSuccess(response.message);
      reload();
    } catch (e) {
      setError(e instanceof Error ? e.message : "The action failed.");
    } finally {
      setBusy(false);
    }
  };

  const resendSelected = () => {
    const revoked = selected.filter((id) => statusById.current.get(id) === "revoked").length;
    run(
      "/admin/students/invitations/resend",
      { student_ids: selected },
      `Send the invitation again to ${selected.length} student(s)? Each gets a new set-password link by email.` +
        (revoked > 0 ? ` ${revoked} of them were revoked; resending gives them a new link and un-revokes them.` : "")
    );
  };
  const revokeSelected = () =>
    run(
      "/admin/students/invitations/revoke",
      { student_ids: selected },
      `Revoke the invitation of ${selected.length} student(s)? Their set-password links stop working until you resend.`
    );
  const resendAllPending = () =>
    run(
      "/admin/students/invitations/resend",
      { all_pending: true, batches: filters.batches, search: filters.search.trim() || undefined },
      `Send the invitation again to all ${pending} pending student(s)${filters.batches.length || filters.search.trim() ? " matching the Batches and search filters" : ""}? Students who have already accepted are skipped, and revoked invitations are not included (select those students to re-invite them).`
    );

  return (
    <>
      <PageHeader
        icon={<MailOutlineIcon />}
        title="Send Invitations"
        subtitle="Set-password invitations of every student account: Sent, Accepted (password set) or Revoked"
        backHref="/admin/students"
        backLabel="All Students"
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

      <Paper sx={{ p: 2, mb: 2 }}>
        <Stack direction={{ xs: "column", md: "row" }} spacing={1.5} alignItems={{ md: "center" }}>
          <FormControl size="small" sx={{ minWidth: { md: 180 } }}>
            <InputLabel id="inv-status">Status</InputLabel>
            <Select
              labelId="inv-status"
              label="Status"
              value={draft.status}
              onChange={(event) => apply({ ...draft, status: event.target.value })}
            >
              <MenuItem value="">Select a status</MenuItem>
              {STATUSES.map(([value, label]) => (
                <MenuItem key={value} value={value}>
                  {label}
                </MenuItem>
              ))}
            </Select>
          </FormControl>
          <Autocomplete
            multiple
            size="small"
            limitTags={2}
            options={BATCHES}
            value={draft.batches}
            getOptionLabel={(b) => `${b} Passout Batch`}
            onChange={(_e, value) => apply({ ...draft, batches: value })}
            sx={{ minWidth: { md: 240 } }}
            renderInput={(params) => <TextField {...params} label="Batches" placeholder={draft.batches.length ? "" : "Select a batch"} />}
          />
          <TextField
            size="small"
            fullWidth
            label="Search"
            placeholder="Search by name, email or roll no ..."
            value={draft.search}
            onChange={(event) => setDraft((prev) => ({ ...prev, search: event.target.value }))}
            onKeyDown={(event) => event.key === "Enter" && apply(draft)}
            slotProps={{ input: { startAdornment: <SearchIcon fontSize="small" sx={{ mr: 1, color: "text.secondary" }} /> } }}
          />
          <Button variant="contained" onClick={() => apply(draft)} sx={{ flexShrink: 0 }}>
            Search
          </Button>
        </Stack>
      </Paper>

      <Card>
        <Stack
          direction={{ xs: "column", sm: "row" }}
          spacing={1}
          alignItems={{ sm: "center" }}
          justifyContent="space-between"
          sx={{ px: 2, py: 1.5 }}
        >
          {selected.length > 0 ? (
            <Stack direction="row" spacing={1} flexWrap="wrap" useFlexGap alignItems="center">
              <Typography variant="body2" fontWeight={700}>
                {selected.length} selected
              </Typography>
              <Button size="small" variant="contained" startIcon={<SendIcon />} disabled={busy} onClick={resendSelected}>
                Re - Send Invites
              </Button>
              <Button size="small" variant="outlined" color="error" startIcon={<CloseIcon />} disabled={busy} onClick={revokeSelected}>
                Revoke Invites
              </Button>
              <Button size="small" color="inherit" disabled={busy} onClick={() => setSelected([])}>
                Clear selection
              </Button>
            </Stack>
          ) : (
            <Stack direction="row" spacing={1} flexWrap="wrap" useFlexGap>
              {[
                ["revoked", "Revoked"],
                ["sent", "Sent"],
                ["accepted", "Accepted"],
              ].map(([value, label]) => (
                <Chip
                  key={value}
                  label={`${label} (${counts[value]})`}
                  variant={filters.status === value ? "filled" : "outlined"}
                  color="primary"
                  onClick={() => apply({ ...filters, status: filters.status === value ? "" : value })}
                />
              ))}
            </Stack>
          )}
          <Tooltip title="Every student with a Sent invitation in the Batches and search filters. Accepted students are skipped and revoked invitations are not included; select a revoked student to re-invite them.">
            <span>
              <Button size="small" variant="outlined" startIcon={<SendIcon />} disabled={busy || pending === 0} onClick={resendAllPending}>
                Resend to all pending ({pending})
              </Button>
            </span>
          </Tooltip>
        </Stack>
        {(loading || busy) && <LinearProgress />}
        <TableContainer>
          <Table size="small" sx={{ minWidth: 1100 }}>
            <TableHead>
              <TableRow>
                <TableCell padding="checkbox">
                  <Checkbox
                    size="small"
                    checked={allOnPage}
                    indeterminate={!allOnPage && selectable.some((s) => selected.includes(s.id))}
                    disabled={selectable.length === 0}
                    onChange={toggleAll}
                    slotProps={{ input: { "aria-label": "Select all pending on this page" } }}
                  />
                </TableCell>
                <TableCell>Name</TableCell>
                <TableCell>Mobile</TableCell>
                <TableCell>Batch</TableCell>
                <TableCell>Email</TableCell>
                <TableCell>Personal Email Address</TableCell>
                <TableCell>Roll Number</TableCell>
                <TableCell>Gender</TableCell>
                <TableCell>Status</TableCell>
                <TableCell>DOB</TableCell>
                <TableCell>Programme / Branch</TableCell>
                <TableCell align="right">View</TableCell>
              </TableRow>
            </TableHead>
            <TableBody>
              {!loading && students.length === 0 && (
                <TableRow>
                  <TableCell colSpan={12}>
                    <Typography color="text.secondary" sx={{ py: 3, textAlign: "center" }}>
                      {filters.search.trim() ? `We could not find any student matching "${filters.search.trim()}"` : "No invitations match these filters."}
                    </Typography>
                  </TableCell>
                </TableRow>
              )}
              {students.map((student) => {
                const accepted = student.invitation_status === "accepted";
                return (
                  <TableRow key={student.id} hover selected={selected.includes(student.id)}>
                    <TableCell padding="checkbox">
                      <Tooltip title={accepted ? "Already accepted" : ""}>
                        <span>
                          <Checkbox
                            size="small"
                            disabled={accepted}
                            checked={selected.includes(student.id)}
                            onChange={() => toggle(student.id)}
                            slotProps={{ input: { "aria-label": `Select ${student.roll_no}` } }}
                          />
                        </span>
                      </Tooltip>
                    </TableCell>
                    <TableCell sx={{ fontWeight: 600, whiteSpace: "nowrap" }}>{student.full_name}</TableCell>
                    <TableCell sx={{ whiteSpace: "nowrap" }}>{dash(student.phone)}</TableCell>
                    <TableCell>{student.graduating_batch}</TableCell>
                    <TableCell sx={{ whiteSpace: "nowrap" }}>{student.institute_email}</TableCell>
                    <TableCell sx={{ whiteSpace: "nowrap" }}>{dash(student.personal_email)}</TableCell>
                    <TableCell sx={{ whiteSpace: "nowrap" }}>{student.roll_no}</TableCell>
                    <TableCell>{student.gender ? titleCase(student.gender) : "—"}</TableCell>
                    <TableCell>
                      <Tooltip
                        title={
                          accepted
                            ? `Password set ${formatDate(student.activated_at)}`
                            : `Last sent ${formatDate(student.last_invited_at)} · sent ${student.invite_count} time(s)`
                        }
                      >
                        <span>
                          <StatusChip status={student.invitation_status} />
                        </span>
                      </Tooltip>
                    </TableCell>
                    <TableCell sx={{ whiteSpace: "nowrap" }}>{student.date_of_birth ? formatDate(student.date_of_birth) : "—"}</TableCell>
                    <TableCell>
                      {shortProgramme(student.programme)} / {student.branch}
                    </TableCell>
                    <TableCell align="right">
                      <Tooltip title="Open student page">
                        <IconButton component={Link} href={`/admin/students/${student.id}`} size="small" color="primary">
                          <VisibilityIcon fontSize="small" />
                        </IconButton>
                      </Tooltip>
                    </TableCell>
                  </TableRow>
                );
              })}
            </TableBody>
          </Table>
        </TableContainer>
        <Box
          sx={{
            px: 2,
            py: 1.5,
            display: "flex",
            flexDirection: { xs: "column", sm: "row" },
            gap: 1,
            alignItems: "center",
            justifyContent: "space-between",
          }}
        >
          <Typography variant="body2" color="text.secondary">
            Showing Page {meta.current_page} of {meta.last_page} ({meta.total} records)
          </Typography>
          {meta.last_page > 1 && (
            <Pagination
              count={meta.last_page}
              page={page}
              onChange={(_e, value) => {
                setPage(value);
                setSelected([]);
              }}
              color="primary"
              size="small"
              showFirstButton
              showLastButton
            />
          )}
        </Box>
      </Card>
    </>
  );
}
