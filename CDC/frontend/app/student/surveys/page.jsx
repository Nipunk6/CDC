"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import { Alert, Box, Button, Card, CardContent, Chip, LinearProgress, Pagination, Stack, Typography } from "@mui/material";

import { studentApi } from "@/lib/studentapi";
import { formatDateTime } from "@/lib/format";

// Surveys from the CDC that this student may answer, 20 per page (Superset parity S7.3). Students never see response
// counts.
export default function StudentSurveysPage() {
  const [page, setPage] = useState(1);
  const [surveys, setSurveys] = useState(null);
  const [meta, setMeta] = useState(null);
  const [error, setError] = useState(null);

  useEffect(() => {
    studentApi(`/student/surveys?page=${page}`)
      .then((r) => {
        setSurveys(r.surveys ?? []);
        setMeta(r.meta ?? null);
      })
      .catch((e) => setError(e instanceof Error ? e.message : "Failed to load surveys."));
  }, [page]);

  const changePage = (value) => {
    setPage(value);
    if (typeof window !== "undefined") window.scrollTo({ top: 0, behavior: "smooth" });
  };

  const action = (s) => {
    if (s.can_edit) return "View / edit response";
    if (s.can_submit) return s.responded ? "Submit another response" : "Respond";
    return s.responded ? "View response" : "View";
  };

  return (
    <Box sx={{ maxWidth: 860 }}>
      <Typography variant="h5" fontWeight={700}>
        Surveys
      </Typography>
      <Typography variant="body2" color="text.secondary" sx={{ mb: 2 }}>
        Forms from the CDC for you to fill in.
      </Typography>
      {error && <Alert severity="error">{error}</Alert>}
      {!surveys && !error && <LinearProgress />}
      {surveys && surveys.length === 0 && <Typography color="text.secondary">No surveys right now.</Typography>}
      <Stack spacing={1.25}>
        {(surveys ?? []).map((s) => (
          <Card key={s.id} variant="outlined">
            <CardContent>
              <Stack direction={{ xs: "column", sm: "row" }} spacing={1.5} justifyContent="space-between" alignItems={{ sm: "center" }}>
                <Box sx={{ minWidth: 0 }}>
                  <Typography fontWeight={700} sx={{ wordBreak: "break-word" }}>
                    {s.title}
                  </Typography>
                  <Stack direction="row" spacing={1} alignItems="center" flexWrap="wrap" useFlexGap sx={{ mt: 0.5 }}>
                    <Chip size="small" color={s.is_open ? "success" : "default"} label={s.is_open ? "Open" : "Closed"} />
                    {s.responded && <Chip size="small" color="primary" variant="outlined" label="Submitted" />}
                    {s.deadline_at && (
                      <Typography variant="caption" color="text.secondary">
                        Deadline {formatDateTime(s.deadline_at)}
                      </Typography>
                    )}
                  </Stack>
                </Box>
                <Button component={Link} href={`/student/surveys/${s.id}`} variant={s.can_submit && !s.responded ? "contained" : "outlined"} sx={{ flexShrink: 0 }}>
                  {action(s)}
                </Button>
              </Stack>
            </CardContent>
          </Card>
        ))}
      </Stack>
      {meta?.last_page > 1 && (
        <Stack alignItems="center" sx={{ mt: 2 }}>
          <Pagination count={meta.last_page} page={meta.current_page} onChange={(_e, value) => changePage(value)} siblingCount={0} />
        </Stack>
      )}
    </Box>
  );
}
