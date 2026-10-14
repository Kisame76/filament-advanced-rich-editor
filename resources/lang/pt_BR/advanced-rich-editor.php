<?php

declare(strict_types=1);

return [
    'slash' => [
        'groups' => [
            'style' => 'Estilo',
            'insert' => 'Inserir',
        ],
        'empty' => 'Nenhum comando encontrado',
        'aliases' => [
            'paragraph' => 'texto, parágrafo, corpo',
            'h1' => 'título, cabeçalho 1',
            'h2' => 'cabeçalho 2, subtítulo',
            'h3' => 'cabeçalho 3',
            'h4' => 'cabeçalho 4',
            'h5' => 'cabeçalho 5',
            'h6' => 'cabeçalho 6',
            'bulletList' => 'ul, marcadores, tópicos, lista',
            'orderedList' => 'ol, numerada, numeração, ordenada',
            'taskList' => 'todo, tarefas, checklist, caixa de seleção',
            'descriptionList' => 'dl, glossário, definições, termos, dicionário',
            'blockquote' => 'citação, aspas',
            'calloutNote' => 'nota, info, observação, destaque, callout',
            'calloutTip' => 'dica, sugestão, conselho, destaque, callout',
            'calloutWarning' => 'aviso, alerta, atenção, cuidado, destaque, callout',
            'calloutDanger' => 'perigo, erro, crítico, destaque, callout',
            'codeBlock' => 'código, pre, trecho, snippet',
            'horizontalRule' => 'hr, separador, divisor, linha',
            'details' => 'acordeão, sanfona, expandir, recolher',
            'embed' => 'vídeo, youtube, vimeo, iframe, incorporar, mídia',
            'video' => 'mp4, webm, filme, clipe, player, mídia',
            'audio' => 'mp3, som, música, podcast, player, mídia',
            'file' => 'arquivo, documento, pdf, anexo, download, word, excel, zip',
            'image' => 'img, foto, figura',
            'table' => 'tabela, grade, linhas, colunas',
            'attachFiles' => 'enviar, carregar, anexar, upload',
            'emoji' => 'smiley, emoticon, carinha, ícone',
            'characters' => 'símbolo, caractere especial, traço, seta, moeda, acento',
            'insertDate' => 'data, hoje, dia',
            'insertTime' => 'hora, horário, relógio, agora',
            'insertDateTime' => 'data e hora, timestamp, agora',
            'customBlocks' => 'bloco, componente',
            'mergeTags' => 'tag, variável, campo, dinâmica',
        ],
    ],
    /*
     * The kinds of callout. The key is the variant's own name, so a project that adds one
     * translates it by adding a key here; one without a translation is titled from its
     * name.
     */
    'callouts' => [
        'note' => 'Nota',
        'tip' => 'Dica',
        'warning' => 'Aviso',
        'danger' => 'Perigo',
    ],
    'tools' => [
        'image' => 'Imagem',
        // The library button. Named for the library rather than for pictures, because that
        // is what it opens onto and what it will hold more of.
        'media_browser' => 'Mídia',
        'link' => [
            'target' => [
                'label' => 'Abre em',
                'self' => 'Mesma janela',
                'blank' => 'Nova janela',
                'parent' => 'Frame pai',
                'top' => 'Frame superior',
            ],
            'rel' => [
                'label' => 'Relação (rel)',
                'new_tab_hint' => 'Um link que abre em uma nova janela recebe noopener e noreferrer automaticamente.',
                'other' => 'Outros valores de rel',
            ],
            'referrerpolicy' => 'Política de referência',
            'hreflang' => 'Idioma da página vinculada',
            'id' => 'Âncora',
            'internal' => [
                'label' => 'Link para um registro',
                'hint' => 'Escolher um preenche a URL abaixo, que é o que o link armazena.',
            ],
        ],
        'code_block' => [
            'plain' => 'Texto simples',
        ],
        'embed' => [
            'label' => 'Vídeo',
            'heading' => 'Incorporar um vídeo',
            'url' => 'Link do vídeo',
            'url_hint' => 'Cole o link da barra de endereço ou do botão Compartilhar. O tempo de início que ele tiver é mantido.',
            'title' => 'Título',
            'title_hint' => 'Lido por um leitor de tela no lugar de “vídeo”.',
            'ratio' => 'Proporção',
            'unsupported' => 'Esse link não é um vídeo que este editor consiga incorporar.',
            'providers' => [
                'youtube' => 'YouTube',
                'vimeo' => 'Vimeo',
            ],
            // The bar over a selected embed.
            'replace' => 'Substituir vídeo',
            'delete' => 'Remover',
        ],
        /*
         * A file on this server rather than one somebody else hosts. The two buttons open
         * this one dialog, so the strings are shared and `kinds` names both of them.
         */
        'media' => [
            'label' => 'Vídeo ou áudio',
            'heading' => 'Vídeo ou áudio do seu servidor',
            'src' => 'Endereço',
            'src_hint' => 'Um caminho neste servidor, como /storage/clips/palestra.mp4, ou um link https completo.',
            'kind' => 'Tipo',
            'kinds' => [
                'video' => 'Arquivo de vídeo',
                'audio' => 'Arquivo de áudio',
            ],
            'poster' => 'Imagem de capa',
            'poster_hint' => 'Exibida antes de alguém dar play. Sem ela, o vídeo é um retângulo preto.',
            'title' => 'Título',
            'title_hint' => 'Lido por um leitor de tela no lugar do nome do arquivo.',
            'preload' => 'Pré-carregamento',
            'preload_hint' => 'Quanto o navegador baixa antes de alguém dar play.',
            'preloads' => [
                'none' => 'Nada',
                'metadata' => 'Só duração e tamanho',
                'auto' => 'O quanto o navegador quiser',
            ],
            'loop' => 'Repetir ao terminar',
            'unsupported' => 'Um navegador não reproduz arquivos desse endereço.',
            // The bar over a selected film or sound.
            'replace' => 'Substituir arquivo',
            'download' => 'Baixar arquivo',
            'delete' => 'Remover',
        ],
        /*
         * A document in the text, drawn as a card: the button that opens the browser on the
         * documents, and the bar over a card that is selected.
         */
        'file' => [
            'label' => 'Arquivo',
            'replace' => 'Substituir arquivo',
            'download' => 'Baixar arquivo',
            'delete' => 'Remover',
        ],
        'format_brush' => [
            'label' => 'Pincel de formatação',
            'once' => 'Ativo para uma única aplicação',
            'sticky' => 'Ativo até você desativá-lo',
        ],

        'task_list' => 'Lista de tarefas',

        'description_list' => 'Lista de definições',
        'callouts' => 'Caixa de destaque',
        'language' => 'Idioma',
        'language_none' => 'Idioma da página',
        'list_properties' => [
            'bullet' => 'Propriedades da lista',
            'ordered' => 'Propriedades da lista',
            'marker' => 'Marcador',
            'marker_default' => 'Padrão',
            'start' => 'Começar em',
            'reversed' => 'Contar de trás para frente',
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
                    'disc' => 'Disco',
                    'circle' => 'Círculo',
                    'square' => 'Quadrado',
                ],
            ],
        ],
        'characters' => [
            'label' => 'Caractere especial',
            'search' => 'Pesquisar caracteres...',
            'empty' => 'Nenhum caractere encontrado',
            'empty_recent' => 'Os caracteres que você escolher aparecem aqui.',
            'close' => 'Fechar',
            'groups' => [
                'recent' => 'Recentes',
                'punctuation' => 'Pontuação',
                'currency' => 'Moedas',
                'math' => 'Matemática',
                'arrows' => 'Setas',
                'symbols' => 'Símbolos',
                'latin' => 'Letras latinas',
                'greek' => 'Letras gregas',
            ],
        ],
        'heading_level' => 'Título :level',
        'styles' => 'Estilo',
        'styles_clear' => 'Nenhum',
        'font_family' => 'Fonte',
        'font_family_clear' => 'Padrão',
        'font_size' => [
            'label' => 'Tamanho da fonte',
            'custom' => 'Tamanho personalizado',
            'default' => 'Padrão',
        ],
        'alignment' => 'Alinhamento',
        'line_height' => [
            'label' => 'Espaçamento entre linhas',
            'single' => 'Simples (1,0)',
            'double' => 'Duplo (2,0)',
            'value' => ':value',
        ],
        /*
         * The date tools. The key is the configured format's own key, so a project that
         * adds a fourth format translates it by adding a key here; one without a
         * translation is labelled with the format itself, rendered as it will read.
         */
        'date_time' => [
            'label' => 'Data e hora',
            // The configured keys live under their own key rather than beside 'label', so a
            // format keyed `label` cannot resolve to the dropdown's own text.
            'formats' => [
                'date' => 'Data',
                'time' => 'Hora',
                'date_time' => 'Data e hora',
            ],
        ],
        'text_case' => [
            'label' => 'Alterar maiúsculas e minúsculas',
            // Each one is set in the case it applies, so the list says what it does without
            // three icons that would all have to mean "the letter A, but differently".
            'sentence' => 'Primeira letra da frase em maiúscula',
            'lower' => 'minúsculas',
            'upper' => 'MAIÚSCULAS',
        ],
        'align' => [
            'start' => 'Esquerda',
            'center' => 'Centro',
            'end' => 'Direita',
            'justify' => 'Justificado',
        ],
        'image_lock' => [
            'locked' => 'Proporção bloqueada',
            'unlocked' => 'Proporção desbloqueada',
        ],
        'paragraph' => 'Parágrafo',
        'text_color' => 'Cor do texto',
        'text_background' => 'Fundo do texto',
        'color_clear' => 'Sem cor',
        'color_custom' => 'Escolher uma cor',
        'fullscreen' => [
            'enter' => 'Tela cheia',
            'exit' => 'Sair da tela cheia',
        ],
        'image_replace' => 'Substituir imagem',
        'image_download' => 'Baixar imagem',
        'image_delete' => 'Excluir imagem',
        'image_float_left' => 'Flutuar à esquerda',
        'image_float_right' => 'Flutuar à direita',
        'image_float_center' => 'Centralizar',
        'image_decorative' => 'Imagem decorativa',

        'image_link' => [
            'label' => 'Vincular imagem',
            'heading' => 'Para onde esta imagem aponta',
            'href' => 'Endereço',
            'href_help' => 'Um endereço completo, ou um deste site, como /artigos/7. Deixe em branco para remover o link.',
            'new_tab' => 'Abrir em uma nova aba',
        ],
        'image_rotate_left' => 'Rotacionar para a esquerda',
        'image_rotate_right' => 'Rotacionar para a direita',
        'media_library' => [
            'label' => 'Imagem',
            'heading' => 'Inserir da biblioteca',
            'search' => 'Pesquisar arquivos...',
            'empty_record' => 'Ainda não há nada neste registro. Envie um arquivo para começar.',
            'empty_library' => 'A biblioteca está vazia. Envie um arquivo para começar.',
            'empty_search' => 'Nada encontrado.',
            'up' => 'Pasta superior',
            'pending' => 'Ainda não salvo',
            'upload' => 'Enviar',
            'view_grid' => 'Grade',
            'view_list' => 'Lista',
            'filter' => 'Filtrar',
            'from_url' => 'De um link',
            'from_url_heading' => 'Adicionar de um link',
            'from_url_label' => 'Link',
            'from_url_hint' => 'Um link do YouTube ou do Vimeo é adicionado à sua biblioteca e pode ser usado de novo. Qualquer outro endereço — um arquivo no servidor de outra pessoa — é inserido como está, um documento como cartão, e não é armazenado aqui.',
            'from_url_hint_files' => 'Um arquivo hospedado por outra pessoa. Ele é inserido como está, um documento como cartão, e nada é adicionado à sua biblioteca.',
            'from_url_no_embeds' => 'Vídeos do YouTube e do Vimeo estão desativados para este campo.',
            'all_types' => 'Todos os tipos',
            'all_kinds' => 'Todos',
            'kinds' => [
                'image' => 'Imagens',
                'video' => 'Vídeo',
                'audio' => 'Áudio',
                'file' => 'Arquivos',
                'embed' => 'Incorporados',
            ],
            'sort' => 'Ordenar',
            'previous' => 'Página anterior',
            'next' => 'Próxima página',
            'nothing_selected' => 'Escolha um arquivo para ver os detalhes.',
            // Put in front of the names of the uploads the browser refused.
            'rejected' => 'Não aceitos aqui:',
            'dismiss' => 'Dispensar',
            'alt' => 'Texto alternativo',
            'title' => 'Título',
            'saved' => 'Salvo',
            'download' => 'Baixar',
            'play' => 'Reproduzir aqui',
            'delete' => 'Excluir',
            'deleting' => 'Excluindo…',
            // The confirmation is Filament's own dialog: a heading naming the file, and under it
            // what follows from it. `:name` is filled in by the browser.
            'delete_heading' => 'Excluir “:name” definitivamente?',
            'confirm_delete' => 'Tudo o que ainda aponta para ele vai deixar de funcionar.',
            // A library shared across records: what breaks is not on this screen.
            'confirm_delete_shared' => 'Ele pode estar em outros documentos também, e eles vão perdê-lo.',
            // A new file in the selected one's place, under the same id. `:from` and `:to` are
            // filled in by the browser, which is where both names are known.
            'replace' => 'Substituir',
            'replacing' => 'Enviando…',
            'replace_heading' => 'Substituir “:from” por “:to”?',
            'confirm_replace' => 'Todos os documentos que o usam vão mostrar o novo arquivo.',
            'replace_refused' => '“:name” não pode ocupar o lugar dele. Aceita: :accept',
            'replace_failed' => 'Não foi possível substituir o arquivo.',
            // How an entry using a file is named in the question before deleting or replacing it.
            'usage_entry' => ':model #:key “:title”',
            'usage_entry_untitled' => ':model #:key',
            'usage_more' => '+:count mais',
            // Where the file is used in entries the server found. They are changed too: a
            // deleted file is taken out of them, a replaced one shown in its place.
            'confirm_delete_used' => 'O arquivo é usado em :count entradas (:entries) e também será removido delas.',
            'confirm_delete_used_one' => 'O arquivo é usado em uma entrada (:entries) e também será removido dela.',
            'confirm_replace_used' => 'O arquivo é usado em :count entradas (:entries), que vão mostrar o novo arquivo.',
            'confirm_replace_used_one' => 'O arquivo é usado em uma entrada (:entries), que vai mostrar o novo arquivo.',
            'deleted_from' => 'Excluído e removido de :count entradas.',
            'deleted_from_one' => 'Excluído e removido de uma entrada.',
            'replaced_in' => 'Substituído e atualizado em :count entradas.',
            'replaced_in_one' => 'Substituído e atualizado em uma entrada.',
            'copy_url' => 'Copiar link',
            'copied' => 'Copiado',
            'drop' => 'Solte para enviar',
            'items' => 'itens',
            'sorts' => [
                'newest' => 'Mais recentes primeiro',
                'oldest' => 'Mais antigos primeiro',
                'name' => 'Nome',
                'largest' => 'Maiores primeiro',
                'smallest' => 'Menores primeiro',
            ],
            'details' => [
                'name' => 'Nome',
                'size' => 'Tamanho',
                'dimensions' => 'Dimensões',
                'type' => 'Tipo',
                'modified' => 'Modificado',
            ],
        ],
        'image_alt' => [
            'label' => 'Texto',
            'alt' => 'Texto alternativo',
            'hint' => 'Substitui a imagem onde ela não pode ser vista. Deixe em branco para removê-lo.',
            'caption' => 'Legenda',
            'caption_hint' => 'Exibida abaixo da imagem. Só para uma imagem sozinha em sua linha.',
        ],
        'image_size' => [
            'label' => 'Tamanho',
            'width' => 'Largura',
            'height' => 'Altura',
            'apply' => 'Aplicar',
            'reset' => 'Limpar',
        ],
        'headings' => 'Títulos',
        'lists' => 'Listas',
        'more' => 'Mais',
        'tools_menu' => 'Ferramentas',
        'find' => [
            'label' => 'Localizar e substituir',
            'find' => 'Localizar',
            'replace' => 'Substituir por',
            'previous' => 'Resultado anterior',
            'next' => 'Próximo resultado',
            'replace_one' => 'Substituir',
            'replace_all' => 'Substituir tudo',
            'close' => 'Fechar',
            'match_case' => 'Diferenciar maiúsculas e minúsculas',
            'whole_word' => 'Somente palavras inteiras',
            'no_results' => 'Nenhum resultado',
            // Both numbers sit in one sentence, because where they sit in it is a question
            // about the language and not one the browser should be answering.
            'count' => ':current de :total',
        ],
        'source_code' => [
            'label' => 'Código-fonte',
            'heading' => 'Código-fonte',
            'description' => 'O HTML como ele é armazenado. O que o editor não consegue manter é descartado ao aplicar.',
            'apply' => 'Aplicar',
        ],
        'invisible_characters' => [
            'label' => 'Mostrar caracteres invisíveis',
        ],
        'block_outlines' => [
            'label' => 'Mostrar blocos',
        ],
        'emoji' => [
            'label' => 'Emoji',
            'search' => 'Pesquisar emoji',
            'empty' => 'Nada encontrado.',
            'empty_recent' => 'Os emojis que você escolher ficam aqui.',
            'close' => 'Fechar',
            'groups' => [
                'recent' => 'Usados com frequência',
                'smileys' => 'Smileys e pessoas',
                'nature' => 'Animais e natureza',
                'food' => 'Comidas e bebidas',
                'activities' => 'Atividades',
                'travel' => 'Viagens e lugares',
                'objects' => 'Objetos',
                'symbols' => 'Símbolos',
                'flags' => 'Bandeiras',
            ],
        ],
        'direction' => [
            'ltr' => 'Da esquerda para a direita',
            'rtl' => 'Da direita para a esquerda',
        ],
        /*
         * The indent pair. Named for the move rather than for a state, because that is
         * what the buttons are: a block is not "indented" the way it is bold, it is one
         * step further in than it was.
         */
        'indent' => [
            'indent' => 'Aumentar recuo',
            'outdent' => 'Diminuir recuo',
        ],
    ],

    'preview' => [
        'label' => 'Pré-visualização',
        'heading' => 'Pré-visualização',
        'description' => 'O documento como a sua própria folha de estilos o desenha. Os estilos do editor não são carregados aqui.',
        'frame' => 'Pré-visualização do documento',
    ],

    'statistics' => [
        'label' => 'Estatísticas',
        'heading' => 'Estatísticas',
        'words' => 'Palavras',
        'characters' => 'Caracteres',
        'characters_without_spaces' => 'Caracteres sem espaços',
        'paragraphs' => 'Blocos',
        'reading_time' => 'Tempo de leitura',
        'reading_time_none' => '—',
        'reading_time_under' => 'menos de um minuto',
        'reading_time_minutes' => ':minutes min',
    ],

    'help' => [
        'label' => 'Ajuda',
        'heading' => 'Ajuda',
        'shortcuts' => 'Atalhos',
        'more' => 'Mais',
        'editing' => [
            'line_break' => 'Quebra de linha sem novo parágrafo',
            'paste_plain' => 'Colar como texto simples',
            'indent_list' => 'Aumentar o recuo do item da lista',
            'outdent_list' => 'Diminuir o recuo do item da lista',
            'indent_block' => 'Aumentar recuo',
            'outdent_block' => 'Diminuir recuo',
            'change_case' => 'Alternar maiúsculas e minúsculas na seleção',
            'next_cell' => 'Próxima célula da tabela',
            'find' => 'Localizar',
            'find_replace' => 'Localizar e substituir',
        ],
    ],

    'accessibility' => [
        'title' => 'Verificação de acessibilidade',
        'close' => 'Fechar',
        'empty' => 'Nada a relatar.',
        'ratio' => ':ratio dos :needed necessários',
        'rules' => [
            'missing_alt' => 'Imagem sem texto alternativo',
            'decorative_link' => 'Imagem com link sem nada para anunciar',
            'empty_link' => 'Link sem texto',
            'weak_link_text' => 'Texto do link não diz nada',
            'skipped_heading' => 'Nível de título pulado',
            'table_without_header' => 'Tabela sem linha de cabeçalho',
            'weak_contrast' => 'Cor do texto fraca demais para ler',
        ],
        // A link's whole text has to be one of these, so what belongs here is what people
        // write as the entire text of a link and nothing longer.
        'weak_link_phrases' => [
            'aqui', 'clique aqui', 'clique', 'acesse', 'acesse aqui', 'este link', 'link',
            'saiba mais', 'leia mais', 'mais', 'veja mais', 'ver mais', 'mais informações',
            'detalhes', 'baixar', 'download',
        ],
    ],

    'autosave' => [
        'found' => 'Um rascunho não salvo de :time foi encontrado neste navegador.',
        'restore' => 'Restaurar',
        'discard' => 'Descartar',
    ],

    'drag_handle' => [
        'drag' => 'Arraste para mover este bloco, clique para selecioná-lo',
        'insert' => 'Adicionar um bloco abaixo',
    ],

    'styles' => [
        'block' => 'Bloco',
        'inline' => 'Texto',
    ],

    'fonts' => [
        'search' => 'Pesquisar fontes',
        'system' => 'Sistema',
        'serif' => 'Serifada',
        'monospace' => 'Monoespaçada',
    ],

    'character_count' => [
        'characters' => [
            'one' => ':count caractere',
            'other' => ':count caracteres',
        ],
        'characters_of_limit' => [
            'one' => ':count / :limit caracteres',
            'other' => ':count / :limit caracteres',
        ],
        'words' => [
            'one' => ':count palavra',
            'other' => ':count palavras',
        ],
    ],

    'validation' => [
        'min_words' => 'O campo :attribute deve ter pelo menos :min palavras.',
        'max_words' => 'O campo :attribute não pode ter mais de :max palavras.',
        'must_contain' => 'O campo :attribute deve conter :content.',

        /*
         * What a node or a mark is called in that sentence. A type with no entry here falls
         * back to its own name, which is what makes a project's own node askable for without
         * an edit to this file.
         */
        'content' => [
            'blockquote' => 'uma citação',
            'bulletList' => 'uma lista com marcadores',
            'callout' => 'uma caixa de destaque',
            'codeBlock' => 'um bloco de código',
            'embed' => 'um vídeo incorporado',
            'heading' => 'um título',
            'horizontalRule' => 'uma linha horizontal',
            'image' => 'uma imagem',
            'link' => 'um link',
            'orderedList' => 'uma lista numerada',
            'table' => 'uma tabela',
            'taskList' => 'uma lista de tarefas',
            'descriptionList' => 'uma lista de definições',
        ],
    ],
];
