"use client";

import { useState } from "react";
import { Alert, Box, Button, Chip, Dialog, DialogActions, DialogContent, DialogTitle, MenuItem, Stack, TextField, Typography } from "@mui/material";

import PermissionGrid from "@/components/admin/access/permissiongrid";
import { adminApi } from "@/lib/adminapi";

// Add User (staff roles): only name, email and role. The person gets an invitation describing the role, sets a
// password from it and fills in their own details on first sign-in.
export default function AddUserDialog({ open, roles, catalogue, onClose, onCreated }) {
  const [form, setForm] = useState({ name: "", email: "", staff_role_id: "" });
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState(null);
  const role = roles.find((r) => String(r.id) === String(form.staff_role_id));
  const set = (field) => (event) => setForm((prev) => ({ ...prev, [field]: event.target.value }));

  const close = () => {
    if (busy) return;
    setForm({ name: "", email: "", staff_role_id: "" });
    setError(null);
    onClose();
  };

  const submit = async () => {
    if (!form.name.trim() || !form.email.trim() || !form.staff_role_id) {
      setError("Enter the name and email, and choose a role.");
      return;
    }
    setBusy(true);
    setError(null);
    try {
      const response = await adminApi("/admin/manage-admins", { method: "POST", body: JSON.stringify(form) });
      setForm({ name: "", email: "", staff_role_id: "" });
      onCreated(response.user, response.message);
    } catch (e) {
      setError(e instanceof Error ? e.message : "Failed to add the user.");
    } finally {
      setBusy(false);
    }
  };

  return (
    <Dialog open={open} onClose={close} maxWidth="md" fullWidth>
      <DialogTitle>Add User</DialogTitle>
      <DialogContent dividers>
        <Stack spacing={2}>
          {error && <Alert severity="error">{error}</Alert>}
          <Typography variant="body2" color="text.secondary">
            They get an email describing their role, with a link to set a password. On first sign-in they fill in their own details (full name, mobile, designation).
          </Typography>
          <Stack direction={{ xs: "column", sm: "row" }} spacing={1}>
            <TextField size="small" label="Name" required value={form.name} onChange={set("name")} fullWidth slotProps={{ htmlInput: { maxLength: 255 } }} />
            <TextField size="small" label="Email ID" type="email" required value={form.email} onChange={set("email")} fullWidth slotProps={{ htmlInput: { maxLength: 255 } }} />
          </Stack>
          <TextField select size="small" label="Role" required value={form.staff_role_id} onChange={set("staff_role_id")}>
            {roles.map((r) => (
              <MenuItem key={r.id} value={r.id}>
                <Chip size="small" color={r.color} label={r.name} sx={{ mr: 1 }} />
                {r.description ? (
                  <Typography variant="caption" color="text.secondary" noWrap>
                    {r.description}
                  </Typography>
                ) : null}
              </MenuItem>
            ))}
          </TextField>
          {role && (
            <Box>
              <Typography variant="subtitle2" fontWeight={700} gutterBottom>
                What {role.name} can do
              </Typography>
              {role.is_full_access ? (
                <Alert severity="info">Everything except managing users and roles.</Alert>
              ) : (
                <PermissionGrid catalogue={catalogue} levels={role.levels} disabled />
              )}
              <Typography variant="caption" color="text.secondary">
                You can tick or untick single permissions for this person after adding them.
              </Typography>
            </Box>
          )}
        </Stack>
      </DialogContent>
      <DialogActions>
        <Button onClick={close} disabled={busy}>
          Cancel
        </Button>
        <Button variant="contained" onClick={submit} disabled={busy}>
          {busy ? "Sending..." : "Add and send invitation"}
        </Button>
      </DialogActions>
    </Dialog>
  );
}
