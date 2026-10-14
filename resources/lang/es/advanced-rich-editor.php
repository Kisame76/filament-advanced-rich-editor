<?php

declare(strict_types=1);

return [
    'slash' => [
        'groups' => [
            'style' => 'Estilo',
            'insert' => 'Insertar',
        ],
        'empty' => 'Ningún comando coincide',
        'aliases' => [
            'paragraph' => 'texto, párrafo, cuerpo',
            'h1' => 'título, encabezado 1',
            'h2' => 'encabezado 2, subtítulo',
            'h3' => 'encabezado 3',
            'h4' => 'encabezado 4',
            'h5' => 'encabezado 5',
            'h6' => 'encabezado 6',
            'bulletList' => 'ul, viñetas, lista, puntos',
            'orderedList' => 'ol, numerada, numeración, ordenada',
            'taskList' => 'todo, tareas, casillas, checklist, pendientes',
            'descriptionList' => 'dl, glosario, definiciones, términos, diccionario',
            'blockquote' => 'cita, comillas',
            'calloutNote' => 'nota, info, observación, recuadro, callout',
            'calloutTip' => 'consejo, sugerencia, truco, recuadro, callout',
            'calloutWarning' => 'advertencia, aviso, precaución, atención, recuadro, callout',
            'calloutDanger' => 'peligro, error, crítico, recuadro, callout',
            'codeBlock' => 'código, pre, fragmento, snippet',
            'horizontalRule' => 'hr, separador, divisor, línea',
            'details' => 'acordeón, desplegable, expandir, contraer',
            'embed' => 'video, youtube, vimeo, iframe, incrustar, multimedia',
            'video' => 'mp4, webm, película, clip, reproductor, multimedia',
            'audio' => 'mp3, sonido, música, podcast, reproductor, multimedia',
            'file' => 'archivo, documento, pdf, adjunto, descarga, word, excel, zip',
            'image' => 'img, foto, ilustración',
            'table' => 'tabla, cuadrícula, filas, columnas',
            'attachFiles' => 'subir, cargar, adjuntar',
            'emoji' => 'smiley, emoticono, carita, icono',
            'characters' => 'símbolo, carácter especial, guion, flecha, moneda, acento',
            'insertDate' => 'fecha, hoy, día',
            'insertTime' => 'hora, reloj, ahora',
            'insertDateTime' => 'fecha y hora, marca de tiempo, ahora',
            'customBlocks' => 'bloque, componente',
            'mergeTags' => 'etiqueta, variable, marcador, campo',
        ],
    ],
    /*
     * The kinds of callout. The key is the variant's own name, so a project that adds one
     * translates it by adding a key here; one without a translation is titled from its
     * name.
     */
    'callouts' => [
        'note' => 'Nota',
        'tip' => 'Consejo',
        'warning' => 'Advertencia',
        'danger' => 'Peligro',
    ],
    'tools' => [
        'image' => 'Imagen',
        // The library button. Named for the library rather than for pictures, because that
        // is what it opens onto and what it will hold more of.
        'media_browser' => 'Multimedia',
        'link' => [
            'target' => [
                'label' => 'Se abre en',
                'self' => 'Misma ventana',
                'blank' => 'Ventana nueva',
                'parent' => 'Marco padre',
                'top' => 'Marco superior',
            ],
            'rel' => [
                'label' => 'Relación (rel)',
                'new_tab_hint' => 'Un enlace que se abre en una ventana nueva recibe noopener y noreferrer automáticamente.',
                'other' => 'Otros valores de rel',
            ],
            'referrerpolicy' => 'Política de referencia',
            'hreflang' => 'Idioma de la página enlazada',
            'id' => 'Ancla',
            'internal' => [
                'label' => 'Enlazar a un registro',
                'hint' => 'Al elegir uno se rellena la URL de abajo, que es lo que guarda el enlace.',
            ],
        ],
        'code_block' => [
            'plain' => 'Texto sin formato',
        ],
        'embed' => [
            'label' => 'Video',
            'heading' => 'Insertar un video',
            'url' => 'Enlace del video',
            'url_hint' => 'Pegue el enlace de la barra de direcciones o del botón Compartir. Se conserva la marca de tiempo que contenga.',
            'title' => 'Título',
            'title_hint' => 'Lo lee un lector de pantalla en lugar de «video».',
            'ratio' => 'Relación de aspecto',
            'unsupported' => 'Ese enlace no es un video que este editor pueda insertar.',
            'providers' => [
                'youtube' => 'YouTube',
                'vimeo' => 'Vimeo',
            ],
            // The bar over a selected embed.
            'replace' => 'Reemplazar video',
            'delete' => 'Quitar',
        ],
        /*
         * A file on this server rather than one somebody else hosts. The two buttons open
         * this one dialog, so the strings are shared and `kinds` names both of them.
         */
        'media' => [
            'label' => 'Video o audio',
            'heading' => 'Video o audio de su servidor',
            'src' => 'Dirección',
            'src_hint' => 'Una ruta en este servidor, como /storage/clips/charla.mp4, o un enlace https completo.',
            'kind' => 'Tipo',
            'kinds' => [
                'video' => 'Archivo de video',
                'audio' => 'Archivo de audio',
            ],
            'poster' => 'Imagen de portada',
            'poster_hint' => 'Se muestra antes de que alguien pulse reproducir. Sin ella, el video es un rectángulo negro.',
            'title' => 'Título',
            'title_hint' => 'Lo lee un lector de pantalla en lugar del nombre del archivo.',
            'preload' => 'Precarga',
            'preload_hint' => 'Cuánto descarga el navegador antes de que se pulse reproducir.',
            'preloads' => [
                'none' => 'Nada',
                'metadata' => 'Solo duración y tamaño',
                'auto' => 'Lo que el navegador quiera',
            ],
            'loop' => 'Volver a reproducir al terminar',
            'unsupported' => 'Un navegador no reproducirá ningún archivo desde esa dirección.',
            // The bar over a selected film or sound.
            'replace' => 'Reemplazar archivo',
            'download' => 'Descargar archivo',
            'delete' => 'Quitar',
        ],
        /*
         * A document in the text, drawn as a card: the button that opens the browser on the
         * documents, and the bar over a card that is selected.
         */
        'file' => [
            'label' => 'Archivo',
            'replace' => 'Reemplazar archivo',
            'download' => 'Descargar archivo',
            'delete' => 'Quitar',
        ],
        'format_brush' => [
            'label' => 'Copiar formato',
            'once' => 'Activo para una sola aplicación',
            'sticky' => 'Activo hasta que lo desactive',
        ],

        'task_list' => 'Lista de tareas',

        'description_list' => 'Lista de definiciones',
        'callouts' => 'Recuadro',
        'language' => 'Idioma',
        'language_none' => 'Idioma de la página',
        'list_properties' => [
            'bullet' => 'Propiedades de la lista',
            'ordered' => 'Propiedades de la lista',
            'marker' => 'Marcador',
            'marker_default' => 'Predeterminado',
            'start' => 'Empezar en',
            'reversed' => 'Contar hacia atrás',
            'markers' => [
                // Named by an example rather than by a name: "a, b, c" says what the
                // choice does and "Lower alpha" says what somebody called it.
                'ordered' => [
                    '1' => '1, 2, 3',
                    'a' => 'a, b, c',
                    'A' => 'A, B, C',
                    'i' => 'i, ii, iii',
                    'I' => 'I, II, III',
                ],
                'bullet' => [
                    'disc' => 'Disco',
                    'circle' => 'Círculo',
                    'square' => 'Cuadrado',
                ],
            ],
        ],
        'characters' => [
            'label' => 'Carácter especial',
            'search' => 'Buscar caracteres...',
            'empty' => 'Ningún carácter coincide',
            'empty_recent' => 'Los caracteres que elija aparecerán aquí.',
            'close' => 'Cerrar',
            'groups' => [
                'recent' => 'Recientes',
                'punctuation' => 'Puntuación',
                'currency' => 'Monedas',
                'math' => 'Matemáticas',
                'arrows' => 'Flechas',
                'symbols' => 'Símbolos',
                'latin' => 'Letras latinas',
                'greek' => 'Letras griegas',
            ],
        ],
        'heading_level' => 'Encabezado :level',
        'styles' => 'Estilo',
        'styles_clear' => 'Ninguno',
        'font_family' => 'Fuente',
        'font_family_clear' => 'Predeterminada',
        'font_size' => [
            'label' => 'Tamaño de fuente',
            'custom' => 'Tamaño personalizado',
            'default' => 'Predeterminado',
        ],
        'alignment' => 'Alineación',
        'line_height' => [
            'label' => 'Interlineado',
            'single' => 'Sencillo (1,0)',
            'double' => 'Doble (2,0)',
            'value' => ':value',
        ],
        /*
         * The date tools. The key is the configured format's own key, so a project that
         * adds a fourth format translates it by adding a key here; one without a
         * translation is labelled with the format itself, rendered as it will read.
         */
        'date_time' => [
            'label' => 'Fecha y hora',
            // The configured keys live under their own key rather than beside 'label', so a
            // format keyed `label` cannot resolve to the dropdown's own text.
            'formats' => [
                'date' => 'Fecha',
                'time' => 'Hora',
                'date_time' => 'Fecha y hora',
            ],
        ],
        'text_case' => [
            'label' => 'Cambiar mayúsculas y minúsculas',
            // Each one is set in the case it applies, so the list says what it does without
            // three icons that would all have to mean "the letter A, but differently".
            'sentence' => 'Tipo oración',
            'lower' => 'minúsculas',
            'upper' => 'MAYÚSCULAS',
        ],
        'align' => [
            'start' => 'Izquierda',
            'center' => 'Centro',
            'end' => 'Derecha',
            'justify' => 'Justificado',
        ],
        'image_lock' => [
            'locked' => 'Relación de aspecto bloqueada',
            'unlocked' => 'Relación de aspecto desbloqueada',
        ],
        'paragraph' => 'Párrafo',
        'text_color' => 'Color de texto',
        'text_background' => 'Fondo del texto',
        'color_clear' => 'Sin color',
        'color_custom' => 'Elegir un color',
        'fullscreen' => [
            'enter' => 'Pantalla completa',
            'exit' => 'Salir de pantalla completa',
        ],
        'image_replace' => 'Reemplazar imagen',
        'image_download' => 'Descargar imagen',
        'image_delete' => 'Eliminar imagen',
        'image_float_left' => 'Flotar a la izquierda',
        'image_float_right' => 'Flotar a la derecha',
        'image_float_center' => 'Centrar',
        'image_decorative' => 'Imagen decorativa',

        'image_link' => [
            'label' => 'Enlazar imagen',
            'heading' => 'Adónde lleva esta imagen',
            'href' => 'Dirección',
            'href_help' => 'Una dirección completa, o una de este sitio como /articulos/7. Déjelo vacío para quitar el enlace.',
            'new_tab' => 'Abrir en una nueva pestaña',
        ],
        'image_rotate_left' => 'Girar a la izquierda',
        'image_rotate_right' => 'Girar a la derecha',
        'media_library' => [
            'label' => 'Imagen',
            'heading' => 'Insertar desde la biblioteca',
            'search' => 'Buscar archivos...',
            'empty_record' => 'Este registro aún no tiene nada. Suba un archivo para empezar.',
            'empty_library' => 'La biblioteca está vacía. Suba un archivo para empezar a llenarla.',
            'empty_search' => 'No hay coincidencias.',
            'up' => 'Carpeta superior',
            'pending' => 'Aún sin guardar',
            'upload' => 'Subir',
            'view_grid' => 'Mosaico',
            'view_list' => 'Lista',
            'filter' => 'Filtrar',
            'from_url' => 'Desde un enlace',
            'from_url_heading' => 'Añadir desde un enlace',
            'from_url_label' => 'Enlace',
            'from_url_hint' => 'Un enlace de YouTube o Vimeo se añade a su biblioteca y puede volver a usarse. Cualquier otra dirección — un archivo en el servidor de otra persona — se inserta tal cual, un documento como tarjeta, y no se guarda aquí.',
            'from_url_hint_files' => 'Un archivo alojado en otro sitio. Se inserta tal cual, un documento como tarjeta, y no se añade nada a su biblioteca.',
            'from_url_no_embeds' => 'Los videos de YouTube y Vimeo están desactivados para este campo.',
            'all_types' => 'Todos los tipos',
            'all_kinds' => 'Todo',
            'kinds' => [
                'image' => 'Imágenes',
                'video' => 'Video',
                'audio' => 'Audio',
                'file' => 'Archivos',
                'embed' => 'Incrustados',
            ],
            'sort' => 'Ordenar',
            'previous' => 'Página anterior',
            'next' => 'Página siguiente',
            'nothing_selected' => 'Elija un archivo para ver sus detalles.',
            // Put in front of the names of the uploads the browser refused.
            'rejected' => 'No se aceptan aquí:',
            'dismiss' => 'Descartar',
            'alt' => 'Texto alternativo',
            'title' => 'Título',
            'saved' => 'Guardado',
            'download' => 'Descargar',
            'play' => 'Reproducir aquí',
            'delete' => 'Eliminar',
            'deleting' => 'Eliminando…',
            // The confirmation is Filament's own dialog: a heading naming the file, and under it
            // what follows from it. `:name` is filled in by the browser.
            'delete_heading' => '¿Eliminar «:name» definitivamente?',
            'confirm_delete' => 'Todo lo que aún apunte a él dejará de funcionar.',
            // A library shared across records: what breaks is not on this screen.
            'confirm_delete_shared' => 'Puede que también esté en otros documentos, y estos lo perderán.',
            // A new file in the selected one's place, under the same id. `:from` and `:to` are
            // filled in by the browser, which is where both names are known.
            'replace' => 'Reemplazar',
            'replacing' => 'Subiendo…',
            'replace_heading' => '¿Reemplazar «:from» por «:to»?',
            'confirm_replace' => 'Todos los documentos que lo usan mostrarán el archivo nuevo.',
            'replace_refused' => '«:name» no puede ocupar su lugar. Se acepta: :accept',
            'replace_failed' => 'No se pudo reemplazar el archivo.',
            // How an entry using a file is named in the question before deleting or replacing it.
            'usage_entry' => ':model #:key «:title»',
            'usage_entry_untitled' => ':model #:key',
            'usage_more' => '+:count más',
            // Where the file is used in entries the server found. They are changed too: a
            // deleted file is taken out of them, a replaced one shown in its place.
            'confirm_delete_used' => 'Se usa en :count entradas (:entries) y también se quitará de ellas.',
            'confirm_delete_used_one' => 'Se usa en una entrada (:entries) y también se quitará de ella.',
            'confirm_replace_used' => 'Se usa en :count entradas (:entries), que mostrarán el archivo nuevo.',
            'confirm_replace_used_one' => 'Se usa en una entrada (:entries), que mostrará el archivo nuevo.',
            'deleted_from' => 'Eliminado y quitado de :count entradas.',
            'deleted_from_one' => 'Eliminado y quitado de una entrada.',
            'replaced_in' => 'Reemplazado y actualizado en :count entradas.',
            'replaced_in_one' => 'Reemplazado y actualizado en una entrada.',
            'copy_url' => 'Copiar enlace',
            'copied' => 'Copiado',
            'drop' => 'Suelte para subir',
            'items' => 'elementos',
            'sorts' => [
                'newest' => 'Más recientes primero',
                'oldest' => 'Más antiguos primero',
                'name' => 'Nombre',
                'largest' => 'Más grandes primero',
                'smallest' => 'Más pequeños primero',
            ],
            'details' => [
                'name' => 'Nombre',
                'size' => 'Tamaño',
                'dimensions' => 'Dimensiones',
                'type' => 'Tipo',
                'modified' => 'Modificado',
            ],
        ],
        'image_alt' => [
            'label' => 'Texto',
            'alt' => 'Texto alternativo',
            'hint' => 'Sustituye a la imagen donde no se puede ver. Déjelo vacío para quitarlo.',
            'caption' => 'Pie de foto',
            'caption_hint' => 'Se muestra bajo la imagen. Solo para una imagen en su propia línea.',
        ],
        'image_size' => [
            'label' => 'Tamaño',
            'width' => 'Ancho',
            'height' => 'Altura',
            'apply' => 'Aplicar',
            'reset' => 'Reiniciar',
        ],
        'headings' => 'Encabezados',
        'lists' => 'Listas',
        'more' => 'Más',
        'tools_menu' => 'Herramientas',
        'find' => [
            'label' => 'Buscar y reemplazar',
            'find' => 'Buscar',
            'replace' => 'Reemplazar por',
            'previous' => 'Coincidencia anterior',
            'next' => 'Coincidencia siguiente',
            'replace_one' => 'Reemplazar',
            'replace_all' => 'Reemplazar todo',
            'close' => 'Cerrar',
            'match_case' => 'Coincidir mayúsculas y minúsculas',
            'whole_word' => 'Solo palabras completas',
            'no_results' => 'Sin coincidencias',
            // Both numbers sit in one sentence, because where they sit in it is a question
            // about the language and not one the browser should be answering.
            'count' => ':current de :total',
        ],
        'source_code' => [
            'label' => 'Código fuente',
            'heading' => 'Código fuente',
            'description' => 'El HTML tal como se guarda. Lo que el editor no pueda contener se descarta al aplicarlo.',
            'apply' => 'Aplicar',
        ],
        'invisible_characters' => [
            'label' => 'Mostrar caracteres invisibles',
        ],
        'block_outlines' => [
            'label' => 'Mostrar bloques',
        ],
        'emoji' => [
            'label' => 'Emoji',
            'search' => 'Buscar emoji',
            'empty' => 'No hay coincidencias.',
            'empty_recent' => 'Los emojis que elija se reúnen aquí.',
            'close' => 'Cerrar',
            'groups' => [
                'recent' => 'Usados con frecuencia',
                'smileys' => 'Emoticonos y personas',
                'nature' => 'Animales y naturaleza',
                'food' => 'Comida y bebida',
                'activities' => 'Actividades',
                'travel' => 'Viajes y lugares',
                'objects' => 'Objetos',
                'symbols' => 'Símbolos',
                'flags' => 'Banderas',
            ],
        ],
        'direction' => [
            'ltr' => 'De izquierda a derecha',
            'rtl' => 'De derecha a izquierda',
        ],
        /*
         * The indent pair. Named for the move rather than for a state, because that is
         * what the buttons are: a block is not "indented" the way it is bold, it is one
         * step further in than it was.
         */
        'indent' => [
            'indent' => 'Aumentar sangría',
            'outdent' => 'Disminuir sangría',
        ],
    ],

    'preview' => [
        'label' => 'Vista previa',
        'heading' => 'Vista previa',
        'description' => 'El documento tal como lo dibuja su propia hoja de estilos. Los estilos del editor no se cargan aquí.',
        'frame' => 'Vista previa del documento',
    ],

    'statistics' => [
        'label' => 'Estadísticas',
        'heading' => 'Estadísticas',
        'words' => 'Palabras',
        'characters' => 'Caracteres',
        'characters_without_spaces' => 'Caracteres sin espacios',
        'paragraphs' => 'Bloques',
        'reading_time' => 'Tiempo de lectura',
        'reading_time_none' => '—',
        'reading_time_under' => 'menos de un minuto',
        'reading_time_minutes' => ':minutes min',
    ],

    'help' => [
        'label' => 'Ayuda',
        'heading' => 'Ayuda',
        'shortcuts' => 'Atajos de teclado',
        'more' => 'Más',
        'editing' => [
            'line_break' => 'Salto de línea sin nuevo párrafo',
            'paste_plain' => 'Pegar como texto sin formato',
            'indent_list' => 'Aumentar la sangría del elemento de lista',
            'outdent_list' => 'Disminuir la sangría del elemento de lista',
            'indent_block' => 'Aumentar sangría',
            'outdent_block' => 'Disminuir sangría',
            'change_case' => 'Alternar mayúsculas y minúsculas en la selección',
            'next_cell' => 'Siguiente celda de la tabla',
            'find' => 'Buscar',
            'find_replace' => 'Buscar y reemplazar',
        ],
    ],

    'accessibility' => [
        'title' => 'Comprobación de accesibilidad',
        'close' => 'Cerrar',
        'empty' => 'Nada que señalar.',
        'ratio' => ':ratio de los :needed necesarios',
        'rules' => [
            'missing_alt' => 'Imagen sin texto alternativo',
            'decorative_link' => 'Imagen enlazada sin nada que anunciar',
            'empty_link' => 'Enlace sin texto',
            'weak_link_text' => 'El texto del enlace no dice nada',
            'skipped_heading' => 'Nivel de encabezado omitido',
            'table_without_header' => 'Tabla sin fila de encabezado',
            'weak_contrast' => 'Color de texto demasiado tenue para leerse',
        ],
        // A link's whole text has to be one of these, so what belongs here is what people
        // write as the entire text of a link and nothing longer.
        'weak_link_phrases' => [
            'aquí', 'clic aquí', 'click aquí', 'haga clic aquí', 'haz clic aquí', 'pincha aquí',
            'clic', 'enlace', 'este enlace', 'más', 'leer más', 'ver más', 'más información',
            'saber más', 'detalles', 'descargar',
        ],
    ],

    'autosave' => [
        'found' => 'Se encontró en este navegador un borrador sin guardar del :time.',
        'restore' => 'Restaurarlo',
        'discard' => 'Descartarlo',
    ],

    'drag_handle' => [
        'drag' => 'Arrastre para mover este bloque, haga clic para seleccionarlo',
        'insert' => 'Añadir un bloque debajo',
    ],

    'styles' => [
        'block' => 'Bloque',
        'inline' => 'Texto',
    ],

    'fonts' => [
        'search' => 'Buscar fuentes',
        'system' => 'Sistema',
        'serif' => 'Serif',
        'monospace' => 'Monoespaciada',
    ],

    'character_count' => [
        'characters' => [
            'one' => ':count carácter',
            'other' => ':count caracteres',
        ],
        'characters_of_limit' => [
            'one' => ':count / :limit caracteres',
            'other' => ':count / :limit caracteres',
        ],
        'words' => [
            'one' => ':count palabra',
            'other' => ':count palabras',
        ],
    ],

    'validation' => [
        'min_words' => 'El campo :attribute debe tener al menos :min palabras.',
        'max_words' => 'El campo :attribute no debe tener más de :max palabras.',
        'must_contain' => 'El campo :attribute debe contener :content.',

        /*
         * What a node or a mark is called in that sentence. A type with no entry here falls
         * back to its own name, which is what makes a project's own node askable for without
         * an edit to this file.
         */
        'content' => [
            'blockquote' => 'una cita',
            'bulletList' => 'una lista con viñetas',
            'callout' => 'un recuadro',
            'codeBlock' => 'un bloque de código',
            'embed' => 'un video incrustado',
            'heading' => 'un encabezado',
            'horizontalRule' => 'una línea horizontal',
            'image' => 'una imagen',
            'link' => 'un enlace',
            'orderedList' => 'una lista numerada',
            'table' => 'una tabla',
            'taskList' => 'una lista de tareas',
            'descriptionList' => 'una lista de definiciones',
        ],
    ],
];
