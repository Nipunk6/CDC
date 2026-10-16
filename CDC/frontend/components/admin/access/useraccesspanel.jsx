"use client";

import { useCallback, useEffect, useState } from "react";
import {
  Alert,
  Autocomplete,
  Box,
  Button,
  Checkbox,
  Chip,
  Dialog,
  DialogActions,
  DialogContent,
  DialogTitle,
  Divider,
  FormControlLabel,
  LinearProgress,
  MenuItem,
  Stack,
  TextField,
  Typography,
} from "@mui/material";
import PowerSettingsNewIcon from "@mui/icons-material/PowerSettingsNew";
import SwapHorizIcon from "@mui/icons-material/SwapHoriz";
import RestartAltIcon from "@mui/icons-material/RestartAlt";
import SaveIcon from "@mui/icons-material/Save";

import PermissionGrid from "@/components/admin/access/permissiongrid";
import { adminApi } from "@/lib/adminapi";

// The access part of a staff member's card: role (change / turn off / move to someone else), department limit,
// and every permission with where it comes from ("From role", "Added for this person", "Removed for this person").
export default function UserAccessPanel({ user, roles, catalogue, departmentOptions, admins, onChanged }) {
  const [access, setAccess] = useState(null);
  const [draft, setDraft] = useState(null);
  const [departments, setDepartments] = useState(user.staff_departments ?? []);
  const [roleChoice, setRoleChoice] = useState(user.staff_role?.id ?? "");
  const [moving, setMoving] = useState(false);
  const [moveTo, setMoveTo] = useState("");
  const [copyOverrides, setCopyOverrides] = useState(false);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState(null);
  const [success, setSuccess] = useState(null);

  const load = useCallback(async () => {
    try {
      const response = await adminApi(`/admin/manage-admins/${user.id}/access`);
      setAccess(response.access);
      setDraft({ ...response.access.configured });
    } catch (e) {
      setError(e instanceof Error ? e.message : "Failed to load access.");
    }
  }, [user.id]);

  useEffect(() => {
    void load();
    setDepartments(user.staff_departments ?? []);
    setRoleChoice(user.staff_role?.id ?? "");
    setSuccess(null);
    setError(null);
  }, [load, user.staff_departments, user.staff_role?.id]);

  const run = async (request, after) => {
    setBusy(true);
    setError(null);
    try {
      const response = await request();
      setSuccess(response.message);
      await load();
      await onChanged?.();
      after?.();
    } catch (e) {
      setError(e instanceof Error ? e.message : "Something went wrong.");
    } finally {
      setBusy(false);
    }
  };

  const changeRole = () => {
    const role = roles.find((r) => String(r.id) === String(roleChoice));
    if (!window.confirm(role ? `Give ${user.name} the role ${role.name}? Their own ticks and unticks are cleared.` : `Remove ${user.name}'s role? They will not be able to open any admin page.`)) return;
    void run(() => adminApi(`/admin/manage-admins/${user.id}/role`, { method: "PATCH", body: JSON.stringify({ staff_role_id: role ? role.id : null }) }));
  };

  const toggleRole = () => {
    const enabled = !user.staff_role_enabled;
    if (!enabled && !window.confirm(`Turn off ${user.name}'s role? They can still sign in but cannot open any admin page until you turn it back on.`)) return;
    void run(() => adminApi(`/admin/manage-admins/${user.id}/role-status`, { method: "PATCH", body: JSON.stringify({ enabled }) }));
  };

  const moveRole = () =>
    run(
      () => adminApi(`/admin/manage-admins/${user.id}/transfer-role`, { method: "POST", body: JSON.stringify({ to_user_id: moveTo, copy_overrides: copyOverrides }) }),
      () => {
        setMoving(false);
        setMoveTo("");
        setCopyOverrides(false);
      }
    );

  const saveDepartments = () =>
    run(() => adminApi(`/admin/manage-admins/${user.id}/departments`, { method: "PUT", body: JSON.stringify({ departments: departments.length ? departments : null }) }));

  const saveAccess = () => run(() => adminApi(`/admin/manage-admins/${user.id}/access`, { method: "PUT", body: JSON.stringify({ levels: draft }) }));

  const resetAccess = () => {
    if (!window.confirm(`Remove every tick and untick made for ${user.name}? They keep exactly what the role gives.`)) return;
    void run(() => adminApi(`/admin/manage-admins/${user.id}/access`, { method: "PUT", body: JSON.stringify({ reset: true }) }));
  };

  if (!access || !draft) return error ? <Alert severity="error">{error}</Alert> : <LinearProgress />;

  const hasRole = Boolean(user.staff_role);
  const dirty = Object.keys(draft).some((key) => draft[key] !== access.configured[key]);
  const added = Object.values(access.sources).filter((s) => s === "added").length;
  const removed = Object.values(access.sources).filter((s) => s === "removed").length;
  const others = admins.filter((u) => !u.is_super_admin && u.id !== user.id);

  return (
    <Stack spacing={2}>
      <Divider />
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

      <Box>
        <Typography variant="subtitle1" fontWeight={700} gutterBottom>
          Role
        </Typography>
        <Stack direction="row" spacing={1} alignItems="center" flexWrap="wrap" useFlexGap sx={{ mb: 1 }}>
          {hasRole ? <Chip color={user.staff_role.color} label={user.staff_role.name} /> : <Chip variant="outlined" label="No role" />}
          {hasRole && !user.staff_role_enabled && <Chip color="warning" label="Role turned off" />}
          {hasRole && (added > 0 || removed > 0) && (
            <Typography variant="body2" color="text.secondary">
              + {added} added, {removed} removed for this person
            </Typography>
          )}
        </Stack>
        <Stack direction={{ xs: "column", sm: "row" }} spacing={1} flexWrap="wrap" useFlexGap>
          <TextField select size="small" label="Change role" value={roleChoice} onChange={(e) => setRoleChoice(e.target.value)} sx={{ minWidth: 220 }}>
            <MenuItem value="">No role</MenuItem>
            {roles.map((r) => (
              <MenuItem key={r.id} value={r.id}>
                {r.name}
              </MenuItem>
            ))}
          </TextField>
          <Button variant="outlined" onClick={changeRole} disabled={busy || String(roleChoice) === String(user.staff_role?.id ?? "")}>
            Apply
          </Button>
          {hasRole && (
            <Button variant="outlined" color={user.staff_role_enabled ? "warning" : "success"} startIcon={<PowerSettingsNewIcon />} onClick={toggleRole} disabled={busy}>
              {user.staff_role_enabled ? "Turn role off" : "Turn role on"}
            </Button>
          )}
          {hasRole && (
            <Button variant="outlined" startIcon={<SwapHorizIcon />} onClick={() => setMoving(true)} disabled={busy}>
              Move role to someone else
            </Button>
          )}
        </Stack>
      </Box>

      <Box>
        <Typography variant="subtitle1" fontWeight={700} gutterBottom>
          Departments
        </Typography>
        <Typography variant="body2" color="text.secondary" sx={{ mb: 1 }}>
          Leave empty for every department. When set, student lists, student pages, resumes and branch-change requests show only these departments.
        </Typography>
        <Stack direction={{ xs: "column", sm: "row" }} spacing={1} alignItems={{ sm: "flex-start" }}>
          <Autocomplete
            multiple
            size="small"
            options={departmentOptions}
            value={departments}
            onChange={(_e, value) => setDepartments(value)}
            renderInput={(params) => <TextField {...params} label="Limit to departments" placeholder={departments.length ? "" : "Every department"} />}
            sx={{ flex: 1, minWidth: 0 }}
          />
          <Button variant="outlined" onClick={saveDepartments} disabled={busy || JSON.stringify(departments) === JSON.stringify(user.staff_departments ?? [])}>
            Save departments
          </Button>
        </Stack>
      </Box>

      <Box>
        <Stack direction={{ xs: "column", sm: "row" }} justifyContent="space-between" alignItems={{ sm: "center" }} spacing={1} sx={{ mb: 1 }}>
          <Typography variant="subtitle1" fontWeight={700}>
            Access
          </Typography>
          {hasRole && (
            <Stack direction="row" spacing={1}>
              <Button size="small" variant="contained" startIcon={<SaveIcon />} onClick={saveAccess} disabled={busy || !dirty}>
                Save access
              </Button>
              <Button size="small" variant="outlined" startIcon={<RestartAltIcon />} onClick={resetAccess} disabled={busy || (added === 0 && removed === 0)}>
                Reset to role
              </Button>
            </Stack>
          )}
        </Stack>
        {!hasRole ? (
          <Alert severity="info">Give this person a role first; then you can tick or untick single permissions.</Alert>
        ) : (
          <>
            {!user.staff_role_enabled && (
              <Alert severity="warning" sx={{ mb: 1 }}>
                The role is turned off, so none of this applies right now. Changes you save here apply when you turn it back on.
              </Alert>
            )}
            <Typography variant="body2" color="text.secondary" sx={{ mb: 1 }}>
              Change a level to give or take away one permission for this person only. A level equal to the role&apos;s removes their own change.
            </Typography>
            <PermissionGrid catalogue={catalogue} levels={draft} onChange={setDraft} sources={dirty ? undefined : access.sources} baseLevels={access.configured} />
          </>
        )}
      </Box>

      <Dialog open={moving} onClose={() => !busy && setMoving(false)} maxWidth="sm" fullWidth>
        <DialogTitle>Move {user.staff_role?.name} to someone else</DialogTitle>
        <DialogContent dividers>
          <Stack spacing={2}>
            <Typography variant="body2" color="text.secondary">
              The other person gets this role (replacing theirs). {user.name} is left with no role and cannot open any admin page until you give them one.
            </Typography>
            <TextField select size="small" label="Give the role to" value={moveTo} onChange={(e) => setMoveTo(e.target.value)}>
              {others.map((u) => (
                <MenuItem key={u.id} value={u.id}>
                  {u.name} · {u.email} {u.staff_role ? `(now: ${u.staff_role.name})` : "(no role)"}
                </MenuItem>
              ))}
            </TextField>
            <FormControlLabel
              control={<Checkbox checked={copyOverrides} onChange={(e) => setCopyOverrides(e.target.checked)} />}
              label={`Also copy ${user.name}'s own ticks and unticks (${added} added, ${removed} removed)`}
            />
          </Stack>
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setMoving(false)} disabled={busy}>
            Cancel
          </Button>
          <Button variant="contained" onClick={moveRole} disabled={busy || !moveTo}>
            Move role
          </Button>
        </DialogActions>
      </Dialog>
    </Stack>
  );
}
