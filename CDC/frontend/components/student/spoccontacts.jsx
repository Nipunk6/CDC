"use client";

import { Card, CardContent, Divider, Link as MuiLink, Stack, Typography } from "@mui/material";
import PhoneIcon from "@mui/icons-material/PhoneOutlined";
import MailIcon from "@mui/icons-material/MailOutline";

/**
 * Job profile → "SPOC for this company" (P10.2): the SCPT members a student can contact about this job profile.
 */
export default function SpocContacts({ spocs, sx }) {
  return (
    <Card sx={sx}>
      <CardContent>
        <Typography variant="subtitle1" fontWeight={700}>
          SPOC for this company
        </Typography>
        <Typography variant="body2" color="text.secondary" sx={{ mb: 1.5 }}>
          Contact them with questions about this job profile.
        </Typography>
        <Stack spacing={1.5} divider={<Divider flexItem />}>
          {spocs.map((spoc) => (
            <div key={spoc.roll_no}>
              <Typography fontWeight={600}>{spoc.name}</Typography>
              <Typography variant="caption" color="text.secondary" display="block">
                {spoc.roll_no}
              </Typography>
              {spoc.phone && (
                <Stack direction="row" spacing={1} alignItems="center" sx={{ mt: 0.5 }}>
                  <PhoneIcon fontSize="small" color="action" />
                  <MuiLink href={`tel:${spoc.phone}`} underline="hover">
                    {spoc.phone}
                  </MuiLink>
                </Stack>
              )}
              {spoc.email && (
                <Stack direction="row" spacing={1} alignItems="center" sx={{ mt: 0.5, minWidth: 0 }}>
                  <MailIcon fontSize="small" color="action" />
                  <MuiLink href={`mailto:${spoc.email}`} underline="hover" sx={{ wordBreak: "break-all" }}>
                    {spoc.email}
                  </MuiLink>
                </Stack>
              )}
            </div>
          ))}
        </Stack>
      </CardContent>
    </Card>
  );
}
