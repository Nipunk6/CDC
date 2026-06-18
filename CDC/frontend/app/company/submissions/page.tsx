"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import {
  Alert,
  Button,
  Card,
  CardContent,
  Grid2,
  Stack,
  Typography,
  Accordion,
  AccordionSummary,
  AccordionDetails,
  Chip,
} from "@mui/material";
import ExpandMoreIcon from "@mui/icons-material/ExpandMore";
import { companyApi } from "@/lib/companyapi";

type JnfItem = {
  id: number;
  job_title: string;
  status: string;
  updated_at: string;
  edit_access_requested_at?: string | null;
  graduating_batch?: string;
};
type InfItem = {
  id: number;
  internship_title: string;
  status: string;
  updated_at: string;
  edit_access_requested_at?: string | null;
  graduating_batch?: string;
};

export default function SubmissionsPage() {
  const [jnfs, setJnfs] = useState<JnfItem[]>([]);
  const [infs, setInfs] = useState<InfItem[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [requestingEditAccess, setRequestingEditAccess] = useState<{ type: "jnf" | "inf"; id: number } | null>(null);

  useEffect(() => {
    const run = async () => {
      try {
        const [jnfData, infData] = await Promise.all([
          companyApi<{ jnfs: JnfItem[] }>("/company/jnfs"),
          companyApi<{ infs: InfItem[] }>("/company/infs"),
        ]);
        setJnfs(jnfData.jnfs);
        setInfs(infData.infs);
      } catch (e) {
        setError(
          e instanceof Error ? e.message : "Failed to load submissions.",
        );
      }
    };

    void run();
  }, []);

  const handleRequestEditAccess = async (type: "jnf" | "inf", id: number) => {
    const reason = window.prompt("Briefly explain why you need edit access again:");
    if (reason === null) return;

    const trimmedReason = reason.trim();
    if (!trimmedReason) {
      setError("Please provide a brief reason.");
      return;
    }

    try {
      setError(null);
      setRequestingEditAccess({ type, id });

      const endpoint = type === "jnf"
        ? `/company/jnfs/${id}/request-edit-access`
        : `/company/infs/${id}/request-edit-access`;

      await companyApi(endpoint, {
        method: "POST",
        body: JSON.stringify({ reason: trimmedReason }),
      });

      const [jnfData, infData] = await Promise.all([
        companyApi<{ jnfs: JnfItem[] }>("/company/jnfs"),
        companyApi<{ infs: InfItem[] }>("/company/infs"),
      ]);
      setJnfs(jnfData.jnfs);
      setInfs(infData.infs);
    } catch (e) {
      setError(e instanceof Error ? e.message : "Failed to request edit access.");
    } finally {
      setRequestingEditAccess(null);
    }
  };

  const groupedJnfs = jnfs.reduce((acc, jnf) => {
    const batch = jnf.graduating_batch || "Unknown Batch";
    if (!acc[batch]) {
      acc[batch] = [];
    }
    acc[batch].push(jnf);
    return acc;
  }, {} as Record<string, JnfItem[]>);

  const sortedJnfBatches = Object.keys(groupedJnfs).sort((a, b) => b.localeCompare(a));

  const groupedInfs = infs.reduce((acc, inf) => {
    const batch = inf.graduating_batch || "Unknown Batch";
    if (!acc[batch]) {
      acc[batch] = [];
    }
    acc[batch].push(inf);
    return acc;
  }, {} as Record<string, InfItem[]>);

  const sortedInfBatches = Object.keys(groupedInfs).sort((a, b) => b.localeCompare(a));

  return (
    <Stack spacing={2.5}>
      <Typography variant="h4" color="primary.main">
        My Submissions
      </Typography>
      {error && <Alert severity="error">{error}</Alert>}

      <Grid2 container spacing={2}>
        <Grid2 size={{ xs: 12, md: 6 }}>
          <Card>
            <CardContent>
              <Typography variant="h6" gutterBottom>
                JNF Submissions
              </Typography>
              <Stack spacing={2}>
                {sortedJnfBatches.map((batch) => (
                  <Accordion key={batch} defaultExpanded sx={{ '&:before': { display: 'none' }, boxShadow: 1, borderRadius: 1 }}>
                    <AccordionSummary expandIcon={<ExpandMoreIcon />} sx={{ bgcolor: 'grey.50', borderRadius: 1 }}>
                      <Stack direction="row" spacing={2} alignItems="center">
                        <Typography variant="subtitle2" fontWeight={600}>
                          {batch === "Unknown Batch" ? batch : `Batch of ${batch}`}
                        </Typography>
                        <Chip label={groupedJnfs[batch].length} size="small" color="primary" sx={{ height: 20 }} />
                      </Stack>
                    </AccordionSummary>
                    <AccordionDetails sx={{ p: 2 }}>
                      <Stack spacing={2}>
                        {groupedJnfs[batch].map((item) => (
                          <Stack
                            key={item.id}
                            direction="row"
                            justifyContent="space-between"
                            alignItems="center"
                            sx={{ pb: 1, borderBottom: '1px solid', borderColor: 'divider', '&:last-child': { borderBottom: 0, pb: 0 } }}
                          >
                            <Stack direction="row" spacing={1} alignItems="center" flexWrap="wrap">
                              <Typography variant="body2" fontWeight={500}>
                                {item.job_title}
                              </Typography>
                              <Chip label={item.status.replace("_", " ")} size="small" variant="outlined" />
                              {item.status === "under_review" && (
                                <Button
                                  component={Link}
                                  href={`/company/jnf/${item.id}`}
                                  size="small"
                                  variant="text"
                                >
                                  Admin Message
                                </Button>
                              )}
                              {item.status === "submitted" && !item.edit_access_requested_at && (
                                <Button
                                  size="small"
                                  variant="text"
                                  onClick={() => void handleRequestEditAccess("jnf", item.id)}
                                  disabled={requestingEditAccess?.type === "jnf" && requestingEditAccess.id === item.id}
                                >
                                  {requestingEditAccess?.type === "jnf" && requestingEditAccess.id === item.id ? "Requesting..." : "Request Edit Access"}
                                </Button>
                              )}
                              {item.status === "submitted" && item.edit_access_requested_at && (
                                <Typography variant="caption" color="warning.main" sx={{ display: 'block', width: '100%' }}>
                                  Edit access request submitted
                                </Typography>
                              )}
                            </Stack>
                            <Stack direction="row" spacing={1}>
                              <Button
                                component={Link}
                                href={`/company/jnf/${item.id}`}
                                size="small"
                              >
                                View
                              </Button>
                              {(item.status === "draft" || item.status === "under_review") && (
                                <Button
                                  component={Link}
                                  href={`/company/jnf/${item.id}/edit`}
                                  size="small"
                                >
                                  Edit
                                </Button>
                              )}
                            </Stack>
                          </Stack>
                        ))}
                      </Stack>
                    </AccordionDetails>
                  </Accordion>
                ))}
                {jnfs.length === 0 && (
                  <Typography variant="body2" color="text.secondary">
                    No JNF submissions yet.
                  </Typography>
                )}
              </Stack>
            </CardContent>
          </Card>
        </Grid2>

        <Grid2 size={{ xs: 12, md: 6 }}>
          <Card>
            <CardContent>
              <Typography variant="h6" gutterBottom>
                INF Submissions
              </Typography>
              <Stack spacing={2}>
                {sortedInfBatches.map((batch) => (
                  <Accordion key={batch} defaultExpanded sx={{ '&:before': { display: 'none' }, boxShadow: 1, borderRadius: 1 }}>
                    <AccordionSummary expandIcon={<ExpandMoreIcon />} sx={{ bgcolor: 'grey.50', borderRadius: 1 }}>
                      <Stack direction="row" spacing={2} alignItems="center">
                        <Typography variant="subtitle2" fontWeight={600}>
                          {batch === "Unknown Batch" ? batch : `Batch of ${batch}`}
                        </Typography>
                        <Chip label={groupedInfs[batch].length} size="small" color="secondary" sx={{ height: 20 }} />
                      </Stack>
                    </AccordionSummary>
                    <AccordionDetails sx={{ p: 2 }}>
                      <Stack spacing={2}>
                        {groupedInfs[batch].map((item) => (
                          <Stack
                            key={item.id}
                            direction="row"
                            justifyContent="space-between"
                            alignItems="center"
                            sx={{ pb: 1, borderBottom: '1px solid', borderColor: 'divider', '&:last-child': { borderBottom: 0, pb: 0 } }}
                          >
                            <Stack direction="row" spacing={1} alignItems="center" flexWrap="wrap">
                              <Typography variant="body2" fontWeight={500}>
                                {item.internship_title}
                              </Typography>
                              <Chip label={item.status.replace("_", " ")} size="small" variant="outlined" />
                              {item.status === "under_review" && (
                                <Button
                                  component={Link}
                                  href={`/company/inf/${item.id}`}
                                  size="small"
                                  variant="text"
                                >
                                  Admin Message
                                </Button>
                              )}
                              {item.status === "submitted" && !item.edit_access_requested_at && (
                                <Button
                                  size="small"
                                  variant="text"
                                  onClick={() => void handleRequestEditAccess("inf", item.id)}
                                  disabled={requestingEditAccess?.type === "inf" && requestingEditAccess.id === item.id}
                                >
                                  {requestingEditAccess?.type === "inf" && requestingEditAccess.id === item.id ? "Requesting..." : "Request Edit Access"}
                                </Button>
                              )}
                              {item.status === "submitted" && item.edit_access_requested_at && (
                                <Typography variant="caption" color="warning.main" sx={{ display: 'block', width: '100%' }}>
                                  Edit access request submitted
                                </Typography>
                              )}
                            </Stack>
                            <Stack direction="row" spacing={1}>
                              <Button
                                component={Link}
                                href={`/company/inf/${item.id}`}
                                size="small"
                              >
                                View
                              </Button>
                              {(item.status === "draft" || item.status === "under_review") && (
                                <Button
                                  component={Link}
                                  href={`/company/inf/${item.id}/edit`}
                                  size="small"
                                >
                                  Edit
                                </Button>
                              )}
                            </Stack>
                          </Stack>
                        ))}
                      </Stack>
                    </AccordionDetails>
                  </Accordion>
                ))}
                {infs.length === 0 && (
                  <Typography variant="body2" color="text.secondary">
                    No INF submissions yet.
                  </Typography>
                )}
              </Stack>
            </CardContent>
          </Card>
        </Grid2>
      </Grid2>
    </Stack>
  );
}
