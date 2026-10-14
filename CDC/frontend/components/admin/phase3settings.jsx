"use client";

import { useState } from "react";
import Link from "next/link";
import { useSession } from "next-auth/react";
import {
  Alert,
  Box,
  Button,
  Checkbox,
  FormControl,
  FormControlLabel,
  FormGroup,
  InputLabel,
  MenuItem,
  Radio,
  RadioGroup,
  Select,
  Stack,
  Switch,
  TextField,
  Typography,
} from "@mui/material";
import { adminApi } from "@/lib/adminapi";

// Phase 3 settings sections of the Admin hub (P0.3, S22): Reminders, Rules, Login log, MIS API, and the sender-pool
// choice shown inside the Mail tab. Each section saves only its own keys through PATCH /admin/settings.

async function saveSettings(payload, onSaved, onError, setBusy) {
  setBusy(true);
  try {
    const response = await adminApi("/admin/settings", { method: "PATCH", body: JSON.stringify(payload) });
    onSaved(response.settings, response.message, response);
  } catch (e) {
    onError(e instanceof Error ? e.message : "Failed to save settings.");
  } finally {
    setBusy(false);
  }
}

function SectionTitle({ title, children }) {
  return (
    <>
      <Typography variant="h6" fontWeight={700}>
        {title}
      </Typography>
      <Typography variant="body2" color="text.secondary" sx={{ mb: 2 }}>
        {children}
      </Typography>
    </>
  );
}

function SaveRow({ onSave, disabled }) {
  return (
    <Stack direction="row" sx={{ mt: 3 }}>
      <Button variant="contained" onClick={onSave} disabled={disabled}>
        Save
      </Button>
    </Stack>
  );
}

const wholeNumber = (text, min, max) => {
  const value = Number(text);
  return String(text).trim() !== "" && Number.isInteger(value) && value >= min && value <= max;
};

export function RemindersTab({ settings, onSaved, onError }) {
  const [enabled, setEnabled] = useState(Boolean(settings["reminders.enabled"]));
  const [channels, setChannels] = useState(settings["reminders.channels"] ?? []);
  const [days, setDays] = useState(String(settings["reminders.daily_from_days"] ?? 3));
  const [every, setEvery] = useState(String(settings["reminders.final_hours_interval"] ?? 6));
  const [quietStart, setQuietStart] = useState(settings["reminders.quiet_start"] ?? "23:00");
  const [quietEnd, setQuietEnd] = useState(settings["reminders.quiet_end"] ?? "07:00");
  const [busy, setBusy] = useState(false);

  const daysValid = wholeNumber(days, 0, 30);
  const everyValid = wholeNumber(every, 1, 24);
  const toggleChannel = (channel) => setChannels((prev) => (prev.includes(channel) ? prev.filter((c) => c !== channel) : [...prev, channel]));

  const save = () =>
    saveSettings(
      {
        reminders: {
          enabled,
          channels: ["app", "email"].filter((c) => channels.includes(c)),
          daily_from_days: Number(days),
          final_hours_interval: Number(every),
          quiet_start: quietStart,
          quiet_end: quietEnd,
        },
      },
      onSaved,
      onError,
      setBusy,
    );

  return (
    <>
      <SectionTitle title="Deadline reminders">
        Students who are eligible for an open job profile and have not applied are reminded before its application deadline.
      </SectionTitle>
      <Alert severity="info" sx={{ mb: 2 }}>
        Reminders are not sent yet. What you save here is used as soon as deadline reminders are released.
      </Alert>

      <FormControlLabel control={<Switch checked={enabled} onChange={(e) => setEnabled(e.target.checked)} />} label="Send deadline reminders" />

      <Typography variant="subtitle2" sx={{ mt: 2 }}>
        Where the reminder appears
      </Typography>
      <FormGroup row>
        <FormControlLabel control={<Checkbox checked={channels.includes("app")} onChange={() => toggleChannel("app")} />} label="In the portal" />
        <FormControlLabel control={<Checkbox checked={channels.includes("email")} onChange={() => toggleChannel("email")} />} label="By email" />
      </FormGroup>

      <Stack direction={{ xs: "column", sm: "row" }} spacing={2} sx={{ mt: 2 }}>
        <TextField
          label="Daily reminders start (days before)"
          type="number"
          size="small"
          value={days}
          onChange={(e) => setDays(e.target.value)}
          error={!daysValid}
          helperText={daysValid ? "One a day at 09:00 IST. 0 turns the daily reminders off." : "Enter a whole number from 0 to 30."}
          slotProps={{ htmlInput: { min: 0, max: 30, step: 1 } }}
          sx={{ width: { xs: "100%", sm: 300 } }}
        />
        <TextField
          label="In the last 24 hours, every (hours)"
          type="number"
          size="small"
          value={every}
          onChange={(e) => setEvery(e.target.value)}
          error={!everyValid}
          helperText={everyValid ? "For example 6 = four reminders on the last day." : "Enter a whole number from 1 to 24."}
          slotProps={{ htmlInput: { min: 1, max: 24, step: 1 } }}
          sx={{ width: { xs: "100%", sm: 300 } }}
        />
      </Stack>

      <Typography variant="subtitle2" sx={{ mt: 2 }}>
        Quiet hours (IST)
      </Typography>
      <Typography variant="body2" color="text.secondary" sx={{ mb: 1.5 }}>
        A reminder that falls in quiet hours waits until they end.
      </Typography>
      <Stack direction="row" spacing={2}>
        <TextField label="From" type="time" size="small" value={quietStart} onChange={(e) => setQuietStart(e.target.value)} slotProps={{ inputLabel: { shrink: true } }} />
        <TextField label="To" type="time" size="small" value={quietEnd} onChange={(e) => setQuietEnd(e.target.value)} slotProps={{ inputLabel: { shrink: true } }} />
      </Stack>

      <SaveRow onSave={save} disabled={busy || !daysValid || !everyValid || !quietStart || !quietEnd} />
    </>
  );
}

