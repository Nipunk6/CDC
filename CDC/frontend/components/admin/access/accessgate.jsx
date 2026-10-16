"use client";

import { useState } from "react";
import Link from "next/link";
import { usePathname } from "next/navigation";
import { useSession } from "next-auth/react";
import { Alert, Box, Button, Card, CardContent, LinearProgress, MenuItem, Stack, TextField, Typography } from "@mui/material";
import LockIcon from "@mui/icons-material/LockOutlined";

import PageHeader from "@/components/shared/pageheader";
import { adminApi } from "@/lib/adminapi";
import { LEVEL_LABELS, pagePermission, useAccess } from "@/lib/access";
import { adminNav } from "@/lib/nav/admin";

const COUNTRY_CODES = ["+91", "+1", "+44", "+61", "+65", "+971", "+49", "+81"];

// After the invitation: the new staff member fills in their own details before anything else opens.
function CompleteProfile({ onDone }) {
  const { data: session } = useSession();
  const words = (session?.user?.name ?? "").trim().split(/\s+/).filter(Boolean);
  const [form, setForm] = useState({
    first_name: words[0] ?? "",
    middle_name: words.length > 2 ? words.slice(1, -1).join(" ") : "",
    last_name: words.length > 1 ? words[words.length - 1] : "",
    designation: "",
    alias: "",
    code: "+91",
    number: "",
  });
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState(null);
  const set = (field) => (event) => setForm((prev) => ({ ...prev, [field]: event.target.value }));

  const save = async (event) => {
    event.preventDefault();
    const number = form.number.replace(/\D/g, "");
    if (!form.first_name.trim() || !form.designation.trim() || !number) {
      setError("Enter your first name, designation and mobile number.");
      return;
    }
    setBusy(true);
    setError(null);
    try {
      await adminApi("/admin/me/profile", {
        method: "PUT",
        body: JSON.stringify({
          first_name: form.first_name,
          middle_name: form.middle_name,
          last_name: form.last_name,
          designation: form.designation,
          alias: form.alias,
          mobile: `${form.code} ${number}`,
        }),
      });
      await onDone();
    } catch (e) {
      setError(e instanceof Error ? e.message : "Could not save your details.");
      setBusy(false);
    }
  };

  return (
    <Box sx={{ maxWidth: 640 }}>
      <PageHeader title="Complete your profile" subtitle="Welcome to the CDC Admin Portal. Fill in your details once; your access opens right after." />
      <Card variant="outlined">
        <CardContent component="form" onSubmit={save}>
          <Stack spacing={2}>
            {error && <Alert severity="error">{error}</Alert>}
            <Stack direction={{ xs: "column", sm: "row" }} spacing={1}>
              <TextField size="small" label="First name" required value={form.first_name} onChange={set("first_name")} fullWidth slotProps={{ htmlInput: { maxLength: 100 } }} />
              <TextField size="small" label="Middle name" value={form.middle_name} onChange={set("middle_name")} fullWidth slotProps={{ htmlInput: { maxLength: 100 } }} />
              <TextField size="small" label="Last name" value={form.last_name} onChange={set("last_name")} fullWidth slotProps={{ htmlInput: { maxLength: 100 } }} />
            </Stack>
            <TextField size="small" label="Designation" required placeholder="e.g. Faculty-in-Charge, Mining Engineering" value={form.designation} onChange={set("designation")} slotProps={{ htmlInput: { maxLength: 150 } }} />
            <Stack direction="row" spacing={1}>
              <TextField select size="small" label="Code" value={form.code} onChange={set("code")} sx={{ width: 110, flexShrink: 0 }}>
                {COUNTRY_CODES.map((code) => (
                  <MenuItem key={code} value={code}>
                    {code}
                  </MenuItem>
                ))}
              </TextField>
              <TextField size="small" label="Mobile number" required value={form.number} onChange={set("number")} fullWidth slotProps={{ htmlInput: { inputMode: "numeric", maxLength: 14 } }} />
            </Stack>
            <TextField size="small" label="Alias (optional)" helperText="Shown instead of your name in notices to students, if set." value={form.alias} onChange={set("alias")} slotProps={{ htmlInput: { maxLength: 100 } }} />
            <TextField size="small" label="Email ID" value={session?.user?.email ?? ""} disabled helperText="Your login. Ask a super admin to change it." />
            <Stack direction="row" justifyContent="flex-end">
              <Button type="submit" variant="contained" disabled={busy}>
                {busy ? "Saving..." : "Save and continue"}
              </Button>
            </Stack>
          </Stack>
        </CardContent>
      </Card>
    </Box>
  );
}

