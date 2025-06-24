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
        /* General Body & Layout */
        body {
            background-color: #f8f9fa;
            font-family: "Garamond","Adobe Ming Std","Adobe Song Std","澹雅明体A","Sarasa UI CL","更纱黑体","更纱黑体","Han Sans TC","Hiragino Sans GB","Microsoft JhengHei UI","Microsoft YaHei UI",sans-serif;
        }

        #container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 1em 1.5em;
        }

        h1 {
            color: #343a40;
        }

        /* Search Form Beautification */
        #sheet-search-form {
            background-color: #fff;
            padding: 1.5em 2em;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            margin: 2em 0;
            text-align: center;
        }

        #query-input {
            width: 100%;
            max-width: 500px;
            padding: 0.7em;
            font-size: 1em;
            border: 1px solid #ced4da;
            border-radius: 4px;
            margin-bottom: 1em;
            box-sizing: border-box;
        }
        
        #location-select {
            padding: 0.7em;
            font-size: 0.9em;
            border: 1px solid #ced4da;
            border-radius: 4px;
        }
        
        #sheet-search-form .options-group {
            margin: 1.2em 0;
            display: flex;
            gap: 1.5em;
            justify-content: center;
            flex-wrap: wrap;
        }

        #sheet-search-form label {
            font-size: 0.9em;
            color: #495057;
        }

        #search-button {
            background-color: #007bff;
            color: white;
            padding: 0.7em 1.8em;
            font-size: 1em;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.2s;
        }

        #search-button:hover {
            background-color: #0056b3;
        }

        /* Result Card Beautification (Higher Density & Responsive) */
        #sheet-results {
            margin-top: 2em;
        }

        .result-card {
            background: #fff;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 1.5em;
            margin-bottom: 1.2em;
            display: flex;
            flex-wrap: nowrap; /* Prevent wrapping by default */
            gap: 1.5em;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            transition: box-shadow 0.3s;
        }
        
        .result-card:hover {
            box-shadow: 0 8px 16px rgba(0,0,0,0.08);
        }

        .result-left {
            flex: 1;
            min-width: 120px;
            display: flex;
            align-items: center;
            gap: 1.2em;
            text-align: center;
            flex-direction: column;
            justify-content: center;
        }
        
        .result-left-main-char {
            text-align: center;
        }

        .result-left-details {
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .result-right {
            flex: 6;
            min-width: 300px;
        }

        .char-display {
            font-size: 4em;
            font-weight: bold;
            color: #212529;
            line-height: 1.1;
        }

        .pron-display {
            font-size: 1.4em;
            color: #495057;
            white-space: pre-wrap;
        }

        .unicode-display {
            color: #6c757d;
            margin-top: 0.5em;
            white-space: pre-wrap;
            font-family: monospace;
            font-size: 0.85em;
            text-align: center;
        }
        
        .meanings-section {
            margin-bottom: 1em;
            line-height: 1.6;
            font-size: 1.05em;
        }
        
        .locations-section {
            line-height: 1.2;
        }
        
        .location-entry-wrapper {
            display: inline-block;
            vertical-align: top;
            margin-right: 1.2em;
            margin-bottom: 0.6em;
            font-size: 0.95em;
            text-align: center;
        }
        
        .clickable-note {
            text-decoration: underline;
            text-decoration-style: dotted;
            cursor: pointer;
            transition: color 0.2s;
        }
        .clickable-note:hover {
            color: #0056b3;
        }
        
        .note-content {
            background-color: #f0f4f8;
            border-left: 3px solid #007bff;
            padding: 0 1em;
            margin-top: 0;
            border-radius: 4px;
            white-space: pre-wrap;
            line-height: 1.5;
            color: #34495e;
            overflow: hidden;
            max-height: 0;
            max-width: 0;
            opacity: 0;
            transition: max-height 0.3s ease-in-out, max-width 0.35s ease-in-out, opacity 0.25s ease-in-out, margin-top 0.3s ease-in-out, padding 0.3s ease-in-out;
            box-sizing: border-box;
            overflow: hidden;
        }
        .note-content.show {
            max-height: 10em;
            max-width: 500px;
            opacity: 1;
            margin-top: 0.5em;
            padding: 0.8em 1em;
        }

        .foreign-languages, .classification-section {
            margin-top: 1em;
            padding-top: 0.8em;
            border-top: 1px solid #f1f3f5;
        }
        
        .foreign-languages {
            font-size: 0.9em;
            color: #333;
        }
        
        .classification-section {
            font-size: 0.85em;
            color: #adb5bd;
            white-space: pre-wrap;
        }
        
        /* Responsive Adjustments */
        @media (max-width: 800px) {
            .result-card {
                flex-direction: column; /* Stack left and right parts */
                gap: 1em;
            }
            .result-left {
                border-bottom: 1px solid #e9ecef;
                padding-bottom: 1em;
                min-width: auto;
                flex-direction: row;
            }
        }

        @media (max-width: 500px) {
            #container {
                padding: 1em 0.5em;
            }
            #sheet-search-form {
                padding: 1em;
                margin: 1em 0;
            }
            .result-card {
                padding: 0.8em;
            }
            .result-left {
                gap: 0.8em;
            }
            .char-display {
                font-size: 3.2em;
            }
            .pron-display {
                font-size: 1.2em;
            }
        }

    </style>
