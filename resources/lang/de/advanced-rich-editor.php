<?php

declare(strict_types=1);

return [
    'tools' => [
        'image' => 'Bild',
        'task_list' => 'Aufgabenliste',
        'heading_level' => 'Überschrift :level',
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
        'image_rotate_left' => 'Nach links drehen',
        'image_rotate_right' => 'Nach rechts drehen',
        'image_alt' => [
            'label' => 'Alt-Text',
            'hint' => 'Leer lassen, um ihn zu entfernen.',
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

    'help' => [
        'label' => 'Hilfe',
        'heading' => 'Hilfe',
        'shortcuts' => 'Tastenkürzel',
        'more' => 'Weiteres',
        'close' => 'Schließen',
        'editing' => [
            'line_break' => 'Zeilenumbruch ohne neuen Absatz',
            'indent_list' => 'Listenpunkt einrücken',
            'outdent_list' => 'Listenpunkt ausrücken',
            'next_cell' => 'Nächste Tabellenzelle',
        ],
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
];
