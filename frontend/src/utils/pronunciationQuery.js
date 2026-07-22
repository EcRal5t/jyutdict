const INPUT_FORMAT = /^[a-z? ]{1,10}\d{0,2}$/;
const INITIAL_FORMAT = /^(?:ngg?|mb?|n[jrd]?|[bdg]{1,2}|g[whn]?|r[bdgzscrh]|[zcs][hrjl]?|[ptkvw]h?|[hqfjlrx0])(?=$|[aeoiuymn? ])/;
const CODA_FORMAT = /(ng|n|m|p|t|k|h)$/;
const TONE_FORMAT = /[0-9]?[0-9*][0-9']?(`\d+)?$/;

const invalidResult = (status = 'invalid') => ({
    status,
    valid: false,
    components: { in: '', nu: '', co: '', to: '' },
    query: { in: '', nu: '', co: '', to: '' }
});

/**
 * Parse the extended Jyutping search syntax used by /pronunciation.
 * A literal space occupies one component and becomes the API wildcard `%`.
 */
export const parsePronunciationQuery = (rawInput) => {
    const pron = String(rawInput ?? '').toLowerCase();
    if (!pron || !pron.trim()) return invalidResult('neutral');
    if (!INPUT_FORMAT.test(pron)) return invalidResult();

    const toneMatch = pron.match(TONE_FORMAT);
    const tone = toneMatch ? toneMatch[0] : '';
    const withoutTone = tone ? pron.slice(0, -tone.length) : pron;

    let remaining = withoutTone;
    let initial = '';
    let initialWildcard = false;
    if (remaining.startsWith(' ')) {
        initialWildcard = true;
        remaining = remaining.slice(1);
    } else {
        const initialMatch = remaining.match(INITIAL_FORMAT);
        initial = initialMatch ? initialMatch[0] : '';
        remaining = initial ? remaining.slice(initial.length) : remaining;
    }

    let nuclei = '';
    let coda = '';
    let nucleiWildcard = false;
    let codaWildcard = false;

    if (remaining.startsWith(' ')) {
        nucleiWildcard = true;
        remaining = remaining.slice(1);
        if (remaining === ' ') {
            codaWildcard = true;
            remaining = '';
        } else if (remaining && !remaining.includes(' ')) {
            const codaMatch = remaining.match(CODA_FORMAT);
            if (!codaMatch || codaMatch[0] !== remaining) return invalidResult();
            coda = remaining;
            remaining = '';
        }
    } else {
        if (remaining.endsWith(' ')) {
            codaWildcard = true;
            remaining = remaining.slice(0, -1);
        }
        if (remaining.includes(' ')) return invalidResult();
        const codaMatch = remaining.match(CODA_FORMAT);
        if (codaMatch && remaining.length > codaMatch[0].length) {
            coda = codaMatch[0];
            nuclei = remaining.slice(0, -coda.length);
        } else {
            nuclei = remaining;
        }
        remaining = '';
    }

    if (remaining || (!initial && !initialWildcard && !nuclei && !nucleiWildcard && !coda && !codaWildcard)) {
        return invalidResult();
    }

    return {
        status: 'valid',
        valid: true,
        components: {
            in: initialWildcard ? ' ' : initial,
            nu: nucleiWildcard ? ' ' : nuclei,
            co: codaWildcard ? ' ' : coda,
            to: tone
        },
        query: {
            in: initialWildcard ? '%' : initial,
            nu: nucleiWildcard ? '%' : nuclei,
            co: codaWildcard ? '%' : coda,
            to: tone || '%'
        }
    };
};
