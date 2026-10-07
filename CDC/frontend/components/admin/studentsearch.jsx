"use client";

import { useRef, useState } from "react";
import { useRouter } from "next/navigation";
import {
  Avatar,
  Box,
  Dialog,
  IconButton,
  LinearProgress,
  List,
  ListItemAvatar,
  ListItemButton,
  ListItemText,
  TextField,
  Tooltip,
  Typography,
} from "@mui/material";
import SearchIcon from "@mui/icons-material/Search";

import { adminApi } from "@/lib/adminapi";
import { shortProgramme } from "@/lib/usecatalogue";

const LIMIT = 8;

export const initials = (name) =>
  String(name ?? "")
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0].toUpperCase())
    .join("") || "?";

/**
 * Global "Search students" (Superset parity S4.3): an icon in the admin top bar that opens a small search dialog.
 * Typing a roll number or name lists up to eight matches; choosing one opens the student page.
 */
export default function StudentSearch() {
  const router = useRouter();
  const [open, setOpen] = useState(false);
  const [term, setTerm] = useState("");
  const [results, setResults] = useState(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);
  const timer = useRef(null);
  const seq = useRef(0);

  const close = () => {
    clearTimeout(timer.current);
    setOpen(false);
    setTerm("");
    setResults(null);
    setError(null);
    setLoading(false);
  };

  const search = (value) => {
    setTerm(value);
    clearTimeout(timer.current);
    const query = value.trim();
    if (query.length < 2) {
      seq.current += 1;
      setResults(null);
      setLoading(false);
      return;
    }
    setLoading(true);
    timer.current = setTimeout(() => {
      const mine = ++seq.current;
      adminApi(`/admin/students?per_page=${LIMIT}&search=${encodeURIComponent(query)}`)
        .then((response) => {
          if (mine !== seq.current) return;
          setResults(response.students ?? []);
          setError(null);
        })
        .catch((e) => {
          if (mine === seq.current) setError(e instanceof Error ? e.message : "Search failed.");
        })
        .finally(() => {
          if (mine === seq.current) setLoading(false);
        });
    }, 250);
  };

  const go = (student) => {
    close();
    router.push(`/admin/students/${student.id}`);
  };

  return (
    <>
      <Tooltip title="Search students">
        <IconButton color="inherit" aria-label="Search students" onClick={() => setOpen(true)}>
          <SearchIcon />
        </IconButton>
      </Tooltip>
      <Dialog
        open={open}
        onClose={close}
        fullWidth
        maxWidth="sm"
        sx={{ "& .MuiDialog-container": { alignItems: "flex-start" } }}
        PaperProps={{ sx: { mt: { xs: 2, sm: 10 }, mx: 2, width: "calc(100% - 32px)" } }}
      >
        <Box sx={{ p: 2, pb: 1 }}>
          <TextField
            autoFocus
            fullWidth
            size="small"
            placeholder="Search students"
            value={term}
            onChange={(event) => search(event.target.value)}
            onKeyDown={(event) => {
              if (event.key === "Enter" && results?.length) go(results[0]);
            }}
            helperText="Type a roll number or name"
            slotProps={{ input: { startAdornment: <SearchIcon fontSize="small" sx={{ mr: 1, color: "text.secondary" }} /> } }}
          />
        </Box>
        {loading && <LinearProgress />}
        {error && (
          <Typography color="error" variant="body2" sx={{ px: 2, pb: 2 }}>
            {error}
          </Typography>
        )}
        {results && results.length === 0 && !loading && (
          <Typography color="text.secondary" variant="body2" sx={{ px: 2, pb: 2 }}>
            Could not find any students
          </Typography>
        )}
        {results && results.length > 0 && (
          <List dense sx={{ pt: 0 }}>
            {results.map((student) => (
              <ListItemButton key={student.id} onClick={() => go(student)}>
                <ListItemAvatar>
                  <Avatar sx={{ width: 32, height: 32, fontSize: 14, bgcolor: "primary.main" }}>{initials(student.full_name)}</Avatar>
                </ListItemAvatar>
                <ListItemText
                  primary={student.full_name}
                  secondary={`${student.roll_no} · ${shortProgramme(student.programme)} · ${student.branch}`}
                  primaryTypographyProps={{ fontWeight: 600, noWrap: true }}
                  secondaryTypographyProps={{ noWrap: true }}
                />
              </ListItemButton>
            ))}
          </List>
        )}
      </Dialog>
    </>
  );
}
