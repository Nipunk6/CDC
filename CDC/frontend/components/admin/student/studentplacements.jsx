"use client";

import { useState } from "react";
import Link from "next/link";
import {
  Alert,
  Box,
  Button,
  Chip,
  Collapse,
  Paper,
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

import { adminDownload } from "@/lib/adminapi";
import { formatDate, formatDateTime, statusColor, titleCase } from "@/lib/format";

const resultLabel = (stage) => {
  if (!stage.result) return "—";
  const label =
    { selected: stage.is_final ? "Selected" : "Shortlisted", waitlisted: "On Hold", rejected: "Not selected" }[stage.result] ?? "Pending";
  return stage.published ? label : `${label} (draft)`;
};

const attendanceChip = (attendance) =>
  attendance === "yes" ? (
    <Chip size="small" color="success" variant="outlined" label="Yes" />
  ) : attendance === "no" ? (
    <Chip size="small" color="error" variant="outlined" label="No" />
  ) : (
    <Typography component="span" color="text.secondary">
      —
    </Typography>
  );

function CycleRow({ entry }) {
  const [showApplications, setShowApplications] = useState(false);
  const [showAttendance, setShowAttendance] = useState(false);
  const applications = entry.applications ?? [];

  return (
    <Paper variant="outlined" sx={{ p: 2 }}>
      <Stack direction={{ xs: "column", sm: "row" }} spacing={1} justifyContent="space-between" alignItems={{ sm: "center" }}>
        <Box sx={{ minWidth: 0 }}>
          <Typography fontWeight={700} sx={{ wordBreak: "break-word" }}>
            <Link href={`/admin/placement-cycles/${entry.cycle.id}`}>{entry.cycle.name}</Link>
          </Typography>
          <Typography variant="body2" color="text.secondary">
            {titleCase(entry.cycle.type)}
            {entry.enrollment ? ` · Enrolled on ${formatDate(entry.enrollment.created_at)}` : ""}
          </Typography>
        </Box>
        <Chip
          color={entry.placed ? "success" : statusColor(entry.enrollment?.status)}
          variant={entry.placed ? "filled" : "outlined"}
          label={entry.status_label}
          sx={{ maxWidth: "100%", height: "auto", "& .MuiChip-label": { whiteSpace: "normal", py: 0.5 } }}
        />
      </Stack>

      <Stack direction="row" spacing={1} sx={{ mt: 1 }} flexWrap="wrap" useFlexGap>
        <Button size="small" onClick={() => setShowApplications((v) => !v)}>
          {showApplications ? "Hide Applications" : `Show Applications (${applications.length})`}
        </Button>
        <Button size="small" onClick={() => setShowAttendance((v) => !v)} disabled={applications.length === 0}>
          {showAttendance ? "Hide Attendance" : "Show Attendance"}
        </Button>
      </Stack>

      <Collapse in={showApplications} unmountOnExit>
        {applications.length === 0 ? (
          <Typography variant="body2" color="text.secondary" sx={{ mt: 1 }}>
            No applications in this placement.
          </Typography>
        ) : (
          <TableContainer sx={{ mt: 1 }}>
            <Table size="small">
              <TableHead>
                <TableRow>
                  <TableCell>Job Profile</TableCell>
                  <TableCell>Company</TableCell>
                  <TableCell>Status</TableCell>
                  <TableCell>Offer</TableCell>
                  <TableCell>Applied</TableCell>
                </TableRow>
              </TableHead>
              <TableBody>
                {applications.map((application) => (
                  <TableRow key={application.id}>
                    <TableCell>
                      <Link href={`/admin/postings/${application.job_posting_id}`}>{application.title}</Link>
                    </TableCell>
                    <TableCell>{application.company_name}</TableCell>
                    <TableCell>
                      <Chip size="small" variant="outlined" color={statusColor(application.status)} label={titleCase(application.status)} />
                    </TableCell>
                    <TableCell>{application.offer_label ?? "—"}</TableCell>
                    <TableCell sx={{ whiteSpace: "nowrap" }}>{formatDateTime(application.applied_at)}</TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </TableContainer>
        )}
      </Collapse>

      <Collapse in={showAttendance} unmountOnExit>
        <Stack spacing={1.5} sx={{ mt: 1 }}>
          {applications.map((application) => (
            <Box key={application.id}>
              <Typography variant="subtitle2">
                {application.title} · {application.company_name}
              </Typography>
              {(application.stages ?? []).length === 0 ? (
                <Typography variant="body2" color="text.secondary">
                  No stages yet.
                </Typography>
              ) : (
                <TableContainer>
                  <Table size="small">
                    <TableHead>
                      <TableRow>
                        <TableCell>Stage</TableCell>
                        <TableCell>Attendance</TableCell>
                        <TableCell>Result</TableCell>
                      </TableRow>
                    </TableHead>
                    <TableBody>
                      {application.stages.map((stage, index) => (
                        <TableRow key={stage.round_id}>
                          <TableCell>
                            {index + 1}. {stage.name}
                          </TableCell>
                          <TableCell>{attendanceChip(stage.attendance)}</TableCell>
                          <TableCell>{resultLabel(stage)}</TableCell>
                        </TableRow>
                      ))}
                    </TableBody>
                  </Table>
                </TableContainer>
              )}
            </Box>
          ))}
        </Stack>
      </Collapse>
    </Paper>
  );
}

// "Placements" (Superset parity S4.5): one row per placement cycle, Enrolled / Placed, applications, attendance, reports.
export default function StudentPlacements({ student }) {
  const [error, setError] = useState(null);
  const [busy, setBusy] = useState(null);
  const placements = student.placements ?? [];
  const firstName = (student.full_name ?? "").split(" ")[0] || "This student";

  const download = async (kind) => {
    setBusy(kind);
    setError(null);
    try {
      await adminDownload(`/admin/students/${student.id}/${kind}`, `${student.roll_no}-${kind}.xlsx`);
    } catch (e) {
      setError(e instanceof Error ? e.message : "Download failed.");
    } finally {
      setBusy(null);
    }
  };

  return (
    <Stack spacing={2}>
      <Stack direction={{ xs: "column", sm: "row" }} spacing={1} justifyContent="space-between" alignItems={{ sm: "center" }}>
        <Typography variant="subtitle1" fontWeight={700}>
          Placements
        </Typography>
        <Stack direction={{ xs: "column", sm: "row" }} spacing={1}>
          <Button size="small" variant="outlined" startIcon={<DownloadIcon />} disabled={busy !== null} onClick={() => download("placement-report")}>
            Download Placement Report
          </Button>
          <Button size="small" variant="outlined" startIcon={<DownloadIcon />} disabled={busy !== null} onClick={() => download("eligibility-report")}>
            Download Eligibility Report
          </Button>
        </Stack>
      </Stack>
      {error && (
        <Alert severity="error" onClose={() => setError(null)}>
          {error}
        </Alert>
      )}
      {placements.length === 0 ? (
        <Typography color="text.secondary">Not enrolled in any placement.</Typography>
      ) : (
        <>
          <Typography variant="body2" color="text.secondary">
            {firstName} has participated in the following placement cycles
          </Typography>
          {placements.map((entry) => (
            <CycleRow key={entry.cycle.id} entry={entry} />
          ))}
        </>
      )}
    </Stack>
  );
}
