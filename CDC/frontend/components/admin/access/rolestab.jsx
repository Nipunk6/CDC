"use client";

import { useCallback, useEffect, useMemo, useState } from "react";
import {
  Alert,
  Avatar,
  Box,
  Button,
  Card,
  CardContent,
  Chip,
  LinearProgress,
  List,
  ListItemButton,
  ListItemText,
  MenuItem,
  Paper,
  Stack,
  TextField,
  Typography,
} from "@mui/material";
import AddIcon from "@mui/icons-material/Add";
import ContentCopyIcon from "@mui/icons-material/ContentCopy";
import DeleteIcon from "@mui/icons-material/DeleteOutline";
import SaveIcon from "@mui/icons-material/Save";

import PermissionGrid from "@/components/admin/access/permissiongrid";
import { adminApi } from "@/lib/adminapi";

const COLORS = ["primary", "secondary", "success", "info", "warning", "error", "default"];
const emptyLevels = (catalogue) => Object.fromEntries(catalogue.flatMap((g) => g.permissions.map((p) => [p.key, "none"])));
const countGranted = (levels) => Object.values(levels ?? {}).filter((l) => l !== "none").length;

// Roles tab (super admins): build roles from the permission catalogue, see who has each, assign a role to someone.
export default function RolesTab() {
  const [data, setData] = useState(null);
  const [admins, setAdmins] = useState([]);
  const [selectedId, setSelectedId] = useState(null);
  const [draft, setDraft] = useState(null);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState(null);
  const [success, setSuccess] = useState(null);
  const [assignTo, setAssignTo] = useState("");

  const load = useCallback(async (selectId) => {
    try {
      const [roles, users] = await Promise.all([adminApi("/admin/roles"), adminApi("/admin/manage-admins")]);
      setData(roles);
      setAdmins((users.admins ?? []).filter((u) => !u.is_super_admin));
      if (selectId !== undefined) setSelectedId(selectId);
    } catch (e) {
      setError(e instanceof Error ? e.message : "Failed to load roles.");
    }
  }, []);

  useEffect(() => {
    void load();
  }, [load]);

  const roles = useMemo(() => data?.roles ?? [], [data]);
  const catalogue = useMemo(() => data?.catalogue ?? [], [data]);
  const selected = selectedId === "new" ? null : (roles.find((r) => r.id === selectedId) ?? roles[0] ?? null);

  // The editor works on a draft of the selected role (or a new one).
  useEffect(() => {
    if (!data) return;
    if (selectedId === "new") {
      setDraft({ id: null, name: "", description: "", color: "primary", levels: emptyLevels(catalogue), is_system: false, is_full_access: false, members: [] });
    } else if (selected) {
      setDraft({ ...selected, description: selected.description ?? "", levels: { ...selected.levels } });
    }
    setAssignTo("");
  }, [data, selectedId, selected?.id]); // eslint-disable-line react-hooks/exhaustive-deps

  const save = async () => {
    if (!draft.name.trim()) {
      setError("Give the role a name.");
      return;
    }
    setBusy(true);
    setError(null);
    try {
      const body = JSON.stringify({ name: draft.name, description: draft.description || null, color: draft.color, levels: draft.levels });
      const response = draft.id
        ? await adminApi(`/admin/roles/${draft.id}`, { method: "PATCH", body })
        : await adminApi("/admin/roles", { method: "POST", body });
      setSuccess(response.message);
      await load(response.role.id);
    } catch (e) {
      setError(e instanceof Error ? e.message : "Failed to save the role.");
    } finally {
      setBusy(false);
    }
  };

  const duplicate = async () => {
    setBusy(true);
    try {
      const response = await adminApi(`/admin/roles/${draft.id}/duplicate`, { method: "POST" });
      setSuccess(response.message);
      await load(response.role.id);
    } catch (e) {
      setError(e instanceof Error ? e.message : "Failed to duplicate the role.");
    } finally {
      setBusy(false);
    }
  };

  const remove = async () => {
    if (!window.confirm(`Delete the role "${draft.name}"?`)) return;
    setBusy(true);
    try {
      const response = await adminApi(`/admin/roles/${draft.id}`, { method: "DELETE" });
      setSuccess(response.message);
      await load(null);
    } catch (e) {
      setError(e instanceof Error ? e.message : "Failed to delete the role.");
    } finally {
      setBusy(false);
    }
  };

  const assign = async () => {
    const person = admins.find((u) => String(u.id) === String(assignTo));
    if (!person) return;
    if (person.staff_role && !window.confirm(`${person.name} has the role ${person.staff_role.name}. Replace it with ${draft.name}? Their own ticks and unticks are cleared.`)) return;
    setBusy(true);
    try {
      const response = await adminApi(`/admin/manage-admins/${person.id}/role`, { method: "PATCH", body: JSON.stringify({ staff_role_id: draft.id }) });
      setSuccess(response.message);
      await load(draft.id);
    } catch (e) {
      setError(e instanceof Error ? e.message : "Failed to assign the role.");
    } finally {
      setBusy(false);
    }
  };

  if (!data) return error ? <Alert severity="error">{error}</Alert> : <LinearProgress />;

  return (
    <Box>
      {error && (
        <Alert severity="error" sx={{ mb: 2 }} onClose={() => setError(null)}>
          {error}
        </Alert>
      )}
      {success && (
        <Alert severity="success" sx={{ mb: 2 }} onClose={() => setSuccess(null)}>
          {success}
        </Alert>
      )}
      <Box sx={{ display: "grid", gap: 2, gridTemplateColumns: { xs: "1fr", md: "minmax(240px, 1fr) 3fr" }, alignItems: "start" }}>
        <Paper variant="outlined" sx={{ p: 1.5, minWidth: 0 }}>
          <Button fullWidth variant="contained" startIcon={<AddIcon />} onClick={() => setSelectedId("new")} sx={{ mb: 1.5 }}>
            New role
          </Button>
          <List dense disablePadding>
            {roles.map((role) => (
              <ListItemButton key={role.id} selected={selectedId !== "new" && selected?.id === role.id} onClick={() => setSelectedId(role.id)} sx={{ borderRadius: 1 }}>
                <ListItemText
                  primary={
                    <Stack direction="row" spacing={1} alignItems="center">
                      <Chip size="small" color={role.color} label={role.name} />
                      {role.is_system && (
                        <Typography variant="caption" color="text.secondary">
                          built-in
                        </Typography>
                      )}
                    </Stack>
                  }
                  secondary={`${role.members.length} person(s) · ${role.is_full_access ? "everything" : `${countGranted(role.levels)} permission(s)`}`}
                />
              </ListItemButton>
            ))}
          </List>
        </Paper>

        {draft && (
          <Card variant="outlined" sx={{ minWidth: 0 }}>
            <CardContent>
              <Stack spacing={2}>
                <Typography variant="h6" fontWeight={700}>
                  {draft.id ? `Role: ${draft.name}` : "New role"}
                </Typography>
                <Stack direction={{ xs: "column", sm: "row" }} spacing={1}>
                  <TextField size="small" label="Role name" required value={draft.name} disabled={draft.is_system} onChange={(e) => setDraft({ ...draft, name: e.target.value })} fullWidth slotProps={{ htmlInput: { maxLength: 80 } }} />
                  <TextField select size="small" label="Colour" value={draft.color} onChange={(e) => setDraft({ ...draft, color: e.target.value })} sx={{ minWidth: 150 }}>
                    {COLORS.map((color) => (
                      <MenuItem key={color} value={color}>
                        <Chip size="small" color={color} label={color} />
                      </MenuItem>
                    ))}
                  </TextField>
                </Stack>
                <TextField size="small" label="Description" helperText="Shown in the invitation email to everyone who gets this role." value={draft.description} onChange={(e) => setDraft({ ...draft, description: e.target.value })} multiline minRows={2} slotProps={{ htmlInput: { maxLength: 500 } }} />

                <Box>
                  <Typography variant="subtitle1" fontWeight={700} gutterBottom>
                    What this role can do
                  </Typography>
                  {draft.is_full_access ? (
                    <Alert severity="info">Full Access grants every permission, including ones added later. Managing users and roles stays with super admins.</Alert>
                  ) : (
                    <>
                      <Typography variant="body2" color="text.secondary" sx={{ mb: 1 }}>
                        Read = view and download. Verify = also accept or reject. Write = also create, edit, delete and send. Users and roles are always for super admins only.
                      </Typography>
                      <PermissionGrid catalogue={catalogue} levels={draft.levels} onChange={(levels) => setDraft({ ...draft, levels })} />
                    </>
                  )}
                </Box>

                <Stack direction="row" spacing={1} flexWrap="wrap" useFlexGap>
                  <Button variant="contained" startIcon={<SaveIcon />} onClick={save} disabled={busy}>
                    {draft.id ? "Save role" : "Create role"}
                  </Button>
                  {draft.id && (
                    <Button variant="outlined" startIcon={<ContentCopyIcon />} onClick={duplicate} disabled={busy}>
                      Duplicate
                    </Button>
                  )}
                  {draft.id && !draft.is_system && (
                    <Button variant="outlined" color="error" startIcon={<DeleteIcon />} onClick={remove} disabled={busy || draft.members.length > 0} title={draft.members.length > 0 ? "Give its people another role first" : undefined}>
                      Delete
                    </Button>
                  )}
                </Stack>

                {draft.id && (
                  <Box>
                    <Typography variant="subtitle1" fontWeight={700} gutterBottom>
                      People with this role ({draft.members.length})
                    </Typography>
                    {draft.members.length === 0 && <Typography color="text.secondary">Nobody yet.</Typography>}
                    <Stack spacing={0.5} sx={{ mb: 1.5 }}>
                      {draft.members.map((m) => (
                        <Stack key={m.id} direction="row" spacing={1} alignItems="center">
                          <Avatar sx={{ width: 28, height: 28, fontSize: 12, bgcolor: "primary.main" }}>{m.name?.[0]}</Avatar>
                          <Typography variant="body2" sx={{ minWidth: 0, overflowWrap: "anywhere" }}>
                            {m.name} · {m.email}
                          </Typography>
                          {!m.enabled && <Chip size="small" color="warning" variant="outlined" label="Role off" />}
                        </Stack>
                      ))}
                    </Stack>
                    <Stack direction={{ xs: "column", sm: "row" }} spacing={1}>
                      <TextField select size="small" label="Assign this role to" value={assignTo} onChange={(e) => setAssignTo(e.target.value)} sx={{ minWidth: 260 }}>
                        {admins
                          .filter((u) => u.staff_role?.id !== draft.id)
                          .map((u) => (
                            <MenuItem key={u.id} value={u.id}>
                              {u.name} {u.staff_role ? `(now: ${u.staff_role.name})` : "(no role)"}
                            </MenuItem>
                          ))}
                      </TextField>
                      <Button variant="outlined" onClick={assign} disabled={busy || !assignTo}>
                        Assign
                      </Button>
                    </Stack>
                  </Box>
                )}
              </Stack>
            </CardContent>
          </Card>
        )}
      </Box>
    </Box>
  );
}
