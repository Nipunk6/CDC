"use client";

import { useState } from "react";
import Link from "next/link";
import { useSession } from "next-auth/react";
import { Avatar, Box, Divider, IconButton, ListItemIcon, Menu, MenuItem, Tooltip, Typography } from "@mui/material";
import AccountCircleIcon from "@mui/icons-material/AccountCircleOutlined";
import SettingsIcon from "@mui/icons-material/SettingsOutlined";
import LogoutIcon from "@mui/icons-material/Logout";

const initials = (name) =>
  (name ?? "")
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase())
    .join("") || "A";

// Top-bar account menu (S8.1, Superset's avatar dropdown): Account / Settings / Logout.
export default function AccountMenu({ onLogout }) {
  const { data: session } = useSession();
  const [anchor, setAnchor] = useState(null);
  const name = session?.user?.name ?? "Admin";
  const close = () => setAnchor(null);

  return (
    <>
      <Tooltip title="Account">
        <IconButton
          onClick={(event) => setAnchor(event.currentTarget)}
          aria-label="Open account menu"
          aria-haspopup="menu"
          aria-expanded={anchor ? "true" : undefined}
          size="small"
          sx={{ ml: 0.5 }}
        >
          <Avatar sx={{ width: 32, height: 32, bgcolor: "primary.main", fontSize: "0.85rem", fontWeight: 700 }}>{initials(name)}</Avatar>
        </IconButton>
      </Tooltip>
      <Menu
        anchorEl={anchor}
        open={Boolean(anchor)}
        onClose={close}
        anchorOrigin={{ vertical: "bottom", horizontal: "right" }}
        transformOrigin={{ vertical: "top", horizontal: "right" }}
        slotProps={{ paper: { sx: { minWidth: 220, mt: 0.5 } } }}
      >
        <Box sx={{ px: 2, py: 1, maxWidth: 280 }}>
          <Typography variant="subtitle2" fontWeight={700} noWrap>
            {name}
          </Typography>
          {session?.user?.email && (
            <Typography variant="caption" color="text.secondary" noWrap component="div">
              {session.user.email}
            </Typography>
          )}
        </Box>
        <Divider />
        <MenuItem component={Link} href="/admin/settings?tab=account" onClick={close}>
          <ListItemIcon>
            <AccountCircleIcon fontSize="small" />
          </ListItemIcon>
          Account
        </MenuItem>
        <MenuItem component={Link} href="/admin/settings" onClick={close}>
          <ListItemIcon>
            <SettingsIcon fontSize="small" />
          </ListItemIcon>
          Settings
        </MenuItem>
        <Divider />
        <MenuItem
          onClick={() => {
            close();
            void onLogout();
          }}
          sx={{ color: "secondary.main" }}
        >
          <ListItemIcon sx={{ color: "inherit" }}>
            <LogoutIcon fontSize="small" />
          </ListItemIcon>
          Logout
        </MenuItem>
      </Menu>
    </>
  );
}
