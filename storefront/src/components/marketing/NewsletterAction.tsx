"use client";

import Link from "next/link";
import { useState } from "react";
import { bff } from "@/lib/client-api";
import { buttonClass } from "@/components/ui/Button";

/**
 * Confirmar la suscripción o darse de baja requiere un clic: los antivirus de correo abren los
 * enlaces automáticamente y no deben confirmar ni dar de baja por su cuenta.
 */
export function NewsletterAction({ action, token }: { action: "confirm" | "unsubscribe"; token: string }) {
  const [state, setState] = useState<"idle" | "busy" | "done" | "error">("idle");
  const [message, setMessage] = useState<string | null>(null);

  const run = async () => {
    setState("busy");
    const res = await bff<{ message: string }>(`newsletter/${action}`, { method: "POST", body: { token } });
    setState(res.ok ? "done" : "error");
    setMessage(res.ok ? (res.data?.message ?? null) : (res.errors.token ?? res.message));
  };

  if (state === "done" || state === "error") {
    return (
      <div className="mt-8">
        <p role={state === "error" ? "alert" : "status"} className={state === "error" ? "text-sale" : "text-lg"}>
          {message}
        </p>
        <Link href="/catalogo" className={buttonClass("primary", "mt-8")}>
          Ver el catálogo
        </Link>
      </div>
    );
  }

  return (
    <button type="button" onClick={run} disabled={state === "busy"} className={buttonClass(action === "confirm" ? "primary" : "ghost", "mt-8 h-12 px-8")}>
      {state === "busy" ? "Un momento…" : action === "confirm" ? "Confirmar mi suscripción" : "Darme de baja"}
    </button>
  );
}
