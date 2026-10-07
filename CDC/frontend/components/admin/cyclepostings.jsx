"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import {
  Alert,
  Box,
  Button,
  Card,
  CardContent,
  Chip,
  LinearProgress,
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TableRow,
  Typography,
} from "@mui/material";
import WorkOutlineIcon from "@mui/icons-material/WorkOutline";
import AddIcon from "@mui/icons-material/Add";

import { adminApi } from "@/lib/adminapi";
import { formatDate, formatDateTime, postingStatusLabel, statusColor } from "@/lib/format";

// Postings floated into one placement cycle (cycle detail page, Postings tab).
export default function CyclePostings({ cycleId }) {
  const [postings, setPostings] = useState(null);
  const [error, setError] = useState(null);

  useEffect(() => {
    adminApi(`/admin/postings?cycle_id=${cycleId}`)
      .then((response) => setPostings(response.postings ?? []))
      .catch((e) => setError(e.message));
  }, [cycleId]);

  if (error) return <Alert severity="error">{error}</Alert>;
  if (!postings) return <LinearProgress />;

  // "+ Add New Job" (S6.1): the CDC fills a JNF/INF for a company and returns here to open it.
  const addNewJob = (
    <Button component={Link} href={`/admin/postings/new?cycle=${cycleId}`} variant="contained" size="small" startIcon={<AddIcon />}>
      Add New Job
    </Button>
  );

  if (postings.length === 0) {
    return (
      <Card>
        <CardContent sx={{ textAlign: "center", py: 6 }}>
          <WorkOutlineIcon sx={{ fontSize: 56, color: "text.disabled", mb: 1 }} />
          <Typography variant="h6" gutterBottom>
            No job profiles in this placement yet
          </Typography>
          <Typography color="text.secondary" sx={{ textAlign: "center" }}>
            Accepted JNFs and INFs appear here once an admin opens them for applications in this placement.
          </Typography>
          <Box sx={{ mt: 2 }}>{addNewJob}</Box>
        </CardContent>
      </Card>
    );
  }

  return (
    <Card>
      <Box sx={{ display: "flex", justifyContent: "flex-end", px: 2, pt: 2 }}>{addNewJob}</Box>
      <TableContainer>
        <Table size="small">
          <TableHead>
            <TableRow>
              <TableCell>Company</TableCell>
              <TableCell>Profile</TableCell>
              <TableCell>Date of Visit</TableCell>
              <TableCell>Deadline</TableCell>
              <TableCell>Status</TableCell>
              <TableCell align="right">Applicants</TableCell>
            </TableRow>
          </TableHead>
          <TableBody>
            {postings.map((posting) => (
              <TableRow key={posting.id} hover>
                <TableCell>{posting.company?.name}</TableCell>
                <TableCell>
                  <Link href={`/admin/postings/${posting.id}`}>{posting.title}</Link>
                  <Typography variant="caption" color="text.secondary" display="block">
                    {posting.form_type.toUpperCase()} · {posting.offer_label}
                  </Typography>
                </TableCell>
                <TableCell sx={{ whiteSpace: "nowrap" }}>{posting.visit_date ? formatDate(posting.visit_date) : "—"}</TableCell>
                <TableCell sx={{ whiteSpace: "nowrap" }}>{formatDateTime(posting.application_deadline)}</TableCell>
                <TableCell>
                  <Chip size="small" variant="outlined" color={statusColor(posting.status)} label={postingStatusLabel(posting)} />
                </TableCell>
                <TableCell align="right">{posting.applied_count}</TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>
      </TableContainer>
    </Card>
  );
}
