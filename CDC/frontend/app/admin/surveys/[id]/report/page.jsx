"use client";

import { use, useEffect, useState } from "react";
import Link from "next/link";
import {
  Alert,
  Box,
  Button,
  Card,
  CardContent,
  Chip,
  LinearProgress,
  Link as MuiLink,
  Paper,
  Stack,
  Tab,
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TableRow,
  Tabs,
  Typography,
} from "@mui/material";
import DashboardIcon from "@mui/icons-material/DashboardOutlined";
import DownloadIcon from "@mui/icons-material/Download";

import PageHeader from "@/components/shared/pageheader";
import StudentQuickView from "@/components/admin/studentquickview";
import { SURVEY_STATUS } from "@/components/admin/engagement/surveylabels";
import { adminApi, adminDownload } from "@/lib/adminapi";
import { adminBlobUrl } from "@/lib/adminupload";
import { formatDateTime } from "@/lib/format";
import { shortProgramme } from "@/lib/usecatalogue";

function Stat({ label, value }) {
  return (
    <Paper variant="outlined" sx={{ p: 2, flex: 1, minWidth: 130 }}>
      <Typography variant="h5" fontWeight={800}>
        {value}
      </Typography>
      <Typography variant="body2" color="text.secondary">
        {label}
      </Typography>
    </Paper>
  );
}

