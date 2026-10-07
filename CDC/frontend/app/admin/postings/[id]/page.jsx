"use client";

import { use, useCallback, useEffect, useState } from "react";
import Link from "next/link";
import { Alert, Button, Card, CardContent, Chip, LinearProgress, Stack, Tab, Tabs } from "@mui/material";
import WorkIcon from "@mui/icons-material/Work";
import EmojiEventsIcon from "@mui/icons-material/EmojiEvents";
import TuneIcon from "@mui/icons-material/Tune";
import RemoveFromProcess from "@/components/admin/posting/removefromprocess";
import EditEligibilityDialog from "@/components/admin/editeligibilitydialog";
import TemplateDownloadButton from "@/components/admin/templatedownloadbutton";

import PageHeader from "@/components/shared/pageheader";
import OverviewTab from "@/components/admin/posting/overviewtab";
import ApplicantsTab from "@/components/admin/posting/applicantstab";
import EligibleTab from "@/components/admin/posting/eligibletab";
import RoundsTab from "@/components/admin/posting/roundstab";
import QuestionsTab from "@/components/admin/posting/questionstab";
import PipelineTab from "@/components/admin/posting/pipelinetab";
import WaitlistTab from "@/components/admin/posting/waitlisttab";
import ProposalsList from "@/components/admin/proposalslist";
import DocumentsTab from "@/components/admin/posting/documentstab";
import ActivityTab from "@/components/admin/posting/activitytab";
import CommunicationTab from "@/components/admin/posting/communicationtab";
import { adminApi } from "@/lib/adminapi";
import { formatDateTime, statusColor, postingStatusLabel } from "@/lib/format";

const TABS = [
  { key: "overview", label: "Overview" },
  { key: "applicants", label: "Applicants" },
  { key: "eligible", label: "Eligible" },
  { key: "pipeline", label: "Progress Grid" },
  { key: "waitlist", label: "On Hold" },
  { key: "proposals", label: "Proposals" },
  { key: "rounds", label: "Stages" },
  { key: "questions", label: "Additional Questions" },
  { key: "documents", label: "Attached Documents" },
  { key: "activity", label: "Activity" },
  { key: "communication", label: "Communication Log" },
];

export default function AdminPostingDetailPage({ params }) {
  const { id } = use(params);
  const [posting, setPosting] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [success, setSuccess] = useState(null);
  const [tab, setTab] = useState("overview");
  const [editingEligibility, setEditingEligibility] = useState(false);

  const load = useCallback(async () => {
    try {
      const response = await adminApi(`/admin/postings/${id}`);
      setPosting(response.posting);
      setError(null);
    } catch (e) {
      setError(e instanceof Error ? e.message : "Failed to load this job profile.");
    } finally {
      setLoading(false);
    }
  }, [id]);

  useEffect(() => {
    void load();
  }, [load]);

  if (loading) return <LinearProgress />;
  if (!posting) return <Alert severity="error">{error ?? "Job profile not found."}</Alert>;

  const shared = { posting, onChanged: load, onMessage: setSuccess };

  return (
    <>
      <PageHeader
        icon={<WorkIcon />}
        title={`${posting.company?.name ?? ""} — ${posting.title}`}
        subtitle={`${posting.placement_cycle?.name} · apply by ${formatDateTime(posting.application_deadline)}`}
        backHref="/admin/postings"
        backLabel="All Job Profiles"
        actions={
          <>
            <Chip color={statusColor(posting.status)} label={postingStatusLabel(posting)} sx={{ bgcolor: "white" }} variant="outlined" />
            {["open", "in_process"].includes(posting.status) && (
              <Button variant="contained" color="secondary" startIcon={<TuneIcon />} onClick={() => setEditingEligibility(true)}>
                Edit eligibility
              </Button>
            )}
            <TemplateDownloadButton
              label="Download Applicants"
              path={`/admin/postings/${posting.id}/export`}
              fileName={`posting-${posting.id}-applicants.xlsx`}
              onError={setError}
            />
            <Button component={Link} href={`/admin/postings/${posting.id}/results`} variant="contained" color="secondary" startIcon={<EmojiEventsIcon />}>
              Shortlist for Offer
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
      {editingEligibility && (
        <EditEligibilityDialog
          posting={posting}
          onClose={() => setEditingEligibility(false)}
          onSaved={(message) => {
            setSuccess(message);
            void load();
          }}
        />
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
            {tab === "documents" && <DocumentsTab posting={posting} onMessage={setSuccess} />}
            {tab === "activity" && <ActivityTab posting={posting} />}
            {tab === "communication" && <CommunicationTab posting={posting} />}
          </Stack>
        </CardContent>
      </Card>
    </>
  );
}
