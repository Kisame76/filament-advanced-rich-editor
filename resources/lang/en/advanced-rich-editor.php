<?php

declare(strict_types=1);

return [
    'tools' => [
        'image' => 'Image',
        'link' => [
            'target' => [
                'label' => 'Opens in',
                'self' => 'Same window',
                'blank' => 'New window',
                'parent' => 'Parent frame',
                'top' => 'Top frame',
            ],
            'rel' => [
                'label' => 'Relationship (rel)',
                'new_tab_hint' => 'A link opening in a new window is given noopener and noreferrer automatically.',
                'other' => 'Other rel values',
            ],
            'referrerpolicy' => 'Referrer policy',
            'hreflang' => 'Language of the linked page',
            'id' => 'Anchor',
        ],
        'task_list' => 'Task list',
        'heading_level' => 'Heading :level',
        'font_family' => 'Font',
        'font_family_clear' => 'Default',
        'font_size' => [
            'label' => 'Font size',
            'custom' => 'Custom size',
            'default' => 'Default',
        ],
        'alignment' => 'Alignment',
        'line_height' => [
            'label' => 'Line spacing',
            'single' => 'Single (1.0)',
            'double' => 'Double (2.0)',
            'value' => ':value',
        ],
        'align' => [
            'start' => 'Left',
            'center' => 'Center',
            'end' => 'Right',
            'justify' => 'Justify',
        ],
        'image_lock' => [
            'locked' => 'Aspect ratio locked',
            'unlocked' => 'Aspect ratio unlocked',
        ],
        'paragraph' => 'Paragraph',
        'text_color' => 'Text colour',
        'text_background' => 'Text background',
        'color_clear' => 'No colour',
        'color_custom' => 'Pick a colour',
        'fullscreen' => [
            'enter' => 'Fullscreen',
            'exit' => 'Leave fullscreen',
        ],
        'image_download' => 'Download image',
        'image_delete' => 'Delete image',
        'image_rotate_left' => 'Rotate left',
        'image_rotate_right' => 'Rotate right',
        'image_alt' => [
            'label' => 'Alt text',
            'hint' => 'Leave empty to remove it.',
        ],
        'image_size' => [
            'label' => 'Size',
            'width' => 'Width',
            'height' => 'Height',
            'apply' => 'Apply',
            'reset' => 'Reset',
        ],
        'headings' => 'Headings',
        'lists' => 'Lists',
        'more' => 'More',
        'source_code' => [
            'label' => 'Source code',
            'heading' => 'Source code',
            'description' => 'The HTML as it is stored. Anything the editor cannot hold is dropped when this is applied.',
            'apply' => 'Apply',
        ],
        'emoji' => [
            'label' => 'Emoji',
            'search' => 'Search emoji',
            'empty' => 'Nothing matches that.',
            'empty_recent' => 'The emoji you pick collect here.',
            'close' => 'Close',
            'groups' => [
                'recent' => 'Frequently used',
                'smileys' => 'Smileys & people',
                'nature' => 'Animals & nature',
                'food' => 'Food & drink',
                'activities' => 'Activity',
                'travel' => 'Travel & places',
                'objects' => 'Objects',
                'symbols' => 'Symbols',
                'flags' => 'Flags',
            ],
        ],
        'direction' => [
            'ltr' => 'Left to right',
            'rtl' => 'Right to left',
        ],
    ],

    'help' => [
        'label' => 'Help',
        'heading' => 'Help',
        'shortcuts' => 'Shortcuts',
        'more' => 'More',
        'close' => 'Close',
        'editing' => [
            'line_break' => 'Line break without a new paragraph',
            'indent_list' => 'Indent list item',
            'outdent_list' => 'Outdent list item',
            'next_cell' => 'Next table cell',
        ],
    ],

    'fonts' => [
        'search' => 'Search fonts',
        'system' => 'System',
        'serif' => 'Serif',
        'monospace' => 'Monospace',
    ],

    'character_count' => [
        'characters' => [
            'one' => ':count character',
            'other' => ':count characters',
        ],
        'characters_of_limit' => [
            'one' => ':count / :limit characters',
            'other' => ':count / :limit characters',
        ],
        'words' => [
            'one' => ':count word',
            'other' => ':count words',
        ],
    ],
];
