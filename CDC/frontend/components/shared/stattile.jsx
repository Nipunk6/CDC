"use client";

import Link from "next/link";
import { Box, Paper, Typography } from "@mui/material";

// A dashboard number (design brief §5): a label, a large tabular number, an optional one-line note or link.
// Flat and bordered; the number is the only emphasis. `tone` colours the number when the value needs attention.
/**
 * @param {object} props
 * @param {string} props.label
 * @param {React.ReactNode} props.value
 * @param {React.ReactNode} [props.note]
 * @param {string} [props.href]
 * @param {string} [props.linkLabel]
 * @param {string} [props.tone]  a theme colour path such as "warning.dark"
 */
export default function StatTile({ label, value, note, href, linkLabel, tone = "text.primary" }) {
  return (
    <Paper sx={{ p: 2, height: "100%", borderRadius: 3, display: "flex", flexDirection: "column", gap: 0.5 }}>
      <Typography variant="body2" color="text.secondary">
        {label}
      </Typography>
      <Typography component="div" sx={{ fontSize: "1.75rem", lineHeight: "36px", fontWeight: 700, fontVariantNumeric: "tabular-nums", color: tone }}>
        {value}
      </Typography>
      {note && (
        <Typography variant="caption" color="text.secondary">
          {note}
        </Typography>
      )}
      {href && (
        <Box sx={{ mt: "auto", pt: 0.5 }}>
          <Typography component={Link} href={href} variant="body2" sx={{ color: "primary.main", fontWeight: 600, textDecoration: "none", "&:hover": { textDecoration: "underline" } }}>
            {linkLabel}
          </Typography>
        </Box>
      )}
    </Paper>
  );
}
