"use client";

import { useEffect, useState } from "react";
import { Alert, Chip, LinearProgress, Pagination, Stack, Table, TableBody, TableCell, TableContainer, TableHead, TableRow, TextField, Typography } from "@mui/material";

import { adminApi } from "@/lib/adminapi";
import { formatDateTime, titleCase } from "@/lib/format";

const KIND_LABELS = {
  opening: "Opened for applications",
  application_receipt: "Application receipt",
  stage_result: "Stage result",
  offer: "Offer",
  offer_update: "Offer changed",
  company_notice: "Company notice",
  applicant_list: "Applicant list",
  notice: "Notice",
  stage_email: "Stage email",
  reconcile_regret: "Reconcile regret",
  shortlist_proposal: "Shortlist proposal",
};

const STATUS_COLORS = { sent: "success", queued: "warning", failed: "error" };

/**
 * Communication Log (Superset parity S6.8): every mail sent for this job profile, one row per message.
 */
export default function CommunicationTab({ posting }) {
  const [data, setData] = useState(null);
  const [search, setSearch] = useState("");
  const [query, setQuery] = useState({ search: "", page: 1 });
  const [error, setError] = useState(null);

  useEffect(() => {
    const timer = setTimeout(() => setQuery((q) => (q.search === search ? q : { search, page: 1 })), 300);
    return () => clearTimeout(timer);
  }, [search]);

  useEffect(() => {
    let cancelled = false;
    adminApi(`/admin/postings/${posting.id}/communications?${new URLSearchParams({ search: query.search, page: String(query.page) })}`)
      .then((r) => !cancelled && setData(r))
      .catch((e) => !cancelled && setError(e.message));
    return () => {
      cancelled = true;
    };
  }, [posting.id, query]);

  return (
    <Stack spacing={2}>
      <TextField size="small" label="Search subject" value={search} onChange={(e) => setSearch(e.target.value)} sx={{ maxWidth: { sm: 360 } }} />
      {error && <Alert severity="error">{error}</Alert>}
      {!data && !error && <LinearProgress />}
      {data && (
        <>
          <TableContainer sx={{ border: 1, borderColor: "divider", borderRadius: 1 }}>
            <Table size="small">
              <TableHead>
                <TableRow>
                  <TableCell>Date</TableCell>
                  <TableCell>Type</TableCell>
                  <TableCell>Subject</TableCell>
                  <TableCell align="right">Recipients</TableCell>
                  <TableCell>Status</TableCell>
                </TableRow>
              </TableHead>
              <TableBody>
                {data.messages.length === 0 && (
                  <TableRow>
                    <TableCell colSpan={5}>
                      <Typography variant="body2" color="text.secondary">
                        No mails recorded for this job profile yet.
                      </Typography>
                    </TableCell>
                  </TableRow>
                )}
                {data.messages.map((m) => (
                  <TableRow key={`${m.kind}-${m.subject}-${m.at}`}>
                    <TableCell sx={{ whiteSpace: "nowrap" }}>{formatDateTime(m.at)}</TableCell>
                    <TableCell>{KIND_LABELS[m.kind] ?? (m.kind ? titleCase(m.kind) : "—")}</TableCell>
                    <TableCell sx={{ minWidth: 200 }}>{m.subject}</TableCell>
                    <TableCell align="right">{m.recipients}</TableCell>
                    <TableCell>
                      <Chip size="small" color={STATUS_COLORS[m.status]} variant="outlined" label={titleCase(m.status)} />
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </TableContainer>
          <Stack direction={{ xs: "column", sm: "row" }} justifyContent="space-between" alignItems="center" spacing={1}>
            <Typography variant="caption" color="text.secondary">
              Mails sent before this log existed are not listed. Broadcasts go out in BCC batches; recipients counts each student once.
            </Typography>
            {data.meta.last_page > 1 && <Pagination count={data.meta.last_page} page={data.meta.current_page} onChange={(_e, page) => setQuery((q) => ({ ...q, page }))} />}
          </Stack>
        </>
      )}
    </Stack>
  );
}
