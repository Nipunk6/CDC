import CompanyShell from "@/components/company/companyshell";

export default function CompanyLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return <CompanyShell>{children}</CompanyShell>;
}
