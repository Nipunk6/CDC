"use client";

import { useEffect, useState, useMemo } from "react";
import Link from "next/link";
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
  ListSubheader,
  Stack,
  Toolbar,
  Typography,
  alpha,
  useTheme,
} from "@mui/material";
import MenuIcon from "@mui/icons-material/Menu";
import MoreHorizIcon from "@mui/icons-material/MoreHoriz";
import NotificationsIcon from "@mui/icons-material/NotificationsNone";
import { adminApi } from "@/lib/adminapi";
import StudentSearch from "@/components/admin/studentsearch";
import AccountMenu from "@/components/admin/accountmenu";
import BrandLogo from "@/components/shared/brandlogo";
import { useBranding } from "@/lib/branding";

const handleSignOut = async () => {
  try {
    await adminApi("/auth/logout", { method: "POST" }); // revoke the Sanctum token server-side
  } catch {
    // ignore - the client session is cleared regardless
  }
  await signOut({ callbackUrl: "/auth/login/admin" });
};

type NavItem = { label: string; href: string; group: string; primary?: boolean };

// `primary` pages sit in the top row from 1024px; everything is in the grouped menu drawer (QA F-024, decision 7).
const NAV_GROUPS = ["Placement", "Engagement", "Company forms", "Reports", "Administration"];
const baseNavItems: NavItem[] = [
  { label: "Dashboard", href: "/admin", group: "Placement", primary: true },
  { label: "Placements", href: "/admin/placement-cycles", group: "Placement", primary: true },
  { label: "Job Profiles", href: "/admin/postings", group: "Placement", primary: true },
  { label: "Students", href: "/admin/students", group: "Placement", primary: true },
  { label: "Proposals", href: "/admin/proposals", group: "Placement" },
  { label: "Resumes", href: "/admin/resumes", group: "Placement" },
  { label: "Branch Changes", href: "/admin/branch-changes", group: "Placement" },
  { label: "Events", href: "/admin/events", group: "Placement" },
  { label: "Calendar", href: "/admin/calendar", group: "Placement" },
  { label: "Analytics", href: "/admin/analytics", group: "Placement" },
  { label: "Student Categories", href: "/admin/student-categories", group: "Placement" },
  { label: "Notices", href: "/admin/notices", group: "Engagement" },
  { label: "Surveys", href: "/admin/surveys", group: "Engagement" },
  { label: "JNF Reviews", href: "/admin/jnfs", group: "Company forms", primary: true },
  { label: "INF Reviews", href: "/admin/infs", group: "Company forms", primary: true },
  { label: "Companies", href: "/admin/companies", group: "Company forms" },
  { label: "Policy Documents", href: "/admin/policy-documents", group: "Company forms" },
  { label: "Alumni Outreach", href: "/admin/alumni-outreach", group: "Company forms" },
  { label: "Reports", href: "/admin/reports", group: "Reports" },
  { label: "Excel Templates", href: "/admin/excel-templates", group: "Reports" },
  { label: "Notifications", href: "/admin/notifications", group: "Administration" },
  { label: "Audit Log", href: "/admin/audit-logs", group: "Administration" },
  { label: "Settings", href: "/admin/settings", group: "Administration" },
];

const ROW = "@media (min-width:1024px)";
// Between 1024 and 1279px the row needs the room, so the title shortens to "CDC Admin".
const TIGHT = "@media (min-width:1024px) and (max-width:1279.98px)";

const isActive = (pathname: string, href: string) =>
  href === "/admin" ? pathname === href : pathname === href || pathname.startsWith(`${href}/`);

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
  const branding = useBranding();

  const navItems = useMemo(() => {
    const items = [...baseNavItems];
    if (session?.user?.isSuperAdmin) {
      items.push({ label: "Users", href: "/admin/users", group: "Administration" });
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
          <List dense>
            {NAV_GROUPS.flatMap((group) => [
              <ListSubheader key={group} disableSticky sx={{ lineHeight: "32px", fontWeight: 700, letterSpacing: 0.4, textTransform: "uppercase", fontSize: "0.7rem" }}>
                {group}
              </ListSubheader>,
              ...navItems.filter((item) => item.group === group).map((item) => {
                const active = isActive(pathname, item.href);
                const isNotificationItem = item.href === "/admin/notifications";
                return (
                  <ListItem key={item.href} disablePadding sx={{ mb: 0.25 }}>
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
              }),
            ])}
            <Divider sx={{ my: 1 }} />
            <ListItem disablePadding>
              <ListItemButton
                onClick={handleSignOut}
                sx={{ borderRadius: 1, color: "secondary.main" }}
              >
                <ListItemText primary="Logout" primaryTypographyProps={{ fontWeight: 600 }} />
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
                aria-label="Open menu"
                onClick={() => setMobileMenuOpen(true)}
                sx={{ display: "flex", ml: -1, [ROW]: { display: "none" } }}
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
                <BrandLogo branding={branding} />
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
                <Box
                  component="span"
                  title={branding?.display_name ?? undefined}
                  sx={{ display: { xs: "none", sm: "inline-block" }, [TIGHT]: { display: "none" }, maxWidth: 300, overflow: "hidden", textOverflow: "ellipsis", whiteSpace: "nowrap", verticalAlign: "bottom" }}
                >
                  {branding?.display_name ?? "IIT ISM CDC - Admin Portal"}
                </Box>
                <Box component="span" sx={{ display: { xs: "inline", sm: "none" }, [TIGHT]: { display: "inline" } }}>
                  CDC Admin
                </Box>
              </Typography>
            </Stack>

            <Stack direction="row" spacing={0.5} alignItems="center">
              <Stack direction="row" spacing={0.5} alignItems="center" sx={{ display: "none", [ROW]: { display: "flex" } }}>
                {navItems.filter((item) => item.primary).map((item) => {
                  const active = isActive(pathname, item.href);
                  return (
                    <Button
                      key={item.href}
                      component={Link}
                      href={item.href}
                      variant={active ? "contained" : "text"}
                      color={active ? "primary" : "inherit"}
                      size="small"
                      sx={{ whiteSpace: "nowrap" }}
                    >
                      {item.label}
                    </Button>
                  );
                })}
                <Button
                  color="inherit"
                  size="small"
                  endIcon={<MoreHorizIcon />}
                  onClick={() => setMobileMenuOpen(true)}
                  sx={{ whiteSpace: "nowrap" }}
                >
                  More
                </Button>
              </Stack>

              <StudentSearch />

              <IconButton component={Link} href="/admin/notifications" color="inherit" aria-label="Notifications">
                <Badge color="error" badgeContent={unreadNotifications} max={99} invisible={unreadNotifications === 0}>
                  <NotificationsIcon />
                </Badge>
              </IconButton>

              {/* Account / Settings / Logout (S8.1) replaces the Logout button; the drawer keeps Logout too. */}
              <AccountMenu onLogout={handleSignOut} />
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
