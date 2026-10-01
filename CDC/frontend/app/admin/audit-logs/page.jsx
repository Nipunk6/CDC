"use client";

import { useEffect, useState } from "react";
import {
  Alert,
  Box,
  Button,
  Card,
  Chip,
  Collapse,
  FormControl,
  Grid2 as Grid,
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
  Typography,
} from "@mui/material";
import HistoryIcon from "@mui/icons-material/History";
import ExpandMoreIcon from "@mui/icons-material/ExpandMore";
import ExpandLessIcon from "@mui/icons-material/ExpandLess";

import PageHeader from "@/components/shared/pageheader";
import { adminApi } from "@/lib/adminapi";
import { formatDateTime } from "@/lib/format";

const pretty = (value) => (value === null || value === undefined ? "—" : JSON.stringify(value, null, 2));
const subject = (log) => (log.subject_type ? `${log.subject_type.split("\\").pop()} #${log.subject_id}` : "—");

function LogRow({ log }) {
  const [open, setOpen] = useState(false);
  return (
    <>
      <TableRow hover onClick={() => setOpen((v) => !v)} sx={{ cursor: "pointer" }}>
        <TableCell sx={{ whiteSpace: "nowrap" }}>{formatDateTime(log.created_at)}</TableCell>
        <TableCell>{log.user?.name ?? log.actor_name ?? "System"}{!log.user && log.actor_name ? " (deleted)" : ""}</TableCell>
        <TableCell>
          <Chip size="small" variant="outlined" label={log.action} />
        </TableCell>
        <TableCell>{subject(log)}</TableCell>
        <TableCell sx={{ display: { xs: "none", md: "table-cell" } }}>{log.ip ?? "—"}</TableCell>
        <TableCell padding="checkbox">
          <IconButton size="small">{open ? <ExpandLessIcon /> : <ExpandMoreIcon />}</IconButton>
        </TableCell>
      </TableRow>
      <TableRow>
        <TableCell colSpan={6} sx={{ py: 0, borderBottom: open ? undefined : 0 }}>
          <Collapse in={open} unmountOnExit>
            <Grid container spacing={2} sx={{ py: 1.5 }}>
              {[
                ["Before", log.before],
                ["After", log.after],
              ].map(([label, value]) => (
                <Grid key={label} size={{ xs: 12, md: 6 }}>
                  <Typography variant="caption" fontWeight={700}>
                    {label}
                  </Typography>
                  <Box component="pre" sx={{ m: 0, p: 1.5, bgcolor: "grey.50", border: 1, borderColor: "divider", borderRadius: 1, fontSize: 12, overflowX: "auto", maxHeight: 320 }}>
                    {pretty(value)}
                  </Box>
                </Grid>
              ))}
            </Grid>
          </Collapse>
        </TableCell>
      </TableRow>
    </>
  );
}

export default function AdminAuditLogsPage() {
  const [filters, setFilters] = useState({ user_id: "", action: "", from: "", to: "" });
  const [applied, setApplied] = useState(filters);
  const [page, setPage] = useState(1);
  const [data, setData] = useState(null);
  const [error, setError] = useState(null);

  useEffect(() => {
    let cancelled = false;
    const query = new URLSearchParams({ page: String(page) });
    Object.entries(applied).forEach(([k, v]) => v && query.set(k, v));
    adminApi(`/admin/audit-logs?${query.toString()}`)
      .then((response) => {
        if (!cancelled) {
          setData(response);
          setError(null);
        }
      })
      .catch((e) => !cancelled && setError(e instanceof Error ? e.message : "Failed to load the audit log."));
    return () => {
      cancelled = true;
    };
  }, [applied, page]);

  const set = (key) => (e) => setFilters((f) => ({ ...f, [key]: e.target.value }));

  return (
    <>
      <PageHeader icon={<HistoryIcon />} title="Audit Log" subtitle="Every admin change: who, what, when, before → after." backHref="/admin" backLabel="Back to Dashboard" />
      {error && (
        <Alert severity="error" sx={{ mb: 2 }}>
          {error}
        </Alert>
      )}
      <Paper sx={{ p: 2, mb: 2 }}>
        <Stack direction={{ xs: "column", md: "row" }} spacing={1.5}>
          <FormControl size="small" sx={{ minWidth: 200 }}>
            <InputLabel id="al-admin">Admin</InputLabel>
            <Select labelId="al-admin" label="Admin" value={filters.user_id} onChange={set("user_id")}>
              <MenuItem value="">All</MenuItem>
              {(data?.admins ?? []).map((a) => (
                <MenuItem key={a.id} value={String(a.id)}>
                  {a.name}
                </MenuItem>
              ))}
            </Select>
          </FormControl>
          <FormControl size="small" sx={{ minWidth: 220 }}>
            <InputLabel id="al-action">Action</InputLabel>
            <Select labelId="al-action" label="Action" value={filters.action} onChange={set("action")}>
              <MenuItem value="">All</MenuItem>
              {(data?.actions ?? []).map((a) => (
                <MenuItem key={a} value={a}>
                  {a}
                </MenuItem>
              ))}
            </Select>
          </FormControl>
          <TextField size="small" type="date" label="From" value={filters.from} onChange={set("from")} slotProps={{ inputLabel: { shrink: true } }} />
          <TextField size="small" type="date" label="To" value={filters.to} onChange={set("to")} slotProps={{ inputLabel: { shrink: true } }} />
          <Button
            variant="contained"
            onClick={() => {
              setPage(1);
              setApplied({ ...filters });
            }}
          >
            Filter
          </Button>
        </Stack>
      </Paper>
      <Card>
        {!data && <LinearProgress />}
        <TableContainer>
          <Table size="small">
            <TableHead>
              <TableRow>
                <TableCell>When</TableCell>
                <TableCell>Admin</TableCell>
                <TableCell>Action</TableCell>
                <TableCell>Subject</TableCell>
                <TableCell sx={{ display: { xs: "none", md: "table-cell" } }}>IP</TableCell>
                <TableCell padding="checkbox" />
              </TableRow>
            </TableHead>
            <TableBody>
              {data && data.audit_logs.length === 0 && (
                <TableRow>
                  <TableCell colSpan={6}>No entries.</TableCell>
                </TableRow>
              )}
              {(data?.audit_logs ?? []).map((log) => (
                <LogRow key={log.id} log={log} />
              ))}
            </TableBody>
          </Table>
        </TableContainer>
        {data?.meta?.last_page > 1 && (
          <Stack alignItems="center" sx={{ py: 2 }}>
            <Pagination count={data.meta.last_page} page={page} onChange={(_e, v) => setPage(v)} color="primary" />
          </Stack>
        )}
      </Card>
    </>
  );
}
