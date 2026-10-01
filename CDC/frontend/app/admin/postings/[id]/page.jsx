"use client";

import { use, useCallback, useEffect, useState } from "react";
import Link from "next/link";
import { Alert, Button, Card, CardContent, Chip, LinearProgress, Stack, Tab, Tabs } from "@mui/material";
import WorkIcon from "@mui/icons-material/Work";
import EmojiEventsIcon from "@mui/icons-material/EmojiEvents";
import DownloadIcon from "@mui/icons-material/Download";
import RemoveFromProcess from "@/components/admin/posting/removefromprocess";

import PageHeader from "@/components/shared/pageheader";
import OverviewTab from "@/components/admin/posting/overviewtab";
import ApplicantsTab from "@/components/admin/posting/applicantstab";
import EligibleTab from "@/components/admin/posting/eligibletab";
import RoundsTab from "@/components/admin/posting/roundstab";
import QuestionsTab from "@/components/admin/posting/questionstab";
import PipelineTab from "@/components/admin/posting/pipelinetab";
import WaitlistTab from "@/components/admin/posting/waitlisttab";
import ProposalsList from "@/components/admin/proposalslist";
import { adminApi, adminDownload } from "@/lib/adminapi";
import { formatDateTime, statusColor, titleCase } from "@/lib/format";

const TABS = [
  { key: "overview", label: "Overview" },
  { key: "applicants", label: "Applicants" },
  { key: "eligible", label: "Eligible" },
  { key: "pipeline", label: "Pipeline" },
  { key: "waitlist", label: "Waitlist" },
  { key: "proposals", label: "Proposals" },
  { key: "rounds", label: "Rounds" },
  { key: "questions", label: "Questions" },
];

export default function AdminPostingDetailPage({ params }) {
  const { id } = use(params);
  const [posting, setPosting] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [success, setSuccess] = useState(null);
  const [tab, setTab] = useState("overview");

  const load = useCallback(async () => {
    try {
      const response = await adminApi(`/admin/postings/${id}`);
      setPosting(response.posting);
      setError(null);
    } catch (e) {
      setError(e instanceof Error ? e.message : "Failed to load this posting.");
    } finally {
      setLoading(false);
    }
  }, [id]);

  useEffect(() => {
    void load();
  }, [load]);

  if (loading) return <LinearProgress />;
  if (!posting) return <Alert severity="error">{error ?? "Posting not found."}</Alert>;

  const shared = { posting, onChanged: load, onMessage: setSuccess };

  return (
    <>
      <PageHeader
        icon={<WorkIcon />}
        title={`${posting.company?.name ?? ""} — ${posting.title}`}
        subtitle={`${posting.placement_cycle?.name} · apply by ${formatDateTime(posting.application_deadline)}`}
        backHref="/admin/postings"
        backLabel="All Postings"
        actions={
          <>
            <Chip color={statusColor(posting.status)} label={titleCase(posting.status)} sx={{ bgcolor: "white" }} variant="outlined" />
            <Button
              variant="contained"
              color="secondary"
              startIcon={<DownloadIcon />}
              onClick={() => adminDownload(`/admin/postings/${posting.id}/export`, `posting-${posting.id}-applicants.xlsx`).catch((e) => setError(e.message))}
            >
              Export
            </Button>
            <Button component={Link} href={`/admin/postings/${posting.id}/results`} variant="contained" color="secondary" startIcon={<EmojiEventsIcon />}>
              Results & Offers
            </Button>
          </>
        }
      />
      {error && (
        <Alert severity="error" sx={{ mb: 2 }} onClose={() => setError(null)}>
          {error}
        </Alert>
      )}
      {success && (
        <Alert severity="success" sx={{ mb: 2 }} onClose={() => setSuccess(null)}>
          {success}
        </Alert>
      )}
      <Card>
        <Tabs value={tab} onChange={(_e, value) => setTab(value)} variant="scrollable" allowScrollButtonsMobile>
          {TABS.map((t) => (
            <Tab key={t.key} value={t.key} label={t.label} />
          ))}
        </Tabs>
        <CardContent>
          <Stack>
            {tab === "overview" && <OverviewTab {...shared} />}
            {tab === "applicants" && (
              <ApplicantsTab
                posting={posting}
                rowActions={(application, reload) =>
                  application.placed_elsewhere_flag && application.status === "applied" && !application.out_of_process ? (
                    <RemoveFromProcess posting={posting} application={application} onDone={(message) => { setSuccess(message); void reload(); }} />
                  ) : null
                }
              />
            )}
            {tab === "eligible" && <EligibleTab posting={posting} />}
            {tab === "pipeline" && <PipelineTab posting={posting} onMessage={setSuccess} onChanged={load} />}
            {tab === "waitlist" && <WaitlistTab posting={posting} onMessage={setSuccess} onChanged={load} />}
            {tab === "proposals" && <ProposalsList postingId={posting.id} onDecided={load} />}
            {tab === "rounds" && <RoundsTab {...shared} />}
            {tab === "questions" && <QuestionsTab {...shared} />}
          </Stack>
        </CardContent>
      </Card>
    </>
  );
}
