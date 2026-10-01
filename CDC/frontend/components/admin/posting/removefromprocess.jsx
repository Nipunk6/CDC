"use client";

import { useState } from "react";
import {
  Alert,
  Button,
  Checkbox,
  Dialog,
  DialogActions,
  DialogContent,
  DialogTitle,
  FormControlLabel,
  Typography,
} from "@mui/material";

import { adminApi } from "@/lib/adminapi";

/**
 * Placed-elsewhere protocol (spec Q3.7): end this application's process, optionally telling the company.
 */
export default function RemoveFromProcess({ posting, application, onDone }) {
  const [open, setOpen] = useState(false);
  const [notify, setNotify] = useState(true);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState(null);

  const submit = async () => {
    setBusy(true);
    setError(null);
    try {
      const response = await adminApi(`/admin/postings/${posting.id}/applications/${application.id}/remove-from-process`, {
        method: "POST",
        body: JSON.stringify({ notify_company: notify }),
      });
      setOpen(false);
      onDone?.(response.message);
    } catch (e) {
      setError(e instanceof Error ? e.message : "Could not remove the application.");
    } finally {
      setBusy(false);
    }
  };

  return (
    <>
      <Button size="small" color="error" onClick={() => setOpen(true)}>
        Remove from process
      </Button>
      <Dialog open={open} onClose={() => !busy && setOpen(false)} maxWidth="xs" fullWidth>
        <DialogTitle>Remove from process</DialogTitle>
        <DialogContent>
          {error && (
            <Alert severity="error" sx={{ mb: 2 }}>
              {error}
            </Alert>
          )}
          <Typography gutterBottom>
            {application.student_profile?.roll_no} {application.student_profile?.full_name} is placed elsewhere. Their current round will be marked
            &quot;Selected elsewhere via CDC&quot;.
          </Typography>
          <FormControlLabel
            control={<Checkbox checked={notify} onChange={(e) => setNotify(e.target.checked)} />}
            label="Notify company & invite replacements"
          />
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setOpen(false)} disabled={busy}>
            Cancel
          </Button>
          <Button color="error" variant="contained" onClick={submit} disabled={busy}>
            Remove
          </Button>
        </DialogActions>
      </Dialog>
    </>
  );
}
