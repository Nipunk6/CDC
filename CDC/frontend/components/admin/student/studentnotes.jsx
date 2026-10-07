"use client";

import { useEffect, useState } from "react";
import { Alert, Box, Button, IconButton, Paper, Stack, TextField, Tooltip, Typography } from "@mui/material";
import DeleteOutlineIcon from "@mui/icons-material/DeleteOutline";

import { adminApi } from "@/lib/adminapi";
import { formatDateTime } from "@/lib/format";

// "Notes" (Superset parity S4.5): admin-only internal notes, never shown to the student or companies.
export default function StudentNotes({ studentId }) {
  const [notes, setNotes] = useState([]);
  const [loaded, setLoaded] = useState(false);
  const [body, setBody] = useState("");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState(null);

  const refresh = () =>
    adminApi(`/admin/students/${studentId}/notes`)
      .then((response) => {
        setNotes(response.notes ?? []);
        setLoaded(true);
      })
      .catch((e) => setError(e instanceof Error ? e.message : "Failed to load notes."));

  useEffect(() => {
    let active = true;
    adminApi(`/admin/students/${studentId}/notes`)
      .then((response) => {
        if (!active) return;
        setNotes(response.notes ?? []);
        setLoaded(true);
      })
      .catch((e) => active && setError(e instanceof Error ? e.message : "Failed to load notes."));
    return () => {
      active = false;
    };
  }, [studentId]);

  const add = async () => {
    setBusy(true);
    setError(null);
    try {
      await adminApi(`/admin/students/${studentId}/notes`, { method: "POST", body: JSON.stringify({ body }) });
      setBody("");
      await refresh();
    } catch (e) {
      setError(e instanceof Error ? e.message : "Failed to add the note.");
    } finally {
      setBusy(false);
    }
  };

  const remove = async (note) => {
    if (!window.confirm("Delete this note?")) return;
    setError(null);
    try {
      await adminApi(`/admin/students/${studentId}/notes/${note.id}`, { method: "DELETE" });
      await refresh();
    } catch (e) {
      setError(e instanceof Error ? e.message : "Failed to delete the note.");
    }
  };

  return (
    <Stack spacing={2}>
      <Box>
        <Typography variant="subtitle1" fontWeight={700}>
          Notes
        </Typography>
        <Typography variant="body2" color="text.secondary">
          Visible to CDC admins only.
        </Typography>
      </Box>
      {error && (
        <Alert severity="error" onClose={() => setError(null)}>
          {error}
        </Alert>
      )}
      <Stack spacing={1}>
        <TextField
          multiline
          minRows={2}
          fullWidth
          placeholder="Write notes about this student"
          value={body}
          onChange={(event) => setBody(event.target.value)}
          slotProps={{ htmlInput: { maxLength: 5000 } }}
        />
        <Box>
          <Button variant="contained" disabled={busy || !body.trim()} onClick={add}>
            Add Note
          </Button>
        </Box>
      </Stack>
      {loaded && notes.length === 0 && <Typography color="text.secondary">No notes yet.</Typography>}
      {notes.map((note) => (
        <Paper key={note.id} variant="outlined" sx={{ p: 1.5 }}>
          <Stack direction="row" spacing={1} justifyContent="space-between" alignItems="flex-start">
            <Box sx={{ minWidth: 0 }}>
              <Typography variant="caption" color="text.secondary">
                {note.author?.name ?? "Former admin"} · {formatDateTime(note.created_at)}
              </Typography>
              <Typography variant="body2" sx={{ whiteSpace: "pre-wrap", wordBreak: "break-word" }}>
                {note.body}
              </Typography>
            </Box>
            <Tooltip title="Delete (author or super admin)">
              <IconButton size="small" aria-label="Delete note" onClick={() => remove(note)}>
                <DeleteOutlineIcon fontSize="small" />
              </IconButton>
            </Tooltip>
          </Stack>
        </Paper>
      ))}
    </Stack>
  );
}
