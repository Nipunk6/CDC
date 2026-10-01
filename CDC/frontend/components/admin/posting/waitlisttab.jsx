"use client";

import { useCallback, useEffect, useMemo, useState } from "react";
import {
  Alert,
  Box,
  Button,
  Chip,
  FormControl,
  InputLabel,
  LinearProgress,
  MenuItem,
  Paper,
  Select,
  Stack,
  Typography,
} from "@mui/material";

import { adminApi } from "@/lib/adminapi";

/**
 * A round's waitlist (spec Q4.4). Waitlists have no order or positions (D90): any published waitlisted candidate
 * can be moved on — promoted to "selected" in THIS round and notified, so the next round decides them like everyone
 * else (QA F-002) — or removed.
 */
export default function WaitlistTab({ posting, onMessage, onChanged }) {
  const [data, setData] = useState(null);
  const [roundId, setRoundId] = useState("");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState(null);

  const load = useCallback(async () => {
    try {
      setData(await adminApi(`/admin/postings/${posting.id}/pipeline`));
    } catch (e) {
      setError(e instanceof Error ? e.message : "Failed to load.");
    }
  }, [posting.id]);

  useEffect(() => {
    void load();
  }, [load]);

  const waitlisted = useMemo(() => {
    if (!data || !roundId) return [];
    return data.applications
      .filter((a) => a.results?.[roundId]?.result === "waitlisted")
      .sort((x, y) => String(x.student?.roll_no).localeCompare(String(y.student?.roll_no)));
  }, [data, roundId]);

  const rounds = [...(data?.rounds ?? [])].sort((x, y) => x.sort_order - y.sort_order);
  const roundsWithWaitlist = rounds.filter((r) => data.applications.some((a) => a.results?.[r.id]?.result === "waitlisted"));
  const nextRound = rounds[rounds.findIndex((r) => String(r.id) === roundId) + 1] ?? null;

  const run = async (request, fallback) => {
    setBusy(true);
    setError(null);
    try {
      const response = await request();
      onMessage?.(response.message);
      await load();
      onChanged?.();
    } catch (e) {
      setError(e instanceof Error ? e.message : fallback);
    } finally {
      setBusy(false);
    }
  };

  const moveOn = (application) => {
    if (!window.confirm(`Move ${application.student?.roll_no} off the waitlist into ${nextRound.name}? The student is told straight away.`)) return;
    void run(
      () => adminApi(`/admin/postings/${posting.id}/rounds/${roundId}/waitlist/${application.id}/promote`, { method: "POST" }),
      "Failed to move the candidate."
    );
  };

  const remove = (application) => {
    if (!window.confirm(`Remove ${application.student?.roll_no} from the waitlist? If the waitlist was published they are told they were not selected.`)) return;
    void run(() => adminApi(`/admin/postings/${posting.id}/rounds/${roundId}/waitlist/${application.id}`, { method: "DELETE" }), "Failed to remove.");
  };

  if (!data) return error ? <Alert severity="error">{error}</Alert> : <LinearProgress />;

  return (
    <Stack spacing={2}>
      {error && <Alert severity="error">{error}</Alert>}
      {roundsWithWaitlist.length === 0 ? (
        <Typography color="text.secondary">No round has waitlisted candidates yet. Mark candidates as waitlisted from the Pipeline tab.</Typography>
      ) : (
        <FormControl size="small" sx={{ maxWidth: 320 }}>
          <InputLabel id="wl-round">Round</InputLabel>
          <Select labelId="wl-round" label="Round" value={roundId} onChange={(e) => setRoundId(e.target.value)}>
            {roundsWithWaitlist.map((r) => (
              <MenuItem key={r.id} value={String(r.id)}>
                {r.name}
              </MenuItem>
            ))}
          </Select>
        </FormControl>
      )}

      {roundId && (
        <>
          <Typography variant="body2" color="text.secondary">
            The waitlist has no order — move any candidate on{nextRound ? ` to ${nextRound.name}` : ""}. Moving marks them as
            selected in this round and notifies them; {nextRound ? `${nextRound.name} then decides them like everyone else.` : "this is the last round — tick them on the Results page to give an offer."}
          </Typography>
          <Stack spacing={1}>
            {waitlisted.map((a) => {
              const published = Boolean(a.results[roundId].published);
              return (
                <Paper key={a.id} variant="outlined" sx={{ p: 1.25 }}>
                  <Stack direction={{ xs: "column", sm: "row" }} spacing={1.5} alignItems={{ xs: "flex-start", sm: "center" }}>
                    <Typography variant="body2" fontWeight={600}>
                      {a.student?.roll_no} {a.student?.full_name}
                    </Typography>
                    <Typography variant="caption" color="text.secondary">
                      {a.student?.branch} · CGPA {a.student?.current_cgpa ?? "—"}
                    </Typography>
                    {!published && <Chip size="small" variant="outlined" label="draft — publish the round first" />}
                    <Box sx={{ flex: 1 }} />
                    <Stack direction="row" spacing={1}>
                      {nextRound && (
                        <Button size="small" variant="outlined" disabled={busy || !published} onClick={() => moveOn(a)}>
                          Move to {nextRound.name}
                        </Button>
                      )}
                      <Button size="small" color="error" disabled={busy} onClick={() => remove(a)}>
                        Remove
                      </Button>
                    </Stack>
                  </Stack>
                </Paper>
              );
            })}
          </Stack>
        </>
      )}
    </Stack>
  );
}
