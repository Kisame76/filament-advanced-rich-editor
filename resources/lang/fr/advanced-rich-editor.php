<?php

declare(strict_types=1);

return [
    'slash' => [
        'groups' => [
            'style' => 'Style',
            'insert' => 'Insérer',
        ],
        'empty' => 'Aucune commande correspondante',
        'aliases' => [
            'paragraph' => 'texte, paragraphe, corps',
            'h1' => 'titre, titre 1',
            'h2' => 'titre 2, sous-titre',
            'h3' => 'titre 3',
            'h4' => 'titre 4',
            'h5' => 'titre 5',
            'h6' => 'titre 6',
            'bulletList' => 'ul, puces, liste à puces, tirets',
            'orderedList' => 'ol, numérotée, numérotation, ordonnée',
            'taskList' => 'todo, tâches, cases à cocher, checklist',
            'descriptionList' => 'dl, glossaire, définitions, termes, lexique',
            'blockquote' => 'citation, guillemets',
            'calloutNote' => 'note, info, remarque, encadré, callout',
            'calloutTip' => 'astuce, conseil, suggestion, encadré, callout',
            'calloutWarning' => 'avertissement, attention, alerte, encadré, callout',
            'calloutDanger' => 'danger, erreur, critique, encadré, callout',
            'codeBlock' => 'code, pre, snippet, extrait de code',
            'horizontalRule' => 'hr, séparateur, ligne, trait',
            'details' => 'accordéon, déplier, replier, dérouler',
            'embed' => 'vidéo, youtube, vimeo, iframe, intégrer, média',
            'video' => 'mp4, webm, film, clip, lecteur, média',
            'audio' => 'mp3, son, musique, podcast, lecteur, média',
            'file' => 'fichier, document, pdf, pièce jointe, téléchargement, word, excel, zip',
            'image' => 'img, photo, illustration',
            'table' => 'tableau, grille, lignes, colonnes',
            'attachFiles' => 'téléverser, importer, joindre, envoyer',
            'emoji' => 'smiley, émoticône, icône',
            'characters' => 'symbole, caractère spécial, tiret, flèche, devise, accent',
            'insertDate' => 'date, aujourd\'hui, jour',
            'insertTime' => 'heure, horloge, maintenant',
            'insertDateTime' => 'date et heure, horodatage, maintenant',
            'customBlocks' => 'bloc, composant',
            'mergeTags' => 'balise, tag, variable, fusion',
        ],
    ],
    /*
     * The kinds of callout. The key is the variant's own name, so a project that adds one
     * translates it by adding a key here; one without a translation is titled from its
     * name.
     */
    'callouts' => [
        'note' => 'Note',
        'tip' => 'Astuce',
        'warning' => 'Avertissement',
        'danger' => 'Danger',
    ],
    'tools' => [
        'image' => 'Image',
        // The library button. Named for the library rather than for pictures, because that
        // is what it opens onto and what it will hold more of.
        'media_browser' => 'Médias',
        'link' => [
            'target' => [
                'label' => 'S\'ouvre dans',
                'self' => 'Même fenêtre',
                'blank' => 'Nouvelle fenêtre',
                'parent' => 'Cadre parent',
                'top' => 'Cadre supérieur',
            ],
            'rel' => [
                'label' => 'Relation (rel)',
                'new_tab_hint' => 'Un lien qui s\'ouvre dans une nouvelle fenêtre reçoit automatiquement noopener et noreferrer.',
                'other' => 'Autres valeurs rel',
            ],
            'referrerpolicy' => 'Politique de référent',
            'hreflang' => 'Langue de la page liée',
            'id' => 'Ancre',
            'internal' => [
                'label' => 'Lien vers un enregistrement',
                'hint' => 'En choisir un remplit l\'URL ci-dessous, et c\'est elle que le lien enregistre.',
            ],
        ],
        'code_block' => [
            'plain' => 'Texte brut',
        ],
        'embed' => [
            'label' => 'Vidéo',
            'heading' => 'Intégrer une vidéo',
            'url' => 'Lien de la vidéo',
            'url_hint' => 'Collez le lien de la barre d\'adresse ou du bouton Partager. Le moment de départ qu\'il contient est conservé.',
            'title' => 'Titre',
            'title_hint' => 'Lu par un lecteur d\'écran à la place de « vidéo ».',
            'ratio' => 'Proportions',
            'unsupported' => 'Ce lien n\'est pas une vidéo que cet éditeur peut intégrer.',
            'providers' => [
                'youtube' => 'YouTube',
                'vimeo' => 'Vimeo',
            ],
            // The bar over a selected embed.
            'replace' => 'Remplacer la vidéo',
            'delete' => 'Retirer',
        ],
        /*
         * A file on this server rather than one somebody else hosts. The two buttons open
         * this one dialog, so the strings are shared and `kinds` names both of them.
         */
        'media' => [
            'label' => 'Vidéo ou audio',
            'heading' => 'Vidéo ou audio de votre serveur',
            'src' => 'Adresse',
            'src_hint' => 'Un chemin sur ce serveur, par exemple /storage/clips/conference.mp4, ou un lien https complet.',
            'kind' => 'Type',
            'kinds' => [
                'video' => 'Fichier vidéo',
                'audio' => 'Fichier audio',
            ],
            'poster' => 'Image de couverture',
            'poster_hint' => 'Affichée avant que quelqu\'un lance la lecture. Sans elle, la vidéo est un rectangle noir.',
            'title' => 'Titre',
            'title_hint' => 'Lu par un lecteur d\'écran à la place du nom du fichier.',
            'preload' => 'Préchargement',
            'preload_hint' => 'Ce que le navigateur charge avant que la lecture soit lancée.',
            'preloads' => [
                'none' => 'Rien',
                'metadata' => 'Durée et taille uniquement',
                'auto' => 'Autant que le navigateur le souhaite',
            ],
            'loop' => 'Rejouer à la fin',
            'unsupported' => 'Un navigateur ne lira aucun fichier depuis cette adresse.',
            // The bar over a selected film or sound.
            'replace' => 'Remplacer le fichier',
            'download' => 'Télécharger le fichier',
            'delete' => 'Retirer',
        ],
        /*
         * A document in the text, drawn as a card: the button that opens the browser on the
         * documents, and the bar over a card that is selected.
         */
        'file' => [
            'label' => 'Fichier',
            'replace' => 'Remplacer le fichier',
            'download' => 'Télécharger le fichier',
            'delete' => 'Retirer',
        ],
        'format_brush' => [
            'label' => 'Reproduire la mise en forme',
            'once' => 'Activé pour une seule application',
            'sticky' => 'Activé jusqu\'à ce que vous le désactiviez',
        ],

        'task_list' => 'Liste de tâches',

        'description_list' => 'Liste de définitions',
        'callouts' => 'Encadré',
        'language' => 'Langue',
        'language_none' => 'Langue de la page',
        'list_properties' => [
            'bullet' => 'Propriétés de la liste',
            'ordered' => 'Propriétés de la liste',
            'marker' => 'Marqueur',
            'marker_default' => 'Par défaut',
            'start' => 'Commencer à',
            'reversed' => 'Compter à rebours',
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
                    'disc' => 'Disque',
                    'circle' => 'Cercle',
                    'square' => 'Carré',
                ],
            ],
        ],
        'characters' => [
            'label' => 'Caractère spécial',
            'search' => 'Rechercher des caractères...',
            'empty' => 'Aucun caractère correspondant',
            'empty_recent' => 'Les caractères que vous choisissez apparaissent ici.',
            'close' => 'Fermer',
            'groups' => [
                'recent' => 'Récents',
                'punctuation' => 'Ponctuation',
                'currency' => 'Devises',
                'math' => 'Mathématiques',
                'arrows' => 'Flèches',
                'symbols' => 'Symboles',
                'latin' => 'Lettres latines',
                'greek' => 'Lettres grecques',
            ],
        ],
        'heading_level' => 'Titre :level',
        'styles' => 'Style',
        'styles_clear' => 'Aucun',
        'font_family' => 'Police',
        'font_family_clear' => 'Par défaut',
        'font_size' => [
            'label' => 'Taille de police',
            'custom' => 'Taille personnalisée',
            'default' => 'Par défaut',
        ],
        'alignment' => 'Alignement',
        'line_height' => [
            'label' => 'Interligne',
            'single' => 'Simple (1,0)',
            'double' => 'Double (2,0)',
            'value' => ':value',
        ],
        /*
         * The date tools. The key is the configured format's own key, so a project that
         * adds a fourth format translates it by adding a key here; one without a
         * translation is labelled with the format itself, rendered as it will read.
         */
        'date_time' => [
            'label' => 'Date et heure',
            // The configured keys live under their own key rather than beside 'label', so a
            // format keyed `label` cannot resolve to the dropdown's own text.
            'formats' => [
                'date' => 'Date',
                'time' => 'Heure',
                'date_time' => 'Date et heure',
            ],
        ],
        'text_case' => [
            'label' => 'Changer la casse',
            // Each one is set in the case it applies, so the list says what it does without
            // three icons that would all have to mean "the letter A, but differently".
            'sentence' => 'Majuscule en début de phrase',
            'lower' => 'minuscules',
            'upper' => 'MAJUSCULES',
        ],
        'align' => [
            'start' => 'À gauche',
            'center' => 'Centré',
            'end' => 'À droite',
            'justify' => 'Justifié',
        ],
        'image_lock' => [
            'locked' => 'Proportions verrouillées',
            'unlocked' => 'Proportions déverrouillées',
        ],
        'paragraph' => 'Paragraphe',
        'text_color' => 'Couleur du texte',
        'text_background' => 'Arrière-plan du texte',
        'color_clear' => 'Aucune couleur',
        'color_custom' => 'Choisir une couleur',
        'fullscreen' => [
            'enter' => 'Plein écran',
            'exit' => 'Quitter le plein écran',
        ],
        'image_replace' => 'Remplacer l\'image',
        'image_edit' => [
            'label' => 'Modifier l\'image',
            'apply' => 'Appliquer',
            'hint' => 'Utilisez le crayon sur l\'image pour la recadrer, la faire pivoter ou la retourner. Le fichier d\'origine est conservé.',
        ],
        'image_download' => 'Télécharger l\'image',
        'image_delete' => 'Supprimer l\'image',
        'image_float_left' => 'Flottante à gauche',
        'image_float_right' => 'Flottante à droite',
        'image_float_center' => 'Centrer',
        'image_decorative' => 'Image décorative',

        'image_link' => [
            'label' => 'Lier l\'image',
            'heading' => 'Où mène cette image',
            'href' => 'Adresse',
            'href_help' => 'Une adresse complète, ou une adresse de ce site comme /articles/7. Laissez vide pour supprimer le lien.',
            'new_tab' => 'Ouvrir dans un nouvel onglet',
        ],
        'image_rotate_left' => 'Faire pivoter à gauche',
        'image_rotate_right' => 'Faire pivoter à droite',
        'media_library' => [
            'label' => 'Image',
            'heading' => 'Insérer depuis la médiathèque',
            'search' => 'Rechercher des fichiers...',
            'empty_record' => 'Rien sur cet enregistrement pour l\'instant. Téléversez un fichier pour commencer.',
            'empty_library' => 'La médiathèque est vide. Téléversez un fichier pour la remplir.',
            'empty_search' => 'Rien ne correspond.',
            'up' => 'Dossier parent',
            'pending' => 'Pas encore enregistré',
            'upload' => 'Téléverser',
            'view_grid' => 'Vignettes',
            'view_list' => 'Liste',
            'filter' => 'Filtrer',
            'from_url' => 'Depuis un lien',
            'from_url_heading' => 'Ajouter depuis un lien',
            'from_url_label' => 'Lien',
            'from_url_hint' => 'Un lien YouTube ou Vimeo est ajouté à votre médiathèque et peut être réutilisé. Toute autre adresse — un fichier sur le serveur de quelqu\'un d\'autre — est insérée telle quelle, un document sous forme de carte, et n\'est pas stockée ici.',
            'from_url_hint_files' => 'Un fichier hébergé ailleurs. Il est inséré tel quel, un document sous forme de carte, et rien n\'est ajouté à votre médiathèque.',
            'from_url_no_embeds' => 'Les vidéos YouTube et Vimeo sont désactivées pour ce champ.',
            'all_types' => 'Tous les types',
            'all_kinds' => 'Tout',
            'kinds' => [
                'image' => 'Images',
                'video' => 'Vidéo',
                'audio' => 'Audio',
                'file' => 'Fichiers',
                'embed' => 'Intégrations',
            ],
            'sort' => 'Trier',
            'previous' => 'Page précédente',
            'next' => 'Page suivante',
            'nothing_selected' => 'Choisissez un fichier pour voir ses détails.',
            // Put in front of the names of the uploads the browser refused.
            'rejected' => 'Non acceptés ici :',
            'dismiss' => 'Ignorer',
            'alt' => 'Texte alternatif',
            'title' => 'Titre',
            'saved' => 'Enregistré',
            'download' => 'Télécharger',
            'play' => 'Lire ici',
            'delete' => 'Supprimer',
            'deleting' => 'Suppression…',
            // The confirmation is Filament's own dialog: a heading naming the file, and under it
            // what follows from it. `:name` is filled in by the browser.
            'delete_heading' => 'Supprimer « :name » définitivement ?',
            'confirm_delete' => 'Tout ce qui pointe encore vers lui cessera de fonctionner.',
            // A library shared across records: what breaks is not on this screen.
            'confirm_delete_shared' => 'Il se trouve peut-être aussi dans d\'autres documents, qui le perdront.',
            // A new file in the selected one's place, under the same id. `:from` and `:to` are
            // filled in by the browser, which is where both names are known.
            'replace' => 'Remplacer',
            'replacing' => 'Téléversement…',
            'replace_heading' => 'Remplacer « :from » par « :to » ?',
            'confirm_replace' => 'Chaque document qui l\'utilise affichera le nouveau fichier.',
            'replace_refused' => '« :name » ne peut pas prendre sa place. Types acceptés : :accept',
            'replace_failed' => 'Le fichier n\'a pas pu être remplacé.',
            // How an entry using a file is named in the question before deleting or replacing it.
            'usage_entry' => ':model #:key « :title »',
            'usage_entry_untitled' => ':model #:key',
            'usage_more' => '+:count autres',
            // Where the file is used in entries the server found. They are changed too: a
            // deleted file is taken out of them, a replaced one shown in its place.
            'confirm_delete_used' => 'Il est utilisé dans :count entrées (:entries) et en sera également retiré.',
            'confirm_delete_used_one' => 'Il est utilisé dans une entrée (:entries) et en sera également retiré.',
            'confirm_replace_used' => 'Il est utilisé dans :count entrées (:entries), qui afficheront le nouveau fichier.',
            'confirm_replace_used_one' => 'Il est utilisé dans une entrée (:entries), qui affichera le nouveau fichier.',
            'deleted_from' => 'Supprimé et retiré de :count entrées.',
            'deleted_from_one' => 'Supprimé et retiré d\'une entrée.',
            'replaced_in' => 'Remplacé et mis à jour dans :count entrées.',
            'replaced_in_one' => 'Remplacé et mis à jour dans une entrée.',
            'copy_url' => 'Copier le lien',
            'copied' => 'Copié',
            'drop' => 'Déposez pour téléverser',
            'items' => 'éléments',
            'sorts' => [
                'newest' => 'Plus récents d\'abord',
                'oldest' => 'Plus anciens d\'abord',
                'name' => 'Nom',
                'largest' => 'Plus volumineux d\'abord',
                'smallest' => 'Moins volumineux d\'abord',
            ],
            'details' => [
                'name' => 'Nom',
                'size' => 'Taille',
                'dimensions' => 'Dimensions',
                'type' => 'Type',
                'modified' => 'Modifié',
            ],
        ],
        'image_alt' => [
            'label' => 'Texte',
            'alt' => 'Texte alternatif',
            'hint' => 'Remplace l\'image là où elle ne peut pas être vue. Laissez vide pour le supprimer.',
            'caption' => 'Légende',
            'caption_hint' => 'Affichée sous l\'image. Uniquement pour une image seule sur sa ligne.',
        ],
        'image_size' => [
            'label' => 'Taille',
            'width' => 'Largeur',
            'height' => 'Hauteur',
            'apply' => 'Appliquer',
            'reset' => 'Réinitialiser',
        ],
        'headings' => 'Titres',
        'lists' => 'Listes',
        'more' => 'Plus',
        'tools_menu' => 'Outils',
        'find' => [
            'label' => 'Rechercher et remplacer',
            'find' => 'Rechercher',
            'replace' => 'Remplacer par',
            'previous' => 'Résultat précédent',
            'next' => 'Résultat suivant',
            'replace_one' => 'Remplacer',
            'replace_all' => 'Tout remplacer',
            'close' => 'Fermer',
            'match_case' => 'Respecter la casse',
            'whole_word' => 'Mots entiers uniquement',
            'no_results' => 'Aucun résultat',
            // Both numbers sit in one sentence, because where they sit in it is a question
            // about the language and not one the browser should be answering.
            'count' => ':current sur :total',
        ],
        'source_code' => [
            'label' => 'Code source',
            'heading' => 'Code source',
            'description' => 'Le HTML tel qu\'il est enregistré. Ce que l\'éditeur ne peut pas contenir est supprimé lorsque vous appliquez.',
            'apply' => 'Appliquer',
        ],
        'invisible_characters' => [
            'label' => 'Afficher les caractères invisibles',
        ],
        'block_outlines' => [
            'label' => 'Afficher les blocs',
        ],
        'emoji' => [
            'label' => 'Emoji',
            'search' => 'Rechercher un emoji',
            'empty' => 'Rien ne correspond.',
            'empty_recent' => 'Les emoji que vous choisissez se retrouvent ici.',
            'close' => 'Fermer',
            'groups' => [
                'recent' => 'Fréquemment utilisés',
                'smileys' => 'Smileys et personnes',
                'nature' => 'Animaux et nature',
                'food' => 'Nourriture et boissons',
                'activities' => 'Activités',
                'travel' => 'Voyages et lieux',
                'objects' => 'Objets',
                'symbols' => 'Symboles',
                'flags' => 'Drapeaux',
            ],
        ],
        'direction' => [
            'ltr' => 'De gauche à droite',
            'rtl' => 'De droite à gauche',
        ],
        /*
         * The indent pair. Named for the move rather than for a state, because that is
         * what the buttons are: a block is not "indented" the way it is bold, it is one
         * step further in than it was.
         */
        'indent' => [
            'indent' => 'Augmenter le retrait',
            'outdent' => 'Diminuer le retrait',
        ],
    ],

    'preview' => [
        'label' => 'Aperçu',
        'heading' => 'Aperçu',
        'description' => 'Le document tel que votre propre feuille de style l\'affiche. Les styles de l\'éditeur ne sont pas chargés ici.',
        'frame' => 'Aperçu du document',
    ],

    'statistics' => [
        'label' => 'Statistiques',
        'heading' => 'Statistiques',
        'words' => 'Mots',
        'characters' => 'Caractères',
        'characters_without_spaces' => 'Caractères sans espaces',
        'paragraphs' => 'Blocs',
        'reading_time' => 'Temps de lecture',
        'reading_time_none' => '—',
        'reading_time_under' => 'moins d\'une minute',
        'reading_time_minutes' => ':minutes min',
    ],

    'help' => [
        'label' => 'Aide',
        'heading' => 'Aide',
        'shortcuts' => 'Raccourcis clavier',
        'more' => 'Plus',
        'editing' => [
            'line_break' => 'Saut de ligne sans nouveau paragraphe',
            'paste_plain' => 'Coller en texte brut',
            'indent_list' => 'Augmenter le retrait de l\'élément de liste',
            'outdent_list' => 'Diminuer le retrait de l\'élément de liste',
            'indent_block' => 'Augmenter le retrait',
            'outdent_block' => 'Diminuer le retrait',
            'change_case' => 'Alterner la casse de la sélection',
            'next_cell' => 'Cellule suivante du tableau',
            'find' => 'Rechercher',
            'find_replace' => 'Rechercher et remplacer',
        ],
    ],

    'accessibility' => [
        'title' => 'Vérification de l\'accessibilité',
        'close' => 'Fermer',
        'empty' => 'Rien à signaler.',
        'ratio' => ':ratio sur les :needed requis',
        'rules' => [
            'missing_alt' => 'Image sans texte alternatif',
            'decorative_link' => 'Image avec lien, sans rien à annoncer',
            'empty_link' => 'Lien sans texte',
            'weak_link_text' => 'Texte de lien qui ne dit rien',
            'skipped_heading' => 'Niveau de titre sauté',
            'table_without_header' => 'Tableau sans ligne d\'en-tête',
            'weak_contrast' => 'Couleur du texte trop pâle pour être lue',
        ],
        // A link's whole text has to be one of these, so what belongs here is what people
        // write as the entire text of a link and nothing longer.
        'weak_link_phrases' => [
            'ici', 'cliquez ici', 'cliquer ici', 'cliquez', 'lien', 'ce lien', 'en savoir plus',
            'lire la suite', 'la suite', 'suite', 'plus', 'voir plus', 'plus d\'infos',
            'plus d\'informations', 'détails', 'télécharger',
        ],
    ],

    'autosave' => [
        'found' => 'Un brouillon non enregistré du :time a été trouvé dans ce navigateur.',
        'restore' => 'Le restaurer',
        'discard' => 'L\'abandonner',
    ],

    'drag_handle' => [
        'drag' => 'Faites glisser pour déplacer ce bloc, cliquez pour le sélectionner',
        'insert' => 'Ajouter un bloc en dessous',
    ],

    'styles' => [
        'block' => 'Bloc',
        'inline' => 'Texte',
    ],

    'fonts' => [
        'search' => 'Rechercher une police',
        'system' => 'Système',
        'serif' => 'Serif',
        'monospace' => 'Chasse fixe',
    ],

    'character_count' => [
        'characters' => [
            'one' => ':count caractère',
            'other' => ':count caractères',
        ],
        'characters_of_limit' => [
            'one' => ':count / :limit caractères',
            'other' => ':count / :limit caractères',
        ],
        'words' => [
            'one' => ':count mot',
            'other' => ':count mots',
        ],
    ],

    'validation' => [
        'min_words' => 'Le champ :attribute doit contenir au moins :min mots.',
        'max_words' => 'Le champ :attribute ne doit pas dépasser :max mots.',
        'must_contain' => 'Le champ :attribute doit contenir :content.',

        /*
         * What a node or a mark is called in that sentence. A type with no entry here falls
         * back to its own name, which is what makes a project's own node askable for without
         * an edit to this file.
         */
        'content' => [
            'blockquote' => 'une citation',
            'bulletList' => 'une liste à puces',
            'callout' => 'un encadré',
            'codeBlock' => 'un bloc de code',
            'embed' => 'une vidéo intégrée',
            'heading' => 'un titre',
            'horizontalRule' => 'une ligne horizontale',
            'image' => 'une image',
            'link' => 'un lien',
            'orderedList' => 'une liste numérotée',
            'table' => 'un tableau',
            'taskList' => 'une liste de tâches',
            'descriptionList' => 'une liste de définitions',
        ],
    ],
];
