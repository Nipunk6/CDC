"use client";

import { useState } from "react";
import { Avatar, Box, Button, Card, CardContent, Chip, Stack, Typography } from "@mui/material";
import PlaceIcon from "@mui/icons-material/PlaceOutlined";
import VideocamIcon from "@mui/icons-material/VideocamOutlined";

import { stripHtml } from "@/components/forms/shared";
import { IST, formatDateTime } from "@/lib/format";

const TYPES = { ppt: "Pre-Placement Talk", workshop: "Workshop", webinar: "Webinar", other: "Event" };

// Read-only event card (student events page, company Drives page). Rich text is shown as plain text, like Phase 1.
export default function EventCard({ event }) {
  const [now] = useState(() => Date.now());
  const past = new Date(event.starts_at).getTime() < now;
  // Keep paragraph breaks: stripHtml alone would join "<p>A</p><p>B</p>" into "AB".
  const description = stripHtml((event.description ?? "").replace(/<\/p>|<br\s*\/?>/gi, "\n")).trim();

  return (
    <Card variant="outlined" sx={{ height: "100%", opacity: past ? 0.7 : 1 }}>
      <CardContent>
        <Stack direction="row" spacing={1.5}>
          <Box sx={{ textAlign: "center", minWidth: 56 }}>
            <Typography variant="caption" color="primary.main" fontWeight={700}>
              {new Date(event.starts_at).toLocaleDateString("en-IN", { month: "short", timeZone: IST }).toUpperCase()}
            </Typography>
            <Typography variant="h5" fontWeight={800} lineHeight={1}>
              {new Date(event.starts_at).toLocaleDateString("en-IN", { day: "numeric", timeZone: IST })}
            </Typography>
          </Box>
          <Box sx={{ minWidth: 0, flex: 1 }}>
            <Stack direction="row" spacing={1} alignItems="center" flexWrap="wrap" useFlexGap>
              <Chip size="small" variant="outlined" color="secondary" label={TYPES[event.event_type] ?? "Event"} />
              {past && <Chip size="small" label="Past" />}
            </Stack>
            <Typography fontWeight={700} sx={{ mt: 0.5, wordBreak: "break-word" }}>
              {event.title}
            </Typography>
            {event.company && (
              <Stack direction="row" spacing={0.75} alignItems="center">
                <Avatar src={event.company.logo_url ?? undefined} sx={{ width: 20, height: 20, fontSize: 12 }}>
                  {event.company.name?.[0]}
                </Avatar>
                <Typography variant="body2" color="text.secondary">
                  {event.company.name}
                </Typography>
              </Stack>
            )}
            <Typography variant="body2" sx={{ mt: 0.5 }}>
              {formatDateTime(event.starts_at)}
            </Typography>
            {event.venue && (
              <Typography variant="body2" color="text.secondary" sx={{ display: "flex", alignItems: "center", gap: 0.5 }}>
                <PlaceIcon fontSize="inherit" /> {event.venue}
              </Typography>
            )}
            {description && (
              <Typography variant="body2" color="text.secondary" sx={{ mt: 1, whiteSpace: "pre-wrap" }}>
                {description}
              </Typography>
            )}
            {event.meeting_link && !past && (
              <Button size="small" startIcon={<VideocamIcon />} href={event.meeting_link} target="_blank" rel="noopener" sx={{ mt: 1 }}>
                Join
              </Button>
            )}
          </Box>
        </Stack>
      </CardContent>
    </Card>
  );
}
