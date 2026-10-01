"use client";

import { useEffect, useMemo, useState } from "react";
import Link from "next/link";
import {
  Alert,
  Box,
  Button,
  Card,
  CardContent,
  IconButton,
  LinearProgress,
  List,
  ListItemButton,
  ListItemText,
  Popover,
  Stack,
  Typography,
  useMediaQuery,
  useTheme,
} from "@mui/material";
import ChevronLeftIcon from "@mui/icons-material/ChevronLeft";
import ChevronRightIcon from "@mui/icons-material/ChevronRight";

const typeColors = { event: "#1e3a8a", deadline: "#7B1113", round: "#b45309" };
const typeLabels = { event: "Event", deadline: "Application deadline", round: "Selection round" };
const WEEKDAYS = ["Mon", "Tue", "Wed", "Thu", "Fri", "Sat", "Sun"];

const monthKey = (date) => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, "0")}`;
const dayKey = (date) => `${monthKey(date)}-${String(date.getDate()).padStart(2, "0")}`;
// Items are bucketed by their IST date (the server windows months in IST), whatever the browser's timezone.
const istDayKey = (value) => new Date(value).toLocaleDateString("en-CA", { timeZone: "Asia/Kolkata" });
const time = (value) => new Date(value).toLocaleTimeString("en-IN", { hour: "2-digit", minute: "2-digit", timeZone: "Asia/Kolkata" });

/**
 * Plain-MUI month grid (spec M8.3). `api(path)` fetches `${endpoint}?month=YYYY-MM` → { items: [{type, at, title, subtitle, link, draft}] }.
 */
export default function MonthCalendar({ api, endpoint }) {
  const theme = useTheme();
  const compact = useMediaQuery(theme.breakpoints.down("sm"));
  const [cursor, setCursor] = useState(() => {
    const now = new Date();
    return new Date(now.getFullYear(), now.getMonth(), 1);
  });
  const [items, setItems] = useState(null);
  const [error, setError] = useState(null);
  const [popover, setPopover] = useState(null); // { anchor, day }

  useEffect(() => {
    // Ignore responses for a month the user has already navigated away from.
    let current = true;
    api(`${endpoint}?month=${monthKey(cursor)}`)
      .then((response) => {
        if (current) {
          setItems(response.items ?? []);
          setError(null);
        }
      })
      .catch((e) => {
        if (current) {
          setError(e instanceof Error ? e.message : "Failed to load the calendar.");
          setItems([]);
        }
      });
    return () => {
      current = false;
    };
  }, [api, endpoint, cursor]);

  const byDay = useMemo(() => {
    const map = {};
    (items ?? []).forEach((item) => {
      const key = istDayKey(item.at);
      (map[key] ??= []).push(item);
    });
    return map;
  }, [items]);

  const cells = useMemo(() => {
    const first = new Date(cursor);
    const offset = (first.getDay() + 6) % 7; // Monday first
    const days = new Date(cursor.getFullYear(), cursor.getMonth() + 1, 0).getDate();
    const list = Array.from({ length: offset }, () => null);
    for (let d = 1; d <= days; d++) list.push(new Date(cursor.getFullYear(), cursor.getMonth(), d));
    while (list.length % 7 !== 0) list.push(null);
    return list;
  }, [cursor]);

  const todayKey = dayKey(new Date());
  const move = (delta) => {
    setItems(null);
    setCursor((c) => new Date(c.getFullYear(), c.getMonth() + delta, 1));
  };
  const popItems = popover ? byDay[dayKey(popover.day)] ?? [] : [];

  return (
    <Card>
      <CardContent>
        <Stack direction="row" alignItems="center" justifyContent="space-between" sx={{ mb: 2 }}>
          <IconButton onClick={() => move(-1)} aria-label="Previous month">
            <ChevronLeftIcon />
          </IconButton>
          <Stack alignItems="center">
            <Typography variant="h6" fontWeight={700}>
              {cursor.toLocaleDateString("en-IN", { month: "long", year: "numeric" })}
            </Typography>
            <Button size="small" onClick={() => setCursor(new Date(new Date().getFullYear(), new Date().getMonth(), 1))}>
              Today
            </Button>
          </Stack>
          <IconButton onClick={() => move(1)} aria-label="Next month">
            <ChevronRightIcon />
          </IconButton>
        </Stack>

        <Stack direction="row" spacing={2} flexWrap="wrap" useFlexGap sx={{ mb: 1.5 }}>
          {Object.entries(typeLabels).map(([type, label]) => (
            <Stack key={type} direction="row" spacing={0.75} alignItems="center">
              <Box sx={{ width: 10, height: 10, borderRadius: "50%", bgcolor: typeColors[type] }} />
              <Typography variant="caption">{label}</Typography>
            </Stack>
          ))}
        </Stack>

        {error && <Alert severity="error">{error}</Alert>}
        {!items && <LinearProgress sx={{ mb: 1 }} />}

        <Box sx={{ display: "grid", gridTemplateColumns: "repeat(7, minmax(0, 1fr))", border: 1, borderColor: "divider", borderRadius: 1, overflow: "hidden" }}>
          {WEEKDAYS.map((d) => (
            <Box key={d} sx={{ p: 0.75, textAlign: "center", bgcolor: "grey.50", borderBottom: 1, borderColor: "divider" }}>
              <Typography variant="caption" fontWeight={700}>
                {compact ? d[0] : d}
              </Typography>
            </Box>
          ))}
          {cells.map((date, index) => {
            const dayItems = date ? byDay[dayKey(date)] ?? [] : [];
            const isToday = date && dayKey(date) === todayKey;
            return (
              <Box
                key={index}
                onClick={(e) => date && dayItems.length > 0 && setPopover({ anchor: e.currentTarget, day: date })}
                sx={{
                  minHeight: { xs: 56, md: 104 },
                  p: 0.5,
                  borderRight: (index + 1) % 7 === 0 ? 0 : 1,
                  borderBottom: 1,
                  borderColor: "divider",
                  bgcolor: date ? "background.paper" : "grey.50",
                  cursor: dayItems.length ? "pointer" : "default",
                  "&:hover": dayItems.length ? { bgcolor: "grey.100" } : undefined,
                  minWidth: 0,
                }}
              >
                {date && (
                  <>
                    <Typography
                      variant="caption"
                      fontWeight={isToday ? 800 : 500}
                      sx={isToday ? { bgcolor: "primary.main", color: "white", borderRadius: "50%", px: 0.6, py: 0.1 } : undefined}
                    >
                      {date.getDate()}
                    </Typography>
                    {compact ? (
                      <Stack direction="row" spacing={0.25} flexWrap="wrap" useFlexGap sx={{ mt: 0.5 }}>
                        {dayItems.slice(0, 6).map((item, i) => (
                          <Box key={i} sx={{ width: 7, height: 7, borderRadius: "50%", bgcolor: typeColors[item.type] }} />
                        ))}
                      </Stack>
                    ) : (
                      <Stack spacing={0.25} sx={{ mt: 0.5 }}>
                        {dayItems.slice(0, 3).map((item, i) => (
                          <Typography
                            key={i}
                            variant="caption"
                            noWrap
                            sx={{ display: "block", px: 0.5, borderRadius: 0.5, color: "white", bgcolor: typeColors[item.type], opacity: item.draft ? 0.55 : 1 }}
                          >
                            {time(item.at)} {item.title}
                          </Typography>
                        ))}
                        {dayItems.length > 3 && (
                          <Typography variant="caption" color="text.secondary">
                            +{dayItems.length - 3} more
                          </Typography>
                        )}
                      </Stack>
                    )}
                  </>
                )}
              </Box>
            );
          })}
        </Box>

        <Popover
          open={Boolean(popover)}
          anchorEl={popover?.anchor}
          onClose={() => setPopover(null)}
          anchorOrigin={{ vertical: "bottom", horizontal: "center" }}
          transformOrigin={{ vertical: "top", horizontal: "center" }}
        >
          <Box sx={{ p: 1.5, maxWidth: 340 }}>
            <Typography variant="subtitle2" fontWeight={700}>
              {popover?.day.toLocaleDateString("en-IN", { weekday: "long", day: "numeric", month: "long" })}
            </Typography>
            <List dense disablePadding>
              {popItems.map((item, i) => (
                <ListItemButton key={i} component={Link} href={item.link} onClick={() => setPopover(null)} sx={{ borderLeft: 3, borderColor: typeColors[item.type], my: 0.5 }}>
                  <ListItemText
                    primary={`${time(item.at)} · ${item.title}${item.draft ? " (draft)" : ""}`}
                    secondary={[typeLabels[item.type], item.subtitle].filter(Boolean).join(" · ")}
                  />
                </ListItemButton>
              ))}
            </List>
          </Box>
        </Popover>
      </CardContent>
    </Card>
  );
}
