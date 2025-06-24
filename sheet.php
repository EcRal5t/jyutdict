<?php
include("const.php");
?>

<!DOCTYPE html>
<html lang="zh-cn">
<head>
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-16">
    <meta http-equiv="Cache-control" content="no-cache">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>泛粵大典 - 泛粵字表查詢</title>
    <link rel="stylesheet" type="text/css" href="./css/index.css">
    <link rel="icon" href="./img/favicon.ico">
    
    <script src="./js/general.js"></script>
    <style>
        #sheet-search-form {
            margin: 20px 0;
            text-align: center;
        }
        #sheet-search-form input[type="text"] {
            width: 300px;
            padding: 5px;
            font-size: 18px;
        }
        #sheet-search-form select {
            padding: 5px;
            font-size: 16px;
        }
        #sheet-search-form button {
            padding: 5px 15px;
            font-size: 16px;
        }
        #sheet-search-form label {
            margin: 0 10px;
        }
        #sheet-results {
            margin-top: 20px;
        }
        .result-card {
            border: 1px solid #ccc;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 20px;
            display: flex;
            flex-wrap: wrap;
            text-align: left;
        }
        .result-left {
            flex: 1;
            min-width: 120px;
            padding-right: 15px;
            margin-right: 15px;
            border-right: 1px solid #eee;
            text-align: center;
        }
        .result-right {
            flex: 4;
            min-width: 300px;
        }
        .char-display {
            font-size: 4em;
            font-weight: bold;
            margin-bottom: 10px;
            line-height: 1.2;
        }
        .pron-display {
            font-size: 1.5em;
            line-height: 1.4;
        }
        .unicode-display {
            color: #666;
            margin-bottom: 15px;
        }
        .meanings-section {
            margin-bottom: 15px;
            line-height: 1.7;
        }
        .locations-section {
            white-space: pre-wrap;
            line-height: 1.8;
        }
        .location-entry {
            display: inline-block;
            margin-right: 1.5em;
        }
        .foreign-languages {
            margin-top: 1em;
            font-size: 0.9em;
            color: #333;
        }
        .classification-section {
            margin-top: 1em;
            font-size: 0.8em;
            color: #aaa;
            white-space: pre-wrap;
        }
        .clickable-note {
            text-decoration: underline;
            text-decoration-style: dotted;
            cursor: pointer;
        }
    </style>
</head>

<body>

<div id="wrapper" class="wrapper">
    <?PHP Info::showSidenav(); ?>
    <div id="container" class="container" style="">
        <button class="sidenav-show-btn" onclick="showSidenav()"></button>
        
        <h1 style="text-align:center; margin: 2em;">泛粵字表查詢</h1>

        <div id="sheet-search-form">
            <input type="text" id="query-input" placeholder="輸入字、詞或讀音...">
            <select id="location-select">
                <option value="">所有列</option>
                <option value="檢">檢索音</option>
            </select>
            <br>
            <div style="margin-top:10px;">
                <label><input type="checkbox" id="fuzzy-checkbox" checked> 模糊查詢 (查字)</label>
                <label><input type="checkbox" id="trim-checkbox" checked> 音節整體 (查音)</label>
                <label><input type="checkbox" id="regex-checkbox"> 正則表達式</label>
                <label><input type="checkbox" id="def-checkbox"> 反查釋義</label>
            </div>
            <br>
            <button id="search-button">查詢</button>
        </div>

        <div id="sheet-results">
            </div>

    </div>
    <?PHP Info::showFooter(); ?>
</div>

