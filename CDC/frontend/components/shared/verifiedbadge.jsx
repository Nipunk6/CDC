"use client";

import { Tooltip } from "@mui/material";
import VerifiedIcon from "@mui/icons-material/Verified";
import NewReleasesOutlinedIcon from "@mui/icons-material/NewReleasesOutlined";

// Profile verification badge shown after a student's name: green when the CDC has verified every student-changeable
// field and resume, orange otherwise. The tooltip and aria-label carry the meaning, so colour is not the only signal.
export default function VerifiedBadge({ verified, size = 18, verifiedText, unverifiedText }) {
  const title = verified
    ? (verifiedText ?? "Verified: the CDC has checked every profile field and resume")
    : (unverifiedText ?? "Unverified: some profile fields or resumes are waiting for the CDC's check");

  return (
    <Tooltip title={title}>
      {verified ? (
        <VerifiedIcon color="success" sx={{ fontSize: size, flexShrink: 0 }} aria-label="Verified" role="img" />
      ) : (
        <NewReleasesOutlinedIcon color="warning" sx={{ fontSize: size, flexShrink: 0 }} aria-label="Unverified" role="img" />
      )}
    </Tooltip>
  );
}
