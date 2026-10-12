"use client";

import { useEffect, useMemo, useState } from "react";
import Link from "next/link";
import { usePathname } from "next/navigation";
import {
  Avatar,
  Badge,
  Box,
  Divider,
  Drawer,
  IconButton,
  List,
  ListItemButton,
  ListItemIcon,
  ListItemText,
  Tooltip,
  Typography,
  alpha,
  useMediaQuery,
} from "@mui/material";
import MenuIcon from "@mui/icons-material/Menu";
import MenuOpenIcon from "@mui/icons-material/MenuOpen";
import NotificationsIcon from "@mui/icons-material/NotificationsNone";
import LogoutIcon from "@mui/icons-material/Logout";
import HistoryIcon from "@mui/icons-material/History";

import BrandLogo from "@/components/shared/brandlogo";

// The one application shell of every signed-in portal (Phase 3 PU.2, see CDC/docs/ui/DESIGN_BRIEF.md).
// All navigation between pages is in the left sidebar; the top bar only says where you are and holds global utilities.
//   >= 1200px  full sidebar (260px) that can collapse to an icon rail (72px); the choice is remembered
//   768-1199px icon rail; the menu button opens the full sidebar as a drawer
//   < 768px    no sidebar; the menu button opens it as a drawer

export const SIDEBAR_WIDTH = 260;
export const RAIL_WIDTH = 72;
export const TOPBAR_HEIGHT = 56;
export const CONTENT_MAX_WIDTH = 1440;

const FROM_TABLET = "@media (min-width:768px)";
const TABLET_ONLY = "@media (min-width:768px) and (max-width:1199.98px)";
const FROM_DESKTOP = "@media (min-width:1200px)";
const RECENT_LIMIT = 5;

const matches = (item, pathname) => (item.exact ? pathname === item.href : pathname === item.href || pathname.startsWith(`${item.href}/`));

/** The nav item for this path: the longest matching href wins ("/admin/students/invitations" over "/admin/students"). */
export function findActive(navSections, pathname) {
  let best = null;
  for (const section of navSections) {
    for (const item of section.items) {
      if (matches(item, pathname) && (!best || item.href.length > best.item.href.length)) {
        best = { section, item };
      }
    }
  }
  return best;
}

const readStored = (key, fallback) => {
  try {
    const raw = window.localStorage.getItem(key);
    return raw === null ? fallback : JSON.parse(raw);
  } catch {
    return fallback;
  }
};

const store = (key, value) => {
  try {
    window.localStorage.setItem(key, JSON.stringify(value));
  } catch {
    // Storage can be unavailable (private window, blocked); the shell works without it.
  }
};

/**
 * @param {object} props
 * @param {string} props.portal  short key of the portal ("admin", "student", …); names the remembered choices
 * @param {Array<{heading: string, items: Array<{label: string, href: string, icon: React.ElementType, exact?: boolean}>}>} props.navSections
 * @param {{title: string, subtitle: string, homeHref: string, branding?: object | null}} props.brand
 * @param {{name: string, detail?: string, initials: string, profileHref?: string}} props.user
 * @param {() => (void | Promise<void>)} props.onSignOut
 * @param {number} [props.notificationsCount]
 * @param {string} [props.notificationsHref]
 * @param {React.ReactNode} [props.utilities]  global utilities for the right of the top bar (never links to pages)
 * @param {React.ReactNode} [props.footer]
 * @param {React.ReactNode} props.children
 */