<script>
    let sheetHeaderInfo = {}; // To store header info globally

    document.addEventListener('DOMContentLoaded', function() {
        const queryInput = document.getElementById('query-input');
        
        fetch('api/v0.9/sheet.php?query=&header=1')
            .then(response => response.json())
            .then(data => {
                const headers = data.__valid_options;
                if (headers) {
                    sheetHeaderInfo = {
                        all: headers,
                        cities: headers.filter(h => h.is_city == 1),
                        foreign: headers.filter(h => h.is_city == 2)
                    };
                    const locationSelect = document.getElementById('location-select');
                    sheetHeaderInfo.cities.forEach(header => {
                        const option = document.createElement('option');
                        option.value = header.col;
                        option.textContent = header.city + (header.sub ? `${header.sub}` : '');
                        locationSelect.appendChild(option);
                    });
                }
            });

        function performSearch() {
            const inputString = queryInput.value.trim();
            const location = document.getElementById('location-select').value;
            const isFuzzy = document.getElementById('fuzzy-checkbox').checked;
            const isTrim = document.getElementById('trim-checkbox').checked;
            const isRegex = document.getElementById('regex-checkbox').checked;
            const isDef = document.getElementById('def-checkbox').checked;

            let url = 'api/v0.9/sheet.php?';
            
            if (inputString === '' && !isDef) {
                url += 'query=!&limit=10';
            } else {
                url += `query=${encodeURIComponent(inputString)}`;
                if (/[a-zA-Z0-9]/.test(inputString) && !isDef) {
                    if(isTrim) url += '&trim';
                } else {
                    if(isFuzzy) url += '&fuzzy';
                }

                if (isDef) url += '&b';
                if (location) url += `&col=${location}`;
                if (isRegex) url += '&regex';
            }

            fetch(url)
                .then(response => response.json())
                .then(data => {
                    renderResults(data);
                })
                .catch(error => {
                    const resultsDiv = document.getElementById('sheet-results');
                    resultsDiv.innerHTML = '<p style="text-align:center;">查詢出錯，請檢查輸入或稍後再試。</p>';
                    console.error('Error fetching data:', error);
                });
        }

        document.getElementById('search-button').addEventListener('click', performSearch);
        queryInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                performSearch();
            }
        });
    });

    function renderResults(data) {
        const resultsDiv = document.getElementById('sheet-results');
        resultsDiv.innerHTML = '';

        if (!data || data.length < 2 || (data.hasOwnProperty('error'))) {
            resultsDiv.innerHTML = `<p style="text-align:center;">${data.error ? data.error : '未找到結果。'}</p>`;
            return;
        }

        const rows = data.slice(1);
        rows.forEach(rowData => {
            const card = document.createElement('div');
            card.className = 'result-card';

            card.innerHTML = `
                <div class="result-left">
                    ${formatCharacter(rowData)}
                    ${formatUnicode(rowData)}
                    ${formatPronunciation(rowData)}
                </div>
                <div class="result-right">
                    ${formatMeanings(rowData)}
                    ${formatLocations(rowData)}
                </div>
            `;
            resultsDiv.appendChild(card);
        });
    }

    function formatCharacter(rowData) {
        let chara = rowData['繁'] || '';
        let displayChara = chara.replaceAll(/[?/!！？見歸 ]/g, '');
        if (!displayChara) {
            displayChara = '□';
        }

        let style = '';
        if (chara.includes('？') || chara.includes('?')) {
            style = 'color: #B9BAA3;';
        } else if (chara.includes('見') || chara.includes('歸')) {
            style = 'color: #3D3B4F;';
        }

        return `<div class="char-display" style="${style}">${displayChara}</div>`;
    }

    function formatUnicode(rowData) {
        let chara = (rowData['繁'] || '').replaceAll(/[?/!！？見歸 ]/g, '');
        let ssb = '';
        if (chara.length === 1) {
            let unicode = 'U+' + chara.charCodeAt(0).toString(16).toUpperCase().padStart(4, '0');
            ssb += unicode;
        }
        
        let ids = rowData['IDS'] || '';
        if (ids) {
            if (ssb) ssb += '\n';
            ssb += `[${ids}]`;
        }
        return `<div class="unicode-display" style="white-space: pre-wrap;">${ssb}</div>`;
    }

    function formatPronunciation(rowData) {
        let pron = (rowData['綜'] || '').replaceAll(/[!！]/g, '');
        
        let pronParts = pron.split('/');
        let pronDisplay = '';
        for (let i = 0; i < pronParts.length; i++) {
            if (i > 0) {
                pronDisplay += (i % 2 === 0) ? '/\n' : '/';
            }
            pronDisplay += pronParts[i];
        }
        
        let style = '';
        if (pron.includes('?')) {
            style = 'font-style: italic;';
        }

        let ssb = `<div class="pron-display" style="${style} white-space: pre-wrap;">${pronDisplay}`;

        let adaptedChara = rowData['俗/常'] || '';
        if (adaptedChara) {
            if (pron) ssb += '\n';
            ssb += `(${adaptedChara})`;
        }
        ssb += `</div>`;
        return ssb;
    }

    function formatMeanings(rowData) {
        let sb = '';
        const booksChara = rowData['錔'] || '';
        const booksPron = rowData['音'] || '';
        const booksMeaning = rowData['義'] || '';
        if (booksChara || booksPron || booksMeaning) {
            sb += '—— <i>';
            if (booksChara) {
                sb += `${booksChara}`;
                if (booksPron || booksMeaning) {
                    sb += `: ${booksPron}`;
                    if (booksPron && booksMeaning) sb += ' | ';
                    if (booksMeaning) sb += `「${booksMeaning}」`;
                }
            } else {
                sb += booksPron;
                if (booksPron && booksMeaning) sb += ' | ';
                if (booksMeaning) sb += `「${booksMeaning}」`;
            }
            sb += '</i><br>';
        }

        let oriString = rowData['釋義'] || '';
        if (!oriString) return `<div class="meanings-section">${sb}</div>`;
        
        oriString = oriString.replace(/</g, "&lt;");
        oriString = oriString.replace(/(?<=([^}"“]))&lt;/g, '；&lt;');
        if (oriString.startsWith('[粵]') && oriString.includes('{1}')) {
            oriString = oriString.replace('[粵]', '[粵]；');
        }
        oriString = oriString.replace(/}/g, '} ');

        const meanings = oriString.split(/[；。？！] *?(?!=(&lt;|\{))/);
        const grammarMarkers = (rowData['語法'] || '').split(/[;；] ?/).filter(m => m);
        
        let grammarMarkerOrder = 0;
        for (const meaning of meanings) {
            if (!meaning || !meaning.trim()) continue;
            
            let processedMeaning = meaning;
            if (grammarMarkers.length > 0 && grammarMarkers[grammarMarkerOrder]) {
                const marker = `‹${grammarMarkers[grammarMarkerOrder].replace('？', '?')}›`;
                processedMeaning = processedMeaning.replace(/(?<=[}])/ ,marker);
                grammarMarkerOrder++;
            }
            
            if (processedMeaning.includes('[粵]') && meanings.length > 1) {
                sb += `<b>${processedMeaning}</b>`;
            } else {
                sb += processedMeaning;
            }
            sb += '<br>';
        }

        if (sb.endsWith('<br>')) sb = sb.slice(0, -4);
        
        return `<div class="meanings-section">${sb}</div>`;
    }

    function darkenColor(hex, ratio) {
        if (!hex || hex.length < 4) return '#000000';
        let r = parseInt(hex.slice(1, 3), 16),
            g = parseInt(hex.slice(3, 5), 16),
            b = parseInt(hex.slice(5, 7), 16);
        r = Math.floor(r * ratio);
        g = Math.floor(g * ratio);
        b = Math.floor(b * ratio);
        return `#${r.toString(16).padStart(2, '0')}${g.toString(16).padStart(2, '0')}${b.toString(16).padStart(2, '0')}`;
    }

    function showNote(noteText) {
        alert(noteText);
    }

    function formatLocations(rowData) {
        let ssb = '';
        let cellNotes = {};
        try {
            const notesString = rowData['附'] || '{}';
            const sanitizedString = notesString
                .replace(/\n/g, '\\n')
                .replace(/\t/g, '\\t')
                .replace(/'/g, '"');
            cellNotes = JSON.parse(sanitizedString);
        } catch(e) { 
            console.warn('Could not parse cell notes:', rowData['附'], e);
        }

        (sheetHeaderInfo.cities || []).forEach(cityInfo => {
            const key = cityInfo.col;
            let value = rowData[key] ? String(rowData[key]).trim() : '';
            if (!value) return;

            const fullName = cityInfo.city + (cityInfo.sub ? `${cityInfo.sub}` : '');
            let displayValue = '';
            if (value.includes('^')) {
                const parts = value.split('^');
                displayValue = parts[0] + parts.slice(1).map(p => `<del>${p}</del>`).join('');
            } else {
                displayValue = value;
            }
            
            let valueStyle = '';
            let locationNameStyle = `color: ${darkenColor(cityInfo.color, 0.92)};`;
            if (value.includes('?')) valueStyle += 'font-style: italic;';
            if (value === '_') valueStyle += 'color: #BBBBBB;';
            
            let locationEntry = `<span style="${locationNameStyle}">${fullName}: </span><span style="${valueStyle}">${displayValue}</span>`;

            if (cellNotes[key]) {
                const chara = rowData['繁'] || '□', pron = rowData['綜'] || '';
                let noteText = `>「${chara}」(${pron}) [${key}] ${value}\n${cellNotes[key]}`;
                locationEntry = `<span class="clickable-note" onclick="showNote(\`${noteText.replace(/`/g, "\\`")}\`)">${locationEntry}</span>`;
            }
            ssb += `<span class="location-entry">${locationEntry}</span>`;
        });

        let foreignSsb = '';
        (sheetHeaderInfo.foreign || []).forEach(foreignInfo => {
            const key = foreignInfo.col;
            let value = rowData[key] ? String(rowData[key]).trim() : '';
            if (!value) return;

            let valueStyle = foreignInfo.color ? `color: ${darkenColor(foreignInfo.color, 0.92)};` : '';
            if (value.includes('?')) valueStyle += 'font-style: italic;';

            let foreignEntry = `<span style="${valueStyle}">${foreignInfo.fullname}: ${value.replace(/\n/g, ', ')}</span>`;
            
            if (cellNotes[key]) {
                 const chara = rowData['繁'] || '□', pron = rowData['綜'] || '';
                 let noteText = `>「${chara}」(${pron}) [${key}] ${value}\n${cellNotes[key]}`;
                 foreignEntry = `<span class="clickable-note" onclick="showNote(\`${noteText.replace(/`/g, "\\`")}\`)">${foreignEntry}</span>`;
            }
            foreignSsb += `<span class="location-entry">${foreignEntry}</span>`;
        });
        if (foreignSsb) ssb += `<div class="foreign-languages">${foreignSsb}</div>`;

        let classified = rowData['大類'] || '';
        if (classified) {
            let classificationText = classified;
            const class_secondary = rowData['中類'] || '';
            const class_minor = rowData['小類'] || '';
            if (class_secondary) classificationText += `\n${class_secondary}`;
            if (class_minor) classificationText += `\n${class_minor}`;
            ssb += `<div class="classification-section">${classificationText}</div>`;
        }
        return `<div class="locations-section">${ssb}</div>`;
    }
</script>

</body>
</html>