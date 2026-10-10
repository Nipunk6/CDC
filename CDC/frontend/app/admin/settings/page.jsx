"use client";

import { Suspense, useEffect, useRef, useState } from "react";
import { useRouter, useSearchParams } from "next/navigation";
import {
  Alert,
  Box,
  Button,
  Card,
  CardContent,
  FormControl,
  FormControlLabel,
  LinearProgress,
  Paper,
  Radio,
  RadioGroup,
  Stack,
  Tab,
  Tabs,
  TextField,
  Typography,
} from "@mui/material";
import SettingsIcon from "@mui/icons-material/Settings";
import CloudUploadIcon from "@mui/icons-material/CloudUploadOutlined";

import PageHeader from "@/components/shared/pageheader";
import BrandLogo from "@/components/shared/brandlogo";
import UsersDirectory from "@/components/admin/usersdirectory";
import BranchManager from "@/components/admin/branchmanager";
import ExcelTemplateLibrary from "@/components/admin/exceltemplatelibrary";
import { adminApi } from "@/lib/adminapi";
import { adminUpload } from "@/lib/adminupload";
import { notifyBrandingUpdated, useBranding } from "@/lib/branding";

// The Admin hub (Superset parity S8.1): one page, a left vertical tab list (top tabs on phones).
const TABS = [
  { key: "account", label: "Account" },
  { key: "users", label: "Users" },
  { key: "mail", label: "Mail" },
  { key: "branch-manager", label: "Branch Manager" },
  { key: "excel-templates", label: "Excel Templates" },
];

const LOGO_TYPES = ["image/png", "image/jpeg", "image/webp"];
const LOGO_MAX_BYTES = 1024 * 1024;

function AccountTab({ initialName, onMessage, onError }) {
  const branding = useBranding();
  const [name, setName] = useState(initialName ?? "");
  const [savedName, setSavedName] = useState(initialName ?? "");
  const [busy, setBusy] = useState(false);
  const fileInput = useRef(null);

  const saveName = async () => {
    setBusy(true);
    try {
      const response = await adminApi("/admin/settings", { method: "PATCH", body: JSON.stringify({ institute_name: name.trim() || null }) });
      const stored = response.branding?.display_name ?? "";
      setName(stored);
      setSavedName(stored);
      notifyBrandingUpdated();
      onMessage(response.message);
    } catch (e) {
      onError(e instanceof Error ? e.message : "Failed to save the institute name.");
    } finally {
      setBusy(false);
    }
  };

  const upload = async (file) => {
    if (!file) return;
    if (!LOGO_TYPES.includes(file.type)) {
      onError("The logo must be a PNG, JPG or WebP image.");
      return;
    }
    if (file.size > LOGO_MAX_BYTES) {
      onError("The logo must be 1 MB or smaller.");
      return;
    }
    const body = new FormData();
    body.append("logo", file);
    setBusy(true);
    try {
      const response = await adminUpload("/admin/settings/logo", body);
      notifyBrandingUpdated();
      onMessage(response.message);
    } catch (e) {
      onError(e instanceof Error ? e.message : "Failed to upload the logo.");
    } finally {
      setBusy(false);
      if (fileInput.current) fileInput.current.value = "";
    }
  };

  const removeLogo = async () => {
    if (!window.confirm("Remove the account logo? The portal goes back to the built-in badge.")) return;
    setBusy(true);
    try {
      const response = await adminApi("/admin/settings/logo", { method: "DELETE" });
      notifyBrandingUpdated();
      onMessage(response.message);
    } catch (e) {
      onError(e instanceof Error ? e.message : "Failed to remove the logo.");
    } finally {
      setBusy(false);
    }
  };

  return (
    <Stack spacing={3}>
      <Typography variant="h6" fontWeight={700} sx={{ overflowWrap: "anywhere" }}>
        {savedName || "IIT (ISM) Dhanbad"}
      </Typography>

      <Box>
        <Typography variant="subtitle1" fontWeight={700} sx={{ mb: 1 }}>
          Account Logo
        </Typography>
        <Box
          component="button"
          type="button"
          onClick={() => fileInput.current?.click()}
          disabled={busy}
          aria-label="Change Logo"
          sx={{
            position: "relative",
            width: 160,
            height: 160,
            p: 1.5,
            border: "1px solid",
            borderColor: "divider",
            borderRadius: 2,
            bgcolor: "background.paper",
            cursor: "pointer",
            display: "flex",
            alignItems: "center",
            justifyContent: "center",
            "&:hover .logo-overlay, &:focus-visible .logo-overlay": { opacity: 1 },
          }}
        >
          <BrandLogo branding={branding} size={132} alt="Account logo" />
          <Box
            className="logo-overlay"
            sx={{
              position: "absolute",
              inset: 0,
              borderRadius: 2,
              bgcolor: "rgba(0,0,0,0.45)",
              color: "common.white",
              display: "flex",
              flexDirection: "column",
              alignItems: "center",
              justifyContent: "center",
              gap: 0.5,
              opacity: 0,
              transition: "opacity 120ms",
            }}
          >
            <CloudUploadIcon />
            <Typography variant="body2" fontWeight={600}>
              Change Logo
            </Typography>
          </Box>
        </Box>
        <input ref={fileInput} type="file" accept=".png,.jpg,.jpeg,.webp,image/png,image/jpeg,image/webp" hidden onChange={(event) => upload(event.target.files?.[0])} />
        <Typography variant="body2" color="text.secondary" sx={{ mt: 1 }}>
          Click on the image above to upload a new account logo
        </Typography>
        <Typography variant="body2" color="text.secondary">
          The logo should preferably be a square image for best fit and look across interfaces.
        </Typography>
        <Typography variant="caption" color="text.secondary" component="div" sx={{ mt: 0.5 }}>
          PNG, JPG or WebP, up to 1 MB. It appears in the admin, student and company portals and in portal emails.
        </Typography>
        {branding?.logo_url && (
          <Button size="small" color="error" onClick={removeLogo} disabled={busy} sx={{ mt: 1 }}>
            Remove logo
          </Button>
        )}
      </Box>

      <Box sx={{ maxWidth: 520 }}>
        <Typography variant="subtitle1" fontWeight={700} sx={{ mb: 1 }}>
          Institute display name
        </Typography>
        <Stack direction={{ xs: "column", sm: "row" }} spacing={1} alignItems={{ sm: "flex-start" }}>
          <TextField
            size="small"
            fullWidth
            value={name}
            onChange={(event) => setName(event.target.value)}
            placeholder="IIT (ISM) Dhanbad"
            helperText="Shown in the portal headers and emails. Leave blank to use the built-in name."
            inputProps={{ maxLength: 150 }}
          />
          <Button variant="contained" onClick={saveName} disabled={busy || name.trim() === savedName.trim()}>
            Save
          </Button>
        </Stack>
      </Box>
      {busy && <LinearProgress />}
    </Stack>
  );
}

