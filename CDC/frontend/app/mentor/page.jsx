"use client";

import { useSession } from "next-auth/react";
import { Paper, Typography } from "@mui/material";

import PageHeader from "@/components/shared/pageheader";

// Phase 3 P0.2 / PU: the mentor role can sign in; the mentor pages (mentees, resume queue) are built in the mentor
// milestone (P8) and will replace this holding page.
export default function MentorHomePage() {
  const { data: session } = useSession();

  return (
    <>
      <PageHeader title="Mentor portal" subtitle={session?.user?.name ? `Signed in as ${session.user.name}` : undefined} />
      <Paper sx={{ p: 3, maxWidth: "72ch" }}>
        <Typography>Your mentees and their resumes will appear here once the CDC opens the mentor pages.</Typography>
      </Paper>
    </>
  );
}
