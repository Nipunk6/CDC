import { stripHtml } from "@/components/forms/shared";

// Rich text (HTML from RichTextEditor) as plain text that keeps paragraph and line breaks. Never renders HTML (D76);
// show the result with `whiteSpace: "pre-line"`.
export const plainText = (html) => stripHtml(String(html ?? "").replace(/<\/(p|li|div|h[1-6])>|<br\s*\/?>/gi, "\n")).replace(/\n{3,}/g, "\n\n");
