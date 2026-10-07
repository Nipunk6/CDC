"use client";

import { useCallback, useEffect, useState } from "react";
import Link from "next/link";
import {
  Alert,
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
import SwapHorizIcon from "@mui/icons-material/SwapHoriz";

import PageHeader from "@/components/shared/pageheader";
import { adminApi } from "@/lib/adminapi";
import { formatDateTime, statusColor, titleCase } from "@/lib/format";

const statuses = ["pending", "approved", "rejected"];

export default function AdminBranchChangesPage() {
  const [status, setStatus] = useState("pending");
  const [items, setItems] = useState([]);
  const [meta, setMeta] = useState({ current_page: 1, last_page: 1, total: 0 });
  const [page, setPage] = useState(1);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [success, setSuccess] = useState(null);
  const [rejecting, setRejecting] = useState(null);
  const [remark, setRemark] = useState("");
  const [busy, setBusy] = useState(false);

  const load = useCallback(async (currentStatus, targetPage) => {
    setLoading(true);
    try {
      const response = await adminApi(`/admin/branch-changes?status=${currentStatus}&page=${targetPage}`);
      setItems(response.branch_change_requests ?? []);
      setMeta(response.meta ?? { current_page: 1, last_page: 1, total: 0 });
      setError(null);
    } catch (e) {
      setError(e instanceof Error ? e.message : "Failed to load branch change requests.");
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    void load(status, page);
  }, [load, status, page]);

  const decide = async (item, decision, adminRemark = null) => {
    setBusy(true);
    try {
      const response = await adminApi(`/admin/branch-changes/${item.id}`, {
        method: "PATCH",
        body: JSON.stringify({ status: decision, admin_remark: adminRemark }),
      });
      setSuccess(response.message);
      setRejecting(null);
      await load(status, page);
    } catch (e) {
      setError(e instanceof Error ? e.message : "Failed to update the request.");
    } finally {
      setBusy(false);
    }
  };

  return (
    <>
      <PageHeader
        icon={<SwapHorizIcon />}
        title="Branch Change Requests"
        subtitle={`${meta.total} ${status} request(s)`}
        backHref="/admin/students"
        backLabel="Students"
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

      <Tabs
        value={status}
        onChange={(_e, value) => {
          setStatus(value);
          setPage(1);
        }}
        sx={{ mb: 2 }}
      >
        {statuses.map((value) => (
          <Tab key={value} value={value} label={titleCase(value)} />
        ))}
      </Tabs>

      {loading && <LinearProgress sx={{ mb: 2 }} />}
      {!loading && items.length === 0 && (
        <Card>
          <CardContent>
            <Typography color="text.secondary">No {status} requests.</Typography>
          </CardContent>
        </Card>
      )}

      <Stack spacing={2}>
        {items.map((item) => (
          <Card key={item.id}>
            <CardContent>
              <Stack direction={{ xs: "column", md: "row" }} justifyContent="space-between" spacing={2}>
                <Stack spacing={0.75} sx={{ minWidth: 0 }}>
                  <Stack direction="row" spacing={1} alignItems="center" flexWrap="wrap" useFlexGap>
                    <Typography fontWeight={700} component={Link} href={`/admin/students/${item.student_profile?.id}`} sx={{ color: "primary.main" }}>
                      {item.student_profile?.roll_no} · {item.student_profile?.full_name}
                    </Typography>
                    <Chip size="small" variant="outlined" color={statusColor(item.status)} label={titleCase(item.status)} />
                  </Stack>
                  <Typography variant="body2">
                    <strong>{item.current_branch}</strong> → <strong>{item.requested_branch}</strong>
                    {item.requested_programme ? ` (${item.requested_programme})` : ""}
                  </Typography>
                  <Typography variant="body2" color="text.secondary" sx={{ whiteSpace: "pre-wrap" }}>
                    {item.reason}
                  </Typography>
                  <Typography variant="caption" color="text.secondary">
                    Submitted {formatDateTime(item.created_at)} · CGPA {item.student_profile?.current_cgpa ?? "—"}
                    {item.decided_at ? ` · Decided ${formatDateTime(item.decided_at)} by ${item.decided_by?.name ?? "—"}` : ""}
                  </Typography>
                  {item.admin_remark && (
                    <Alert severity="info" sx={{ mt: 1 }}>
                      {item.admin_remark}
                    </Alert>
                  )}
                </Stack>
                {item.status === "pending" && (
                  <Stack direction="row" spacing={1} alignItems="flex-start">
                    <Button
                      variant="contained"
                      color="success"
                      disabled={busy}
                      onClick={() =>
                        window.confirm(`Accept changes and move ${item.student_profile?.roll_no} to ${item.requested_branch}?`) &&
                        decide(item, "approved")
                      }
                    >
                      Accept Changes
                    </Button>
                    <Button
                      variant="outlined"
                      color="error"
                      disabled={busy}
                      onClick={() => {
                        setRemark("");
                        setRejecting(item);
                      }}
                    >
                      Reject Changes
                    </Button>
                  </Stack>
                )}
              </Stack>
            </CardContent>
          </Card>
        ))}
      </Stack>

      {meta.last_page > 1 && (
        <Stack alignItems="center" sx={{ py: 2 }}>
          <Pagination count={meta.last_page} page={page} onChange={(_e, value) => setPage(value)} color="primary" />
        </Stack>
      )}

      <Dialog open={Boolean(rejecting)} onClose={() => !busy && setRejecting(null)} maxWidth="sm" fullWidth>
        <DialogTitle>Reject branch change</DialogTitle>
        <DialogContent>
          <TextField
            autoFocus
            fullWidth
            multiline
            minRows={3}
            label="Remark (sent to the student)"
            value={remark}
            onChange={(event) => setRemark(event.target.value)}
            sx={{ mt: 1 }}
          />
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setRejecting(null)} disabled={busy}>
            Cancel
          </Button>
          <Button color="error" variant="contained" disabled={busy || !remark.trim()} onClick={() => decide(rejecting, "rejected", remark.trim())}>
            Reject Changes
          </Button>
        </DialogActions>
      </Dialog>
    </>
  );
}
