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
import SupervisorAccountIcon from "@mui/icons-material/SupervisorAccountOutlined";
import AssignmentIcon from "@mui/icons-material/AssignmentOutlined";
import SchoolIcon from "@mui/icons-material/SchoolOutlined";
import BusinessIcon from "@mui/icons-material/BusinessOutlined";
import TimelineIcon from "@mui/icons-material/TimelineOutlined";
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
// Staff roles: each item names the permission it needs; `can` (from useAccess) hides the rest, and empty sections go.
/**
 * @param {{ isSuperAdmin?: boolean, can?: (permission?: string|null, level?: string) => boolean }} [options]
 */
export function adminNav({ isSuperAdmin = false, can = () => true } = {}) {
  const sections = [
    {
      heading: "Overview",
      items: [
        { label: "Dashboard", href: "/admin", permission: "dashboard", icon: DashboardIcon, exact: true },
        { label: "Analytics", href: "/admin/analytics", permission: "dashboard", icon: InsightsIcon },
        { label: "Calendar", href: "/admin/calendar", permission: "calendar", icon: CalendarMonthIcon },
        { label: "Reports", href: "/admin/reports", permission: "reports", icon: SummarizeIcon },
      ],
    },
    {
      heading: "Placement",
      items: [
        { label: "Placements", href: "/admin/placement-cycles", permission: "placement_cycles", icon: FlagIcon },
        { label: "Job Profiles", href: "/admin/postings", permission: "job_profiles", icon: WorkIcon },
        { label: "Proposals", href: "/admin/proposals", permission: "proposals", icon: RuleIcon },
        { label: "Events", href: "/admin/events", permission: "events", icon: EventIcon },
      ],
    },
    {
      heading: "Companies & Forms",
      items: [
        { label: "JNF Reviews", href: "/admin/jnfs", permission: "form_reviews", icon: AssignmentIcon },
        { label: "INF Reviews", href: "/admin/infs", permission: "form_reviews", icon: SchoolIcon },
        { label: "Companies", href: "/admin/companies", permission: "companies", icon: BusinessIcon },
        { label: "Company History", href: "/admin/company-history", permission: "companies", icon: TimelineIcon },
        { label: "Policy Documents", href: "/admin/policy-documents", permission: "policy_documents", icon: PolicyIcon },
        { label: "Alumni Outreach", href: "/admin/alumni-outreach", permission: "alumni_outreach", icon: Diversity3Icon },
      ],
    },
    {
      heading: "Students",
      items: [
        { label: "Students", href: "/admin/students", permission: "students", icon: GroupsIcon },
        { label: "Resumes", href: "/admin/resumes", permission: "resumes", icon: DescriptionIcon },
        { label: "Branch Changes", href: "/admin/branch-changes", permission: "branch_changes", icon: SwapHorizIcon },
        { label: "Student Categories", href: "/admin/student-categories", permission: "student_categories", icon: LabelIcon },
        { label: "SCPT & SPOCs", href: "/admin/scpt", permission: "scpt", icon: SupervisorAccountIcon },
      ],
    },
    {
      heading: "Engagement",
      items: [
        { label: "Notices", href: "/admin/notices", permission: "notices", icon: CampaignIcon },
        { label: "Surveys", href: "/admin/surveys", permission: "surveys", icon: PollIcon },
      ],
    },
    {
      heading: "Settings",
      items: [
        { label: "Settings", href: "/admin/settings", permission: "settings", icon: SettingsIcon },
        { label: "Excel Templates", href: "/admin/excel-templates", permission: "excel_templates", icon: GridOnIcon },
        { label: "Audit Log", href: "/admin/audit-logs", permission: "audit_log", icon: HistoryIcon },
        { label: "Login Log", href: "/admin/login-logs", permission: "login_log", icon: LoginIcon },
        // Users & Roles stays for super admins only.
        ...(isSuperAdmin ? [{ label: "Users & Roles", href: "/admin/users", icon: ManageAccountsIcon }] : []),
      ],
    },
  ];

  return sections
    .map((section) => ({ ...section, items: section.items.filter((item) => !item.permission || can(item.permission, "read")) }))
    .filter((section) => section.items.length > 0);
}
