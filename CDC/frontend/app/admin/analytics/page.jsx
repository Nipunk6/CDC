"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import {
  Alert,
  Box,
  Card,
  CardContent,
  FormControl,
  Grid2 as Grid,
  InputLabel,
  LinearProgress,
  MenuItem,
  Select,
  Stack,
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TableRow,
  Typography,
  useTheme,
} from "@mui/material";
import InsightsIcon from "@mui/icons-material/Insights";
import { BarChart } from "@mui/x-charts/BarChart";
import { LineChart } from "@mui/x-charts/LineChart";
import { PieChart } from "@mui/x-charts/PieChart";

import PageHeader from "@/components/shared/pageheader";
import { adminApi } from "@/lib/adminapi";
import { shortProgramme } from "@/lib/usecatalogue";
import { formatMoney } from "@/lib/format";

const lpa = (value) => (value ? `${(value / 100000).toFixed(value % 100000 ? 1 : 0)} LPA` : "—");

function Stat({ label, value, hint, color }) {
  return (
    <Card sx={{ height: "100%" }}>
      <CardContent>
        <Typography variant="body2" color="text.secondary">
          {label}
        </Typography>
        <Typography variant="h4" fontWeight={800} color={color ?? "text.primary"}>
          {value}
        </Typography>
        {hint && (
          <Typography variant="caption" color="text.secondary">
            {hint}
          </Typography>
        )}
      </CardContent>
    </Card>
  );
}

function Panel({ title, children, action }) {
  return (
    <Card sx={{ height: "100%" }}>
      <CardContent>
        <Stack direction="row" justifyContent="space-between" alignItems="center" sx={{ mb: 1 }}>
          <Typography variant="subtitle1" fontWeight={700}>
            {title}
          </Typography>
          {action}
        </Stack>
        {children}
      </CardContent>
    </Card>
  );
}

function PercentBar({ value }) {
  return (
    <Stack direction="row" spacing={1} alignItems="center">
      <Box sx={{ flex: 1, minWidth: 60 }}>
        <LinearProgress variant="determinate" value={Math.min(100, value)} sx={{ height: 8, borderRadius: 4 }} />
      </Box>
      <Typography variant="caption" sx={{ minWidth: 42, textAlign: "right" }}>
        {value}%
      </Typography>
    </Stack>
  );
}

