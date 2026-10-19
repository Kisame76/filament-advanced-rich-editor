<?php

declare(strict_types=1);

return [
    'slash' => [
        'groups' => [
            'style' => 'Typografi',
            'insert' => 'Indsæt',
        ],
        'empty' => 'Ingen matchende kommando',
        'aliases' => [
            'paragraph' => 'tekst, afsnit, brødtekst',
            'h1' => 'titel, overskrift 1',
            'h2' => 'overskrift 2, undertitel, underoverskrift',
            'h3' => 'overskrift 3',
            'h4' => 'overskrift 4',
            'h5' => 'overskrift 5',
            'h6' => 'overskrift 6',
            'bulletList' => 'ul, punktopstilling, punkter, liste',
            'orderedList' => 'ol, nummereret, nummerering, talopstilling',
            'taskList' => 'todo, opgaver, tjekliste, afkrydsning',
            'descriptionList' => 'dl, ordliste, definitioner, begreber, leksikon',
            'blockquote' => 'citat',
            'calloutNote' => 'note, bemærk, info, infoboks, boks, callout',
            'calloutTip' => 'tip, råd, forslag, boks, callout',
            'calloutWarning' => 'advarsel, forsigtig, obs, boks, callout',
            'calloutDanger' => 'fare, fejl, kritisk, boks, callout',
            'codeBlock' => 'kode, pre, kildekode, snippet',
            'horizontalRule' => 'hr, skillelinje, linje, adskiller',
            'details' => 'harmonika, accordion, fold ud, udvid, skjul',
            'embed' => 'video, youtube, vimeo, iframe, indlejr, medier',
            'video' => 'mp4, webm, film, klip, afspiller, medier',
            'audio' => 'mp3, lyd, musik, podcast, afspiller, medier',
            'file' => 'fil, dokument, pdf, vedhæftning, bilag, download, word, excel, zip',
            'image' => 'img, billede, foto',
            'table' => 'tabel, gitter, rækker, kolonner',
            'attachFiles' => 'upload, vedhæft, billedupload',
            'emoji' => 'smiley, humørikon, ikon',
            'characters' => 'symbol, specialtegn, tegn, tankestreg, pil, valuta, accent',
            'insertDate' => 'dato, dag, i dag, dags dato',
            'insertTime' => 'tid, klokken, klokkeslæt, nu, time',
            'insertDateTime' => 'dato og tid, tidsstempel, nu',
            'customBlocks' => 'blok, komponent',
            'mergeTags' => 'flettefelt, variabel, pladsholder, tag',
        ],
    ],
    /*
     * The kinds of callout. The key is the variant's own name, so a project that adds one
     * translates it by adding a key here; one without a translation is titled from its
     * name.
     */
    'callouts' => [
        'note' => 'Bemærk',
        'tip' => 'Tip',
        'warning' => 'Advarsel',
        'danger' => 'Fare',
    ],
    'tools' => [
        'image' => 'Billede',
        // The library button. Named for the library rather than for pictures, because that
        // is what it opens onto and what it will hold more of.
        'media_browser' => 'Medier',
        'link' => [
            'target' => [
                'label' => 'Åbner i',
                'self' => 'Samme vindue',
                'blank' => 'Nyt vindue',
                'parent' => 'Overordnet ramme',
                'top' => 'Øverste ramme',
            ],
            'rel' => [
                'label' => 'Relation (rel)',
                'new_tab_hint' => 'Et link, der åbner i et nyt vindue, får automatisk noopener og noreferrer.',
                'other' => 'Andre rel-værdier',
            ],
            'referrerpolicy' => 'Referrer-politik',
            'hreflang' => 'Sprog på den linkede side',
            'id' => 'Anker',
            'internal' => [
                'label' => 'Link til en post',
                'hint' => 'Et valg udfylder URL-feltet nedenfor, og det er den adresse, linket gemmer.',
            ],
        ],
        'code_block' => [
            'plain' => 'Ren tekst',
        ],
        'embed' => [
            'label' => 'Video',
            'heading' => 'Indlejr en video',
            'url' => 'Videolink',
            'url_hint' => 'Indsæt linket fra adresselinjen eller deleknappen. Et tidsstempel i linket bevares.',
            'title' => 'Titel',
            'title_hint' => 'Læses op af en skærmlæser i stedet for »video«.',
            'ratio' => 'Størrelsesforhold',
            'unsupported' => 'Det link er ikke en video, som denne editor kan indlejre.',
            'providers' => [
                'youtube' => 'YouTube',
                'vimeo' => 'Vimeo',
            ],
            // The bar over a selected embed.
            'replace' => 'Erstat video',
            'delete' => 'Fjern',
        ],
        /*
         * A file on this server rather than one somebody else hosts. The two buttons open
         * this one dialog, so the strings are shared and `kinds` names both of them.
         */
        'media' => [
            'label' => 'Video eller lyd',
            'heading' => 'Video eller lyd fra din server',
            'src' => 'Adresse',
            'src_hint' => 'En sti på denne server, f.eks. /storage/clips/foredrag.mp4, eller et fuldt https-link.',
            'kind' => 'Type',
            'kinds' => [
                'video' => 'Videofil',
                'audio' => 'Lydfil',
            ],
            'poster' => 'Miniaturebillede',
            'poster_hint' => 'Vises, før nogen trykker på afspil. Uden et er videoen et sort rektangel.',
            'title' => 'Titel',
            'title_hint' => 'Læses op af en skærmlæser i stedet for filnavnet.',
            'preload' => 'Forudindlæs',
            'preload_hint' => 'Hvor meget en browser henter, før der trykkes på afspil.',
            'preloads' => [
                'none' => 'Intet',
                'metadata' => 'Kun længde og størrelse',
                'auto' => 'Så meget, browseren vil',
            ],
            'loop' => 'Afspil igen, når den slutter',
            'unsupported' => 'En browser afspiller ikke filer fra den adresse.',
            // The bar over a selected film or sound.
            'replace' => 'Erstat fil',
            'download' => 'Download fil',
            'delete' => 'Fjern',
        ],
        /*
         * A document in the text, drawn as a card: the button that opens the browser on the
         * documents, and the bar over a card that is selected.
         */
        'file' => [
            'label' => 'Fil',
            'replace' => 'Erstat fil',
            'download' => 'Download fil',
            'delete' => 'Fjern',
        ],
        'format_brush' => [
            'label' => 'Formatpensel',
            'once' => 'Klar til ét penselstrøg',
            'sticky' => 'Klar, indtil du slår den fra',
        ],

        'task_list' => 'Opgaveliste',

        'description_list' => 'Definitionsliste',
        'callouts' => 'Infoboks',
        'language' => 'Sprog',
        'language_none' => 'Sidens sprog',
        'list_properties' => [
            'bullet' => 'Listeegenskaber',
            'ordered' => 'Listeegenskaber',
            'marker' => 'Markør',
            'marker_default' => 'Standard',
            'start' => 'Start ved',
            'reversed' => 'Tæl baglæns',
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
                    'disc' => 'Prik',
                    'circle' => 'Cirkel',
                    'square' => 'Firkant',
                ],
            ],
        ],
        'characters' => [
            'label' => 'Specialtegn',
            'search' => 'Søg efter tegn...',
            'empty' => 'Intet matchende tegn',
            'empty_recent' => 'Tegn, du vælger, vises her.',
            'close' => 'Luk',
            'groups' => [
                'recent' => 'Seneste',
                'punctuation' => 'Tegnsætning',
                'currency' => 'Valuta',
                'math' => 'Matematik',
                'arrows' => 'Pile',
                'symbols' => 'Symboler',
                'latin' => 'Latinske bogstaver',
                'greek' => 'Græske bogstaver',
            ],
        ],
        'heading_level' => 'Overskrift :level',
        'styles' => 'Typografi',
        'styles_clear' => 'Ingen',
        'font_family' => 'Skrifttype',
        'font_family_clear' => 'Standard',
        'font_size' => [
            'label' => 'Skriftstørrelse',
            'custom' => 'Tilpasset størrelse',
            'default' => 'Standard',
        ],
        'alignment' => 'Justering',
        'line_height' => [
            'label' => 'Linjeafstand',
            'single' => 'Enkelt (1,0)',
            'double' => 'Dobbelt (2,0)',
            'value' => ':value',
        ],
        /*
         * The date tools. The key is the configured format's own key, so a project that
         * adds a fourth format translates it by adding a key here; one without a
         * translation is labelled with the format itself, rendered as it will read.
         */
        'date_time' => [
            'label' => 'Dato og klokkeslæt',
            // The configured keys live under their own key rather than beside 'label', so a
            // format keyed `label` cannot resolve to the dropdown's own text.
            'formats' => [
                'date' => 'Dato',
                'time' => 'Klokkeslæt',
                'date_time' => 'Dato og klokkeslæt',
            ],
        ],
        'text_case' => [
            'label' => 'Skift store/små bogstaver',
            // Each one is set in the case it applies, so the list says what it does without
            // three icons that would all have to mean "the letter A, but differently".
            'sentence' => 'Som i en sætning',
            'lower' => 'små bogstaver',
            'upper' => 'STORE BOGSTAVER',
        ],
        'align' => [
            'start' => 'Venstre',
            'center' => 'Centreret',
            'end' => 'Højre',
            'justify' => 'Lige margener',
        ],
        'image_lock' => [
            'locked' => 'Størrelsesforhold låst',
            'unlocked' => 'Størrelsesforhold ulåst',
        ],
        'paragraph' => 'Afsnit',
        'text_color' => 'Tekstfarve',
        'text_background' => 'Tekstbaggrund',
        'color_clear' => 'Ingen farve',
        'color_custom' => 'Vælg en farve',
        'fullscreen' => [
            'enter' => 'Fuld skærm',
            'exit' => 'Afslut fuld skærm',
        ],
        'image_replace' => 'Erstat billede',
        'image_edit' => [
            'label' => 'Rediger billede',
            'apply' => 'Anvend',
            'hint' => 'Brug blyanten på billedet til at beskære, rotere eller spejlvende det. Den oprindelige fil bevares.',
        ],
        'image_download' => 'Download billede',
        'image_delete' => 'Slet billede',
        'image_float_left' => 'Flydende venstre',
        'image_float_right' => 'Flydende højre',
        'image_float_center' => 'Centrér',
        'image_decorative' => 'Dekorativt billede',

        'image_link' => [
            'label' => 'Link billede',
            'heading' => 'Hvor dette billede peger hen',
            'href' => 'Adresse',
            'href_help' => 'En fuld adresse eller en på dette websted, f.eks. /artikler/7. Lad feltet være tomt for at fjerne linket.',
            'new_tab' => 'Åbn i en ny fane',
        ],
        'image_rotate_left' => 'Roter til venstre',
        'image_rotate_right' => 'Roter til højre',
        'media_library' => [
            'label' => 'Billede',
            'heading' => 'Indsæt fra biblioteket',
            'search' => 'Søg i filer...',
            'empty_record' => 'Der er intet på denne post endnu. Upload en fil for at begynde.',
            'empty_library' => 'Biblioteket er tomt. Upload en fil for at komme i gang.',
            'empty_search' => 'Intet matcher det.',
            'up' => 'En mappe op',
            'pending' => 'Ikke gemt endnu',
            'upload' => 'Upload',
            'view_grid' => 'Fliser',
            'view_list' => 'Liste',
            'filter' => 'Filter',
            'from_url' => 'Fra et link',
            'from_url_heading' => 'Tilføj fra et link',
            'from_url_label' => 'Link',
            'from_url_hint' => 'Et YouTube- eller Vimeo-link føjes til dit bibliotek og kan bruges igen. Enhver anden adresse — en fil på en andens server — indsættes, som den er, et dokument som et kort, og gemmes ikke her.',
            'from_url_hint_files' => 'En fil, som en anden hoster. Den indsættes, som den er, et dokument som et kort, og intet føjes til dit bibliotek.',
            'from_url_no_embeds' => 'Videoer fra YouTube og Vimeo er slået fra for dette felt.',
            'all_types' => 'Alle typer',
            'all_kinds' => 'Alle',
            'kinds' => [
                'image' => 'Billeder',
                'video' => 'Video',
                'audio' => 'Lyd',
                'file' => 'Filer',
                'embed' => 'Indlejringer',
            ],
            'sort' => 'Sortering',
            'previous' => 'Forrige side',
            'next' => 'Næste side',
            'nothing_selected' => 'Vælg en fil for at se detaljerne.',
            // Put in front of the names of the uploads the browser refused.
            'rejected' => 'Ikke accepteret her:',
            'dismiss' => 'Skjul',
            'alt' => 'Alt-tekst',
            'title' => 'Titel',
            'saved' => 'Gemt',
            'download' => 'Download',
            'play' => 'Afspil her',
            'delete' => 'Slet',
            'deleting' => 'Sletter…',
            // The confirmation is Filament's own dialog: a heading naming the file, and under it
            // what follows from it. `:name` is filled in by the browser.
            'delete_heading' => 'Slet »:name« permanent?',
            'confirm_delete' => 'Alt, der stadig peger på den, holder op med at virke.',
            // A library shared across records: what breaks is not on this screen.
            'confirm_delete_shared' => 'Den kan også være i andre dokumenter, og de vil miste den.',
            // A new file in the selected one's place, under the same id. `:from` and `:to` are
            // filled in by the browser, which is where both names are known.
            'replace' => 'Erstat',
            'replacing' => 'Uploader…',
            'replace_heading' => 'Erstat »:from« med »:to«?',
            'confirm_replace' => 'Alle dokumenter, der bruger den, vil vise den nye fil.',
            'replace_refused' => '»:name« kan ikke tage dens plads. Tilladt: :accept',
            'replace_failed' => 'Filen kunne ikke erstattes.',
            // How an entry using a file is named in the question before deleting or replacing it.
            'usage_entry' => ':model #:key »:title«',
            'usage_entry_untitled' => ':model #:key',
            'usage_more' => '+:count flere',
            // Where the file is used in entries the server found. They are changed too: a
            // deleted file is taken out of them, a replaced one shown in its place.
            'confirm_delete_used' => 'Den bruges i :count poster (:entries) og vil også blive fjernet fra dem.',
            'confirm_delete_used_one' => 'Den bruges i én post (:entries) og vil også blive fjernet fra den.',
            'confirm_replace_used' => 'Den bruges i :count poster (:entries), som vil vise den nye fil.',
            'confirm_replace_used_one' => 'Den bruges i én post (:entries), som vil vise den nye fil.',
            'deleted_from' => 'Slettet og fjernet fra :count poster.',
            'deleted_from_one' => 'Slettet og fjernet fra én post.',
            'replaced_in' => 'Erstattet og opdateret i :count poster.',
            'replaced_in_one' => 'Erstattet og opdateret i én post.',
            'copy_url' => 'Kopiér link',
            'copied' => 'Kopieret',
            'drop' => 'Slip for at uploade',
            'items' => 'elementer',
            'sorts' => [
                'newest' => 'Nyeste først',
                'oldest' => 'Ældste først',
                'name' => 'Navn',
                'largest' => 'Største først',
                'smallest' => 'Mindste først',
            ],
            'details' => [
                'name' => 'Navn',
                'size' => 'Størrelse',
                'dimensions' => 'Dimensioner',
                'type' => 'Type',
                'modified' => 'Ændret',
            ],
        ],
        'image_alt' => [
            'label' => 'Tekst',
            'alt' => 'Alt-tekst',
            'hint' => 'Træder i stedet for billedet, hvor det ikke kan ses. Lad feltet være tomt for at fjerne den.',
            'caption' => 'Billedtekst',
            'caption_hint' => 'Vises under billedet. Kun for et billede på sin egen linje.',
        ],
        'image_size' => [
            'label' => 'Størrelse',
            'width' => 'Bredde',
            'height' => 'Højde',
            'apply' => 'Anvend',
            'reset' => 'Nulstil',
        ],
        'headings' => 'Overskrifter',
        'lists' => 'Lister',
        'more' => 'Mere',
        'tools_menu' => 'Værktøjer',
        'find' => [
            'label' => 'Søg og erstat',
            'find' => 'Søg',
            'replace' => 'Erstat med',
            'previous' => 'Forrige forekomst',
            'next' => 'Næste forekomst',
            'replace_one' => 'Erstat',
            'replace_all' => 'Erstat alle',
            'close' => 'Luk',
            'match_case' => 'Forskel på store og små bogstaver',
            'whole_word' => 'Kun hele ord',
            'no_results' => 'Ingen forekomster',
            // Both numbers sit in one sentence, because where they sit in it is a question
            // about the language and not one the browser should be answering.
            'count' => ':current af :total',
        ],
        'source_code' => [
            'label' => 'Kildekode',
            'heading' => 'Kildekode',
            'description' => 'HTML-koden, som den gemmes. Alt, hvad editoren ikke kan rumme, fjernes, når dette anvendes.',
            'apply' => 'Anvend',
        ],
        'invisible_characters' => [
            'label' => 'Vis skjulte tegn',
        ],
        'block_outlines' => [
            'label' => 'Vis blokke',
        ],
        'emoji' => [
            'label' => 'Emoji',
            'search' => 'Søg efter emoji',
            'empty' => 'Intet matcher det.',
            'empty_recent' => 'De emojis, du vælger, samles her.',
            'close' => 'Luk',
            'groups' => [
                'recent' => 'Ofte brugt',
                'smileys' => 'Smileys og personer',
                'nature' => 'Dyr og natur',
                'food' => 'Mad og drikke',
                'activities' => 'Aktiviteter',
                'travel' => 'Rejser og steder',
                'objects' => 'Objekter',
                'symbols' => 'Symboler',
                'flags' => 'Flag',
            ],
        ],
        'direction' => [
            'ltr' => 'Venstre mod højre',
            'rtl' => 'Højre mod venstre',
        ],
        /*
         * The indent pair. Named for the move rather than for a state, because that is
         * what the buttons are: a block is not "indented" the way it is bold, it is one
         * step further in than it was.
         */
        'indent' => [
            'indent' => 'Øg indrykning',
            'outdent' => 'Formindsk indrykning',
        ],
    ],

    'preview' => [
        'label' => 'Forhåndsvisning',
        'heading' => 'Forhåndsvisning',
        'description' => 'Dokumentet, som dit eget stylesheet tegner det. Editorens styling er ikke indlæst her.',
        'frame' => 'Forhåndsvisning af dokumentet',
    ],

    'statistics' => [
        'label' => 'Statistik',
        'heading' => 'Statistik',
        'words' => 'Ord',
        'characters' => 'Tegn',
        'characters_without_spaces' => 'Tegn uden mellemrum',
        'paragraphs' => 'Blokke',
        'reading_time' => 'Læsetid',
        'reading_time_none' => '—',
        'reading_time_under' => 'under et minut',
        'reading_time_minutes' => ':minutes min.',
    ],

    'help' => [
        'label' => 'Hjælp',
        'heading' => 'Hjælp',
        'shortcuts' => 'Tastaturgenveje',
        'more' => 'Mere',
        'editing' => [
            'line_break' => 'Linjeskift uden nyt afsnit',
            'paste_plain' => 'Indsæt som ren tekst',
            'indent_list' => 'Ryk listepunkt ind',
            'outdent_list' => 'Ryk listepunkt ud',
            'indent_block' => 'Øg indrykning',
            'outdent_block' => 'Formindsk indrykning',
            'change_case' => 'Skift mellem store og små bogstaver i markeringen',
            'next_cell' => 'Næste tabelcelle',
            'find' => 'Søg',
            'find_replace' => 'Søg og erstat',
        ],
    ],

    'accessibility' => [
        'title' => 'Tilgængelighedstjek',
        'close' => 'Luk',
        'empty' => 'Intet at rapportere.',
        'ratio' => ':ratio af de krævede :needed',
        'rules' => [
            'missing_alt' => 'Billede uden alt-tekst',
            'decorative_link' => 'Linket billede uden noget at læse op',
            'empty_link' => 'Link uden tekst',
            'weak_link_text' => 'Linkteksten siger intet',
            'skipped_heading' => 'Overskriftsniveau sprunget over',
            'table_without_header' => 'Tabel uden overskriftsrække',
            'weak_contrast' => 'Tekstfarven er for svag til at kunne læses',
        ],
        // A link's whole text has to be one of these, so what belongs here is what people
        // write as the entire text of a link and nothing longer.
        'weak_link_phrases' => [
            'her', 'klik her', 'tryk her', 'klik', 'dette link', 'link', 'læs mere', 'læs her',
            'mere', 'se mere', 'mere info', 'mere information', 'detaljer', 'download', 'hent', 'gå',
        ],
    ],

    'autosave' => [
        'found' => 'Der blev fundet en ikke-gemt kladde fra :time i denne browser.',
        'restore' => 'Gendan',
        'discard' => 'Kassér',
    ],

    'drag_handle' => [
        'drag' => 'Træk for at flytte denne blok, klik for at markere den',
        'insert' => 'Tilføj en blok nedenfor',
    ],

    'styles' => [
        'block' => 'Blok',
        'inline' => 'Tekst',
    ],

    'fonts' => [
        'search' => 'Søg efter skrifttyper',
        'system' => 'System',
        'serif' => 'Serif',
        'monospace' => 'Monospace',
    ],

    'character_count' => [
        'characters' => [
            'one' => ':count tegn',
            'other' => ':count tegn',
        ],
        'characters_of_limit' => [
            'one' => ':count / :limit tegn',
            'other' => ':count / :limit tegn',
        ],
        'words' => [
            'one' => ':count ord',
            'other' => ':count ord',
        ],
    ],

    'validation' => [
        'min_words' => ':attribute skal have mindst :min ord.',
        'max_words' => ':attribute må ikke have mere end :max ord.',
        'must_contain' => ':attribute skal indeholde :content.',

        /*
         * What a node or a mark is called in that sentence. A type with no entry here falls
         * back to its own name, which is what makes a project's own node askable for without
         * an edit to this file.
         */
        'content' => [
            'blockquote' => 'et citat',
            'bulletList' => 'en punktopstilling',
            'callout' => 'en infoboks',
            'codeBlock' => 'en kodeblok',
            'embed' => 'en indlejret video',
            'heading' => 'en overskrift',
            'horizontalRule' => 'en vandret linje',
            'image' => 'et billede',
            'link' => 'et link',
            'orderedList' => 'en nummereret liste',
            'table' => 'en tabel',
            'taskList' => 'en opgaveliste',
            'descriptionList' => 'en definitionsliste',
        ],
    ],
];
