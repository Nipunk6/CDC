"use client";

import Link from "next/link";
import { Avatar, Box, Card, CardActionArea, CardContent, Chip, Stack, Tooltip, Typography } from "@mui/material";
import CheckCircleIcon from "@mui/icons-material/CheckCircle";
import ScheduleIcon from "@mui/icons-material/Schedule";
import PlaceIcon from "@mui/icons-material/PlaceOutlined";

import { formatDateTime, formatMoney } from "@/lib/format";

export const countdown = (deadline) => {
  const ms = new Date(deadline).getTime() - Date.now();
  if (Number.isNaN(ms)) return "";
  if (ms <= 0) return "Closed";
  const hours = Math.floor(ms / 3600000);
  if (hours < 1) return `${Math.max(1, Math.floor(ms / 60000))} min left`;
  if (hours < 48) return `${hours} h left`;
  return `${Math.floor(hours / 24)} days left`;
};

export const compensationText = (comp) => {
  if (!comp) return "";
  if (comp.ctc_annual) return `${formatMoney(comp.ctc_annual, comp.currency)} CTC`;
  if (comp.stipend_monthly) return `${formatMoney(comp.stipend_monthly, comp.currency)}/month${comp.duration_weeks ? ` · ${comp.duration_weeks} wks` : ""}`;
  return "";
};

export default function PostingCard({ posting }) {
  const applied = posting.application?.status === "applied";
  const eligible = posting.eligibility?.eligible;
  const open = posting.accepts_applications;

  return (
    <Card variant="outlined" sx={{ height: "100%", "&:hover": { borderColor: "primary.main" } }}>
      <CardActionArea component={Link} href={`/student/postings/${posting.id}`} sx={{ height: "100%", alignItems: "stretch" }}>
        <CardContent>
          <Stack direction="row" spacing={1.5} alignItems="flex-start">
            <Avatar src={posting.company?.logo_url ?? undefined} variant="rounded" sx={{ width: 48, height: 48, bgcolor: "primary.main" }}>
              {posting.company?.name?.[0]}
            </Avatar>
            <Box sx={{ minWidth: 0, flex: 1 }}>
              <Stack direction="row" spacing={1} justifyContent="space-between" alignItems="flex-start">
                <Box sx={{ minWidth: 0 }}>
                  <Typography fontWeight={700} sx={{ wordBreak: "break-word", lineHeight: 1.3 }}>
                    {posting.title}
                  </Typography>
                  <Typography variant="body2" color="text.secondary" noWrap>
                    {posting.company?.name}
                  </Typography>
                </Box>
                {applied && (
                  <Tooltip title="You have applied">
                    <CheckCircleIcon color="success" />
                  </Tooltip>
                )}
              </Stack>
              <Stack direction="row" spacing={0.75} flexWrap="wrap" useFlexGap sx={{ mt: 1 }}>
                <Chip size="small" variant="outlined" color={posting.type === "fulltime" ? "primary" : "secondary"} label={posting.offer_label ?? (posting.type === "fulltime" ? "Full Time" : "Internship")} />
                {open ? (
                  <Chip size="small" icon={<ScheduleIcon />} color="warning" variant="outlined" label={countdown(posting.application_deadline)} />
                ) : (
                  <Chip
                    size="small"
                    variant="outlined"
                    label={posting.status === "completed" ? "Completed" : posting.status === "in_process" ? "In process" : "Applications closed"}
                  />
                )}
                {applied ? (
                  <Chip size="small" color="success" label="Applied" />
                ) : eligible ? (
                  <Chip size="small" color="success" variant="outlined" label="Eligible" />
                ) : (
                  <Tooltip title={(posting.eligibility?.reasons ?? []).join(" · ")}>
                    <Chip size="small" color="error" variant="outlined" label={posting.eligibility?.reasons?.[0] ?? "Not eligible"} sx={{ maxWidth: "100%" }} />
                  </Tooltip>
                )}
              </Stack>
              <Stack direction="row" spacing={2} sx={{ mt: 1 }} flexWrap="wrap" useFlexGap>
                {compensationText(posting.compensation) && (
                  <Typography variant="body2" fontWeight={600}>
                    {compensationText(posting.compensation)}
                  </Typography>
                )}
                {posting.location && (
                  <Typography variant="body2" color="text.secondary" sx={{ display: "flex", alignItems: "center", gap: 0.25 }}>
                    <PlaceIcon fontSize="inherit" /> {posting.location}
                  </Typography>
                )}
              </Stack>
              <Typography variant="caption" color="text.secondary">
                Apply by {formatDateTime(posting.application_deadline)}
              </Typography>
            </Box>
          </Stack>
        </CardContent>
      </CardActionArea>
    </Card>
  );
}
