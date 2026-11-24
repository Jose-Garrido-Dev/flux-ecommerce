# SEO y redirecciones

**Para qué sirve:** que una dirección antigua lleve a la nueva, sin perder visitas ni posicionamiento. Está en *Contenido → SEO y redirecciones*.

## Automáticas
Cuando cambias la URL de un producto, categoría, marca, página publicada o artículo, se crea una redirección permanente (301) de la antigua a la nueva. Si la nueva dirección tenía una redirección, se elimina (ahora tiene contenido).

## Manuales
Útiles al migrar desde otra tienda: **Nueva redirección**, dirección antigua (por ejemplo /tienda/polera-negra) y destino.
- **Permanente (301):** traspasa el posicionamiento. Úsala casi siempre.
- **Temporal (302):** para cambios de pocos días.

El sistema evita ciclos (A → B → A) y apunta las cadenas directo al destino final (A → B → C queda A → C). Las rutas del sistema (/api, /admin, /media, /vista-previa) no se pueden redirigir. Máximo 5.000 redirecciones.

La columna **Visitas** cuenta cuántas veces se usó cada redirección.

## Mapa del sitio y robots
- **/sitemap.xml** lista la portada, el catálogo, las categorías, marcas y productos visibles, las páginas publicadas y los artículos del blog. Se actualiza solo.
- **/robots.txt** indica a los buscadores que no indexen el carrito, el pago, Mi cuenta, el seguimiento ni la vista previa, y dónde está el mapa del sitio.
- Envía el mapa del sitio a Google Search Console una vez instalada la tienda (guía de instalación).
