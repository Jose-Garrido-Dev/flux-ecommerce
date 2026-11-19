# Arquitectura

El diseño técnico completo (arquitectura, esquema de base de datos de ~95 tablas, diagrama de relaciones, árbol de carpetas, panel, storefront, contratos de integración, flujos comerciales, plan de pruebas y fases) está en el documento **"Flux E-commerce Premium Intelligence — Diseño técnico v0.1"**:
<https://claude.ai/artifact/SbiSMf2wBUNh3BUGtNzmiy>

Este archivo resume las decisiones que todo desarrollador debe respetar.

## Reglas de oro

1. **Laravel decide, Next.js presenta.** Precios, totales, IVA, stock, pagos y permisos se calculan y validan solo en Laravel. Next.js nunca confirma un pago ni confía en un precio enviado por el navegador.
2. **Dinero en enteros.** Montos en la unidad mínima de la moneda (`CLP` = pesos, sin decimales) con su código ISO. Nunca `float`.
3. **El pedido guarda su historia.** Nombre, SKU, precio, IVA, dirección y despacho se copian al pedido; cambios posteriores del catálogo no lo alteran.
4. **Nada se borra si tiene historia.** Pedidos, movimientos de stock, transacciones y auditoría son de solo inserción; las correcciones son contramovimientos.
5. **Idempotencia.** Webhooks y checkout llevan claves únicas: repetirlos no duplica cobros, descuentos de stock ni correos.
6. **Integraciones verificadas.** Cada pasarela o courier implementa un contrato (`PaymentGateway`, `CourierAdapter`) y queda marcada "No verificada" hasta probarla en sandbox.
7. **Permisos en el backend.** Toda acción pasa por Policies y permisos `modulo.accion`; ocultar un botón no es seguridad.
8. **Personalización fuera del núcleo.** Lo específico de un cliente va en `custom/` o en paquetes, nunca editando `backend/app/Domain`.

## Organización del backend

```text
backend/app/
  Domain/<Dominio>/   Models, Actions, Data, Events, Policies, Contracts
  Http/               Controladores delgados de API y webhooks
  Filament/           Panel administrativo
  Support/            Utilidades transversales (Money, RUT)
```

Un dominio llama a otro solo mediante sus Actions o eventos.

## Stack

| Capa | Elección |
| --- | --- |
| Backend | Laravel 13, PHP 8.3+ |
| Panel | Filament 5 |
| Storefront | Next.js 15 (App Router), React 19, Tailwind 4 |
| Base de datos | PostgreSQL 16 |
| Colas, caché, sesiones | Redis 7 |
| Búsqueda | Meilisearch (opcional) |
| Despliegue | Docker Compose + Caddy |
