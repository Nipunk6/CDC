"use client";

import { useCallback, useEffect, useMemo, useState } from "react";
import Link from "next/link";
import {
  Alert,
  Box,
  Button,
  Checkbox,
  Chip,
  IconButton,
  LinearProgress,
  Stack,
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TableRow,
  Tooltip,
  Typography,
  alpha,
} from "@mui/material";
import PersonRemoveIcon from "@mui/icons-material/PersonRemove";
import WarningAmberIcon from "@mui/icons-material/WarningAmber";

import ConfirmDialog from "@/components/admin/engagement/confirmdialog";
import { adminApi } from "@/lib/adminapi";
import { useAccess } from "@/lib/access";

/**
 * Job profile → SPOC tab (P10.2): every SCPT member of this job profile's placement, with whether they are themselves
 * eligible for it (a warning only). The CDC picks one or more SPOCs; each is mailed with the current HR details.
 */
export default function SpocTab({ posting, onMessage }) {
  const canWrite = useAccess().can("scpt", "write");
  const [data, setData] = useState(null);
  const [error, setError] = useState(null);
  const [selected, setSelected] = useState([]);
  const [confirming, setConfirming] = useState(false);
  const [removing, setRemoving] = useState(null);
  const [busy, setBusy] = useState(false);

  const load = useCallback(async () => {
    try {
      setData(await adminApi(`/admin/postings/${posting.id}/spocs`));
      setError(null);
    } catch (e) {
      setError(e instanceof Error ? e.message : "Could not load the SCPT.");
    }
  }, [posting.id]);

  useEffect(() => {
    void load();
  }, [load]);

  const spocs = useMemo(() => (data?.candidates ?? []).filter((c) => c.spoc_id), [data]);
  const others = useMemo(() => (data?.candidates ?? []).filter((c) => !c.spoc_id), [data]);
  const chosen = others.filter((c) => selected.includes(c.scpt_member_id));
  const eligibleChosen = chosen.filter((c) => c.eligible);
  const appliedChosen = chosen.filter((c) => c.applied);

  const toggle = (id) => setSelected((list) => (list.includes(id) ? list.filter((x) => x !== id) : [...list, id]));

  const assign = async () => {
    setBusy(true);
    try {
      const response = await adminApi(`/admin/postings/${posting.id}/spocs`, { method: "POST", body: JSON.stringify({ scpt_member_ids: selected }) });
      onMessage(response.message);
      setSelected([]);
      setConfirming(false);
      await load();
    } catch (e) {
      setError(e instanceof Error ? e.message : "Could not assign the SPOCs.");
    } finally {
      setBusy(false);
    }
  };

  const remove = async () => {
    setBusy(true);
    try {
      const response = await adminApi(`/admin/postings/${posting.id}/spocs/${removing.spoc_id}`, { method: "DELETE" });
      onMessage(response.message);
      setRemoving(null);
      await load();
    } catch (e) {
      setError(e instanceof Error ? e.message : "Could not remove the SPOC.");
    } finally {
      setBusy(false);
    }
  };

  if (!data && !error) return <LinearProgress />;

  return (
    <Stack spacing={3}>
      {error && (
        <Alert severity="error" onClose={() => setError(null)}>
          {error}
        </Alert>
      )}

      <Box>
        <Typography variant="subtitle1" fontWeight={700} gutterBottom>
          SPOCs for this job profile
        </Typography>
        {spocs.length === 0 ? (
          <Typography variant="body2" color="text.secondary">
            No SPOC assigned yet. Students see the SPOC&apos;s name, phone and email on the job profile.
          </Typography>
        ) : (
          <MemberTable rows={spocs} renderAction={canWrite ? (row) => (
            <Tooltip title="Remove as SPOC">
              <IconButton size="small" color="error" onClick={() => setRemoving(row)} aria-label={`Remove ${row.student?.roll_no}`}>
                <PersonRemoveIcon fontSize="small" />
              </IconButton>
            </Tooltip>
          ) : null} />
        )}
      </Box>

      <Box>
        <Stack direction={{ xs: "column", sm: "row" }} justifyContent="space-between" alignItems={{ sm: "center" }} spacing={1} sx={{ mb: 1 }}>
          <div>
            <Typography variant="subtitle1" fontWeight={700}>
              SCPT of {data?.placement_cycle?.name ?? "this placement"}
            </Typography>
            <Typography variant="body2" color="text.secondary">
              Members who are themselves eligible are marked, and those who have applied are shown in red. You can still choose them.
            </Typography>
          </div>
          {canWrite && (
            <Button variant="contained" disabled={selected.length === 0 || busy || posting.status === "cancelled"} onClick={() => setConfirming(true)}>
              Assign {selected.length > 0 ? selected.length : ""} as SPOC
            </Button>
          )}
        </Stack>
        {others.length === 0 ? (
          <Alert severity="info">
            {data?.candidates?.length ? "Every SCPT member of this placement is already a SPOC here." : "This placement has no SCPT members yet."}{" "}
            <Link href="/admin/scpt">Manage the SCPT</Link>
          </Alert>
        ) : (
          <MemberTable rows={others} selectable={canWrite} selected={selected} onToggle={toggle} />
        )}
      </Box>

      <ConfirmDialog
        open={confirming}
        title={`Assign ${selected.length} SPOC(s)?`}
        message={[
          "Each SPOC gets a mail with the company, the role and the current HR details. Students of this job profile will see their name, phone and email.",
          eligibleChosen.length > 0
            ? `Warning: ${eligibleChosen.map((c) => c.student?.full_name).join(", ")} ${eligibleChosen.length === 1 ? "is" : "are"} also eligible for this job profile.`
            : "",
          appliedChosen.length > 0
            ? `${appliedChosen.map((c) => c.student?.full_name).join(", ")} ${appliedChosen.length === 1 ? "has" : "have"} applied to this job profile.`
            : "",
        ].filter(Boolean).join(" ")}
        confirmLabel="Assign and mail"
        busy={busy}
        onConfirm={assign}
        onCancel={() => setConfirming(false)}
      />
      <ConfirmDialog
        open={Boolean(removing)}
        title="Remove SPOC?"
        message={`${removing?.student?.full_name ?? ""} will no longer be SPOC for this job profile and will be told by mail.`}
        confirmLabel="Remove"
        color="error"
        busy={busy}
        onConfirm={remove}
        onCancel={() => setRemoving(null)}
      />
    </Stack>
  );
}

