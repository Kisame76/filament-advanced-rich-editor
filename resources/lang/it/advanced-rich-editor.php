<?php

declare(strict_types=1);

return [
    'slash' => [
        'groups' => [
            'style' => 'Stile',
            'insert' => 'Inserisci',
        ],
        'empty' => 'Nessun comando corrispondente',
        'aliases' => [
            'paragraph' => 'testo, paragrafo, corpo',
            'h1' => 'titolo, intestazione 1',
            'h2' => 'intestazione 2, sottotitolo',
            'h3' => 'intestazione 3',
            'h4' => 'intestazione 4',
            'h5' => 'intestazione 5',
            'h6' => 'intestazione 6',
            'bulletList' => 'ul, elenco, lista, puntato, punti',
            'orderedList' => 'ol, numerato, numeri, numerazione',
            'taskList' => 'todo, attività, checklist, spunta, compiti',
            'descriptionList' => 'dl, glossario, definizioni, termini, dizionario',
            'blockquote' => 'citazione, cita',
            'calloutNote' => 'nota, info, riquadro, callout',
            'calloutTip' => 'suggerimento, consiglio, riquadro, callout',
            'calloutWarning' => 'attenzione, avvertenza, avviso, riquadro, callout',
            'calloutDanger' => 'pericolo, errore, critico, riquadro, callout',
            'codeBlock' => 'codice, code, snippet',
            'horizontalRule' => 'hr, linea, separatore, divisore',
            'details' => 'accordion, fisarmonica, espandi, comprimi',
            'embed' => 'video, youtube, vimeo, incorpora, iframe',
            'video' => 'mp4, webm, filmato, clip, player, media',
            'audio' => 'mp3, suono, musica, podcast, player, media',
            'file' => 'documento, pdf, allegato, download, scarica, word, excel, zip',
            'image' => 'img, immagine, foto, figura',
            'table' => 'tabella, griglia, righe, colonne',
            'attachFiles' => 'carica, upload, invia',
            'emoji' => 'faccina, emoticon, smiley, icona',
            'characters' => 'simbolo, carattere speciale, trattino, freccia, valuta, accento',
            'insertDate' => 'data, oggi, giorno',
            'insertTime' => 'ora, orario, orologio, adesso',
            'insertDateTime' => 'data e ora, timestamp, adesso',
            'customBlocks' => 'blocco, componente',
            'mergeTags' => 'tag, variabile, segnaposto',
        ],
    ],
    /*
     * The kinds of callout. The key is the variant's own name, so a project that adds one
     * translates it by adding a key here; one without a translation is titled from its
     * name.
     */
    'callouts' => [
        'note' => 'Nota',
        'tip' => 'Suggerimento',
        'warning' => 'Attenzione',
        'danger' => 'Pericolo',
    ],
    'tools' => [
        'image' => 'Immagine',
        // The library button. Named for the library rather than for pictures, because that
        // is what it opens onto and what it will hold more of.
        'media_browser' => 'Media',
        'link' => [
            'target' => [
                'label' => 'Si apre in',
                'self' => 'Stessa finestra',
                'blank' => 'Nuova finestra',
                'parent' => 'Frame padre',
                'top' => 'Frame superiore',
            ],
            'rel' => [
                'label' => 'Relazione (rel)',
                'new_tab_hint' => 'A un link che si apre in una nuova finestra vengono aggiunti automaticamente noopener e noreferrer.',
                'other' => 'Altri valori rel',
            ],
            'referrerpolicy' => 'Criterio referrer',
            'hreflang' => 'Lingua della pagina collegata',
            'id' => 'Ancora',
            'internal' => [
                'label' => 'Collega a un record',
                'hint' => 'La scelta compila l\'URL qui sotto, che è ciò che il link memorizza.',
            ],
        ],
        'code_block' => [
            'plain' => 'Testo semplice',
        ],
        'embed' => [
            'label' => 'Video',
            'heading' => 'Incorpora un video',
            'url' => 'Link del video',
            'url_hint' => 'Incolla il link dalla barra degli indirizzi o dal pulsante Condividi. Un eventuale minuto di inizio viene mantenuto.',
            'title' => 'Titolo',
            'title_hint' => 'Letto da uno screen reader al posto di “video”.',
            'ratio' => 'Proporzioni',
            'unsupported' => 'Questo link non è un video che l\'editor può incorporare.',
            'providers' => [
                'youtube' => 'YouTube',
                'vimeo' => 'Vimeo',
            ],
            // The bar over a selected embed.
            'replace' => 'Sostituisci video',
            'delete' => 'Rimuovi',
        ],
        /*
         * A file on this server rather than one somebody else hosts. The two buttons open
         * this one dialog, so the strings are shared and `kinds` names both of them.
         */
        'media' => [
            'label' => 'Video o audio',
            'heading' => 'Video o audio dal tuo server',
            'src' => 'Indirizzo',
            'src_hint' => 'Un percorso su questo server, ad esempio /storage/clips/intervento.mp4, oppure un link https completo.',
            'kind' => 'Tipo',
            'kinds' => [
                'video' => 'File video',
                'audio' => 'File audio',
            ],
            'poster' => 'Immagine di anteprima',
            'poster_hint' => 'Mostrata prima che qualcuno prema play. Senza, il video è un rettangolo nero.',
            'title' => 'Titolo',
            'title_hint' => 'Letto da uno screen reader al posto del nome del file.',
            'preload' => 'Precaricamento',
            'preload_hint' => 'Quanto scarica il browser prima che venga premuto play.',
            'preloads' => [
                'none' => 'Niente',
                'metadata' => 'Solo durata e dimensioni',
                'auto' => 'Quanto vuole il browser',
            ],
            'loop' => 'Ricomincia quando finisce',
            'unsupported' => 'Da questo indirizzo un browser non riproduce alcun file.',
            // The bar over a selected film or sound.
            'replace' => 'Sostituisci file',
            'download' => 'Scarica file',
            'delete' => 'Rimuovi',
        ],
        /*
         * A document in the text, drawn as a card: the button that opens the browser on the
         * documents, and the bar over a card that is selected.
         */
        'file' => [
            'label' => 'File',
            'replace' => 'Sostituisci file',
            'download' => 'Scarica file',
            'delete' => 'Rimuovi',
        ],
        'format_brush' => [
            'label' => 'Copia formato',
            'once' => 'Attivo per una sola applicazione',
            'sticky' => 'Attivo finché non lo disattivi',
        ],

        'task_list' => 'Elenco attività',

        'description_list' => 'Elenco di definizioni',
        'callouts' => 'Riquadro',
        'language' => 'Lingua',
        'language_none' => 'Lingua della pagina',
        'list_properties' => [
            'bullet' => 'Proprietà elenco',
            'ordered' => 'Proprietà elenco',
            'marker' => 'Marcatore',
            'marker_default' => 'Predefinito',
            'start' => 'Inizia da',
            'reversed' => 'Numerazione inversa',
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
                    'disc' => 'Punto',
                    'circle' => 'Cerchio',
                    'square' => 'Quadrato',
                ],
            ],
        ],
        'characters' => [
            'label' => 'Carattere speciale',
            'search' => 'Cerca caratteri...',
            'empty' => 'Nessun carattere corrispondente',
            'empty_recent' => 'I caratteri che scegli compaiono qui.',
            'close' => 'Chiudi',
            'groups' => [
                'recent' => 'Recenti',
                'punctuation' => 'Punteggiatura',
                'currency' => 'Valute',
                'math' => 'Matematica',
                'arrows' => 'Frecce',
                'symbols' => 'Simboli',
                'latin' => 'Lettere latine',
                'greek' => 'Lettere greche',
            ],
        ],
        'heading_level' => 'Titolo :level',
        'styles' => 'Stile',
        'styles_clear' => 'Nessuno',
        'font_family' => 'Carattere',
        'font_family_clear' => 'Predefinito',
        'font_size' => [
            'label' => 'Dimensione carattere',
            'custom' => 'Dimensione personalizzata',
            'default' => 'Predefinita',
        ],
        'alignment' => 'Allineamento',
        'line_height' => [
            'label' => 'Interlinea',
            'single' => 'Singola (1,0)',
            'double' => 'Doppia (2,0)',
            'value' => ':value',
        ],
        /*
         * The date tools. The key is the configured format's own key, so a project that
         * adds a fourth format translates it by adding a key here; one without a
         * translation is labelled with the format itself, rendered as it will read.
         */
        'date_time' => [
            'label' => 'Data e ora',
            // The configured keys live under their own key rather than beside 'label', so a
            // format keyed `label` cannot resolve to the dropdown's own text.
            'formats' => [
                'date' => 'Data',
                'time' => 'Ora',
                'date_time' => 'Data e ora',
            ],
        ],
        'text_case' => [
            'label' => 'Cambia maiuscole/minuscole',
            // Each one is set in the case it applies, so the list says what it does without
            // three icons that would all have to mean "the letter A, but differently".
            'sentence' => 'Tipo frase',
            'lower' => 'minuscolo',
            'upper' => 'MAIUSCOLO',
        ],
        'align' => [
            'start' => 'A sinistra',
            'center' => 'Al centro',
            'end' => 'A destra',
            'justify' => 'Giustificato',
        ],
        'image_lock' => [
            'locked' => 'Proporzioni bloccate',
            'unlocked' => 'Proporzioni sbloccate',
        ],
        'paragraph' => 'Paragrafo',
        'text_color' => 'Colore testo',
        'text_background' => 'Sfondo testo',
        'color_clear' => 'Nessun colore',
        'color_custom' => 'Scegli un colore',
        'fullscreen' => [
            'enter' => 'Schermo intero',
            'exit' => 'Esci da schermo intero',
        ],
        'image_replace' => 'Sostituisci immagine',
        'image_download' => 'Scarica immagine',
        'image_delete' => 'Elimina immagine',
        'image_float_left' => 'Allinea a sinistra',
        'image_float_right' => 'Allinea a destra',
        'image_float_center' => 'Centra',
        'image_decorative' => 'Immagine decorativa',

        'image_link' => [
            'label' => 'Collega immagine',
            'heading' => 'Dove punta questa immagine',
            'href' => 'Indirizzo',
            'href_help' => 'Un indirizzo completo, o uno interno a questo sito come /articoli/7. Lascia vuoto per rimuovere il link.',
            'new_tab' => 'Apri in una nuova scheda',
        ],
        'image_rotate_left' => 'Ruota a sinistra',
        'image_rotate_right' => 'Ruota a destra',
        'media_library' => [
            'label' => 'Immagine',
            'heading' => 'Inserisci dalla libreria',
            'search' => 'Cerca file...',
            'empty_record' => 'Ancora nulla in questo record. Carica un file per iniziare.',
            'empty_library' => 'La libreria è vuota. Carica un file per iniziare.',
            'empty_search' => 'Nessun risultato.',
            'up' => 'Cartella superiore',
            'pending' => 'Non ancora salvato',
            'upload' => 'Carica',
            'view_grid' => 'Riquadri',
            'view_list' => 'Elenco',
            'filter' => 'Filtro',
            'from_url' => 'Da un link',
            'from_url_heading' => 'Aggiungi da un link',
            'from_url_label' => 'Link',
            'from_url_hint' => 'Un link di YouTube o Vimeo viene aggiunto alla tua libreria e può essere riutilizzato. Qualsiasi altro indirizzo — un file sul server di qualcun altro — viene inserito così com\'è, un documento come scheda, e non viene salvato qui.',
            'from_url_hint_files' => 'Un file ospitato da qualcun altro. Viene inserito così com\'è, un documento come scheda, e alla tua libreria non viene aggiunto nulla.',
            'from_url_no_embeds' => 'I video di YouTube e Vimeo sono disattivati per questo campo.',
            'all_types' => 'Tutti i tipi',
            'all_kinds' => 'Tutti',
            'kinds' => [
                'image' => 'Immagini',
                'video' => 'Video',
                'audio' => 'Audio',
                'file' => 'File',
                'embed' => 'Incorporati',
            ],
            'sort' => 'Ordina',
            'previous' => 'Pagina precedente',
            'next' => 'Pagina successiva',
            'nothing_selected' => 'Scegli un file per vederne i dettagli.',
            // Put in front of the names of the uploads the browser refused.
            'rejected' => 'Non accettati qui:',
            'dismiss' => 'Nascondi',
            'alt' => 'Testo alternativo',
            'title' => 'Titolo',
            'saved' => 'Salvato',
            'download' => 'Scarica',
            'play' => 'Riproduci qui',
            'delete' => 'Elimina',
            'deleting' => 'Eliminazione…',
            // The confirmation is Filament's own dialog: a heading naming the file, and under it
            // what follows from it. `:name` is filled in by the browser.
            'delete_heading' => 'Eliminare definitivamente “:name”?',
            'confirm_delete' => 'Tutto ciò che vi punta ancora smetterà di funzionare.',
            // A library shared across records: what breaks is not on this screen.
            'confirm_delete_shared' => 'Potrebbe trovarsi anche in altri documenti, che lo perderanno.',
            // A new file in the selected one's place, under the same id. `:from` and `:to` are
            // filled in by the browser, which is where both names are known.
            'replace' => 'Sostituisci',
            'replacing' => 'Caricamento…',
            'replace_heading' => 'Sostituire “:from” con “:to”?',
            'confirm_replace' => 'Ogni documento che lo usa mostrerà il nuovo file.',
            'replace_refused' => '“:name” non può prenderne il posto. Tipi accettati: :accept',
            'replace_failed' => 'Impossibile sostituire il file.',
            // How an entry using a file is named in the question before deleting or replacing it.
            'usage_entry' => ':model #:key “:title”',
            'usage_entry_untitled' => ':model #:key',
            'usage_more' => '+:count altri',
            // Where the file is used in entries the server found. They are changed too: a
            // deleted file is taken out of them, a replaced one shown in its place.
            'confirm_delete_used' => 'È usato in :count voci (:entries) e verrà rimosso anche da queste.',
            'confirm_delete_used_one' => 'È usato in una voce (:entries) e verrà rimosso anche da questa.',
            'confirm_replace_used' => 'È usato in :count voci (:entries), che mostreranno il nuovo file.',
            'confirm_replace_used_one' => 'È usato in una voce (:entries), che mostrerà il nuovo file.',
            'deleted_from' => 'Eliminato e rimosso da :count voci.',
            'deleted_from_one' => 'Eliminato e rimosso da una voce.',
            'replaced_in' => 'Sostituito e aggiornato in :count voci.',
            'replaced_in_one' => 'Sostituito e aggiornato in una voce.',
            'copy_url' => 'Copia link',
            'copied' => 'Copiato',
            'drop' => 'Rilascia per caricare',
            'items' => 'elementi',
            'sorts' => [
                'newest' => 'Prima i più recenti',
                'oldest' => 'Prima i meno recenti',
                'name' => 'Nome',
                'largest' => 'Prima i più grandi',
                'smallest' => 'Prima i più piccoli',
            ],
            'details' => [
                'name' => 'Nome',
                'size' => 'Dimensione',
                'dimensions' => 'Dimensioni',
                'type' => 'Tipo',
                'modified' => 'Modificato',
            ],
        ],
        'image_alt' => [
            'label' => 'Testo',
            'alt' => 'Testo alternativo',
            'hint' => 'Sostituisce l\'immagine dove non può essere vista. Lascia vuoto per rimuoverlo.',
            'caption' => 'Didascalia',
            'caption_hint' => 'Mostrata sotto l\'immagine. Solo per un\'immagine su una riga a sé.',
        ],
        'image_size' => [
            'label' => 'Dimensioni',
            'width' => 'Larghezza',
            'height' => 'Altezza',
            'apply' => 'Applica',
            'reset' => 'Reimposta',
        ],
        'headings' => 'Titoli',
        'lists' => 'Elenchi',
        'more' => 'Altro',
        'tools_menu' => 'Strumenti',
        'find' => [
            'label' => 'Trova e sostituisci',
            'find' => 'Trova',
            'replace' => 'Sostituisci con',
            'previous' => 'Risultato precedente',
            'next' => 'Risultato successivo',
            'replace_one' => 'Sostituisci',
            'replace_all' => 'Sostituisci tutto',
            'close' => 'Chiudi',
            'match_case' => 'Maiuscole/minuscole',
            'whole_word' => 'Solo parole intere',
            'no_results' => 'Nessun risultato',
            // Both numbers sit in one sentence, because where they sit in it is a question
            // about the language and not one the browser should be answering.
            'count' => ':current di :total',
        ],
        'source_code' => [
            'label' => 'Codice sorgente',
            'heading' => 'Codice sorgente',
            'description' => 'L\'HTML così come viene salvato. Ciò che l\'editor non può contenere viene scartato quando lo applichi.',
            'apply' => 'Applica',
        ],
        'invisible_characters' => [
            'label' => 'Mostra caratteri invisibili',
        ],
        'block_outlines' => [
            'label' => 'Mostra blocchi',
        ],
        'emoji' => [
            'label' => 'Emoji',
            'search' => 'Cerca emoji',
            'empty' => 'Nessun risultato.',
            'empty_recent' => 'Le emoji che scegli si raccolgono qui.',
            'close' => 'Chiudi',
            'groups' => [
                'recent' => 'Usate di frequente',
                'smileys' => 'Faccine e persone',
                'nature' => 'Animali e natura',
                'food' => 'Cibo e bevande',
                'activities' => 'Attività',
                'travel' => 'Viaggi e luoghi',
                'objects' => 'Oggetti',
                'symbols' => 'Simboli',
                'flags' => 'Bandiere',
            ],
        ],
        'direction' => [
            'ltr' => 'Da sinistra a destra',
            'rtl' => 'Da destra a sinistra',
        ],
        /*
         * The indent pair. Named for the move rather than for a state, because that is
         * what the buttons are: a block is not "indented" the way it is bold, it is one
         * step further in than it was.
         */
        'indent' => [
            'indent' => 'Aumenta rientro',
            'outdent' => 'Riduci rientro',
        ],
    ],

    'preview' => [
        'label' => 'Anteprima',
        'heading' => 'Anteprima',
        'description' => 'Il documento come lo disegna il tuo foglio di stile. Lo stile dell\'editor qui non viene caricato.',
        'frame' => 'Anteprima del documento',
    ],

    'statistics' => [
        'label' => 'Statistiche',
        'heading' => 'Statistiche',
        'words' => 'Parole',
        'characters' => 'Caratteri',
        'characters_without_spaces' => 'Caratteri senza spazi',
        'paragraphs' => 'Blocchi',
        'reading_time' => 'Tempo di lettura',
        'reading_time_none' => '—',
        'reading_time_under' => 'meno di un minuto',
        'reading_time_minutes' => ':minutes min',
    ],

    'help' => [
        'label' => 'Aiuto',
        'heading' => 'Aiuto',
        'shortcuts' => 'Scorciatoie da tastiera',
        'more' => 'Altro',
        'editing' => [
            'line_break' => 'A capo senza nuovo paragrafo',
            'paste_plain' => 'Incolla come testo semplice',
            'indent_list' => 'Aumenta rientro della voce di elenco',
            'outdent_list' => 'Riduci rientro della voce di elenco',
            'indent_block' => 'Aumenta rientro',
            'outdent_block' => 'Riduci rientro',
            'change_case' => 'Alterna maiuscole/minuscole della selezione',
            'next_cell' => 'Cella successiva della tabella',
            'find' => 'Trova',
            'find_replace' => 'Trova e sostituisci',
        ],
    ],

    'accessibility' => [
        'title' => 'Verifica accessibilità',
        'close' => 'Chiudi',
        'empty' => 'Niente da segnalare.',
        'ratio' => ':ratio su :needed richiesto',
        'rules' => [
            'missing_alt' => 'Immagine senza testo alternativo',
            'decorative_link' => 'Immagine collegata senza nulla da annunciare',
            'empty_link' => 'Link senza testo',
            'weak_link_text' => 'Il testo del link non dice nulla',
            'skipped_heading' => 'Livello di titolo saltato',
            'table_without_header' => 'Tabella senza riga di intestazione',
            'weak_contrast' => 'Colore del testo troppo debole per essere letto',
        ],
        // A link's whole text has to be one of these, so what belongs here is what people
        // write as the entire text of a link and nothing longer.
        'weak_link_phrases' => [
            'qui', 'clicca qui', 'questo link', 'link', 'leggi di più', 'leggi tutto',
            'continua a leggere', 'scopri di più', 'per saperne di più', 'approfondisci',
            'maggiori informazioni', 'altro', 'dettagli', 'scarica', 'download', 'vai',
        ],
    ],

    'autosave' => [
        'found' => 'In questo browser è stata trovata una bozza non salvata del :time.',
        'restore' => 'Ripristina',
        'discard' => 'Scarta',
    ],

    'drag_handle' => [
        'drag' => 'Trascina per spostare questo blocco, fai clic per selezionarlo',
        'insert' => 'Aggiungi un blocco sotto',
    ],

    'styles' => [
        'block' => 'Blocco',
        'inline' => 'Testo',
    ],

    'fonts' => [
        'search' => 'Cerca font',
        'system' => 'Sistema',
        'serif' => 'Serif',
        'monospace' => 'Monospazio',
    ],

    'character_count' => [
        'characters' => [
            'one' => ':count carattere',
            'other' => ':count caratteri',
        ],
        'characters_of_limit' => [
            'one' => ':count / :limit caratteri',
            'other' => ':count / :limit caratteri',
        ],
        'words' => [
            'one' => ':count parola',
            'other' => ':count parole',
        ],
    ],

    'validation' => [
        'min_words' => 'Il campo :attribute deve contenere almeno :min parole.',
        'max_words' => 'Il campo :attribute non deve contenere più di :max parole.',
        'must_contain' => 'Il campo :attribute deve contenere :content.',

        /*
         * What a node or a mark is called in that sentence. A type with no entry here falls
         * back to its own name, which is what makes a project's own node askable for without
         * an edit to this file.
         */
        'content' => [
            'blockquote' => 'una citazione',
            'bulletList' => 'un elenco puntato',
            'callout' => 'un riquadro',
            'codeBlock' => 'un blocco di codice',
            'embed' => 'un video incorporato',
            'heading' => 'un titolo',
            'horizontalRule' => 'una linea orizzontale',
            'image' => 'un\'immagine',
            'link' => 'un link',
            'orderedList' => 'un elenco numerato',
            'table' => 'una tabella',
            'taskList' => 'un elenco di attività',
            'descriptionList' => 'un elenco di definizioni',
        ],
    ],
];
