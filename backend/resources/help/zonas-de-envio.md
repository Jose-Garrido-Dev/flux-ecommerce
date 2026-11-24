# Zonas de envío

**Para qué sirve:** agrupar destinos que cobran lo mismo (ej.: "Santiago urbano", "Regiones extremas") para usarlos en los métodos de envío con *Tarifas por zona y peso*.

## Cómo se arma una zona
| Campo | Explicación |
| --- | --- |
| Regiones completas | Todas las comunas de esas regiones entran en la zona |
| Comunas sueltas | Comunas puntuales, aunque su región no esté elegida |
| Comunas excluidas | Comunas de las regiones elegidas que **no** entran (ej.: Lo Barnechea fuera de "Santiago urbano") |
| Prioridad | Si una comuna está en dos zonas, gana la de número **menor** |

Las comunas se comparan sin importar mayúsculas, tildes ni espacios de más.

## Ejemplo
1. "Santiago urbano": región Metropolitana, excluye Lo Barnechea y Colina, prioridad 10.
2. "Resto del país": todas las regiones, prioridad 100.

Lo Barnechea queda en "Resto del país" porque está excluida de la primera.

## Reglas
- Una zona usada por tarifas no se elimina: desactívala o quita primero sus tarifas.
- Si ninguna zona incluye la comuna, los métodos por zona no se ofrecen para ese destino.
