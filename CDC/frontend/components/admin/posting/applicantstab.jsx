"use client";

import { useCallback, useEffect, useMemo, useState } from "react";
import Link from "next/link";
import {
  Alert,
  Box,
  Button,
  Chip,
  Dialog,
  DialogActions,
  DialogContent,
  DialogTitle,
  LinearProgress,
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
import { formatDateTime } from "@/lib/format";
import { shortProgramme } from "@/lib/usecatalogue";

const proxied = (url) => `/api/proxy-pdf?url=${encodeURIComponent(url)}`;

const renderAnswer = (answer) => (Array.isArray(answer) ? answer.join(", ") : String(answer ?? "—"));

/**
 * Applicants of a posting with every admin-only flag. `rowActions(application, reload)` lets later
 * milestones (M7 placed-elsewhere removal) add buttons without touching this table.
 */
export default function ApplicantsTab({ posting, rowActions }) {
  const [applications, setApplications] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [view, setView] = useState("applied");
  const [search, setSearch] = useState("");
  const [answersFor, setAnswersFor] = useState(null);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const response = await adminApi(`/admin/postings/${posting.id}/applications`);
      setApplications(response.applications ?? []);
      setError(null);
    } catch (e) {
      setError(e instanceof Error ? e.message : "Failed to load applicants.");
    } finally {
      setLoading(false);
    }
  }, [posting.id]);

  useEffect(() => {
    void load();
  }, [load]);

  const questions = useMemo(() => Object.fromEntries((posting.questions ?? []).map((q) => [q.id, q.question])), [posting.questions]);

  const rows = applications.filter((a) => {
    if (a.status !== view) return false;
    const term = search.trim().toLowerCase();
    if (!term) return true;
    const s = a.student_profile ?? {};
    return `${s.roll_no} ${s.full_name} ${s.branch}`.toLowerCase().includes(term);
  });

  return (
    <Stack spacing={2}>
      {error && <Alert severity="error">{error}</Alert>}
      <Stack direction={{ xs: "column", sm: "row" }} justifyContent="space-between" spacing={1}>
        <Tabs value={view} onChange={(_e, value) => setView(value)}>
          <Tab value="applied" label={`Applied (${applications.filter((a) => a.status === "applied").length})`} />
          <Tab value="withdrawn" label={`Withdrawn (${applications.filter((a) => a.status === "withdrawn").length})`} />
        </Tabs>
        <TextField size="small" label="Search roll no, name, branch" value={search} onChange={(e) => setSearch(e.target.value)} />
      </Stack>
      {loading && <LinearProgress />}
      <TableContainer>
        <Table size="small">
          <TableHead>
            <TableRow>
              <TableCell>Roll no</TableCell>
              <TableCell>Name</TableCell>
              <TableCell sx={{ display: { xs: "none", md: "table-cell" } }}>Programme · Branch</TableCell>
              <TableCell>CGPA</TableCell>
              <TableCell>Resume</TableCell>
              <TableCell>Flags</TableCell>
              <TableCell sx={{ display: { xs: "none", md: "table-cell" } }}>Applied</TableCell>
              <TableCell align="right">Actions</TableCell>
            </TableRow>
          </TableHead>
          <TableBody>
            {!loading && rows.length === 0 && (
              <TableRow>
                <TableCell colSpan={8}>
                  <Typography color="text.secondary" sx={{ py: 2 }}>
                    No {view} applications.
                  </Typography>
                </TableCell>
              </TableRow>
            )}
            {rows.map((a) => (
              <TableRow key={a.id} hover>
                <TableCell sx={{ fontWeight: 600 }}>
                  <Link href={`/admin/students/${a.student_profile?.id}`}>{a.student_profile?.roll_no}</Link>
                </TableCell>
                <TableCell>{a.student_profile?.full_name}</TableCell>
                <TableCell sx={{ display: { xs: "none", md: "table-cell" } }}>
                  {shortProgramme(a.student_profile?.programme)} · {a.student_profile?.branch}
                </TableCell>
                <TableCell>{a.student_profile?.current_cgpa ?? "—"}</TableCell>
                <TableCell>
                  {a.resume_url ? (
                    <a href={proxied(a.resume_url)} target="_blank" rel="noopener">
                      {a.resume?.label ?? "Resume"}
                    </a>
                  ) : (
                    "—"
                  )}
                </TableCell>
                <TableCell>
                  <Stack direction="row" spacing={0.5} flexWrap="wrap" useFlexGap>
                    {a.used_unverified_resume && <Chip size="small" color="warning" label="⚠ Unverified resume" />}
                    {a.placed_elsewhere_flag && <Chip size="small" color="error" label="🚩 Placed elsewhere" />}
                  </Stack>
                </TableCell>
                <TableCell sx={{ display: { xs: "none", md: "table-cell" }, whiteSpace: "nowrap" }}>{formatDateTime(a.applied_at)}</TableCell>
                <TableCell align="right" sx={{ whiteSpace: "nowrap" }}>
                  {(a.answers ?? []).length > 0 && (
                    <Button size="small" onClick={() => setAnswersFor(a)}>
                      Answers
                    </Button>
                  )}
                  {rowActions?.(a, load)}
                </TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>
      </TableContainer>

      <Dialog open={Boolean(answersFor)} onClose={() => setAnswersFor(null)} maxWidth="sm" fullWidth>
        <DialogTitle>
          Answers — {answersFor?.student_profile?.roll_no} {answersFor?.student_profile?.full_name}
        </DialogTitle>
        <DialogContent dividers>
          <Stack spacing={2}>
            {(answersFor?.answers ?? []).map((item) => (
              <Box key={item.question_id}>
                <Typography variant="caption" color="text.secondary">
                  {questions[item.question_id] ?? "(question removed)"}
                </Typography>
                <Typography sx={{ whiteSpace: "pre-wrap" }}>{renderAnswer(item.answer)}</Typography>
              </Box>
            ))}
          </Stack>
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setAnswersFor(null)}>Close</Button>
        </DialogActions>
      </Dialog>
    </Stack>
  );
}
