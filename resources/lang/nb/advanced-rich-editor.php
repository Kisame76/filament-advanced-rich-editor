<?php

declare(strict_types=1);

return [
    'slash' => [
        'groups' => [
            'style' => 'Stil',
            'insert' => 'Sett inn',
        ],
        'empty' => 'Ingen passende kommando',
        'aliases' => [
            'paragraph' => 'tekst, avsnitt, brødtekst',
            'h1' => 'tittel, overskrift 1',
            'h2' => 'overskrift 2, undertittel',
            'h3' => 'overskrift 3',
            'h4' => 'overskrift 4',
            'h5' => 'overskrift 5',
            'h6' => 'overskrift 6',
            'bulletList' => 'ul, punktliste, punkter, kulepunkter, liste',
            'orderedList' => 'ol, nummerert, nummerering, tall',
            'taskList' => 'todo, oppgaver, sjekkliste, gjøremål, avkrysning',
            'descriptionList' => 'dl, ordliste, definisjoner, begreper, leksikon',
            'blockquote' => 'sitat',
            'calloutNote' => 'merknad, notat, info, infoboks, boks, callout',
            'calloutTip' => 'tips, råd, forslag, boks, callout',
            'calloutWarning' => 'advarsel, forsiktig, obs, boks, callout',
            'calloutDanger' => 'fare, feil, kritisk, boks, callout',
            'codeBlock' => 'kode, pre, kildekode, kodesnutt',
            'horizontalRule' => 'hr, skillelinje, linje, skille',
            'details' => 'trekkspill, accordion, utvid, skjul, sammenleggbar',
            'embed' => 'video, youtube, vimeo, iframe, innebygging, medier',
            'video' => 'mp4, webm, film, klipp, avspiller, medier',
            'audio' => 'mp3, lyd, musikk, podkast, podcast, avspiller, medier',
            'file' => 'fil, dokument, pdf, vedlegg, nedlasting, last ned, word, excel, zip',
            'image' => 'img, bilde, foto',
            'table' => 'tabell, rutenett, rader, kolonner',
            'attachFiles' => 'last opp, opplasting, bildeopplasting',
            'emoji' => 'smilefjes, smiley, ikon',
            'characters' => 'symbol, spesialtegn, tegn, tankestrek, pil, valuta, aksent',
            'insertDate' => 'dato, dag, i dag, dagens dato',
            'insertTime' => 'tid, klokke, klokkeslett, nå, time',
            'insertDateTime' => 'dato og tid, tidsstempel, nå',
            'customBlocks' => 'blokk, komponent',
            'mergeTags' => 'flettefelt, variabel, plassholder, tagg',
        ],
    ],
    /*
     * The kinds of callout. The key is the variant's own name, so a project that adds one
     * translates it by adding a key here; one without a translation is titled from its
     * name.
     */
    'callouts' => [
        'note' => 'Merknad',
        'tip' => 'Tips',
        'warning' => 'Advarsel',
        'danger' => 'Fare',
    ],
    'tools' => [
        'image' => 'Bilde',
        // The library button. Named for the library rather than for pictures, because that
        // is what it opens onto and what it will hold more of.
        'media_browser' => 'Medier',
        'link' => [
            'target' => [
                'label' => 'Åpnes i',
                'self' => 'Samme vindu',
                'blank' => 'Nytt vindu',
                'parent' => 'Overordnet ramme',
                'top' => 'Øverste ramme',
            ],
            'rel' => [
                'label' => 'Relasjon (rel)',
                'new_tab_hint' => 'En lenke som åpnes i et nytt vindu, får noopener og noreferrer automatisk.',
                'other' => 'Andre rel-verdier',
            ],
            'referrerpolicy' => 'Referrer-policy',
            'hreflang' => 'Språk på den lenkede siden',
            'id' => 'Anker',
            'internal' => [
                'label' => 'Lenke til en post',
                'hint' => 'Et valg fyller ut URL-en nedenfor, og det er den lenken lagrer.',
            ],
        ],
        'code_block' => [
            'plain' => 'Ren tekst',
        ],
        'embed' => [
            'label' => 'Video',
            'heading' => 'Bygg inn en video',
            'url' => 'Videolenke',
            'url_hint' => 'Lim inn lenken fra adresselinjen eller deleknappen. Et tidsstempel i den beholdes.',
            'title' => 'Tittel',
            'title_hint' => 'Leses opp av en skjermleser i stedet for «video».',
            'ratio' => 'Sideforhold',
            'unsupported' => 'Den lenken er ikke en video denne editoren kan bygge inn.',
            'providers' => [
                'youtube' => 'YouTube',
                'vimeo' => 'Vimeo',
            ],
            // The bar over a selected embed.
            'replace' => 'Erstatt video',
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
            'src_hint' => 'En sti på denne serveren, f.eks. /storage/clips/foredrag.mp4, eller en fullstendig https-lenke.',
            'kind' => 'Type',
            'kinds' => [
                'video' => 'Videofil',
                'audio' => 'Lydfil',
            ],
            'poster' => 'Miniatyrbilde',
            'poster_hint' => 'Vises før noen trykker på spill av. Uten et er videoen et svart rektangel.',
            'title' => 'Tittel',
            'title_hint' => 'Leses opp av en skjermleser i stedet for filnavnet.',
            'preload' => 'Forhåndslast',
            'preload_hint' => 'Hvor mye en nettleser henter før noen trykker på spill av.',
            'preloads' => [
                'none' => 'Ingenting',
                'metadata' => 'Bare lengde og størrelse',
                'auto' => 'Så mye nettleseren vil',
            ],
            'loop' => 'Spill av på nytt når den er ferdig',
            'unsupported' => 'En nettleser spiller ikke av filer fra den adressen.',
            // The bar over a selected film or sound.
            'replace' => 'Erstatt fil',
            'download' => 'Last ned fil',
            'delete' => 'Fjern',
        ],
        /*
         * A document in the text, drawn as a card: the button that opens the browser on the
         * documents, and the bar over a card that is selected.
         */
        'file' => [
            'label' => 'Fil',
            'replace' => 'Erstatt fil',
            'download' => 'Last ned fil',
            'delete' => 'Fjern',
        ],
        'format_brush' => [
            'label' => 'Kopier format',
            'once' => 'Klar for ett penselstrøk',
            'sticky' => 'Klar til du slår den av',
        ],

        'task_list' => 'Oppgaveliste',

        'description_list' => 'Definisjonsliste',
        'callouts' => 'Infoboks',
        'language' => 'Språk',
        'language_none' => 'Sidens språk',
        'list_properties' => [
            'bullet' => 'Listeegenskaper',
            'ordered' => 'Listeegenskaper',
            'marker' => 'Markør',
            'marker_default' => 'Standard',
            'start' => 'Start på',
            'reversed' => 'Tell baklengs',
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
                    'disc' => 'Prikk',
                    'circle' => 'Sirkel',
                    'square' => 'Firkant',
                ],
            ],
        ],
        'characters' => [
            'label' => 'Spesialtegn',
            'search' => 'Søk etter tegn...',
            'empty' => 'Ingen passende tegn',
            'empty_recent' => 'Tegn du velger, vises her.',
            'close' => 'Lukk',
            'groups' => [
                'recent' => 'Nylig brukt',
                'punctuation' => 'Tegnsetting',
                'currency' => 'Valuta',
                'math' => 'Matematikk',
                'arrows' => 'Piler',
                'symbols' => 'Symboler',
                'latin' => 'Latinske bokstaver',
                'greek' => 'Greske bokstaver',
            ],
        ],
        'heading_level' => 'Overskrift :level',
        'styles' => 'Stil',
        'styles_clear' => 'Ingen',
        'font_family' => 'Skrift',
        'font_family_clear' => 'Standard',
        'font_size' => [
            'label' => 'Skriftstørrelse',
            'custom' => 'Egendefinert størrelse',
            'default' => 'Standard',
        ],
        'alignment' => 'Justering',
        'line_height' => [
            'label' => 'Linjeavstand',
            'single' => 'Enkel (1,0)',
            'double' => 'Dobbel (2,0)',
            'value' => ':value',
        ],
        /*
         * The date tools. The key is the configured format's own key, so a project that
         * adds a fourth format translates it by adding a key here; one without a
         * translation is labelled with the format itself, rendered as it will read.
         */
        'date_time' => [
            'label' => 'Dato og klokkeslett',
            // The configured keys live under their own key rather than beside 'label', so a
            // format keyed `label` cannot resolve to the dropdown's own text.
            'formats' => [
                'date' => 'Dato',
                'time' => 'Klokkeslett',
                'date_time' => 'Dato og klokkeslett',
            ],
        ],
        'text_case' => [
            'label' => 'Endre store/små bokstaver',
            // Each one is set in the case it applies, so the list says what it does without
            // three icons that would all have to mean "the letter A, but differently".
            'sentence' => 'Som i en setning',
            'lower' => 'små bokstaver',
            'upper' => 'STORE BOKSTAVER',
        ],
        'align' => [
            'start' => 'Venstre',
            'center' => 'Midtstilt',
            'end' => 'Høyre',
            'justify' => 'Blokkjustert',
        ],
        'image_lock' => [
            'locked' => 'Sideforhold låst',
            'unlocked' => 'Sideforhold ulåst',
        ],
        'paragraph' => 'Avsnitt',
        'text_color' => 'Tekstfarge',
        'text_background' => 'Tekstbakgrunn',
        'color_clear' => 'Ingen farge',
        'color_custom' => 'Velg en farge',
        'fullscreen' => [
            'enter' => 'Fullskjerm',
            'exit' => 'Avslutt fullskjerm',
        ],
        'image_replace' => 'Erstatt bilde',
        'image_download' => 'Last ned bilde',
        'image_delete' => 'Slett bilde',
        'image_float_left' => 'Flytende venstre',
        'image_float_right' => 'Flytende høyre',
        'image_float_center' => 'Midtstill',
        'image_decorative' => 'Dekorativt bilde',

        'image_link' => [
            'label' => 'Lenk bilde',
            'heading' => 'Hvor dette bildet peker',
            'href' => 'Adresse',
            'href_help' => 'En fullstendig adresse, eller en på dette nettstedet, f.eks. /artikler/7. La feltet stå tomt for å fjerne lenken.',
            'new_tab' => 'Åpne i ny fane',
        ],
        'image_rotate_left' => 'Roter til venstre',
        'image_rotate_right' => 'Roter til høyre',
        'media_library' => [
            'label' => 'Bilde',
            'heading' => 'Sett inn fra biblioteket',
            'search' => 'Søk i filer...',
            'empty_record' => 'Ingenting på denne posten ennå. Last opp en fil for å begynne.',
            'empty_library' => 'Biblioteket er tomt. Last opp en fil for å komme i gang.',
            'empty_search' => 'Ingenting passer til det.',
            'up' => 'En mappe opp',
            'pending' => 'Ikke lagret ennå',
            'upload' => 'Last opp',
            'view_grid' => 'Fliser',
            'view_list' => 'Liste',
            'filter' => 'Filter',
            'from_url' => 'Fra en lenke',
            'from_url_heading' => 'Legg til fra en lenke',
            'from_url_label' => 'Lenke',
            'from_url_hint' => 'En YouTube- eller Vimeo-lenke legges til i biblioteket ditt og kan brukes igjen. Alle andre adresser — en fil på noen andres server — settes inn som de er, et dokument som et kort, og lagres ikke her.',
            'from_url_hint_files' => 'En fil som noen andre er vert for. Den settes inn som den er, et dokument som et kort, og ingenting legges til i biblioteket ditt.',
            'from_url_no_embeds' => 'Videoer fra YouTube og Vimeo er slått av for dette feltet.',
            'all_types' => 'Alle typer',
            'all_kinds' => 'Alle',
            'kinds' => [
                'image' => 'Bilder',
                'video' => 'Video',
                'audio' => 'Lyd',
                'file' => 'Filer',
                'embed' => 'Innebygginger',
            ],
            'sort' => 'Sortering',
            'previous' => 'Forrige side',
            'next' => 'Neste side',
            'nothing_selected' => 'Velg en fil for å se detaljene.',
            // Put in front of the names of the uploads the browser refused.
            'rejected' => 'Ikke godtatt her:',
            'dismiss' => 'Skjul',
            'alt' => 'Alt-tekst',
            'title' => 'Tittel',
            'saved' => 'Lagret',
            'download' => 'Last ned',
            'play' => 'Spill av her',
            'delete' => 'Slett',
            'deleting' => 'Sletter…',
            // The confirmation is Filament's own dialog: a heading naming the file, and under it
            // what follows from it. `:name` is filled in by the browser.
            'delete_heading' => 'Slette «:name» for godt?',
            'confirm_delete' => 'Alt som fortsatt peker på den, slutter å virke.',
            // A library shared across records: what breaks is not on this screen.
            'confirm_delete_shared' => 'Den kan også finnes i andre dokumenter, og de vil miste den.',
            // A new file in the selected one's place, under the same id. `:from` and `:to` are
            // filled in by the browser, which is where both names are known.
            'replace' => 'Erstatt',
            'replacing' => 'Laster opp…',
            'replace_heading' => 'Erstatte «:from» med «:to»?',
            'confirm_replace' => 'Alle dokumenter som bruker den, vil vise den nye filen.',
            'replace_refused' => '«:name» kan ikke ta dens plass. Tillatt: :accept',
            'replace_failed' => 'Filen kunne ikke erstattes.',
            // How an entry using a file is named in the question before deleting or replacing it.
            'usage_entry' => ':model #:key «:title»',
            'usage_entry_untitled' => ':model #:key',
            'usage_more' => '+:count til',
            // Where the file is used in entries the server found. They are changed too: a
            // deleted file is taken out of them, a replaced one shown in its place.
            'confirm_delete_used' => 'Den brukes i :count oppføringer (:entries) og blir også fjernet fra dem.',
            'confirm_delete_used_one' => 'Den brukes i én oppføring (:entries) og blir også fjernet fra den.',
            'confirm_replace_used' => 'Den brukes i :count oppføringer (:entries), som vil vise den nye filen.',
            'confirm_replace_used_one' => 'Den brukes i én oppføring (:entries), som vil vise den nye filen.',
            'deleted_from' => 'Slettet og fjernet fra :count oppføringer.',
            'deleted_from_one' => 'Slettet og fjernet fra én oppføring.',
            'replaced_in' => 'Erstattet og oppdatert i :count oppføringer.',
            'replaced_in_one' => 'Erstattet og oppdatert i én oppføring.',
            'copy_url' => 'Kopier lenke',
            'copied' => 'Kopiert',
            'drop' => 'Slipp for å laste opp',
            'items' => 'elementer',
            'sorts' => [
                'newest' => 'Nyeste først',
                'oldest' => 'Eldste først',
                'name' => 'Navn',
                'largest' => 'Største først',
                'smallest' => 'Minste først',
            ],
            'details' => [
                'name' => 'Navn',
                'size' => 'Størrelse',
                'dimensions' => 'Dimensjoner',
                'type' => 'Type',
                'modified' => 'Endret',
            ],
        ],
        'image_alt' => [
            'label' => 'Tekst',
            'alt' => 'Alt-tekst',
            'hint' => 'Står i stedet for bildet der det ikke kan ses. La feltet stå tomt for å fjerne den.',
            'caption' => 'Bildetekst',
            'caption_hint' => 'Vises under bildet. Bare for et bilde på sin egen linje.',
        ],
        'image_size' => [
            'label' => 'Størrelse',
            'width' => 'Bredde',
            'height' => 'Høyde',
            'apply' => 'Bruk',
            'reset' => 'Nullstill',
        ],
        'headings' => 'Overskrifter',
        'lists' => 'Lister',
        'more' => 'Mer',
        'tools_menu' => 'Verktøy',
        'find' => [
            'label' => 'Søk og erstatt',
            'find' => 'Søk',
            'replace' => 'Erstatt med',
            'previous' => 'Forrige treff',
            'next' => 'Neste treff',
            'replace_one' => 'Erstatt',
            'replace_all' => 'Erstatt alle',
            'close' => 'Lukk',
            'match_case' => 'Skill mellom store og små bokstaver',
            'whole_word' => 'Bare hele ord',
            'no_results' => 'Ingen treff',
            // Both numbers sit in one sentence, because where they sit in it is a question
            // about the language and not one the browser should be answering.
            'count' => ':current av :total',
        ],
        'source_code' => [
            'label' => 'Kildekode',
            'heading' => 'Kildekode',
            'description' => 'HTML-koden slik den lagres. Alt editoren ikke kan håndtere, forkastes når dette brukes.',
            'apply' => 'Bruk',
        ],
        'invisible_characters' => [
            'label' => 'Vis skjulte tegn',
        ],
        'block_outlines' => [
            'label' => 'Vis blokker',
        ],
        'emoji' => [
            'label' => 'Emoji',
            'search' => 'Søk etter emoji',
            'empty' => 'Ingenting passer til det.',
            'empty_recent' => 'Emojiene du velger, samles her.',
            'close' => 'Lukk',
            'groups' => [
                'recent' => 'Ofte brukt',
                'smileys' => 'Smilefjes og personer',
                'nature' => 'Dyr og natur',
                'food' => 'Mat og drikke',
                'activities' => 'Aktiviteter',
                'travel' => 'Reise og steder',
                'objects' => 'Objekter',
                'symbols' => 'Symboler',
                'flags' => 'Flagg',
            ],
        ],
        'direction' => [
            'ltr' => 'Venstre til høyre',
            'rtl' => 'Høyre til venstre',
        ],
        /*
         * The indent pair. Named for the move rather than for a state, because that is
         * what the buttons are: a block is not "indented" the way it is bold, it is one
         * step further in than it was.
         */
        'indent' => [
            'indent' => 'Øk innrykk',
            'outdent' => 'Reduser innrykk',
        ],
    ],

    'preview' => [
        'label' => 'Forhåndsvisning',
        'heading' => 'Forhåndsvisning',
        'description' => 'Dokumentet slik ditt eget stilark tegner det. Editorens stiler er ikke lastet inn her.',
        'frame' => 'Forhåndsvisning av dokumentet',
    ],

    'statistics' => [
        'label' => 'Statistikk',
        'heading' => 'Statistikk',
        'words' => 'Ord',
        'characters' => 'Tegn',
        'characters_without_spaces' => 'Tegn uten mellomrom',
        'paragraphs' => 'Blokker',
        'reading_time' => 'Lesetid',
        'reading_time_none' => '—',
        'reading_time_under' => 'under ett minutt',
        'reading_time_minutes' => ':minutes min',
    ],

    'help' => [
        'label' => 'Hjelp',
        'heading' => 'Hjelp',
        'shortcuts' => 'Hurtigtaster',
        'more' => 'Mer',
        'editing' => [
            'line_break' => 'Linjeskift uten nytt avsnitt',
            'paste_plain' => 'Lim inn som ren tekst',
            'indent_list' => 'Rykk inn listepunkt',
            'outdent_list' => 'Rykk ut listepunkt',
            'indent_block' => 'Øk innrykk',
            'outdent_block' => 'Reduser innrykk',
            'change_case' => 'Veksle mellom store og små bokstaver i markeringen',
            'next_cell' => 'Neste tabellcelle',
            'find' => 'Søk',
            'find_replace' => 'Søk og erstatt',
        ],
    ],

    'accessibility' => [
        'title' => 'Tilgjengelighetssjekk',
        'close' => 'Lukk',
        'empty' => 'Ingenting å rapportere.',
        'ratio' => ':ratio av påkrevde :needed',
        'rules' => [
            'missing_alt' => 'Bilde uten alt-tekst',
            'decorative_link' => 'Lenket bilde uten noe å lese opp',
            'empty_link' => 'Lenke uten tekst',
            'weak_link_text' => 'Lenketeksten sier ingenting',
            'skipped_heading' => 'Overskriftsnivå hoppet over',
            'table_without_header' => 'Tabell uten overskriftsrad',
            'weak_contrast' => 'Tekstfargen er for svak til å leses',
        ],
        // A link's whole text has to be one of these, so what belongs here is what people
        // write as the entire text of a link and nothing longer.
        'weak_link_phrases' => [
            'her', 'klikk her', 'trykk her', 'klikk', 'denne lenken', 'lenke', 'les mer', 'les her',
            'mer', 'se mer', 'mer info', 'mer informasjon', 'detaljer', 'last ned', 'nedlasting', 'gå',
        ],
    ],

    'autosave' => [
        'found' => 'Et ulagret utkast fra :time ble funnet i denne nettleseren.',
        'restore' => 'Gjenopprett',
        'discard' => 'Forkast',
    ],

    'drag_handle' => [
        'drag' => 'Dra for å flytte denne blokken, klikk for å markere den',
        'insert' => 'Legg til en blokk under',
    ],

    'styles' => [
        'block' => 'Blokk',
        'inline' => 'Tekst',
    ],

    'fonts' => [
        'search' => 'Søk etter skrifter',
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
        'min_words' => ':attribute må ha minst :min ord.',
        'max_words' => ':attribute kan ikke ha mer enn :max ord.',
        'must_contain' => ':attribute må inneholde :content.',

        /*
         * What a node or a mark is called in that sentence. A type with no entry here falls
         * back to its own name, which is what makes a project's own node askable for without
         * an edit to this file.
         */
        'content' => [
            'blockquote' => 'et sitat',
            'bulletList' => 'en punktliste',
            'callout' => 'en infoboks',
            'codeBlock' => 'en kodeblokk',
            'embed' => 'en innebygd video',
            'heading' => 'en overskrift',
            'horizontalRule' => 'en horisontal linje',
            'image' => 'et bilde',
            'link' => 'en lenke',
            'orderedList' => 'en nummerert liste',
            'table' => 'en tabell',
            'taskList' => 'en oppgaveliste',
            'descriptionList' => 'en definisjonsliste',
        ],
    ],
];
