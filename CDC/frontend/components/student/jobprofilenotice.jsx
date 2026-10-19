"use client";

import { Box, Typography } from "@mui/material";

const Heading = ({ children }) => (
  <Typography variant="body2" fontWeight={700} sx={{ mt: 2, mb: 0.75 }}>
    {children}
  </Typography>
);

const Numbered = ({ items, children }) => (
  <Box component="ol" sx={{ m: 0, pl: 3, "& > li": { mb: 0.5, typography: "body2" } }}>
    {children ?? items.map((item) => <li key={item}>{item}</li>)}
  </Box>
);

/**
 * The opened "Open for applications" notice of a job profile (Superset-style card): applicable courses, eligibility,
 * hiring stages and the deadline. Built from the notice's `job_profile` data with plain components, never from HTML (D76).
 */
export default function JobProfileNotice({ details }) {
  const { company, title, institute, courses = [], eligibility = [], stages = [], deadline, message } = details;

  return (
    <Box sx={{ wordBreak: "break-word" }}>
      {message && (
        // The CDC's own message for this opening, written when it was announced (plain text).
        <Typography variant="body2" sx={{ whiteSpace: "pre-line", mb: 1.5 }}>
          {message}
        </Typography>
      )}
      <Typography variant="body2">
        Applications are now being accepted for <strong>{company}</strong>&apos;s Job Profile: <strong>{title}</strong>.
      </Typography>
      <Typography variant="body2" sx={{ mt: 1.5 }}>
        Eligible students will be able to find this job profile under <strong>JOB PROFILES</strong> options, and apply.
      </Typography>

      {courses.length > 0 && (
        <>
          <Heading>Applicable Courses</Heading>
          <Typography variant="body2" fontWeight={700} sx={{ mb: 0.75 }}>
            {institute}
          </Typography>
          <Numbered items={courses} />
        </>
      )}

      {eligibility.length > 0 && (
        <>
          <Heading>Eligibility</Heading>
          <Numbered>
            {eligibility.map((group) => (
              <li key={group.label}>
                {group.label}
                <Box component="ul" sx={{ mt: 0.5, mb: 0, pl: 2.5, listStyleType: "disc", "& > li": { typography: "body2", color: "text.secondary" } }}>
                  {group.points.map((point) => (
                    <li key={point}>{point}</li>
                  ))}
                </Box>
              </li>
            ))}
          </Numbered>
        </>
      )}

      {stages.length > 0 && (
        <>
          <Heading>Hiring Process - Stages</Heading>
          <Numbered items={stages} />
        </>
      )}

      {deadline && (
        <Typography variant="body2" fontWeight={700} sx={{ mt: 2 }}>
          Deadline for applications is {deadline}.
        </Typography>
      )}
    </Box>
  );
}
