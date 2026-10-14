// The greeting line of a dashboard (design brief §7: kept from the Superset reference).
const IST = "Asia/Kolkata";

/** "Good morning" / "Good afternoon" / "Good evening", by the clock in India. */
export const greeting = (now = new Date()) => {
  const hour = Number(new Intl.DateTimeFormat("en-GB", { hour: "numeric", hour12: false, timeZone: IST }).format(now));
  return hour < 12 ? "Good morning" : hour < 17 ? "Good afternoon" : "Good evening";
};

/** "Monday, 12 October 2026" in India. */
export const todayLabel = (now = new Date()) =>
  new Intl.DateTimeFormat("en-IN", { weekday: "long", day: "numeric", month: "long", year: "numeric", timeZone: IST }).format(now);