function MemberTable({ rows, selectable = false, selected = [], onToggle, renderAction }) {
  return (
    <TableContainer sx={{ border: 1, borderColor: "divider", borderRadius: 1 }}>
      <Table size="small">
        <TableHead>
          <TableRow>
            {selectable && <TableCell padding="checkbox" />}
            <TableCell>Roll Number</TableCell>
            <TableCell>Name</TableCell>
            <TableCell sx={{ display: { xs: "none", md: "table-cell" } }}>Branch</TableCell>
            <TableCell sx={{ display: { xs: "none", sm: "table-cell" } }}>Contact</TableCell>
            <TableCell>For this job profile</TableCell>
            {renderAction && <TableCell align="right" />}
          </TableRow>
        </TableHead>
        <TableBody>
          {rows.map((row) => (
            <TableRow
              key={row.scpt_member_id}
              hover
              selected={selected.includes(row.scpt_member_id)}
              // An SCPT member who applied to this job profile is shown in red (owner request 2026-10-19).
              sx={row.applied ? { bgcolor: (theme) => alpha(theme.palette.error.main, 0.06), "& > td:first-of-type": { boxShadow: (theme) => `inset 3px 0 0 ${theme.palette.error.main}` } } : undefined}
            >
              {selectable && (
                <TableCell padding="checkbox">
                  <Checkbox
                    checked={selected.includes(row.scpt_member_id)}
                    onChange={() => onToggle(row.scpt_member_id)}
                    inputProps={{ "aria-label": `Choose ${row.student?.roll_no}` }}
                  />
                </TableCell>
              )}
              <TableCell>
                <Link href={`/admin/students/${row.student?.id}`} style={row.applied ? { color: "inherit" } : undefined}>
                  <Typography component="span" variant="body2" color={row.applied ? "error.main" : "primary.main"} fontWeight={row.applied ? 600 : undefined}>
                    {row.student?.roll_no}
                  </Typography>
                </Link>
              </TableCell>
              <TableCell sx={row.applied ? { color: "error.main", fontWeight: 600 } : undefined}>
                {row.student?.full_name}
                {row.designation && (
                  <Typography variant="caption" color="text.secondary" display="block">
                    {row.designation}
                  </Typography>
                )}
              </TableCell>
              <TableCell sx={{ display: { xs: "none", md: "table-cell" } }}>
                {row.student?.branch}
                {row.student?.graduating_batch ? ` · ${row.student.graduating_batch}` : ""}
              </TableCell>
              <TableCell sx={{ display: { xs: "none", sm: "table-cell" } }}>
                <Typography variant="body2">{row.student?.phone || "—"}</Typography>
                <Typography variant="caption" color="text.secondary">
                  {row.student?.institute_email}
                </Typography>
              </TableCell>
              <TableCell>
                <Stack direction="row" spacing={0.5} flexWrap="wrap" useFlexGap>
                  {row.eligible ? (
                    <Tooltip title="This SCPT member can apply to this job profile.">
                      <Chip size="small" color="warning" icon={<WarningAmberIcon />} label="Eligible" />
                    </Tooltip>
                  ) : (
                    <Tooltip title={row.reasons?.join(" ") || "Not eligible."}>
                      <Chip size="small" variant="outlined" label="Not eligible" />
                    </Tooltip>
                  )}
                  {row.applied && <Chip size="small" color="error" label="Applied" />}
                </Stack>
              </TableCell>
              {renderAction && <TableCell align="right">{renderAction(row)}</TableCell>}
            </TableRow>
          ))}
        </TableBody>
      </Table>
    </TableContainer>
  );
}