export function RulesTab({ settings, onSaved, onError }) {
  const [oneEach, setOneEach] = useState(Boolean(settings["rules.one_intern_one_placement"]));
  const [ppoDecline, setPpoDecline] = useState(Boolean(settings["rules.ppo_decline_exclusion"]));
  const [busy, setBusy] = useState(false);

  const save = () =>
    saveSettings({ rules: { one_intern_one_placement: oneEach, ppo_decline_exclusion: ppoDecline } }, onSaved, onError, setBusy);

  const rule = (checked, onChange, title, detail) => (
    <FormControlLabel
      sx={{ alignItems: "flex-start", mb: 2 }}
      control={<Switch checked={checked} onChange={(e) => onChange(e.target.checked)} />}
      label={
        <Box sx={{ pt: 0.75 }}>
          <Typography fontWeight={600}>{title}</Typography>
          <Typography variant="body2" color="text.secondary">
            {detail}
          </Typography>
        </Box>
      }
    />
  );

  return (
    <>
      <SectionTitle title="Offer rules">Rules that decide who can apply, across every placement.</SectionTitle>
      <Alert severity="info" sx={{ mb: 2 }}>
        These rules do not change eligibility yet. What you save here is used as soon as the new eligibility rules are released.
      </Alert>
      <Stack>
        {rule(
          oneEach,
          setOneEach,
          "One internship and one placement per student",
          "A student who holds an internship offer cannot apply for another internship, and a student who holds a placement offer cannot apply for another placement. An admin can lift this for one student.",
        )}
        {rule(
          ppoDecline,
          setPpoDecline,
          "A declined PPO closes that company to the student",
          "When the CDC records that a student declined a PPO, the student is no longer eligible for that company's later job profiles.",
        )}
      </Stack>
      <SaveRow onSave={save} disabled={busy} />
    </>
  );
}

export function LoginLogTab({ settings, onSaved, onError }) {
  const [days, setDays] = useState(String(settings["login_logs.retention_days"] ?? 180));
  const [busy, setBusy] = useState(false);
  const valid = wholeNumber(days, 30, 3650);

  const save = () => saveSettings({ login_logs: { retention_days: Number(days) } }, onSaved, onError, setBusy);

  return (
    <>
      <SectionTitle title="Login log">
        Every sign-in attempt is recorded with its time, IP address and result. <Link href="/admin/login-logs">Open the login log</Link>
      </SectionTitle>
      <TextField
        label="Keep records for (days)"
        type="number"
        size="small"
        value={days}
        onChange={(e) => setDays(e.target.value)}
        error={!valid}
        helperText={valid ? "Older records are deleted every night." : "Enter a whole number from 30 to 3650."}
        slotProps={{ htmlInput: { min: 30, max: 3650, step: 1 } }}
        sx={{ width: { xs: "100%", sm: 260 } }}
      />
      <SaveRow onSave={save} disabled={busy || !valid || Number(days) === settings["login_logs.retention_days"]} />
    </>
  );
}

