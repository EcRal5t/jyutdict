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
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }

        #container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 1em;
        }

        h1 {
            color: #343a40;
        }

        /* Search Form Beautification */
        #sheet-search-form {
            background-color: #fff;
            padding: 2em;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            margin: 2em 0;
            text-align: center;
        }

        #query-input {
            width: 100%;
            max-width: 500px;
            padding: 0.8em;
            font-size: 1.1em;
            border: 1px solid #ced4da;
            border-radius: 4px;
            margin-bottom: 1em;
            box-sizing: border-box; /* Ensures padding doesn't affect width */
        }

        #location-select {
            padding: 0.8em;
            font-size: 1em;
            border: 1px solid #ced4da;
            border-radius: 4px;
        }

        #sheet-search-form .options-group {
            margin: 1.5em 0;
            display: flex;
            gap: 1.5em;
            justify-content: center;
            flex-wrap: wrap;
        }

        #sheet-search-form label {
            font-size: 0.95em;
            color: #495057;
        }

        #search-button {
            background-color: #007bff;
            color: white;
            padding: 0.8em 2em;
            font-size: 1.1em;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.2s;
        }

        #search-button:hover {
            background-color: #0056b3;
        }

        /* Result Card Beautification */
        #sheet-results {
            margin-top: 2em;
        }

        .result-card {
            background: #fff;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 1.5em;
            margin-bottom: 1.5em;
            display: flex;
            flex-wrap: wrap;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            transition: box-shadow 0.3s;
        }
        
        .result-card:hover {
            box-shadow: 0 6px 12px rgba(0,0,0,0.08);
        }

        .result-left {
            flex: 1;
            min-width: 150px;
            padding-right: 1.5em;
            margin-right: 1.5em;
            border-right: 1px solid #e9ecef;
            text-align: center;
        }

        .result-right {
            flex: 4;
            min-width: 300px;
        }

        .char-display {
            font-size: 5em;
            font-weight: bold;
            color: #212529;
            line-height: 1.1;
        }

        .pron-display {
            font-size: 1.8em;
            color: #495057;
            line-height: 1.4;
            white-space: pre-wrap;
        }

        .unicode-display {
            color: #6c757d;
            margin-top: 0.5em;
            white-space: pre-wrap;
            font-family: monospace;
        }

        .meanings-section {
            margin-bottom: 1.5em;
            line-height: 1.7;
            font-size: 1.1em;
        }

        .locations-section {
            line-height: 1.8;
        }
        
        .location-entry {
            display: inline-block;
            margin-right: 1.5em;
            margin-bottom: 0.5em;
            font-size: 0.95em;
        }
        
        .foreign-languages, .classification-section {
            margin-top: 1.5em;
            padding-top: 1em;
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

        .clickable-note {
            text-decoration: underline;
            text-decoration-style: dotted;
            cursor: pointer;
            transition: color 0.2s;
        }
        .clickable-note:hover {
            color: #0056b3;
        }

        /* MODAL (for notes) STYLES */
        .modal-container {
            display: none; /* Hidden by default */
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.6);
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            position: relative;
            background-color: #fefefe;
            margin: auto;
            padding: 30px;
            border: 1px solid #888;
            width: 80%;
            max-width: 600px;
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
            animation: fadeIn 0.3s;
        }

        @keyframes fadeIn {
            from {opacity: 0; transform: translateY(-20px);}
            to {opacity: 1; transform: translateY(0);}
        }

        .modal-close {
            color: #aaa;
            position: absolute;
            top: 10px;
            right: 20px;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }

        .modal-close:hover,
        .modal-close:focus {
            color: black;
        }
        
        #note-modal-text {
            white-space: pre-wrap;
            line-height: 1.6;
            color: #343a40;
        }

    </style>
</head>

<body>

<div id="wrapper" class="wrapper">
    <?PHP Info::showSidenav(); ?>
    <div id="container" class="container" style="">
        <button class="sidenav-show-btn" onclick="showSidenav()"></button>
        
        <h1 style="text-align:center; margin-top: 1em;">泛粵字表查詢</h1>

        <div id="sheet-search-form">
            <input type="text" id="query-input" placeholder="輸入字、詞或讀音...">
            <select id="location-select">
                <option value="">所有列</option>
                <option value="檢">檢索音</option>
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

<div id="note-modal" class="modal-container">
  <div class="modal-content">
    <span class="modal-close" onclick="closeNote()">&times;</span>
    <p id="note-modal-text"></p>
  </div>
</div>


