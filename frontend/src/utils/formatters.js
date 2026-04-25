
// Ported from Android Java implementation provided by user
function hsvToRgb(h, s, v) {
    let r, g, b;
    let i = Math.floor(h * 6);
    let f = h * 6 - i;
    let p = v * (1 - s);
    let q = v * (1 - f * s);
    let t = v * (1 - (1 - f) * s);
    switch (i % 6) {
        case 0: r = v; g = t; b = p; break;
        case 1: r = q; g = v; b = p; break;
        case 2: r = p; g = v; b = t; break;
        case 3: r = p; g = q; b = v; break;
        case 4: r = t; g = p; b = v; break;
        case 5: r = v; g = p; b = q; break;
    }
    return [Math.round(r * 255), Math.round(g * 255), Math.round(b * 255)];
}

function rgbToHsv(r, g, b) {
    r /= 255, g /= 255, b /= 255;
    let max = Math.max(r, g, b), min = Math.min(r, g, b);
    let h, s, v = max;
    let d = max - min;
    s = max === 0 ? 0 : d / max;
    if (max === min) {
        h = 0;
    } else {
        switch (max) {
            case r: h = (g - b) / d + (g < b ? 6 : 0); break;
            case g: h = (b - r) / d + 2; break;
            case b: h = (r - g) / d + 4; break;
        }
        h /= 6;
    }
    return [h, s, v];
}

export function darkenColor(hex, ratio) {
    if (!hex) return '#333333';
    if (hex.startsWith('#')) hex = hex.slice(1);
    if (hex.length === 3) hex = hex.split('').map(c => c + c).join('');

    let r = parseInt(hex.substring(0, 2), 16);
    let g = parseInt(hex.substring(2, 4), 16);
    let b = parseInt(hex.substring(4, 6), 16);

    let [h, s, v] = rgbToHsv(r, g, b);

    // Logic from user: hsv[2] *= ratio; hsv[1] /= ratio*ratio;
    v = Math.min(1, v * ratio);
    s = Math.min(1, s / (ratio * ratio));

    [r, g, b] = hsvToRgb(h, s, v);

    return `#${r.toString(16).padStart(2, '0')}${g.toString(16).padStart(2, '0')}${b.toString(16).padStart(2, '0')}`;
}

export function formatCharacter(rowData) {
    let chara = rowData['繁'] || '';
    let displayChara = chara.replaceAll(/[?/!！？見歸 ]/g, '');
    if (!displayChara) displayChara = '□';
    let style = '';
    // Keeping colors explicitly as style for now, but could be classes
    if (chara.includes('？') || chara.includes('?')) style = 'color: #9CA3AF;'; // Gray-400
    else if (chara.includes('見') || chara.includes('歸')) style = 'color: #4B5563;'; // Gray-600
    return { display: displayChara, style };
}

export function formatUnicode(rowData) {
    let chara = (rowData['繁'] || '').replaceAll(/[?/!！？見歸 ]/g, '');
    let ssb = '';
    if (chara.length === 1) {
        ssb += 'U+' + chara.charCodeAt(0).toString(16).toUpperCase().padStart(4, '0');
    }
    let ids = rowData['IDS'] || '';
    if (ids) ssb += `\n[${ids}]`;
    return ssb.trim();
}

export function formatPronunciation(rowData) {
    let pron = (rowData['綜'] || '').replaceAll(/[!！]/g, '');
    let pronSimplified = (rowData['檢'] || '').replaceAll(/[!！]/g, '');
    let pronParts = pron.split('/');
    let pronDisplay = pronParts.join('/\n');

    let isItalic = pron.includes('?');
    let simplifiedText = null;
    if (pron != pronSimplified) {
        simplifiedText = pronSimplified;
    }
    let adaptedChara = rowData['俗/常'] || '';

    return {
        text: pronDisplay,
        isItalic,
        simplifiedText,
        adaptedChara
    };
}

export function formatMeanings(rowData) {
    let sb = '';
    const booksChara = rowData['錔'] || '', booksPron = rowData['音'] || '', booksMeaning = rowData['義'] || '';
    let bookInfo = null;

    if (booksChara || booksPron || booksMeaning) {
        let text = '';
        if (booksChara) {
            text += `${booksChara}`;
            if (booksPron || booksMeaning) text += `: ${booksPron}`;
            if (booksPron && booksMeaning) text += ' | ';
            if (booksMeaning) text += `「${booksMeaning}」`;
        } else {
            text += booksPron;
            if (booksPron && booksMeaning) text += ' | ';
            if (booksMeaning) text += `「${booksMeaning}」`;
        }
        bookInfo = text;
    }

    let oriString = rowData['釋義'] || '';
    if (!oriString) return { bookInfo, meanings: [] };

    oriString = oriString.replace(/</g, "&lt;").replace(/(?<=([^}"“]))&lt;/g, '；&lt;');
    if (oriString.startsWith('[粵]') && oriString.includes('{1}')) oriString = oriString.replace('[粵]', '[粵]；');
    oriString = oriString.replace(/}/g, '} ');

    const rawMeanings = oriString.split(/[；。？！] *?(?=&lt;)|(?=\{)/);
    const grammarMarkers = (rowData['語法'] || '').split(/[;；] ?/).filter(m => m);
    let grammarMarkerOrder = 0;

    const formattedMeanings = [];

    for (const meaning of rawMeanings) {
        if (!meaning || !meaning.trim()) continue;
        let processedMeaning = meaning;
        if (grammarMarkers.length > 0 && grammarMarkers[grammarMarkerOrder]) {
            processedMeaning = processedMeaning.replace(/(?<=[}])/, `‹${grammarMarkers[grammarMarkerOrder++].replace('？', '?')}›`);
        }
        let isCantonese = (processedMeaning.includes('[粵]') && rawMeanings.length > 1);
        formattedMeanings.push({ text: processedMeaning, isBold: isCantonese });
    }

    return { bookInfo, meanings: formattedMeanings };
}