export default function AdminAnalyticsPage() {
  const theme = useTheme();
  const [overview, setOverview] = useState(null);
  const [cycleId, setCycleId] = useState("");
  const [data, setData] = useState(null);
  const [error, setError] = useState(null);

  useEffect(() => {
    adminApi("/admin/dashboard/overview")
      .then((response) => {
        setOverview(response.cycles ?? []);
        if (response.cycles?.length) setCycleId(String(response.cycles[0].id));
      })
      .catch((e) => setError(e.message));
  }, []);

  useEffect(() => {
    if (!cycleId) return;
    let cancelled = false;
    adminApi(`/admin/dashboard/cycle/${cycleId}`)
      .then((response) => !cancelled && setData(response))
      .catch((e) => !cancelled && setError(e.message));
    return () => {
      cancelled = true;
    };
  }, [cycleId]);

  // Switching cycle clears the previous cycle's data and any old error, so a loader shows meanwhile.
  const selectCycle = (id) => {
    if (id === cycleId) return;
    setError(null);
    setData(null);
    setCycleId(id);
  };

  const palette = [theme.palette.primary.main, theme.palette.secondary.main, "#b45309", "#047857", "#6d28d9", "#0e7490"];

  return (
    <>
      <PageHeader icon={<InsightsIcon />} title="Placement Analytics" subtitle="Placement % uses every enrolled student as the denominator." backHref="/admin" backLabel="Back to Dashboard" />
      {error && (
        <Alert severity="error" sx={{ mb: 2 }}>
          {error}
        </Alert>
      )}
      {!overview && !error && <LinearProgress />}
      {overview && overview.length === 0 && <Alert severity="info">Create a placement cycle to see analytics.</Alert>}

      {overview && overview.length > 0 && (
        <Stack spacing={3}>
          <Panel title="All cycles">
            <TableContainer>
              <Table size="small">
                <TableHead>
                  <TableRow>
                    <TableCell>Cycle</TableCell>
                    <TableCell>Enrolled</TableCell>
                    <TableCell>Placed</TableCell>
                    <TableCell sx={{ minWidth: 160 }}>Placed %</TableCell>
                    <TableCell>Offers</TableCell>
                    <TableCell>Postings</TableCell>
                  </TableRow>
                </TableHead>
                <TableBody>
                  {overview.map((c) => (
                    <TableRow key={c.id} hover selected={String(c.id) === cycleId} onClick={() => selectCycle(String(c.id))} sx={{ cursor: "pointer" }}>
                      <TableCell>
                        <Link href={`/admin/placement-cycles/${c.id}`} onClick={(e) => e.stopPropagation()}>
                          {c.name}
                        </Link>
                      </TableCell>
                      <TableCell>{c.enrolled}</TableCell>
                      <TableCell>{c.placed}</TableCell>
                      <TableCell>
                        <PercentBar value={c.placed_percent} />
                      </TableCell>
                      <TableCell>{c.offers}</TableCell>
                      <TableCell>{c.postings}</TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </TableContainer>
          </Panel>

          <FormControl size="small" sx={{ maxWidth: 320 }}>
            <InputLabel id="an-cycle">Cycle</InputLabel>
            <Select labelId="an-cycle" label="Cycle" value={cycleId} onChange={(e) => selectCycle(e.target.value)}>
              {overview.map((c) => (
                <MenuItem key={c.id} value={String(c.id)}>
                  {c.name}
                </MenuItem>
              ))}
            </Select>
          </FormControl>

          {!data && <LinearProgress />}
          {data && (
            <>
              <Grid container spacing={2}>
                <Grid size={{ xs: 6, md: 3 }}>
                  <Stat label="Enrolled" value={data.totals.enrolled} hint={`${data.totals.applications} applications`} />
                </Grid>
                <Grid size={{ xs: 6, md: 3 }}>
                  <Stat label="Placed" value={`${data.totals.placed_percent}%`} hint={`${data.totals.placed} placed · ${data.totals.unplaced} unplaced`} color="primary.main" />
                </Grid>
                <Grid size={{ xs: 6, md: 3 }}>
                  <Stat label="Offers" value={data.totals.offers} hint={`${data.totals.drives_completed} drive(s) completed · ${data.totals.drives_ongoing} ongoing`} />
                </Grid>
                <Grid size={{ xs: 6, md: 3 }}>
                  <Stat
                    label={data.cycle.type === "internship" ? "Median stipend" : "Median CTC"}
                    value={data.cycle.type === "internship" ? formatMoney(data.stipend.median) : lpa(data.ctc.median)}
                    hint={data.cycle.type === "internship" ? `highest ${formatMoney(data.stipend.highest)}` : `highest ${lpa(data.ctc.highest)}`}
                    color="secondary.main"
                  />
                </Grid>
              </Grid>

              <Grid container spacing={2}>
                <Grid size={{ xs: 12, md: 8 }}>
                  <Panel title="Applications per day">
                    {data.applications_over_time.length === 0 ? (
                      <Typography color="text.secondary">No applications yet.</Typography>
                    ) : (
                      <LineChart
                        height={280}
                        xAxis={[{ scaleType: "point", data: data.applications_over_time.map((d) => d.date.slice(5)) }]}
                        series={[{ data: data.applications_over_time.map((d) => d.count), label: "Applications", color: palette[0], area: true }]}
                        margin={{ left: 40, right: 20, top: 30, bottom: 30 }}
                      />
                    )}
                  </Panel>
                </Grid>
                <Grid size={{ xs: 12, md: 4 }}>
                  <Panel title="Offers by type">
                    {data.offers_by_type.length === 0 ? (
                      <Typography color="text.secondary">No offers yet.</Typography>
                    ) : (
                      <PieChart
                        height={280}
                        colors={palette}
                        series={[{ data: data.offers_by_type.map((o, i) => ({ id: i, value: o.count, label: o.label })), innerRadius: 50, paddingAngle: 2, cornerRadius: 4 }]}
                        slotProps={{ legend: { direction: "row", position: { vertical: "bottom", horizontal: "middle" }, padding: 0, itemMarkWidth: 10, itemMarkHeight: 10, labelStyle: { fontSize: 12 } } }}
                        margin={{ top: 10, bottom: 30 + 22 * Math.ceil(data.offers_by_type.length / 2), left: 10, right: 10 }}
                      />
                    )}
                  </Panel>
                </Grid>
              </Grid>

              <Grid container spacing={2}>
                <Grid size={{ xs: 12, md: 6 }}>
                  <Panel title="Placed by programme">
                    <BarChart
                      height={260}
                      layout="horizontal"
                      yAxis={[{ scaleType: "band", data: data.by_programme.map((p) => shortProgramme(p.key)) }]}
                      series={[
                        { data: data.by_programme.map((p) => p.placed), label: "Placed", color: palette[0], stack: "a" },
                        { data: data.by_programme.map((p) => p.enrolled - p.placed), label: "Unplaced", color: "#cbd5e1", stack: "a" },
                      ]}
                      margin={{ left: 90, right: 20, top: 40, bottom: 30 }}
                    />
                  </Panel>
                </Grid>
                <Grid size={{ xs: 12, md: 6 }}>
                  <Panel title="Compensation">
                    <Table size="small">
                      <TableHead>
                        <TableRow>
                          <TableCell />
                          <TableCell>CTC (annual)</TableCell>
                          <TableCell>Stipend (monthly)</TableCell>
                        </TableRow>
                      </TableHead>
                      <TableBody>
                        {["highest", "average", "median", "lowest"].map((k) => (
                          <TableRow key={k}>
                            <TableCell sx={{ textTransform: "capitalize" }}>{k}</TableCell>
                            <TableCell>{data.ctc[k] ? `${formatMoney(data.ctc[k])} (${lpa(data.ctc[k])})` : "—"}</TableCell>
                            <TableCell>{data.stipend[k] ? formatMoney(data.stipend[k]) : "—"}</TableCell>
                          </TableRow>
                        ))}
                        <TableRow>
                          <TableCell>Offers counted</TableCell>
                          <TableCell>{data.ctc.count}</TableCell>
                          <TableCell>{data.stipend.count}</TableCell>
                        </TableRow>
                      </TableBody>
                    </Table>
                    {Object.keys(data.non_inr_offers ?? {}).length > 0 && (
                      <Typography variant="caption" color="text.secondary" display="block" sx={{ mt: 1 }}>
                        Figures are INR offers only. Also: {Object.entries(data.non_inr_offers).map(([c, n]) => `${n} ${c}`).join(", ")} offer(s).
                      </Typography>
                    )}
                    <Typography variant="subtitle2" fontWeight={700} sx={{ mt: 2 }}>
                      Gender split
                    </Typography>
                    {data.by_gender.map((g) => (
                      <Stack key={g.key} direction="row" spacing={1} alignItems="center" sx={{ mt: 0.5 }}>
                        <Typography variant="body2" sx={{ width: 70 }}>
                          {g.key}
                        </Typography>
                        <Typography variant="caption" sx={{ width: 90 }}>
                          {g.placed}/{g.enrolled} placed
                        </Typography>
                        <Box sx={{ flex: 1 }}>
                          <PercentBar value={g.placed_percent} />
                        </Box>
                      </Stack>
                    ))}
                  </Panel>
                </Grid>
              </Grid>

              <Panel title="Branch-wise placement">
                <TableContainer sx={{ maxHeight: 520 }}>
                  <Table size="small" stickyHeader>
                    <TableHead>
                      <TableRow>
                        <TableCell>Programme</TableCell>
                        <TableCell>Branch</TableCell>
                        <TableCell>Enrolled</TableCell>
                        <TableCell>Placed</TableCell>
                        <TableCell sx={{ minWidth: 180 }}>Placed %</TableCell>
                        <TableCell>Offers</TableCell>
                        <TableCell>Avg CTC</TableCell>
                        <TableCell>Highest CTC</TableCell>
                      </TableRow>
                    </TableHead>
                    <TableBody>
                      {data.branch_table.map((row) => (
                        <TableRow key={`${row.programme}-${row.branch}`} hover>
                          <TableCell>{shortProgramme(row.programme)}</TableCell>
                          <TableCell>{row.branch}</TableCell>
                          <TableCell>{row.enrolled}</TableCell>
                          <TableCell>{row.placed}</TableCell>
                          <TableCell>
                            <PercentBar value={row.placed_percent} />
                          </TableCell>
                          <TableCell>{row.offers}</TableCell>
                          <TableCell>{lpa(row.average_ctc)}</TableCell>
                          <TableCell>{lpa(row.highest_ctc)}</TableCell>
                        </TableRow>
                      ))}
                    </TableBody>
                  </Table>
                </TableContainer>
              </Panel>

              <Grid container spacing={2}>
                <Grid size={{ xs: 12, md: 6 }}>
                  <Panel title="Top recruiters">
                    {data.top_recruiters.length === 0 ? (
                      <Typography color="text.secondary">No offers yet.</Typography>
                    ) : (
                      <Table size="small">
                        <TableBody>
                          {data.top_recruiters.map((r) => (
                            <TableRow key={r.company}>
                              <TableCell>{r.company}</TableCell>
                              <TableCell>{r.offers} offer(s)</TableCell>
                              <TableCell>{lpa(r.best_ctc)}</TableCell>
                            </TableRow>
                          ))}
                        </TableBody>
                      </Table>
                    )}
                  </Panel>
                </Grid>
                <Grid size={{ xs: 12, md: 6 }}>
                  <Panel title="Placed by graduating batch">
                    {data.by_batch.map((b) => (
                      <Stack key={b.key} direction="row" spacing={1} alignItems="center" sx={{ mt: 0.75 }}>
                        <Typography variant="body2" sx={{ width: 60 }}>
                          {b.key}
                        </Typography>
                        <Typography variant="caption" sx={{ width: 90 }}>
                          {b.placed}/{b.enrolled}
                        </Typography>
                        <Box sx={{ flex: 1 }}>
                          <PercentBar value={b.placed_percent} />
                        </Box>
                      </Stack>
                    ))}
                  </Panel>
                </Grid>
              </Grid>
            </>
          )}
        </Stack>
      )}
    </>
  );
}
