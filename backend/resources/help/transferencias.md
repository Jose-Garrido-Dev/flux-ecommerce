# Transferencias entre bodegas

**Para qué sirve:** mover stock de una bodega a otra dejando registro.

## Pasos
1. **Nueva transferencia**.
2. Elige la bodega de origen (*Desde*) y la de destino (*Hacia*).
3. Agrega los productos y la cantidad de cada uno.
4. Guarda. Se registra una salida en el origen y una entrada en el destino en el mismo momento.

## Reglas
- Solo se puede transferir el stock disponible (físico menos reservado) del origen.
- Si un producto no tiene stock suficiente no se transfiere nada: corrige la cantidad y vuelve a intentar.
- Una transferencia no se edita ni se borra. Para deshacerla, crea otra en sentido contrario.

## Relaciones con otros módulos
- **Movimientos** muestra las dos partes de cada transferencia.
- **Bodegas** define desde y hacia dónde se puede mover.
