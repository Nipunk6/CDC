"use client";

import { useEffect, useState } from "react";
import { Alert, Button, Card, CardContent, FormControl, Grid2 as Grid, InputLabel, LinearProgress, MenuItem, Select, Stack, Typography } from "@mui/material";
import AssessmentIcon from "@mui/icons-material/Assessment";
import DownloadIcon from "@mui/icons-material/Download";

import PageHeader from "@/components/shared/pageheader";
import { adminApi, adminDownload } from "@/lib/adminapi";

const DESCRIPTIONS = {
  job_offers: "Every offer announced in the placement, with company, job profile, CTC offered, currency and interval.",
  students_placed: "Enrolled students with at least one offer that counts as placed (a PPO offered but not accepted does not).",
  students_not_placed: "Enrolled students without a placing offer, out of everyone enrolled.",
  placement_matrix: "Programme and branch against company: how many offers each company made to each branch.",
  absentees: "Every stage where a student was marked absent.",
  job_profiles: "All job profiles of the placement with dates, status, applicants and offers.",
};

/**
 * Reports (Superset parity S8.5): placement-level Excel lists. Every download is recorded in the audit log.
 */
export default function ReportsPage() {
  const [data, setData] = useState(null);
  const [cycleId, setCycleId] = useState("");
  const [busy, setBusy] = useState(null);
  const [error, setError] = useState(null);

  useEffect(() => {
    adminApi("/admin/reports")
      .then((r) => {
        setData(r);
        const current = r.cycles.find((c) => c.status === "open") ?? r.cycles[0];
        if (current) setCycleId(String(current.id));
      })
      .catch((e) => setError(e instanceof Error ? e.message : "Failed to load reports."));
  }, []);

  const download = async (report) => {
    setBusy(report.key);
    setError(null);
    try {
      await adminDownload(`/admin/placement-cycles/${cycleId}/reports/${report.key}`, `${report.key}.xlsx`);
    } catch (e) {
      setError(e instanceof Error ? e.message : "Download failed.");
    } finally {
      setBusy(null);
    }
  };

  return (
    <>
      <PageHeader icon={<AssessmentIcon />} title="Reports" subtitle="Placement lists as Excel downloads. Pick a placement, then a report." />
      {error && (
        <Alert severity="error" sx={{ mb: 2 }} onClose={() => setError(null)}>
          {error}
        </Alert>
      )}
      {!data && !error && <LinearProgress />}
      {data && (
        <Stack spacing={2}>
          <FormControl sx={{ maxWidth: 420 }} fullWidth>
            <InputLabel id="report-cycle">Placement</InputLabel>
            <Select labelId="report-cycle" label="Placement" value={cycleId} onChange={(e) => setCycleId(e.target.value)}>
              {data.cycles.map((c) => (
                <MenuItem key={c.id} value={String(c.id)}>
                  {c.name}
                  {c.status !== "open" ? " (closed)" : ""}
                </MenuItem>
              ))}
            </Select>
          </FormControl>
          <Grid container spacing={2}>
            {data.reports.map((report) => (
              <Grid key={report.key} size={{ xs: 12, sm: 6, lg: 4 }}>
                <Card variant="outlined" sx={{ height: "100%" }}>
                  <CardContent sx={{ height: "100%", display: "flex", flexDirection: "column", gap: 1.5 }}>
                    <Typography variant="subtitle1" fontWeight={700}>
                      {report.label}
                    </Typography>
                    <Typography variant="body2" color="text.secondary" sx={{ flexGrow: 1 }}>
                      {DESCRIPTIONS[report.key]}
                    </Typography>
                    <Button variant="outlined" startIcon={<DownloadIcon />} disabled={!cycleId || busy !== null} onClick={() => download(report)}>
                      {busy === report.key ? "Preparing..." : "Download Excel"}
                    </Button>
                  </CardContent>
                </Card>
              </Grid>
            ))}
          </Grid>
        </Stack>
      )}
    </>
  );
}
