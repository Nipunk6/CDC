"use client";

import { useEffect, useState } from "react";
import {
  Alert,
  Box,
  Button,
  Checkbox,
  Dialog,
  DialogActions,
  DialogContent,
  DialogTitle,
  LinearProgress,
  Stack,
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TableRow,
  Typography,
} from "@mui/material";
import DownloadIcon from "@mui/icons-material/Download";

import { adminApi, adminDownload } from "@/lib/adminapi";

/**
 * "Reconcile Ineligible Students" for one stage (owner decision B2-11, fix H1). Lists the stage's pool members who are
 * no longer eligible (EligibilityService reasons, the same sentences students see), downloads the report, and marks
 * the ticked students as rejected in this stage with the regret mail — after a confirm step.
 */
export default function ReconcileDialog({ postingId, round, onClose, onDone }) {
  const base = `/admin/postings/${postingId}/rounds/${round.id}/reconcile`;
  const [data, setData] = useState(null);
  const [ticked, setTicked] = useState([]);
  const [confirming, setConfirming] = useState(false);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState(null);

  useEffect(() => {
    let cancelled = false;
    adminApi(base)
      .then((r) => {
        if (cancelled) return;
        setData(r);
        setTicked(r.students.map((s) => s.application_id)); // all ticked by default
      })
      .catch((e) => !cancelled && setError(e instanceof Error ? e.message : "Could not check eligibility."));
    return () => {
      cancelled = true;
    };
  }, [base]);

  const students = data?.students ?? [];
  const all = students.length > 0 && ticked.length === students.length;
  const toggle = (id) => setTicked((t) => (t.includes(id) ? t.filter((x) => x !== id) : [...t, id]));

  const reject = async () => {
    setBusy(true);
    setError(null);
    try {
      const response = await adminApi(base, { method: "POST", body: JSON.stringify({ application_ids: ticked, confirm: true }) });
      onDone(response.message);
    } catch (e) {
      setError(e instanceof Error ? e.message : "Could not mark the students as rejected.");
      setConfirming(false);
    } finally {
      setBusy(false);
    }
  };

  return (
    <Dialog open onClose={() => !busy && onClose()} maxWidth="md" fullWidth>
      <DialogTitle>Reconcile Ineligible Students — {round.name}</DialogTitle>
      <DialogContent dividers>
        <Stack spacing={2}>
          {error && <Alert severity="error">{error}</Alert>}
          {!data && !error && (
            <Box>
              <Typography variant="body2" color="text.secondary" gutterBottom>
                Checking Students Eligibility....
              </Typography>
              <LinearProgress />
            </Box>
          )}
          {data && (
            <>
              <Typography variant="body2" color="text.secondary">
                Every student in this stage ({data.pool_count}) was checked against the job profile&apos;s current eligibility, blocks and debarments.
                Students with an offer on this job profile and students already decided in this stage are not listed.
              </Typography>
              {data.blocked_reason && <Alert severity="info">{data.blocked_reason}</Alert>}
              {students.length === 0 ? (
                <Alert severity="success">All students in this stage are eligible.</Alert>
              ) : (
                <TableContainer sx={{ border: 1, borderColor: "divider", borderRadius: 1, maxHeight: 420 }}>
                  <Table size="small" stickyHeader>
                    <TableHead>
                      <TableRow>
                        <TableCell padding="checkbox">
                          <Checkbox
                            checked={all}
                            indeterminate={!all && ticked.length > 0}
                            onChange={() => setTicked(all ? [] : students.map((s) => s.application_id))}
                            inputProps={{ "aria-label": "Select all" }}
                          />
                        </TableCell>
                        <TableCell>Roll Number</TableCell>
                        <TableCell>Name</TableCell>
                        <TableCell sx={{ display: { xs: "none", sm: "table-cell" } }}>Branch</TableCell>
                        <TableCell>Reason</TableCell>
                      </TableRow>
                    </TableHead>
                    <TableBody>
                      {students.map((s) => (
                        <TableRow key={s.application_id} hover>
                          <TableCell padding="checkbox">
                            <Checkbox
                              checked={ticked.includes(s.application_id)}
                              onChange={() => toggle(s.application_id)}
                              inputProps={{ "aria-label": `Select ${s.student.roll_no}` }}
                            />
                          </TableCell>
                          <TableCell sx={{ whiteSpace: "nowrap" }}>{s.student.roll_no}</TableCell>
                          <TableCell>{s.student.full_name}</TableCell>
                          <TableCell sx={{ display: { xs: "none", sm: "table-cell" } }}>{s.student.branch}</TableCell>
                          <TableCell>
                            {s.reasons.map((reason) => (
                              <Typography key={reason} variant="body2" color="error.main">
                                {reason}
                              </Typography>
                            ))}
                          </TableCell>
                        </TableRow>
                      ))}
                    </TableBody>
                  </Table>
                </TableContainer>
              )}
              {confirming && (
                <Alert severity="warning">
                  Mark {ticked.length} student(s) as rejected in {round.name}? The decision is published at once and each of them gets the regret
                  mail. This overrides the rule that applicants keep their applications when eligibility changes.
                </Alert>
              )}
            </>
          )}
        </Stack>
      </DialogContent>
      <DialogActions sx={{ flexWrap: "wrap", gap: 1 }}>
        <Button
          startIcon={<DownloadIcon />}
          disabled={!data || students.length === 0 || busy}
          onClick={() => adminDownload(`${base}/export`, `stage-${round.id}-ineligible.xlsx`).catch((e) => setError(e.message))}
        >
          Download report
        </Button>
        <Box sx={{ flexGrow: 1 }} />
        <Button onClick={confirming ? () => setConfirming(false) : onClose} disabled={busy}>
          {confirming ? "Back" : "Cancel"}
        </Button>
        {confirming ? (
          <Button variant="contained" color="error" onClick={reject} disabled={busy || ticked.length === 0}>
            {busy ? "Marking..." : `Confirm: reject ${ticked.length}`}
          </Button>
        ) : (
          <Button
            variant="contained"
            color="error"
            disabled={!data || ticked.length === 0 || Boolean(data?.blocked_reason)}
            onClick={() => setConfirming(true)}
          >
            Mark Selected Students As Rejected
          </Button>
        )}
      </DialogActions>
    </Dialog>
  );
}
