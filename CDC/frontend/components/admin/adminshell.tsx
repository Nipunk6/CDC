"use client";

import { useEffect, useState, useMemo } from "react";
import Link from "next/link";
import Image from "next/image";
import { usePathname } from "next/navigation";
import { signOut, useSession } from "next-auth/react";
import {
  AppBar,
  Badge,
  Box,
  Button,
  Container,
  Divider,
  Drawer,
  IconButton,
  List,
  ListItem,
  ListItemButton,
  ListItemText,
  Stack,
  Toolbar,
  Typography,
  alpha,
  useTheme,
} from "@mui/material";
import MenuIcon from "@mui/icons-material/Menu";
import { adminApi } from "@/lib/adminapi";

const baseNavItems = [
  { label: "Dashboard", href: "/admin" },
  { label: "Alumni Outreach", href: "/admin/alumni-outreach" },
  { label: "JNF Reviews", href: "/admin/jnfs" },
  { label: "INF Reviews", href: "/admin/infs" },
  { label: "Companies", href: "/admin/companies" },
  { label: "Branch Manager", href: "/admin/programme-branches" },
  { label: "Policy Documents", href: "/admin/policy-documents" },
  { label: "Notifications", href: "/admin/notifications" },
];

export default function AdminShell({
  children,
}: {
  children: React.ReactNode;
}) {
  const theme = useTheme();
  const pathname = usePathname();
  const { data: session } = useSession();
  const [unreadNotifications, setUnreadNotifications] = useState(0);
  const [mobileMenuOpen, setMobileMenuOpen] = useState(false);

  const navItems = useMemo(() => {
    const items = [...baseNavItems];
    if (session?.user?.isSuperAdmin) {
      items.push({ label: "Manage Admins", href: "/admin/manage-admins" });
    }
    return items;
  }, [session?.user?.isSuperAdmin]);

  useEffect(() => {
    const run = async () => {
      try {
        const response = await adminApi<{ unread_count: number }>(`/auth/notifications?ts=${Date.now()}`);
        setUnreadNotifications(response.unread_count ?? 0);
      } catch {
        // Keep navigation resilient even if notification fetch fails.
        setUnreadNotifications(0);
      }
    };

    const handleNotificationsUpdated = (event: Event) => {
      const customEvent = event as CustomEvent<number>;
      if (typeof customEvent.detail === "number") {
        setUnreadNotifications(customEvent.detail);
        return;
      }

      void run();
    };

    window.addEventListener("admin-notifications-updated", handleNotificationsUpdated);

    void run();

    return () => {
      window.removeEventListener("admin-notifications-updated", handleNotificationsUpdated);
    };
  }, [pathname]);

  return (
    <Box
      sx={{
        minHeight: "100vh",
        display: "flex",
        flexDirection: "column",
        background: "linear-gradient(145deg, #f4f7fb 0%, #fef6ed 100%)",
      }}
    >
      <Drawer
        anchor="left"
        open={mobileMenuOpen}
        onClose={() => setMobileMenuOpen(false)}
        PaperProps={{
          sx: { width: 280, bgcolor: "white" }
        }}
      >
        <Box sx={{ p: 2 }}>
          <Typography variant="subtitle1" fontWeight={700} color="primary" sx={{ mb: 2, px: 1 }}>
            Admin Navigation
          </Typography>
          <List>
            {navItems.map((item) => {
              const active = pathname === item.href;
              const isNotificationItem = item.href === "/admin/notifications";
              return (
                <ListItem key={item.href} disablePadding sx={{ mb: 0.5 }}>
                  <ListItemButton
                    component={Link}
                    href={item.href}
                    onClick={() => setMobileMenuOpen(false)}
                    selected={active}
                    sx={{
                      borderRadius: 1,
                      "&.Mui-selected": {
                        bgcolor: alpha(theme.palette.primary.main, 0.1),
                        color: "primary.main",
                        "&:hover": { bgcolor: alpha(theme.palette.primary.main, 0.15) }
                      }
                    }}
                  >
                    <ListItemText 
                      primary={item.label}
                      secondary={isNotificationItem && unreadNotifications > 0 ? `${unreadNotifications} unread` : null}
                      primaryTypographyProps={{ fontWeight: active ? 700 : 500 }}
                    />
                  </ListItemButton>
                </ListItem>
              );
            })}
            <Divider sx={{ my: 1 }} />
            <ListItem disablePadding>
              <ListItemButton
                onClick={() => signOut({ callbackUrl: "/auth/login/admin" })}
                sx={{ borderRadius: 1, color: "secondary.main" }}
              >
                <ListItemText primary="Sign Out" primaryTypographyProps={{ fontWeight: 600 }} />
              </ListItemButton>
            </ListItem>
          </List>
        </Box>
      </Drawer>

      <AppBar
        position="sticky"
        color="inherit"
        elevation={0}
        sx={{ borderBottom: "1px solid #dce4ee" }}
      >
        <Toolbar>
          <Container
            maxWidth="xl"
            sx={{
              display: "flex",
              justifyContent: "space-between",
              alignItems: "center",
              px: "0 !important",
            }}
          >
            <Stack direction="row" alignItems="center" spacing={1.5}>
              <IconButton
                color="inherit"
                onClick={() => setMobileMenuOpen(true)}
                sx={{ display: { xs: "flex", lg: "none" }, ml: -1 }}
              >
                <MenuIcon />
              </IconButton>
              <Box
                sx={{
                  width: 32,
                  height: 32,
                  display: "flex",
                  alignItems: "center",
                  justifyContent: "center",
                }}
              >
                <Image
                  src="/images/centenary-badge.png"
                  alt="Centenary Badge"
                  width={32}
                  height={32}
                  style={{ objectFit: "contain" }}
                />
              </Box>
              <Typography
                component={Link}
                href="/admin"
                variant="h6"
                color="primary.main"
                fontWeight={700}
                sx={{ 
                  textDecoration: "none",
                  fontSize: { xs: "1rem", sm: "1.25rem" }
                }}
              >
                IIT ISM CDC - Admin Portal
              </Typography>
            </Stack>

            <Stack
              direction="row"
              spacing={1}
              alignItems="center"
              sx={{ display: { xs: "none", lg: "flex" } }}
            >
              {navItems.map((item) => {
                const active = pathname === item.href;
                const isNotificationItem = item.href === "/admin/notifications";
                return (
                  <Button
                    key={item.href}
                    component={Link}
                    href={item.href}
                    variant={active ? "contained" : "text"}
                    color={active ? "primary" : "inherit"}
                    size="small"
                  >
                    {isNotificationItem ? (
                      <Badge
                        color="error"
                        badgeContent={unreadNotifications}
                        max={99}
                        overlap="circular"
                        invisible={unreadNotifications === 0}
                      >
                        <span>{item.label}</span>
                      </Badge>
                    ) : (
                      item.label
                    )}
                  </Button>
                );
              })}

              <Button
                color="secondary"
                variant="contained"
                size="small"
                onClick={() => signOut({ callbackUrl: "/auth/login/admin" })}
              >
                Sign Out
              </Button>
            </Stack>
          </Container>
        </Toolbar>
      </AppBar>

      <Container maxWidth="xl" sx={{ py: 4, flex: 1 }}>
        {children}
      </Container>

      <Box
        component="footer"
        sx={{
          py: 2,
          bgcolor: "#1a1a2e",
          color: "grey.500",
          borderTop: "1px solid rgba(255,255,255,0.1)",
        }}
      >
        <Container maxWidth="xl">
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