<script>
    let sheetHeaderInfo = {}; // To store header info globally

    document.addEventListener('DOMContentLoaded', function() {
        const queryInput = document.getElementById('query-input');
        
        // Fetch header info for populating the location dropdown
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
                        option.textContent = header.city + (header.sub ? ` (${header.sub})` : '');
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

        // Event Listeners for search
        document.getElementById('search-button').addEventListener('click', performSearch);
        queryInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                performSearch();
            }
        });

        // Event listener to close modal with Escape key
        window.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeNote();
            }
        });
    });

    /**
     * NEW MODAL FUNCTIONS
     */
    function showNote(noteText) {
        const modal = document.getElementById('note-modal');
        const modalText = document.getElementById('note-modal-text');
        
        // Use innerText to safely insert the text content
        modalText.innerText = noteText;
        
        modal.style.display = 'flex';
    }

    function closeNote() {
        const modal = document.getElementById('note-modal');
        modal.style.display = 'none';
    }
    // Close modal if user clicks on the overlay
    document.getElementById('note-modal').addEventListener('click', function(event) {
        if (event.target === this) {
            closeNote();
        }
    });


    // --- Functions to render results (mostly unchanged logic, just formatting) ---

    function renderResults(data) {
        const resultsDiv = document.getElementById('sheet-results');
        resultsDiv.innerHTML = '';

        if (!data || data.length < 2 || (data.hasOwnProperty('error'))) {
            resultsDiv.innerHTML = `<p style="text-align:center; padding: 2em; background: #fff; border-radius: 8px;">${data.error ? data.error : '未找到結果。'}</p>`;
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
        return `<div class="unicode-display">${ssb}</div>`;
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

        let ssb = `<div class="pron-display" style="${style}">${pronDisplay}`;

        let adaptedChara = rowData['俗/常'] || '';
        if (adaptedChara) {
            ssb += `\n<span style="font-size: 0.6em; color: #6c757d;">(${adaptedChara})</span>`;
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

    function formatLocations(rowData) {
        let ssb = '<div class="locations-section">';
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

            const fullName = cityInfo.city + (cityInfo.sub ? ` (${cityInfo.sub})` : '');
            let displayValue = '';
            if (value.includes('^')) {
                const parts = value.split('^');
                displayValue = parts[0] + parts.slice(1).map(p => `<del>${p}</del>`).join('');
            } else {
                displayValue = value;
            }
            
            let valueStyle = '';
            let locationNameStyle = `color: ${darkenColor(cityInfo.color, 0.9)}; font-weight: 500;`;
            if (value.includes('?')) valueStyle += 'font-style: italic;';
            if (value === '_') valueStyle += 'color: #BBBBBB;';
            
            let locationEntry = `<span style="${locationNameStyle}">${fullName}: </span><span style="${valueStyle}">${displayValue}</span>`;

            if (cellNotes[key]) {
                const chara = rowData['繁'] || '□', pron = rowData['綜'] || '';
                let noteText = `>「${chara}」(${pron}) [${key}] ${value}\n\n${cellNotes[key]}`;
                // Important: Escape backticks in the noteText before creating the template literal for onclick
                locationEntry = `<span class="clickable-note" onclick="showNote(\`${noteText.replace(/`/g, "\\`")}\`)">${locationEntry}</span>`;
            }
            ssb += `<span class="location-entry">${locationEntry}</span>`;
        });

        let foreignSsb = '';
        (sheetHeaderInfo.foreign || []).forEach(foreignInfo => {
            const key = foreignInfo.col;
            let value = rowData[key] ? String(rowData[key]).trim() : '';
            if (!value) return;

            let valueStyle = foreignInfo.color ? `color: ${darkenColor(foreignInfo.color, 0.9)};` : '';
            if (value.includes('?')) valueStyle += 'font-style: italic;';

            let foreignEntry = `<span style="${valueStyle}"><strong>${foreignInfo.fullname}:</strong> ${value.replace(/\n/g, ', ')}</span>`;
            
            if (cellNotes[key]) {
                 const chara = rowData['繁'] || '□', pron = rowData['綜'] || '';
                 let noteText = `>「${chara}」(${pron}) [${key}] ${value}\n\n${cellNotes[key]}`;
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
            if (class_secondary) classificationText += ` > ${class_secondary}`;
            if (class_minor) classificationText += ` > ${class_minor}`;
            ssb += `<div class="classification-section">${classificationText}</div>`;
        }
        ssb += `</div>`; // Close locations-section
        return ssb;
    }
</script>

</body>
</html>