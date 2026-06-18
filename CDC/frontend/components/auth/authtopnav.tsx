"use client";

import Link from "next/link";
import Image from "next/image";
import {
  AppBar,
  Box,
  Button,
  Container,
  Stack,
  Toolbar,
  Typography,
  alpha,
} from "@mui/material";

type AuthTopNavProps = {
  current: "login" | "register";
};

export default function AuthTopNav({ current }: AuthTopNavProps) {
  return (
    <AppBar
      position="static"
      color="inherit"
      elevation={0}
      sx={{
        borderBottom: "1px solid",
        borderColor: "divider",
        bgcolor: alpha("#ffffff", 0.94),
        backdropFilter: "blur(8px)",
      }}
    >
      <Toolbar sx={{ py: 0.5 }}>
        <Container
          maxWidth="xl"
          sx={{
            display: "flex",
            justifyContent: "space-between",
            alignItems: "center",
            px: "0 !important",
          }}
        >
          <Stack
            component={Link}
            href="/"
            direction="row"
            spacing={1.25}
            alignItems="center"
            sx={{ textDecoration: "none", color: "primary.main" }}
          >
            <Box
              sx={{
                width: 36,
                height: 36,
                display: "flex",
                alignItems: "center",
                justifyContent: "center",
              }}
            >
              <Image
                src="/images/iitism-logo.png"
                alt="IIT ISM Logo"
                width={36}
                height={36}
                style={{ objectFit: "contain" }}
              />
            </Box>
            <Box>
              <Typography variant="subtitle1" fontWeight={700} lineHeight={1.1}>
                IIT ISM CDC
              </Typography>
              <Typography variant="caption" color="text.secondary" lineHeight={1.1}>
                Recruiter Portal
              </Typography>
            </Box>
          </Stack>

          <Stack direction="row" spacing={1}>
            <Button
              component={Link}
              href="/auth/login"
              variant={current === "login" ? "contained" : "text"}
              color={current === "login" ? "primary" : "inherit"}
              size="small"
            >
              Recruiter Login
            </Button>
            <Button
              component={Link}
              href="/company/register"
              variant={current === "register" ? "contained" : "outlined"}
              color="secondary"
              size="small"
            >
              Company Registration
            </Button>
          </Stack>
        </Container>
      </Toolbar>
    </AppBar>
  );
}
