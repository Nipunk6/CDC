"use client";

import { useCallback, useEffect, useState } from "react";
import Link from "next/link";
import {
  Alert,
  Avatar,
  Box,
  Button,
  Card,
  CardActionArea,
  CardContent,
  Chip,
  FormControl,
  InputLabel,
  LinearProgress,
  MenuItem,
  Paper,
  Select,
  Stack,
  TextField,
  Typography,
} from "@mui/material";
import WorkIcon from "@mui/icons-material/Work";
import AddIcon from "@mui/icons-material/Add";

import PageHeader from "@/components/shared/pageheader";
import { adminApi } from "@/lib/adminapi";
import { formatDate, formatDateTime, postingStatusLabel, statusColor } from "@/lib/format";

export default function AdminPostingsPage() {
  const [cycles, setCycles] = useState([]);
  const [cycleId, setCycleId] = useState("");
  const [status, setStatus] = useState("");
  const [search, setSearch] = useState("");
  const [term, setTerm] = useState("");
  const [postings, setPostings] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  useEffect(() => {
    adminApi("/admin/placement-cycles")
      .then((response) => setCycles(response.placement_cycles ?? []))
      .catch(() => setCycles([]));
  }, []);

  useEffect(() => {
    const timer = setTimeout(() => setTerm(search.trim()), 300);
    return () => clearTimeout(timer);
  }, [search]);

  const load = useCallback(async (cycle, currentStatus, currentTerm) => {
    setLoading(true);
    try {
      const query = new URLSearchParams();
      if (cycle) query.set("cycle_id", cycle);
      if (currentStatus) query.set("status", currentStatus);
      if (currentTerm) query.set("search", currentTerm);
      const response = await adminApi(`/admin/postings?${query.toString()}`);
      setPostings(response.postings ?? []);
      setError(null);
    } catch (e) {
      setError(e instanceof Error ? e.message : "Failed to load job profiles.");
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    void load(cycleId, status, term);
  }, [load, cycleId, status, term]);

  return (
    <>
      <PageHeader
        icon={<WorkIcon />}
        title="Job Profiles"
        subtitle={loading ? "Loading job profiles…" : `${postings.length} job profile(s) opened for applications. Open new ones from an accepted JNF/INF.`}
        backHref="/admin"
        backLabel="Back to Dashboard"
        actions={
          <Button
            component={Link}
            href={cycleId ? `/admin/postings/new?cycle=${cycleId}` : "/admin/postings/new"}
            variant="contained"
            startIcon={<AddIcon />}
            sx={{ bgcolor: "common.white", color: "primary.main", "&:hover": { bgcolor: "grey.100" } }}
          >
            Add New Job
          </Button>
        }
      />

      {error && (
        <Alert severity="error" sx={{ mb: 2 }}>
          {error}
        </Alert>
      )}

      <Paper sx={{ p: 2, mb: 2 }}>
        <Stack direction={{ xs: "column", sm: "row" }} spacing={1.5}>
          <TextField size="small" label="Search company or profile" value={search} onChange={(e) => setSearch(e.target.value)} sx={{ minWidth: { sm: 260 } }} />
          <FormControl size="small" sx={{ minWidth: 240 }}>
            <InputLabel id="p-cycle">Placement</InputLabel>
            <Select labelId="p-cycle" label="Placement" value={cycleId} onChange={(e) => setCycleId(e.target.value)}>
              <MenuItem value="">All placements</MenuItem>
              {cycles.map((cycle) => (
                <MenuItem key={cycle.id} value={String(cycle.id)}>
                  {cycle.name}
                </MenuItem>
              ))}
            </Select>
          </FormControl>
          <FormControl size="small" sx={{ minWidth: 180 }}>
            <InputLabel id="p-status">Status</InputLabel>
            <Select labelId="p-status" label="Status" value={status} onChange={(e) => setStatus(e.target.value)}>
              <MenuItem value="">All</MenuItem>
              {["open", "in_process", "completed", "cancelled"].map((s) => (
                <MenuItem key={s} value={s}>
                  {postingStatusLabel(s)}
                </MenuItem>
              ))}
            </Select>
          </FormControl>
        </Stack>
      </Paper>

      {loading && <LinearProgress sx={{ mb: 2 }} />}
      {!loading && postings.length === 0 && (
        <Paper sx={{ p: 5, textAlign: "center" }}>
          <Typography color="text.secondary">
            No job profiles yet. Open an accepted JNF or INF and use <strong>Open Profile for Applications</strong>.
          </Typography>
        </Paper>
      )}

      <Stack spacing={1.5}>
        {postings.map((posting) => (
          <Card key={posting.id}>
            <CardActionArea component={Link} href={`/admin/postings/${posting.id}`}>
              <CardContent>
                <Stack direction={{ xs: "column", md: "row" }} spacing={2} justifyContent="space-between" alignItems={{ md: "center" }}>
                  <Stack direction="row" spacing={2} alignItems="center" sx={{ minWidth: 0 }}>
                    <Avatar src={posting.company?.logo_url ?? undefined} variant="rounded" sx={{ bgcolor: "primary.main" }}>
                      {posting.company?.name?.[0]}
                    </Avatar>
                    <Box sx={{ minWidth: 0 }}>
                      <Typography fontWeight={700} sx={{ wordBreak: "break-word" }}>
                        {posting.company?.name} — {posting.title}
                      </Typography>
                      <Stack direction="row" spacing={1} flexWrap="wrap" useFlexGap sx={{ mt: 0.5 }}>
                        <Chip size="small" variant="outlined" color={posting.type === "fulltime" ? "primary" : "secondary"} label={`${posting.form_type.toUpperCase()} · ${posting.type === "fulltime" ? "Full Time" : "Internship"}`} />
                        <Chip size="small" variant="outlined" color={statusColor(posting.status)} label={postingStatusLabel(posting)} />
                        <Chip size="small" variant="outlined" label={posting.placement_cycle?.name} />
                      </Stack>
                    </Box>
                  </Stack>
                  <Stack direction="row" spacing={3} alignItems="center">
                    <Box sx={{ textAlign: "center" }}>
                      <Typography variant="h6" fontWeight={700}>
                        {posting.applied_count}
                      </Typography>
                      <Typography variant="caption" color="text.secondary">
                        Applicants
                      </Typography>
                    </Box>
                    <Box>
                      <Typography variant="caption" color="text.secondary" display="block">
                        Date of Visit
                      </Typography>
                      <Typography variant="body2" fontWeight={600}>
                        {posting.visit_date ? formatDate(posting.visit_date) : "—"}
                      </Typography>
                    </Box>
                    <Box>
                      <Typography variant="caption" color="text.secondary" display="block">
                        Deadline
                      </Typography>
                      <Typography variant="body2" color={posting.deadline_passed ? "text.secondary" : "error.main"} fontWeight={600}>
                        {formatDateTime(posting.application_deadline)}
                      </Typography>
                    </Box>
                  </Stack>
                </Stack>
              </CardContent>
            </CardActionArea>
          </Card>
        ))}
      </Stack>
    </>
  );
}
