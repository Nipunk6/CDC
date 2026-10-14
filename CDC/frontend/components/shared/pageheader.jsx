"use client";

import Link from "next/link";
import { Box, Button, Stack, Typography } from "@mui/material";
import ArrowBackIcon from "@mui/icons-material/ArrowBack";

// The header of every signed-in page (design brief §4): a quiet "Back" link when the page has a parent, the title,
// one line of explanation, and the page's actions on the right. It sits on the page background: no box, no colour.
// `icon` is accepted for the pages that pass one and is not drawn; the sidebar already shows where you are.
/**
 * @param {object} props
 * @param {React.ReactNode} props.title
 * @param {React.ReactNode} [props.subtitle]
 * @param {string} [props.backHref]
 * @param {string} [props.backLabel]
 * @param {React.ReactNode} [props.actions]
 * @param {React.ReactNode} [props.icon]  accepted for older callers; not drawn
 */
export default function PageHeader({ title, subtitle, backHref, backLabel = "Back", actions }) {
  return (
    <Box component="header" sx={{ mb: 3 }}>
      {backHref && (
        <Button component={Link} href={backHref} size="small" color="inherit" startIcon={<ArrowBackIcon fontSize="small" />} sx={{ ml: -0.75, mb: 0.5, color: "text.secondary", fontWeight: 500 }}>
          {backLabel}
        </Button>
      )}
      <Stack direction={{ xs: "column", md: "row" }} justifyContent="space-between" alignItems={{ md: "flex-start" }} spacing={2}>
        <Box sx={{ minWidth: 0 }}>
          <Typography component="h1" variant="h4" sx={{ fontSize: { xs: "1.25rem", sm: "1.5rem" }, lineHeight: { xs: "28px", sm: "32px" }, wordBreak: "break-word" }}>
            {title}
          </Typography>
          {subtitle && (
            <Typography variant="body2" color="text.secondary" sx={{ mt: 0.5, maxWidth: "80ch" }}>
              {subtitle}
            </Typography>
          )}
        </Box>
        {actions && (
          <Stack direction="row" spacing={1} flexWrap="wrap" useFlexGap sx={{ flexShrink: 0 }}>
            {actions}
          </Stack>
        )}
      </Stack>
    </Box>
  );
}
