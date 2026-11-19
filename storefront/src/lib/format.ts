const clp = new Intl.NumberFormat("es-CL", { style: "currency", currency: "CLP", maximumFractionDigits: 0 });

/** 12990 → "$12.990" */
export function formatMoney(amount: number, currency = "CLP"): string {
  if (currency === "CLP") return clp.format(amount);
  return new Intl.NumberFormat("es-CL", { style: "currency", currency }).format(amount / 100);
}

export function mediaUrl(path: string): string {
  return `/media/${path.split("/").map(encodeURIComponent).join("/")}`;
}
