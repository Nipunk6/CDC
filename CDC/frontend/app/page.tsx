"use client";

import { useState } from "react";

import Link from "next/link";
import Image from "next/image";
import {
  AppBar,
  Drawer,
  IconButton,
  List,
  ListItem,
  ListItemButton,
  ListItemText,
  Box,
  Button,
  Card,
  CardContent,
  Container,
  Divider,
  Grid2,
  Paper,
  Stack,
  Toolbar,
  Typography,
  alpha,
  useTheme,
} from "@mui/material";
import MenuIcon from "@mui/icons-material/Menu";
import VerifiedIcon from "@mui/icons-material/Verified";
import RocketLaunchIcon from "@mui/icons-material/RocketLaunch";
import EngineeringIcon from "@mui/icons-material/Engineering";
import WorkIcon from "@mui/icons-material/Work";
import GroupsIcon from "@mui/icons-material/Groups";
import SchoolIcon from "@mui/icons-material/School";
import EmailIcon from "@mui/icons-material/Email";
import PhoneIcon from "@mui/icons-material/Phone";
import LocationOnIcon from "@mui/icons-material/LocationOn";

import LanguageIcon from "@mui/icons-material/Language";
import FormatQuoteIcon from "@mui/icons-material/FormatQuote";

const programmes = [
  "B.Tech (4 Year)",
  "Integrated M.Tech (5 Year)",
  "B.Tech Double Major (5 Year)",
  "B.Tech-M.Tech Dual Degree (5 Year)",
  "M.Tech (2 Year)",
  "MBA (2 Year)",
  "M.Sc (2 Year)",
  "M.A. (2 Year)",
  "M.Sc.Tech (3 Year)",
  "Ph.D",
];

