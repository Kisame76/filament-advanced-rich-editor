<?php

declare(strict_types=1);

return [
    'slash' => [
        'groups' => [
            'style' => 'Styl',
            'insert' => 'Vložit',
        ],
        'empty' => 'Žádný odpovídající příkaz',
        'aliases' => [
            'paragraph' => 'text, odstavec, běžný text',
            'h1' => 'nadpis 1, titulek, název',
            'h2' => 'nadpis 2, podnadpis',
            'h3' => 'nadpis 3',
            'h4' => 'nadpis 4',
            'h5' => 'nadpis 5',
            'h6' => 'nadpis 6',
            'bulletList' => 'ul, odrážky, seznam, nečíslovaný',
            'orderedList' => 'ol, číslovaný, číslování, čísla',
            'taskList' => 'todo, úkoly, checklist, zaškrtávání',
            'descriptionList' => 'dl, glosář, definice, pojmy, slovník',
            'blockquote' => 'citace, citát',
            'calloutNote' => 'poznámka, info, infobox, box, callout',
            'calloutTip' => 'tip, rada, doporučení, box, callout',
            'calloutWarning' => 'varování, upozornění, pozor, box, callout',
            'calloutDanger' => 'nebezpečí, chyba, kritické, box, callout',
            'codeBlock' => 'kód, pre, zdrojový kód, snippet',
            'horizontalRule' => 'hr, oddělovač, čára, linka',
            'details' => 'akordeon, rozbalit, sbalit, rozbalovací',
            'embed' => 'video, youtube, vimeo, iframe, vložené video, média',
            'video' => 'mp4, webm, film, klip, přehrávač, média',
            'audio' => 'mp3, zvuk, hudba, podcast, přehrávač, média',
            'file' => 'soubor, dokument, pdf, příloha, stáhnout, word, excel, zip',
            'image' => 'img, obrázek, fotka, foto',
            'table' => 'tabulka, mřížka, řádky, sloupce',
            'attachFiles' => 'nahrát, nahrát obrázek, upload',
            'emoji' => 'smajlík, emotikon, ikona',
            'characters' => 'symbol, speciální znak, znak, pomlčka, šipka, měna, diakritika',
            'insertDate' => 'datum, dnes, den',
            'insertTime' => 'čas, hodiny, teď, nyní, hodina',
            'insertDateTime' => 'datum a čas, časové razítko, teď, nyní',
            'customBlocks' => 'blok, komponenta',
            'mergeTags' => 'značka, proměnná, zástupný symbol',
        ],
    ],
    /*
     * The kinds of callout. The key is the variant's own name, so a project that adds one
     * translates it by adding a key here; one without a translation is titled from its
     * name.
     */
    'callouts' => [
        'note' => 'Poznámka',
        'tip' => 'Tip',
        'warning' => 'Varování',
        'danger' => 'Nebezpečí',
    ],
    'tools' => [
        'image' => 'Obrázek',
        // The library button. Named for the library rather than for pictures, because that
        // is what it opens onto and what it will hold more of.
        'media_browser' => 'Média',
        'link' => [
            'target' => [
                'label' => 'Otevře se v',
                'self' => 'Stejném okně',
                'blank' => 'Novém okně',
                'parent' => 'Nadřazeném rámci',
                'top' => 'Nejvyšším rámci',
            ],
            'rel' => [
                'label' => 'Vztah (rel)',
                'new_tab_hint' => 'Odkaz, který se otevírá v novém okně, automaticky dostane noopener a noreferrer.',
                'other' => 'Další hodnoty rel',
            ],
            'referrerpolicy' => 'Zásady refereru',
            'hreflang' => 'Jazyk cílové stránky',
            'id' => 'Kotva',
            'internal' => [
                'label' => 'Odkaz na záznam',
                'hint' => 'Výběrem se vyplní URL níže, kterou odkaz ukládá.',
            ],
        ],
        'code_block' => [
            'plain' => 'Prostý text',
        ],
        'embed' => [
            'label' => 'Video',
            'heading' => 'Vložit video',
            'url' => 'Odkaz na video',
            'url_hint' => 'Vložte odkaz z adresního řádku nebo z tlačítka Sdílet. Časová značka v něm zůstane zachována.',
            'title' => 'Název',
            'title_hint' => 'Čtečka obrazovky přečte tento název místo slova „video“.',
            'ratio' => 'Poměr stran',
            'unsupported' => 'Tento odkaz není video, které tento editor umí vložit.',
            'providers' => [
                'youtube' => 'YouTube',
                'vimeo' => 'Vimeo',
            ],
            // The bar over a selected embed.
            'replace' => 'Nahradit video',
            'delete' => 'Odstranit',
        ],
        /*
         * A file on this server rather than one somebody else hosts. The two buttons open
         * this one dialog, so the strings are shared and `kinds` names both of them.
         */
        'media' => [
            'label' => 'Video nebo zvuk',
            'heading' => 'Video nebo zvuk z vašeho serveru',
            'src' => 'Adresa',
            'src_hint' => 'Cesta na tomto serveru, např. /storage/clips/prednaska.mp4, nebo úplný odkaz https.',
            'kind' => 'Druh',
            'kinds' => [
                'video' => 'Videosoubor',
                'audio' => 'Zvukový soubor',
            ],
            'poster' => 'Náhledový obrázek',
            'poster_hint' => 'Zobrazí se, než někdo stiskne přehrát. Bez něj je video černý obdélník.',
            'title' => 'Název',
            'title_hint' => 'Čtečka obrazovky přečte tento název místo názvu souboru.',
            'preload' => 'Načítat předem',
            'preload_hint' => 'Kolik toho prohlížeč stáhne, než někdo stiskne přehrát.',
            'preloads' => [
                'none' => 'Nic',
                'metadata' => 'Jen délku a velikost',
                'auto' => 'Kolik bude prohlížeč chtít',
            ],
            'loop' => 'Po skončení přehrát znovu',
            'unsupported' => 'Z této adresy prohlížeč soubor nepřehraje.',
            // The bar over a selected film or sound.
            'replace' => 'Nahradit soubor',
            'download' => 'Stáhnout soubor',
            'delete' => 'Odstranit',
        ],
        /*
         * A document in the text, drawn as a card: the button that opens the browser on the
         * documents, and the bar over a card that is selected.
         */
        'file' => [
            'label' => 'Soubor',
            'replace' => 'Nahradit soubor',
            'download' => 'Stáhnout soubor',
            'delete' => 'Odstranit',
        ],
        'format_brush' => [
            'label' => 'Kopírovat formát',
            'once' => 'Připraveno na jedno použití',
            'sticky' => 'Připraveno, dokud to nevypnete',
        ],

        'task_list' => 'Seznam úkolů',

        'description_list' => 'Seznam definic',
        'callouts' => 'Infobox',
        'language' => 'Jazyk',
        'language_none' => 'Jazyk stránky',
        'list_properties' => [
            'bullet' => 'Vlastnosti seznamu',
            'ordered' => 'Vlastnosti seznamu',
            'marker' => 'Značka',
            'marker_default' => 'Výchozí',
            'start' => 'Začít od',
            'reversed' => 'Počítat pozpátku',
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
                    'disc' => 'Tečka',
                    'circle' => 'Kroužek',
                    'square' => 'Čtverec',
                ],
            ],
        ],
        'characters' => [
            'label' => 'Speciální znak',
            'search' => 'Hledat znaky...',
            'empty' => 'Žádný odpovídající znak',
            'empty_recent' => 'Zde se zobrazí znaky, které vyberete.',
            'close' => 'Zavřít',
            'groups' => [
                'recent' => 'Nedávné',
                'punctuation' => 'Interpunkce',
                'currency' => 'Měny',
                'math' => 'Matematika',
                'arrows' => 'Šipky',
                'symbols' => 'Symboly',
                'latin' => 'Latinská písmena',
                'greek' => 'Řecká písmena',
            ],
        ],
        'heading_level' => 'Nadpis :level',
        'styles' => 'Styl',
        'styles_clear' => 'Žádný',
        'font_family' => 'Písmo',
        'font_family_clear' => 'Výchozí',
        'font_size' => [
            'label' => 'Velikost písma',
            'custom' => 'Vlastní velikost',
            'default' => 'Výchozí',
        ],
        'alignment' => 'Zarovnání',
        'line_height' => [
            'label' => 'Řádkování',
            'single' => 'Jednoduché (1,0)',
            'double' => 'Dvojité (2,0)',
            'value' => ':value',
        ],
        /*
         * The date tools. The key is the configured format's own key, so a project that
         * adds a fourth format translates it by adding a key here; one without a
         * translation is labelled with the format itself, rendered as it will read.
         */
        'date_time' => [
            'label' => 'Datum a čas',
            // The configured keys live under their own key rather than beside 'label', so a
            // format keyed `label` cannot resolve to the dropdown's own text.
            'formats' => [
                'date' => 'Datum',
                'time' => 'Čas',
                'date_time' => 'Datum a čas',
            ],
        ],
        'text_case' => [
            'label' => 'Změnit velikost písmen',
            // Each one is set in the case it applies, so the list says what it does without
            // three icons that would all have to mean "the letter A, but differently".
            'sentence' => 'Jako ve větě',
            'lower' => 'malá písmena',
            'upper' => 'VELKÁ PÍSMENA',
        ],
        'align' => [
            'start' => 'Vlevo',
            'center' => 'Na střed',
            'end' => 'Vpravo',
            'justify' => 'Do bloku',
        ],
        'image_lock' => [
            'locked' => 'Poměr stran uzamčen',
            'unlocked' => 'Poměr stran odemčen',
        ],
        'paragraph' => 'Odstavec',
        'text_color' => 'Barva textu',
        'text_background' => 'Pozadí textu',
        'color_clear' => 'Bez barvy',
        'color_custom' => 'Vybrat barvu',
        'fullscreen' => [
            'enter' => 'Celá obrazovka',
            'exit' => 'Ukončit celou obrazovku',
        ],
        'image_replace' => 'Nahradit obrázek',
        'image_edit' => [
            'label' => 'Upravit obrázek',
            'apply' => 'Použít',
            'hint' => 'Tužkou na obrázku jej oříznete, otočíte nebo překlopíte. Původní soubor zůstane zachován.',
        ],
        'image_download' => 'Stáhnout obrázek',
        'image_delete' => 'Smazat obrázek',
        'image_float_left' => 'Obtékat vlevo',
        'image_float_right' => 'Obtékat vpravo',
        'image_float_center' => 'Na střed',
        'image_decorative' => 'Dekorativní obrázek',

        'image_link' => [
            'label' => 'Odkaz obrázku',
            'heading' => 'Kam tento obrázek vede',
            'href' => 'Adresa',
            'href_help' => 'Úplná adresa nebo adresa v rámci tohoto webu, např. /clanky/7. Když pole necháte prázdné, odkaz se odstraní.',
            'new_tab' => 'Otevřít v nové záložce',
        ],
        'image_rotate_left' => 'Otočit doleva',
        'image_rotate_right' => 'Otočit doprava',
        'media_library' => [
            'label' => 'Obrázek',
            'heading' => 'Vložit z knihovny',
            'search' => 'Hledat soubory...',
            'empty_record' => 'K tomuto záznamu zatím nic nepatří. Začněte nahráním souboru.',
            'empty_library' => 'Knihovna je prázdná. Nahrajte soubor a začněte ji plnit.',
            'empty_search' => 'Tomu nic neodpovídá.',
            'up' => 'O složku výš',
            'pending' => 'Zatím neuloženo',
            'upload' => 'Nahrát',
            'view_grid' => 'Dlaždice',
            'view_list' => 'Seznam',
            'filter' => 'Filtr',
            'from_url' => 'Z odkazu',
            'from_url_heading' => 'Přidat z odkazu',
            'from_url_label' => 'Odkaz',
            'from_url_hint' => 'Odkaz na YouTube nebo Vimeo se přidá do vaší knihovny a můžete ho použít znovu. Jakákoli jiná adresa — soubor na cizím serveru — se vloží tak, jak je, dokument jako karta, a sem se neuloží.',
            'from_url_hint_files' => 'Soubor, který hostuje někdo jiný. Vloží se tak, jak je, dokument jako karta, a do vaší knihovny se nic nepřidá.',
            'from_url_no_embeds' => 'Videa z YouTube a Vimea jsou pro toto pole vypnutá.',
            'all_types' => 'Všechny typy',
            'all_kinds' => 'Vše',
            'kinds' => [
                'image' => 'Obrázky',
                'video' => 'Video',
                'audio' => 'Zvuk',
                'file' => 'Soubory',
                'embed' => 'Vložený obsah',
            ],
            'sort' => 'Řazení',
            'previous' => 'Předchozí stránka',
            'next' => 'Další stránka',
            'nothing_selected' => 'Vyberte soubor, abyste viděli jeho podrobnosti.',
            // Put in front of the names of the uploads the browser refused.
            'rejected' => 'Zde nepřijato:',
            'dismiss' => 'Skrýt',
            'alt' => 'Alternativní text',
            'title' => 'Název',
            'saved' => 'Uloženo',
            'download' => 'Stáhnout',
            'play' => 'Přehrát zde',
            'delete' => 'Smazat',
            'deleting' => 'Mazání…',
            // The confirmation is Filament's own dialog: a heading naming the file, and under it
            // what follows from it. `:name` is filled in by the browser.
            'delete_heading' => 'Trvale smazat „:name“?',
            'confirm_delete' => 'Vše, co na něj ještě odkazuje, přestane fungovat.',
            // A library shared across records: what breaks is not on this screen.
            'confirm_delete_shared' => 'Může být i v jiných dokumentech a ty o něj přijdou.',
            // A new file in the selected one's place, under the same id. `:from` and `:to` are
            // filled in by the browser, which is where both names are known.
            'replace' => 'Nahradit',
            'replacing' => 'Nahrávání…',
            'replace_heading' => 'Nahradit „:from“ souborem „:to“?',
            'confirm_replace' => 'Všechny dokumenty, které ho používají, zobrazí nový soubor.',
            'replace_refused' => '„:name“ ho nemůže nahradit. Povolené typy: :accept',
            'replace_failed' => 'Soubor se nepodařilo nahradit.',
            // How an entry using a file is named in the question before deleting or replacing it.
            'usage_entry' => ':model #:key „:title“',
            'usage_entry_untitled' => ':model #:key',
            'usage_more' => 'a další (:count)',
            // Where the file is used in entries the server found. They are changed too: a
            // deleted file is taken out of them, a replaced one shown in its place.
            'confirm_delete_used' => 'Používá se v :count záznamech (:entries) a bude z nich také odstraněn.',
            'confirm_delete_used_one' => 'Používá se v jednom záznamu (:entries) a bude z něj také odstraněn.',
            'confirm_replace_used' => 'Používá se v :count záznamech (:entries), které zobrazí nový soubor.',
            'confirm_replace_used_one' => 'Používá se v jednom záznamu (:entries), který zobrazí nový soubor.',
            'deleted_from' => 'Smazáno a odstraněno z :count záznamů.',
            'deleted_from_one' => 'Smazáno a odstraněno z jednoho záznamu.',
            'replaced_in' => 'Nahrazeno a aktualizováno v :count záznamech.',
            'replaced_in_one' => 'Nahrazeno a aktualizováno v jednom záznamu.',
            'copy_url' => 'Kopírovat odkaz',
            'copied' => 'Zkopírováno',
            'drop' => 'Pusťte pro nahrání',
            'items' => 'ks',
            'sorts' => [
                'newest' => 'Nejnovější první',
                'oldest' => 'Nejstarší první',
                'name' => 'Název',
                'largest' => 'Největší první',
                'smallest' => 'Nejmenší první',
            ],
            'details' => [
                'name' => 'Název',
                'size' => 'Velikost',
                'dimensions' => 'Rozměry',
                'type' => 'Typ',
                'modified' => 'Změněno',
            ],
        ],
        'image_alt' => [
            'label' => 'Text',
            'alt' => 'Alternativní text',
            'hint' => 'Zastupuje obrázek tam, kde ho nelze vidět. Když pole necháte prázdné, text se odstraní.',
            'caption' => 'Popisek',
            'caption_hint' => 'Zobrazí se pod obrázkem. Jen u obrázku na samostatném řádku.',
        ],
        'image_size' => [
            'label' => 'Velikost',
            'width' => 'Šířka',
            'height' => 'Výška',
            'apply' => 'Použít',
            'reset' => 'Obnovit',
        ],
        'headings' => 'Nadpisy',
        'lists' => 'Seznamy',
        'more' => 'Další',
        'tools_menu' => 'Nástroje',
        'find' => [
            'label' => 'Najít a nahradit',
            'find' => 'Najít',
            'replace' => 'Nahradit čím',
            'previous' => 'Předchozí výskyt',
            'next' => 'Další výskyt',
            'replace_one' => 'Nahradit',
            'replace_all' => 'Nahradit vše',
            'close' => 'Zavřít',
            'match_case' => 'Rozlišovat velká a malá písmena',
            'whole_word' => 'Pouze celá slova',
            'no_results' => 'Žádné výskyty',
            // Both numbers sit in one sentence, because where they sit in it is a question
            // about the language and not one the browser should be answering.
            'count' => ':current z :total',
        ],
        'source_code' => [
            'label' => 'Zdrojový kód',
            'heading' => 'Zdrojový kód',
            'description' => 'HTML v podobě, v jaké se ukládá. Co editor neumí zachovat, se při použití zahodí.',
            'apply' => 'Použít',
        ],
        'invisible_characters' => [
            'label' => 'Zobrazit skryté znaky',
        ],
        'block_outlines' => [
            'label' => 'Zobrazit bloky',
        ],
        'emoji' => [
            'label' => 'Emoji',
            'search' => 'Hledat emoji',
            'empty' => 'Tomu nic neodpovídá.',
            'empty_recent' => 'Zde se shromáždí emoji, které vyberete.',
            'close' => 'Zavřít',
            'groups' => [
                'recent' => 'Často používané',
                'smileys' => 'Smajlíci a lidé',
                'nature' => 'Zvířata a příroda',
                'food' => 'Jídlo a pití',
                'activities' => 'Aktivity',
                'travel' => 'Cestování a místa',
                'objects' => 'Předměty',
                'symbols' => 'Symboly',
                'flags' => 'Vlajky',
            ],
        ],
        'direction' => [
            'ltr' => 'Zleva doprava',
            'rtl' => 'Zprava doleva',
        ],
        /*
         * The indent pair. Named for the move rather than for a state, because that is
         * what the buttons are: a block is not "indented" the way it is bold, it is one
         * step further in than it was.
         */
        'indent' => [
            'indent' => 'Zvětšit odsazení',
            'outdent' => 'Zmenšit odsazení',
        ],
    ],

    'preview' => [
        'label' => 'Náhled',
        'heading' => 'Náhled',
        'description' => 'Dokument tak, jak ho vykresluje vaše vlastní šablona stylů. Styly editoru se zde nenačítají.',
        'frame' => 'Náhled dokumentu',
    ],

    'statistics' => [
        'label' => 'Statistika',
        'heading' => 'Statistika',
        'words' => 'Slova',
        'characters' => 'Znaky',
        'characters_without_spaces' => 'Znaky bez mezer',
        'paragraphs' => 'Bloky',
        'reading_time' => 'Doba čtení',
        'reading_time_none' => '—',
        'reading_time_under' => 'méně než minuta',
        'reading_time_minutes' => ':minutes min',
    ],

    'help' => [
        'label' => 'Nápověda',
        'heading' => 'Nápověda',
        'shortcuts' => 'Klávesové zkratky',
        'more' => 'Další',
        'editing' => [
            'line_break' => 'Zalomení řádku bez nového odstavce',
            'paste_plain' => 'Vložit jako prostý text',
            'indent_list' => 'Odsadit položku seznamu',
            'outdent_list' => 'Zmenšit odsazení položky seznamu',
            'indent_block' => 'Zvětšit odsazení',
            'outdent_block' => 'Zmenšit odsazení',
            'change_case' => 'Přepínat velikost písmen ve výběru',
            'next_cell' => 'Další buňka tabulky',
            'find' => 'Najít',
            'find_replace' => 'Najít a nahradit',
        ],
    ],

    'accessibility' => [
        'title' => 'Kontrola přístupnosti',
        'close' => 'Zavřít',
        'empty' => 'Nic k hlášení.',
        'ratio' => ':ratio z požadovaných :needed',
        'rules' => [
            'missing_alt' => 'Obrázek bez alternativního textu',
            'decorative_link' => 'Obrázek s odkazem bez textu k přečtení',
            'empty_link' => 'Odkaz bez textu',
            'weak_link_text' => 'Text odkazu nic neříká',
            'skipped_heading' => 'Přeskočená úroveň nadpisu',
            'table_without_header' => 'Tabulka bez řádku záhlaví',
            'weak_contrast' => 'Barva textu je příliš slabá na čtení',
        ],
        // A link's whole text has to be one of these, so what belongs here is what people
        // write as the entire text of a link and nothing longer.
        'weak_link_phrases' => [
            'zde', 'sem', 'tady', 'klikněte zde', 'klikněte sem', 'klikni zde', 'klikněte',
            'odkaz', 'tento odkaz', 'více', 'číst dále', 'více informací', 'zjistit více',
            'podrobnosti', 'stáhnout', 'ke stažení',
        ],
    ],

    'autosave' => [
        'found' => 'V tomto prohlížeči byl nalezen neuložený koncept z :time.',
        'restore' => 'Obnovit',
        'discard' => 'Zahodit',
    ],

    'drag_handle' => [
        'drag' => 'Přetažením blok přesunete, kliknutím ho vyberete',
        'insert' => 'Přidat blok níže',
    ],

    'styles' => [
        'block' => 'Blok',
        'inline' => 'Text',
    ],

    'fonts' => [
        'search' => 'Hledat písma',
        'system' => 'Systémové',
        'serif' => 'Patkové',
        'monospace' => 'Neproporcionální',
    ],

    'character_count' => [
        'characters' => [
            'one' => 'Znaky: :count',
            'other' => 'Znaky: :count',
        ],
        'characters_of_limit' => [
            'one' => 'Znaky: :count / :limit',
            'other' => 'Znaky: :count / :limit',
        ],
        'words' => [
            'one' => 'Slova: :count',
            'other' => 'Slova: :count',
        ],
    ],

    'validation' => [
        'min_words' => 'Počet slov v poli :attribute musí být alespoň :min.',
        'max_words' => 'Počet slov v poli :attribute nesmí být vyšší než :max.',
        'must_contain' => 'Pole :attribute musí obsahovat :content.',

        /*
         * What a node or a mark is called in that sentence. A type with no entry here falls
         * back to its own name, which is what makes a project's own node askable for without
         * an edit to this file.
         */
        'content' => [
            'blockquote' => 'citaci',
            'bulletList' => 'seznam s odrážkami',
            'callout' => 'infobox',
            'codeBlock' => 'blok kódu',
            'embed' => 'vložené video',
            'heading' => 'nadpis',
            'horizontalRule' => 'vodorovnou čáru',
            'image' => 'obrázek',
            'link' => 'odkaz',
            'orderedList' => 'číslovaný seznam',
            'table' => 'tabulku',
            'taskList' => 'seznam úkolů',
            'descriptionList' => 'seznam definic',
        ],
    ],
];
