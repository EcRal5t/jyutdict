<script setup>
import { onMounted, ref, watch } from 'vue';
// Leaflet must be imported dynamically or we need to ensure CSS is waiting.
// Standard Vue use: import L from 'leaflet'
// But user environment might just need a script tag or npm install.
// Let's assume we can use the CDN link provided in view.class.php or install it.
// Since we are in Vite/Vue environment, using a library is best.
// But we didn't install leaflet. 
// "Map display missing" -> User wants it.
// I can add leaflet to package.json, or just inject script/css like legacy?
// Legacy used CDN. I'll inject script/css in index.html and use global L.

const props = defineProps({
  locations: {
    type: Array,
    required: true
  }
});

const mapContainer = ref(null);
let map = null;

const initMap = () => {
    if (!window.L || !mapContainer.value) return;
    
    // Legacy coords
    // ViewMap.php: [22.6, 111], zoom 7
    // Bounds: 26... to 17...
    const leftup = L.latLng('26.938400','105.819028'); 
    const rightdown = L.latLng('17.119569','115.883134');
    const bounds = L.latLngBounds(leftup,rightdown);
    
    if (map) map.remove();
    
    map = L.map(mapContainer.value, {
        maxBounds: bounds,
        minZoom: 6,
        maxZoom: 12
    }).setView([22.6, 111], 7);

    L.tileLayer('https://api.mapbox.com/styles/v1/{id}/tiles/256/{z}/{x}/{y}@2x?access_token={accessToken}', {
        tms: false,
        id: 'zenam/cjzk0fd9k0esv1csaqrxhiemj',
        accessToken: 'pk.eyJ1IjoiemVuYW0iLCJhIjoiY2p4bjh5MjFxMGM4aTNobGF0dXNoejlseiJ9.BPrObTer-_5w5L3oEaEWfQ',
        attribution: '©Mapbox | Performed By Lingnaam Jyutjam'
    }).addTo(map);
    
    // Add Markers
    // props.locations is Array of Location Objects (enriched in DetailView)
    props.locations.forEach(entry => {
         if (entry.latitude && entry.longitude) {
             const lat = parseFloat(entry.latitude);
             const lng = parseFloat(entry.longitude);
             
             // Circle
             L.circle([lat, lng], {
                 color: entry.color,
                 fillColor: entry.color,
                 fillOpacity: 0.5,
                 radius: 1000
             }).addTo(map);
             
             // Label Content
             // entry['粵拼'] is array. Join with space or newline? Space is good.
             const prons = (entry['粵拼'] || []).join(' ');
             
             const html = `
                <div class='locale-label' style='background-color: ${entry.color}; opacity: 0.85;'>
                    <div class='label-triangle' style='border-bottom-color: ${entry.color}'></div>
                    <span style='color: white; font-weight: bold;'>${prons}</span>
                </div>
             `;
             
             L.marker([lat, lng], {
                 icon: L.divIcon({
                     className: 'divIconDefault',
                     html: html,
                     iconSize: [60, 40] // estimate
                 })
             }).addTo(map);
         }
    });
};

onMounted(() => {
    // Check if L exists, if not wait or load?
    // We added CDN to index.html ? Not yet.
    if (window.L) {
        initMap();
    } else {
        // Simple polling or event waiting
        const timer = setInterval(() => {
            if (window.L) {
                initMap();
                clearInterval(timer);
            }
        }, 100);
    }
});

watch(() => props.locations, () => {
    initMap();
});

</script>

<template>
  <div class="w-full h-[400px] rounded-xl overflow-hidden shadow-sm border border-slate-200 dark:border-slate-700 bg-slate-100">
      <div ref="mapContainer" class="w-full h-full z-0"></div>
  </div>
</template>

<style>
/* Leaflet Global Styles needed for markers */
.locale-label {
    padding: 2px 6px;
    border-radius: 4px;
    position: relative;
    text-align: center;
    white-space: nowrap;
    box-shadow: 0 1px 3px rgba(0,0,0,0.2);
}
.label-triangle {
    position: absolute;
    bottom: -5px;
    left: 50%;
    transform: translateX(-50%);
    width: 0;
    height: 0;
    border-left: 5px solid transparent;
    border-right: 5px solid transparent;
    border-bottom: 5px solid; /* set color inline */
    transform: translateX(-50%) rotate(180deg);
}
</style>
