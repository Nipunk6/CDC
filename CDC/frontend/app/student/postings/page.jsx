"use client";

import { Suspense, useCallback, useEffect, useState } from "react";
import { useSearchParams } from "next/navigation";
import {
  Alert,
  Box,
  FormControl,
  Grid2 as Grid,
  InputAdornment,
  InputLabel,
  LinearProgress,
  MenuItem,
  Pagination,
  Paper,
  Select,
  Stack,
  TextField,
  Typography,
} from "@mui/material";
import SearchIcon from "@mui/icons-material/Search";

import PostingCard from "@/components/student/postingcard";
import { studentApi } from "@/lib/studentapi";

const filtersInitial = { type: "", eligibility: "", applied: "", status: "", search: "" };

export default function StudentPostingsPage() {
  return (
    <Suspense fallback={<LinearProgress />}>
      <StudentPostingsBoard />
    </Suspense>
  );
}

function StudentPostingsBoard() {
  const params = useSearchParams();
  // Links such as the dashboard's "Open for you" preset filters through the query string.
  const [filters, setFilters] = useState(() =>
    Object.fromEntries(Object.keys(filtersInitial).map((key) => [key, params.get(key) ?? ""]))
  );
  const [search, setSearch] = useState("");
  const [page, setPage] = useState(1);
  const [postings, setPostings] = useState([]);
  const [meta, setMeta] = useState({ last_page: 1, total: 0 });
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  const load = useCallback(async (current, targetPage) => {
    setLoading(true);
    try {
      const query = new URLSearchParams({ page: String(targetPage) });
      Object.entries(current).forEach(([key, value]) => value && query.set(key, value));
      const response = await studentApi(`/student/postings?${query.toString()}`);
      setPostings(response.postings ?? []);
      setMeta(response.meta ?? { last_page: 1, total: 0 });
      setError(null);
    } catch (e) {
      setError(e instanceof Error ? e.message : "Failed to load job profiles.");
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    void load(filters, page);
  }, [load, filters, page]);

  const setFilter = (key) => (event) => {
    setPage(1);
    setFilters((prev) => ({ ...prev, [key]: event.target.value }));
  };

  const select = (key, label, options) => (
    <FormControl size="small" sx={{ minWidth: { xs: "100%", sm: 150 } }}>
      <InputLabel id={`f-${key}`}>{label}</InputLabel>
      <Select labelId={`f-${key}`} label={label} value={filters[key]} onChange={setFilter(key)}>
        <MenuItem value="">All</MenuItem>
        {options.map(([value, text]) => (
          <MenuItem key={value} value={value}>
            {text}
          </MenuItem>
        ))}
      </Select>
    </FormControl>
  );

  return (
    <Stack spacing={3}>
      <Box>
        <Typography variant="h4" color="primary.main" fontWeight={700}>
          Job Profiles
        </Typography>
        <Typography color="text.secondary">Openings from the placement cycles you are enrolled in.</Typography>
      </Box>

      <Paper variant="outlined" sx={{ p: 2 }}>
        <Stack direction={{ xs: "column", md: "row" }} spacing={1.5} alignItems={{ md: "center" }}>
          <TextField
            size="small"
            placeholder="Search company or role"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            onKeyDown={(e) => {
              if (e.key === "Enter") {
                setPage(1);
                setFilters((prev) => ({ ...prev, search }));
              }
            }}
            slotProps={{ input: { startAdornment: <InputAdornment position="start"><SearchIcon fontSize="small" /></InputAdornment> } }}
            sx={{ flex: 1, minWidth: { md: 220 } }}
          />
          {select("type", "Type", [["fulltime", "Full Time"], ["internship", "Internship"]])}
          {select("eligibility", "Eligibility", [["eligible", "Eligible"], ["ineligible", "Not eligible"]])}
          {select("applied", "Applied", [["yes", "Applied"], ["no", "Not applied"]])}
          {select("status", "Status", [["open", "Open"], ["closed", "Closed"]])}
        </Stack>
      </Paper>

      {error && <Alert severity="error">{error}</Alert>}
      {loading && <LinearProgress />}
      {!loading && postings.length === 0 && (
        <Paper variant="outlined" sx={{ p: 5, textAlign: "center" }}>
          <Typography color="text.secondary">
            No job profiles match. New openings appear here as soon as the CDC floats them.
          </Typography>
        </Paper>
      )}

      <Grid container spacing={2}>
        {postings.map((posting) => (
          <Grid key={posting.id} size={{ xs: 12, md: 6, xl: 4 }}>
            <PostingCard posting={posting} />
          </Grid>
        ))}
      </Grid>

      {meta.last_page > 1 && (
        <Stack alignItems="center">
          <Pagination count={meta.last_page} page={page} onChange={(_e, value) => setPage(value)} color="primary" />
        </Stack>
      )}
    </Stack>
  );
}
