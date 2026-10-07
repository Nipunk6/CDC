"use client";

import { Button, Dialog, DialogActions, DialogContent, DialogContentText, DialogTitle } from "@mui/material";

/**
 * MUI confirmation dialog for the engagement pages (replaces window.confirm, L27). Controlled: render it with
 * `open`, and close it from `onCancel` / after `onConfirm` finishes.
 */
export default function ConfirmDialog({ open, title, message, confirmLabel = "Confirm", cancelLabel = "Cancel", color = "primary", busy = false, onConfirm, onCancel }) {
  return (
    <Dialog open={open} onClose={() => !busy && onCancel()} maxWidth="xs" fullWidth>
      <DialogTitle>{title}</DialogTitle>
      {message && (
        <DialogContent>
          <DialogContentText sx={{ wordBreak: "break-word" }}>{message}</DialogContentText>
        </DialogContent>
      )}
      <DialogActions>
        <Button onClick={onCancel} disabled={busy}>
          {cancelLabel}
        </Button>
        <Button variant="contained" color={color} onClick={onConfirm} disabled={busy} autoFocus>
          {confirmLabel}
        </Button>
      </DialogActions>
    </Dialog>
  );
}
