import { Typography } from "@mui/material";

// The credit line every portal shows under its content (same text as before PU, now a quiet caption on the page).
export default function ShellFooter() {
  return (
    <>
      <Typography variant="caption" color="text.secondary" component="p" sx={{ m: 0 }}>
        Copyright © {new Date().getFullYear()} All Rights Reserved || Designed & Developed by - The Batch of Mathematics and Computing BTech-2028
      </Typography>
      {/* Kept as before PU: present and selectable, in the colour of the page. */}
      <Typography component="p" sx={{ m: 0, mt: 0.5, fontSize: "0.6rem", color: "background.default", userSelect: "text", cursor: "default" }}>
        Special contribution- Saurabh Pathak, Rohit Garg, Nipun Kansal, Nehmeet Patel
      </Typography>
    </>
  );
}
