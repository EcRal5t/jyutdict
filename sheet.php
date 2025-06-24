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
            overflow-x: auto;
        }
    </style>
</head>

<body>

<div id="wrapper" class="wrapper">
    <?PHP Info::showSidenav(); ?>
    <div id="container" class="container" style="">
        <button class="sidenav-show-btn" onclick="showSidenav()"></button>
        
        <h1 style="text-align:center;">泛粵字表查詢</h1>

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

        <div id="sheet-results" class="general-form">
            </div>

    </div>
    <?PHP Info::showFooter(); ?>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const locationSelect = document.getElementById('location-select');
        
        // Populate location dropdown
        fetch('api/v0.9/sheet.php?query=&header=1')
            .then(response => response.json())
            .then(data => {
                const headers = data.__valid_options;
                if (headers) {
                    headers.forEach(header => {
                        if(header.is_city == 1) {
                            const option = document.createElement('option');
                            option.value = header.col;
                            option.textContent = header.city + (header.sub ? ` (${header.sub})` : '');
                            locationSelect.appendChild(option);
                        }
                    });
                }
            });

        document.getElementById('search-button').addEventListener('click', function() {
            const inputString = document.getElementById('query-input').value.trim();
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

                if (isDef) {
                    url += '&b';
                }
                if (location) {
                    url += `&col=${location}`;
                }
                if (isRegex) {
                    url += '&regex';
                }
            }

            fetch(url)
                .then(response => response.json())
                .then(data => {
                    renderTable(data);
                })
                .catch(error => {
                    const resultsDiv = document.getElementById('sheet-results');
                    resultsDiv.innerHTML = '查詢出錯，請檢查輸入或稍後再試。';
                    console.error('Error fetching data:', error);
                });
        });

        function renderTable(data) {
            const resultsDiv = document.getElementById('sheet-results');
            resultsDiv.innerHTML = '';

            if (!data || data.length < 2 || (data.hasOwnProperty('error'))) {
                resultsDiv.textContent = data.error ? data.error : '未找到結果。';
                return;
            }

            const headerMap = data[0];
            const rows = data.slice(1);
            
            const table = document.createElement('table');
            table.className = 'general-form';
            const thead = document.createElement('thead');
            const tbody = document.createElement('tbody');
            const headerRow = document.createElement('tr');

            // Create a sorted list of headers
            const sortedHeaders = Object.keys(headerMap).sort((a, b) => headerMap[a] - headerMap[b]);
            
            sortedHeaders.forEach(key => {
                const th = document.createElement('th');
                th.textContent = key;
                headerRow.appendChild(th);
            });
            thead.appendChild(headerRow);

            rows.forEach(rowData => {
                const tr = document.createElement('tr');
                sortedHeaders.forEach(headerKey => {
                    const td = document.createElement('td');
                    td.textContent = rowData[headerKey] || '';
                    tr.appendChild(td);
                });
                tbody.appendChild(tr);
            });

            table.appendChild(thead);
            table.appendChild(tbody);
            resultsDiv.appendChild(table);
        }
    });
</script>

</body>
</html>