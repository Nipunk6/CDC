"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import { usePathname } from "next/navigation";
import { signOut, useSession } from "next-auth/react";
import {
  AppBar,
  Avatar,
  Badge,
  Box,
  Button,
  Divider,
  Drawer,
  IconButton,
  List,
  ListItemButton,
  ListItemIcon,
  ListItemText,
  Paper,
  Toolbar,
  Typography,
  alpha,
  useTheme,
} from "@mui/material";
import MenuIcon from "@mui/icons-material/Menu";
import DashboardIcon from "@mui/icons-material/SpaceDashboard";
import WorkIcon from "@mui/icons-material/WorkOutline";
import AssignmentIcon from "@mui/icons-material/AssignmentTurnedIn";
import DescriptionIcon from "@mui/icons-material/Description";
import EventIcon from "@mui/icons-material/Event";
import CalendarMonthIcon from "@mui/icons-material/CalendarMonth";
import NotificationsIcon from "@mui/icons-material/NotificationsNone";
import PersonIcon from "@mui/icons-material/PersonOutline";
import LogoutIcon from "@mui/icons-material/Logout";
import BlockIcon from "@mui/icons-material/Block";
import CampaignIcon from "@mui/icons-material/Campaign";
import PollIcon from "@mui/icons-material/Poll";

import { SESSION_EXPIRED_EVENT, SUSPENDED_EVENT, studentApi } from "@/lib/studentapi";
import BrandLogo from "@/components/shared/brandlogo";
import { useBranding } from "@/lib/branding";

const DRAWER_WIDTH = 260;

const navItems = [
  { label: "Dashboard", href: "/student", icon: <DashboardIcon /> },
  { label: "Job Profiles", href: "/student/postings", icon: <WorkIcon /> },
  { label: "My Applications", href: "/student/applications", icon: <AssignmentIcon /> },
  { label: "My Resumes", href: "/student/resumes", icon: <DescriptionIcon /> },
  { label: "Notices", href: "/student/notices", icon: <CampaignIcon /> },
  { label: "Surveys", href: "/student/surveys", icon: <PollIcon /> },
  { label: "Events", href: "/student/events", icon: <EventIcon /> },
  { label: "Calendar", href: "/student/calendar", icon: <CalendarMonthIcon /> },
  { label: "Notifications", href: "/student/notifications", icon: <NotificationsIcon /> },
  { label: "Profile", href: "/student/profile", icon: <PersonIcon /> },
];

const handleSignOut = async () => {
  try {
    await studentApi("/auth/logout", { method: "POST" }); // revoke the Sanctum token server-side
  } catch {
    // ignore - the client session is cleared regardless
  }
  await signOut({ callbackUrl: "/auth/login/student" });
};

const isActive = (pathname, href) => (href === "/student" ? pathname === href : pathname.startsWith(href));

