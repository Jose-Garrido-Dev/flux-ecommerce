"use client";

import { useEffect, useRef } from "react";

/**
 * Publica el desplazamiento vertical en la variable CSS --scroll del contenedor.
 * Los hijos con .float-card la usan para moverse a distinta velocidad (parallax).
 * Se desactiva con movimiento reducido.
 */
export function Parallax({ children, className = "" }: { children: React.ReactNode; className?: string }) {
  const ref = useRef<HTMLDivElement>(null);

  useEffect(() => {
    if (window.matchMedia("(prefers-reduced-motion: reduce)").matches) return;
    let frame = 0;
    const update = () => {
      frame = 0;
      const y = Math.min(window.scrollY, 1200);
      ref.current?.style.setProperty("--scroll", String(y));
    };
    const onScroll = () => {
      if (!frame) frame = requestAnimationFrame(update);
    };
    window.addEventListener("scroll", onScroll, { passive: true });
    return () => {
      window.removeEventListener("scroll", onScroll);
      cancelAnimationFrame(frame);
    };
  }, []);

  return (
    <div ref={ref} className={className}>
      {children}
    </div>
  );
}
