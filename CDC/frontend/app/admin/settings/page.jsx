"use client";

import { useEffect, useState } from "react";
import {
  Alert,
  Box,
  Button,
  Card,
  CardContent,
  FormControl,
  FormControlLabel,
  LinearProgress,
  Radio,
  RadioGroup,
  Stack,
  Typography,
} from "@mui/material";
import SettingsIcon from "@mui/icons-material/Settings";

import PageHeader from "@/components/shared/pageheader";
import { adminApi } from "@/lib/adminapi";

export default function AdminSettingsPage() {
  const [settings, setSettings] = useState(null);
  const [mailMode, setMailMode] = useState("queued");
  const [error, setError] = useState(null);
  const [success, setSuccess] = useState(null);
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    adminApi("/admin/settings")
      .then((response) => {
        setSettings(response.settings);
        setMailMode(response.settings?.mail_mode ?? "queued");
      })
      .catch((e) => setError(e.message));
  }, []);

  const save = async () => {
    setBusy(true);
    setError(null);
    try {
      const response = await adminApi("/admin/settings", { method: "PATCH", body: JSON.stringify({ mail_mode: mailMode }) });
      setSettings(response.settings);
      setSuccess(response.message);
    } catch (e) {
      setError(e instanceof Error ? e.message : "Failed to save settings.");
    } finally {
      setBusy(false);
    }
  };

  return (
    <>
      <PageHeader icon={<SettingsIcon />} title="Portal Settings" subtitle="Every change is recorded in the audit log." backHref="/admin" backLabel="Back to Dashboard" />
      {error && (
        <Alert severity="error" sx={{ mb: 2 }}>
          {error}
        </Alert>
      )}
      {success && (
        <Alert severity="success" sx={{ mb: 2 }} onClose={() => setSuccess(null)}>
          {success}
        </Alert>
      )}
      {!settings ? (
        <LinearProgress />
      ) : (
        <Card>
          <CardContent>
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
            <Stack direction="row" sx={{ mt: 2 }}>
              <Button variant="contained" onClick={save} disabled={busy || mailMode === settings.mail_mode}>
                Save
              </Button>
            </Stack>
          </CardContent>
        </Card>
      )}
    </>
  );
}