export function MisApiTab({ settings, secretSet, onSaved, onError }) {
  const { data: session } = useSession();
  const isSuper = Boolean(session?.user?.isSuperAdmin);
  const [baseUrl, setBaseUrl] = useState(settings["mis.api.base_url"] ?? "");
  const [authType, setAuthType] = useState(settings["mis.api.auth_type"] ?? "none");
  const [secret, setSecret] = useState("");
  const [fieldMap, setFieldMap] = useState(JSON.stringify(settings["mis.api.field_map"] ?? {}, null, 2).replace(/^\[\]$/, "{}"));
  const [busy, setBusy] = useState(false);

  let parsedMap = null;
  try {
    const candidate = JSON.parse(fieldMap || "{}");
    if (candidate && typeof candidate === "object" && !Array.isArray(candidate)) parsedMap = candidate;
  } catch {
    parsedMap = null;
  }
  const urlValid = baseUrl.trim() === "" || /^https:\/\/\S+$/i.test(baseUrl.trim());

  const save = () =>
    saveSettings(
      {
        mis: {
          api: {
            base_url: baseUrl.trim() === "" ? null : baseUrl.trim(),
            auth_type: authType,
            field_map: parsedMap,
            ...(secret !== "" ? { secret } : {}),
          },
        },
      },
      (next, message, response) => {
        setSecret("");
        onSaved(next, message, response);
      },
      onError,
      setBusy,
    );

  const removeSecret = () => saveSettings({ mis: { api: { secret: null } } }, onSaved, onError, setBusy);

  return (
    <>
      <SectionTitle title="MIS API connection">Where the portal will fetch student records from the institute&apos;s MIS.</SectionTitle>
      <Alert severity="info" sx={{ mb: 2 }}>
        Sync from the MIS API is not available yet: it needs the institute&apos;s MIS API documentation. You can store the connection here; nothing is
        sent to this address until the sync is released and switched on. Until then, student data comes in through file upload.
      </Alert>
      {!isSuper && (
        <Alert severity="warning" sx={{ mb: 2 }}>
          Only a super admin can change the MIS API connection.
        </Alert>
      )}

      <FormControlLabel control={<Switch checked={Boolean(settings["mis.api.enabled"])} disabled />} label="Sync from the MIS API (not available yet)" />

      <Stack spacing={2} sx={{ mt: 2, maxWidth: 640 }}>
        <TextField
          label="Base URL"
          size="small"
          value={baseUrl}
          onChange={(e) => setBaseUrl(e.target.value)}
          disabled={!isSuper}
          error={!urlValid}
          helperText={urlValid ? "Must start with https://" : "Enter an https:// address."}
          placeholder="https://mis.example.ac.in/api"
        />
        <FormControl size="small" disabled={!isSuper}>
          <InputLabel id="mis-auth-type">Sign-in method</InputLabel>
          <Select labelId="mis-auth-type" label="Sign-in method" value={authType} onChange={(e) => setAuthType(e.target.value)}>
            <MenuItem value="none">None</MenuItem>
            <MenuItem value="bearer">Bearer token</MenuItem>
            <MenuItem value="basic">Username and password (Basic)</MenuItem>
          </Select>
        </FormControl>
        <Box>
          <TextField
            label={secretSet ? "Replace the stored secret" : "Secret (token or user:password)"}
            type="password"
            size="small"
            fullWidth
            value={secret}
            onChange={(e) => setSecret(e.target.value)}
            disabled={!isSuper}
            autoComplete="new-password"
            helperText={secretSet ? "A secret is stored. It is never shown again; type a new one to replace it." : "Stored encrypted. It is never shown again after saving."}
          />
          {secretSet && isSuper && (
            <Button size="small" color="error" onClick={removeSecret} disabled={busy} sx={{ mt: 0.5 }}>
              Remove the stored secret
            </Button>
          )}
        </Box>
        <TextField
          label="Field map (JSON)"
          multiline
          minRows={4}
          value={fieldMap}
          onChange={(e) => setFieldMap(e.target.value)}
          disabled={!isSuper}
          error={parsedMap === null}
          helperText={parsedMap === null ? 'Enter a JSON object, for example {"admission_no": "roll_no"}.' : "MIS field name on the left, portal field name on the right."}
          slotProps={{ htmlInput: { sx: { fontFamily: "monospace", fontSize: 13 } } }}
        />
      </Stack>

      <SaveRow onSave={save} disabled={busy || !isSuper || !urlValid || parsedMap === null} />
    </>
  );
}

// Shown inside the Mail tab, saved with its own button.
export function SenderPoolSection({ settings, onSaved, onError }) {
  const [strategy, setStrategy] = useState(settings["mail.pool_strategy"] ?? "round_robin");
  const [busy, setBusy] = useState(false);

  const save = () => saveSettings({ mail: { pool_strategy: strategy } }, onSaved, onError, setBusy);

  const option = (value, title, detail) => (
    <FormControlLabel
      value={value}
      control={<Radio />}
      label={
        <Box>
          <Typography fontWeight={600}>{title}</Typography>
          <Typography variant="body2" color="text.secondary">
            {detail}
          </Typography>
        </Box>
      }
    />
  );

  return (
    <Box sx={{ mt: 4 }}>
      <SectionTitle title="Sender mailboxes">
        How mail is shared across the sending mailboxes. This has no effect until sender mailboxes are added; the daily recipient limit above is the
        total for all of them.
      </SectionTitle>
      <FormControl>
        <RadioGroup value={strategy} onChange={(e) => setStrategy(e.target.value)}>
          {option("round_robin", "Take turns (round robin)", "Each message goes out from the next mailbox that still has room in its own daily limit.")}
          {option(
            "group_list",
            "Use a group address when there is one",
            "A message for exactly one student group that has its own list address is sent once, to that address. Everything else takes turns.",
          )}
        </RadioGroup>
      </FormControl>
      <Stack direction="row" sx={{ mt: 1 }}>
        <Button variant="outlined" onClick={save} disabled={busy || strategy === settings["mail.pool_strategy"]}>
          Save sender choice
        </Button>
      </Stack>
    </Box>
  );
}