function MailTab({ settings, quota, onSaved, onError }) {
  const [mailMode, setMailMode] = useState(settings?.mail_mode ?? "queued");
  const savedCap = String(settings?.mail_daily_recipient_cap ?? "");
  const [cap, setCap] = useState(savedCap);
  const [busy, setBusy] = useState(false);

  const capNumber = Number(cap);
  const capValid = cap.trim() !== "" && Number.isInteger(capNumber) && capNumber >= 0 && capNumber <= 100000;
  const changed = mailMode !== settings.mail_mode || cap !== savedCap;

  const save = async () => {
    setBusy(true);
    try {
      const response = await adminApi("/admin/settings", {
        method: "PATCH",
        body: JSON.stringify({ mail_mode: mailMode, mail_daily_recipient_cap: capNumber }),
      });
      onSaved(response.settings, response.message, response.mail_quota);
    } catch (e) {
      onError(e instanceof Error ? e.message : "Failed to save settings.");
    } finally {
      setBusy(false);
    }
  };

  return (
    <>
      <Typography variant="h6" fontWeight={700}>
        Student email delivery
      </Typography>
      <Typography variant="body2" color="text.secondary" sx={{ mb: 2 }}>
        Applies to all Phase 2 student emails (invitations, new openings, results, events).
      </Typography>
      <FormControl>
        <RadioGroup value={mailMode} onChange={(e) => setMailMode(e.target.value)}>
          <FormControlLabel
            value="queued"
            control={<Radio />}
            label={
              <Box>
                <Typography fontWeight={600}>Queued (recommended)</Typography>
                <Typography variant="body2" color="text.secondary">
                  Emails are sent in the background by the queue worker (`php artisan queue:work` must be running).
                </Typography>
              </Box>
            }
          />
          <FormControlLabel
            value="sync"
            control={<Radio />}
            label={
              <Box>
                <Typography fontWeight={600}>Immediate</Typography>
                <Typography variant="body2" color="text.secondary">
                  Emails are sent while the admin waits. Use only when no queue worker is running — large sends will be slow.
                </Typography>
              </Box>
            }
          />
        </RadioGroup>
      </FormControl>

      <Typography variant="h6" fontWeight={700} sx={{ mt: 3 }}>
        Daily recipient limit
      </Typography>
      <Typography variant="body2" color="text.secondary" sx={{ mb: 2 }}>
        Every address on an email counts, including each student in BCC and the portal&apos;s own To address. When
        today&apos;s limit is used up, the remaining emails wait until 00:05 IST the next day; nothing is dropped. Set
        this to the sending mailbox&apos;s daily limit, with some headroom. 0 means no limit.
      </Typography>
      <Stack direction={{ xs: "column", sm: "row" }} spacing={2} alignItems={{ sm: "center" }}>
        <TextField
          label="Recipients per day"
          type="number"
          size="small"
          value={cap}
          onChange={(e) => setCap(e.target.value)}
          error={!capValid}
          helperText={capValid ? " " : "Enter a whole number from 0 to 100000."}
          slotProps={{ htmlInput: { min: 0, max: 100000, step: 1 } }}
          sx={{ width: { xs: "100%", sm: 220 } }}
        />
        {quota && (
          <Paper variant="outlined" sx={{ px: 2, py: 1.5, flex: 1 }}>
            <Typography variant="body2" fontWeight={600}>
              Today ({quota.date}): {quota.used} used
              {quota.cap > 0 ? ` of ${quota.cap} · ${quota.remaining} remaining` : " · no limit"}
            </Typography>
            <Typography variant="body2" color="text.secondary">
              {quota.deferred > 0
                ? `${quota.deferred} recipient(s) are waiting for later days because earlier days were full.`
                : "No emails are waiting for a later day."}
            </Typography>
          </Paper>
        )}
      </Stack>

      <Stack direction="row" sx={{ mt: 2 }}>
        <Button variant="contained" onClick={save} disabled={busy || !changed || !capValid}>
          Save
        </Button>
      </Stack>
    </>
  );
}

