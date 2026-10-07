"use client";

import { useEffect, useMemo, useState } from "react";
import { useSession } from "next-auth/react";
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
  InputAdornment,
  LinearProgress,
  List,
  ListItemAvatar,
  ListItemButton,
  ListItemText,
  MenuItem,
  Paper,
  Stack,
  TextField,
  Typography,
} from "@mui/material";
import SearchIcon from "@mui/icons-material/Search";
import EditIcon from "@mui/icons-material/EditOutlined";
import DeleteIcon from "@mui/icons-material/DeleteOutline";
import PersonAddIcon from "@mui/icons-material/PersonAdd";

import { adminApi } from "@/lib/adminapi";
import AddAdminModal from "@/app/admin/manage-admins/addadminmodal";

const COUNTRY_CODES = ["+91", "+1", "+44", "+61", "+65", "+971", "+49", "+81"];

const initials = (name) =>
  (name ?? "")
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase())
    .join("") || "?";

// Users created before S8.2 have only `name`: split it so the Edit form starts filled in.
const nameParts = (user) => {
  if (user.first_name) {
    return { first_name: user.first_name, middle_name: user.middle_name ?? "", last_name: user.last_name ?? "" };
  }
  const words = (user.name ?? "").trim().split(/\s+/).filter(Boolean);
  return {
    first_name: words[0] ?? "",
    middle_name: words.length > 2 ? words.slice(1, -1).join(" ") : "",
    last_name: words.length > 1 ? words[words.length - 1] : "",
  };
};

const splitMobile = (mobile) => {
  const match = /^(\+\d{1,4}) (\d+)$/.exec(mobile ?? "");
  return match ? { code: match[1], number: match[2] } : { code: "+91", number: "" };
};

function EditUserDialog({ user, onClose, onSaved }) {
  const [form, setForm] = useState(() => {
    const mobile = splitMobile(user.mobile);
    return { ...nameParts(user), designation: user.designation ?? "", alias: user.alias ?? "", code: mobile.code, number: mobile.number };
  });
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState(null);
  const set = (field) => (event) => setForm((prev) => ({ ...prev, [field]: event.target.value }));
  const codes = COUNTRY_CODES.includes(form.code) ? COUNTRY_CODES : [form.code, ...COUNTRY_CODES];

  const save = async () => {
    if (!form.first_name.trim()) {
      setError("Enter the first name.");
      return;
    }
    const number = form.number.replace(/\D/g, "");
    setSaving(true);
    setError(null);
    try {
      const response = await adminApi(`/admin/manage-admins/${user.id}`, {
        method: "PATCH",
        body: JSON.stringify({
          first_name: form.first_name,
          middle_name: form.middle_name,
          last_name: form.last_name,
          designation: form.designation,
          alias: form.alias,
          mobile: number ? `${form.code} ${number}` : null,
        }),
      });
      onSaved(response.user, response.message);
    } catch (e) {
      setError(e instanceof Error ? e.message : "Failed to update the user.");
      setSaving(false);
    }
  };

  return (
    <Dialog open onClose={() => !saving && onClose()} maxWidth="sm" fullWidth>
      <DialogTitle>Edit User</DialogTitle>
      <DialogContent dividers>
        <Stack spacing={2}>
          {error && <Alert severity="error">{error}</Alert>}
          <Box>
            <Typography variant="subtitle2" fontWeight={700} sx={{ mb: 1 }}>
              Name *
            </Typography>
            <Stack direction={{ xs: "column", sm: "row" }} spacing={1}>
              <TextField size="small" placeholder="First Name" value={form.first_name} onChange={set("first_name")} fullWidth inputProps={{ maxLength: 100, "aria-label": "First Name" }} />
              <TextField size="small" placeholder="Middle Name" value={form.middle_name} onChange={set("middle_name")} fullWidth inputProps={{ maxLength: 100, "aria-label": "Middle Name" }} />
              <TextField size="small" placeholder="Last Name" value={form.last_name} onChange={set("last_name")} fullWidth inputProps={{ maxLength: 100, "aria-label": "Last Name" }} />
            </Stack>
          </Box>
          <TextField size="small" label="Alias" placeholder="user name alias" value={form.alias} onChange={set("alias")} inputProps={{ maxLength: 100 }} />
          <Stack direction="row" spacing={1}>
            <TextField select size="small" label="Mobile" value={form.code} onChange={set("code")} sx={{ width: 110, flexShrink: 0 }}>
              {codes.map((code) => (
                <MenuItem key={code} value={code}>
                  {code}
                </MenuItem>
              ))}
            </TextField>
            <TextField size="small" placeholder="Mobile number" value={form.number} onChange={set("number")} fullWidth inputProps={{ inputMode: "numeric", maxLength: 14, "aria-label": "Mobile number" }} />
          </Stack>
          <TextField size="small" label="Email ID" value={user.email} disabled helperText="The Email ID is the login and cannot be changed." />
          <TextField size="small" label="Designation" value={form.designation} onChange={set("designation")} inputProps={{ maxLength: 150 }} />
        </Stack>
      </DialogContent>
      <DialogActions>
        <Button onClick={onClose} disabled={saving}>
          Cancel
        </Button>
        <Button variant="contained" onClick={save} disabled={saving}>
          {saving ? "Saving..." : "Edit User"}
        </Button>
      </DialogActions>
    </Dialog>
  );
}