export default function AppShell({
  portal,
  navSections,
  brand,
  user,
  onSignOut,
  notificationsCount = 0,
  notificationsHref,
  utilities = null,
  footer = null,
  children,
}) {
  const pathname = usePathname();
  const isDesktop = useMediaQuery("(min-width:1200px)");
  const isTablet = useMediaQuery("(min-width:768px)");
  const [collapsed, setCollapsed] = useState(false);
  const [drawerOpen, setDrawerOpen] = useState(false);
  const [recent, setRecent] = useState([]);

  const active = useMemo(() => findActive(navSections, pathname), [navSections, pathname]);
  const collapsedKey = `cdc.shell.${portal}.collapsed`;
  const recentKey = `cdc.shell.${portal}.recent`;

  // Remembered choices are read after the first paint, so the server and the browser render the same thing first.
  useEffect(() => {
    // eslint-disable-next-line react-hooks/set-state-in-effect -- one-time read of browser storage
    setCollapsed(readStored(collapsedKey, false) === true);
    setRecent(Array.isArray(readStored(recentKey, [])) ? readStored(recentKey, []) : []);
  }, [collapsedKey, recentKey]);

  // "Recently visited": page names and paths from the nav config only, never anything about their content.
  useEffect(() => {
    if (!active) return;
    const { href, label } = active.item;
    // eslint-disable-next-line react-hooks/set-state-in-effect -- follows the route, which is external to React state
    setRecent((previous) => {
      const next = [{ href, label }, ...previous.filter((entry) => entry.href !== href)].slice(0, RECENT_LIMIT + 1);
      store(recentKey, next);
      return next;
    });
  }, [active, recentKey]);

  const railMode = isTablet && (!isDesktop || collapsed);
  const toggleSidebar = () => {
    if (isDesktop) {
      setCollapsed((value) => {
        store(collapsedKey, !value);
        return !value;
      });
    } else {
      setDrawerOpen(true);
    }
  };

  // `full` = inside the drawer, where labels always show. Otherwise labels hide by CSS whenever the sidebar is a rail.
  const sidebar = (full) => {
    const rail = !full && railMode;
    const whenRail = (styles) => (full ? {} : { [TABLET_ONLY]: styles, ...(collapsed ? { [FROM_DESKTOP]: styles } : {}) });
    const hideInRail = whenRail({ display: "none" });
    const closeDrawer = () => setDrawerOpen(false);

    const itemSx = (theme) => ({
      position: "relative",
      mx: 1.5,
      px: 1.5,
      py: 0.5,
      minHeight: 36,
      borderRadius: 1,
      color: "text.primary",
      "& .MuiListItemIcon-root": { minWidth: 36, color: "text.secondary" },
      "&:hover": { bgcolor: alpha(theme.palette.text.primary, 0.04) },
      "&.Mui-focusVisible": { outline: `2px solid ${theme.palette.primary.main}`, outlineOffset: -2 },
      "&.Mui-selected, &.Mui-selected:hover": {
        bgcolor: alpha(theme.palette.primary.main, 0.08),
        color: "primary.main",
        "& .MuiListItemIcon-root": { color: "primary.main" },
        "&::before": {
          content: '""',
          position: "absolute",
          left: -12,
          top: 6,
          bottom: 6,
          width: 3,
          borderRadius: "0 3px 3px 0",
          bgcolor: "primary.main",
        },
      },
      ...whenRail({ justifyContent: "center", px: 0, "& .MuiListItemIcon-root": { minWidth: 0 } }),
    });

    const navItem = (item, { selected, badge = 0, onClick } = {}) => {
      const Icon = item.icon;
      return (
        <Tooltip key={item.href ?? item.label} title={rail ? item.label : ""} placement="right" disableInteractive>
          <ListItemButton
            component={item.href ? Link : "button"}
            href={item.href}
            selected={Boolean(selected)}
            aria-current={selected ? "page" : undefined}
            aria-label={item.label}
            onClick={() => {
              closeDrawer();
              onClick?.();
            }}
            sx={itemSx}
          >
            <ListItemIcon>
              <Badge color="error" badgeContent={badge} max={99} invisible={!badge}>
                <Icon fontSize="small" />
              </Badge>
            </ListItemIcon>
            <ListItemText
              primary={item.label}
              sx={{ my: 0, ...hideInRail }}
              slotProps={{ primary: { noWrap: true, sx: { fontSize: "0.875rem", lineHeight: "20px", fontWeight: selected ? 600 : 500 } } }}
            />
          </ListItemButton>
        </Tooltip>
      );
    };

    const recentItems = recent.filter((entry) => entry.href !== active?.item.href).slice(0, RECENT_LIMIT);

    return (
      <Box sx={{ display: "flex", flexDirection: "column", height: "100%", bgcolor: "background.paper" }}>
        {/* The one saturated block of the screen: the institute's maroon, with the logo and the portal's name. */}
        <Box
          component={Link}
          href={brand.homeHref}
          onClick={closeDrawer}
          sx={{
            display: "flex",
            alignItems: "center",
            gap: 1.25,
            px: 2,
            height: 64,
            flexShrink: 0,
            bgcolor: "primary.main",
            color: "primary.contrastText",
            textDecoration: "none",
            ...whenRail({ justifyContent: "center", px: 0 }),
          }}
        >
          <BrandLogo branding={brand.branding} size={36} alt="IIT (ISM) Dhanbad" />
          <Box sx={{ minWidth: 0, ...hideInRail }}>
            <Typography noWrap sx={{ fontWeight: 700, fontSize: "0.9375rem", lineHeight: 1.3 }}>
              {brand.title}
            </Typography>
            <Typography noWrap variant="caption" component="div" title={brand.subtitle} sx={{ opacity: 0.85, lineHeight: 1.3 }}>
              {brand.subtitle}
            </Typography>
          </Box>
        </Box>

        <Box component="nav" aria-label={`${brand.title} navigation`} sx={{ flex: 1, overflowY: "auto", overflowX: "hidden", py: 1 }}>
          {navSections.map((section, index) => (
            <Box key={section.heading}>
              <Typography
                component="div"
                sx={{
                  px: 3,
                  pt: index === 0 ? 1 : 2,
                  pb: 0.5,
                  fontSize: "0.6875rem",
                  lineHeight: "16px",
                  fontWeight: 600,
                  letterSpacing: "0.06em",
                  textTransform: "uppercase",
                  color: "text.secondary",
                  ...hideInRail,
                }}
              >
                {section.heading}
              </Typography>
              {index > 0 && <Divider sx={{ display: "none", mx: 2, my: 1, ...whenRail({ display: "block" }) }} />}
              <List disablePadding sx={{ display: "flex", flexDirection: "column", gap: 0.25 }}>
                {section.items.map((item) => navItem(item, { selected: active?.item.href === item.href }))}
              </List>
            </Box>
          ))}

          {recentItems.length > 0 && (
            <Box sx={hideInRail}>
              <Typography
                component="div"
                sx={{ px: 3, pt: 2, pb: 0.5, fontSize: "0.6875rem", lineHeight: "16px", fontWeight: 600, letterSpacing: "0.06em", textTransform: "uppercase", color: "text.secondary" }}
              >
                Recently visited
              </Typography>
              <List disablePadding sx={{ display: "flex", flexDirection: "column", gap: 0.25 }}>
                {recentItems.map((entry) => navItem({ ...entry, icon: HistoryIcon }))}
              </List>
            </Box>
          )}
        </Box>

        <Divider />
        <List disablePadding sx={{ py: 1, display: "flex", flexDirection: "column", gap: 0.25, flexShrink: 0 }}>
          {notificationsHref &&
            navItem(
              { label: "Notifications", href: notificationsHref, icon: NotificationsIcon },
              { selected: pathname === notificationsHref || pathname.startsWith(`${notificationsHref}/`), badge: notificationsCount },
            )}
          <Tooltip title={rail ? user.name : ""} placement="right" disableInteractive>
            <ListItemButton
              component={user.profileHref ? Link : "div"}
              href={user.profileHref}
              onClick={closeDrawer}
              aria-label={`Signed in as ${user.name}`}
              sx={(theme) => ({ ...itemSx(theme), minHeight: 48, cursor: user.profileHref ? "pointer" : "default" })}
            >
              <ListItemIcon>
                <Avatar
                  sx={(theme) => ({ width: 28, height: 28, fontSize: "0.75rem", fontWeight: 700, bgcolor: alpha(theme.palette.primary.main, 0.12), color: "primary.main" })}
                >
                  {user.initials}
                </Avatar>
              </ListItemIcon>
              <ListItemText
                primary={user.name}
                secondary={user.detail}
                sx={{ my: 0, ...hideInRail }}
                slotProps={{
                  primary: { noWrap: true, sx: { fontSize: "0.875rem", fontWeight: 600, lineHeight: 1.3 } },
                  secondary: { noWrap: true, sx: { fontSize: "0.75rem", lineHeight: 1.3 } },
                }}
              />
            </ListItemButton>
          </Tooltip>
          {navItem({ label: "Sign out", icon: LogoutIcon }, { onClick: () => void onSignOut() })}
        </List>
      </Box>
    );
  };

  return (
    <Box sx={{ display: "flex", minHeight: "100vh", bgcolor: "background.default" }}>
      {/* Permanent sidebar (tablet and up). Its width is set by CSS, so nothing jumps when the page loads. */}
      <Box
        component="aside"
        sx={{
          display: "none",
          [FROM_TABLET]: { display: "block" },
          flexShrink: 0,
          width: RAIL_WIDTH,
          [FROM_DESKTOP]: { width: collapsed ? RAIL_WIDTH : SIDEBAR_WIDTH },
          transition: "width 160ms ease",
          "@media (prefers-reduced-motion: reduce)": { transition: "none" },
        }}
      >
        <Box
          sx={{
            position: "fixed",
            top: 0,
            bottom: 0,
            left: 0,
            width: "inherit",
            borderRight: 1,
            borderColor: "divider",
            overflow: "hidden",
          }}
        >
          {sidebar(false)}
        </Box>
      </Box>

      {/* The same sidebar as a drawer, for phones and for the tablet rail. */}
      <Drawer open={drawerOpen} onClose={() => setDrawerOpen(false)} slotProps={{ paper: { sx: { width: SIDEBAR_WIDTH, maxWidth: "86vw", borderRadius: 0 } } }}>
        {sidebar(true)}
      </Drawer>

      <Box sx={{ flex: 1, minWidth: 0, display: "flex", flexDirection: "column" }}>
        <Box
          component="header"
          sx={{
            position: "sticky",
            top: 0,
            zIndex: (theme) => theme.zIndex.appBar,
            height: TOPBAR_HEIGHT,
            flexShrink: 0,
            display: "flex",
            alignItems: "center",
            gap: 1,
            px: { xs: 1, sm: 2 },
            bgcolor: "background.paper",
            borderBottom: 1,
            borderColor: "divider",
          }}
        >
          <IconButton
            onClick={toggleSidebar}
            aria-label={isDesktop ? (collapsed ? "Expand the sidebar" : "Collapse the sidebar") : "Open the menu"}
            edge="start"
            sx={{ ml: 0 }}
          >
            {isDesktop && !collapsed ? <MenuOpenIcon /> : <MenuIcon />}
          </IconButton>

          {/* Where you are. Never a link to another page. */}
          <Box sx={{ flex: 1, minWidth: 0, display: "flex", alignItems: "baseline", gap: 1 }}>
            {active && (
              <Typography noWrap variant="body2" color="text.secondary" sx={{ display: "none", [FROM_TABLET]: { display: "block" }, flexShrink: 0 }}>
                {active.section.heading} /
              </Typography>
            )}
            <Typography noWrap component="div" sx={{ fontSize: "0.9375rem", fontWeight: 600 }}>
              {active?.item.label ?? (pathname === notificationsHref ? "Notifications" : brand.title)}
            </Typography>
          </Box>

          <Box sx={{ display: "flex", alignItems: "center", gap: 0.5 }}>
            {utilities}
            {notificationsHref && (
              <IconButton component={Link} href={notificationsHref} aria-label={notificationsCount ? `Notifications, ${notificationsCount} unread` : "Notifications"}>
                <Badge color="error" badgeContent={notificationsCount} max={99} invisible={!notificationsCount}>
                  <NotificationsIcon />
                </Badge>
              </IconButton>
            )}
          </Box>
        </Box>

        <Box component="main" sx={{ flex: 1, minWidth: 0, width: "100%", maxWidth: CONTENT_MAX_WIDTH, px: { xs: 2, md: 3 }, py: 3 }}>
          {children}
        </Box>

        {footer && (
          <Box component="footer" sx={{ px: { xs: 2, md: 3 }, py: 2, maxWidth: CONTENT_MAX_WIDTH }}>
            {footer}
          </Box>
        )}
      </Box>
    </Box>
  );
}