function YourAccess({ access, catalogue }) {
  const granted = catalogue
    .map((group) => ({ ...group, permissions: group.permissions.filter((p) => (access?.levels?.[p.key] ?? "none") !== "none") }))
    .filter((group) => group.permissions.length > 0);

  if (granted.length === 0) return null;
  return (
    <Card variant="outlined" sx={{ mt: 2 }}>
      <CardContent>
        <Typography variant="subtitle1" fontWeight={700} gutterBottom>
          Your access{access?.role ? ` · ${access.role.name}` : ""}
        </Typography>
        {access?.departments?.length > 0 && (
          <Typography variant="body2" color="text.secondary" sx={{ mb: 1 }}>
            Students of: {access.departments.join(", ")}
          </Typography>
        )}
        <Box sx={{ display: "grid", gridTemplateColumns: { xs: "1fr", sm: "1fr 1fr" }, gap: 0.5 }}>
          {granted.flatMap((group) =>
            group.permissions.map((p) => (
              <Typography key={p.key} variant="body2">
                <strong>{p.label}</strong>: {LEVEL_LABELS[access.levels[p.key]]}
              </Typography>
            ))
          )}
        </Box>
      </CardContent>
    </Card>
  );
}

// Home page for staff without the Dashboard permission: links to the sections they can open.
function StaffHome({ access, catalogue, can }) {
  const sections = adminNav({ isSuperAdmin: false, can });
  const { data: session } = useSession();
  return (
    <Box>
      <PageHeader title={`Welcome, ${session?.user?.name ?? ""}`} subtitle={access?.role ? `Role: ${access.role.name}` : undefined} />
      {!access?.enabled ? (
        <Alert severity="warning">Your access is turned off or you have no role yet. Ask a super admin to give you access.</Alert>
      ) : (
        <Stack direction="row" spacing={1} flexWrap="wrap" useFlexGap>
          {sections.flatMap((section) =>
            section.items.map((item) => (
              <Button key={item.href} component={Link} href={item.href} variant="outlined">
                {item.label}
              </Button>
            ))
          )}
        </Stack>
      )}
      <YourAccess access={access} catalogue={catalogue} />
    </Box>
  );
}

function NoAccess({ permission, catalogue }) {
  const label = catalogue.flatMap((g) => g.permissions).find((p) => p.key === permission)?.label;
  return (
    <Box sx={{ maxWidth: 560, mx: "auto", mt: 6, textAlign: "center" }}>
      <LockIcon color="action" sx={{ fontSize: 48 }} />
      <Typography variant="h6" fontWeight={700} sx={{ mt: 1 }}>
        You don&apos;t have access to this page
      </Typography>
      <Typography color="text.secondary" sx={{ mt: 1 }}>
        {permission === "super" ? "Only a super admin can open it." : `It needs the "${label ?? permission}" permission. Ask a super admin if you need it.`}
      </Typography>
      <Button component={Link} href="/admin" variant="contained" sx={{ mt: 3 }}>
        Go to your home page
      </Button>
    </Box>
  );
}

// Staff roles gate around every admin page: profile first, then the page's permission.
export default function AccessGate({ children }) {
  const pathname = usePathname();
  const { loading, access, catalogue, can, isSuper, reload, error } = useAccess();

  if (loading) return <LinearProgress />;
  if (!access) return <Alert severity="error">{error ?? "Could not load your access."}</Alert>;
  if (!isSuper && access.profile_completed === false) return <CompleteProfile onDone={reload} />;

  const permission = pagePermission(pathname);
  if (can(permission, "read")) return children;
  if (permission === "dashboard") return <StaffHome access={access} catalogue={catalogue} can={can} />;
  return <NoAccess permission={permission} catalogue={catalogue} />;
}
