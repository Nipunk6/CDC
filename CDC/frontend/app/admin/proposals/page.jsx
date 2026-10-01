"use client";

import PlaylistAddCheckIcon from "@mui/icons-material/PlaylistAddCheck";
import { Card, CardContent } from "@mui/material";

import PageHeader from "@/components/shared/pageheader";
import ProposalsList from "@/components/admin/proposalslist";

export default function AdminProposalsPage() {
  return (
    <>
      <PageHeader
        icon={<PlaylistAddCheckIcon />}
        title="Company Proposals"
        subtitle="Shortlists, waitlists and addenda proposed by companies. Approving creates drafts; publish the round to notify students."
        backHref="/admin/postings"
        backLabel="Postings"
      />
      <Card>
        <CardContent>
          <ProposalsList />
        </CardContent>
      </Card>
    </>
  );
}
