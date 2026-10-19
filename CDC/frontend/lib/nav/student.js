import DashboardIcon from "@mui/icons-material/SpaceDashboardOutlined";
import CalendarMonthIcon from "@mui/icons-material/CalendarMonthOutlined";
import WorkIcon from "@mui/icons-material/WorkOutline";
import AssignmentIcon from "@mui/icons-material/AssignmentTurnedInOutlined";
import DescriptionIcon from "@mui/icons-material/DescriptionOutlined";
import CampaignIcon from "@mui/icons-material/CampaignOutlined";
import PollIcon from "@mui/icons-material/PollOutlined";
import EventIcon from "@mui/icons-material/EventOutlined";
import PersonIcon from "@mui/icons-material/PersonOutline";
import SupportAgentIcon from "@mui/icons-material/SupportAgentOutlined";

// Student portal navigation (PU.3). Shown only in the left sidebar.
export function studentNav() {
  return [
    {
      heading: "Overview",
      items: [
        { label: "Dashboard", href: "/student", icon: DashboardIcon, exact: true },
        { label: "Calendar", href: "/student/calendar", icon: CalendarMonthIcon },
      ],
    },
    {
      heading: "Placement",
      items: [
        { label: "Job Profiles", href: "/student/postings", icon: WorkIcon },
        { label: "My Applications", href: "/student/applications", icon: AssignmentIcon },
        { label: "My Resumes", href: "/student/resumes", icon: DescriptionIcon },
        { label: "SPOC", href: "/student/spoc", icon: SupportAgentIcon },
      ],
    },
    {
      heading: "Campus",
      items: [
        { label: "Notices", href: "/student/notices", icon: CampaignIcon },
        { label: "Surveys", href: "/student/surveys", icon: PollIcon },
        { label: "Events", href: "/student/events", icon: EventIcon },
      ],
    },
    { heading: "Account", items: [{ label: "Profile", href: "/student/profile", icon: PersonIcon }] },
  ];
}
