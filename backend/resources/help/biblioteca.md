# Biblioteca

**Para qué sirve:** guardar en un solo lugar las imágenes de la tienda (productos, banners, logos) y reutilizarlas. Está en *Contenido → Biblioteca*.

## Subir imágenes
**Subir imágenes** acepta JPG, PNG, WebP y GIF de hasta 10 MB cada una, varias a la vez, y permite elegir la carpeta. Al subir:
- se revisa que el archivo sea realmente una imagen (no basta con la extensión);
- se vuelve a guardar la imagen, lo que elimina datos ocultos como la ubicación GPS de la foto;
- se crean versiones pequeñas para el panel;
- si la misma imagen ya estaba en la biblioteca, no se duplica.

Las imágenes se guardan en el disco del propio servidor (`storage/app/public`). No se usa ningún servicio externo.

## Texto alternativo
Edita cada imagen para escribir el **texto alternativo**: una descripción breve de lo que se ve («Polera negra de algodón, vista frontal»). Lo leen los lectores de pantalla y los buscadores. Si lo dejas vacío, se usa el nombre del producto o el título del bloque.

## Carpetas
Con **Carpetas** se crean carpetas para ordenar (por ejemplo *Campañas*, *Logos*). Las imágenes subidas desde una página quedan en *contenido*. Filtra por carpeta en el listado.

## Dónde se usa y eliminar
**Dónde se usa** muestra los productos, marcas, categorías, valores de atributo y páginas que usan la imagen. Una imagen en uso **no se puede eliminar**: primero quítala de esos lugares. Al eliminar se borran el archivo y sus versiones pequeñas.