export default function StudentShell({ children }) {
  const theme = useTheme();
  const pathname = usePathname();
  const { data: session } = useSession();
  const [mobileOpen, setMobileOpen] = useState(false);
  const [unread, setUnread] = useState(0);
  const [suspended, setSuspended] = useState(false);
  const branding = useBranding();

  // Any student API call that meets a suspended account or a dead token lands here (QA F-020).
  useEffect(() => {
    const onSuspended = () => setSuspended(true);
    const onExpired = () => void signOut({ callbackUrl: "/auth/login/student" });
    window.addEventListener(SUSPENDED_EVENT, onSuspended);
    window.addEventListener(SESSION_EXPIRED_EVENT, onExpired);
    return () => {
      window.removeEventListener(SUSPENDED_EVENT, onSuspended);
      window.removeEventListener(SESSION_EXPIRED_EVENT, onExpired);
    };
  }, []);

  useEffect(() => {
    const run = async () => {
      try {
        const response = await studentApi(`/auth/notifications?ts=${Date.now()}`);
        setUnread(response.unread_count ?? 0);
      } catch {
        setUnread(0);
      }
    };

    const handleUpdated = (event) => {
      if (typeof event.detail === "number") {
        setUnread(event.detail);
        return;
      }
      void run();
    };

    window.addEventListener("student-notifications-updated", handleUpdated);
    void run();
    return () => window.removeEventListener("student-notifications-updated", handleUpdated);
  }, [pathname]);

  const drawer = (
    <Box sx={{ display: "flex", flexDirection: "column", height: "100%" }}>
      <Box
        sx={{
          px: 2.5,
          py: 2.5,
          background: `linear-gradient(135deg, ${theme.palette.primary.main} 0%, ${theme.palette.primary.dark} 100%)`,
          color: "white",
        }}
      >
        <Box sx={{ display: "flex", alignItems: "center", gap: 1.25, mb: 2 }}>
          <BrandLogo branding={branding} alt="IIT ISM" />
          <Box sx={{ minWidth: 0 }}>
            <Typography fontWeight={700} lineHeight={1.2}>
              CDC Placement Portal
            </Typography>
            <Typography variant="caption" component="div" sx={{ opacity: 0.85, overflowWrap: "anywhere" }}>
              {branding?.display_name ?? "IIT (ISM) Dhanbad"}
            </Typography>
          </Box>
        </Box>
        <Box sx={{ display: "flex", alignItems: "center", gap: 1.25 }}>
          <Avatar sx={{ bgcolor: "white", color: "primary.main", width: 36, height: 36, fontWeight: 700 }}>
            {session?.user?.name?.[0] ?? "S"}
          </Avatar>
          <Box sx={{ minWidth: 0 }}>
            <Typography variant="body2" fontWeight={700} noWrap>
              {session?.user?.name ?? "Student"}
            </Typography>
            <Typography variant="caption" sx={{ opacity: 0.85 }}>
              {session?.user?.rollNo ?? ""}
            </Typography>
          </Box>
        </Box>
      </Box>

      <List sx={{ px: 1.5, py: 1.5, flex: 1 }}>
        {navItems.map((item) => {
          const active = isActive(pathname, item.href);
          const isNotifications = item.href === "/student/notifications";
          return (
            <ListItemButton
              key={item.href}
              component={Link}
              href={item.href}
              onClick={() => setMobileOpen(false)}
              selected={active}
              sx={{
                borderRadius: 1.5,
                mb: 0.5,
                "&.Mui-selected": {
                  bgcolor: alpha(theme.palette.primary.main, 0.1),
                  color: "primary.main",
                  "& .MuiListItemIcon-root": { color: "primary.main" },
                  "&:hover": { bgcolor: alpha(theme.palette.primary.main, 0.15) },
                },
              }}
            >
              <ListItemIcon sx={{ minWidth: 40 }}>
                {isNotifications ? (
                  <Badge color="error" badgeContent={unread} max={99} invisible={unread === 0}>
                    {item.icon}
                  </Badge>
                ) : (
                  item.icon
                )}
              </ListItemIcon>
              <ListItemText primary={item.label} primaryTypographyProps={{ fontWeight: active ? 700 : 500 }} />
            </ListItemButton>
          );
        })}
      </List>

      <Divider />
      <List sx={{ px: 1.5 }}>
        <ListItemButton onClick={handleSignOut} sx={{ borderRadius: 1.5, color: "secondary.main" }}>
          <ListItemIcon sx={{ minWidth: 40, color: "secondary.main" }}>
            <LogoutIcon />
          </ListItemIcon>
          <ListItemText primary="Logout" primaryTypographyProps={{ fontWeight: 600 }} />
        </ListItemButton>
      </List>
    </Box>
  );

  const current = navItems.find((item) => isActive(pathname, item.href));

  if (suspended) {
    return (
      <Box sx={{ minHeight: "100vh", display: "grid", placeItems: "center", bgcolor: "#f6f7fb", px: 2 }}>
        <Paper variant="outlined" sx={{ p: { xs: 3, sm: 4 }, maxWidth: 440, textAlign: "center" }} role="alert">
          <BlockIcon color="error" sx={{ fontSize: 48, mb: 1 }} />
          <Typography variant="h5" fontWeight={700} gutterBottom>
            Account suspended
          </Typography>
          <Typography color="text.secondary" sx={{ mb: 3 }}>
            Your account has been suspended by the CDC. Contact the CDC office to restore access.
          </Typography>
          <Button variant="contained" onClick={() => void signOut({ callbackUrl: "/auth/login/student" })}>
            Logout
          </Button>
        </Paper>
      </Box>
    );
  }

  return (
    <Box sx={{ display: "flex", minHeight: "100vh", bgcolor: "#f6f7fb" }}>
      <AppBar
        position="fixed"
        color="inherit"
        elevation={0}
        sx={{
          display: { md: "none" },
          borderBottom: "1px solid #dce4ee",
        }}
      >
        <Toolbar>
          <IconButton edge="start" onClick={() => setMobileOpen(true)} sx={{ mr: 1 }} aria-label="Open menu">
            <MenuIcon />
          </IconButton>
          <Typography variant="h6" color="primary.main" fontWeight={700} noWrap sx={{ flex: 1 }}>
            {current?.label ?? "CDC Portal"}
          </Typography>
          <IconButton component={Link} href="/student/notifications" aria-label="Notifications">
            <Badge color="error" badgeContent={unread} max={99} invisible={unread === 0}>
              <NotificationsIcon />
            </Badge>
          </IconButton>
        </Toolbar>
      </AppBar>

      <Box component="nav" sx={{ width: { md: DRAWER_WIDTH }, flexShrink: { md: 0 } }}>
        <Drawer
          variant="temporary"
          open={mobileOpen}
          onClose={() => setMobileOpen(false)}
          ModalProps={{ keepMounted: true }}
          sx={{ display: { xs: "block", md: "none" }, "& .MuiDrawer-paper": { width: DRAWER_WIDTH } }}
        >
          {drawer}
        </Drawer>
        <Drawer
          variant="permanent"
          open
          sx={{
            display: { xs: "none", md: "block" },
            "& .MuiDrawer-paper": { width: DRAWER_WIDTH, borderRight: "1px solid #e5e7eb" },
          }}
        >
          {drawer}
        </Drawer>
      </Box>

      <Box
        component="main"
        sx={{
          flexGrow: 1,
          minWidth: 0,
          width: { md: `calc(100% - ${DRAWER_WIDTH}px)` },
          px: { xs: 2, sm: 3, md: 4 },
          pt: { xs: 10, md: 4 },
          pb: 4,
        }}
      >
        {children}
      </Box>
    </Box>
  );
}
