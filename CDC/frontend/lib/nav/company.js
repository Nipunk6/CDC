import DashboardIcon from "@mui/icons-material/SpaceDashboardOutlined";
import AssignmentIcon from "@mui/icons-material/AssignmentOutlined";
import SchoolIcon from "@mui/icons-material/SchoolOutlined";
import InboxIcon from "@mui/icons-material/InboxOutlined";
import WorkIcon from "@mui/icons-material/WorkOutline";
import BusinessIcon from "@mui/icons-material/BusinessOutlined";

// Recruiter portal navigation (PU.3). Shown only in the left sidebar.
export function companyNav() {
  return [
    { heading: "Overview", items: [{ label: "Dashboard", href: "/company", icon: DashboardIcon, exact: true }] },
    {
      heading: "Forms",
      items: [
        { label: "JNF Form", href: "/company/jnf/new", icon: AssignmentIcon },
        { label: "INF Form", href: "/company/inf/new", icon: SchoolIcon },
        { label: "Submissions", href: "/company/submissions", icon: InboxIcon },
      ],
    },
    { heading: "Hiring", items: [{ label: "Job Profiles", href: "/company/postings", icon: WorkIcon }] },
    { heading: "Account", items: [{ label: "Profile", href: "/company/profile", icon: BusinessIcon }] },
  ];
}
