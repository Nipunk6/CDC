"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import {
  Alert,
  Box,
  Chip,
  LinearProgress,
  Pagination,
  Stack,
  Tab,
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TableRow,
  Tabs,
  TextField,
  Typography,
} from "@mui/material";

import { adminApi } from "@/lib/adminapi";
import StudentQuickView from "@/components/admin/studentquickview";
import TemplateDownloadButton from "@/components/admin/templatedownloadbutton";
import { shortProgramme } from "@/lib/usecatalogue";

// A student's name opens the quick-view drawer (Superset parity S4.3).
const nameButtonSx = { border: 0, p: 0, bgcolor: "transparent", color: "primary.main", cursor: "pointer", textAlign: "left", font: "inherit" };

/**
 * "Eligible – Applied / Not applied" (req 20, QA F-009): every student currently eligible for the posting,
 * so the CDC can see who has not applied yet.
 */
export default function EligibleTab({ posting }) {
  const [status, setStatus] = useState("not_applied");
  const [search, setSearch] = useState("");
  const [page, setPage] = useState(1);
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [quickView, setQuickView] = useState(null);

  useEffect(() => {
    let cancelled = false;
    const timer = setTimeout(() => {
      setLoading(true);
      const query = new URLSearchParams({ status, page: String(page) });
      if (search.trim()) query.set("search", search.trim());
      adminApi(`/admin/postings/${posting.id}/eligible?${query}`)
        .then((response) => {
          if (cancelled) return;
          setData(response);
          setError(null);
        })
        .catch((e) => !cancelled && setError(e instanceof Error ? e.message : "Failed to load eligible students."))
        .finally(() => !cancelled && setLoading(false));
    }, 250);
    return () => {
      cancelled = true;
      clearTimeout(timer);
    };
  }, [posting.id, status, search, page]);

  const counts = data?.counts;

  return (
    <Stack spacing={2}>
      {error && <Alert severity="error">{error}</Alert>}
      <Stack direction={{ xs: "column", sm: "row" }} justifyContent="space-between" alignItems={{ sm: "center" }} spacing={1}>
        <Typography variant="body2" color="text.secondary">
          Students who are eligible for this job profile right now (enrolled, meeting the cut-offs and not blocked).
        </Typography>
        <TemplateDownloadButton
          label="Download Eligible List"
          path={`/admin/postings/${posting.id}/eligible/export`}
          fileName={`posting-${posting.id}-eligible.xlsx`}
          onError={setError}
          variant="outlined"
          color="primary"
          size="small"
        />
      </Stack>
      <Stack direction={{ xs: "column", sm: "row" }} justifyContent="space-between" spacing={1}>
        <Tabs
          value={status}
          onChange={(_e, value) => {
            setStatus(value);
            setPage(1);
          }}
          variant="scrollable"
          allowScrollButtonsMobile
        >
          <Tab value="not_applied" label={`Not applied${counts ? ` (${counts.not_applied})` : ""}`} />
          <Tab value="applied" label={`Applied${counts ? ` (${counts.applied})` : ""}`} />
          <Tab value="all" label={`All eligible${counts ? ` (${counts.eligible})` : ""}`} />
        </Tabs>
        <TextField
          size="small"
          label="Search Roll Number or name"
          value={search}
          onChange={(e) => {
            setSearch(e.target.value);
            setPage(1);
          }}
        />
      </Stack>
      {loading && <LinearProgress />}
      <TableContainer>
        <Table size="small">
          <TableHead>
            <TableRow>
              <TableCell>Roll Number</TableCell>
              <TableCell>Name</TableCell>
              <TableCell sx={{ display: { xs: "none", md: "table-cell" } }}>Programme · Branch</TableCell>
              <TableCell>CGPA</TableCell>
              <TableCell sx={{ display: { xs: "none", sm: "table-cell" } }}>Institute email</TableCell>
              <TableCell>Status</TableCell>
            </TableRow>
          </TableHead>
          <TableBody>
            {(data?.students ?? []).map((s) => (
              <TableRow key={s.id} hover>
                <TableCell>
                  <Link href={`/admin/students/${s.id}`}>{s.roll_no}</Link>
                </TableCell>
                <TableCell>
                  <Typography component="button" type="button" variant="body2" onClick={() => setQuickView(s.id)} sx={nameButtonSx}>
                    {s.full_name}
                  </Typography>
                </TableCell>
                <TableCell sx={{ display: { xs: "none", md: "table-cell" } }}>
                  {shortProgramme(s.programme)} · {s.branch}
                </TableCell>
                <TableCell>{s.current_cgpa ?? "—"}</TableCell>
                <TableCell sx={{ display: { xs: "none", sm: "table-cell" }, wordBreak: "break-all" }}>{s.institute_email}</TableCell>
                <TableCell>
                  <Chip size="small" variant="outlined" color={s.applied ? "success" : "default"} label={s.applied ? "Applied" : "Not applied"} />
                </TableCell>
              </TableRow>
            ))}
            {!loading && data?.students?.length === 0 && (
              <TableRow>
                <TableCell colSpan={6}>
                  <Typography variant="body2" color="text.secondary" sx={{ py: 2, textAlign: "center" }}>
                    No students here.
                  </Typography>
                </TableCell>
              </TableRow>
            )}
          </TableBody>
        </Table>
      </TableContainer>
      <StudentQuickView studentId={quickView} onClose={() => setQuickView(null)} />
      {data?.meta?.last_page > 1 && (
        <Box sx={{ display: "flex", justifyContent: "center" }}>
          <Pagination count={data.meta.last_page} page={page} onChange={(_e, v) => setPage(v)} color="primary" />
        </Box>
      )}
    </Stack>
  );
}
