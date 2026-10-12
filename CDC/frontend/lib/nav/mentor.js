import DashboardIcon from "@mui/icons-material/SpaceDashboardOutlined";

// Mentor portal navigation (PU.3). The mentee and resume pages are added here when they are built (P8).
export function mentorNav() {
  return [{ heading: "Overview", items: [{ label: "Home", href: "/mentor", icon: DashboardIcon, exact: true }] }];
}
