"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import { Alert, Avatar, Box, Button, Chip, Divider, Drawer, IconButton, LinearProgress, Stack, Typography } from "@mui/material";
import CloseIcon from "@mui/icons-material/Close";
import OpenInNewIcon from "@mui/icons-material/OpenInNew";

import { adminApi } from "@/lib/adminapi";
import { adminBlobUrl } from "@/lib/adminupload";
import { formatMoney } from "@/lib/format";

const Row = ({ label, value }) => (
  <Stack direction="row" justifyContent="space-between" spacing={2}>
    <Typography variant="body2" color="text.secondary">
      {label}
    </Typography>
    <Typography variant="body2" fontWeight={600} sx={{ textAlign: "right", wordBreak: "break-word" }}>
      {value ?? "—"}
    </Typography>
  </Stack>
);

/**
 * Student quick view (Superset parity S4.3): a side drawer opened from lists such as the stage shortlist.
 * `studentId` null closes it.
 */
export default function StudentQuickView({ studentId, onClose }) {
  return (
    <Drawer anchor="right" open={Boolean(studentId)} onClose={onClose} PaperProps={{ sx: { width: { xs: "100%", sm: 420 } } }}>
      <Stack direction="row" alignItems="center" justifyContent="space-between" sx={{ px: 2, py: 1.5, borderBottom: 1, borderColor: "divider" }}>
        <Typography variant="subtitle1" fontWeight={700}>
          Student
        </Typography>
        <IconButton onClick={onClose} aria-label="Close">
          <CloseIcon />
        </IconButton>
      </Stack>
      {studentId && <QuickViewBody key={studentId} studentId={studentId} />}
    </Drawer>
  );
}

// Keyed by student, so every student starts from an empty, loading state.
function QuickViewBody({ studentId }) {
  const [student, setStudent] = useState(null);
  const [photo, setPhoto] = useState(null);
  const [error, setError] = useState(null);

  useEffect(() => {
    let cancelled = false;
    let objectUrl = null;
    adminApi(`/admin/students/${studentId}`)
      .then(async (response) => {
        if (cancelled) return;
        setStudent(response.student);
        if (response.student?.has_photo) {
          objectUrl = await adminBlobUrl(`/admin/students/${studentId}/photo`).catch(() => null);
          if (!cancelled) setPhoto(objectUrl);
        }
      })
      .catch((e) => !cancelled && setError(e instanceof Error ? e.message : "Could not load the student."));
    return () => {
      cancelled = true;
      if (objectUrl) URL.revokeObjectURL(objectUrl);
    };
  }, [studentId]);

  const applications = student?.applications ?? [];
  const offers = student?.offers ?? [];

  return (
    <>
      {!student && !error && <LinearProgress />}
      {error && (
        <Alert severity="error" sx={{ m: 2 }}>
          {error}
        </Alert>
      )}
      {student && (
        <Box sx={{ p: 2, overflowY: "auto" }}>
          <Stack direction="row" spacing={2} alignItems="center">
            <Avatar src={photo ?? undefined} sx={{ width: 64, height: 64, bgcolor: "primary.main" }}>
              {student.full_name?.[0]}
            </Avatar>
            <Box sx={{ minWidth: 0 }}>
              <Typography variant="h6" fontWeight={700} noWrap>
                {student.full_name}
              </Typography>
              <Typography variant="body2" color="text.secondary">
                {student.roll_no}
              </Typography>
              {student.is_active === false && <Chip size="small" color="error" label="Suspended" sx={{ mt: 0.5 }} />}
            </Box>
          </Stack>

          <Stack direction="row" spacing={1} sx={{ my: 2 }}>
            <Chip label={`CGPA ${student.current_cgpa ?? "—"}`} color="primary" variant="outlined" />
            <Chip label={`${applications.length} Applications`} variant="outlined" />
            <Chip label={`${offers.length} Offers`} color={offers.length ? "success" : "default"} variant="outlined" />
          </Stack>

          <Stack spacing={1}>
            <Row label="Programme" value={student.programme} />
            <Row label="Branch" value={student.branch} />
            <Row label="Passout Batch" value={student.graduating_batch} />
            <Row label="Backlogs (ongoing / total)" value={`${student.ongoing_backlogs ?? 0} / ${student.total_backlogs ?? 0}`} />
            <Row label="Class X Percentage" value={student.tenth_percent} />
            <Row label="Class XII Percentage" value={student.twelfth_percent} />
            <Row label="Email" value={student.institute_email} />
            <Row label="Mobile No." value={student.phone} />
          </Stack>

          {offers.length > 0 && (
            <>
              <Divider sx={{ my: 2 }} />
              <Typography variant="subtitle2" fontWeight={700} gutterBottom>
                Offers
              </Typography>
              <Stack spacing={1}>
                {offers.map((o) => (
                  <Box key={o.id}>
                    <Typography variant="body2" fontWeight={600}>
                      🏆 {o.company_name} · {o.label}
                    </Typography>
                    <Typography variant="caption" color="text.secondary">
                      {o.cycle_name}
                      {o.ctc_annual ? ` · ${formatMoney(o.ctc_annual, o.currency)} / year` : ""}
                      {o.stipend_monthly ? ` · ${formatMoney(o.stipend_monthly, o.currency)} / month` : ""}
                    </Typography>
                  </Box>
                ))}
              </Stack>
            </>
          )}

          <Button component={Link} href={`/admin/students/${student.id}`} variant="outlined" endIcon={<OpenInNewIcon />} fullWidth sx={{ mt: 3 }}>
            Open student page
          </Button>
        </Box>
      )}
    </>
  );
}
