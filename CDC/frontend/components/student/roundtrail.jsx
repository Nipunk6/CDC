"use client";

import { Box, Chip, Step, StepLabel, Stepper, Typography } from "@mui/material";

const resultChip = (step) => {
  if (!step.published) return null;
  const map = {
    selected: { color: "success", label: step.is_final ? "Selected" : "Cleared" },
    rejected: { color: "error", label: "Not selected" },
    waitlisted: { color: "info", label: "Waitlisted" },
    pending: { color: "default", label: "Result pending" },
  };
  const chip = map[step.result] ?? map.pending;
  return <Chip size="small" color={chip.color} variant="outlined" label={chip.label} />;
};

/**
 * Eligible → Applied → each round (appeared? → result) → Final, from PUBLISHED results only (spec Q4.7).
 */
export default function RoundTrail({ trail = [], status, offer }) {
  const withdrawn = status === "withdrawn";
  // The process stops at the first published "not selected" or "waitlisted" round (waitlist is not an error).
  const stopIndex = trail.findIndex((step) => step.published && ["rejected", "waitlisted"].includes(step.result));
  const lastPublished = trail.reduce((acc, step, index) => (step.published ? index : acc), -1);
  const reached = stopIndex >= 0 ? stopIndex : lastPublished;
  // Steps: Eligible, Applied, rounds…, Final.
  const active = withdrawn ? 1 : offer ? trail.length + 2 : reached + 2 + (stopIndex >= 0 ? 0 : 1);

  return (
    <Box sx={{ overflowX: "auto", pb: 1 }}>
      <Stepper activeStep={active} alternativeLabel sx={{ minWidth: Math.max(420, (trail.length + 3) * 110) }}>
        <Step completed>
          <StepLabel>Eligible</StepLabel>
        </Step>
        <Step completed={!withdrawn}>
          <StepLabel error={withdrawn}>{withdrawn ? "Withdrawn" : "Applied"}</StepLabel>
        </Step>
        {trail.map((step, index) => {
          const beyondStop = stopIndex >= 0 && index > stopIndex;
          return (
            <Step key={step.round_id} completed={step.published && step.result === "selected"}>
              <StepLabel
                error={step.published && step.result === "rejected"}
                optional={
                  <Box sx={{ display: "flex", flexDirection: "column", alignItems: "center", gap: 0.5, mt: 0.5 }}>
                    {step.published && step.attendance && (
                      <Typography variant="caption" color="text.secondary">
                        {step.attendance === "yes" ? "Appeared" : "Absent"}
                      </Typography>
                    )}
                    {resultChip(step)}
                    {!step.published && !withdrawn && !beyondStop && index === reached + 1 && (
                      <Typography variant="caption" color="text.secondary">
                        Awaiting result
                      </Typography>
                    )}
                  </Box>
                }
              >
                {step.name}
              </StepLabel>
            </Step>
          );
        })}
        <Step completed={Boolean(offer)}>
          <StepLabel>{offer ? "Offer" : "Final"}</StepLabel>
        </Step>
      </Stepper>
    </Box>
  );
}
