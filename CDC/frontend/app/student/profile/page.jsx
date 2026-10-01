"use client";

import { useCallback, useEffect, useState } from "react";
import {
  Alert,
  Avatar,
  Box,
  Button,
  Card,
  CardContent,
  Chip,
  Dialog,
  DialogActions,
  DialogContent,
  DialogTitle,
  FormControl,
  Grid2 as Grid,
  InputLabel,
  LinearProgress,
  MenuItem,
  Select,
  Stack,
  TextField,
  Typography,
} from "@mui/material";
import PhotoCameraIcon from "@mui/icons-material/PhotoCamera";
import LockIcon from "@mui/icons-material/Lock";
import SwapHorizIcon from "@mui/icons-material/SwapHoriz";
import BlockIcon from "@mui/icons-material/Block";

import { studentApi, studentBlobUrl, studentUpload } from "@/lib/studentapi";
import useCatalogue from "@/lib/usecatalogue";
import { dash, formatDate, formatDateTime, statusColor, titleCase } from "@/lib/format";

const academicFields = [
  ["Roll number", "roll_no"],
  ["Institute email", "institute_email"],
  ["Programme", "programme"],
  ["Branch", "branch"],
  ["Graduating batch", "graduating_batch"],
  ["CGPA", "current_cgpa"],
  ["Ongoing backlogs", "ongoing_backlogs"],
  ["Total backlogs", "total_backlogs"],
  ["10th %", "tenth_percent"],
  ["12th %", "twelfth_percent"],
  ["Gender", "gender"],
  ["Date of birth", "date_of_birth"],
  ["Category", "category"],
  ["PwD", "pwd"],
];

const personalFields = [
  ["personal_email", "Personal email"],
  ["phone", "Phone"],
  ["home_state", "Home state"],
  ["linkedin_url", "LinkedIn URL"],
  ["github_url", "GitHub URL"],
];

