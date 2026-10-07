"use client";

import { useCallback, useEffect, useState } from "react";
import Link from "next/link";
import {
  Alert,
  Box,
  Button,
  Card,
  CardContent,
  Chip,
  Dialog,
  DialogActions,
  DialogContent,
  DialogTitle,
  LinearProgress,
  Pagination,
  Stack,
  Tab,
  Tabs,
  TextField,
  Typography,
} from "@mui/material";

import { adminApi } from "@/lib/adminapi";
import { formatDateTime, statusColor, titleCase } from "@/lib/format";

/**
 * Company shortlist/waitlist/addendum proposals. Approving writes drafts only; the round must still be published.
 */
export default function ProposalsList({ postingId, onDecided }) {
  const [status, setStatus] = useState("pending");
  const [page, setPage] = useState(1);
  const [items, setItems] = useState(null);
  const [meta, setMeta] = useState({ last_page: 1, total: 0 });
  const [error, setError] = useState(null);
  const [success, setSuccess] = useState(null);
  const [rejecting, setRejecting] = useState(null);
  const [remark, setRemark] = useState("");
  const [busy, setBusy] = useState(false);

  const load = useCallback(async () => {
    try {
      const query = new URLSearchParams({ status, page: String(page) });
      if (postingId) query.set("job_posting_id", String(postingId));
      const response = await adminApi(`/admin/proposals?${query.toString()}`);
      setItems(response.proposals ?? []);
      setMeta(response.meta ?? { last_page: 1, total: 0 });
    } catch (e) {
      setError(e instanceof Error ? e.message : "Failed to load proposals.");
    }
  }, [status, page, postingId]);

  useEffect(() => {
    void load();
  }, [load]);

  const decide = async (proposal, decision, adminRemark = null) => {
    setBusy(true);
    setError(null);
    try {
      const response = await adminApi(`/admin/proposals/${proposal.id}`, {
        method: "PATCH",
        body: JSON.stringify({ status: decision, admin_remark: adminRemark }),
      });
      const skipped = (response.errors ?? []).map((e) => `${e.roll_no}: ${e.reason}`).join(" · ");
      setSuccess(skipped ? `${response.message} Skipped — ${skipped}` : response.message);
      setRejecting(null);
      await load();
      onDecided?.();
    } catch (e) {
      setError(e instanceof Error ? e.message : "Failed to update the proposal.");
    } finally {
      setBusy(false);
    }
  };

  return (
    <Stack spacing={2}>
      {error && <Alert severity="error">{error}</Alert>}
      {success && (
        <Alert severity="success" onClose={() => setSuccess(null)}>
          {success}
        </Alert>
      )}
      <Tabs
        value={status}
        onChange={(_e, value) => {
          setStatus(value);
          setPage(1);
        }}
      >
        {["pending", "approved", "rejected"].map((s) => (
          <Tab key={s} value={s} label={titleCase(s)} />
        ))}
      </Tabs>
      {!items && <LinearProgress />}
      {items && items.length === 0 && <Typography color="text.secondary">No {status} proposals.</Typography>}
      {(items ?? []).map((p) => (
        <Card key={p.id} variant="outlined">
          <CardContent>
            <Stack direction={{ xs: "column", md: "row" }} justifyContent="space-between" spacing={2}>
              <Box sx={{ minWidth: 0 }}>
                <Stack direction="row" spacing={1} alignItems="center" flexWrap="wrap" useFlexGap>
                  <Typography fontWeight={700}>
                    {p.kind === "waitlist" ? "On Hold" : titleCase(p.kind)} · {p.round?.name}
                  </Typography>
                  <Chip size="small" variant="outlined" color={statusColor(p.status)} label={titleCase(p.status)} />
                </Stack>
                {!postingId && (
                  <Typography variant="body2">
                    <Link href={`/admin/postings/${p.posting.id}`}>
                      {p.posting.company} — {p.posting.title}
                    </Link>
                  </Typography>
                )}
                <Typography variant="caption" color="text.secondary">
                  By {p.proposed_by?.name} ({p.proposed_by?.email}) · {formatDateTime(p.created_at)}
                  {p.decided_at ? ` · decided ${formatDateTime(p.decided_at)} by ${p.decided_by?.name ?? "—"}` : ""}
                </Typography>
                <Stack direction="row" spacing={0.5} flexWrap="wrap" useFlexGap sx={{ mt: 1 }}>
                  {(p.payload ?? []).map((e) => (
                    <Chip key={e.roll_no} size="small" label={e.roll_no} />
                  ))}
                </Stack>
                {p.admin_remark && (
                  <Typography variant="body2" sx={{ mt: 1 }}>
                    Remark: {p.admin_remark}
                  </Typography>
                )}
              </Box>
              {p.status === "pending" && (
                <Stack direction="row" spacing={1} alignItems="flex-start">
                  <Button variant="contained" color="success" disabled={busy} onClick={() => decide(p, "approved")}>
                    Approve as draft
                  </Button>
                  <Button
                    variant="outlined"
                    color="error"
                    disabled={busy}
                    onClick={() => {
                      setRemark("");
                      setRejecting(p);
                    }}
                  >
                    Reject
                  </Button>
                </Stack>
              )}
            </Stack>
          </CardContent>
        </Card>
      ))}
      {meta.last_page > 1 && (
        <Stack alignItems="center">
          <Pagination count={meta.last_page} page={page} onChange={(_e, v) => setPage(v)} />
        </Stack>
      )}

      <Dialog open={Boolean(rejecting)} onClose={() => !busy && setRejecting(null)} maxWidth="sm" fullWidth>
        <DialogTitle>Reject proposal</DialogTitle>
        <DialogContent>
          {error && <Alert severity="error">{error}</Alert>}
          <TextField autoFocus fullWidth multiline minRows={3} sx={{ mt: 1 }} label="Remark for the company" value={remark} onChange={(e) => setRemark(e.target.value)} />
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setRejecting(null)} disabled={busy}>
            Cancel
          </Button>
          <Button color="error" variant="contained" disabled={busy || !remark.trim()} onClick={() => decide(rejecting, "rejected", remark.trim())}>
            Reject
          </Button>
        </DialogActions>
      </Dialog>
    </Stack>
  );
}