// "View Report" (Superset parity S7.3): responses, responders and non-responders, Excel export. Admin only.
export default function SurveyReportPage({ params }) {
  const { id } = use(params);
  const [data, setData] = useState(null);
  const [error, setError] = useState(null);
  const [tab, setTab] = useState("responses");
  const [quickView, setQuickView] = useState(null);
  const [downloading, setDownloading] = useState(false);

  useEffect(() => {
    adminApi(`/admin/surveys/${id}/report`)
      .then(setData)
      .catch((e) => setError(e instanceof Error ? e.message : "Failed to load the report."));
  }, [id]);

  const download = async () => {
    setDownloading(true);
    try {
      await adminDownload(`/admin/surveys/${id}/export`, `survey-${id}-responses.xlsx`);
    } catch (e) {
      setError(e instanceof Error ? e.message : "Download failed.");
    } finally {
      setDownloading(false);
    }
  };

  const openFile = async (responseId, questionId) => {
    try {
      const url = await adminBlobUrl(`/admin/surveys/${id}/responses/${responseId}/files/${questionId}`);
      window.open(url, "_blank", "noopener");
      setTimeout(() => URL.revokeObjectURL(url), 60000);
    } catch (e) {
      setError(e instanceof Error ? e.message : "Could not open the file.");
    }
  };

  if (!data) return error ? <Alert severity="error">{error}</Alert> : <LinearProgress />;

  const { survey, questions, counts, summary, responses, non_responders: nonResponders } = data;

  return (
    <>
      <PageHeader
        icon={<DashboardIcon />}
        title={survey.title}
        subtitle={`Survey report · ${SURVEY_STATUS[survey.status] ?? survey.status}${survey.deadline_at ? ` · Deadline ${formatDateTime(survey.deadline_at)}` : ""}`}
        backHref={`/admin/surveys/${id}`}
        backLabel="Back to form"
        actions={
          <Button variant="contained" color="secondary" startIcon={<DownloadIcon />} onClick={download} disabled={downloading}>
            Download as Excel
          </Button>
        }
      />
      {error && (
        <Alert severity="error" sx={{ mb: 2 }} onClose={() => setError(null)}>
          {error}
        </Alert>
      )}
      {survey.job_posting && (
        <Alert severity="info" sx={{ mb: 2 }}>
          Linked to {survey.job_posting.label}. Answers are information only; change offers or blocks yourself on the{" "}
          <MuiLink component={Link} href={`/admin/postings/${survey.job_posting.id}`}>
            job profile
          </MuiLink>{" "}
          if needed.
        </Alert>
      )}

      <Stack direction="row" spacing={1.5} flexWrap="wrap" useFlexGap sx={{ mb: 2 }}>
        <Stat label="Students in audience" value={counts.audience} />
        <Stat label="Responses" value={counts.responses} />
        <Stat label="Responders" value={counts.responders} />
        <Stat label="Non-responders" value={counts.non_responders} />
      </Stack>

      {summary.length > 0 && (
        <Box sx={{ display: "grid", gap: 1.5, gridTemplateColumns: { xs: "1fr", md: "1fr 1fr" }, mb: 2 }}>
          {summary.map((s) => {
            const q = questions.find((x) => x.id === s.question_id);
            const total = s.counts.reduce((sum, c) => sum + c.count, 0) || 1;
            return (
              <Card key={s.question_id} variant="outlined">
                <CardContent>
                  <Typography fontWeight={700} sx={{ mb: 1, wordBreak: "break-word" }}>
                    Q{s.number}. {q?.question}
                  </Typography>
                  <Stack spacing={0.75}>
                    {s.counts.map((c) => (
                      <Box key={c.label}>
                        <Stack direction="row" justifyContent="space-between" spacing={1}>
                          <Typography variant="body2" sx={{ wordBreak: "break-word" }}>
                            {c.label}
                          </Typography>
                          <Typography variant="body2" fontWeight={700}>
                            {c.count}
                          </Typography>
                        </Stack>
                        <LinearProgress variant="determinate" value={(c.count / total) * 100} sx={{ height: 6, borderRadius: 3 }} />
                      </Box>
                    ))}
                  </Stack>
                </CardContent>
              </Card>
            );
          })}
        </Box>
      )}

      <Card>
        <CardContent>
          <Tabs value={tab} onChange={(_e, v) => setTab(v)} sx={{ mb: 2 }} variant="scrollable" allowScrollButtonsMobile>
            <Tab value="responses" label={`Responses (${counts.responses})`} />
            <Tab value="non" label={`Non-responders (${counts.non_responders})`} />
          </Tabs>
          {tab === "responses" &&
            (responses.length === 0 ? (
              <Typography color="text.secondary">No responses yet.</Typography>
            ) : (
              <TableContainer sx={{ maxHeight: 640 }}>
                <Table size="small" stickyHeader>
                  <TableHead>
                    <TableRow>
                      <TableCell>Roll Number</TableCell>
                      <TableCell>Name</TableCell>
                      <TableCell>Branch</TableCell>
                      <TableCell>Submitted</TableCell>
                      {questions.map((q) => (
                        <TableCell key={q.id} sx={{ minWidth: 160, maxWidth: 280 }}>
                          Q{q.number}: {q.question}
                        </TableCell>
                      ))}
                    </TableRow>
                  </TableHead>
                  <TableBody>
                    {responses.map((r) => (
                      <TableRow key={r.id} hover>
                        <TableCell sx={{ whiteSpace: "nowrap" }}>{r.student?.roll_no}</TableCell>
                        <TableCell>
                          <MuiLink component="button" type="button" onClick={() => setQuickView(r.student?.id)} sx={{ textAlign: "left" }}>
                            {r.student?.full_name}
                          </MuiLink>
                        </TableCell>
                        <TableCell sx={{ minWidth: 140 }}>
                          {r.student?.branch}
                          <Typography variant="caption" color="text.secondary" component="div">
                            {shortProgramme(r.student?.programme)}
                          </Typography>
                        </TableCell>
                        <TableCell sx={{ whiteSpace: "nowrap" }}>{formatDateTime(r.submitted_at)}</TableCell>
                        {questions.map((q) => (
                          <TableCell key={q.id} sx={{ maxWidth: 280, wordBreak: "break-word" }}>
                            {r.files.includes(String(q.id)) ? (
                              <Chip size="small" variant="outlined" label={r.answers[q.id]} onClick={() => openFile(r.id, q.id)} />
                            ) : (
                              r.answers[q.id] || "—"
                            )}
                          </TableCell>
                        ))}
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              </TableContainer>
            ))}
          {tab === "non" &&
            (nonResponders.length === 0 ? (
              <Typography color="text.secondary">Everyone in the audience has responded.</Typography>
            ) : (
              <>
                {counts.non_responders > nonResponders.length && (
                  <Alert severity="info" sx={{ mb: 1 }}>
                    Showing the first {nonResponders.length} of {counts.non_responders}.
                  </Alert>
                )}
                <TableContainer sx={{ maxHeight: 640 }}>
                  <Table size="small" stickyHeader>
                    <TableHead>
                      <TableRow>
                        <TableCell>Roll Number</TableCell>
                        <TableCell>Name</TableCell>
                        <TableCell>Branch</TableCell>
                        <TableCell>Passout Batch</TableCell>
                        <TableCell>Institute Email</TableCell>
                      </TableRow>
                    </TableHead>
                    <TableBody>
                      {nonResponders.map((s) => (
                        <TableRow key={s.id} hover>
                          <TableCell sx={{ whiteSpace: "nowrap" }}>{s.roll_no}</TableCell>
                          <TableCell>
                            <MuiLink component="button" type="button" onClick={() => setQuickView(s.id)} sx={{ textAlign: "left" }}>
                              {s.full_name}
                            </MuiLink>
                          </TableCell>
                          <TableCell>{s.branch}</TableCell>
                          <TableCell>{s.graduating_batch}</TableCell>
                          <TableCell sx={{ wordBreak: "break-all" }}>{s.institute_email}</TableCell>
                        </TableRow>
                      ))}
                    </TableBody>
                  </Table>
                </TableContainer>
              </>
            ))}
        </CardContent>
      </Card>

      <StudentQuickView studentId={quickView} onClose={() => setQuickView(null)} />
    </>
  );
}