export default function StudentProfilePage() {
  const catalogue = useCatalogue(studentApi);
  const [student, setStudent] = useState(null);
  const [personal, setPersonal] = useState({});
  const [requests, setRequests] = useState([]);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState(null);
  const [success, setSuccess] = useState(null);
  const [photoUrl, setPhotoUrl] = useState(null);
  const [photoVersion, setPhotoVersion] = useState(0);

  const [branchOpen, setBranchOpen] = useState(false);
  const [branchForm, setBranchForm] = useState({ requested_programme: "", requested_branch: "", reason: "" });
  const [branchError, setBranchError] = useState(null);
  const [branchBusy, setBranchBusy] = useState(false);

  const load = useCallback(async () => {
    try {
      const [profile, branchChanges] = await Promise.all([
        studentApi("/student/profile"),
        studentApi("/student/branch-change"),
      ]);
      setStudent(profile.student);
      setPersonal(Object.fromEntries(personalFields.map(([key]) => [key, profile.student?.[key] ?? ""])));
      setRequests(branchChanges.branch_change_requests ?? []);
      setError(null);
    } catch (e) {
      setError(e instanceof Error ? e.message : "Failed to load your profile.");
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    void load();
  }, [load]);

  useEffect(() => {
    if (!student?.has_photo) return undefined;
    let url = null;
    studentBlobUrl("/student/profile/photo")
      .then((value) => {
        url = value;
        setPhotoUrl(value);
      })
      .catch(() => setPhotoUrl(null));
    return () => {
      if (url) URL.revokeObjectURL(url);
    };
  }, [student?.has_photo, photoVersion]);

  const savePersonal = async () => {
    setSaving(true);
    setError(null);
    try {
      const body = Object.fromEntries(Object.entries(personal).map(([k, v]) => [k, String(v).trim() || null]));
      const response = await studentApi("/student/profile", { method: "PATCH", body: JSON.stringify(body) });
      setStudent(response.student);
      setSuccess(response.message);
    } catch (e) {
      setError(e instanceof Error ? e.message : "Failed to save.");
    } finally {
      setSaving(false);
    }
  };

  const uploadPhoto = async (file) => {
    if (!file) return;
    const body = new FormData();
    body.append("photo", file);
    try {
      const response = await studentUpload("/student/profile/photo", body);
      setStudent(response.student);
      setPhotoVersion((v) => v + 1);
      setSuccess(response.message);
    } catch (e) {
      setError(e instanceof Error ? e.message : "Photo upload failed.");
    }
  };

  const submitBranchChange = async () => {
    setBranchError(null);
    setBranchBusy(true);
    try {
      const response = await studentApi("/student/branch-change", {
        method: "POST",
        body: JSON.stringify({
          requested_programme: branchForm.requested_programme || null,
          requested_branch: branchForm.requested_branch,
          reason: branchForm.reason,
        }),
      });
      setBranchOpen(false);
      setSuccess(response.message);
      await load();
    } catch (e) {
      setBranchError(e instanceof Error ? e.message : "Could not submit the request.");
    } finally {
      setBranchBusy(false);
    }
  };

  if (loading) return <LinearProgress />;
  if (!student) return <Alert severity="error">{error ?? "Profile not found."}</Alert>;

  const hasPending = requests.some((r) => r.status === "pending");
  const branchProgramme = branchForm.requested_programme || student.programme;
  const branchOptions = (catalogue[branchProgramme] ?? []).filter(
    (b) => !(branchProgramme === student.programme && b === student.branch)
  );
  const activeBlocks = student.active_blocks ?? [];

  return (
    <Stack spacing={3}>
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

      <Card>
        <CardContent>
          <Stack direction={{ xs: "column", sm: "row" }} spacing={3} alignItems={{ xs: "center", sm: "center" }}>
            <Box sx={{ position: "relative" }}>
              <Avatar src={photoUrl ?? undefined} sx={{ width: 104, height: 104, fontSize: 40, bgcolor: "primary.main" }}>
                {student.full_name?.[0]}
              </Avatar>
              <Button
                component="label"
                size="small"
                variant="contained"
                sx={{ position: "absolute", bottom: -6, right: -6, minWidth: 0, p: 0.75, borderRadius: "50%" }}
                aria-label="Upload photo"
              >
                <PhotoCameraIcon fontSize="small" />
                <input hidden type="file" accept="image/png,image/jpeg" onChange={(e) => uploadPhoto(e.target.files?.[0])} />
              </Button>
            </Box>
            <Box sx={{ textAlign: { xs: "center", sm: "left" } }}>
              <Typography variant="h5" fontWeight={700}>
                {student.full_name}
              </Typography>
              <Typography color="text.secondary">
                {student.roll_no} · {student.branch}
              </Typography>
              <Typography variant="body2" color="text.secondary">
                {student.programme} · Batch {student.graduating_batch}
              </Typography>
            </Box>
          </Stack>
        </CardContent>
      </Card>

      {activeBlocks.length > 0 && (
        <Alert severity="warning" icon={<BlockIcon />}>
          {activeBlocks.map((block) => (
            <div key={block.id}>{block.message}</div>
          ))}
        </Alert>
      )}

      <Card>
        <CardContent>
          <Stack direction="row" spacing={1} alignItems="center" sx={{ mb: 0.5 }}>
            <LockIcon fontSize="small" color="action" />
            <Typography variant="h6" fontWeight={700}>
              Academic record
            </Typography>
          </Stack>
          <Typography variant="body2" color="text.secondary" sx={{ mb: 2 }}>
            Synced from institute records — contact CDC for corrections.
          </Typography>
          <Grid container spacing={2}>
            {academicFields.map(([label, key]) => (
              <Grid key={key} size={{ xs: 6, md: 4 }}>
                <Typography variant="caption" color="text.secondary">
                  {label}
                </Typography>
                <Typography variant="body2" fontWeight={500} sx={{ wordBreak: "break-word" }}>
                  {key === "date_of_birth"
                    ? formatDate(student[key])
                    : typeof student[key] === "boolean"
                      ? student[key]
                        ? "Yes"
                        : "No"
                      : key === "gender"
                        ? titleCase(student[key])
                        : dash(student[key])}
                </Typography>
              </Grid>
            ))}
          </Grid>
        </CardContent>
      </Card>

      <Card>
        <CardContent>
          <Typography variant="h6" fontWeight={700} gutterBottom>
            Personal details
          </Typography>
          <Grid container spacing={2}>
            {personalFields.map(([key, label]) => (
              <Grid key={key} size={{ xs: 12, sm: 6 }}>
                <TextField
                  fullWidth
                  size="small"
                  label={label}
                  value={personal[key] ?? ""}
                  onChange={(event) => setPersonal((prev) => ({ ...prev, [key]: event.target.value }))}
                />
              </Grid>
            ))}
          </Grid>
          <Stack direction="row" justifyContent="flex-end" sx={{ mt: 2 }}>
            <Button variant="contained" onClick={savePersonal} disabled={saving}>
              {saving ? "Saving..." : "Save"}
            </Button>
          </Stack>
        </CardContent>
      </Card>

      <Card>
        <CardContent>
          <Stack direction={{ xs: "column", sm: "row" }} justifyContent="space-between" spacing={1} sx={{ mb: 1 }}>
            <Typography variant="h6" fontWeight={700}>
              Branch change
            </Typography>
            <Button
              variant="outlined"
              startIcon={<SwapHorizIcon />}
              disabled={hasPending}
              onClick={() => {
                setBranchForm({ requested_programme: "", requested_branch: "", reason: "" });
                setBranchError(null);
                setBranchOpen(true);
              }}
            >
              Request branch change
            </Button>
          </Stack>
          {hasPending && (
            <Typography variant="body2" color="text.secondary" sx={{ mb: 1 }}>
              You have a pending request. You can raise another once the CDC decides it.
            </Typography>
          )}
          {requests.length === 0 ? (
            <Typography variant="body2" color="text.secondary">
              No branch change requests.
            </Typography>
          ) : (
            <Stack spacing={1.5}>
              {requests.map((request) => (
                <Box key={request.id} sx={{ p: 1.5, border: 1, borderColor: "divider", borderRadius: 1 }}>
                  <Stack direction="row" spacing={1} alignItems="center" flexWrap="wrap" useFlexGap>
                    <Typography variant="body2" fontWeight={600}>
                      {request.current_branch} → {request.requested_branch}
                    </Typography>
                    <Chip size="small" variant="outlined" color={statusColor(request.status)} label={titleCase(request.status)} />
                  </Stack>
                  <Typography variant="caption" color="text.secondary">
                    Submitted {formatDateTime(request.created_at)}
                  </Typography>
                  {request.admin_remark && (
                    <Typography variant="body2" sx={{ mt: 0.5 }}>
                      CDC remark: {request.admin_remark}
                    </Typography>
                  )}
                </Box>
              ))}
            </Stack>
          )}
        </CardContent>
      </Card>

      <Dialog open={branchOpen} onClose={() => setBranchOpen(false)} maxWidth="sm" fullWidth>
        <DialogTitle>Request branch change</DialogTitle>
        <DialogContent>
          <Stack spacing={2} sx={{ pt: 1 }}>
            {branchError && <Alert severity="error">{branchError}</Alert>}
            <FormControl fullWidth size="small">
              <InputLabel id="bc-programme">Programme</InputLabel>
              <Select
                labelId="bc-programme"
                label="Programme"
                value={branchForm.requested_programme || student.programme}
                onChange={(event) =>
                  setBranchForm((prev) => ({
                    ...prev,
                    requested_programme: event.target.value === student.programme ? "" : event.target.value,
                    requested_branch: "",
                  }))
                }
              >
                {Object.keys(catalogue).map((p) => (
                  <MenuItem key={p} value={p}>
                    {p}
                  </MenuItem>
                ))}
              </Select>
            </FormControl>
            <FormControl fullWidth size="small">
              <InputLabel id="bc-branch">New branch</InputLabel>
              <Select
                labelId="bc-branch"
                label="New branch"
                value={branchForm.requested_branch}
                onChange={(event) => setBranchForm((prev) => ({ ...prev, requested_branch: event.target.value }))}
              >
                {branchOptions.map((b) => (
                  <MenuItem key={b} value={b}>
                    {b}
                  </MenuItem>
                ))}
              </Select>
            </FormControl>
            <TextField
              label="Reason"
              multiline
              minRows={3}
              fullWidth
              helperText="At least 10 characters. Mention the senate / office order if you have one."
              value={branchForm.reason}
              onChange={(event) => setBranchForm((prev) => ({ ...prev, reason: event.target.value }))}
            />
          </Stack>
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setBranchOpen(false)}>Cancel</Button>
          <Button
            variant="contained"
            onClick={submitBranchChange}
            disabled={branchBusy || !branchForm.requested_branch || branchForm.reason.trim().length < 10}
          >
            Submit
          </Button>
        </DialogActions>
      </Dialog>
    </Stack>
  );
}
