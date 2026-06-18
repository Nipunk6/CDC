import type { Metadata } from "next";
import "./globals.css";
import ThemeRegistry from "./themeregistry";

export const metadata: Metadata = {
  title: "IIT ISM CDC Placement Portal",
  description: "Career Development Centre - Placement Portal for IIT (ISM) Dhanbad",
};

export default function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  return (
    <html lang="en">
      <body>
        <ThemeRegistry>
          {children}
        </ThemeRegistry>
      </body>
    </html>
  );
}
