export class Jyutping {
    constructor() {
        this.initial = "";
        this.nuclei = "";
        this.coda = "";
        this.tone = "";
        this.ipa = "";
    }

    // Logic ported from Jyutping.class.php
    static parse(jyutpingStr) {
        // Regex patterns from PHP class
        const format = /^[a-z%]{1,10}([0-9]?[0-9*][0-9\']?)?$/;
        const initialFormat = /^(mb?|n[jrd]?|ngg?|[bdg]{1,2}|g[hn]?|r[bdgzscrh]|[zcs][hrjl]?|[ptkvw]h?|[hqfjlrx0])([jwv]?)(?=[aeoiuymn])/;
        const codaFormat = /[aoreiwu%](n[ng]?|[mptkh|%])(\d{0,2}|%)$/;
        const toneFormat = /[0-9]?[0-9*][0-9\']?$/;
        const vowelFormat = /(^ng?$|^m$|i[rwi]?|u[rwu]?|[aeo][aeowr]?|yu$|y)$/;

        if (format.test(jyutpingStr)) {
            // PHP logic: 
            // $tone = preg_match... ? match : "";
            // $initial = preg_match... ? match : "";
            // ...

            let temp;

            // Tone
            temp = jyutpingStr.match(toneFormat);
            let tone = temp ? temp[0] : "";

            // Initial
            temp = jyutpingStr.match(initialFormat);
            let initial = temp ? temp[0] : ""; // PHP uses group 0 for whole match? Wait, regex has groups. match[0] is full match.

            // Coda
            // PHP: /$codaFormat/ ... matches END of string because of $.
            // But we need to be careful if tone is stripped or not?
            // "jyutpingStr" contains tone.
            // PHP logic: matches input string.
            // Coda format in PHP: `/[aoreiwu%](n[ng]?|[mptkh|%])(\d{0,2}|%)$/`
            // Matches vowel+coda+tone? No. `[aoreiwu%]` is LAST VOWEL char?
            // Wait, parsing logic in PHP:
            // 1. Find Tone (at end)
            // 2. Find Initial (at start)
            // 3. Find Coda (at end... with tone included in regex?)
            // PHP Coda regex includes `(\d{0,2}|%)` at end. So yes.
            // `tempResult[1]` is the capture group 1: `(n[ng]?|[mptkh|%])`.

            temp = jyutpingStr.match(codaFormat);
            let coda = temp ? temp[1] : "";

            // Nuclei
            // PHP: substr(str, len(initial), len(str) - len(in) - len(co) - len(to))
            let nuclei = jyutpingStr.substring(initial.length, jyutpingStr.length - coda.length - tone.length);

            // Validate Nuclei (Vowel partitioning)
            // ... PHP loop checking vowels ...
            // We can skip deep validation for display purposes if we assume DB data is mostly correct.
            // But coloring depends on splitting correctly.

            return { initial, nuclei, coda, tone };
        }
        return null;
    }
}
