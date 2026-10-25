/** Mirrors App\Support\Phone on the backend: 01XXXXXXXXX, Bangla digits and +88 accepted. */
export const BD_PHONE = /^01[3-9]\d{8}$/;

export function normalizePhone(input: string): string {
  const latin = input.replace(/[০-৯]/g, (d) => String(d.charCodeAt(0) - 0x09e6));
  const digits = latin.replace(/\D/g, "");
  return digits.startsWith("880") ? digits.slice(2) : digits;
}
