<?php

declare(strict_types=1);

return [
    'slash' => [
        'groups' => [
            'style' => 'Styl',
            'insert' => 'Wstaw',
        ],
        'empty' => 'Brak pasującego polecenia',
        'aliases' => [
            'paragraph' => 'tekst, akapit, treść',
            'h1' => 'tytuł, tytul, nagłówek 1, naglowek 1',
            'h2' => 'nagłówek 2, naglowek 2, podtytuł, podtytul',
            'h3' => 'nagłówek 3, naglowek 3, śródtytuł',
            'h4' => 'nagłówek 4, naglowek 4',
            'h5' => 'nagłówek 5, naglowek 5',
            'h6' => 'nagłówek 6, naglowek 6',
            'bulletList' => 'ul, lista, punktory, wypunktowanie, punkty',
            'orderedList' => 'ol, numerowana, numeracja, numery',
            'taskList' => 'todo, zadania, zadanie, checklista, checkbox',
            'descriptionList' => 'dl, słowniczek, slowniczek, definicje, pojęcia, glosariusz',
            'blockquote' => 'cytat, cytowanie',
            'calloutNote' => 'uwaga, notatka, info, ramka, callout',
            'calloutTip' => 'wskazówka, porada, rada, ramka, callout',
            'calloutWarning' => 'ostrzeżenie, ostrożnie, alert, ramka, callout',
            'calloutDanger' => 'niebezpieczeństwo, błąd, blad, krytyczne, ramka, callout',
            'codeBlock' => 'kod, code, fragment, snippet',
            'horizontalRule' => 'hr, linia, separator, kreska',
            'details' => 'akordeon, rozwiń, zwiń, rozwijane',
            'embed' => 'wideo, video, youtube, vimeo, osadź, iframe',
            'video' => 'mp4, webm, film, klip, odtwarzacz, media',
            'audio' => 'mp3, dźwięk, muzyka, podcast, odtwarzacz, media',
            'file' => 'plik, dokument, pdf, załącznik, zalacznik, pobierz, word, excel, zip',
            'image' => 'img, obraz, obrazek, zdjęcie, grafika, foto',
            'table' => 'tabela, siatka, wiersze, kolumny',
            'attachFiles' => 'prześlij, wgraj, upload, załącz, zalacz, dolacz',
            'emoji' => 'emotka, emotikona, buźka, smiley, ikona',
            'characters' => 'znak specjalny, symbol, myślnik, strzałka, strzalka, waluta, akcent',
            'insertDate' => 'data, dzisiaj, dzień',
            'insertTime' => 'godzina, czas, zegar, teraz',
            'insertDateTime' => 'data i godzina, znacznik czasu, timestamp, teraz',
            'customBlocks' => 'blok, komponent',
            'mergeTags' => 'tag, zmienna, placeholder, scalanie',
        ],
    ],
    /*
     * The kinds of callout. The key is the variant's own name, so a project that adds one
     * translates it by adding a key here; one without a translation is titled from its
     * name.
     */
    'callouts' => [
        'note' => 'Uwaga',
        'tip' => 'Wskazówka',
        'warning' => 'Ostrzeżenie',
        'danger' => 'Niebezpieczeństwo',
    ],
    'tools' => [
        'image' => 'Obraz',
        // The library button. Named for the library rather than for pictures, because that
        // is what it opens onto and what it will hold more of.
        'media_browser' => 'Media',
        'link' => [
            'target' => [
                'label' => 'Miejsce otwarcia',
                'self' => 'To samo okno',
                'blank' => 'Nowe okno',
                'parent' => 'Ramka nadrzędna',
                'top' => 'Ramka najwyższego poziomu',
            ],
            'rel' => [
                'label' => 'Relacja (rel)',
                'new_tab_hint' => 'Link otwierany w nowym oknie automatycznie dostaje noopener i noreferrer.',
                'other' => 'Inne wartości rel',
            ],
            'referrerpolicy' => 'Polityka referrera',
            'hreflang' => 'Język strony docelowej',
            'id' => 'Kotwica',
            'internal' => [
                'label' => 'Link do rekordu',
                'hint' => 'Wybór wypełnia poniższy adres URL i to on jest zapisywany w linku.',
            ],
        ],
        'code_block' => [
            'plain' => 'Zwykły tekst',
        ],
        'embed' => [
            'label' => 'Wideo',
            'heading' => 'Osadź wideo',
            'url' => 'Link do wideo',
            'url_hint' => 'Wklej link z paska adresu lub z przycisku Udostępnij. Zawarty w nim znacznik czasu zostanie zachowany.',
            'title' => 'Tytuł',
            'title_hint' => 'Odczytywany przez czytnik ekranu zamiast „wideo”.',
            'ratio' => 'Proporcje',
            'unsupported' => 'Ten link nie prowadzi do wideo, które ten edytor potrafi osadzić.',
            'providers' => [
                'youtube' => 'YouTube',
                'vimeo' => 'Vimeo',
            ],
            // The bar over a selected embed.
            'replace' => 'Zastąp wideo',
            'delete' => 'Usuń',
        ],
        /*
         * A file on this server rather than one somebody else hosts. The two buttons open
         * this one dialog, so the strings are shared and `kinds` names both of them.
         */
        'media' => [
            'label' => 'Wideo lub audio',
            'heading' => 'Wideo lub audio z twojego serwera',
            'src' => 'Adres',
            'src_hint' => 'Ścieżka na tym serwerze, np. /storage/clips/wyklad.mp4, albo pełny link https.',
            'kind' => 'Rodzaj',
            'kinds' => [
                'video' => 'Plik wideo',
                'audio' => 'Plik audio',
            ],
            'poster' => 'Obraz podglądu',
            'poster_hint' => 'Widoczny, zanim ktoś naciśnie odtwarzanie. Bez niego wideo jest czarnym prostokątem.',
            'title' => 'Tytuł',
            'title_hint' => 'Odczytywany przez czytnik ekranu zamiast nazwy pliku.',
            'preload' => 'Wczytywanie z wyprzedzeniem',
            'preload_hint' => 'Ile przeglądarka pobiera, zanim ktoś naciśnie odtwarzanie.',
            'preloads' => [
                'none' => 'Nic',
                'metadata' => 'Tylko długość i rozmiar',
                'auto' => 'Tyle, ile zechce przeglądarka',
            ],
            'loop' => 'Odtwarzaj ponownie po zakończeniu',
            'unsupported' => 'Przeglądarka nie odtworzy pliku z tego adresu.',
            // The bar over a selected film or sound.
            'replace' => 'Zastąp plik',
            'download' => 'Pobierz plik',
            'delete' => 'Usuń',
        ],
        /*
         * A document in the text, drawn as a card: the button that opens the browser on the
         * documents, and the bar over a card that is selected.
         */
        'file' => [
            'label' => 'Plik',
            'replace' => 'Zastąp plik',
            'download' => 'Pobierz plik',
            'delete' => 'Usuń',
        ],
        'format_brush' => [
            'label' => 'Malarz formatów',
            'once' => 'Aktywny na jedno użycie',
            'sticky' => 'Aktywny, dopóki go nie wyłączysz',
        ],

        'task_list' => 'Lista zadań',

        'description_list' => 'Lista definicji',
        'callouts' => 'Ramka',
        'language' => 'Język',
        'language_none' => 'Język strony',
        'list_properties' => [
            'bullet' => 'Właściwości listy',
            'ordered' => 'Właściwości listy',
            'marker' => 'Znacznik',
            'marker_default' => 'Domyślny',
            'start' => 'Zacznij od',
            'reversed' => 'Numeruj od końca',
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
                    'disc' => 'Kropka',
                    'circle' => 'Okrąg',
                    'square' => 'Kwadrat',
                ],
            ],
        ],
        'characters' => [
            'label' => 'Znak specjalny',
            'search' => 'Szukaj znaków...',
            'empty' => 'Brak pasującego znaku',
            'empty_recent' => 'Tu pojawią się wybrane przez ciebie znaki.',
            'close' => 'Zamknij',
            'groups' => [
                'recent' => 'Ostatnie',
                'punctuation' => 'Interpunkcja',
                'currency' => 'Waluty',
                'math' => 'Matematyka',
                'arrows' => 'Strzałki',
                'symbols' => 'Symbole',
                'latin' => 'Litery łacińskie',
                'greek' => 'Litery greckie',
            ],
        ],
        'heading_level' => 'Nagłówek :level',
        'styles' => 'Styl',
        'styles_clear' => 'Brak',
        'font_family' => 'Czcionka',
        'font_family_clear' => 'Domyślna',
        'font_size' => [
            'label' => 'Rozmiar czcionki',
            'custom' => 'Własny rozmiar',
            'default' => 'Domyślny',
        ],
        'alignment' => 'Wyrównanie',
        'line_height' => [
            'label' => 'Interlinia',
            'single' => 'Pojedyncza (1,0)',
            'double' => 'Podwójna (2,0)',
            'value' => ':value',
        ],
        /*
         * The date tools. The key is the configured format's own key, so a project that
         * adds a fourth format translates it by adding a key here; one without a
         * translation is labelled with the format itself, rendered as it will read.
         */
        'date_time' => [
            'label' => 'Data i godzina',
            // The configured keys live under their own key rather than beside 'label', so a
            // format keyed `label` cannot resolve to the dropdown's own text.
            'formats' => [
                'date' => 'Data',
                'time' => 'Godzina',
                'date_time' => 'Data i godzina',
            ],
        ],
        'text_case' => [
            'label' => 'Zmień wielkość liter',
            // Each one is set in the case it applies, so the list says what it does without
            // three icons that would all have to mean "the letter A, but differently".
            'sentence' => 'Jak w zdaniu',
            'lower' => 'małe litery',
            'upper' => 'WIELKIE LITERY',
        ],
        'align' => [
            'start' => 'Do lewej',
            'center' => 'Wyśrodkuj',
            'end' => 'Do prawej',
            'justify' => 'Justuj',
        ],
        'image_lock' => [
            'locked' => 'Proporcje zablokowane',
            'unlocked' => 'Proporcje odblokowane',
        ],
        'paragraph' => 'Akapit',
        'text_color' => 'Kolor tekstu',
        'text_background' => 'Tło tekstu',
        'color_clear' => 'Bez koloru',
        'color_custom' => 'Wybierz kolor',
        'fullscreen' => [
            'enter' => 'Pełny ekran',
            'exit' => 'Zamknij pełny ekran',
        ],
        'image_replace' => 'Zastąp obraz',
        'image_edit' => [
            'label' => 'Edytuj obraz',
            'apply' => 'Zastosuj',
            'hint' => 'Użyj ołówka na obrazie, aby go przyciąć, obrócić lub odbić. Oryginalny plik pozostaje bez zmian.',
        ],
        'image_download' => 'Pobierz obraz',
        'image_delete' => 'Usuń obraz',
        'image_float_left' => 'Do lewej, tekst obok',
        'image_float_right' => 'Do prawej, tekst obok',
        'image_float_center' => 'Wyśrodkuj',
        'image_decorative' => 'Obraz dekoracyjny',

        'image_link' => [
            'label' => 'Dodaj link do obrazu',
            'heading' => 'Dokąd prowadzi ten obraz',
            'href' => 'Adres',
            'href_help' => 'Pełny adres albo adres w obrębie tej witryny, np. /artykuly/7. Zostaw puste, aby usunąć link.',
            'new_tab' => 'Otwórz w nowej karcie',
        ],
        'image_rotate_left' => 'Obróć w lewo',
        'image_rotate_right' => 'Obróć w prawo',
        'media_library' => [
            'label' => 'Obraz',
            'heading' => 'Wstaw z biblioteki',
            'search' => 'Szukaj plików...',
            'empty_record' => 'Ten rekord nie ma jeszcze plików. Prześlij plik, aby zacząć.',
            'empty_library' => 'Biblioteka jest pusta. Prześlij plik, aby ją zapełnić.',
            'empty_search' => 'Nic nie pasuje.',
            'up' => 'Folder wyżej',
            'pending' => 'Jeszcze niezapisane',
            'upload' => 'Prześlij',
            'view_grid' => 'Kafelki',
            'view_list' => 'Lista',
            'filter' => 'Filtr',
            'from_url' => 'Z linku',
            'from_url_heading' => 'Dodaj z linku',
            'from_url_label' => 'Link',
            'from_url_hint' => 'Link z YouTube lub Vimeo trafia do twojej biblioteki i można go użyć ponownie. Każdy inny adres — plik na cudzym serwerze — jest wstawiany tak, jak jest, dokument jako karta, i nie jest tu przechowywany.',
            'from_url_hint_files' => 'Plik na cudzym serwerze. Jest wstawiany tak, jak jest, dokument jako karta, a do twojej biblioteki nic nie trafia.',
            'from_url_no_embeds' => 'Filmy z YouTube i Vimeo są wyłączone dla tego pola.',
            'all_types' => 'Wszystkie typy',
            'all_kinds' => 'Wszystkie',
            'kinds' => [
                'image' => 'Obrazy',
                'video' => 'Wideo',
                'audio' => 'Audio',
                'file' => 'Pliki',
                'embed' => 'Osadzone',
            ],
            'sort' => 'Sortuj',
            'previous' => 'Poprzednia strona',
            'next' => 'Następna strona',
            'nothing_selected' => 'Wybierz plik, aby zobaczyć szczegóły.',
            // Put in front of the names of the uploads the browser refused.
            'rejected' => 'Tutaj nieakceptowane:',
            'dismiss' => 'Ukryj',
            'alt' => 'Tekst alternatywny',
            'title' => 'Tytuł',
            'saved' => 'Zapisano',
            'download' => 'Pobierz',
            'play' => 'Odtwórz tutaj',
            'delete' => 'Usuń',
            'deleting' => 'Usuwanie…',
            // The confirmation is Filament's own dialog: a heading naming the file, and under it
            // what follows from it. `:name` is filled in by the browser.
            'delete_heading' => 'Usunąć „:name” na zawsze?',
            'confirm_delete' => 'Wszystko, co nadal do niego prowadzi, przestanie działać.',
            // A library shared across records: what breaks is not on this screen.
            'confirm_delete_shared' => 'Może być także w innych dokumentach, które go stracą.',
            // A new file in the selected one's place, under the same id. `:from` and `:to` are
            // filled in by the browser, which is where both names are known.
            'replace' => 'Zastąp',
            'replacing' => 'Przesyłanie…',
            'replace_heading' => 'Zastąpić „:from” plikiem „:to”?',
            'confirm_replace' => 'Każdy dokument, który go używa, pokaże nowy plik.',
            'replace_refused' => '„:name” nie może go zastąpić. Dozwolone: :accept',
            'replace_failed' => 'Nie udało się zastąpić pliku.',
            // How an entry using a file is named in the question before deleting or replacing it.
            'usage_entry' => ':model #:key „:title”',
            'usage_entry_untitled' => ':model #:key',
            'usage_more' => '+:count więcej',
            // Where the file is used in entries the server found. They are changed too: a
            // deleted file is taken out of them, a replaced one shown in its place.
            'confirm_delete_used' => 'Jest używany w :count wpisach (:entries) i również z nich zostanie usunięty.',
            'confirm_delete_used_one' => 'Jest używany w jednym wpisie (:entries) i również z niego zostanie usunięty.',
            'confirm_replace_used' => 'Jest używany w :count wpisach (:entries), które pokażą nowy plik.',
            'confirm_replace_used_one' => 'Jest używany w jednym wpisie (:entries), który pokaże nowy plik.',
            'deleted_from' => 'Usunięto, także z :count wpisów.',
            'deleted_from_one' => 'Usunięto, także z jednego wpisu.',
            'replaced_in' => 'Zastąpiono i zaktualizowano w :count wpisach.',
            'replaced_in_one' => 'Zastąpiono i zaktualizowano w jednym wpisie.',
            'copy_url' => 'Kopiuj link',
            'copied' => 'Skopiowano',
            'drop' => 'Upuść, aby przesłać',
            'items' => 'elem.',
            'sorts' => [
                'newest' => 'Od najnowszych',
                'oldest' => 'Od najstarszych',
                'name' => 'Nazwa',
                'largest' => 'Od największych',
                'smallest' => 'Od najmniejszych',
            ],
            'details' => [
                'name' => 'Nazwa',
                'size' => 'Rozmiar',
                'dimensions' => 'Wymiary',
                'type' => 'Typ',
                'modified' => 'Zmodyfikowano',
            ],
        ],
        'image_alt' => [
            'label' => 'Tekst',
            'alt' => 'Tekst alternatywny',
            'hint' => 'Zastępuje obraz tam, gdzie nie można go zobaczyć. Zostaw puste, aby go usunąć.',
            'caption' => 'Podpis',
            'caption_hint' => 'Wyświetlany pod obrazem. Tylko dla obrazu w osobnym wierszu.',
        ],
        'image_size' => [
            'label' => 'Rozmiar',
            'width' => 'Szerokość',
            'height' => 'Wysokość',
            'apply' => 'Zastosuj',
            'reset' => 'Zresetuj',
        ],
        'headings' => 'Nagłówki',
        'lists' => 'Listy',
        'more' => 'Więcej',
        'tools_menu' => 'Narzędzia',
        'find' => [
            'label' => 'Znajdź i zamień',
            'find' => 'Znajdź',
            'replace' => 'Zamień na',
            'previous' => 'Poprzednie wystąpienie',
            'next' => 'Następne wystąpienie',
            'replace_one' => 'Zamień',
            'replace_all' => 'Zamień wszystko',
            'close' => 'Zamknij',
            'match_case' => 'Uwzględniaj wielkość liter',
            'whole_word' => 'Tylko całe wyrazy',
            'no_results' => 'Brak wyników',
            // Both numbers sit in one sentence, because where they sit in it is a question
            // about the language and not one the browser should be answering.
            'count' => ':current z :total',
        ],
        'source_code' => [
            'label' => 'Kod źródłowy',
            'heading' => 'Kod źródłowy',
            'description' => 'HTML w takiej postaci, w jakiej jest zapisywany. To, czego edytor nie może przechować, zostanie odrzucone po zastosowaniu.',
            'apply' => 'Zastosuj',
        ],
        'invisible_characters' => [
            'label' => 'Pokaż znaki niedrukowane',
        ],
        'block_outlines' => [
            'label' => 'Pokaż bloki',
        ],
        'emoji' => [
            'label' => 'Emoji',
            'search' => 'Szukaj emoji',
            'empty' => 'Nic nie pasuje.',
            'empty_recent' => 'Tu zbierają się emoji, które wybierasz.',
            'close' => 'Zamknij',
            'groups' => [
                'recent' => 'Często używane',
                'smileys' => 'Buźki i ludzie',
                'nature' => 'Zwierzęta i przyroda',
                'food' => 'Jedzenie i picie',
                'activities' => 'Aktywność',
                'travel' => 'Podróże i miejsca',
                'objects' => 'Przedmioty',
                'symbols' => 'Symbole',
                'flags' => 'Flagi',
            ],
        ],
        'direction' => [
            'ltr' => 'Od lewej do prawej',
            'rtl' => 'Od prawej do lewej',
        ],
        /*
         * The indent pair. Named for the move rather than for a state, because that is
         * what the buttons are: a block is not "indented" the way it is bold, it is one
         * step further in than it was.
         */
        'indent' => [
            'indent' => 'Zwiększ wcięcie',
            'outdent' => 'Zmniejsz wcięcie',
        ],
    ],

    'preview' => [
        'label' => 'Podgląd',
        'heading' => 'Podgląd',
        'description' => 'Dokument tak, jak rysuje go twój własny arkusz stylów. Style edytora nie są tu wczytywane.',
        'frame' => 'Podgląd dokumentu',
    ],

    'statistics' => [
        'label' => 'Statystyki',
        'heading' => 'Statystyki',
        'words' => 'Słowa',
        'characters' => 'Znaki',
        'characters_without_spaces' => 'Znaki bez spacji',
        'paragraphs' => 'Bloki',
        'reading_time' => 'Czas czytania',
        'reading_time_none' => '—',
        'reading_time_under' => 'poniżej minuty',
        'reading_time_minutes' => ':minutes min',
    ],

    'help' => [
        'label' => 'Pomoc',
        'heading' => 'Pomoc',
        'shortcuts' => 'Skróty klawiszowe',
        'more' => 'Więcej',
        'editing' => [
            'line_break' => 'Podział wiersza bez nowego akapitu',
            'paste_plain' => 'Wklej jako zwykły tekst',
            'indent_list' => 'Zwiększ wcięcie elementu listy',
            'outdent_list' => 'Zmniejsz wcięcie elementu listy',
            'indent_block' => 'Zwiększ wcięcie',
            'outdent_block' => 'Zmniejsz wcięcie',
            'change_case' => 'Przełącz wielkość liter zaznaczenia',
            'next_cell' => 'Następna komórka tabeli',
            'find' => 'Znajdź',
            'find_replace' => 'Znajdź i zamień',
        ],
    ],

    'accessibility' => [
        'title' => 'Sprawdzanie dostępności',
        'close' => 'Zamknij',
        'empty' => 'Nie ma nic do zgłoszenia.',
        'ratio' => ':ratio przy wymaganym :needed',
        'rules' => [
            'missing_alt' => 'Obraz bez tekstu alternatywnego',
            'decorative_link' => 'Obraz z linkiem, który nie ma nic do odczytania',
            'empty_link' => 'Link bez tekstu',
            'weak_link_text' => 'Tekst linku nic nie mówi',
            'skipped_heading' => 'Pominięty poziom nagłówka',
            'table_without_header' => 'Tabela bez wiersza nagłówka',
            'weak_contrast' => 'Kolor tekstu zbyt słaby, by dało się go czytać',
        ],
        // A link's whole text has to be one of these, so what belongs here is what people
        // write as the entire text of a link and nothing longer.
        'weak_link_phrases' => [
            'tutaj', 'tu', 'kliknij tutaj', 'kliknij tu', 'kliknij', 'ten link', 'link',
            'czytaj więcej', 'czytaj dalej', 'więcej', 'dowiedz się więcej', 'zobacz więcej',
            'więcej informacji', 'szczegóły', 'pobierz', 'przejdź',
        ],
    ],

    'autosave' => [
        'found' => 'W tej przeglądarce znaleziono niezapisany szkic z :time.',
        'restore' => 'Przywróć',
        'discard' => 'Odrzuć',
    ],

    'drag_handle' => [
        'drag' => 'Przeciągnij, aby przenieść ten blok, kliknij, aby go zaznaczyć',
        'insert' => 'Dodaj blok poniżej',
    ],

    'styles' => [
        'block' => 'Blok',
        'inline' => 'Tekst',
    ],

    'fonts' => [
        'search' => 'Szukaj czcionek',
        'system' => 'Systemowa',
        'serif' => 'Szeryfowa',
        'monospace' => 'O stałej szerokości',
    ],

    'character_count' => [
        'characters' => [
            'one' => 'Znaki: :count',
            'other' => 'Znaki: :count',
        ],
        'characters_of_limit' => [
            'one' => 'Znaki: :count / :limit',
            'other' => 'Znaki: :count / :limit',
        ],
        'words' => [
            'one' => 'Słowa: :count',
            'other' => 'Słowa: :count',
        ],
    ],

    'validation' => [
        'min_words' => 'Liczba słów w polu :attribute musi wynosić co najmniej :min.',
        'max_words' => 'Liczba słów w polu :attribute nie może przekraczać :max.',
        'must_contain' => 'Pole :attribute musi zawierać :content.',

        /*
         * What a node or a mark is called in that sentence. A type with no entry here falls
         * back to its own name, which is what makes a project's own node askable for without
         * an edit to this file.
         */
        'content' => [
            'blockquote' => 'cytat',
            'bulletList' => 'listę punktowaną',
            'callout' => 'ramkę',
            'codeBlock' => 'blok kodu',
            'embed' => 'osadzone wideo',
            'heading' => 'nagłówek',
            'horizontalRule' => 'linię poziomą',
            'image' => 'obraz',
            'link' => 'link',
            'orderedList' => 'listę numerowaną',
            'table' => 'tabelę',
            'taskList' => 'listę zadań',
            'descriptionList' => 'listę definicji',
        ],
    ],
];
