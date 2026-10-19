<?php

declare(strict_types=1);

return [
    'slash' => [
        'groups' => [
            'style' => 'Stijl',
            'insert' => 'Invoegen',
        ],
        'empty' => 'Geen passende opdracht',
        'aliases' => [
            'paragraph' => 'tekst, alinea, paragraaf',
            'h1' => 'titel, kop 1',
            'h2' => 'kop 2, subkop, ondertitel',
            'h3' => 'kop 3',
            'h4' => 'kop 4',
            'h5' => 'kop 5',
            'h6' => 'kop 6',
            'bulletList' => 'ul, lijst, opsomming, bullets, punten',
            'orderedList' => 'ol, genummerd, nummering, nummers',
            'taskList' => 'todo, taken, takenlijst, checklist, afvinken',
            'descriptionList' => 'dl, woordenlijst, definities, begrippen, glossarium',
            'blockquote' => 'citaat, quote',
            'calloutNote' => 'notitie, info, opmerking, kader, callout',
            'calloutTip' => 'tip, hint, suggestie, kader, callout',
            'calloutWarning' => 'waarschuwing, let op, opgelet, kader, callout',
            'calloutDanger' => 'gevaar, fout, kritiek, kader, callout',
            'codeBlock' => 'code, broncode, snippet',
            'horizontalRule' => 'hr, lijn, scheidingslijn, scheiding',
            'details' => 'accordeon, uitklappen, inklappen, toggle',
            'embed' => 'video, youtube, vimeo, insluiten, embedden, iframe',
            'video' => 'mp4, webm, film, clip, speler, media',
            'audio' => 'mp3, geluid, muziek, podcast, speler, media',
            'file' => 'bestand, document, pdf, bijlage, download, word, excel, zip',
            'image' => 'img, afbeelding, foto, plaatje',
            'table' => 'tabel, raster, rijen, kolommen',
            'attachFiles' => 'uploaden, bijvoegen, afbeelding uploaden',
            'emoji' => 'smiley, emoticon, icoon',
            'characters' => 'symbool, speciaal teken, streepje, pijl, valuta, accent',
            'insertDate' => 'datum, vandaag, dag',
            'insertTime' => 'tijd, tijdstip, klok, nu, uur',
            'insertDateTime' => 'datum en tijd, tijdstempel, nu',
            'customBlocks' => 'blok, component',
            'mergeTags' => 'tag, variabele, placeholder, samenvoegveld',
        ],
    ],
    /*
     * The kinds of callout. The key is the variant's own name, so a project that adds one
     * translates it by adding a key here; one without a translation is titled from its
     * name.
     */
    'callouts' => [
        'note' => 'Notitie',
        'tip' => 'Tip',
        'warning' => 'Waarschuwing',
        'danger' => 'Gevaar',
    ],
    'tools' => [
        'image' => 'Afbeelding',
        // The library button. Named for the library rather than for pictures, because that
        // is what it opens onto and what it will hold more of.
        'media_browser' => 'Media',
        'link' => [
            'target' => [
                'label' => 'Opent in',
                'self' => 'Hetzelfde venster',
                'blank' => 'Nieuw venster',
                'parent' => 'Bovenliggend frame',
                'top' => 'Bovenste frame',
            ],
            'rel' => [
                'label' => 'Relatie (rel)',
                'new_tab_hint' => 'Een link die in een nieuw venster opent, krijgt automatisch noopener en noreferrer.',
                'other' => 'Andere rel-waarden',
            ],
            'referrerpolicy' => 'Referrerbeleid',
            'hreflang' => 'Taal van de gelinkte pagina',
            'id' => 'Anker',
            'internal' => [
                'label' => 'Linken naar een record',
                'hint' => 'Een keuze vult de URL hieronder in, en die URL is wat de link opslaat.',
            ],
        ],
        'code_block' => [
            'plain' => 'Platte tekst',
        ],
        'embed' => [
            'label' => 'Video',
            'heading' => 'Video insluiten',
            'url' => 'Videolink',
            'url_hint' => 'Plak de link uit de adresbalk of van de deelknop. Een tijdstip erin blijft behouden.',
            'title' => 'Titel',
            'title_hint' => 'Wordt door een schermlezer voorgelezen in plaats van ‘video’.',
            'ratio' => 'Beeldverhouding',
            'unsupported' => 'Die link is geen video die deze editor kan insluiten.',
            'providers' => [
                'youtube' => 'YouTube',
                'vimeo' => 'Vimeo',
            ],
            // The bar over a selected embed.
            'replace' => 'Video vervangen',
            'delete' => 'Verwijderen',
        ],
        /*
         * A file on this server rather than one somebody else hosts. The two buttons open
         * this one dialog, so the strings are shared and `kinds` names both of them.
         */
        'media' => [
            'label' => 'Video of audio',
            'heading' => 'Video of audio van je eigen server',
            'src' => 'Adres',
            'src_hint' => 'Een pad op deze server, zoals /storage/clips/lezing.mp4, of een volledige https-link.',
            'kind' => 'Soort',
            'kinds' => [
                'video' => 'Videobestand',
                'audio' => 'Audiobestand',
            ],
            'poster' => 'Voorbeeldafbeelding',
            'poster_hint' => 'Te zien voordat iemand op afspelen drukt. Zonder is de video een zwarte rechthoek.',
            'title' => 'Titel',
            'title_hint' => 'Wordt door een schermlezer voorgelezen in plaats van de bestandsnaam.',
            'preload' => 'Vooraf laden',
            'preload_hint' => 'Hoeveel een browser ophaalt voordat er op afspelen wordt gedrukt.',
            'preloads' => [
                'none' => 'Niets',
                'metadata' => 'Alleen lengte en grootte',
                'auto' => 'Zoveel als de browser wil',
            ],
            'loop' => 'Opnieuw afspelen na afloop',
            'unsupported' => 'Van dit adres speelt een browser geen bestand af.',
            // The bar over a selected film or sound.
            'replace' => 'Bestand vervangen',
            'download' => 'Bestand downloaden',
            'delete' => 'Verwijderen',
        ],
        /*
         * A document in the text, drawn as a card: the button that opens the browser on the
         * documents, and the bar over a card that is selected.
         */
        'file' => [
            'label' => 'Bestand',
            'replace' => 'Bestand vervangen',
            'download' => 'Bestand downloaden',
            'delete' => 'Verwijderen',
        ],
        'format_brush' => [
            'label' => 'Opmaak kopiëren/plakken',
            'once' => 'Actief voor één keer',
            'sticky' => 'Actief tot je het uitzet',
        ],

        'task_list' => 'Takenlijst',

        'description_list' => 'Definitielijst',
        'callouts' => 'Kader',
        'language' => 'Taal',
        'language_none' => 'Taal van de pagina',
        'list_properties' => [
            'bullet' => 'Lijsteigenschappen',
            'ordered' => 'Lijsteigenschappen',
            'marker' => 'Lijstteken',
            'marker_default' => 'Standaard',
            'start' => 'Beginnen bij',
            'reversed' => 'Omgekeerd tellen',
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
                    'disc' => 'Bolletje',
                    'circle' => 'Cirkel',
                    'square' => 'Vierkant',
                ],
            ],
        ],
        'characters' => [
            'label' => 'Speciaal teken',
            'search' => 'Tekens zoeken...',
            'empty' => 'Geen passend teken',
            'empty_recent' => 'Tekens die je kiest, verschijnen hier.',
            'close' => 'Sluiten',
            'groups' => [
                'recent' => 'Recent',
                'punctuation' => 'Leestekens',
                'currency' => 'Valuta',
                'math' => 'Wiskunde',
                'arrows' => 'Pijlen',
                'symbols' => 'Symbolen',
                'latin' => 'Latijnse letters',
                'greek' => 'Griekse letters',
            ],
        ],
        'heading_level' => 'Kop :level',
        'styles' => 'Stijl',
        'styles_clear' => 'Geen',
        'font_family' => 'Lettertype',
        'font_family_clear' => 'Standaard',
        'font_size' => [
            'label' => 'Tekengrootte',
            'custom' => 'Aangepaste grootte',
            'default' => 'Standaard',
        ],
        'alignment' => 'Uitlijning',
        'line_height' => [
            'label' => 'Regelafstand',
            'single' => 'Enkel (1,0)',
            'double' => 'Dubbel (2,0)',
            'value' => ':value',
        ],
        /*
         * The date tools. The key is the configured format's own key, so a project that
         * adds a fourth format translates it by adding a key here; one without a
         * translation is labelled with the format itself, rendered as it will read.
         */
        'date_time' => [
            'label' => 'Datum en tijd',
            // The configured keys live under their own key rather than beside 'label', so a
            // format keyed `label` cannot resolve to the dropdown's own text.
            'formats' => [
                'date' => 'Datum',
                'time' => 'Tijd',
                'date_time' => 'Datum en tijd',
            ],
        ],
        'text_case' => [
            'label' => 'Hoofdlettergebruik wijzigen',
            // Each one is set in the case it applies, so the list says what it does without
            // three icons that would all have to mean "the letter A, but differently".
            'sentence' => 'Zoals in een zin',
            'lower' => 'kleine letters',
            'upper' => 'HOOFDLETTERS',
        ],
        'align' => [
            'start' => 'Links',
            'center' => 'Centreren',
            'end' => 'Rechts',
            'justify' => 'Uitvullen',
        ],
        'image_lock' => [
            'locked' => 'Beeldverhouding vergrendeld',
            'unlocked' => 'Beeldverhouding ontgrendeld',
        ],
        'paragraph' => 'Paragraaf',
        'text_color' => 'Tekstkleur',
        'text_background' => 'Tekstachtergrond',
        'color_clear' => 'Geen kleur',
        'color_custom' => 'Kleur kiezen',
        'fullscreen' => [
            'enter' => 'Volledig scherm',
            'exit' => 'Volledig scherm sluiten',
        ],
        'image_replace' => 'Afbeelding vervangen',
        'image_edit' => [
            'label' => 'Afbeelding bewerken',
            'apply' => 'Toepassen',
            'hint' => 'Gebruik het potlood op de afbeelding om bij te snijden, te draaien of te spiegelen. Het oorspronkelijke bestand blijft bewaard.',
        ],
        'image_download' => 'Afbeelding downloaden',
        'image_delete' => 'Afbeelding verwijderen',
        'image_float_left' => 'Links met tekstomloop',
        'image_float_right' => 'Rechts met tekstomloop',
        'image_float_center' => 'Centreren',
        'image_decorative' => 'Decoratieve afbeelding',

        'image_link' => [
            'label' => 'Afbeelding linken',
            'heading' => 'Waar deze afbeelding naartoe linkt',
            'href' => 'Adres',
            'href_help' => 'Een volledig adres, of een adres binnen deze site zoals /artikelen/7. Laat leeg om de link te verwijderen.',
            'new_tab' => 'Openen in nieuwe tab',
        ],
        'image_rotate_left' => 'Naar links draaien',
        'image_rotate_right' => 'Naar rechts draaien',
        'media_library' => [
            'label' => 'Afbeelding',
            'heading' => 'Invoegen uit de bibliotheek',
            'search' => 'Bestanden zoeken...',
            'empty_record' => 'Nog niets bij dit record. Upload een bestand om te beginnen.',
            'empty_library' => 'De bibliotheek is leeg. Upload een bestand om te beginnen.',
            'empty_search' => 'Niets gevonden.',
            'up' => 'Map omhoog',
            'pending' => 'Nog niet opgeslagen',
            'upload' => 'Uploaden',
            'view_grid' => 'Tegels',
            'view_list' => 'Lijst',
            'filter' => 'Filter',
            'from_url' => 'Via een link',
            'from_url_heading' => 'Toevoegen via een link',
            'from_url_label' => 'Link',
            'from_url_hint' => 'Een YouTube- of Vimeo-link wordt aan je bibliotheek toegevoegd en is opnieuw te gebruiken. Elk ander adres — een bestand op de server van iemand anders — wordt ingevoegd zoals het is, een document als kaart, en niet hier opgeslagen.',
            'from_url_hint_files' => 'Een bestand dat iemand anders host. Het wordt ingevoegd zoals het is, een document als kaart, en er komt niets in je bibliotheek bij.',
            'from_url_no_embeds' => 'Video\'s van YouTube en Vimeo zijn uitgeschakeld voor dit veld.',
            'all_types' => 'Alle typen',
            'all_kinds' => 'Alles',
            'kinds' => [
                'image' => 'Afbeeldingen',
                'video' => 'Video',
                'audio' => 'Audio',
                'file' => 'Bestanden',
                'embed' => 'Ingesloten',
            ],
            'sort' => 'Sorteren',
            'previous' => 'Vorige pagina',
            'next' => 'Volgende pagina',
            'nothing_selected' => 'Kies een bestand om de details te zien.',
            // Put in front of the names of the uploads the browser refused.
            'rejected' => 'Hier niet geaccepteerd:',
            'dismiss' => 'Verbergen',
            'alt' => 'Alt tekst',
            'title' => 'Titel',
            'saved' => 'Opgeslagen',
            'download' => 'Downloaden',
            'play' => 'Hier afspelen',
            'delete' => 'Verwijderen',
            'deleting' => 'Verwijderen…',
            // The confirmation is Filament's own dialog: a heading naming the file, and under it
            // what follows from it. `:name` is filled in by the browser.
            'delete_heading' => '‘:name’ definitief verwijderen?',
            'confirm_delete' => 'Alles wat er nog naar verwijst, werkt daarna niet meer.',
            // A library shared across records: what breaks is not on this screen.
            'confirm_delete_shared' => 'Het kan ook in andere documenten staan, en daar verdwijnt het dan ook.',
            // A new file in the selected one's place, under the same id. `:from` and `:to` are
            // filled in by the browser, which is where both names are known.
            'replace' => 'Vervangen',
            'replacing' => 'Uploaden…',
            'replace_heading' => '‘:from’ vervangen door ‘:to’?',
            'confirm_replace' => 'Elk document dat het gebruikt, toont daarna het nieuwe bestand.',
            'replace_refused' => '‘:name’ kan die plaats niet innemen. Toegestaan: :accept',
            'replace_failed' => 'Het bestand kon niet worden vervangen.',
            // How an entry using a file is named in the question before deleting or replacing it.
            'usage_entry' => ':model #:key ‘:title’',
            'usage_entry_untitled' => ':model #:key',
            'usage_more' => '+:count meer',
            // Where the file is used in entries the server found. They are changed too: a
            // deleted file is taken out of them, a replaced one shown in its place.
            'confirm_delete_used' => 'Het wordt gebruikt in :count items (:entries) en wordt daar ook uit verwijderd.',
            'confirm_delete_used_one' => 'Het wordt gebruikt in één item (:entries) en wordt daar ook uit verwijderd.',
            'confirm_replace_used' => 'Het wordt gebruikt in :count items (:entries), die daarna het nieuwe bestand tonen.',
            'confirm_replace_used_one' => 'Het wordt gebruikt in één item (:entries), dat daarna het nieuwe bestand toont.',
            'deleted_from' => 'Verwijderd en uit :count items gehaald.',
            'deleted_from_one' => 'Verwijderd en uit één item gehaald.',
            'replaced_in' => 'Vervangen en bijgewerkt in :count items.',
            'replaced_in_one' => 'Vervangen en bijgewerkt in één item.',
            'copy_url' => 'Link kopiëren',
            'copied' => 'Gekopieerd',
            'drop' => 'Loslaten om te uploaden',
            'items' => 'items',
            'sorts' => [
                'newest' => 'Nieuwste eerst',
                'oldest' => 'Oudste eerst',
                'name' => 'Naam',
                'largest' => 'Grootste eerst',
                'smallest' => 'Kleinste eerst',
            ],
            'details' => [
                'name' => 'Naam',
                'size' => 'Grootte',
                'dimensions' => 'Afmetingen',
                'type' => 'Type',
                'modified' => 'Gewijzigd',
            ],
        ],
        'image_alt' => [
            'label' => 'Tekst',
            'alt' => 'Alt tekst',
            'hint' => 'Vervangt de afbeelding waar die niet te zien is. Laat leeg om hem te verwijderen.',
            'caption' => 'Bijschrift',
            'caption_hint' => 'Staat onder de afbeelding. Alleen voor een afbeelding op een eigen regel.',
        ],
        'image_size' => [
            'label' => 'Grootte',
            'width' => 'Breedte',
            'height' => 'Hoogte',
            'apply' => 'Toepassen',
            'reset' => 'Resetten',
        ],
        'headings' => 'Koppen',
        'lists' => 'Lijsten',
        'more' => 'Meer',
        'tools_menu' => 'Hulpmiddelen',
        'find' => [
            'label' => 'Zoeken en vervangen',
            'find' => 'Zoeken',
            'replace' => 'Vervangen door',
            'previous' => 'Vorige overeenkomst',
            'next' => 'Volgende overeenkomst',
            'replace_one' => 'Vervangen',
            'replace_all' => 'Alles vervangen',
            'close' => 'Sluiten',
            'match_case' => 'Hoofdlettergevoelig',
            'whole_word' => 'Alleen hele woorden',
            'no_results' => 'Geen overeenkomsten',
            // Both numbers sit in one sentence, because where they sit in it is a question
            // about the language and not one the browser should be answering.
            'count' => ':current van :total',
        ],
        'source_code' => [
            'label' => 'Broncode',
            'heading' => 'Broncode',
            'description' => 'De HTML zoals die wordt opgeslagen. Wat de editor niet kan bevatten, valt weg bij het toepassen.',
            'apply' => 'Toepassen',
        ],
        'invisible_characters' => [
            'label' => 'Onzichtbare tekens tonen',
        ],
        'block_outlines' => [
            'label' => 'Blokken tonen',
        ],
        'emoji' => [
            'label' => 'Emoji',
            'search' => 'Emoji zoeken',
            'empty' => 'Niets gevonden.',
            'empty_recent' => 'De emoji die je kiest, verzamelen zich hier.',
            'close' => 'Sluiten',
            'groups' => [
                'recent' => 'Veelgebruikt',
                'smileys' => 'Smileys en mensen',
                'nature' => 'Dieren en natuur',
                'food' => 'Eten en drinken',
                'activities' => 'Activiteiten',
                'travel' => 'Reizen en plaatsen',
                'objects' => 'Objecten',
                'symbols' => 'Symbolen',
                'flags' => 'Vlaggen',
            ],
        ],
        'direction' => [
            'ltr' => 'Links naar rechts',
            'rtl' => 'Rechts naar links',
        ],
        /*
         * The indent pair. Named for the move rather than for a state, because that is
         * what the buttons are: a block is not "indented" the way it is bold, it is one
         * step further in than it was.
         */
        'indent' => [
            'indent' => 'Inspringing vergroten',
            'outdent' => 'Inspringing verkleinen',
        ],
    ],

    'preview' => [
        'label' => 'Voorbeeld',
        'heading' => 'Voorbeeld',
        'description' => 'Het document zoals je eigen stylesheet het weergeeft. De opmaak van de editor wordt hier niet geladen.',
        'frame' => 'Voorbeeld van het document',
    ],

    'statistics' => [
        'label' => 'Statistieken',
        'heading' => 'Statistieken',
        'words' => 'Woorden',
        'characters' => 'Tekens',
        'characters_without_spaces' => 'Tekens zonder spaties',
        'paragraphs' => 'Blokken',
        'reading_time' => 'Leestijd',
        'reading_time_none' => '—',
        'reading_time_under' => 'minder dan een minuut',
        'reading_time_minutes' => ':minutes min',
    ],

    'help' => [
        'label' => 'Help',
        'heading' => 'Help',
        'shortcuts' => 'Sneltoetsen',
        'more' => 'Meer',
        'editing' => [
            'line_break' => 'Regeleinde zonder nieuwe alinea',
            'paste_plain' => 'Plakken als platte tekst',
            'indent_list' => 'Lijstitem laten inspringen',
            'outdent_list' => 'Inspringing van lijstitem verkleinen',
            'indent_block' => 'Inspringing vergroten',
            'outdent_block' => 'Inspringing verkleinen',
            'change_case' => 'Hoofdlettergebruik van de selectie wisselen',
            'next_cell' => 'Volgende tabelcel',
            'find' => 'Zoeken',
            'find_replace' => 'Zoeken en vervangen',
        ],
    ],

    'accessibility' => [
        'title' => 'Toegankelijkheidscontrole',
        'close' => 'Sluiten',
        'empty' => 'Niets te melden.',
        'ratio' => ':ratio van de vereiste :needed',
        'rules' => [
            'missing_alt' => 'Afbeelding zonder alt tekst',
            'decorative_link' => 'Gelinkte afbeelding zonder iets om voor te lezen',
            'empty_link' => 'Link zonder tekst',
            'weak_link_text' => 'Linktekst zegt niets',
            'skipped_heading' => 'Kopniveau overgeslagen',
            'table_without_header' => 'Tabel zonder koprij',
            'weak_contrast' => 'Tekstkleur te zwak om te lezen',
        ],
        // A link's whole text has to be one of these, so what belongs here is what people
        // write as the entire text of a link and nothing longer.
        'weak_link_phrases' => [
            'hier', 'klik hier', 'klik', 'deze link', 'link', 'lees meer', 'lees verder',
            'verder lezen', 'meer lezen', 'meer', 'meer info', 'meer informatie', 'bekijk',
            'details', 'download', 'downloaden',
        ],
    ],

    'autosave' => [
        'found' => 'In deze browser is een niet-opgeslagen concept van :time gevonden.',
        'restore' => 'Herstellen',
        'discard' => 'Verwerpen',
    ],

    'drag_handle' => [
        'drag' => 'Sleep om dit blok te verplaatsen, klik om het te selecteren',
        'insert' => 'Blok eronder toevoegen',
    ],

    'styles' => [
        'block' => 'Blok',
        'inline' => 'Tekst',
    ],

    'fonts' => [
        'search' => 'Lettertypen zoeken',
        'system' => 'Systeem',
        'serif' => 'Serif',
        'monospace' => 'Monospace',
    ],

    'character_count' => [
        'characters' => [
            'one' => ':count teken',
            'other' => ':count tekens',
        ],
        'characters_of_limit' => [
            'one' => ':count / :limit tekens',
            'other' => ':count / :limit tekens',
        ],
        'words' => [
            'one' => ':count woord',
            'other' => ':count woorden',
        ],
    ],

    'validation' => [
        'min_words' => ':attribute moet minstens :min woorden bevatten.',
        'max_words' => ':attribute mag niet meer dan :max woorden bevatten.',
        'must_contain' => ':attribute moet :content bevatten.',

        /*
         * What a node or a mark is called in that sentence. A type with no entry here falls
         * back to its own name, which is what makes a project's own node askable for without
         * an edit to this file.
         */
        'content' => [
            'blockquote' => 'een citaat',
            'bulletList' => 'een lijst met opsommingstekens',
            'callout' => 'een kader',
            'codeBlock' => 'een codeblok',
            'embed' => 'een ingesloten video',
            'heading' => 'een kop',
            'horizontalRule' => 'een horizontale lijn',
            'image' => 'een afbeelding',
            'link' => 'een link',
            'orderedList' => 'een genummerde lijst',
            'table' => 'een tabel',
            'taskList' => 'een takenlijst',
            'descriptionList' => 'een definitielijst',
        ],
    ],
];
