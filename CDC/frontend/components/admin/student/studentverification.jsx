"use client";

import { useState } from "react";
import {
  Alert,
  Avatar,
  Box,
  Button,
  Chip,
  Dialog,
  DialogActions,
  DialogContent,
  DialogTitle,
  Paper,
  Stack,
  TextField,
  Typography,
} from "@mui/material";
import CheckIcon from "@mui/icons-material/Check";
import CloseIcon from "@mui/icons-material/Close";
import DoneAllIcon from "@mui/icons-material/DoneAll";

import VerifiedBadge from "@/components/shared/verifiedbadge";
import { adminApi } from "@/lib/adminapi";
import { useAccess } from "@/lib/access";
import { formatDateTime, statusColor, titleCase } from "@/lib/format";

// Profile verification. Each student-changeable field is accepted or rejected on its own; a student's change applies
// at once and comes back here as unverified. The green badge needs every field verified and no open resume.
// Every decision sends the version the admin saw (changed_at); a newer change is refused with 409 and reloaded.
export default function StudentVerification({ student, photoUrl, onChanged, onOpenResumes }) {
  const fields = student.verification ?? [];
  const resumes = student.resumes ?? [];
  const pendingResumes = resumes.filter((r) => r.status === "pending");
  const rejectedResumes = resumes.filter((r) => r.status === "rejected");
  const openFields = fields.filter((f) => f.status !== "verified");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState(null);
  const [success, setSuccess] = useState(null);
  const [rejecting, setRejecting] = useState(null);
  const [remark, setRemark] = useState("");
  const [dialogError, setDialogError] = useState(null);
  const { can } = useAccess();
  const canVerify = can("profile_verification", "verify");
  const canVerifyResumes = can("resumes", "verify");

  const decide = async (field, status, fieldRemark = null) => {
    setBusy(true);
    setError(null);
    setDialogError(null);
    try {
      const response = await adminApi(`/admin/students/${student.id}/verification/${field.field}`, {
        method: "PATCH",
        body: JSON.stringify({ status, remark: fieldRemark, expected_changed_at: field.changed_at }),
      });
      setSuccess(response.message);
      setRejecting(null);
      await onChanged?.();
    } catch (e) {
      const message = e instanceof Error ? e.message : "Failed to save the decision.";
      if (rejecting) setDialogError(message);
      else setError(message);
      // A 409 means the student changed the value: reload so the new value is shown.
      await onChanged?.();
    } finally {
      setBusy(false);
    }
  };

  const verifyAll = async () => {
    const parts = [];
    if (openFields.length) parts.push(`${openFields.length} profile field(s)`);
    if (pendingResumes.length) parts.push(`${pendingResumes.length} pending resume(s)`);
    if (!window.confirm(`Mark ${parts.join(" and ")} as verified? Check the values first.`)) return;

    setBusy(true);
    setError(null);
    try {
      const messages = [];
      if (openFields.length) {
        const response = await adminApi(`/admin/students/${student.id}/verification/verify-all`, {
          method: "POST",
          body: JSON.stringify({ fields: openFields.map((f) => ({ field: f.field, changed_at: f.changed_at })) }),
        });
        messages.push(response.message);
      }
      if (pendingResumes.length && canVerifyResumes) {
        const response = await adminApi(`/admin/students/${student.id}/resumes/verify-all`, {
          method: "POST",
          body: JSON.stringify({ resumes: pendingResumes.map((r) => ({ id: r.id, expected_updated_at: r.updated_at })) }),
        });
        messages.push(response.message);
      }
      setSuccess(messages.join(" "));
    } catch (e) {
      setError(e instanceof Error ? e.message : "Failed to verify.");
    } finally {
      await onChanged?.();
      setBusy(false);
    }
  };

  const renderValue = (field) => {
    if (field.field === "photo") {
      return field.has_value ? <Avatar src={photoUrl ?? undefined} variant="rounded" sx={{ width: 48, height: 48 }} alt="Student photo" /> : <em>Not uploaded</em>;
    }
    return field.has_value ? field.value : <em>Not provided</em>;
  };

  return (
    <Stack spacing={2}>
      <Stack direction={{ xs: "column", sm: "row" }} spacing={1} justifyContent="space-between" alignItems={{ sm: "center" }}>
        <Stack direction="row" spacing={1} alignItems="center">
          <Typography variant="subtitle1" fontWeight={700}>
            Profile verification
          </Typography>
          <VerifiedBadge verified={student.is_verified} />
          <Typography variant="body2" color="text.secondary">
            {student.is_verified ? "Everything is verified." : `${openFields.length} field(s) and ${pendingResumes.length + rejectedResumes.length} resume(s) still open.`}
          </Typography>
        </Stack>
        <Button
          variant="contained"
          color="success"
          size="small"
          startIcon={<DoneAllIcon />}
          disabled={busy || !canVerify || (openFields.length === 0 && (pendingResumes.length === 0 || !canVerifyResumes))}
          onClick={verifyAll}
        >
          Verify all
        </Button>
      </Stack>

      {error && (
        <Alert severity="error" onClose={() => setError(null)}>
          {error}
        </Alert>
      )}
      {success && (
        <Alert severity="success" onClose={() => setSuccess(null)}>
          {success}
        </Alert>
      )}

      {fields.map((field) => (
        <Paper key={field.field} variant="outlined" sx={{ p: 1.5 }}>
          <Stack direction={{ xs: "column", md: "row" }} spacing={1.5} justifyContent="space-between" alignItems={{ md: "center" }}>
            <Box sx={{ minWidth: 0 }}>
              <Stack direction="row" spacing={1} alignItems="center" flexWrap="wrap" useFlexGap>
                <Typography variant="caption" color="text.secondary">
                  {field.label}
                </Typography>
                <Chip size="small" variant="outlined" color={statusColor(field.status)} label={titleCase(field.status)} />
              </Stack>
              <Typography variant="body2" component="div" sx={{ wordBreak: "break-word", mt: 0.5 }}>
                {renderValue(field)}
              </Typography>
              {field.status === "rejected" && field.remark && (
                <Typography variant="body2" color="error" sx={{ wordBreak: "break-word" }}>
                  Remark: {field.remark}
                </Typography>
              )}
              <Typography variant="caption" color="text.secondary">
                {field.changed_at ? `Changed ${formatDateTime(field.changed_at)}` : "Not changed since registration"}
                {field.reviewed_by ? ` · ${titleCase(field.status)} by ${field.reviewed_by}, ${formatDateTime(field.reviewed_at)}` : ""}
              </Typography>
            </Box>
            <Stack direction="row" spacing={1} sx={{ flexShrink: 0 }}>
              <Button
                size="small"
                variant="outlined"
                color="success"
                startIcon={<CheckIcon />}
                disabled={busy || !canVerify || field.status === "verified"}
                onClick={() => decide(field, "verified")}
              >
                Accept
              </Button>
              <Button
                size="small"
                variant="outlined"
                color="error"
                startIcon={<CloseIcon />}
                disabled={busy || !canVerify || field.status === "rejected"}
                onClick={() => {
                  setRejecting(field);
                  setRemark("");
                  setDialogError(null);
                }}
              >
                Reject
              </Button>
            </Stack>
          </Stack>
        </Paper>
      ))}

      <Paper variant="outlined" sx={{ p: 1.5 }}>
        <Stack direction={{ xs: "column", sm: "row" }} spacing={1} justifyContent="space-between" alignItems={{ sm: "center" }}>
          <Box>
            <Typography variant="caption" color="text.secondary">
              Resumes
            </Typography>
            <Typography variant="body2">
              {resumes.length === 0
                ? "No resume uploaded."
                : `${resumes.length - pendingResumes.length - rejectedResumes.length} verified · ${pendingResumes.length} pending · ${rejectedResumes.length} rejected`}
            </Typography>
          </Box>
          <Button size="small" onClick={onOpenResumes}>
            Review resumes
          </Button>
        </Stack>
      </Paper>

      <Dialog open={Boolean(rejecting)} onClose={() => !busy && setRejecting(null)} fullWidth maxWidth="sm">
        <DialogTitle>Reject {rejecting?.label}</DialogTitle>
        <DialogContent>
          {dialogError && (
            <Alert severity="error" sx={{ mb: 2 }}>
              {dialogError}
            </Alert>
          )}
          <Typography variant="body2" color="text.secondary" sx={{ mb: 2 }}>
            The student keeps the current value and sees your remark on their profile and in their notifications.
          </Typography>
          <TextField
            label="Remark for the student"
            value={remark}
            onChange={(e) => setRemark(e.target.value)}
            fullWidth
            multiline
            minRows={3}
            required
            autoFocus
            slotProps={{ htmlInput: { maxLength: 2000 } }}
          />
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setRejecting(null)} disabled={busy}>
            Cancel
          </Button>
          <Button variant="contained" color="error" disabled={busy || !remark.trim()} onClick={() => decide(rejecting, "rejected", remark.trim())}>
            Reject
          </Button>
        </DialogActions>
      </Dialog>
    </Stack>
  );
}
