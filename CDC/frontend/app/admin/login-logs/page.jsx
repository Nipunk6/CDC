"use client";

import { useEffect, useState } from "react";
import { useSession } from "next-auth/react";
import {
  Alert,
  Box,
  Button,
  Card,
  Chip,
  FormControl,
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
import DownloadIcon from "@mui/icons-material/Download";
import FilterListIcon from "@mui/icons-material/FilterList";

import PageHeader from "@/components/shared/pageheader";
import { adminApi, adminDownload } from "@/lib/adminapi";
import { formatDateTime } from "@/lib/format";

// Phase 3 P0.5 (N4): every sign-in attempt, of every role. A super admin sees all of it and can download it; other
// admins see their own sign-ins and students' sign-ins (the API enforces this).

const ROLES = [
  ["", "All roles"],
  ["student", "Student"],
  ["company", "Recruiter"],
  ["admin", "Admin"],
  ["mentor", "Mentor"],
  ["unknown", "No such account"],
];

const REASONS = {
  wrong_password: "Wrong password",
  unknown_account: "No such account",
  suspended: "Account suspended",
  throttled: "Too many attempts",
};

const ROLE_LABELS = { student: "Student", company: "Recruiter", admin: "Admin", mentor: "Mentor" };

const EMPTY = { role: "", outcome: "", q: "", ip: "", from: "", to: "" };
const FROM_TABLET = "@media (min-width:768px)";

const queryOf = (filters) => {
  const query = new URLSearchParams();
  Object.entries(filters).forEach(([key, value]) => value && query.set(key, value));
  return query;
};

export default function AdminLoginLogsPage() {
  const { data: session } = useSession();
  const isSuper = Boolean(session?.user?.isSuperAdmin);

  const [filters, setFilters] = useState(EMPTY);
  const [applied, setApplied] = useState(EMPTY);
  const [page, setPage] = useState(1);
  const [data, setData] = useState(null);
  const [error, setError] = useState(null);
  const [downloading, setDownloading] = useState(false);
  // On a phone the filters fold away behind one button, so the table is on the first screen (design brief §5).
  const [filtersOpen, setFiltersOpen] = useState(false);
  const filtersSet = Object.values(applied).filter(Boolean).length;

  useEffect(() => {
    let cancelled = false;
    const query = queryOf(applied);
    query.set("page", String(page));
    adminApi(`/admin/login-logs?${query.toString()}`)
      .then((response) => {
        if (!cancelled) {
          setData(response);
          setError(null);
        }
      })
      .catch((e) => !cancelled && setError(e instanceof Error ? e.message : "Failed to load the login log."));
    return () => {
      cancelled = true;
    };
  }, [applied, page]);

  const set = (key) => (e) => setFilters((f) => ({ ...f, [key]: e.target.value }));
  const apply = () => {
    setPage(1);
    setApplied({ ...filters, q: filters.q.trim(), ip: filters.ip.trim() });
  };

  const download = async () => {
    setDownloading(true);
    try {
      await adminDownload(`/admin/login-logs/export?${queryOf(applied).toString()}`, "login-log.xlsx");
    } catch (e) {
      setError(e instanceof Error ? e.message : "Download failed.");
    } finally {
      setDownloading(false);
    }
  };

  const rows = data?.login_logs ?? [];

  return (
    <>
      <PageHeader
        title="Login Log"
        subtitle="Every sign-in attempt: who, when, from which address, and whether it worked."
        backHref="/admin"
        backLabel="Back to Dashboard"
      />
      {error && (
        <Alert severity="error" sx={{ mb: 2 }} onClose={() => setError(null)}>
          {error}
        </Alert>
      )}
      {!isSuper && (
        <Alert severity="info" sx={{ mb: 2 }}>
          You see your own sign-ins and students&apos; sign-ins. A super admin sees every account.
        </Alert>
      )}

      <Paper sx={{ p: 2, mb: 2 }}>
        <Button
          variant="outlined"
          color="inherit"
          fullWidth
          startIcon={<FilterListIcon />}
          onClick={() => setFiltersOpen((open) => !open)}
          aria-expanded={filtersOpen}
          sx={{ display: "flex", justifyContent: "flex-start", [FROM_TABLET]: { display: "none" } }}
        >
          {filtersOpen ? "Hide filters" : filtersSet ? `Filters (${filtersSet} set)` : "Filters"}
        </Button>
        <Stack
          direction={{ xs: "column", lg: "row" }}
          spacing={1.5}
          component="form"
          onSubmit={(e) => {
            e.preventDefault();
            apply();
            setFiltersOpen(false);
          }}
          sx={{ display: filtersOpen ? "flex" : "none", mt: filtersOpen ? 1.5 : 0, [FROM_TABLET]: { display: "flex", mt: 0 } }}
        >
          <FormControl size="small" sx={{ minWidth: 160 }}>
            <InputLabel id="ll-role">Role</InputLabel>
            <Select labelId="ll-role" label="Role" value={filters.role} onChange={set("role")}>
              {ROLES.map(([value, label]) => (
                <MenuItem key={value || "all"} value={value}>
                  {label}
                </MenuItem>
              ))}
            </Select>
          </FormControl>
          <FormControl size="small" sx={{ minWidth: 140 }}>
            <InputLabel id="ll-outcome">Result</InputLabel>
            <Select labelId="ll-outcome" label="Result" value={filters.outcome} onChange={set("outcome")}>
              <MenuItem value="">All</MenuItem>
              <MenuItem value="success">Signed in</MenuItem>
              <MenuItem value="failure">Failed</MenuItem>
            </Select>
          </FormControl>
          <TextField size="small" label="Name, email or roll no" value={filters.q} onChange={set("q")} sx={{ flex: 1, minWidth: 180 }} />
          <TextField size="small" label="IP address starts with" value={filters.ip} onChange={set("ip")} sx={{ minWidth: 180 }} />
          <TextField size="small" type="date" label="From" value={filters.from} onChange={set("from")} slotProps={{ inputLabel: { shrink: true } }} />
          <TextField size="small" type="date" label="To" value={filters.to} onChange={set("to")} slotProps={{ inputLabel: { shrink: true } }} />
          <Button type="submit" variant="contained">
            Filter
          </Button>
          {isSuper && (
            <Button variant="outlined" startIcon={<DownloadIcon />} onClick={download} disabled={downloading} sx={{ whiteSpace: "nowrap" }}>
              Download
            </Button>
          )}
        </Stack>
      </Paper>

      <Card>
        {!data && <LinearProgress />}
        <TableContainer>
          {/* On a phone the table keeps readable columns and scrolls sideways inside its card. */}
          <Table size="small" stickyHeader sx={{ minWidth: 640 }}>
            <TableHead>
              <TableRow>
                <TableCell>When</TableCell>
                <TableCell>Who</TableCell>
                <TableCell>Result</TableCell>
                <TableCell>IP address</TableCell>
                <TableCell sx={{ display: { xs: "none", lg: "table-cell" } }}>Browser</TableCell>
              </TableRow>
            </TableHead>
            <TableBody>
              {data && rows.length === 0 && (
                <TableRow>
                  <TableCell colSpan={5}>No sign-in attempts match these filters.</TableCell>
                </TableRow>
              )}
              {rows.map((row) => (
                <TableRow key={row.id} hover>
                  <TableCell sx={{ whiteSpace: "nowrap" }}>{formatDateTime(row.created_at)}</TableCell>
                  <TableCell>
                    <Typography variant="body2" fontWeight={600} sx={{ textAlign: "left" }}>
                      {row.user?.name ?? (row.identifier ? "Deleted account" : "No such account")}
                    </Typography>
                    <Typography variant="caption" color="text.secondary" sx={{ wordBreak: "break-all" }}>
                      {[row.identifier, ROLE_LABELS[row.role]].filter(Boolean).join(" · ") || "The typed email or roll number is not stored"}
                    </Typography>
                  </TableCell>
                  <TableCell>
                    <Chip
                      size="small"
                      color={row.success ? "success" : "error"}
                      variant={row.success ? "filled" : "outlined"}
                      label={row.success ? "Signed in" : (REASONS[row.failure_reason] ?? "Failed")}
                    />
                  </TableCell>
                  <TableCell>
                    <Box sx={{ display: "flex", alignItems: "center", gap: 1, flexWrap: "wrap" }}>
                      <span>{row.ip}</span>
                      {row.ip_source === "invalid_signature" && (
                        <Tooltip title="A forwarded address was sent with this attempt but could not be verified, so it was ignored. The address shown is where the connection came from.">
                          <Chip size="small" color="warning" variant="outlined" label="Forwarded address not verified" />
                        </Tooltip>
                      )}
                    </Box>
                  </TableCell>
                  <TableCell sx={{ display: { xs: "none", lg: "table-cell" }, maxWidth: 280 }}>
                    <Typography variant="caption" color="text.secondary" noWrap component="div" title={row.user_agent ?? ""}>
                      {row.user_agent ?? "—"}
                    </Typography>
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </TableContainer>
        {data?.meta && (
          <Stack direction={{ xs: "column", sm: "row" }} alignItems="center" justifyContent="space-between" spacing={1} sx={{ px: 2, py: 1.5 }}>
            <Typography variant="body2" color="text.secondary">
              {data.meta.total} attempt{data.meta.total === 1 ? "" : "s"}
            </Typography>
            {data.meta.last_page > 1 && <Pagination count={data.meta.last_page} page={page} onChange={(_e, v) => setPage(v)} color="primary" />}
          </Stack>
        )}
      </Card>
    </>
  );
}
