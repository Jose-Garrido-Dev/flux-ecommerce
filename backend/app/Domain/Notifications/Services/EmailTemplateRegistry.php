<?php

namespace App\Domain\Notifications\Services;

/**
 * Los correos que envía la tienda, con su texto por defecto. El asunto, el título y el texto principal
 * se pueden editar en Correos → Plantillas; las tablas, botones y datos (productos, cuenta bancaria, etc.)
 * los arma el sistema para que no se rompan. Variables entre llaves: {pedido}, {nombre}, etc.
 */
final class EmailTemplateRegistry
{
    public const COMMON_VARIABLES = ['tienda' => 'Nombre de la tienda'];

    public const ORDER_VARIABLES = [
        'nombre' => 'Nombre del cliente', 'pedido' => 'Número del pedido', 'total' => 'Total del pedido con signo $',
    ];

    /** @return array<string, array{group: string, name: string, description: string, subject: string, heading: string, intro: string, variables: array<string, string>, required: bool}> */
    public static function all(): array
    {
        $order = self::ORDER_VARIABLES;

        return [
            'order_placed' => [
                'group' => 'Pedidos', 'name' => 'Pedido recibido (pago por transferencia)', 'required' => true,
                'description' => 'Al cliente, al crear un pedido que se paga por transferencia. Después del texto van los datos de la cuenta y los productos.',
                'subject' => 'Recibimos tu pedido N.º {pedido} · {tienda}',
                'heading' => '¡Gracias por tu compra, {nombre}!',
                'intro' => 'Recibimos tu pedido **N.º {pedido}**. Para completarlo, transfiere el total a la siguiente cuenta:',
                'variables' => $order,
            ],
            'payment_confirmed' => [
                'group' => 'Pedidos', 'name' => 'Pago confirmado', 'required' => true,
                'description' => 'Al cliente, cuando se confirma el pago (transferencia o pasarela).',
                'subject' => 'Pago confirmado: pedido N.º {pedido} · {tienda}',
                'heading' => 'Pago confirmado',
                'intro' => 'Hola {nombre}, confirmamos el pago de tu pedido **N.º {pedido}** por **{total}**. Ya lo estamos preparando.',
                'variables' => $order,
            ],
            'order_cancelled' => [
                'group' => 'Pedidos', 'name' => 'Pedido cancelado', 'required' => false,
                'description' => 'Al cliente, cuando un pedido sin pagar se cancela (por la tienda, por el cliente o porque venció el plazo).',
                'subject' => 'Tu pedido N.º {pedido} fue cancelado · {tienda}',
                'heading' => 'Pedido cancelado',
                'intro' => "Hola {nombre}, tu pedido **N.º {pedido}** fue cancelado. {motivo}\n\nNo se hizo ningún cargo. Si fue un error, puedes volver a comprar cuando quieras.",
                'variables' => $order + ['motivo' => 'Motivo de la cancelación'],
            ],
            'refund' => [
                'group' => 'Pedidos', 'name' => 'Reembolso hecho', 'required' => true,
                'description' => 'Al cliente, al registrar un reembolso total o parcial.',
                'subject' => 'Reembolso del pedido N.º {pedido} · {tienda}',
                'heading' => 'Reembolso del pedido N.º {pedido}',
                'intro' => 'Hola {nombre}, hicimos un reembolso de **{monto}** de tu pedido.',
                'variables' => $order + ['monto' => 'Monto reembolsado con signo $'],
            ],
            'new_order_admin' => [
                'group' => 'Avisos a la tienda', 'name' => 'Nuevo pedido', 'required' => false,
                'description' => 'Al correo de contacto de la tienda, cuando llega un pedido.',
                'subject' => 'Nuevo pedido N.º {pedido} por {total}',
                'heading' => 'Nuevo pedido N.º {pedido}',
                'intro' => 'Llegó un pedido de {nombre} por **{total}**.',
                'variables' => $order,
            ],
            'shipment_shipped' => [
                'group' => 'Despachos', 'name' => 'Pedido despachado', 'required' => false,
                'description' => 'Al cliente, cuando un despacho se marca Despachado. Incluye número y enlace de seguimiento.',
                'subject' => 'Tu pedido N.º {pedido} va en camino · {tienda}',
                'heading' => 'Tu pedido va en camino',
                'intro' => 'Hola {nombre}, tu pedido N.º {pedido} salió con **{courier}**.',
                'variables' => $order + ['courier' => 'Courier o "Despacho propio"', 'seguimiento' => 'Número de seguimiento'],
            ],
            'shipment_ready_for_pickup' => [
                'group' => 'Despachos', 'name' => 'Listo para retiro', 'required' => false,
                'description' => 'Al cliente, cuando un retiro en tienda está listo. Incluye la dirección.',
                'subject' => 'Tu pedido N.º {pedido} está listo para retiro · {tienda}',
                'heading' => 'Tu pedido está listo para retiro',
                'intro' => "Hola {nombre}, ya puedes retirar tu pedido N.º {pedido}.\n\nLleva tu número de pedido y un documento de identidad.",
                'variables' => $order,
            ],
            'shipment_delivered' => [
                'group' => 'Despachos', 'name' => 'Pedido entregado', 'required' => false,
                'description' => 'Al cliente, cuando un despacho se marca Entregado.',
                'subject' => 'Entregamos tu pedido N.º {pedido} · {tienda}',
                'heading' => 'Entregamos tu pedido',
                'intro' => 'Hola {nombre}, tu pedido N.º {pedido} fue entregado. ¡Gracias por tu compra!',
                'variables' => $order,
            ],
            'shipment_picked_up' => [
                'group' => 'Despachos', 'name' => 'Pedido retirado', 'required' => false,
                'description' => 'Al cliente, cuando se registra el retiro en tienda.',
                'subject' => 'Retiraste tu pedido N.º {pedido} · {tienda}',
                'heading' => 'Retiraste tu pedido',
                'intro' => 'Hola {nombre}, registramos el retiro de tu pedido N.º {pedido}. ¡Gracias por tu compra!',
                'variables' => $order,
            ],
            'return_requested' => [
                'group' => 'Devoluciones', 'name' => 'Devolución solicitada', 'required' => false,
                'description' => 'Al cliente, al recibir su solicitud de devolución o cambio.',
                'subject' => 'Recibimos tu solicitud de devolución {devolucion} · {tienda}',
                'heading' => 'Recibimos tu solicitud',
                'intro' => 'Hola {nombre}, recibimos tu solicitud **{devolucion}** del pedido N.º {pedido}. La revisaremos y te responderemos por este medio.',
                'variables' => $order + ['devolucion' => 'Número de la devolución'],
            ],
            'return_approved' => [
                'group' => 'Devoluciones', 'name' => 'Devolución aprobada', 'required' => true,
                'description' => 'Al cliente, al aprobar la devolución. Después del texto van las instrucciones para enviar el producto.',
                'subject' => 'Aprobamos tu devolución {devolucion} · {tienda}',
                'heading' => 'Aprobamos tu devolución',
                'intro' => 'Hola {nombre}, aprobamos tu solicitud **{devolucion}** del pedido N.º {pedido}. Sigue estas instrucciones para enviarnos el producto:',
                'variables' => $order + ['devolucion' => 'Número de la devolución'],
            ],
            'return_rejected' => [
                'group' => 'Devoluciones', 'name' => 'Devolución rechazada', 'required' => true,
                'description' => 'Al cliente, al rechazar la solicitud. Incluye el motivo.',
                'subject' => 'Sobre tu solicitud de devolución {devolucion} · {tienda}',
                'heading' => 'Revisamos tu solicitud',
                'intro' => "Hola {nombre}, revisamos tu solicitud **{devolucion}** del pedido N.º {pedido} y no podemos aceptarla.\n\n**Motivo:** {motivo}",
                'variables' => $order + ['devolucion' => 'Número de la devolución', 'motivo' => 'Motivo del rechazo'],
            ],
            'return_processed' => [
                'group' => 'Devoluciones', 'name' => 'Devolución resuelta', 'required' => true,
                'description' => 'Al cliente, cuando se hace el reembolso o el cambio de una devolución.',
                'subject' => 'Tu devolución {devolucion} está resuelta · {tienda}',
                'heading' => 'Devolución resuelta',
                'intro' => "Hola {nombre}, recibimos los productos de tu solicitud **{devolucion}** y la dimos por resuelta.\n\n{resolucion}",
                'variables' => $order + ['devolucion' => 'Número de la devolución', 'resolucion' => 'Qué se hizo: reembolso con monto o cambio'],
            ],
            'return_new_admin' => [
                'group' => 'Avisos a la tienda', 'name' => 'Nueva solicitud de devolución', 'required' => false,
                'description' => 'Al correo de contacto de la tienda, cuando un cliente pide una devolución.',
                'subject' => 'Nueva devolución {devolucion} del pedido N.º {pedido}',
                'heading' => 'Nueva solicitud de devolución',
                'intro' => '{nombre} pidió la devolución **{devolucion}** del pedido N.º {pedido}. **Motivo:** {motivo}',
                'variables' => $order + ['devolucion' => 'Número de la devolución', 'motivo' => 'Motivo elegido por el cliente'],
            ],
            'customer_verify' => [
                'group' => 'Cuentas de clientes', 'name' => 'Confirmar correo', 'required' => true,
                'description' => 'Al cliente que crea una cuenta. Incluye el botón para confirmar.',
                'subject' => 'Confirma tu correo · {tienda}',
                'heading' => 'Hola, {nombre}',
                'intro' => 'Gracias por crear tu cuenta en {tienda}. Confirma tu correo para ver en tu cuenta los pedidos que hiciste antes con este mismo correo.',
                'variables' => ['nombre' => 'Nombre del cliente'],
            ],
            'customer_reset' => [
                'group' => 'Cuentas de clientes', 'name' => 'Restablecer contraseña', 'required' => true,
                'description' => 'Al cliente que pide cambiar su contraseña. Incluye el botón y el plazo del enlace.',
                'subject' => 'Restablece tu contraseña · {tienda}',
                'heading' => 'Hola, {nombre}',
                'intro' => 'Recibimos una solicitud para cambiar la contraseña de tu cuenta en {tienda}.',
                'variables' => ['nombre' => 'Nombre del cliente'],
            ],
        ];
    }

    public static function get(string $key): array
    {
        return self::all()[$key] ?? throw new \InvalidArgumentException("Plantilla de correo desconocida: $key");
    }
}