</head>

<body>

<div id="wrapper" class="wrapper">
    <?PHP Info::showSidenav(); ?>
    <div id="container" class="container">
        <button class="sidenav-show-btn" onclick="showSidenav()"></button>
        
        <h1 style="text-align:center; margin-top: 1em;">泛粵字表查詢</h1>

        <div id="sheet-search-form">
            <input type="text" id="query-input" placeholder="輸入字、詞或讀音...">
            <select id="location-select">
                <option value="">綜合音/字</option>
                <option value="檢" selected>檢索音/字</option>
            </select>
            <div class="options-group">
                <label><input type="checkbox" id="fuzzy-checkbox" checked> 模糊查詢 (查字)</label>
                <label><input type="checkbox" id="trim-checkbox" checked> 音節整體 (查音)</label>
                <label><input type="checkbox" id="regex-checkbox"> 正則表達式</label>
                <label><input type="checkbox" id="def-checkbox"> 反查釋義</label>
            </div>
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
                        option.textContent = header.city + (header.sub ? header.sub : '');
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
                    if (location) url += `&col=${location}`;
                } else {
                    if(isFuzzy) url += '&fuzzy';
                }

                if (isDef) url += '&b';
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

    function toggleNote(noteId) {
        const noteElement = document.getElementById(noteId);
        if (noteElement) {
            noteElement.classList.toggle('show');
        }
    }
    
    
    function calculateDensityScore(rowData, cityHeaders) {
        let score = 0;
        if (!rowData || !cityHeaders) {
            return 0;
        }
        cityHeaders.forEach(cityInfo => {
            const key = cityInfo.col;
            const value = rowData[key] ? String(rowData[key]).trim() : '';
            // Count as 'filled' if it has content and is not just a placeholder
            if (value && value !== '_' && value !== '?') {
                score++;
            }
        });
        return score;
    }


    function renderResults(data) {
        const resultsDiv = document.getElementById('sheet-results');
        resultsDiv.innerHTML = '';

        if (!data || data.length < 2 || (data.hasOwnProperty('error'))) {
            resultsDiv.innerHTML = `<p style="text-align:center; padding: 2em; background: #fff; border-radius: 8px;">${data.error ? data.error : '未找到結果。'}</p>`;
            return;
        }

        const rows = data.slice(1);
        
        rows.sort((a, b) => {
            const scoreA = calculateDensityScore(a, sheetHeaderInfo.cities);
            const scoreB = calculateDensityScore(b, sheetHeaderInfo.cities);
            return scoreB - scoreA;
        });

        rows.forEach(rowData => {
            const card = document.createElement('div');
            card.className = 'result-card';
            rowData.id = rowData.id || `row-${Math.random()}`;

            // MODIFIED HTML STRUCTURE
            card.innerHTML = `
                <div class="result-left">
                    <div class="result-left-main-char">
                        ${formatCharacter(rowData)}
                    </div>
                    <div class="result-left-details">
                        ${formatUnicode(rowData)}
                        ${formatPronunciation(rowData)}
                    </div>
                </div>
                <div class="result-right">
                    ${formatMeanings(rowData)}
                    ${formatLocations(rowData)}
                </div>
            `;
            resultsDiv.appendChild(card);
        });
    }

    // --- Other formatting functions remain the same ---
    function formatCharacter(rowData) {
        let chara = rowData['繁'] || '';
        let displayChara = chara.replaceAll(/[?/!！？見歸 ]/g, '');
        if (!displayChara) displayChara = '□';
        let style = '';
        if (chara.includes('？') || chara.includes('?')) style = 'color: #B9BAA3;';
        else if (chara.includes('見') || chara.includes('歸')) style = 'color: #3D3B4F;';
        return `<div class="char-display" style="${style}">${displayChara}</div>`;
    }

    function formatUnicode(rowData) {
        let chara = (rowData['繁'] || '').replaceAll(/[?/!！？見歸 ]/g, '');
        let ssb = '';
        if (chara.length === 1) {
            ssb += 'U+' + chara.charCodeAt(0).toString(16).toUpperCase().padStart(4, '0');
        }
        let ids = rowData['IDS'] || '';
        if (ids) ssb += `\n[${ids}]`;
        return `<div class="unicode-display">${ssb}</div>`;
    }

    function formatPronunciation(rowData) {
        let pron = (rowData['綜'] || '').replaceAll(/[!！]/g, '');
        let pronSimplified = (rowData['檢'] || '').replaceAll(/[!！]/g, '');
        let pronParts = pron.split('/');
        let pronDisplay = '';
        for (let i = 0; i < pronParts.length; i++) {
            if (i > 0) pronDisplay += (i % 2 === 0) ? '/\n' : '/';
            pronDisplay += pronParts[i];
        }
        let style = pron.includes('?') ? 'font-style: italic;' : '';
        let ssb = `<div class="pron-display" style="${style}">${pronDisplay}`;
        if (pron != pronSimplified) {
            ssb += `<span style="font-size: 0.5em; color: #6c757d;"> ${pronSimplified}</span>`;
        }
        let adaptedChara = rowData['俗/常'] || '';
        if (adaptedChara) {
            ssb += `\n<span style="font-size: 0.6em; color: #6c757d;">(${adaptedChara})</span>`;
        }
        ssb += `</div>`;
        return ssb;
    }

    function formatMeanings(rowData) {
        let sb = '';
        const booksChara = rowData['錔'] || '', booksPron = rowData['音'] || '', booksMeaning = rowData['義'] || '';
        if (booksChara || booksPron || booksMeaning) {
            sb += '—— <i>';
            if (booksChara) {
                sb += `${booksChara}`;
                if (booksPron || booksMeaning) sb += `: ${booksPron}`;
                if (booksPron && booksMeaning) sb += ' | ';
                if (booksMeaning) sb += `「${booksMeaning}」`;
            } else {
                sb += booksPron;
                if (booksPron && booksMeaning) sb += ' | ';
                if (booksMeaning) sb += `「${booksMeaning}」`;
            }
            sb += '</i><br>';
        }

        let oriString = rowData['釋義'] || '';
        if (!oriString) return `<div class="meanings-section">${sb}</div>`;
        oriString = oriString.replace(/</g, "&lt;").replace(/(?<=([^}"“]))&lt;/g, '；&lt;');
        if (oriString.startsWith('[粵]') && oriString.includes('{1}')) oriString = oriString.replace('[粵]', '[粵]；');
        oriString = oriString.replace(/}/g, '} ');
        const meanings = oriString.split(/[；。？！] *?(?!=(&lt;|\{))/);
        const grammarMarkers = (rowData['語法'] || '').split(/[;；] ?/).filter(m => m);
        let grammarMarkerOrder = 0;
        for (const meaning of meanings) {
            if (!meaning || !meaning.trim()) continue;
            let processedMeaning = meaning;
            if (grammarMarkers.length > 0 && grammarMarkers[grammarMarkerOrder]) {
                processedMeaning = processedMeaning.replace(/(?<=[}])/ , `‹${grammarMarkers[grammarMarkerOrder++].replace('？', '?')}›`);
            }
            sb += (processedMeaning.includes('[粵]') && meanings.length > 1) ? `<b>${processedMeaning}</b>` : processedMeaning;
            sb += '<br>';
        }
        if (sb.endsWith('<br>')) sb = sb.slice(0, -4);
        return `<div class="meanings-section">${sb}</div>`;
    }

    function darkenColor(hex, ratio) {
        if (!hex || hex.length < 4) return '#000000';
        let r = parseInt(hex.slice(1, 3), 16), g = parseInt(hex.slice(3, 5), 16), b = parseInt(hex.slice(5, 7), 16);
        r = Math.floor(r * ratio); g = Math.floor(g * ratio); b = Math.floor(b * ratio);
        return `#${r.toString(16).padStart(2, '0')}${g.toString(16).padStart(2, '0')}${b.toString(16).padStart(2, '0')}`;
    }

    function formatLocations(rowData) {
        let ssb = '<div class="locations-section">';
        let cellNotes = {};
        try {
            const notesString = rowData['附'] || '{}';
            cellNotes = JSON.parse(notesString.replace(/\n/g, '\\n').replace(/\t/g, '\\t').replace(/'/g, '"'));
        } catch(e) { console.warn('Could not parse cell notes:', rowData['附'], e); }
        
        const buildEntry = (info, type) => {
            const key = info.col;
            let value = rowData[key] ? String(rowData[key]).trim() : '';
            if (!value) return '';

            let entryHTML;
            if (type === 'city') {
                const fullName = info.city + (info.sub ? info.sub : '');
                const displayValue = value.includes('^') ? value.split('^').slice(1).reduce((acc, p) => acc + `<del>${p}</del>`, value.split('^')[0]) : value;
                const valueStyle = (value.includes('?') ? 'font-style: italic;' : '') + (value === '_' ? 'color: #BBBBBB;' : '');
                const nameStyle = `color: ${darkenColor(info.color, 0.88)}; font-weight: 500;`;
                entryHTML = `<span style="${nameStyle}">${fullName}: </span><span style="${valueStyle}">${displayValue}</span>`;
            } else { // foreign
                const valueStyle = (info.color ? `color: ${darkenColor(info.color, 0.88)};` : '') + (value.includes('?') ? 'font-style: italic;' : '');
                entryHTML = `<span style="${valueStyle}"><strong>${info.fullname}:</strong> ${value.replace(/\n/g, ', ')}</span>`;
            }

            let noteDiv = '';
            if (cellNotes[key]) {
                const noteId = `note-${rowData.id}-${key}`;
                const noteText = `${cellNotes[key]}`.replace(/\n/g, '<br>');
                entryHTML = `<span class="clickable-note" onclick="toggleNote('${noteId}')">${entryHTML}</span>`;
                noteDiv = `<div id="${noteId}" class="note-content">${noteText}</div>`;
            }
            return `<div class="location-entry-wrapper">${entryHTML}${noteDiv}</div>`;
        };

        (sheetHeaderInfo.cities || []).forEach(cityInfo => ssb += buildEntry(cityInfo, 'city'));

        let foreignSsb = '';
        (sheetHeaderInfo.foreign || []).forEach(foreignInfo => foreignSsb += buildEntry(foreignInfo, 'foreign'));
        if (foreignSsb) ssb += `<div class="foreign-languages">${foreignSsb}</div>`;

        let classified = rowData['大類'] || '';
        if (classified) {
            let classificationText = classified;
            const class_secondary = rowData['中類'] || '';
            const class_minor = rowData['小類'] || '';
            if (class_secondary) classificationText += ` > ${class_secondary}`;
            if (class_minor) classificationText += ` > ${class_minor}`;
            ssb += `<div class="classification-section">${classificationText}</div>`;
        }
        ssb += `</div>`;
        return ssb;
    }
</script>

</body>
</html>