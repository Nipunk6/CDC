"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import {
  Alert,
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

import { adminApi } from "@/lib/adminapi";
import { formatDateTime, statusColor, titleCase } from "@/lib/format";

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

  if (postings.length === 0) {
    return (
      <Card>
        <CardContent sx={{ textAlign: "center", py: 6 }}>
          <WorkOutlineIcon sx={{ fontSize: 56, color: "text.disabled", mb: 1 }} />
          <Typography variant="h6" gutterBottom>
            No postings in this cycle yet
          </Typography>
          <Typography color="text.secondary" sx={{ textAlign: "center" }}>
            Accepted JNFs and INFs appear here once an admin floats them into this cycle.
          </Typography>
        </CardContent>
      </Card>
    );
  }

  return (
    <Card>
      <TableContainer>
        <Table size="small">
          <TableHead>
            <TableRow>
              <TableCell>Company · Role</TableCell>
              <TableCell>Form</TableCell>
              <TableCell>Status</TableCell>
              <TableCell>Applied</TableCell>
              <TableCell>Deadline</TableCell>
            </TableRow>
          </TableHead>
          <TableBody>
            {postings.map((posting) => (
              <TableRow key={posting.id} hover>
                <TableCell>
                  <Link href={`/admin/postings/${posting.id}`}>
                    {posting.company?.name} — {posting.title}
                  </Link>
                </TableCell>
                <TableCell>{posting.form_type.toUpperCase()}</TableCell>
                <TableCell>
                  <Chip size="small" variant="outlined" color={statusColor(posting.status)} label={titleCase(posting.status)} />
                </TableCell>
                <TableCell>{posting.applied_count}</TableCell>
                <TableCell sx={{ whiteSpace: "nowrap" }}>{formatDateTime(posting.application_deadline)}</TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>
      </TableContainer>
    </Card>
  );
}
