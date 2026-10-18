"use client";

import {
  Box,
  Card,
  CardContent,
  Chip,
  Divider,
  List,
  ListItem,
  ListItemText,
  Stack,
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TableRow,
  Typography,
} from "@mui/material";

const blockColor = { all: "error", internships_only: "warning" };

const RuleList = ({ items }) => (
  <List dense disablePadding sx={{ listStyleType: "disc", pl: 2.5 }}>
    {items.map((item) => (
      <ListItem key={item} disableGutters sx={{ display: "list-item", py: 0.25 }}>
        <ListItemText primary={item} slotProps={{ primary: { variant: "body2" } }} />
      </ListItem>
    ))}
  </List>
);

// Rules for a Full Time or Internship placement, as sent by the API (built from the code that enforces them).
export default function CycleRulesCard({ cycle }) {
  const rules = cycle.rules;
  if (!rules) return null;

  return (
    <Card>
      <CardContent>
        <Stack direction="row" spacing={1} alignItems="center" sx={{ mb: 0.5 }}>
          <Typography variant="h6" fontWeight={700} sx={{ textAlign: "left" }}>
            Placement Rules
          </Typography>
          <Chip size="small" variant="outlined" label={cycle.type === "fulltime" ? "Full Time" : "Internship"} />
        </Stack>
        <Divider sx={{ mb: 2 }} />

        <Stack spacing={2.5}>
          <Box>
            <Typography variant="subtitle2" fontWeight={700} gutterBottom>
              Job profiles
            </Typography>
            <Typography variant="body2">{rules.job_profiles}</Typography>
          </Box>

          <Box>
            <Typography variant="subtitle2" fontWeight={700} gutterBottom>
              Offers and default blocks
            </Typography>
            <TableContainer>
              <Table size="small">
                <TableHead>
                  <TableRow>
                    <TableCell>Offer type</TableCell>
                    <TableCell>Default block</TableCell>
                  </TableRow>
                </TableHead>
                <TableBody>
                  {rules.offer_types.map((row) => (
                    <TableRow key={row.type}>
                      <TableCell>
                        <Typography variant="body2">{row.label}</Typography>
                      </TableCell>
                      <TableCell>
                        <Chip
                          size="small"
                          color={blockColor[row.block] ?? "default"}
                          variant={row.block ? "filled" : "outlined"}
                          label={row.block_label}
                          sx={{ height: "auto", "& .MuiChip-label": { whiteSpace: "normal", py: 0.25 } }}
                        />
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </TableContainer>
          </Box>

          <Box>
            <Typography variant="subtitle2" fontWeight={700} gutterBottom>
              Who can apply to a job profile
            </Typography>
            <RuleList items={rules.eligibility} />
          </Box>

          <Box>
            <Typography variant="subtitle2" fontWeight={700} gutterBottom>
              General
            </Typography>
            <RuleList items={rules.general} />
          </Box>
        </Stack>
      </CardContent>
    </Card>
  );
}
