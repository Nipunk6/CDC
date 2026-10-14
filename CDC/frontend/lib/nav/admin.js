import DashboardIcon from "@mui/icons-material/SpaceDashboardOutlined";
import InsightsIcon from "@mui/icons-material/InsightsOutlined";
import CalendarMonthIcon from "@mui/icons-material/CalendarMonthOutlined";
import SummarizeIcon from "@mui/icons-material/SummarizeOutlined";
import FlagIcon from "@mui/icons-material/FlagOutlined";
import WorkIcon from "@mui/icons-material/WorkOutline";
import RuleIcon from "@mui/icons-material/RuleOutlined";
import EventIcon from "@mui/icons-material/EventOutlined";
import GroupsIcon from "@mui/icons-material/GroupsOutlined";
import DescriptionIcon from "@mui/icons-material/DescriptionOutlined";
import SwapHorizIcon from "@mui/icons-material/SwapHorizOutlined";
import LabelIcon from "@mui/icons-material/LabelOutlined";
import AssignmentIcon from "@mui/icons-material/AssignmentOutlined";
import SchoolIcon from "@mui/icons-material/SchoolOutlined";
import BusinessIcon from "@mui/icons-material/BusinessOutlined";
import PolicyIcon from "@mui/icons-material/PolicyOutlined";
import Diversity3Icon from "@mui/icons-material/Diversity3Outlined";
import CampaignIcon from "@mui/icons-material/CampaignOutlined";
import PollIcon from "@mui/icons-material/PollOutlined";
import SettingsIcon from "@mui/icons-material/SettingsOutlined";
import GridOnIcon from "@mui/icons-material/GridOnOutlined";
import HistoryIcon from "@mui/icons-material/HistoryOutlined";
import LoginIcon from "@mui/icons-material/LoginOutlined";
import ManageAccountsIcon from "@mui/icons-material/ManageAccountsOutlined";

// Admin portal navigation (PU.3). Every link to an admin page lives here and is shown only in the left sidebar.
// Notifications, the signed-in person and Sign out are added by the shell at the bottom of the sidebar.
export function adminNav({ isSuperAdmin = false } = {}) {
  return [
    {
      heading: "Overview",
      items: [
        { label: "Dashboard", href: "/admin", icon: DashboardIcon, exact: true },
        { label: "Analytics", href: "/admin/analytics", icon: InsightsIcon },
        { label: "Calendar", href: "/admin/calendar", icon: CalendarMonthIcon },
        { label: "Reports", href: "/admin/reports", icon: SummarizeIcon },
      ],
    },
    {
      heading: "Placement",
      items: [
        { label: "Placements", href: "/admin/placement-cycles", icon: FlagIcon },
        { label: "Job Profiles", href: "/admin/postings", icon: WorkIcon },
        { label: "Proposals", href: "/admin/proposals", icon: RuleIcon },
        { label: "Events", href: "/admin/events", icon: EventIcon },
      ],
    },
    {
      heading: "Companies & Forms",
      items: [
        { label: "JNF Reviews", href: "/admin/jnfs", icon: AssignmentIcon },
        { label: "INF Reviews", href: "/admin/infs", icon: SchoolIcon },
        { label: "Companies", href: "/admin/companies", icon: BusinessIcon },
        { label: "Policy Documents", href: "/admin/policy-documents", icon: PolicyIcon },
        { label: "Alumni Outreach", href: "/admin/alumni-outreach", icon: Diversity3Icon },
      ],
    },
    {
      heading: "Students",
      items: [
        { label: "Students", href: "/admin/students", icon: GroupsIcon },
        { label: "Resumes", href: "/admin/resumes", icon: DescriptionIcon },
        { label: "Branch Changes", href: "/admin/branch-changes", icon: SwapHorizIcon },
        { label: "Student Categories", href: "/admin/student-categories", icon: LabelIcon },
      ],
    },
    {
      heading: "Engagement",
      items: [
        { label: "Notices", href: "/admin/notices", icon: CampaignIcon },
        { label: "Surveys", href: "/admin/surveys", icon: PollIcon },
      ],
    },
    {
      heading: "Settings",
      items: [
        { label: "Settings", href: "/admin/settings", icon: SettingsIcon },
        { label: "Excel Templates", href: "/admin/excel-templates", icon: GridOnIcon },
        { label: "Audit Log", href: "/admin/audit-logs", icon: HistoryIcon },
        { label: "Login Log", href: "/admin/login-logs", icon: LoginIcon },
        // Manage Admins stays for super admins only.
        ...(isSuperAdmin ? [{ label: "Users", href: "/admin/users", icon: ManageAccountsIcon }] : []),
      ],
    },
  ];
}