function AdminHub() {
  const router = useRouter();
  const params = useSearchParams();
  const requested = params.get("tab");
  const tab = TABS.some((t) => t.key === requested) ? requested : "account";

  const [settings, setSettings] = useState(null);
  const [mailQuota, setMailQuota] = useState(null);
  const [branding, setBranding] = useState(null);
  const [error, setError] = useState(null);
  const [success, setSuccess] = useState(null);

  useEffect(() => {
    adminApi("/admin/settings")
      .then((response) => {
        setSettings(response.settings);
        setMailQuota(response.mail_quota ?? null);
        setBranding(response.branding ?? { display_name: null, has_logo: false });
      })
      .catch((e) => setError(e.message));
  }, []);

  const selectTab = (key) => {
    setError(null);
    setSuccess(null);
    router.replace(`/admin/settings?tab=${key}`, { scroll: false });
  };

  const onMessage = (message) => {
    setError(null);
    setSuccess(message);
  };
  const onError = (message) => {
    setSuccess(null);
    setError(message);
  };

  return (
    <>
      <PageHeader icon={<SettingsIcon />} title="Admin" subtitle="Every change is recorded in the audit log." backHref="/admin" backLabel="Back to Dashboard" />
      <Box sx={{ display: "flex", flexDirection: { xs: "column", md: "row" }, gap: 2, alignItems: "flex-start" }}>
        <Paper variant="outlined" sx={{ width: { xs: "100%", md: 220 }, flexShrink: 0, bgcolor: "grey.50", minWidth: 0 }}>
          <Tabs
            value={tab}
            onChange={(_, key) => selectTab(key)}
            orientation="vertical"
            variant="scrollable"
            aria-label="Admin settings"
            sx={{
              display: { xs: "none", md: "flex" },
              "& .MuiTab-root": { alignItems: "flex-start", textAlign: "left", minHeight: 44, fontWeight: 600 },
              "& .MuiTabs-indicator": { left: 0 },
            }}
          >
            {TABS.map((t) => (
              <Tab key={t.key} value={t.key} label={t.label} />
            ))}
          </Tabs>
          <Tabs
            value={tab}
            onChange={(_, key) => selectTab(key)}
            variant="scrollable"
            scrollButtons="auto"
            allowScrollButtonsMobile
            aria-label="Admin settings"
            sx={{ display: { xs: "flex", md: "none" }, "& .MuiTab-root": { fontWeight: 600 } }}
          >
            {TABS.map((t) => (
              <Tab key={t.key} value={t.key} label={t.label} />
            ))}
          </Tabs>
        </Paper>

        <Box sx={{ flex: 1, minWidth: 0, width: "100%" }}>
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

          {tab === "users" ? (
            <UsersDirectory />
          ) : tab === "branch-manager" ? (
            <BranchManager />
          ) : tab === "excel-templates" ? (
            <ExcelTemplateLibrary showHeader={false} />
          ) : !settings ? (
            <LinearProgress />
          ) : (
            <Card>
              <CardContent>
                {tab === "account" && <AccountTab initialName={branding?.display_name} onMessage={onMessage} onError={onError} />}
                {tab === "mail" && (
                  <MailTab
                    settings={settings}
                    quota={mailQuota}
                    onSaved={(next, message, quota) => {
                      setSettings(next);
                      if (quota) setMailQuota(quota);
                      onMessage(message);
                    }}
                    onError={onError}
                  />
                )}

              </CardContent>
            </Card>
          )}
        </Box>
      </Box>
    </>
  );
}

export default function AdminSettingsPage() {
  return (
    <Suspense fallback={<LinearProgress />}>
      <AdminHub />
    </Suspense>
  );
}
