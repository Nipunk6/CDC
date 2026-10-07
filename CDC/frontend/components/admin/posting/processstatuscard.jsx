"use client";

import Link from "next/link";
import { Box, Button, Card, CardContent, Stack, Typography } from "@mui/material";
import CheckCircleIcon from "@mui/icons-material/CheckCircle";
import RadioButtonUncheckedIcon from "@mui/icons-material/RadioButtonUnchecked";
import EmojiEventsIcon from "@mui/icons-material/EmojiEvents";
import ArrowForwardIcon from "@mui/icons-material/ArrowForward";

/**
 * Process status (Superset parity S1.5): which stage is in progress, every stage with ✓ / 🏆 linking to its
 * shortlist page, and a "process is complete" card once final results are announced.
 */
export default function ProcessStatusCard({ posting }) {
  const rounds = posting.rounds ?? [];
  if (rounds.length === 0 || posting.status === "cancelled") return null;

  const complete = posting.status === "completed";
  const current = complete ? null : rounds.find((r) => r.status !== "completed") ?? null;
  const stageHref = (round) => `/admin/postings/${posting.id}/rounds/${round.id}`;

  return (
    <Card variant="outlined" sx={{ borderColor: complete ? "success.main" : "primary.main" }}>
      <CardContent>
        <Stack direction={{ xs: "column", md: "row" }} spacing={2} justifyContent="space-between" alignItems={{ md: "center" }}>
          <Box>
            {complete ? (
              <>
                <Typography variant="subtitle1" fontWeight={700} color="success.main">
                  The process is complete — final selected list
                </Typography>
                <Typography variant="body2" color="text.secondary">
                  Final results have been announced. Later selections can still be added on Shortlist for Offer.
                </Typography>
              </>
            ) : (
              <>
                <Typography variant="subtitle1" fontWeight={700}>
                  {current ? `${current.name} is in progress.` : "Every stage is decided."}
                </Typography>
                <Typography variant="body2" color="text.secondary">
                  {posting.status === "open"
                    ? "Decisions can be entered once applications close."
                    : "Decide the stage on its shortlist page, then publish it to students."}
                </Typography>
              </>
            )}
          </Box>
          {complete ? (
            <Button component={Link} href={`/admin/postings/${posting.id}/results`} variant="contained" color="success" endIcon={<ArrowForwardIcon />}>
              Shortlist for Offer
            </Button>
          ) : (
            current && (
              <Button component={Link} href={current.is_final ? `/admin/postings/${posting.id}/results` : stageHref(current)} variant="contained" endIcon={<ArrowForwardIcon />}>
                {current.is_final ? "Shortlist for Offer" : `Shortlist for ${current.name}`}
              </Button>
            )
          )}
        </Stack>

        <Stack direction={{ xs: "column", sm: "row" }} spacing={{ xs: 0.5, sm: 2 }} flexWrap="wrap" useFlexGap sx={{ mt: 2 }}>
          {rounds.map((round, index) => {
            const done = round.status === "completed";
            const Icon = round.is_final ? EmojiEventsIcon : done ? CheckCircleIcon : RadioButtonUncheckedIcon;
            return (
              <Button
                key={round.id}
                component={Link}
                href={stageHref(round)}
                size="small"
                color={done ? "success" : round.id === current?.id ? "primary" : "inherit"}
                startIcon={<Icon fontSize="small" sx={round.is_final ? { color: done ? "warning.main" : "text.disabled" } : undefined} />}
                sx={{ justifyContent: "flex-start", textTransform: "none", fontWeight: round.id === current?.id ? 700 : 500 }}
              >
                {index + 1}. {round.name}
              </Button>
            );
          })}
        </Stack>
      </CardContent>
    </Card>
  );
}
