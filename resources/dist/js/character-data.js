/*
 * The special characters list, in one file so the picker itself stays readable.
 *
 * Not a dump of a Unicode block. What is here is what an editor reaches a picker for: the
 * dashes and quotation marks a keyboard cannot type, the currencies, the mathematics an
 * article uses rather than the mathematics a paper does, the arrows, the marks a document
 * is annotated with, and the accented and Greek letters somebody quoting a foreign word
 * needs one of.
 *
 * The names are Unicode's own where they are readable and shortened where they are not
 * ("EN DASH" rather than "EN DASH (U+2013)"), plus the words somebody would actually type
 * to find the thing - a reader looking for « is looking for "quote", not for "guillemet".
 * The names are what the search matches on, and they are English throughout, exactly like
 * the emoji list: a Unicode name is an English name, and translating this list would mean
 * translating that one too.
 *
 * Shape: [[group, [[character, name], ...]], ...] - the same as `emoji-data.js`, because
 * `glyph-picker.js` paints both.
 */
export default [
    ['punctuation', [
        ["–", "en dash"], ["—", "em dash"], ["‐", "hyphen"], ["…", "ellipsis dots"],
        ["“", "left double quote"], ["”", "right double quote"],
        ["„", "double low quote german"], ["‟", "double high reversed quote"],
        ["‘", "left single quote"], ["’", "right single quote apostrophe"],
        ["‚", "single low quote german"], ["«", "left double angle quote guillemet"],
        ["»", "right double angle quote guillemet"], ["‹", "left single angle quote"],
        ["›", "right single angle quote"],
        // The one invisible character worth offering, and the reason a row may carry a
        // third element: a blank button is a button nobody can aim at, so this one is
        // drawn as the open box every editor uses to stand in for a space. German
        // typography needs it several times a page - "10 %", "Nr. 5", "S. 12". The narrow
        // one and the zero-width space are left out: three invisible characters that all
        // draw the same stand-in are three buttons nobody can tell apart.
        ["\u00a0", "non-breaking space", "␣"],
        ["·", "middle dot interpunct"], ["•", "bullet"], ["‣", "triangular bullet"],
        ["†", "dagger"], ["‡", "double dagger"], ["§", "section sign"], ["¶", "pilcrow paragraph"],
        ["¡", "inverted exclamation mark"], ["¿", "inverted question mark"],
        ["‰", "per mille"], ["‱", "per ten thousand"], ["′", "prime minutes feet"],
        ["″", "double prime seconds inches"], ["№", "numero sign"], ["℮", "estimated sign"],
    ]],
    ['currency', [
        ["€", "euro"], ["$", "dollar"], ["£", "pound sterling"], ["¥", "yen yuan"],
        ["¢", "cent"], ["₹", "indian rupee"], ["₽", "russian ruble"], ["₺", "turkish lira"],
        ["₩", "won"], ["₪", "new shekel"], ["₫", "dong"], ["₴", "hryvnia"], ["₦", "naira"],
        ["₱", "peso"], ["₡", "colon currency"], ["₲", "guarani"], ["₸", "tenge"],
        ["₼", "manat"], ["₾", "lari"], ["¤", "generic currency sign"], ["ƒ", "florin"],
        ["฿", "baht"], ["₿", "bitcoin"],
    ]],
    ['math', [
        ["×", "multiplication times"], ["÷", "division"], ["±", "plus minus"],
        ["∓", "minus plus"], ["−", "minus sign"], ["≈", "almost equal approximately"],
        ["≠", "not equal"], ["≡", "identical to"], ["≤", "less than or equal"],
        ["≥", "greater than or equal"], ["¬", "not sign"], ["∞", "infinity"],
        ["∑", "sum sigma"], ["∏", "product pi"], ["√", "square root"], ["∛", "cube root"],
        ["∫", "integral"], ["∂", "partial differential"], ["∆", "increment delta"],
        ["∇", "nabla del"], ["∈", "element of"], ["∉", "not an element of"],
        ["⊂", "subset of"], ["⊃", "superset of"], ["∪", "union"], ["∩", "intersection"],
        ["∅", "empty set"], ["∀", "for all"], ["∃", "there exists"], ["∴", "therefore"],
        ["∵", "because"], ["∝", "proportional to"], ["∠", "angle"], ["⊥", "perpendicular"],
        ["∥", "parallel to"], ["°", "degree"], ["µ", "micro sign"],
        ["½", "one half"], ["⅓", "one third"], ["⅔", "two thirds"], ["¼", "one quarter"],
        ["¾", "three quarters"], ["⅛", "one eighth"], ["⅜", "three eighths"],
        ["⅝", "five eighths"], ["⅞", "seven eighths"], ["¹", "superscript one"],
        ["²", "superscript two squared"], ["³", "superscript three cubed"],
        ["ⁿ", "superscript n"], ["₀", "subscript zero"], ["₁", "subscript one"],
        ["₂", "subscript two"], ["₃", "subscript three"],
    ]],
    ['arrows', [
        ["←", "leftwards arrow"], ["→", "rightwards arrow"], ["↑", "upwards arrow"],
        ["↓", "downwards arrow"], ["↔", "left right arrow"], ["↕", "up down arrow"],
        ["↖", "north west arrow"], ["↗", "north east arrow"], ["↘", "south east arrow"],
        ["↙", "south west arrow"], ["⇐", "leftwards double arrow"],
        ["⇒", "rightwards double arrow implies"], ["⇑", "upwards double arrow"],
        ["⇓", "downwards double arrow"], ["⇔", "left right double arrow if and only if"],
        ["↵", "carriage return arrow"], ["↩", "leftwards arrow with hook"],
        ["↪", "rightwards arrow with hook"], ["⟵", "long leftwards arrow"],
        ["⟶", "long rightwards arrow"], ["➔", "heavy rightwards arrow"],
        ["▲", "up triangle"], ["▼", "down triangle"], ["◀", "left triangle"],
        ["▶", "right triangle"], ["⌃", "up caret control"], ["⌄", "down caret"],
    ]],
    ['symbols', [
        ["©", "copyright"], ["®", "registered trademark"], ["™", "trademark"],
        ["℠", "service mark"], ["℗", "sound recording copyright"], ["★", "black star"],
        ["☆", "white star"], ["♥", "heart suit"], ["♦", "diamond suit"],
        ["♣", "club suit"], ["♠", "spade suit"], ["♪", "eighth note"],
        ["♫", "beamed notes"], ["♭", "flat music"], ["♯", "sharp music"],
        ["☐", "ballot box empty checkbox"], ["☑", "ballot box with check"],
        ["☒", "ballot box with x"], ["✓", "check mark tick"], ["✔", "heavy check mark"],
        ["✗", "ballot x cross"], ["✘", "heavy ballot x"], ["⚠", "warning sign"],
        ["☎", "telephone"], ["✉", "envelope mail"], ["✂", "scissors"],
        ["✍", "writing hand"], ["⌘", "command key mac"], ["⌥", "option alt key mac"],
        ["⇧", "shift key"], ["⌫", "delete backspace key"], ["⏎", "return enter key"],
        ["⇥", "tab key"], ["␣", "space key"], ["⚑", "flag"], ["☜", "pointing left"],
        ["☞", "pointing right"], ["✱", "asterisk operator"], ["◊", "lozenge diamond"],
    ]],
    ['latin', [
        ["à", "a grave"], ["á", "a acute"], ["â", "a circumflex"], ["ã", "a tilde"],
        ["ä", "a umlaut diaeresis"], ["å", "a ring"], ["æ", "ae ligature"],
        ["ç", "c cedilla"], ["è", "e grave"], ["é", "e acute"], ["ê", "e circumflex"],
        ["ë", "e umlaut diaeresis"], ["ì", "i grave"], ["í", "i acute"],
        ["î", "i circumflex"], ["ï", "i umlaut diaeresis"], ["ñ", "n tilde"],
        ["ò", "o grave"], ["ó", "o acute"], ["ô", "o circumflex"], ["õ", "o tilde"],
        ["ö", "o umlaut diaeresis"], ["ø", "o slash stroke"], ["œ", "oe ligature"],
        ["ù", "u grave"], ["ú", "u acute"], ["û", "u circumflex"],
        ["ü", "u umlaut diaeresis"], ["ý", "y acute"], ["ÿ", "y umlaut diaeresis"],
        ["ß", "sharp s eszett"], ["þ", "thorn"], ["ð", "eth"], ["ł", "l stroke polish"],
        ["š", "s caron"], ["ž", "z caron"], ["č", "c caron"], ["ő", "o double acute"],
        ["À", "capital a grave"], ["Á", "capital a acute"], ["Â", "capital a circumflex"],
        ["Ä", "capital a umlaut diaeresis"], ["Å", "capital a ring"],
        ["Æ", "capital ae ligature"], ["Ç", "capital c cedilla"],
        ["È", "capital e grave"], ["É", "capital e acute"],
        ["Ê", "capital e circumflex"], ["Ë", "capital e umlaut diaeresis"],
        ["Í", "capital i acute"], ["Ñ", "capital n tilde"], ["Ó", "capital o acute"],
        ["Ö", "capital o umlaut diaeresis"], ["Ø", "capital o slash stroke"],
        ["Œ", "capital oe ligature"], ["Ú", "capital u acute"],
        ["Ü", "capital u umlaut diaeresis"], ["ẞ", "capital sharp s eszett"],
    ]],
    ['greek', [
        ["α", "alpha"], ["β", "beta"], ["γ", "gamma"], ["δ", "delta"],
        ["ε", "epsilon"], ["ζ", "zeta"], ["η", "eta"], ["θ", "theta"], ["ι", "iota"],
        ["κ", "kappa"], ["λ", "lambda"], ["μ", "mu"], ["ν", "nu"], ["ξ", "xi"],
        ["π", "pi"], ["ρ", "rho"], ["σ", "sigma"], ["τ", "tau"], ["υ", "upsilon"],
        ["φ", "phi"], ["χ", "chi"], ["ψ", "psi"], ["ω", "omega"],
        ["Γ", "capital gamma"], ["Δ", "capital delta"], ["Θ", "capital theta"],
        ["Λ", "capital lambda"], ["Ξ", "capital xi"], ["Π", "capital pi"],
        ["Σ", "capital sigma"], ["Φ", "capital phi"], ["Ψ", "capital psi"],
        ["Ω", "capital omega"],
    ]],
]
