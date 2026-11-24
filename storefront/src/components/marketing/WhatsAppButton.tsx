"use client";

import { usePathname } from "next/navigation";
import type { WhatsAppConfig } from "@/lib/types";

/**
 * Botón flotante que abre una conversación con el número de la tienda (enlace wa.me).
 * En una ficha de producto usa el mensaje con {producto} y {enlace}.
 */
export function WhatsAppButton({ config }: { config: WhatsAppConfig }) {
  const pathname = usePathname() ?? "/";
  const onProduct = pathname.startsWith("/producto/");
  const hidden = ["/checkout", "/pago", "/vista-previa"].some((p) => pathname.startsWith(p));
  if (hidden || (config.pages === "product" && !onProduct)) return null;

  const open = () => {
    let text = config.message;
    if (onProduct && config.product_message) {
      const name = document.querySelector("h1")?.textContent?.trim() ?? "";
      text = config.product_message.replaceAll("{producto}", name).replaceAll("{enlace}", window.location.href);
    }
    const url = `https://wa.me/${config.phone}${text ? `?text=${encodeURIComponent(text)}` : ""}`;
    window.open(url, "_blank", "noopener,noreferrer");
  };

  return (
    <button
      type="button"
      onClick={open}
      aria-label={config.label}
      title={config.label}
      className={`fixed bottom-5 z-40 flex h-14 items-center gap-2 rounded-full bg-[#1f7a4d] px-4 text-sm font-semibold text-white shadow-lg transition-transform hover:scale-105 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 ${
        config.position === "left" ? "left-5" : "right-5"
      }`}
    >
      <svg aria-hidden viewBox="0 0 24 24" className="h-6 w-6 fill-current">
        <path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2Zm0 18.2a8.2 8.2 0 0 1-4.2-1.1l-.3-.2-3 .8.8-2.9-.2-.3A8.2 8.2 0 1 1 12 20.2Zm4.5-6.1c-.2-.1-1.5-.7-1.7-.8-.2-.1-.4-.1-.6.1l-.8 1c-.1.2-.3.2-.5.1a6.7 6.7 0 0 1-3.3-2.9c-.2-.4.2-.4.7-1.3.1-.2 0-.3 0-.4l-.8-1.8c-.2-.5-.4-.4-.6-.4h-.5a1 1 0 0 0-.7.3 3 3 0 0 0-.9 2.2 5.2 5.2 0 0 0 1.1 2.7 11.8 11.8 0 0 0 4.5 4c1.7.7 2.3.8 3.2.6a2.7 2.7 0 0 0 1.8-1.2 2.2 2.2 0 0 0 .1-1.3c0-.1-.2-.2-.4-.3Z" />
      </svg>
      <span className="hidden sm:inline">{config.label}</span>
    </button>
  );
}
