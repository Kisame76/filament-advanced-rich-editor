<?php

declare(strict_types=1);

return [
    'slash' => [
        'groups' => [
            'style' => 'Format',
            'insert' => 'Infoga',
        ],
        'empty' => 'Inget matchande kommando',
        'aliases' => [
            'paragraph' => 'text, stycke, brödtext',
            'h1' => 'titel, rubrik 1',
            'h2' => 'rubrik 2, underrubrik',
            'h3' => 'rubrik 3',
            'h4' => 'rubrik 4',
            'h5' => 'rubrik 5',
            'h6' => 'rubrik 6',
            'bulletList' => 'ul, punktlista, punkter, lista',
            'orderedList' => 'ol, numrerad, numrering, nummerlista',
            'taskList' => 'todo, uppgifter, checklista, kryssruta, att göra',
            'descriptionList' => 'dl, ordlista, definitioner, begrepp, lexikon',
            'blockquote' => 'citat',
            'calloutNote' => 'notera, anteckning, info, infobox, ruta, callout',
            'calloutTip' => 'tips, råd, förslag, ruta, callout',
            'calloutWarning' => 'varning, akta, observera, obs, ruta, callout',
            'calloutDanger' => 'fara, fel, kritisk, ruta, callout',
            'codeBlock' => 'kod, pre, källkod, kodsnutt',
            'horizontalRule' => 'hr, avdelare, linje, skiljelinje',
            'details' => 'dragspel, accordion, expandera, komprimera, fäll ut',
            'embed' => 'video, youtube, vimeo, iframe, bädda in, media',
            'video' => 'mp4, webm, film, klipp, spelare, media',
            'audio' => 'mp3, ljud, musik, podcast, podd, spelare, media',
            'file' => 'fil, dokument, pdf, bilaga, nedladdning, ladda ner, word, excel, zip',
            'image' => 'img, bild, foto',
            'table' => 'tabell, rutnät, rader, kolumner',
            'attachFiles' => 'ladda upp, uppladdning, bilduppladdning',
            'emoji' => 'smiley, uttryckssymbol, ikon',
            'characters' => 'symbol, specialtecken, tecken, tankstreck, pil, valuta, accent',
            'insertDate' => 'datum, idag, dag, dagens datum',
            'insertTime' => 'tid, klocka, klockslag, nu, timme',
            'insertDateTime' => 'datum och tid, tidsstämpel, nu',
            'customBlocks' => 'block, komponent',
            'mergeTags' => 'mallvariabel, variabel, platshållare, tagg',
        ],
    ],
    /*
     * The kinds of callout. The key is the variant's own name, so a project that adds one
     * translates it by adding a key here; one without a translation is titled from its
     * name.
     */
    'callouts' => [
        'note' => 'Notera',
        'tip' => 'Tips',
        'warning' => 'Varning',
        'danger' => 'Fara',
    ],
    'tools' => [
        'image' => 'Bild',
        // The library button. Named for the library rather than for pictures, because that
        // is what it opens onto and what it will hold more of.
        'media_browser' => 'Media',
        'link' => [
            'target' => [
                'label' => 'Öppnas i',
                'self' => 'Samma fönster',
                'blank' => 'Nytt fönster',
                'parent' => 'Överordnad ram',
                'top' => 'Översta ramen',
            ],
            'rel' => [
                'label' => 'Relation (rel)',
                'new_tab_hint' => 'En länk som öppnas i ett nytt fönster får noopener och noreferrer automatiskt.',
                'other' => 'Andra rel-värden',
            ],
            'referrerpolicy' => 'Referrer-policy',
            'hreflang' => 'Språk på den länkade sidan',
            'id' => 'Ankare',
            'internal' => [
                'label' => 'Länk till en post',
                'hint' => 'Ett val fyller i URL-adressen nedan, och det är den som länken sparar.',
            ],
        ],
        'code_block' => [
            'plain' => 'Ren text',
        ],
        'embed' => [
            'label' => 'Video',
            'heading' => 'Bädda in en video',
            'url' => 'Videolänk',
            'url_hint' => 'Klistra in länken från adressfältet eller dela-knappen. En tidsstämpel i den behålls.',
            'title' => 'Titel',
            'title_hint' => 'Läses upp av en skärmläsare i stället för ”video”.',
            'ratio' => 'Bildformat',
            'unsupported' => 'Den länken är ingen video som den här redigeraren kan bädda in.',
            'providers' => [
                'youtube' => 'YouTube',
                'vimeo' => 'Vimeo',
            ],
            // The bar over a selected embed.
            'replace' => 'Ersätt video',
            'delete' => 'Ta bort',
        ],
        /*
         * A file on this server rather than one somebody else hosts. The two buttons open
         * this one dialog, so the strings are shared and `kinds` names both of them.
         */
        'media' => [
            'label' => 'Video eller ljud',
            'heading' => 'Video eller ljud från din server',
            'src' => 'Adress',
            'src_hint' => 'En sökväg på den här servern, t.ex. /storage/clips/foredrag.mp4, eller en fullständig https-länk.',
            'kind' => 'Typ',
            'kinds' => [
                'video' => 'Videofil',
                'audio' => 'Ljudfil',
            ],
            'poster' => 'Miniatyrbild',
            'poster_hint' => 'Visas innan någon trycker på spela upp. Utan en är videon en svart rektangel.',
            'title' => 'Titel',
            'title_hint' => 'Läses upp av en skärmläsare i stället för filnamnet.',
            'preload' => 'Förladda',
            'preload_hint' => 'Hur mycket en webbläsare hämtar innan någon trycker på spela upp.',
            'preloads' => [
                'none' => 'Ingenting',
                'metadata' => 'Endast längd och storlek',
                'auto' => 'Så mycket webbläsaren vill',
            ],
            'loop' => 'Spela upp igen när den är slut',
            'unsupported' => 'En webbläsare spelar inte upp filer från den adressen.',
            // The bar over a selected film or sound.
            'replace' => 'Ersätt fil',
            'download' => 'Ladda ner fil',
            'delete' => 'Ta bort',
        ],
        /*
         * A document in the text, drawn as a card: the button that opens the browser on the
         * documents, and the bar over a card that is selected.
         */
        'file' => [
            'label' => 'Fil',
            'replace' => 'Ersätt fil',
            'download' => 'Ladda ner fil',
            'delete' => 'Ta bort',
        ],
        'format_brush' => [
            'label' => 'Hämta format',
            'once' => 'Redo för ett penseldrag',
            'sticky' => 'Redo tills du stänger av den',
        ],

        'task_list' => 'Uppgiftslista',

        'description_list' => 'Definitionslista',
        'callouts' => 'Inforuta',
        'language' => 'Språk',
        'language_none' => 'Sidans språk',
        'list_properties' => [
            'bullet' => 'Listegenskaper',
            'ordered' => 'Listegenskaper',
            'marker' => 'Markör',
            'marker_default' => 'Standard',
            'start' => 'Börja på',
            'reversed' => 'Räkna baklänges',
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
                    'disc' => 'Punkt',
                    'circle' => 'Cirkel',
                    'square' => 'Fyrkant',
                ],
            ],
        ],
        'characters' => [
            'label' => 'Specialtecken',
            'search' => 'Sök tecken...',
            'empty' => 'Inget matchande tecken',
            'empty_recent' => 'Tecken du väljer visas här.',
            'close' => 'Stäng',
            'groups' => [
                'recent' => 'Senaste',
                'punctuation' => 'Skiljetecken',
                'currency' => 'Valuta',
                'math' => 'Matematik',
                'arrows' => 'Pilar',
                'symbols' => 'Symboler',
                'latin' => 'Latinska bokstäver',
                'greek' => 'Grekiska bokstäver',
            ],
        ],
        'heading_level' => 'Rubrik :level',
        'styles' => 'Format',
        'styles_clear' => 'Inget',
        'font_family' => 'Teckensnitt',
        'font_family_clear' => 'Standard',
        'font_size' => [
            'label' => 'Teckenstorlek',
            'custom' => 'Anpassad storlek',
            'default' => 'Standard',
        ],
        'alignment' => 'Justering',
        'line_height' => [
            'label' => 'Radavstånd',
            'single' => 'Enkelt (1,0)',
            'double' => 'Dubbelt (2,0)',
            'value' => ':value',
        ],
        /*
         * The date tools. The key is the configured format's own key, so a project that
         * adds a fourth format translates it by adding a key here; one without a
         * translation is labelled with the format itself, rendered as it will read.
         */
        'date_time' => [
            'label' => 'Datum och tid',
            // The configured keys live under their own key rather than beside 'label', so a
            // format keyed `label` cannot resolve to the dropdown's own text.
            'formats' => [
                'date' => 'Datum',
                'time' => 'Tid',
                'date_time' => 'Datum och tid',
            ],
        ],
        'text_case' => [
            'label' => 'Ändra skiftläge',
            // Each one is set in the case it applies, so the list says what it does without
            // three icons that would all have to mean "the letter A, but differently".
            'sentence' => 'Som i en mening',
            'lower' => 'gemener',
            'upper' => 'VERSALER',
        ],
        'align' => [
            'start' => 'Vänster',
            'center' => 'Centrerat',
            'end' => 'Höger',
            'justify' => 'Marginaljusterat',
        ],
        'image_lock' => [
            'locked' => 'Bildformat låst',
            'unlocked' => 'Bildformat olåst',
        ],
        'paragraph' => 'Stycke',
        'text_color' => 'Textfärg',
        'text_background' => 'Textbakgrund',
        'color_clear' => 'Ingen färg',
        'color_custom' => 'Välj en färg',
        'fullscreen' => [
            'enter' => 'Helskärm',
            'exit' => 'Avsluta helskärm',
        ],
        'image_replace' => 'Ersätt bild',
        'image_edit' => [
            'label' => 'Redigera bild',
            'apply' => 'Använd',
            'hint' => 'Använd pennan på bilden för att beskära, rotera eller spegelvända den. Originalfilen behålls.',
        ],
        'image_download' => 'Ladda ner bild',
        'image_delete' => 'Radera bild',
        'image_float_left' => 'Flytande vänster',
        'image_float_right' => 'Flytande höger',
        'image_float_center' => 'Centrera',
        'image_decorative' => 'Dekorativ bild',

        'image_link' => [
            'label' => 'Länka bild',
            'heading' => 'Vart den här bilden pekar',
            'href' => 'Adress',
            'href_help' => 'En fullständig adress, eller en på den här webbplatsen, t.ex. /artiklar/7. Lämna tomt för att ta bort länken.',
            'new_tab' => 'Öppna i ny flik',
        ],
        'image_rotate_left' => 'Rotera åt vänster',
        'image_rotate_right' => 'Rotera åt höger',
        'media_library' => [
            'label' => 'Bild',
            'heading' => 'Infoga från biblioteket',
            'search' => 'Sök filer...',
            'empty_record' => 'Inget på den här posten än. Ladda upp en fil för att börja.',
            'empty_library' => 'Biblioteket är tomt. Ladda upp en fil för att komma igång.',
            'empty_search' => 'Inget matchar det.',
            'up' => 'En mapp upp',
            'pending' => 'Inte sparad än',
            'upload' => 'Ladda upp',
            'view_grid' => 'Rutor',
            'view_list' => 'Lista',
            'filter' => 'Filter',
            'from_url' => 'Från en länk',
            'from_url_heading' => 'Lägg till från en länk',
            'from_url_label' => 'Länk',
            'from_url_hint' => 'En YouTube- eller Vimeo-länk läggs till i ditt bibliotek och kan användas igen. Alla andra adresser — en fil på någon annans server — infogas som de är, ett dokument som ett kort, och sparas inte här.',
            'from_url_hint_files' => 'En fil som någon annan står värd för. Den infogas som den är, ett dokument som ett kort, och inget läggs till i ditt bibliotek.',
            'from_url_no_embeds' => 'Videor från YouTube och Vimeo är avstängda för det här fältet.',
            'all_types' => 'Alla typer',
            'all_kinds' => 'Alla',
            'kinds' => [
                'image' => 'Bilder',
                'video' => 'Video',
                'audio' => 'Ljud',
                'file' => 'Filer',
                'embed' => 'Inbäddningar',
            ],
            'sort' => 'Sortering',
            'previous' => 'Föregående sida',
            'next' => 'Nästa sida',
            'nothing_selected' => 'Välj en fil för att se detaljerna.',
            // Put in front of the names of the uploads the browser refused.
            'rejected' => 'Accepteras inte här:',
            'dismiss' => 'Dölj',
            'alt' => 'Alt-text',
            'title' => 'Titel',
            'saved' => 'Sparat',
            'download' => 'Ladda ner',
            'play' => 'Spela upp här',
            'delete' => 'Radera',
            'deleting' => 'Raderar…',
            // The confirmation is Filament's own dialog: a heading naming the file, and under it
            // what follows from it. `:name` is filled in by the browser.
            'delete_heading' => 'Radera ”:name” för gott?',
            'confirm_delete' => 'Allt som fortfarande pekar på den slutar fungera.',
            // A library shared across records: what breaks is not on this screen.
            'confirm_delete_shared' => 'Den kan finnas i andra dokument också, och de förlorar den.',
            // A new file in the selected one's place, under the same id. `:from` and `:to` are
            // filled in by the browser, which is where both names are known.
            'replace' => 'Ersätt',
            'replacing' => 'Laddar upp…',
            'replace_heading' => 'Ersätta ”:from” med ”:to”?',
            'confirm_replace' => 'Alla dokument som använder den visar den nya filen.',
            'replace_refused' => '”:name” kan inte ta dess plats. Tillåtet: :accept',
            'replace_failed' => 'Filen kunde inte ersättas.',
            // How an entry using a file is named in the question before deleting or replacing it.
            'usage_entry' => ':model #:key ”:title”',
            'usage_entry_untitled' => ':model #:key',
            'usage_more' => '+:count till',
            // Where the file is used in entries the server found. They are changed too: a
            // deleted file is taken out of them, a replaced one shown in its place.
            'confirm_delete_used' => 'Den används i :count poster (:entries) och tas även bort från dem.',
            'confirm_delete_used_one' => 'Den används i en post (:entries) och tas även bort från den.',
            'confirm_replace_used' => 'Den används i :count poster (:entries), som kommer att visa den nya filen.',
            'confirm_replace_used_one' => 'Den används i en post (:entries), som kommer att visa den nya filen.',
            'deleted_from' => 'Raderad och borttagen från :count poster.',
            'deleted_from_one' => 'Raderad och borttagen från en post.',
            'replaced_in' => 'Ersatt och uppdaterad i :count poster.',
            'replaced_in_one' => 'Ersatt och uppdaterad i en post.',
            'copy_url' => 'Kopiera länk',
            'copied' => 'Kopierat',
            'drop' => 'Släpp för att ladda upp',
            'items' => 'objekt',
            'sorts' => [
                'newest' => 'Nyast först',
                'oldest' => 'Äldst först',
                'name' => 'Namn',
                'largest' => 'Störst först',
                'smallest' => 'Minst först',
            ],
            'details' => [
                'name' => 'Namn',
                'size' => 'Storlek',
                'dimensions' => 'Dimensioner',
                'type' => 'Typ',
                'modified' => 'Ändrad',
            ],
        ],
        'image_alt' => [
            'label' => 'Text',
            'alt' => 'Alt-text',
            'hint' => 'Står i stället för bilden där den inte kan ses. Lämna tomt för att ta bort den.',
            'caption' => 'Bildtext',
            'caption_hint' => 'Visas under bilden. Bara för en bild på en egen rad.',
        ],
        'image_size' => [
            'label' => 'Storlek',
            'width' => 'Bredd',
            'height' => 'Höjd',
            'apply' => 'Verkställ',
            'reset' => 'Återställ',
        ],
        'headings' => 'Rubriker',
        'lists' => 'Listor',
        'more' => 'Mer',
        'tools_menu' => 'Verktyg',
        'find' => [
            'label' => 'Sök och ersätt',
            'find' => 'Sök',
            'replace' => 'Ersätt med',
            'previous' => 'Föregående träff',
            'next' => 'Nästa träff',
            'replace_one' => 'Ersätt',
            'replace_all' => 'Ersätt alla',
            'close' => 'Stäng',
            'match_case' => 'Matcha gemener/versaler',
            'whole_word' => 'Endast hela ord',
            'no_results' => 'Inga träffar',
            // Both numbers sit in one sentence, because where they sit in it is a question
            // about the language and not one the browser should be answering.
            'count' => ':current av :total',
        ],
        'source_code' => [
            'label' => 'Källkod',
            'heading' => 'Källkod',
            'description' => 'HTML-koden som den sparas. Allt som redigeraren inte kan hantera försvinner när detta verkställs.',
            'apply' => 'Verkställ',
        ],
        'invisible_characters' => [
            'label' => 'Visa dolda tecken',
        ],
        'block_outlines' => [
            'label' => 'Visa block',
        ],
        'emoji' => [
            'label' => 'Emoji',
            'search' => 'Sök emoji',
            'empty' => 'Inget matchar det.',
            'empty_recent' => 'De emojier du väljer samlas här.',
            'close' => 'Stäng',
            'groups' => [
                'recent' => 'Ofta använda',
                'smileys' => 'Smileys och personer',
                'nature' => 'Djur och natur',
                'food' => 'Mat och dryck',
                'activities' => 'Aktiviteter',
                'travel' => 'Resor och platser',
                'objects' => 'Objekt',
                'symbols' => 'Symboler',
                'flags' => 'Flaggor',
            ],
        ],
        'direction' => [
            'ltr' => 'Vänster till höger',
            'rtl' => 'Höger till vänster',
        ],
        /*
         * The indent pair. Named for the move rather than for a state, because that is
         * what the buttons are: a block is not "indented" the way it is bold, it is one
         * step further in than it was.
         */
        'indent' => [
            'indent' => 'Öka indrag',
            'outdent' => 'Minska indrag',
        ],
    ],

    'preview' => [
        'label' => 'Förhandsgranskning',
        'heading' => 'Förhandsgranskning',
        'description' => 'Dokumentet så som din egen stilmall ritar det. Redigerarens stilar läses inte in här.',
        'frame' => 'Förhandsgranskning av dokumentet',
    ],

    'statistics' => [
        'label' => 'Statistik',
        'heading' => 'Statistik',
        'words' => 'Ord',
        'characters' => 'Tecken',
        'characters_without_spaces' => 'Tecken utan blanksteg',
        'paragraphs' => 'Block',
        'reading_time' => 'Lästid',
        'reading_time_none' => '—',
        'reading_time_under' => 'under en minut',
        'reading_time_minutes' => ':minutes min',
    ],

    'help' => [
        'label' => 'Hjälp',
        'heading' => 'Hjälp',
        'shortcuts' => 'Kortkommandon',
        'more' => 'Mer',
        'editing' => [
            'line_break' => 'Radbrytning utan nytt stycke',
            'paste_plain' => 'Klistra in som ren text',
            'indent_list' => 'Öka indrag för listpunkt',
            'outdent_list' => 'Minska indrag för listpunkt',
            'indent_block' => 'Öka indrag',
            'outdent_block' => 'Minska indrag',
            'change_case' => 'Växla skiftläge för markeringen',
            'next_cell' => 'Nästa tabellcell',
            'find' => 'Sök',
            'find_replace' => 'Sök och ersätt',
        ],
    ],

    'accessibility' => [
        'title' => 'Tillgänglighetskontroll',
        'close' => 'Stäng',
        'empty' => 'Inget att rapportera.',
        'ratio' => ':ratio av de :needed som krävs',
        'rules' => [
            'missing_alt' => 'Bild utan alt-text',
            'decorative_link' => 'Länkad bild utan något att läsa upp',
            'empty_link' => 'Länk utan text',
            'weak_link_text' => 'Länktexten säger ingenting',
            'skipped_heading' => 'Rubriknivå överhoppad',
            'table_without_header' => 'Tabell utan rubrikrad',
            'weak_contrast' => 'Textfärgen är för svag för att läsas',
        ],
        // A link's whole text has to be one of these, so what belongs here is what people
        // write as the entire text of a link and nothing longer.
        'weak_link_phrases' => [
            'här', 'klicka här', 'tryck här', 'klicka', 'den här länken', 'länk', 'läs mer',
            'läs här', 'mer', 'se mer', 'mer info', 'mer information', 'detaljer', 'ladda ner',
            'ladda ned', 'gå',
        ],
    ],

    'autosave' => [
        'found' => 'Ett osparat utkast från :time hittades i den här webbläsaren.',
        'restore' => 'Återställ',
        'discard' => 'Kasta',
    ],

    'drag_handle' => [
        'drag' => 'Dra för att flytta blocket, klicka för att markera det',
        'insert' => 'Lägg till ett block nedanför',
    ],

    'styles' => [
        'block' => 'Block',
        'inline' => 'Text',
    ],

    'fonts' => [
        'search' => 'Sök teckensnitt',
        'system' => 'System',
        'serif' => 'Serif',
        'monospace' => 'Monospace',
    ],

    'character_count' => [
        'characters' => [
            'one' => ':count tecken',
            'other' => ':count tecken',
        ],
        'characters_of_limit' => [
            'one' => ':count / :limit tecken',
            'other' => ':count / :limit tecken',
        ],
        'words' => [
            'one' => ':count ord',
            'other' => ':count ord',
        ],
    ],

    'validation' => [
        'min_words' => ':attribute måste ha minst :min ord.',
        'max_words' => ':attribute får inte ha fler än :max ord.',
        'must_contain' => ':attribute måste innehålla :content.',

        /*
         * What a node or a mark is called in that sentence. A type with no entry here falls
         * back to its own name, which is what makes a project's own node askable for without
         * an edit to this file.
         */
        'content' => [
            'blockquote' => 'ett citat',
            'bulletList' => 'en punktlista',
            'callout' => 'en inforuta',
            'codeBlock' => 'ett kodblock',
            'embed' => 'en inbäddad video',
            'heading' => 'en rubrik',
            'horizontalRule' => 'en horisontell linje',
            'image' => 'en bild',
            'link' => 'en länk',
            'orderedList' => 'en numrerad lista',
            'table' => 'en tabell',
            'taskList' => 'en uppgiftslista',
            'descriptionList' => 'en definitionslista',
        ],
    ],
];
