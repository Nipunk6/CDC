"use client";

import { Alert, AlertTitle, Box } from "@mui/material";

/** The CDC blocking rules, shown wherever an admin floats a posting or announces results. */
export default function BlockingRules({ sx }) {
  return (
    <Alert severity="info" variant="outlined" sx={sx}>
      <AlertTitle>Blocking rules when results are announced</AlertTitle>
      <Box component="ul" sx={{ m: 0, pl: 2.5, "& li": { mb: 0.5 } }}>
        <li>
          <strong>Full-Time</strong>, <strong>Intern + Full-Time</strong> or an <strong>accepted PPO</strong>: the student is blocked
          completely, in every placement cycle they are registered for.
        </li>
        <li>
          <strong>Internship</strong> or <strong>Intern + performance-based PPO</strong>: the student is blocked from internship
          opportunities (the whole internship cycle). Full-time stays open.
        </li>
        <li>
          <strong>PPO offered (not accepted)</strong>: no block.
        </li>
        <li>A block also covers cycles the student is enrolled in later. The CDC can change it at announcement or lift it any time from the student&apos;s profile.</li>
      </Box>
    </Alert>
  );
}
