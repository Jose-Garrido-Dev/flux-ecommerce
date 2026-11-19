"use client";

import { useEffect, useState } from "react";

function parts(ms: number) {
  const s = Math.max(0, Math.floor(ms / 1000));
  return { d: Math.floor(s / 86400), h: Math.floor((s % 86400) / 3600), m: Math.floor((s % 3600) / 60), s: s % 60 };
}

/** Cuenta regresiva hasta el fin real de una oferta (fecha que entrega Laravel). */
export function Countdown({ until }: { until: string }) {
  const end = new Date(until).getTime();
  const [now, setNow] = useState<number | null>(null);

  useEffect(() => {
    setNow(Date.now());
    const id = setInterval(() => setNow(Date.now()), 1000);
    return () => clearInterval(id);
  }, []);

  const p = parts(end - (now ?? end));
  const cells: [number, string][] = [
    [p.d, "días"],
    [p.h, "horas"],
    [p.m, "min"],
    [p.s, "seg"],
  ];

  return (
    <div role="timer" aria-label="Tiempo restante de la oferta" className="flex gap-3">
      {cells.map(([v, label]) => (
        <div key={label} className="min-w-16 rounded-[var(--radius-sm)] border border-white/10 bg-white/5 px-3 py-3 text-center backdrop-blur">
          <span className="block font-display text-3xl tabular-nums" aria-hidden={label === "seg"}>
            {now === null ? "--" : String(v).padStart(2, "0")}
          </span>
          <span className="text-sm font-semibold text-ink-muted">{label}</span>
        </div>
      ))}
    </div>
  );
}