export default function Home() {
  const [mobileMenuOpen, setMobileMenuOpen] = useState(false);
  const theme = useTheme();

  const navLinks = [
    { label: "Recruiter Login", href: "/auth/login/recruiter", variant: "outlined" as const },
    { label: "Alumni", href: "/alumni", variant: "outlined" as const },
    { label: "Admin Login", href: "/auth/login/admin", variant: "outlined" as const },
    { label: "Register Now", href: "/company/register", variant: "contained" as const },
  ];

  return (
    <Box sx={{ minHeight: "100vh", bgcolor: "background.default" }}>
      {/* ─── Mobile Navigation Drawer ─── */}
      <Drawer
        anchor="right"
        open={mobileMenuOpen}
        onClose={() => setMobileMenuOpen(false)}
        PaperProps={{
          sx: { width: 280, bgcolor: "#7B1113", color: "white" }
        }}
      >
        <Box sx={{ p: 3 }}>
          <Typography variant="h6" fontWeight={700} sx={{ mb: 3, color: "#D4A843" }}>
            Navigation
          </Typography>
          <List>
            {navLinks.map((link) => (
              <ListItem key={link.label} disablePadding sx={{ mb: 1 }}>
                <ListItemButton
                  component={Link}
                  href={link.href}
                  onClick={() => setMobileMenuOpen(false)}
                  sx={{
                    borderRadius: 1,
                    border: link.variant === "outlined" ? "1px solid rgba(255,255,255,0.2)" : "none",
                    bgcolor: link.variant === "contained" ? "#D4A843" : "transparent",
                    color: link.variant === "contained" ? "#3e1a00" : "white",
                    "&:hover": {
                      bgcolor: link.variant === "contained" ? "#c9982e" : "rgba(255,255,255,0.08)",
                    }
                  }}
                >
                  <ListItemText
                    primary={link.label}
                    primaryTypographyProps={{ fontWeight: 600 }}
                  />
                </ListItemButton>
              </ListItem>
            ))}
          </List>
        </Box>
      </Drawer>

      {/* ─── Navigation ─── */}
      <AppBar
        position="fixed"
        sx={{
          bgcolor: "#7B1113",
          color: "white",
          borderBottom: "3px solid #D4A843",
        }}
        elevation={0}
      >
        <Container maxWidth={false} sx={{ px: { xs: 2, md: 4 } }}>
          <Toolbar
            sx={{
              px: { xs: 0 },
              py: { xs: 0.5, md: 1 },
              minHeight: { xs: "56px", md: "80px" },
              gap: 2,
            }}
          >
            {/* Logo cluster */}
            <Stack
              direction="row"
              alignItems="center"
              spacing={1.5}
              sx={{ flexGrow: 1, ml: { xs: "7px", sm: "-1px", md: "-9px" } }}
            >
              <Box
                component={Link}
                href="/"
                sx={{
                  display: "flex",
                  alignItems: "center",
                  textDecoration: "none",
                  height: { xs: 40, sm: 50, md: 65 },
                  width: "auto",
                }}
              >
                <Image
                  src="/images/iitism-logo-banner.png"
                  alt="IIT ISM Dhanbad Logo"
                  width={368}
                  height={65}
                  style={{ objectFit: "contain", height: "100%", width: "auto" }}
                  priority
                />
              </Box>
              <Box
                sx={{
                  width: { xs: 40, sm: 50, md: 65 },
                  height: { xs: 40, sm: 50, md: 65 },
                  display: { xs: "none", sm: "flex" },
                  alignItems: "center",
                  justifyContent: "center",
                  flexShrink: 0,
                  ml: 1,
                }}
              >
                <Image
                  src="/images/centenary-badge.png"
                  alt="Centenary Badge"
                  width={65}
                  height={65}
                  style={{ objectFit: "contain", height: "100%", width: "auto" }}
                />
              </Box>
            </Stack>

            {/* Nav buttons */}
            <Stack
              direction="row"
              spacing={1}
              sx={{ display: { xs: "none", md: "flex" } }}
            >
              <Button
                component={Link}
                href="/auth/login/recruiter"
                variant="outlined"
                sx={{
                  color: "white",
                  borderColor: "rgba(255,255,255,0.4)",
                  "&:hover": {
                    borderColor: "#D4A843",
                    bgcolor: "rgba(255,255,255,0.08)",
                  },
                }}
              >
                Recruiter Login
              </Button>
              <Button
                component={Link}
                href="/alumni"
                variant="outlined"
                sx={{
                  color: "white",
                  borderColor: "rgba(255,255,255,0.4)",
                  "&:hover": {
                    borderColor: "#D4A843",
                    bgcolor: "rgba(255,255,255,0.08)",
                  },
                }}
              >
                Alumni
              </Button>
              <Button
                component={Link}
                href="/auth/login/admin"
                variant="outlined"
                sx={{
                  color: "white",
                  borderColor: "rgba(255,255,255,0.4)",
                  "&:hover": {
                    borderColor: "#D4A843",
                    bgcolor: "rgba(255,255,255,0.08)",
                  },
                }}
              >
                Admin Login
              </Button>
              <Button
                component={Link}
                href="/company/register"
                variant="contained"
                sx={{
                  bgcolor: "#D4A843",
                  color: "#3e1a00",
                  fontWeight: 700,
                  "&:hover": { bgcolor: "#c9982e" },
                }}
              >
                Register Now
              </Button>
            </Stack>

            {/* Mobile Menu Toggle */}
            <IconButton
              color="inherit"
              onClick={() => setMobileMenuOpen(true)}
              sx={{ display: { xs: "flex", md: "none" } }}
            >
              <MenuIcon />
            </IconButton>
          </Toolbar>
        </Container>
      </AppBar>

      {/* ─── Hero Section with Campus Image ─── */}
      <Box
        sx={{
          position: "relative",
          pt: { xs: 14, md: 12 },
          pb: { xs: 8, md: 0 },
          minHeight: { md: "85vh" },
          display: "flex",
          alignItems: "center",
          overflow: "hidden",
        }}
      >
        {/* Campus background */}
        <Box
          sx={{
            position: "absolute",
            inset: 0,
            zIndex: 0,
            "&::after": {
              content: '""',
              position: "absolute",
              inset: 0,
              background:
                "linear-gradient(135deg, rgba(123,17,19,0.92) 0%, rgba(90,12,14,0.85) 40%, rgba(26,35,126,0.80) 100%)",
            },
          }}
        >
          <Image
            src="/images/campus.png"
            alt="IIT ISM Campus"
            fill
            style={{ objectFit: "cover", objectPosition: "center 40%" }}
            priority
          />
        </Box>

        <Container maxWidth="lg" sx={{ position: "relative", zIndex: 1 }}>
          <Grid2 container spacing={4} alignItems="center">
            <Grid2
              size={{ xs: 12, md: 8 }}
              sx={{
                position: "relative",
                left: { md: "-32px" },
                top: { md: "32px" },
              }}
            >
              <Typography
                variant="overline"
                sx={{
                  color: "#D4A843",
                  letterSpacing: 3,
                  fontWeight: 600,
                  fontSize: "0.85rem",
                }}
              >
                EST. 1926 &nbsp;·&nbsp; CENTENARY CELEBRATIONS
              </Typography>

              <Typography
                variant="h2"
                fontWeight={800}
                sx={{
                  mt: 1,
                  lineHeight: 1.15,
                  color: "white",
                  fontSize: { xs: "2.4rem", sm: "2.8rem", md: "3.2rem" },
                }}
              >
                Career Development
                {" "}
                {/* <br /> */}
                Centre
              </Typography>

              <Typography
                sx={{
                  color: "#D4A843",
                  fontSize: { xs: "1.1rem", sm: "1.3rem", md: "1.5rem" },
                  fontWeight: 500,
                  mt: 1.5,
                  mb: 2.5,
                  fontStyle: "italic",
                  letterSpacing: "0.02em",
                }}
              >
                Legacy that Inspires the Future
              </Typography>

              <Typography
                variant="h6"
                sx={{
                  opacity: 0.92,
                  mb: 4,
                  maxWidth: 520,
                  color: "white",
                  fontWeight: 400,
                  lineHeight: 1.6,
                }}
              >
                Connecting India&apos;s finest engineering talent from IIT
                (ISM) Dhanbad with leading organizations
                worldwide.
              </Typography>

              <Stack direction={{ xs: "column", sm: "row" }} spacing={2}>
                <Button
                  component={Link}
                  href="/company/register"
                  variant="contained"
                  size="large"

                  sx={{
                    px: 4,
                    py: 1.5,
                    bgcolor: "#D4A843",
                    color: "#3e1a00",
                    fontWeight: 700,
                    fontSize: "1rem",
                    "&:hover": { bgcolor: "#c9982e" },
                  }}
                >
                  Start Recruiting
                </Button>
                <Button
                  component="a"
                  href="http://www.iitism.ac.in/storage/files/iit-ism-placement-brochure-2025-26.pdf"
                  target="_blank"
                  rel="noopener"
                  variant="outlined"
                  size="large"
                  sx={{
                    color: "white",
                    borderColor: "rgba(255,255,255,0.5)",
                    fontWeight: 600,
                    "&:hover": {
                      borderColor: "#D4A843",
                      bgcolor: "rgba(255,255,255,0.08)",
                    },
                  }}
                >
                  Download Brochure
                </Button>
              </Stack>
            </Grid2>
          </Grid2>
        </Container>
      </Box>

      {/* ─── About CDC Section ─── */}
      <Box sx={{ py: 8, bgcolor: "white" }}>
        <Container maxWidth="lg">
          <Grid2 container spacing={5} alignItems="center">
            <Grid2 size={{ xs: 12, md: 5 }}>
              <Box
                sx={{
                  display: "flex",
                  flexDirection: "column",
                  alignItems: "center",
                  justifyContent: "center",
                  textAlign: "center",
                }}
              >
                <Image
                  src="/images/iitism-logo.png"
                  alt="IIT ISM Dhanbad Logo"
                  width={180}
                  height={180}
                  style={{ objectFit: "contain" }}
                />
                <Typography
                  variant="body2"
                  align="center"
                  sx={{
                    mt: 2,
                    color: "#7B1113",
                    fontWeight: 600,
                    fontStyle: "italic",
                  }}
                >
                  भारतीय प्रौद्योगिकी संस्थान (भारतीय खनि विद्यापीठ) धनबाद
                </Typography>
                <Typography
                  variant="caption"
                  color="text.secondary"
                  display="block"
                  align="center"
                >
                  Indian Institute of Technology (Indian School of Mines)
                  Dhanbad
                </Typography>
              </Box>
            </Grid2>
            <Grid2 size={{ xs: 12, md: 7 }}>
              <Typography
                variant="overline"
                sx={{
                  color: "#D4A843",
                  fontWeight: 600,
                  letterSpacing: 2,
                }}
              >
                About the Centre
              </Typography>
              <Typography
                variant="h4"
                fontWeight={700}
                color="primary"
                gutterBottom
              >
                Career Development Centre
              </Typography>
              <Typography
                variant="body1"
                color="text.secondary"
                sx={{ lineHeight: 1.8, mb: 2 }}
              >
                The Career Development Centre (CDC) at IIT (ISM) Dhanbad serves
                as the primary interface between the institute&apos;s talented
                student community and the corporate world. With a legacy
                spanning nearly a century, CDC facilitates the recruitment
                process for B.Tech, Dual Degree, M.Tech, MBA, M.Sc, and Ph.D
                students across 18 departments and multiple centres of
                excellence.
              </Typography>
              <Typography
                variant="body1"
                color="text.secondary"
                sx={{ lineHeight: 1.8 }}
              >
                Our mission is to bridge the gap between academia and industry,
                ensuring that our graduates are well-prepared for the
                professional world while providing organizations access to
                India&apos;s finest engineering and management talent.
              </Typography>
            </Grid2>
          </Grid2>
        </Container>
      </Box>

      {/* ─── Director's & Chairperson's Messages ─── */}
      <Box sx={{ py: 8, bgcolor: "#faf7f2" }}>
        <Container maxWidth="lg">
          <Typography
            variant="overline"
            display="block"
            textAlign="center"
            sx={{ color: "#D4A843", fontWeight: 600, letterSpacing: 2 }}
          >
            Messages
          </Typography>
          <Typography
            variant="h4"
            fontWeight={700}
            textAlign="center"
            color="primary"
            gutterBottom
            mb={5}
          >
            From Our Leadership
          </Typography>

          <Grid2 container spacing={4}>
            {/* Director's Message */}
            <Grid2 size={{ xs: 12, md: 6 }}>
              <Card
                sx={{
                  height: "100%",
                  borderTop: "4px solid #7B1113",
                  borderRadius: 3,
                }}
              >
                <CardContent sx={{ p: 4 }}>
                  <Stack direction="row" spacing={1} alignItems="center" mb={2}>
                    <FormatQuoteIcon
                      sx={{ color: "#D4A843", fontSize: 32, transform: "scaleX(-1)" }}
                    />
                    <Typography variant="h5" fontWeight={700} color="primary">
                      Director&apos;s Message
                    </Typography>
                  </Stack>
                  <Stack direction={{ xs: "column", sm: "row" }} spacing={3} alignItems="flex-start">
                    <Box
                      sx={{
                        flexShrink: 0,
                        width: 120,
                        height: 120,
                        borderRadius: "50%",
                        overflow: "hidden",
                        border: "3px solid",
                        borderColor: "primary.main",
                        mx: { xs: "auto", sm: 0 },
                      }}
                    >
                      <Image
                        src="/images/director.png"
                        alt="Prof. Sukumar Mishra"
                        width={120}
                        height={120}
                        style={{ objectFit: "cover" }}
                      />
                    </Box>
                    <Box sx={{ flex: 1 }}>
                      <Typography
                        variant="body2"
                        color="text.secondary"
                        sx={{ lineHeight: 1.8, mb: 2, fontStyle: "italic" }}
                      >
                        &ldquo;The Institute has a legacy of producing best
                        workforce for the nation &amp; the world. Having stamped its
                        class in academic and corporate circles, the alumni of this
                        institute today don the most challenging and demanding roles
                        in the industry. This is testimony to the trust and belief
                        that the industry has bestowed in us for years. We look
                        forward to traverse higher trajectories of excellence.&rdquo;
                      </Typography>
                    </Box>
                  </Stack>
                  <Divider sx={{ my: 2 }} />
                  <Typography
                    variant="subtitle1"
                    fontWeight={700}
                    color="primary"
                  >
                    Prof. Sukumar Mishra
                  </Typography>
                  <Typography variant="body2" color="text.secondary">
                    Director, IIT (ISM) Dhanbad
                  </Typography>
                </CardContent>
              </Card>
            </Grid2>

            {/* Chairperson's Message */}
            <Grid2 size={{ xs: 12, md: 6 }}>
              <Card
                sx={{
                  height: "100%",
                  borderTop: "4px solid #1a237e",
                  borderRadius: 3,
                }}
              >
                <CardContent sx={{ p: 4 }}>
                  <Stack direction="row" spacing={1} alignItems="center" mb={2}>
                    <FormatQuoteIcon
                      sx={{ color: "#D4A843", fontSize: 32, transform: "scaleX(-1)" }}
                    />
                    <Typography variant="h5" fontWeight={700} color="secondary">
                      Chairperson (CDC)&apos;s Message
                    </Typography>
                  </Stack>
                  <Stack direction={{ xs: "column", sm: "row" }} spacing={3} alignItems="flex-start">
                    <Box
                      sx={{
                        flexShrink: 0,
                        width: 120,
                        height: 120,
                        borderRadius: "50%",
                        overflow: "hidden",
                        border: "3px solid",
                        borderColor: "secondary.main",
                        mx: { xs: "auto", sm: 0 },
                      }}
                    >
                      <Image
                        src="/images/chairperson.png"
                        alt="Prof. Saumya Singh"
                        width={120}
                        height={120}
                        style={{ objectFit: "cover" }}
                      />
                    </Box>
                    <Box sx={{ flex: 1 }}>
                      <Typography
                        variant="body2"
                        color="text.secondary"
                        sx={{ lineHeight: 1.8, mb: 2, fontStyle: "italic" }}
                      >
                        &ldquo;Strong networking with industries and academia across
                        the country and abroad has geared a recent overhaul in our
                        academic curriculum, research facilities and laboratories.
                        We invite you to collaborate with us to meet your HR needs
                        and promote your brands in our institute. Our students have
                        potential to contribute in a big way to the growth and
                        development of organizations they would work for.&rdquo;
                      </Typography>
                    </Box>
                  </Stack>
                  <Divider sx={{ my: 2 }} />
                  <Typography
                    variant="subtitle1"
                    fontWeight={700}
                    color="secondary"
                  >
                    Prof. Saumya Singh
                  </Typography>
                  <Typography variant="body2" color="text.secondary">
                    Chairperson (CDC), IIT (ISM) Dhanbad
                  </Typography>
                </CardContent>
              </Card>
            </Grid2>
          </Grid2>
        </Container>
      </Box>

      {/* ─── Why Recruit Section ─── */}
      <Box sx={{ py: 8, bgcolor: "white" }}>
        <Container maxWidth="lg">
          <Typography
            variant="overline"
            display="block"
            textAlign="center"
            sx={{ color: "#D4A843", fontWeight: 600, letterSpacing: 2 }}
          >
            Our Strengths
          </Typography>
          <Typography
            variant="h4"
            fontWeight={700}
            textAlign="center"
            color="primary"
            gutterBottom
          >
            Why Recruit at IIT (ISM) Dhanbad?
          </Typography>
          <Typography
            variant="body1"
            color="text.secondary"
            textAlign="center"
            mb={5}
            maxWidth={650}
            mx="auto"
          >
            India&apos;s oldest and most prestigious technical institute with a
            unique focus on Mining, Energy, Earth Sciences, and core
            engineering.
          </Typography>
          <Grid2 container spacing={3}>
            {[
              {
                icon: <VerifiedIcon sx={{ fontSize: 44 }} />,
                title: "Century of Excellence",
                desc: "Established in 1926, we are among India's oldest and most respected engineering institutions — celebrating 100 years of academic glory.",
                color: "#7B1113",
              },
              {
                icon: <EngineeringIcon sx={{ fontSize: 44 }} />,
                title: "Unique Talent Pool",
                desc: "Specialized programmes in Mining, Petroleum, Geology, and Geophysics — talent you won't find at any other institution.",
                color: "#1a237e",
              },
              {
                icon: <WorkIcon sx={{ fontSize: 44 }} />,
                title: "Industry-Ready Graduates",
                desc: "Rigorous curriculum combined with industry internships, live projects, and cutting-edge research exposure.",
                color: "#7B1113",
              },
              {
                icon: <GroupsIcon sx={{ fontSize: 44 }} />,
                title: "18+ Departments",
                desc: "From B.Tech to Ph.D across diverse departments — find the perfect fit for every role in your organization.",
                color: "#1a237e",
              },
            ].map((item) => (
              <Grid2 key={item.title} size={{ xs: 12, sm: 6, md: 3 }}>
                <Card
                  sx={{
                    height: "100%",
                    textAlign: "center",
                    p: 2,
                    borderRadius: 3,
                    borderBottom: `3px solid ${item.color}`,
                  }}
                >
                  <CardContent>
                    <Box sx={{ color: item.color, mb: 2 }}>{item.icon}</Box>
                    <Typography variant="h6" fontWeight={700} gutterBottom>
                      {item.title}
                    </Typography>
                    <Typography variant="body2" color="text.secondary">
                      {item.desc}
                    </Typography>
                  </CardContent>
                </Card>
              </Grid2>
            ))}
          </Grid2>
        </Container>
      </Box>

      {/* ─── Programmes Section ─── */}
      <Box
        sx={{
          py: 8,
          background:
            "linear-gradient(135deg, #faf7f2 0%, #f5f0e8 100%)",
        }}
      >
        <Container maxWidth="lg">
          <Typography
            variant="overline"
            display="block"
            textAlign="center"
            sx={{ color: "#D4A843", fontWeight: 600, letterSpacing: 2 }}
          >
            Academics
          </Typography>
          <Typography
            variant="h4"
            fontWeight={700}
            textAlign="center"
            color="primary"
            gutterBottom
          >
            Programmes Available
          </Typography>
          <Typography
            variant="body1"
            color="text.secondary"
            textAlign="center"
            mb={4}
          >
            Recruit from our diverse range of academic programmes
          </Typography>
          <Stack
            direction="row"
            flexWrap="wrap"
            justifyContent="center"
            gap={2}
          >
            {programmes.map((prog) => (
              <Paper
                key={prog}
                sx={{
                  px: 3,
                  py: 1.5,
                  borderRadius: 2,
                  border: "1px solid",
                  borderColor: alpha("#7B1113", 0.2),
                  bgcolor: "white",
                  transition: "all 0.2s ease",
                  "&:hover": {
                    borderColor: "#7B1113",
                    bgcolor: alpha("#7B1113", 0.04),
                    transform: "translateY(-2px)",
                    boxShadow: "0 4px 12px rgba(123,17,19,0.1)",
                  },
                }}
              >
                <Typography variant="body1" fontWeight={500}>
                  <SchoolIcon
                    sx={{
                      fontSize: 18,
                      mr: 1,
                      verticalAlign: "middle",
                      color: "#7B1113",
                    }}
                  />
                  {prog}
                </Typography>
              </Paper>
            ))}
          </Stack>
        </Container>
      </Box>

      {/* ─── CTA Section ─── */}
      <Box
        sx={{
          py: 8,
          background:
            "linear-gradient(135deg, #7B1113 0%, #5A0C0E 50%, #1a237e 100%)",
          color: "white",
          textAlign: "center",
          position: "relative",
          overflow: "hidden",
          "&::before": {
            content: '""',
            position: "absolute",
            top: 0,
            left: 0,
            right: 0,
            height: "3px",
            background:
              "linear-gradient(90deg, #D4A843 0%, #f0d78c 50%, #D4A843 100%)",
          },
        }}
      >
        <Container maxWidth="md">
          <Typography variant="h4" fontWeight={700} gutterBottom>
            Ready to Hire the Best?
          </Typography>
          <Typography variant="body1" sx={{ opacity: 0.9, mb: 4 }}>
            Register now and start filling your Job / Internship Notification
            Forms to recruit from IIT (ISM) Dhanbad.
          </Typography>
          <Stack direction="row" spacing={2} justifyContent="center">
            <Button
              component={Link}
              href="/company/register"
              variant="contained"
              size="large"
              sx={{
                bgcolor: "#D4A843",
                color: "#3e1a00",
                fontWeight: 700,
                px: 4,
                "&:hover": { bgcolor: "#c9982e" },
              }}
            >
              Register as Recruiter
            </Button>
            <Button
              component={Link}
              href="/auth/login/recruiter"
              variant="outlined"
              size="large"
              sx={{
                color: "white",
                borderColor: "rgba(255,255,255,0.5)",
                "&:hover": {
                  borderColor: "#D4A843",
                  bgcolor: "rgba(255,255,255,0.08)",
                },
              }}
            >
              Already Registered? Login
            </Button>
          </Stack>
        </Container>
      </Box>

      {/* ─── Footer ─── */}
      <Box sx={{ bgcolor: "#1a1a2e", color: "grey.400", py: 5 }}>
        <Container maxWidth="lg">
          <Grid2 container spacing={4}>
            <Grid2 size={{ xs: 12, md: 4 }}>
              <Stack direction="row" spacing={1.5} alignItems="center" mb={2}>
                <Box
                  sx={{
                    width: 40,
                    height: 40,
                    display: "flex",
                    alignItems: "center",
                    justifyContent: "center",
                  }}
                >
                  <Image
                    src="/images/iitism-logo.png"
                    alt="IIT ISM"
                    width={36}
                    height={36}
                    style={{ objectFit: "contain" }}
                  />
                </Box>
                <Box>
                  <Typography variant="subtitle1" color="white" fontWeight={700}>
                    Career Development Centre
                  </Typography>
                  <Typography variant="caption" sx={{ color: "#D4A843" }}>
                    IIT (ISM) Dhanbad
                  </Typography>
                </Box>
              </Stack>
              <Typography
                variant="body2"
                sx={{ color: "grey.500", fontStyle: "italic" }}
              >
                &ldquo;Legacy that Inspires the Future&rdquo;
              </Typography>
            </Grid2>

            <Grid2 size={{ xs: 12, md: 4 }}>
              <Typography
                variant="subtitle2"
                color="white"
                fontWeight={600}
                gutterBottom
              >
                Contact Us
              </Typography>
              <Stack spacing={1} mt={1}>
                <Stack direction="row" spacing={1} alignItems="center">
                  <EmailIcon sx={{ fontSize: 16, color: "#D4A843" }} />
                  <Typography variant="body2">cdc@iitism.ac.in</Typography>
                </Stack>
                <Stack direction="row" spacing={1} alignItems="center">
                  <PhoneIcon sx={{ fontSize: 16, color: "#D4A843" }} />
                  <Typography variant="body2">+91-326-223-5555</Typography>
                </Stack>
                <Stack direction="row" spacing={1} alignItems="center">
                  <LocationOnIcon sx={{ fontSize: 16, color: "#D4A843" }} />
                  <Typography variant="body2">
                    Dhanbad – 826004, Jharkhand, India
                  </Typography>
                </Stack>
                <Stack direction="row" spacing={1} alignItems="center">
                  <LanguageIcon sx={{ fontSize: 16, color: "#D4A843" }} />
                  <Typography
                    variant="body2"
                    component="a"
                    href="https://iitism.ac.in"
                    target="_blank"
                    rel="noopener"
                    sx={{
                      color: "grey.400",
                      textDecoration: "none",
                      "&:hover": { color: "#D4A843" },
                    }}
                  >
                    iitism.ac.in
                  </Typography>
                </Stack>
              </Stack>
            </Grid2>

            <Grid2 size={{ xs: 12, md: 4 }}>
              <Typography
                variant="subtitle2"
                color="white"
                fontWeight={600}
                gutterBottom
              >
                Quick Links
              </Typography>
              <Stack spacing={0.5} mt={1}>
                {[
                  { label: "Register as Recruiter", href: "/company/register" },
                  { label: "Recruiter Login", href: "/auth/login/recruiter" },
                  { label: "Admin Login", href: "/auth/login/admin" },
                  { label: "Alumni Connect", href: "/alumni" },
                  {
                    label: "IIT ISM Official Website",
                    href: "https://iitism.ac.in",
                    external: true,
                  },
                ].map((link) => (
                  <Link
                    key={link.label}
                    href={link.href}
                    target={link.external ? "_blank" : undefined}
                    rel={link.external ? "noopener" : undefined}
                    style={{ color: "inherit", textDecoration: "none" }}
                  >
                    <Typography
                      variant="body2"
                      sx={{ "&:hover": { color: "#D4A843" } }}
                    >
                      {link.label}
                    </Typography>
                  </Link>
                ))}
              </Stack>
            </Grid2>
          </Grid2>

          <Divider sx={{ my: 3, borderColor: "rgba(255,255,255,0.08)" }} />

          <Typography
            variant="body2"
            textAlign="center"
            sx={{
              fontSize: "0.7rem",
              letterSpacing: "0.05em",
              textTransform: "uppercase",
              fontWeight: 500,
            }}
          >
            Copyright © {new Date().getFullYear()} All Rights Reserved || Designed & Developed by - The Batch of Mathematics and Computing BTech-2028
          </Typography>
          <Typography
            variant="body2"
            textAlign="center"
            sx={{
              fontSize: "0.6rem",
              color: "#1a1a2e", // Match background color to hide
              userSelect: "text",
              mt: 0.5,
              cursor: "default"
            }}
          >
            Special contribution- Saurabh Pathak, Rohit Garg, Nipun Kansal, Nehmeet Patel
          </Typography>
        </Container>
      </Box>
    </Box>
  );
}
