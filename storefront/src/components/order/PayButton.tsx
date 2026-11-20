"use client";

import { useRouter } from "next/navigation";
import { useState } from "react";
import { startPayment } from "@/lib/pay";
import { buttonClass } from "@/components/ui/Button";

/** Inicia o reintenta el pago con pasarela de un pedido pendiente. */
export function PayButton({ orderToken, label }: { orderToken: string; label: string }) {
  const router = useRouter();
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  return (
    <div>
      <button
        type="button"
        disabled={busy}
        onClick={async () => {
          setBusy(true);
          setError(null);
          const res = await startPayment(orderToken);
          if (!res.ok) {
            setBusy(false);
            setError(res.message);
          } else if (res.paid) {
            router.replace(`/pedido/${orderToken}?pago=aprobado`);
            router.refresh();
          }
        }}
        className={buttonClass("primary", "h-12 px-8")}
      >
        {busy ? "Te llevamos a pagar…" : label}
      </button>
      {error && (
        <p role="alert" className="mt-3 text-sm text-sale">
          {error}
        </p>
      )}
    </div>
  );
}
