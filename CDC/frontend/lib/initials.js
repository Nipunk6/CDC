/** Up to two capital initials of a person's name, for avatars ("Asha Rao" -> "AR"). */
export const initials = (name, fallback = "?") =>
  String(name ?? "")
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase())
    .join("") || fallback;
