<?php

declare(strict_types=1);

return [
    'slash' => [
        'groups' => [
            'style' => 'Format',
            'insert' => 'Einfügen',
        ],
        'empty' => 'Kein passender Befehl',
        'aliases' => [
            'paragraph' => 'text, absatz, fließtext',
            'h1' => 'titel, überschrift 1',
            'h2' => 'überschrift 2, untertitel',
            'h3' => 'überschrift 3',
            'h4' => 'überschrift 4',
            'h5' => 'überschrift 5',
            'h6' => 'überschrift 6',
            'bulletList' => 'ul, liste, aufzählung, punkte',
            'orderedList' => 'ol, nummeriert, nummerierung',
            'taskList' => 'todo, aufgaben, checkliste, haken',
            'blockquote' => 'zitat',
            'calloutNote' => 'hinweis, info, infobox, kasten, callout',
            'calloutTip' => 'tipp, ratschlag, kasten, callout',
            'calloutWarning' => 'warnung, achtung, vorsicht, kasten, callout',
            'calloutDanger' => 'gefahr, fehler, kritisch, kasten, callout',
            'codeBlock' => 'code, quelltext',
            'horizontalRule' => 'hr, trenner, linie',
            'details' => 'akkordeon, aufklappen, ausklappen',
            'embed' => 'video, youtube, vimeo, einbetten, iframe',
            'image' => 'bild, foto, img',
            'table' => 'tabelle, raster, spalten',
            'attachFiles' => 'datei, anhang, hochladen',
            'emoji' => 'smiley, symbol',
            'characters' => 'sonderzeichen, zeichen, strich, pfeil, währung, akzent',
            'customBlocks' => 'block, baustein',
            'mergeTags' => 'platzhalter, variable',
        ],
    ],
    /*
     * Die Arten von Infobox. Der Schlüssel ist der Name der Variante: wer eine eigene
     * hinzufügt, übersetzt sie mit einem Eintrag hier; ohne Übersetzung wird der Name
     * selbst als Beschriftung verwendet.
     */
    'callouts' => [
        'note' => 'Hinweis',
        'tip' => 'Tipp',
        'warning' => 'Warnung',
        'danger' => 'Gefahr',
    ],
    'tools' => [
        'image' => 'Bild',
        'link' => [
            'target' => [
                'label' => 'Öffnet in',
                'self' => 'Gleichem Fenster',
                'blank' => 'Neuem Fenster',
                'parent' => 'Übergeordnetem Frame',
                'top' => 'Oberstem Frame',
            ],
            'rel' => [
                'label' => 'Beziehung (rel)',
                'new_tab_hint' => 'Ein Link, der in einem neuen Fenster öffnet, bekommt noopener und noreferrer automatisch.',
                'other' => 'Weitere rel-Werte',
            ],
            'referrerpolicy' => 'Referrer-Richtlinie',
            'hreflang' => 'Sprache der Zielseite',
            'id' => 'Anker',
            'internal' => [
                'label' => 'Auf einen Datensatz verlinken',
                'hint' => 'Die Auswahl füllt die URL darunter - das ist, was der Link speichert.',
            ],
        ],
        'code_block' => [
            'plain' => 'Reiner Text',
        ],
        'embed' => [
            'label' => 'Video',
            'heading' => 'Video einbetten',
            'url' => 'Video-Link',
            'url_hint' => 'Link aus der Adresszeile oder dem Teilen-Knopf einfügen. Eine Startzeit darin bleibt erhalten.',
            'title' => 'Titel',
            'title_hint' => 'Wird von einem Screenreader statt „Video" vorgelesen.',
            'ratio' => 'Seitenverhältnis',
            'unsupported' => 'Dieser Link ist kein Video, das dieser Editor einbetten kann.',
            'providers' => [
                'youtube' => 'YouTube',
                'vimeo' => 'Vimeo',
            ],
        ],
        'format_brush' => [
            'label' => 'Format übertragen',
            'once' => 'Für einen Strich scharf',
            'sticky' => 'Scharf, bis du es abschaltest',
        ],

        'task_list' => 'Aufgabenliste',
        'callouts' => 'Infobox',
        'language' => 'Sprache',
        'language_none' => 'Sprache der Seite',
        'list_properties' => [
            'bullet' => 'Listeneigenschaften',
            'ordered' => 'Listeneigenschaften',
            'marker' => 'Aufzählungszeichen',
            'marker_default' => 'Standard',
            'start' => 'Beginnt bei',
            'reversed' => 'Rückwärts zählen',
            'markers' => [
                // Mit einem Beispiel benannt statt mit einem Namen: „a, b, c" sagt, was
                // die Wahl bewirkt, „Kleinbuchstaben" sagt, wie jemand sie genannt hat.
                'ordered' => [
                    '1' => '1, 2, 3',
                    'a' => 'a, b, c',
                    'A' => 'A, B, C',
                    'i' => 'i, ii, iii',
                    'I' => 'I, II, III',
                ],
                'bullet' => [
                    'disc' => 'Punkt',
                    'circle' => 'Kreis',
                    'square' => 'Quadrat',
                ],
            ],
        ],
        'characters' => [
            'label' => 'Sonderzeichen',
            'search' => 'Zeichen suchen...',
            'empty' => 'Kein passendes Zeichen',
            'empty_recent' => 'Zeichen, die du auswählst, erscheinen hier.',
            'close' => 'Schließen',
            'groups' => [
                'recent' => 'Zuletzt',
                'punctuation' => 'Interpunktion',
                'currency' => 'Währung',
                'math' => 'Mathematik',
                'arrows' => 'Pfeile',
                'symbols' => 'Symbole',
                'latin' => 'Lateinische Buchstaben',
                'greek' => 'Griechische Buchstaben',
            ],
        ],
        'heading_level' => 'Überschrift :level',
        'styles' => 'Stil',
        'styles_clear' => 'Keiner',
        'font_family' => 'Schriftart',
        'font_family_clear' => 'Standard',
        'font_size' => [
            'label' => 'Schriftgröße',
            'custom' => 'Eigene Größe',
            'default' => 'Standard',
        ],
        'alignment' => 'Ausrichtung',
        'line_height' => [
            'label' => 'Zeilenabstand',
            'single' => 'Einfach (1,0)',
            'double' => 'Doppelt (2,0)',
            'value' => ':value',
        ],
        'text_case' => [
            'label' => 'Schreibweise ändern',
            // Jede in der Schreibweise, die sie herstellt - die Liste zeigt damit, was sie
            // tut, statt es zu behaupten.
            'sentence' => 'Erster Buchstabe groß',
            'lower' => 'kleinbuchstaben',
            'upper' => 'GROSSBUCHSTABEN',
        ],
        'align' => [
            'start' => 'Links',
            'center' => 'Zentriert',
            'end' => 'Rechts',
            'justify' => 'Blocksatz',
        ],
        'image_lock' => [
            'locked' => 'Seitenverhältnis gesperrt',
            'unlocked' => 'Seitenverhältnis frei',
        ],
        'paragraph' => 'Absatz',
        'text_color' => 'Schriftfarbe',
        'text_background' => 'Texthintergrund',
        'color_clear' => 'Keine Farbe',
        'color_custom' => 'Farbe wählen',
        'fullscreen' => [
            'enter' => 'Vollbild',
            'exit' => 'Vollbild verlassen',
        ],
        'image_download' => 'Bild herunterladen',
        'image_delete' => 'Bild löschen',
        'image_float_left' => 'Links umfließen',
        'image_float_right' => 'Rechts umfließen',
        'image_float_center' => 'Zentrieren',
        'image_decorative' => 'Dekoratives Bild',

        'image_link' => [
            'label' => 'Bild verlinken',
            'heading' => 'Wohin dieses Bild zeigt',
            'href' => 'Adresse',
            'href_help' => 'Eine vollständige Adresse oder eine innerhalb dieser Seite, etwa /artikel/7. Leer lassen entfernt den Link.',
            'new_tab' => 'In neuem Tab öffnen',
        ],
        'image_rotate_left' => 'Nach links drehen',
        'image_rotate_right' => 'Nach rechts drehen',
        'media_library' => [
            'label' => 'Bild',
            'heading' => 'Bild einfügen',
            'search' => 'Dateien durchsuchen …',
            'empty_record' => 'Noch keine Bilder an diesem Datensatz. Unten eins hochladen.',
            'empty_library' => 'Die Mediathek ist leer. Unten ein Bild hochladen.',
            'empty_search' => 'Dazu passt kein Bild.',
            'up' => 'Eine Ebene höher',
            'pending' => 'Noch nicht gespeichert',
            'upload' => 'Hochladen',
            'view_grid' => 'Kacheln',
            'view_list' => 'Liste',
            'filter' => 'Filter',
            'all_types' => 'Alle Typen',
            'sort' => 'Sortierung',
            'previous' => 'Vorherige Seite',
            'next' => 'Nächste Seite',
            'nothing_selected' => 'Ein Bild auswählen, um die Details zu sehen.',
            'copy_url' => 'Link kopieren',
            'copied' => 'Kopiert',
            'drop' => 'Zum Hochladen loslassen',
            'items' => 'Dateien',
            'sorts' => [
                'newest' => 'Neueste zuerst',
                'oldest' => 'Älteste zuerst',
                'name' => 'Name',
                'largest' => 'Größte zuerst',
                'smallest' => 'Kleinste zuerst',
            ],
            'details' => [
                'name' => 'Name',
                'size' => 'Größe',
                'dimensions' => 'Abmessungen',
                'type' => 'Typ',
                'modified' => 'Geändert',
            ],
        ],
        'image_alt' => [
            'label' => 'Text',
            'alt' => 'Alt-Text',
            'hint' => 'Steht für das Bild, wo es nicht zu sehen ist. Leer lassen, um ihn zu entfernen.',
            'caption' => 'Bildunterschrift',
            'caption_hint' => 'Steht unter dem Bild. Nur für ein Bild, das allein in einer Zeile steht.',
        ],
        'image_size' => [
            'label' => 'Größe',
            'width' => 'Breite',
            'height' => 'Höhe',
            'apply' => 'Übernehmen',
            'reset' => 'Zurücksetzen',
        ],
        'headings' => 'Überschriften',
        'lists' => 'Listen',
        'more' => 'Mehr',
        'tools_menu' => 'Werkzeuge',
        'find' => [
            'label' => 'Suchen und Ersetzen',
            'find' => 'Suchen',
            'replace' => 'Ersetzen durch',
            'previous' => 'Vorheriger Treffer',
            'next' => 'Nächster Treffer',
            'replace_one' => 'Ersetzen',
            'replace_all' => 'Alle ersetzen',
            'close' => 'Schließen',
            'match_case' => 'Groß- und Kleinschreibung',
            'whole_word' => 'Nur ganze Wörter',
            'no_results' => 'Keine Treffer',
            'count' => ':current von :total',
        ],
        'source_code' => [
            'label' => 'Quellcode',
            'heading' => 'Quellcode',
            'description' => 'Das HTML in der Form, in der es gespeichert wird. Was der Editor nicht abbilden kann, fällt beim Übernehmen weg.',
            'apply' => 'Übernehmen',
        ],
        'emoji' => [
            'label' => 'Emoji',
            'search' => 'Emoji suchen',
            'empty' => 'Dazu passt nichts.',
            'empty_recent' => 'Was du auswählst, sammelt sich hier.',
            'close' => 'Schließen',
            'groups' => [
                'recent' => 'Häufig benutzt',
                'smileys' => 'Smileys & Personen',
                'nature' => 'Tiere & Natur',
                'food' => 'Essen & Trinken',
                'activities' => 'Aktivität',
                'travel' => 'Reisen & Orte',
                'objects' => 'Objekte',
                'symbols' => 'Symbole',
                'flags' => 'Flaggen',
            ],
        ],
        'direction' => [
            'ltr' => 'Links nach rechts',
            'rtl' => 'Rechts nach links',
        ],
    ],

    'preview' => [
        'label' => 'Vorschau',
        'heading' => 'Vorschau',
        'description' => 'Das Dokument, wie das eigene Stylesheet es zeichnet. Die Gestaltung des Editors ist hier nicht geladen.',
        'frame' => 'Vorschau des Dokuments',
    ],

    'statistics' => [
        'label' => 'Statistik',
        'heading' => 'Statistik',
        'words' => 'Wörter',
        'characters' => 'Zeichen',
        'characters_without_spaces' => 'Zeichen ohne Leerzeichen',
        'paragraphs' => 'Blöcke',
        'reading_time' => 'Lesezeit',
        'reading_time_none' => '—',
        'reading_time_under' => 'unter einer Minute',
        'reading_time_minutes' => ':minutes Min.',
    ],

    'help' => [
        'label' => 'Hilfe',
        'heading' => 'Hilfe',
        'shortcuts' => 'Tastenkürzel',
        'more' => 'Weiteres',
        'editing' => [
            'line_break' => 'Zeilenumbruch ohne neuen Absatz',
            'paste_plain' => 'Als reinen Text einfügen',
            'indent_list' => 'Listenpunkt einrücken',
            'outdent_list' => 'Listenpunkt ausrücken',
            'change_case' => 'Schreibweise der Auswahl durchwechseln',
            'find' => 'Suchen',
            'find_replace' => 'Suchen und Ersetzen',
            'next_cell' => 'Nächste Tabellenzelle',
        ],
    ],

    'accessibility' => [
        'title' => 'Barrierefreiheit prüfen',
        'close' => 'Schließen',
        'empty' => 'Nichts zu beanstanden.',
        'ratio' => ':ratio von :needed nötig',
        'rules' => [
            'missing_alt' => 'Bild ohne Alternativtext',
            'decorative_link' => 'Verlinktes Bild ohne Beschriftung',
            'empty_link' => 'Link ohne Text',
            'weak_link_text' => 'Linktext sagt nichts',
            'skipped_heading' => 'Überschriftenebene übersprungen',
            'table_without_header' => 'Tabelle ohne Kopfzeile',
            'weak_contrast' => 'Textfarbe zu schwach zum Lesen',
        ],
        // Der ganze Linktext muss einer davon sein, hier gehört also hinein, was Leute als
        // vollständigen Text eines Links schreiben, und nichts Längeres.
        'weak_link_phrases' => [
            'hier', 'hier klicken', 'klicken', 'klick hier', 'dies', 'dieser link', 'link',
            'mehr', 'mehr erfahren', 'weiterlesen', 'weitere informationen', 'details',
            'los', 'download', 'herunterladen',
        ],
    ],

    'autosave' => [
        'found' => 'In diesem Browser liegt ein ungespeicherter Entwurf von :time.',
        'restore' => 'Wiederherstellen',
        'discard' => 'Verwerfen',
    ],

    'drag_handle' => [
        'drag' => 'Ziehen verschiebt diesen Block, Klicken wählt ihn aus',
        'insert' => 'Block darunter einfügen',
    ],

    'styles' => [
        'block' => 'Absatz',
        'inline' => 'Text',
    ],

    'fonts' => [
        'search' => 'Schrift suchen',
        'system' => 'System',
        'serif' => 'Serif',
        'monospace' => 'Monospace',
    ],

    'character_count' => [
        'characters' => [
            'one' => ':count Zeichen',
            'other' => ':count Zeichen',
        ],
        'characters_of_limit' => [
            'one' => ':count / :limit Zeichen',
            'other' => ':count / :limit Zeichen',
        ],
        'words' => [
            'one' => ':count Wort',
            'other' => ':count Wörter',
        ],
    ],

    'validation' => [
        'min_words' => ':attribute muss mindestens :min Wörter enthalten.',
        'max_words' => ':attribute darf höchstens :max Wörter enthalten.',
        'must_contain' => ':attribute muss :content enthalten.',

        /*
         * Wie ein Knoten oder eine Marke in diesem Satz heißt. Ein Typ ohne Eintrag fällt auf
         * seinen eigenen Namen zurück - das ist es, was einen projekteigenen Knoten
         * abfragbar macht, ohne dass hier etwas geändert werden muss.
         */
        'content' => [
            'blockquote' => 'ein Zitat',
            'bulletList' => 'eine Aufzählung',
            'callout' => 'einen Hinweiskasten',
            'codeBlock' => 'einen Codeblock',
            'embed' => 'ein eingebettetes Video',
            'heading' => 'eine Überschrift',
            'horizontalRule' => 'eine Trennlinie',
            'image' => 'ein Bild',
            'link' => 'einen Link',
            'orderedList' => 'eine nummerierte Liste',
            'table' => 'eine Tabelle',
            'taskList' => 'eine Aufgabenliste',
        ],
    ],
];