// Users directory (S8.2): search, master-detail card, Edit, + Add User and Delete this User. Super admins only.
export default function UsersDirectory() {
  const { data: session, status } = useSession();
  const isSuper = Boolean(session?.user?.isSuperAdmin);
  const [users, setUsers] = useState(null);
  const [search, setSearch] = useState("");
  const [selectedId, setSelectedId] = useState(null);
  const [editing, setEditing] = useState(null);
  const [adding, setAdding] = useState(false);
  const [error, setError] = useState(null);
  const [success, setSuccess] = useState(null);

  const load = () =>
    adminApi("/admin/manage-admins")
      .then((response) => {
        setUsers(response.admins ?? []);
        setError(null);
      })
      .catch((e) => setError(e instanceof Error ? e.message : "Failed to load users."));

  useEffect(() => {
    if (status !== "authenticated" || !isSuper) return;
    adminApi("/admin/manage-admins")
      .then((response) => setUsers(response.admins ?? []))
      .catch((e) => setError(e instanceof Error ? e.message : "Failed to load users."));
  }, [status, isSuper]);

  const filtered = useMemo(() => {
    const term = search.trim().toLowerCase();
    return (users ?? []).filter((u) => !term || `${u.name} ${u.email} ${u.designation ?? ""} ${u.alias ?? ""}`.toLowerCase().includes(term));
  }, [users, search]);

  const selected = (users ?? []).find((u) => u.id === selectedId) ?? filtered[0] ?? null;

  const handleDelete = async (user) => {
    if (!window.confirm(`Delete ${user.name}? They will no longer be able to sign in.`)) return;
    try {
      setError(null);
      await adminApi(`/admin/manage-admins/${user.id}`, { method: "DELETE" });
      setSuccess("User deleted successfully.");
      setSelectedId(null);
      setUsers((prev) => (prev ?? []).filter((u) => u.id !== user.id));
    } catch (e) {
      setError(e instanceof Error ? e.message : "Failed to delete user.");
    }
  };

  if (status === "loading") return <LinearProgress />;
  if (!isSuper) {
    return <Alert severity="info">Only a super admin can view and manage users.</Alert>;
  }

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

      <Box sx={{ display: "grid", gap: 2, gridTemplateColumns: { xs: "1fr", md: "minmax(260px, 2fr) 3fr" }, alignItems: "start" }}>
        <Paper variant="outlined" sx={{ p: 1.5, minWidth: 0 }}>
          <Stack direction="row" spacing={1} alignItems="center" sx={{ mb: 1.5 }}>
            <TextField
              size="small"
              fullWidth
              placeholder="Search by name or email…"
              value={search}
              onChange={(event) => setSearch(event.target.value)}
              slotProps={{ input: { startAdornment: <InputAdornment position="start"><SearchIcon fontSize="small" /></InputAdornment> } }}
            />
            <Button variant="contained" startIcon={<PersonAddIcon />} onClick={() => setAdding(true)} sx={{ whiteSpace: "nowrap", flexShrink: 0 }}>
              + Add User
            </Button>
          </Stack>
          {!users ? (
            <LinearProgress />
          ) : filtered.length === 0 ? (
            <Typography color="text.secondary" sx={{ p: 2, textAlign: "center" }}>
              No users found.
            </Typography>
          ) : (
            <List dense disablePadding sx={{ maxHeight: { md: 520 }, overflowY: "auto" }}>
              {filtered.map((user) => (
                <ListItemButton key={user.id} selected={selected?.id === user.id} onClick={() => setSelectedId(user.id)} sx={{ borderRadius: 1 }}>
                  <ListItemAvatar sx={{ minWidth: 48 }}>
                    <Avatar sx={{ width: 36, height: 36, fontSize: "0.85rem", bgcolor: "primary.main" }}>{initials(user.name)}</Avatar>
                  </ListItemAvatar>
                  <ListItemText
                    primary={user.name}
                    secondary={[user.designation, user.email].filter(Boolean).join(" · ")}
                    primaryTypographyProps={{ fontWeight: 600, noWrap: true }}
                    secondaryTypographyProps={{ noWrap: true }}
                  />
                </ListItemButton>
              ))}
            </List>
          )}
        </Paper>

        {selected ? (
          <Card variant="outlined" sx={{ minWidth: 0 }}>
            <CardContent>
              <Stack direction="row" spacing={2} alignItems="center" sx={{ mb: 2, minWidth: 0 }}>
                <Avatar sx={{ width: 56, height: 56, bgcolor: "primary.main", fontWeight: 700 }}>{initials(selected.name)}</Avatar>
                <Box sx={{ minWidth: 0 }}>
                  <Typography variant="h6" fontWeight={700} sx={{ overflowWrap: "anywhere" }}>
                    {selected.name}
                  </Typography>
                  {selected.designation && <Typography color="text.secondary">{selected.designation}</Typography>}
                  <Typography variant="body2" color="text.secondary" sx={{ overflowWrap: "anywhere" }}>
                    {selected.email}
                  </Typography>
                </Box>
              </Stack>
              <Stack direction="row" spacing={1} flexWrap="wrap" useFlexGap sx={{ mb: 2 }}>
                <Chip size="small" color={selected.is_super_admin ? "primary" : "default"} label={selected.is_super_admin ? "Super Admin" : "Admin"} />
                {selected.is_active === false && <Chip size="small" color="warning" label="Inactive" />}
              </Stack>
              <Box sx={{ display: "grid", gridTemplateColumns: { xs: "1fr", sm: "140px 1fr" }, rowGap: 0.75, columnGap: 2, mb: 2 }}>
                {[
                  ["Mobile", selected.mobile],
                  ["Alias", selected.alias],
                  ["Email ID", selected.email],
                ].map(([label, value]) => (
                  <Box key={label} sx={{ display: "contents" }}>
                    <Typography variant="body2" color="text.secondary">
                      {label}
                    </Typography>
                    <Typography variant="body2" sx={{ overflowWrap: "anywhere" }}>
                      {value || "—"}
                    </Typography>
                  </Box>
                ))}
              </Box>
              <Stack direction="row" spacing={1} flexWrap="wrap" useFlexGap>
                <Button variant="outlined" startIcon={<EditIcon />} onClick={() => setEditing(selected)}>
                  Edit
                </Button>
                {!selected.is_super_admin && String(selected.id) !== String(session?.user?.id) && (
                  <Button color="error" variant="outlined" startIcon={<DeleteIcon />} onClick={() => handleDelete(selected)}>
                    Delete this User
                  </Button>
                )}
              </Stack>
              <Typography variant="body2" color="text.secondary" sx={{ mt: 2 }}>
                Every admin has access to everything; only super admins manage users.
              </Typography>
            </CardContent>
          </Card>
        ) : (
          users && (
            <Paper variant="outlined" sx={{ p: 4, textAlign: "center" }}>
              <Typography color="text.secondary">Select a user to view their details</Typography>
            </Paper>
          )
        )}
      </Box>

      {editing && (
        <EditUserDialog
          key={editing.id}
          user={editing}
          onClose={() => setEditing(null)}
          onSaved={(user, message) => {
            setEditing(null);
            setSuccess(message ?? "User updated.");
            setUsers((prev) => (prev ?? []).map((u) => (u.id === user.id ? user : u)));
          }}
        />
      )}

      <AddAdminModal
        open={adding}
        onClose={() => setAdding(false)}
        onSuccess={() => {
          setAdding(false);
          setSuccess("User created successfully. An invitation email has been sent.");
          void load();
        }}
      />
    </Box>
  );
}
