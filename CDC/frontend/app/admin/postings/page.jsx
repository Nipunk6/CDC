"use client";

import { useCallback, useEffect, useState } from "react";
import Link from "next/link";
import {
  Alert,
  Avatar,
  Box,
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
  Typography,
} from "@mui/material";
import WorkIcon from "@mui/icons-material/Work";

import PageHeader from "@/components/shared/pageheader";
import { adminApi } from "@/lib/adminapi";
import { formatDateTime, statusColor, titleCase } from "@/lib/format";

export default function AdminPostingsPage() {
  const [cycles, setCycles] = useState([]);
  const [cycleId, setCycleId] = useState("");
  const [status, setStatus] = useState("");
  const [postings, setPostings] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  useEffect(() => {
    adminApi("/admin/placement-cycles")
      .then((response) => setCycles(response.placement_cycles ?? []))
      .catch(() => setCycles([]));
  }, []);

  const load = useCallback(async (cycle, currentStatus) => {
    setLoading(true);
    try {
      const query = new URLSearchParams();
      if (cycle) query.set("cycle_id", cycle);
      if (currentStatus) query.set("status", currentStatus);
      const response = await adminApi(`/admin/postings?${query.toString()}`);
      setPostings(response.postings ?? []);
      setError(null);
    } catch (e) {
      setError(e instanceof Error ? e.message : "Failed to load postings.");
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    void load(cycleId, status);
  }, [load, cycleId, status]);

  return (
    <>
      <PageHeader
        icon={<WorkIcon />}
        title="Job Postings"
        subtitle={`${postings.length} posting(s) floated to students. Float new ones from an accepted JNF/INF.`}
        backHref="/admin"
        backLabel="Back to Dashboard"
      />

      {error && (
        <Alert severity="error" sx={{ mb: 2 }}>
          {error}
        </Alert>
      )}

      <Paper sx={{ p: 2, mb: 2 }}>
        <Stack direction={{ xs: "column", sm: "row" }} spacing={1.5}>
          <FormControl size="small" sx={{ minWidth: 240 }}>
            <InputLabel id="p-cycle">Placement cycle</InputLabel>
            <Select labelId="p-cycle" label="Placement cycle" value={cycleId} onChange={(e) => setCycleId(e.target.value)}>
              <MenuItem value="">All cycles</MenuItem>
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
                  {titleCase(s)}
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
            No postings yet. Open an accepted JNF or INF and use <strong>Float to Students</strong>.
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
                        <Chip size="small" variant="outlined" color={statusColor(posting.status)} label={titleCase(posting.status)} />
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
                        Applied
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
